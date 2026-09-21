<?php

namespace App\Http\Traits\Manajemen\Rs\Rj;

use Illuminate\Support\Facades\DB;

/**
 * Shared logic Laporan Kunjungan RJ per Hak Kelas Rawat BPJS.
 *
 * Sumber hak kelas: JSON kunjungan (rstxn_rjhdrs.datadaftarpolirj_json) pada path
 *   sep.reqSep.request.t_sep.klsRawat.klsRawatHak   → '1' | '2' | '3'
 * Diisi saat pembuatan SEP dari hak kelas peserta hasil cek kepesertaan BPJS
 * (vclaim-rj-actions: 'klsRawatHak' => $peserta['hakKelas']['kode']).
 *
 * Oracle di repo ini tidak punya JSON_VALUE, jadi nilainya diambil dengan INSTR + SUBSTR pada
 * CLOB: cari '"klsRawatHak":', ambil 4 karakter sesudahnya, buang tanda kutip & spasi depan, pakai
 * karakter pertama. Tahan terhadap nilai string ("1"), angka (1), maupun spasi sesudah titik dua.
 * Nilai kosong / bukan 1-2-3 digolongkan "tidak terbaca" — dilaporkan sebagai peringatan, bukan ditebak.
 *
 * Konvensi (selaras KunjunganRJTrait):
 *   - Periode memakai rj_date; pasien Kronis (klaim_id='KR') dikeluarkan.
 *   - Kunjungan batal (rj_status='F') dikeluarkan — bukan pelayanan.
 *   - BPJS = klaim_status='BPJS' OR klaim_id='JM' (JKN Mobile). Hak kelas hanya dibaca untuk BPJS.
 *   - Tiap kunjungan BPJS jatuh tepat di satu kelompok:
 *       kelas 1 | kelas 2 | kelas 3 | ber-SEP tapi hak kelas tak terbaca | belum ber-SEP
 */
trait HakKelasRJTrait
{
    /** Penanda di JSON; panjangnya (14) dipakai sebagai geseran SUBSTR. */
    private const PENANDA_HAK_KELAS = '"klsRawatHak":';

    /** Ekspresi SQL hak kelas satu kunjungan ('1'/'2'/'3'/lainnya/NULL). Hanya dievaluasi untuk BPJS. */
    protected function ekspresiHakKelasSql(): string
    {
        $penanda = self::PENANDA_HAK_KELAS;
        $geser = strlen($penanda);
        $json = 'h.datadaftarpolirj_json';

        return "CASE WHEN (k.klaim_status='BPJS' OR h.klaim_id='JM') AND INSTR({$json}, '{$penanda}') > 0"
            . " THEN SUBSTR(LTRIM(REPLACE(TO_CHAR(SUBSTR({$json}, INSTR({$json}, '{$penanda}') + {$geser}, 4)), '\"', '')), 1, 1) END";
    }

    /**
     * Satu baris per kunjungan dengan hak kelas yang sudah diurai — dipakai sebagai subquery supaya
     * pencarian di CLOB dijalankan SEKALI per kunjungan, bukan sekali per kolom agregat.
     */
    protected function kunjunganHakKelasQuery($start, $end)
    {
        return DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->selectRaw(
                "h.rj_no, h.reg_no, h.rj_date, h.poli_id, h.vno_sep, "
                . "CASE WHEN k.klaim_status='BPJS' OR h.klaim_id='JM' THEN 1 ELSE 0 END as is_bpjs, "
                . $this->ekspresiHakKelasSql() . ' as hak_kelas'
            )
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->whereRaw("NVL(h.rj_status,'A') <> 'F'");
    }

    /** Kolom agregat yang sama untuk rekap per periode maupun per poli. */
    private function kolomAgregatHakKelas(): array
    {
        $takTerbaca = "d.is_bpjs = 1 AND NVL(d.hak_kelas,'-') NOT IN ('1','2','3')";

        return [
            DB::raw('COUNT(DISTINCT d.rj_no) as total'),
            DB::raw('COUNT(DISTINCT d.reg_no) as pasien_unik'),
            DB::raw('SUM(d.is_bpjs) as bpjs'),
            DB::raw('SUM(1 - d.is_bpjs) as non_bpjs'),
            DB::raw("SUM(CASE WHEN d.is_bpjs = 1 AND d.hak_kelas = '1' THEN 1 ELSE 0 END) as kelas1"),
            DB::raw("SUM(CASE WHEN d.is_bpjs = 1 AND d.hak_kelas = '2' THEN 1 ELSE 0 END) as kelas2"),
            DB::raw("SUM(CASE WHEN d.is_bpjs = 1 AND d.hak_kelas = '3' THEN 1 ELSE 0 END) as kelas3"),
            // Peringatan: BPJS yang hak kelasnya tidak bisa dipastikan
            DB::raw("SUM(CASE WHEN {$takTerbaca} AND d.vno_sep IS NOT NULL THEN 1 ELSE 0 END) as sep_tak_terbaca"),
            DB::raw("SUM(CASE WHEN {$takTerbaca} AND d.vno_sep IS NULL THEN 1 ELSE 0 END) as belum_sep"),
        ];
    }

    /** Rekap per periode — $groupSql memakai alias d (mis. "to_char(d.rj_date, 'MM')"). */
    protected function buildHakKelasRJAggregate($start, $end, string $groupSql)
    {
        return DB::query()
            ->fromSub($this->kunjunganHakKelasQuery($start, $end), 'd')
            ->select(array_merge([DB::raw("{$groupSql} as periode")], $this->kolomAgregatHakKelas()))
            ->groupBy(DB::raw($groupSql))
            ->orderBy(DB::raw($groupSql))
            ->get()
            ->keyBy('periode');
    }

    /** Rekap per poli, terbanyak BPJS di atas. */
    protected function poliHakKelasRJ($start, $end): array
    {
        return DB::query()
            ->fromSub($this->kunjunganHakKelasQuery($start, $end), 'd')
            ->leftJoin('rsmst_polis as p', 'p.poli_id', '=', 'd.poli_id')
            ->select(array_merge(['d.poli_id', DB::raw('MAX(p.poli_desc) as poli_desc')], $this->kolomAgregatHakKelas()))
            ->groupBy('d.poli_id')
            ->get()
            ->map(fn($row) => $this->fillHakKelasRow($row, (string) ($row->poli_desc ?? '(Tanpa Poli)'), (string) ($row->poli_id ?? '')))
            ->sortByDesc('bpjs')
            ->values()
            ->all();
    }

    protected function pasienUnikHakKelasRJ($start, $end): int
    {
        return DB::table('rstxn_rjhdrs')
            ->whereBetween('rj_date', [$start, $end])
            ->where('klaim_id', '!=', 'KR')
            ->whereRaw("NVL(rj_status,'A') <> 'F'")
            ->distinct()
            ->count('reg_no');
    }

    protected function fillHakKelasRow(?object $row, string $label, string $short): array
    {
        $hasil = [
            'periode_label'   => $label,
            'periode_short'   => $short,
            'total'           => (int) ($row->total ?? 0),
            'pasien_unik'     => (int) ($row->pasien_unik ?? 0),
            'bpjs'            => (int) ($row->bpjs ?? 0),
            'non_bpjs'        => (int) ($row->non_bpjs ?? 0),
            'kelas1'          => (int) ($row->kelas1 ?? 0),
            'kelas2'          => (int) ($row->kelas2 ?? 0),
            'kelas3'          => (int) ($row->kelas3 ?? 0),
            'sep_tak_terbaca' => (int) ($row->sep_tak_terbaca ?? 0),
            'belum_sep'       => (int) ($row->belum_sep ?? 0),
        ];

        return $this->lengkapiHakKelasRow($hasil);
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

    protected function totalsHakKelas(array $rows): array
    {
        $jumlah = fn(string $kunci) => array_sum(array_column($rows, $kunci));

        return $this->lengkapiHakKelasRow([
            'periode_label'   => 'TOTAL',
            'periode_short'   => '',
            'total'           => $jumlah('total'),
            'pasien_unik'     => $jumlah('pasien_unik'),
            'bpjs'            => $jumlah('bpjs'),
            'non_bpjs'        => $jumlah('non_bpjs'),
            'kelas1'          => $jumlah('kelas1'),
            'kelas2'          => $jumlah('kelas2'),
            'kelas3'          => $jumlah('kelas3'),
            'sep_tak_terbaca' => $jumlah('sep_tak_terbaca'),
            'belum_sep'       => $jumlah('belum_sep'),
        ]);
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
