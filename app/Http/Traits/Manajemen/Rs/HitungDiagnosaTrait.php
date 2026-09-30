<?php

namespace App\Http\Traits\Manajemen\Rs;

use App\Support\OracleLob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Hitung jumlah kasus satu diagnosa ICD-10 di RJ, UGD, dan RI pada rentang tanggal.
 *
 * Satu kunjungan bisa punya TIGA versi diagnosa yang isinya berbeda, jadi sumbernya
 * dipilih pengguna ($sumber):
 *
 *   'emr'    — tabel detail relasional (dual-write EMR), tanpa primer/sekunder:
 *                RJ  : rstxn_rjdtls.rj_no    → rstxn_rjhdrs
 *                UGD : rstxn_ugddtls.rj_no   → rstxn_ugdhdrs
 *                RI  : rstxn_ridtls.rihdr_no → rstxn_rihdrs
 *              Kode dicocokkan lewat rsmst_mstdiags.icdx, bukan diag_id: satu icdx bisa
 *              punya dua baris master (seed `I10` + legacy `I10X`).
 *   'idrg'   — versi klaim coder iDRG, JSON `idrg.coderDiagnosa[]` (code = icdx, kategori).
 *   'inacbg' — versi klaim coder INACBG, JSON `idrg.coderInacbgDiagnosa[]`.
 *              Keduanya diisi di Casemix; baru terisi sejak ±Mei 2026 dan bisa berbeda
 *              total dari EMR (contoh RI 61495: EMR kosong, coder A90 + A49.9).
 *
 * Tanggal periode: RJ & UGD rj_date, RI exit_date (tanggal pulang, selaras Laporan
 * Kunjungan RI — pasien yang masih dirawat belum terhitung). Kode kategori (E11) ikut
 * menghitung seluruh sub-kodenya (E11.0–E11.9). Pasien Kronis (klaim_id 'KR') dan
 * kunjungan batal (status F) dikeluarkan.
 */
trait HitungDiagnosaTrait
{
    /** Sumber diagnosa yang didukung → label tampilan. */
    public const SUMBER_DIAGNOSA = [
        'emr'    => 'EMR',
        'idrg'   => 'Klaim iDRG',
        'inacbg' => 'Klaim INACBG',
    ];

    /** Kunci JSON coder per sumber klaim, di bawah node `idrg`. */
    private const KUNCI_CODER = [
        'idrg'   => 'coderDiagnosa',
        'inacbg' => 'coderInacbgDiagnosa',
    ];

    /** Konfigurasi per jalur: tabel detail, tabel header, kunci, kolom tanggal, JSON, syarat aktif. */
    private function konfigurasiJalurDiagnosa(): array
    {
        return [
            'RJ' => [
                'label'       => 'Rawat Jalan',
                'tabelDetail' => 'rstxn_rjdtls',
                'tabelHeader' => 'rstxn_rjhdrs',
                'kunci'       => 'rj_no',
                'tanggal'     => 'rj_date',
                'json'        => 'datadaftarpolirj_json',
                'syaratAktif' => "NVL(h.rj_status,'A') <> 'F'",
            ],
            'UGD' => [
                'label'       => 'UGD',
                'tabelDetail' => 'rstxn_ugddtls',
                'tabelHeader' => 'rstxn_ugdhdrs',
                'kunci'       => 'rj_no',
                'tanggal'     => 'rj_date',
                'json'        => 'datadaftarugd_json',
                'syaratAktif' => "NVL(h.rj_status,'A') <> 'F'",
            ],
            'RI' => [
                'label'       => 'Rawat Inap',
                'tabelDetail' => 'rstxn_ridtls',
                'tabelHeader' => 'rstxn_rihdrs',
                'kunci'       => 'rihdr_no',
                'tanggal'     => 'exit_date',
                'json'        => 'datadaftarri_json',
                'syaratAktif' => "NVL(h.ri_status,'-') <> 'F'",
            ],
        ];
    }

    /**
     * @return array{jalur: array, total: array, perKode: array}
     *   jalur[] / total: kunjungan, pasien, laki, perempuan, primer (null untuk sumber 'emr').
     */
    protected function hitungDiagnosa(string $icdx, Carbon $start, Carbon $end, string $sumber = 'emr'): array
    {
        if (!array_key_exists($sumber, self::SUMBER_DIAGNOSA)) {
            throw new \InvalidArgumentException("Sumber diagnosa tidak dikenal: {$sumber}");
        }

        // Kumpulan kunjungan per jalur: [kunci kunjungan => ['reg_no','sex','primer','kode'=>[icdx=>desc]]]
        $kunjunganPerJalur = [];
        foreach ($this->konfigurasiJalurDiagnosa() as $kodeJalur => $konfigurasi) {
            $kunjunganPerJalur[$kodeJalur] = $this->kunjunganJalur($konfigurasi, $icdx, $start, $end, $sumber);
        }

        return $this->rekapKunjungan($kunjunganPerJalur, $sumber !== 'emr');
    }

    /** Kumpulan kunjungan satu jalur untuk satu sumber (dipakai hitung & daftar). */
    private function kunjunganJalur(array $konfigurasi, string $icdx, Carbon $start, Carbon $end, string $sumber): array
    {
        return $sumber === 'emr'
            ? $this->kunjunganDariTabelDetail($konfigurasi, $icdx, $start, $end)
            : $this->kunjunganDariCoderKlaim($konfigurasi, self::KUNCI_CODER[$sumber], $icdx, $start, $end);
    }

    /**
     * Daftar kunjungan satu jalur (isi modal daftar pasien) — kunjungan yang sama
     * persis dengan angka di tabel, lalu dilengkapi identitas & data kunjungan.
     * Query identitas dipisah dari query hitung supaya hitungan tetap ringan.
     *
     * @return array<int, array> urut tanggal terbaru dulu
     */
    protected function daftarKunjunganDiagnosa(string $icdx, Carbon $start, Carbon $end, string $sumber, string $jalur): array
    {
        $konfigurasiList = $this->konfigurasiJalurDiagnosa();
        if (!array_key_exists($jalur, $konfigurasiList)) {
            throw new \InvalidArgumentException("Jalur tidak dikenal: {$jalur}");
        }
        if (!array_key_exists($sumber, self::SUMBER_DIAGNOSA)) {
            throw new \InvalidArgumentException("Sumber diagnosa tidak dikenal: {$sumber}");
        }

        $konfigurasi = $konfigurasiList[$jalur];
        $kunjunganList = $this->kunjunganJalur($konfigurasi, $icdx, $start, $end, $sumber);
        if (count($kunjunganList) === 0) {
            return [];
        }

        $kunci = 'h.' . $konfigurasi['kunci'];
        $kolom = [
            $kunci . ' as kunjungan_no', 'h.reg_no', 'p.reg_name', 'p.sex', 'p.address',
            DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
            DB::raw("to_char(h.{$konfigurasi['tanggal']},'yyyymmddhh24miss') as tanggal_urut"),
            DB::raw("to_char(h.{$konfigurasi['tanggal']},'dd/mm/yyyy hh24:mi') as tanggal_tampil"),
            'dok.dr_name', 'k.klaim_desc', 'h.vno_sep',
        ];
        if ($jalur === 'RI') {
            $kolom[] = DB::raw("to_char(h.entry_date,'dd/mm/yyyy hh24:mi') as masuk_tampil");
            // DPJP RI dibaca dari Leveling Dokter di JSON, bukan rihdrs.dr_id (dokter admisi).
            $kolom[] = 'h.' . $konfigurasi['json'] . ' as isi_json';
        } else {
            $kolom[] = 'po.poli_desc';
        }

        // Oracle membatasi IN (...) 1.000 elemen → dipecah per 900.
        $detailList = [];
        foreach (array_chunk(array_keys($kunjunganList), 900) as $potongan) {
            $query = DB::table($konfigurasi['tabelHeader'] . ' as h')
                ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
                ->leftJoin('rsmst_doctors as dok', 'dok.dr_id', '=', 'h.dr_id')
                ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
                ->whereIn($kunci, $potongan);
            if ($jalur !== 'RI') {
                $query->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id');
            }
            foreach ($query->select($kolom)->get() as $row) {
                $detailList[(string) $row->kunjungan_no] = $row;
            }
        }

        $daftar = [];
        foreach ($kunjunganList as $nomor => $kunjungan) {
            $detail = $detailList[(string) $nomor] ?? null;
            $levelingDokterList = $jalur === 'RI' && $detail !== null
                ? $this->levelingDokterRI($detail->isi_json ?? null, $konfigurasi, (string) $nomor)
                : [];
            $kodeList = [];
            foreach ($kunjungan['kode'] as $kode => $deskripsi) {
                $kodeList[] = ['kode' => (string) $kode, 'desc' => $deskripsi, 'primer' => (bool) ($kunjungan['kodePrimer'][$kode] ?? false)];
            }

            $daftar[] = [
                'kunjunganNo'   => (string) $nomor,
                'regNo'         => (string) ($detail->reg_no ?? $kunjungan['reg_no']),
                'nama'          => (string) ($detail->reg_name ?? ''),
                'sex'           => (string) ($detail->sex ?? $kunjungan['sex']),
                'tglLahir'      => (string) ($detail->birth_date ?? ''),
                'alamat'        => (string) ($detail->address ?? ''),
                'tanggalUrut'   => (string) ($detail->tanggal_urut ?? ''),
                'tanggalTampil' => (string) ($detail->tanggal_tampil ?? ''),
                'masukTampil'   => (string) ($detail->masuk_tampil ?? ''),
                // RJ/UGD: dokter pemeriksa. RI: dokter penerima (admisi) — DPJP ada di levelingDokterList.
                'dokter'        => (string) ($detail->dr_name ?? ''),
                'levelingDokterList' => $levelingDokterList,
                'poli'          => (string) ($detail->poli_desc ?? ''),
                'penjamin'      => (string) ($detail->klaim_desc ?? ''),
                'sep'           => (string) ($detail->vno_sep ?? ''),
                'kodeList'      => $kodeList,
            ];
        }

        usort($daftar, fn($a, $b) => strcmp($b['tanggalUrut'], $a['tanggalUrut']));

        return $daftar;
    }

    /**
     * Leveling Dokter RI (`pengkajianAwalPasienRawatInap.levelingDokter[]`) apa adanya,
     * urut sesuai isian — ditampilkan seperti Daftar RI: nama + (Utama/Rawat Gabung).
     *
     * @return array<int, array{drName: string, levelDokter: string}>
     */
    private function levelingDokterRI(mixed $isiJson, array $konfigurasi, string $nomor): array
    {
        $dataDaftarRi = json_decode(
            OracleLob::read($isiJson, $konfigurasi['tabelHeader'], $konfigurasi['kunci'], $nomor, $konfigurasi['json']),
            true,
        );

        $levelingDokterList = [];
        foreach ($dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [] as $dokterLeveling) {
            if (!is_array($dokterLeveling) || trim((string) ($dokterLeveling['drName'] ?? '')) === '') {
                continue;
            }
            $levelingDokterList[] = [
                'drName'      => trim((string) $dokterLeveling['drName']),
                'levelDokter' => (string) ($dokterLeveling['levelDokter'] ?? ''),
            ];
        }

        return $levelingDokterList;
    }

    /** Header yang aktif dalam periode — dipakai kedua jenis sumber. */
    private function queryHeaderAktif(array $konfigurasi, Carbon $start, Carbon $end)
    {
        return DB::table($konfigurasi['tabelHeader'] . ' as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->whereBetween('h.' . $konfigurasi['tanggal'], [$start, $end])
            ->whereRaw("NVL(h.klaim_id,'-') <> 'KR'")
            ->whereRaw($konfigurasi['syaratAktif']);
    }

    /** Sumber EMR: baris rstxn_*dtls yang icdx master-nya cocok. */
    private function kunjunganDariTabelDetail(array $konfigurasi, string $icdx, Carbon $start, Carbon $end): array
    {
        $kunci = 'h.' . $konfigurasi['kunci'];

        $rows = $this->queryHeaderAktif($konfigurasi, $start, $end)
            ->join($konfigurasi['tabelDetail'] . ' as d', 'd.' . $konfigurasi['kunci'], '=', $kunci)
            ->join('rsmst_mstdiags as m', 'm.diag_id', '=', 'd.diag_id')
            ->where(fn($subQuery) => $subQuery->where('m.icdx', $icdx)->orWhere('m.icdx', 'like', $icdx . '.%'))
            ->select([$kunci . ' as kunjungan_no', 'h.reg_no', 'p.sex', 'm.icdx', 'm.diag_desc'])
            ->get();

        $kunjunganList = [];
        foreach ($rows as $row) {
            $nomor = (string) $row->kunjungan_no;
            $kunjunganList[$nomor] ??= ['reg_no' => (string) $row->reg_no, 'sex' => (string) $row->sex, 'primer' => false, 'kode' => []];
            $kunjunganList[$nomor]['kode'][(string) $row->icdx] ??= (string) $row->diag_desc;
        }

        return $kunjunganList;
    }

    /**
     * Sumber klaim: JSON coder iDRG/INACBG. CLOB disaring dulu di Oracle dengan
     * dbms_lob.instr (kunci coder + potongan `"code":"<kode>`), baru dicocokkan persis
     * di PHP — JSON_VALUE tidak tersedia di Oracle ini.
     */
    private function kunjunganDariCoderKlaim(array $konfigurasi, string $kunciCoder, string $icdx, Carbon $start, Carbon $end): array
    {
        $kolomJson = 'h.' . $konfigurasi['json'];

        $rows = $this->queryHeaderAktif($konfigurasi, $start, $end)
            ->whereRaw("dbms_lob.instr({$kolomJson}, ?) > 0", ['"' . $kunciCoder . '"'])
            ->whereRaw("dbms_lob.instr({$kolomJson}, ?) > 0", ['"code":"' . $icdx])
            ->select(['h.' . $konfigurasi['kunci'] . ' as kunjungan_no', 'h.reg_no', 'p.sex', $kolomJson . ' as isi_json'])
            ->get();

        $kunjunganList = [];
        foreach ($rows as $row) {
            $isiJson = json_decode(
                OracleLob::read($row->isi_json, $konfigurasi['tabelHeader'], $konfigurasi['kunci'], $row->kunjungan_no, $konfigurasi['json']),
                true,
            );
            $coderList = $isiJson['idrg'][$kunciCoder] ?? null;
            if (!is_array($coderList)) {
                continue;
            }

            foreach ($coderList as $entri) {
                $kode = trim((string) ($entri['code'] ?? ''));
                if ($kode !== $icdx && !str_starts_with($kode, $icdx . '.')) {
                    continue;
                }

                $nomor = (string) $row->kunjungan_no;
                $kunjunganList[$nomor] ??= ['reg_no' => (string) $row->reg_no, 'sex' => (string) $row->sex, 'primer' => false, 'kode' => []];
                $kunjunganList[$nomor]['kode'][$kode] ??= (string) ($entri['desc'] ?? '');
                if (($entri['kategori'] ?? '') === 'Primary') {
                    $kunjunganList[$nomor]['primer'] = true;
                    $kunjunganList[$nomor]['kodePrimer'][$kode] = true;
                }
            }
        }

        return $kunjunganList;
    }

    /** Kunjungan terkumpul → angka per jalur, total, dan rincian per kode. */
    private function rekapKunjungan(array $kunjunganPerJalur, bool $adaKategori): array
    {
        $jalurList = [];
        $perKode = [];
        $pasienSemua = [];

        foreach ($this->konfigurasiJalurDiagnosa() as $kodeJalur => $konfigurasi) {
            $kunjunganList = $kunjunganPerJalur[$kodeJalur];
            $pasienJalur = [];
            $angka = ['laki' => 0, 'perempuan' => 0, 'primer' => 0];

            foreach ($kunjunganList as $kunjungan) {
                $pasienJalur[$kunjungan['reg_no']] = true;
                $pasienSemua[$kunjungan['reg_no']] = true;
                $angka['laki'] += $kunjungan['sex'] === 'L' ? 1 : 0;
                $angka['perempuan'] += $kunjungan['sex'] === 'P' ? 1 : 0;
                $angka['primer'] += $kunjungan['primer'] ? 1 : 0;

                foreach ($kunjungan['kode'] as $kode => $deskripsi) {
                    $perKode[$kode] ??= ['icdx' => $kode, 'diag_desc' => $deskripsi, 'RJ' => 0, 'UGD' => 0, 'RI' => 0];
                    $perKode[$kode][$kodeJalur]++;
                }
            }

            $jalurList[] = [
                'jalur'     => $kodeJalur,
                'label'     => $konfigurasi['label'],
                'kunjungan' => count($kunjunganList),
                'pasien'    => count($pasienJalur),
                'laki'      => $angka['laki'],
                'perempuan' => $angka['perempuan'],
                'primer'    => $adaKategori ? $angka['primer'] : null,
            ];
        }

        $perKode = array_values($perKode);
        usort($perKode, fn($a, $b) => strcmp($a['icdx'], $b['icdx']));

        return [
            'jalur'   => $jalurList,
            'total'   => [
                'kunjungan' => array_sum(array_column($jalurList, 'kunjungan')),
                // Pasien yang sama di RJ dan RI dihitung sekali.
                'pasien'    => count($pasienSemua),
                'laki'      => array_sum(array_column($jalurList, 'laki')),
                'perempuan' => array_sum(array_column($jalurList, 'perempuan')),
                'primer'    => $adaKategori ? array_sum(array_column($jalurList, 'primer')) : null,
            ],
            'perKode' => $perKode,
        ];
    }
}
