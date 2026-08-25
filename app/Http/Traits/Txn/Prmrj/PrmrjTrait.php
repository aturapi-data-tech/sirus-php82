<?php

namespace App\Http\Traits\Txn\Prmrj;

use App\Support\OracleLob;
use App\Support\Options\PrmrjOptions;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Helper bersama modul PRMRJ — Profil Ringkas Medis Rawat Jalan (formulir RM.06).
 *
 * Ringkasan medis pasien rawat jalan berkondisi kompleks, ditempatkan paling atas
 * berkas rekam medis supaya mudah ditelusuri & direview. Diidentifikasi dan
 * dilengkapi DPJP Utama.
 *
 * SATU BARIS PER KUNJUNGAN. Formulir RM.06 yang berisi banyak baris itu DIRAKIT
 * saat tampil/cetak dari semua baris milik REG_NO — bukan disimpan sebagai satu
 * CLOB per pasien. Alasan & rancangan kolom: docs/ddl-prmrj.sql.
 *
 * ISI DIPISAH TIGA:
 *   kriteria  — toggle yang dicentang petugas (SPO poin 2)
 *   otomatis  — SNAPSHOT isi EMR kunjungan saat PRMRJ disimpan
 *   manual    — yang tak punya padanan di EMR (obat khusus)
 *
 * Kenapa snapshot, bukan baca ulang saat cetak: RM.06 dokumen bertanda tangan.
 * Isinya harus sama dengan yang dilihat DPJP saat meneken, walau EMR-nya kelak
 * dikoreksi. Ini pola SNAPSHOT, bukan versioning — lihat skill clause-versioning.
 *
 * Cara baca/tulis CLOB-nya mencontoh App\Http\Traits\Txn\Rj\EmrRJTrait:
 * OracleLob untuk baca, JSON_THROW_ON_ERROR untuk decode, lockForUpdate sebelum
 * read-modify-write, dan bendera encode yang sama.
 */
trait PrmrjTrait
{
    /** Pasien kronis bisa punya puluhan kunjungan; formulir tak perlu memuat semuanya sekaligus. */
    protected const BATAS_RIWAYAT_PRMRJ = 50;

    /** Bendera encode SAMA dengan updateJsonRJ — termasuk UNESCAPED_SLASHES. */
    protected const JSON_FLAGS_PRMRJ = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * PRMRJ milik SATU kunjungan. Disaring lewat REG_NO (terindeks), lalu
     * dicocokkan di PHP karena nomor kunjungannya ada di dalam CLOB.
     *
     * @return array{0: ?object, 1: array} baris + isi JSON-nya
     */
    protected function findDataPrmrj(string $regNo, string $jenisKunjungan, int $nomorKunjungan): array
    {
        if (blank($regNo)) {
            return [null, []];
        }

        foreach (DB::table('rstxn_prmrjs')->where('reg_no', $regNo)->get() as $row) {
            $payload = $this->readJsonPrmrj($row);

            if (($payload['kunjungan']['jenis'] ?? '') === $jenisKunjungan
                && (int) ($payload['kunjungan']['no'] ?? 0) === $nomorKunjungan) {
                return [$row, $payload];
            }
        }

        return [null, []];
    }

    /**
     * Lock baris PRMRJ (SELECT FOR UPDATE) — mencontoh lockRJRow.
     * Wajib DI DALAM DB::transaction, sebelum read-modify-write PRMRJ_JSON.
     *
     * @throws \RuntimeException bila barisnya tak ada
     */
    protected function lockPrmrjRow(int $prmrjNo): void
    {
        $exists = DB::table('rstxn_prmrjs')
            ->where('prmrj_no', $prmrjNo)
            ->lockForUpdate()
            ->exists();

        if (! $exists) {
            throw new \RuntimeException("Baris PRMRJ #{$prmrjNo} tidak ditemukan untuk di-lock.");
        }
    }

    /**
     * Simpan (buat atau perbarui) PRMRJ satu kunjungan.
     *
     * ⚠️ Tidak membungkus DB::transaction sendiri agar tidak membuat nested
     *    transaction di caller. Selalu panggil DI DALAM DB::transaction.
     *
     * Node 'dibuat' dipertahankan dari baris lama supaya pembuat pertama tak
     * tertimpa tiap kali draft disimpan ulang.
     *
     * @throws \JsonException|\RuntimeException
     */
    protected function updateJsonPrmrj(string $regNo, string $jenisKunjungan, int $nomorKunjungan, array $payload): void
    {
        // regNo kosong = INSERT pasti melanggar NOT NULL. Ditolak di sini supaya
        // pesannya terbaca, bukan jadi ORA-01400 dari lapisan bawah.
        if (blank($regNo)) {
            throw new \RuntimeException('Nomor RM kosong — PRMRJ tak bisa disimpan.');
        }

        // Whitelist, bukan if/else: jenis di luar dugaan tidak boleh diam-diam
        // jatuh ke cabang mana pun (lihat skill naming-conventions §4).
        if (! in_array($jenisKunjungan, ['RJ', 'RI'], true)) {
            throw new \RuntimeException("Jenis kunjungan '{$jenisKunjungan}' tidak dikenal.");
        }

        if ($nomorKunjungan <= 0) {
            throw new \RuntimeException('Nomor kunjungan tidak sah — PRMRJ tak bisa disimpan.');
        }

        [$baris, $isiLama] = $this->findDataPrmrj($regNo, $jenisKunjungan, $nomorKunjungan);

        $payload['kunjungan'] = ['jenis' => $jenisKunjungan, 'no' => $nomorKunjungan];
        $payload['dibuat'] = $isiLama['dibuat'] ?? $payload['dibuat'] ?? null;

        $json = json_encode($payload, self::JSON_FLAGS_PRMRJ);

        if ($baris) {
            $this->lockPrmrjRow((int) $baris->prmrj_no);

            DB::table('rstxn_prmrjs')
                ->where('prmrj_no', $baris->prmrj_no)
                ->update(['prmrj_json' => $json]);

            return;
        }

        DB::table('rstxn_prmrjs')->insert([
            'prmrj_no' => DB::raw('SEQ_PRMRJS.NEXTVAL'),
            'reg_no' => $regNo,
            'prmrj_json' => $json,
        ]);
    }

    /**
     * Baca PRMRJ_JSON. CLOB dibaca lewat OracleLob (anti ORA-01555/terpotong),
     * decode dengan JSON_THROW_ON_ERROR.
     *
     * JSON rusak DILEMPAR, bukan diam-diam jadi array kosong — pada alur
     * read-modify-write, array kosong berarti menimpa isi lama dengan isian kosong.
     *
     * @throws \JsonException
     */
    protected function readJsonPrmrj(object $row): array
    {
        $json = OracleLob::read(
            $row->prmrj_json ?? null,
            'rstxn_prmrjs',
            'prmrj_no',
            $row->prmrj_no,
            'prmrj_json'
        );

        return $json === '' ? [] : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * SEMUA baris PRMRJ milik satu pasien — inilah formulir RM.06-nya, dirakit
     * dari baris-baris per kunjungan.
     *
     * Urut TERLAMA DULU: formulir kertasnya bernomor 1,2,3 dari kunjungan
     * pertama, dan pembacaannya memang kronologis.
     *
     * @return array{0: array<int, array>, 1: int} daftar (sudah dipotong) + total sebenarnya
     */
    protected function findRiwayatPrmrj(
        string $regNo,
        ?string $jenisKunjunganSekarang = null,
        ?int $nomorKunjunganSekarang = null
    ): array {
        if (blank($regNo)) {
            return [[], 0];
        }

        $daftar = [];

        foreach (DB::table('rstxn_prmrjs')->where('reg_no', $regNo)->get() as $row) {
            $payload = $this->readJsonPrmrj($row);

            if ($payload === []) {
                continue;
            }

            $otomatis = $payload['otomatis'] ?? [];
            $jenis = (string) ($payload['kunjungan']['jenis'] ?? '');
            $nomor = (int) ($payload['kunjungan']['no'] ?? 0);

            $daftar[] = [
                'prmrjNo' => (int) $row->prmrj_no,
                'jenisKunjungan' => $jenis,
                'nomorKunjungan' => $nomor,
                'tglKunjungan' => (string) ($otomatis['tglKunjungan'] ?? ''),
                'poliklinik' => (string) ($otomatis['poliklinik'] ?? ''),
                'dpjp' => (string) ($otomatis['dpjp'] ?? ''),
                'diagnosa' => (string) ($otomatis['diagnosa'] ?? ''),
                'riwayatAlergi' => (string) ($otomatis['riwayatAlergi'] ?? ''),
                'terapi' => (string) ($otomatis['terapi'] ?? ''),
                'tindakan' => (string) ($otomatis['tindakan'] ?? ''),
                'operasi' => (string) ($otomatis['operasi'] ?? ''),
                'rencanaTindakLanjut' => (string) ($otomatis['rencanaTindakLanjut'] ?? ''),
                'obatKhusus' => (string) ($payload['manual']['obatKhusus'] ?? ''),
                'kriteria' => $this->ringkasKriteriaPrmrj($payload['kriteria'] ?? []),
                'kriteriaCatatan' => (string) ($payload['kriteria']['catatan'] ?? ''),
                'ttdNama' => (string) ($payload['ttd']['nama'] ?? ''),
                'ttdTanggal' => (string) ($payload['ttd']['tanggal'] ?? ''),
                'terkunci' => (bool) ($payload['terkunci'] ?? false),
                'iniKunjunganIni' => $jenisKunjunganSekarang !== null
                    && $nomorKunjunganSekarang !== null
                    && $jenis === $jenisKunjunganSekarang
                    && $nomor === $nomorKunjunganSekarang,
            ];
        }

        // Kronologis. tglKunjungan itu teks d/m/Y H:i:s — tak bisa diurut sebagai
        // string, jadi diubah ke timestamp; yang tak terbaca jatuh ke 0 dan
        // PRMRJ_NO jadi pemutus supaya urutannya tetap pasti.
        usort($daftar, function (array $kiri, array $kanan): int {
            $waktuKiri = $this->parseWaktuPrmrj($kiri['tglKunjungan']);
            $waktuKanan = $this->parseWaktuPrmrj($kanan['tglKunjungan']);

            return $waktuKiri <=> $waktuKanan ?: $kiri['prmrjNo'] <=> $kanan['prmrjNo'];
        });

        $total = count($daftar);

        return [array_slice($daftar, 0, self::BATAS_RIWAYAT_PRMRJ), $total];
    }

    /**
     * Tiga toggle kriteria + rinciannya → daftar teks yang terbaca di kartu & cetakan.
     *
     * Label rincian diambil dari PrmrjOptions (satu sumber untuk layar & kertas);
     * kunci yang tak dikenal dibuang di sana, jadi record lama tak pernah mencetak
     * kunci mentah di formulir pasien.
     */
    protected function ringkasKriteriaPrmrj(array $kriteria): array
    {
        $daftar = [];

        if (! empty($kriteria['diagnosisKompleks'])) {
            $rincian = PrmrjOptions::labelDari('diagnosis', (array) ($kriteria['detailDiagnosis'] ?? []));

            if (filled($kriteria['detailDiagnosisLain'] ?? '')) {
                $rincian[] = $kriteria['detailDiagnosisLain'];
            }

            $daftar[] = 'Diagnosis kompleks'
                . ($rincian === [] ? '' : ': ' . implode(', ', $rincian));
        }

        if (! empty($kriteria['asuhanTigaAtauLebih'])) {
            $rincian = PrmrjOptions::labelDari('asuhan', (array) ($kriteria['detailAsuhan'] ?? []));

            if (filled($kriteria['detailAsuhanLain'] ?? '')) {
                $rincian[] = $kriteria['detailAsuhanLain'];
            }

            $daftar[] = 'Asuhan'
                . ($rincian === [] ? '' : ': ' . implode(', ', $rincian));
        }

        if (! empty($kriteria['alergiObatMdr'])) {
            $daftar[] = 'Alergi obat / multi drug resistance'
                . (filled($kriteria['detailAlergi'] ?? '') ? ': ' . $kriteria['detailAlergi'] : '');
        }

        return $daftar;
    }

    /**
     * SNAPSHOT isi EMR kunjungan RJ → node 'otomatis'.
     *
     * Menerima $dataRJ yang SUDAH dimuat caller (findDataRJ), bukan memuat sendiri
     * — supaya trait ini tak menuntut EmrRJTrait ikut dipakai, dan supaya satu
     * pemuatan CLOB dipakai bersama.
     *
     * Operasi diambil dari RSTXN_OKS lewat status_rjri='RJ' + ref_no. Hari ini
     * belum ada satu pun baris RJ di sana (5.094 RI, 1 UGD), jadi hasilnya wajar
     * kosong — bukan bug, kolomnya memang sudah siap lebih dulu.
     */
    protected function buildOtomatisPrmrj(array $dataRJ, int $rjNo): array
    {
        // Diagnosa, tindakan, dan operasi ditulis sebagai TEKS BEBAS satu baris per
        // butir — bukan daftar terstruktur. Kode digabung di depan uraiannya supaya
        // dua kolom di formulir kertas tetap terbaca dalam satu teks.
        $diagnosa = collect($dataRJ['diagnosis'] ?? [])
            ->map(fn ($baris) => trim(
                trim((string) ($baris['icdX'] ?? $baris['diagId'] ?? ''))
                . ' ' . trim((string) ($baris['diagDesc'] ?? ''))
            ))
            ->filter()
            ->implode("\n");

        $tindakan = collect($dataRJ['procedure'] ?? [])
            ->map(fn ($baris) => trim(
                trim((string) ($baris['procedureId'] ?? ''))
                . ' ' . trim((string) ($baris['procedureDesc'] ?? ''))
            ))
            ->filter()
            ->implode("\n");

        return [
            'tglKunjungan' => (string) ($dataRJ['rjDate'] ?? ''),
            'poliklinik' => (string) ($dataRJ['poliDesc'] ?? ''),
            'dpjp' => (string) ($dataRJ['drDesc'] ?? ''),
            'diagnosa' => $diagnosa,
            'riwayatAlergi' => trim((string) ($dataRJ['anamnesa']['alergi']['alergi'] ?? '')),
            'terapi' => trim((string) ($dataRJ['perencanaan']['terapi']['terapi'] ?? '')),
            'tindakan' => $tindakan,
            'operasi' => $this->findOperasiPrmrj($rjNo),   // sudah berupa teks
            'rencanaTindakLanjut' => trim((string) ($dataRJ['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '')),
        ];
    }

    /** Operasi kunjungan RJ ini dari modul Kamar Operasi, sebagai teks bebas. */
    protected function findOperasiPrmrj(int $rjNo): string
    {
        $rowList = DB::table('rstxn_oks as o')
            ->leftJoin('rsmst_okcases as c', 'c.case_id', '=', 'o.case_id')
            ->where('o.status_rjri', 'RJ')
            ->where('o.ref_no', $rjNo)
            ->select('o.ok_reg', 'c.case_name', DB::raw("to_char(o.ok_date,'dd/mm/yyyy') as ok_date"))
            ->get();

        return $rowList
            ->map(fn ($row) => trim(((string) ($row->ok_date ?? '')) . ' ' . trim((string) ($row->case_name ?? ''))))
            ->filter()
            ->implode("\n");
    }

    /** tglKunjungan ('d/m/Y H:i:s' atau 'd/m/Y') → timestamp; 0 bila tak terbaca. */
    private function parseWaktuPrmrj(string $teks): int
    {
        if (blank($teks)) {
            return 0;
        }

        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $teks)->getTimestamp();
            } catch (\Throwable) {
                continue;
            }
        }

        return 0;
    }

    /**
     * Tabel PRMRJ sudah dipasang? Dipakai komponen untuk menampilkan pesan yang
     * jelas ketimbang ORA-00942 di layar. Di-cache supaya tak query tiap render.
     */
    protected function checkPrmrjTable(): bool
    {
        return \Illuminate\Support\Facades\Cache::remember('prmrj.tabel.ada', 300, function () {
            try {
                DB::table('rstxn_prmrjs')->limit(1)->exists();

                return true;
            } catch (\Throwable) {
                return false;
            }
        });
    }
}
