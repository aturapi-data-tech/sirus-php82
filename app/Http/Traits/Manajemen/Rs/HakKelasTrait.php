<?php

namespace App\Http\Traits\Manajemen\Rs;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

/**
 * Laporan Kunjungan per Hak Kelas Rawat BPJS — SATU sumber untuk RJ, UGD, dan RI.
 * Komponen pemakai cukup mengisi konfigurasiHakKelas(); state Livewire (tab & tahun), query,
 * dan turunannya ada di sini. Tampilan ditanam di tiap halaman laporan-hak-kelas-{rj,ugd,ri}
 * (tiga salinan, disengaja — ikut pola repo: tiap jalur punya berkasnya sendiri).
 *
 * Sumber hak kelas: JSON kunjungan pada path
 *   sep.reqSep.request.t_sep.klsRawat.klsRawatHak   → '1' | '2' | '3'
 * Diisi saat pembuatan SEP (vclaim-{rj,ugd,ri}-actions) dari hak kelas peserta hasil cek
 * kepesertaan BPJS. sep.resSep.peserta.hakKelas TIDAK dipakai — di RI terbukti selalu kosong.
 *
 * Oracle di repo ini tidak punya JSON_VALUE, jadi nilainya diambil dengan INSTR + SUBSTR pada
 * CLOB: cari penanda, ambil 4 karakter sesudahnya, buang tanda kutip & spasi depan, pakai
 * karakter pertama. Tahan terhadap nilai string ("1"), angka (1), maupun spasi sesudah titik dua.
 * Nilai kosong / bukan 1-2-3 digolongkan "tidak terbaca" — dilaporkan sebagai peringatan, bukan ditebak.
 *
 * Konvensi:
 *   - Pasien Kronis (klaim_id='KR') dan kunjungan batal (status F) dikeluarkan.
 *   - BPJS = klaim_status='BPJS' OR klaim_id='JM' (JKN Mobile). Hak kelas hanya dibaca untuk BPJS.
 *   - Tiap kunjungan BPJS jatuh tepat di satu kelompok:
 *       kelas 1 | kelas 2 | kelas 3 | ber-SEP tapi hak kelas tak terbaca | belum ber-SEP
 *
 * Kunci konfigurasiHakKelas():
 *   tabel, kunci (PK kunjungan), json (kolom CLOB), tanggal (kolom periode), syaratAktif (SQL, alias h),
 *   grupSelect (SQL "… as grup_id, … as grup_nama", alias h + alias join), grupJoin (callable $query),
 *   labelGrupKosong.
 * klsRawatNaik (naik_kelas) selalu ikut dihitung; hanya halaman RI yang menampilkannya.
 */
trait HakKelasTrait
{
    /**
     * Rincian waktu:
     *   - 'bulanan' → tab "Tahunan"     (1 tahun, baris per bulan)
     *   - 'tahunan' → tab "Multi-Tahun" (rentang tahun, baris per tahun)
     */
    public string $mode = 'bulanan';

    // Tanpa tipe int: kotak isian yang dikosongkan mengirim '' dan properti bertipe int melempar TypeError.
    public $filterTahun;
    public $tahunFrom;
    public $tahunTo;

    abstract protected function konfigurasiHakKelas(): array;

    /** Hook mount milik trait (konvensi Livewire: mount + NamaTrait). */
    public function mountHakKelasTrait(): void
    {
        $tahunIni = Carbon::now(config('app.timezone'))->year;
        $this->filterTahun = $tahunIni;
        $this->tahunFrom = $tahunIni - 4;
        $this->tahunTo = $tahunIni;
    }

    public function setMode(string $mode): void
    {
        if (in_array($mode, ['bulanan', 'tahunan'], true)) {
            $this->mode = $mode;
        }
    }

    /** Tahun dari kotak isian, dijepit ke rentang wajar; isian ngawur jatuh ke tahun berjalan. */
    private function tahunAman($nilai): int
    {
        $tahun = (int) $nilai;

        return $tahun >= 2000 && $tahun <= 2099 ? $tahun : Carbon::now(config('app.timezone'))->year;
    }

    /** [tahun awal, tahun akhir] periode terpilih. */
    public function rentangTahun(): array
    {
        if ($this->mode === 'bulanan') {
            $tahun = $this->tahunAman($this->filterTahun);

            return [$tahun, $tahun];
        }

        $dari = $this->tahunAman($this->tahunFrom);
        $sampai = $this->tahunAman($this->tahunTo);

        return [min($dari, $sampai), max($dari, $sampai)];
    }

    private function periodeRange(): array
    {
        [$dari, $sampai] = $this->rentangTahun();

        return [Carbon::create($dari, 1, 1)->startOfYear(), Carbon::create($sampai, 12, 31)->endOfYear()];
    }

    /* ===============================
     | SQL
     =============================== */

    /** Karakter pertama nilai sebuah kunci JSON ('1', '2', …) atau NULL bila kuncinya tak ada. Hanya dievaluasi untuk BPJS. */
    protected function ekspresiNilaiJsonSql(string $kunciJson): string
    {
        $penanda = '"' . $kunciJson . '":';
        $geser = strlen($penanda);
        $json = 'h.' . $this->konfigurasiHakKelas()['json'];

        return "CASE WHEN (k.klaim_status='BPJS' OR h.klaim_id='JM') AND INSTR({$json}, '{$penanda}') > 0"
            . " THEN SUBSTR(LTRIM(REPLACE(TO_CHAR(SUBSTR({$json}, INSTR({$json}, '{$penanda}') + {$geser}, 4)), '\"', '')), 1, 1) END";
    }

    /**
     * Satu baris per kunjungan dengan hak kelas yang sudah diurai — dipakai sebagai subquery supaya
     * pencarian di CLOB dijalankan SEKALI per kunjungan, bukan sekali per kolom agregat.
     * $denganGrup = true menambahkan grup_id / grup_nama (poli, dokter, atau bangsal).
     */
    protected function kunjunganHakKelasQuery($start, $end, bool $denganGrup = false)
    {
        $konfigurasi = $this->konfigurasiHakKelas();
        $kolom = "h.{$konfigurasi['kunci']} as kunjungan_no, h.reg_no, h.{$konfigurasi['tanggal']} as tgl_periode, h.vno_sep, "
            . "CASE WHEN k.klaim_status='BPJS' OR h.klaim_id='JM' THEN 1 ELSE 0 END as is_bpjs, "
            . $this->ekspresiNilaiJsonSql('klsRawatHak') . ' as hak_kelas, '
            . $this->ekspresiNilaiJsonSql('klsRawatNaik') . ' as kelas_naik';

        $query = DB::table($konfigurasi['tabel'] . ' as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id');

        if ($denganGrup) {
            $konfigurasi['grupJoin']($query);
            $kolom .= ', ' . $konfigurasi['grupSelect'];
        }

        return $query
            ->selectRaw($kolom)
            ->whereBetween('h.' . $konfigurasi['tanggal'], [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->whereRaw($konfigurasi['syaratAktif']);
    }

    /** Kolom agregat yang sama untuk rekap per periode maupun per grup. */
    private function kolomAgregatHakKelas(): array
    {
        $takTerbaca = "d.is_bpjs = 1 AND NVL(d.hak_kelas,'-') NOT IN ('1','2','3')";

        return [
            DB::raw('COUNT(DISTINCT d.kunjungan_no) as total'),
            DB::raw('COUNT(DISTINCT d.reg_no) as pasien_unik'),
            DB::raw('SUM(d.is_bpjs) as bpjs'),
            DB::raw('SUM(1 - d.is_bpjs) as non_bpjs'),
            DB::raw("SUM(CASE WHEN d.is_bpjs = 1 AND d.hak_kelas = '1' THEN 1 ELSE 0 END) as kelas1"),
            DB::raw("SUM(CASE WHEN d.is_bpjs = 1 AND d.hak_kelas = '2' THEN 1 ELSE 0 END) as kelas2"),
            DB::raw("SUM(CASE WHEN d.is_bpjs = 1 AND d.hak_kelas = '3' THEN 1 ELSE 0 END) as kelas3"),
            // klsRawatNaik terisi kode kelas tujuan (angka) bila peserta naik kelas atas biaya sendiri
            DB::raw("SUM(CASE WHEN d.is_bpjs = 1 AND d.kelas_naik BETWEEN '1' AND '9' THEN 1 ELSE 0 END) as naik_kelas"),
            // Peringatan: BPJS yang hak kelasnya tidak bisa dipastikan
            DB::raw("SUM(CASE WHEN {$takTerbaca} AND d.vno_sep IS NOT NULL THEN 1 ELSE 0 END) as sep_tak_terbaca"),
            DB::raw("SUM(CASE WHEN {$takTerbaca} AND d.vno_sep IS NULL THEN 1 ELSE 0 END) as belum_sep"),
        ];
    }

    /** Rekap per periode — $formatPeriode 'MM' atau 'YYYY'. */
    protected function buildHakKelasAggregate($start, $end, string $formatPeriode)
    {
        $groupSql = "to_char(d.tgl_periode, '" . ($formatPeriode === 'YYYY' ? 'YYYY' : 'MM') . "')";

        return DB::query()
            ->fromSub($this->kunjunganHakKelasQuery($start, $end), 'd')
            ->select(array_merge([DB::raw("{$groupSql} as periode")], $this->kolomAgregatHakKelas()))
            ->groupBy(DB::raw($groupSql))
            ->orderBy(DB::raw($groupSql))
            ->get()
            ->keyBy('periode');
    }

    /* ===============================
     | DATA LAYAR
     =============================== */

    #[Computed]
    public function rows(): array
    {
        [$start, $end] = $this->periodeRange();
        [$dari, $sampai] = $this->rentangTahun();
        $rows = [];

        if ($this->mode === 'bulanan') {
            $aggregate = $this->buildHakKelasAggregate($start, $end, 'MM');
            for ($bulan = 1; $bulan <= 12; $bulan++) {
                $kunci = str_pad((string) $bulan, 2, '0', STR_PAD_LEFT);
                $rows[] = $this->fillHakKelasRow($aggregate->get($kunci), $this->bulanLabelHakKelas($bulan));
            }

            return $rows;
        }

        $aggregate = $this->buildHakKelasAggregate($start, $end, 'YYYY');
        for ($tahun = $dari; $tahun <= $sampai; $tahun++) {
            $rows[] = $this->fillHakKelasRow($aggregate->get((string) $tahun), (string) $tahun);
        }

        return $rows;
    }

    #[Computed]
    public function totals(): array
    {
        $rows = $this->rows;
        $jumlah = fn(string $kunci) => array_sum(array_column($rows, $kunci));

        return $this->lengkapiHakKelasRow([
            'label'           => 'TOTAL',
            'total'           => $jumlah('total'),
            'pasien_unik'     => $jumlah('pasien_unik'),
            'bpjs'            => $jumlah('bpjs'),
            'non_bpjs'        => $jumlah('non_bpjs'),
            'kelas1'          => $jumlah('kelas1'),
            'kelas2'          => $jumlah('kelas2'),
            'kelas3'          => $jumlah('kelas3'),
            'naik_kelas'      => $jumlah('naik_kelas'),
            'sep_tak_terbaca' => $jumlah('sep_tak_terbaca'),
            'belum_sep'       => $jumlah('belum_sep'),
        ]);
    }

    #[Computed]
    public function pasienUnikGlobal(): int
    {
        [$start, $end] = $this->periodeRange();
        $konfigurasi = $this->konfigurasiHakKelas();

        return DB::table($konfigurasi['tabel'] . ' as h')
            ->whereBetween('h.' . $konfigurasi['tanggal'], [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->whereRaw($konfigurasi['syaratAktif'])
            ->distinct()
            ->count('h.reg_no');
    }

    /** Rekap per grup (poli / dokter / bangsal), terbanyak BPJS di atas. */
    #[Computed]
    public function grupBreakdown(): array
    {
        [$start, $end] = $this->periodeRange();
        $labelKosong = (string) $this->konfigurasiHakKelas()['labelGrupKosong'];

        return DB::query()
            ->fromSub($this->kunjunganHakKelasQuery($start, $end, true), 'd')
            ->select(array_merge(['d.grup_id', DB::raw('MAX(d.grup_nama) as grup_nama')], $this->kolomAgregatHakKelas()))
            ->groupBy('d.grup_id')
            ->get()
            ->map(fn($row) => $this->fillHakKelasRow($row, (string) ($row->grup_nama ?? $labelKosong)))
            ->sortByDesc('bpjs')
            ->values()
            ->all();
    }

    protected function fillHakKelasRow(?object $row, string $label): array
    {
        return $this->lengkapiHakKelasRow([
            'label'           => $label,
            'total'           => (int) ($row->total ?? 0),
            'pasien_unik'     => (int) ($row->pasien_unik ?? 0),
            'bpjs'            => (int) ($row->bpjs ?? 0),
            'non_bpjs'        => (int) ($row->non_bpjs ?? 0),
            'kelas1'          => (int) ($row->kelas1 ?? 0),
            'kelas2'          => (int) ($row->kelas2 ?? 0),
            'kelas3'          => (int) ($row->kelas3 ?? 0),
            'naik_kelas'      => (int) ($row->naik_kelas ?? 0),
            'sep_tak_terbaca' => (int) ($row->sep_tak_terbaca ?? 0),
            'belum_sep'       => (int) ($row->belum_sep ?? 0),
        ]);
    }

    /** Turunan: jumlah yang hak kelasnya terbaca + persentase tiap kelas TERHADAP yang terbaca. */
    protected function lengkapiHakKelasRow(array $row): array
    {
        $terbaca = $row['kelas1'] + $row['kelas2'] + $row['kelas3'];
        $persen = fn(int $jumlah) => $terbaca > 0 ? round($jumlah / $terbaca * 100, 1) : null;

        $row['terbaca'] = $terbaca;
        $row['persen1'] = $persen($row['kelas1']);
        $row['persen2'] = $persen($row['kelas2']);
        $row['persen3'] = $persen($row['kelas3']);

        return $row;
    }

    protected function bulanLabelHakKelas(int $bulan): string
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$bulan] ?? (string) $bulan;
    }
}
