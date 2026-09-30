<?php

namespace App\Http\Traits\Manajemen\Rs;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Hitung jumlah kasus satu diagnosa ICD-10 di RJ, UGD, dan RI pada rentang tanggal.
 *
 * Sumber: tabel detail diagnosa relasional (dual-write EMR), BUKAN JSON:
 *   RJ  : rstxn_rjdtls.rj_no      → rstxn_rjhdrs  (tanggal rj_date)
 *   UGD : rstxn_ugddtls.rj_no     → rstxn_ugdhdrs (tanggal rj_date)
 *   RI  : rstxn_ridtls.rihdr_no   → rstxn_rihdrs  (tanggal exit_date = pulang,
 *         selaras Laporan Kunjungan RI; pasien yang masih dirawat belum terhitung)
 *
 * Kode dicocokkan lewat rsmst_mstdiags.icdx, bukan diag_id: satu icdx bisa punya
 * dua baris master (seed `I10` + legacy `I10X`), dan transaksi lama memakai baris
 * legacy. Kode kategori (E11) ikut menghitung seluruh sub-kodenya (E11.0–E11.9).
 *
 * Primer/sekunder tidak dibedakan — kategori itu hanya ada di JSON EMR.
 * Pasien Kronis (klaim_id 'KR') dan kunjungan batal (status F) dikeluarkan.
 */
trait HitungDiagnosaTrait
{
    /** Konfigurasi per jalur: tabel detail, tabel header, kunci, kolom tanggal, syarat aktif. */
    private function konfigurasiJalurDiagnosa(): array
    {
        return [
            'RJ' => [
                'label'       => 'Rawat Jalan',
                'tabelDetail' => 'rstxn_rjdtls',
                'tabelHeader' => 'rstxn_rjhdrs',
                'kunci'       => 'rj_no',
                'tanggal'     => 'rj_date',
                'syaratAktif' => "NVL(h.rj_status,'A') <> 'F'",
            ],
            'UGD' => [
                'label'       => 'UGD',
                'tabelDetail' => 'rstxn_ugddtls',
                'tabelHeader' => 'rstxn_ugdhdrs',
                'kunci'       => 'rj_no',
                'tanggal'     => 'rj_date',
                'syaratAktif' => "NVL(h.rj_status,'A') <> 'F'",
            ],
            'RI' => [
                'label'       => 'Rawat Inap',
                'tabelDetail' => 'rstxn_ridtls',
                'tabelHeader' => 'rstxn_rihdrs',
                'kunci'       => 'rihdr_no',
                'tanggal'     => 'exit_date',
                'syaratAktif' => "NVL(h.ri_status,'-') <> 'F'",
            ],
        ];
    }

    /** Query dasar satu jalur: baris detail diagnosa yang cocok dengan kode, dalam periode. */
    private function queryDiagnosaJalur(array $konfigurasi, string $icdx, Carbon $start, Carbon $end)
    {
        return DB::table($konfigurasi['tabelDetail'] . ' as d')
            ->join($konfigurasi['tabelHeader'] . ' as h', 'h.' . $konfigurasi['kunci'], '=', 'd.' . $konfigurasi['kunci'])
            ->join('rsmst_mstdiags as m', 'm.diag_id', '=', 'd.diag_id')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->where(fn($subQuery) => $subQuery->where('m.icdx', $icdx)->orWhere('m.icdx', 'like', $icdx . '.%'))
            ->whereBetween('h.' . $konfigurasi['tanggal'], [$start, $end])
            ->whereRaw("NVL(h.klaim_id,'-') <> 'KR'")
            ->whereRaw($konfigurasi['syaratAktif']);
    }

    /**
     * @return array{jalur: array, total: array, perKode: array}
     */
    protected function hitungDiagnosa(string $icdx, Carbon $start, Carbon $end): array
    {
        $jalurList = [];
        $perKode = [];
        $pasienQueries = [];

        foreach ($this->konfigurasiJalurDiagnosa() as $kodeJalur => $konfigurasi) {
            $kunci = 'h.' . $konfigurasi['kunci'];

            $row = $this->queryDiagnosaJalur($konfigurasi, $icdx, $start, $end)
                ->selectRaw("COUNT(DISTINCT {$kunci}) as kunjungan, "
                    . 'COUNT(DISTINCT h.reg_no) as pasien, '
                    . "COUNT(DISTINCT CASE WHEN p.sex = 'L' THEN {$kunci} END) as laki, "
                    . "COUNT(DISTINCT CASE WHEN p.sex = 'P' THEN {$kunci} END) as perempuan")
                ->first();

            $jalurList[] = [
                'jalur'     => $kodeJalur,
                'label'     => $konfigurasi['label'],
                'kunjungan' => (int) ($row->kunjungan ?? 0),
                'pasien'    => (int) ($row->pasien ?? 0),
                'laki'      => (int) ($row->laki ?? 0),
                'perempuan' => (int) ($row->perempuan ?? 0),
            ];

            $rowsKode = $this->queryDiagnosaJalur($konfigurasi, $icdx, $start, $end)
                ->groupBy('m.icdx')
                ->selectRaw("m.icdx as icdx, MIN(m.diag_desc) as diag_desc, COUNT(DISTINCT {$kunci}) as kunjungan")
                ->get();

            foreach ($rowsKode as $rowKode) {
                $kode = (string) $rowKode->icdx;
                $perKode[$kode] ??= ['icdx' => $kode, 'diag_desc' => (string) $rowKode->diag_desc, 'RJ' => 0, 'UGD' => 0, 'RI' => 0];
                $perKode[$kode][$kodeJalur] = (int) $rowKode->kunjungan;
            }

            $pasienQueries[] = $this->queryDiagnosaJalur($konfigurasi, $icdx, $start, $end)->select('h.reg_no');
        }

        // Pasien unik lintas jalur: satu pasien bisa muncul di RJ dan RI sekaligus.
        $unionPasien = array_shift($pasienQueries);
        foreach ($pasienQueries as $pasienQuery) {
            $unionPasien->union($pasienQuery);
        }
        $pasienUnik = (int) DB::query()->fromSub($unionPasien, 'u')->count();

        $perKode = array_values($perKode);
        usort($perKode, fn($a, $b) => strcmp($a['icdx'], $b['icdx']));

        return [
            'jalur'   => $jalurList,
            'total'   => [
                'kunjungan' => array_sum(array_column($jalurList, 'kunjungan')),
                'pasien'    => $pasienUnik,
                'laki'      => array_sum(array_column($jalurList, 'laki')),
                'perempuan' => array_sum(array_column($jalurList, 'perempuan')),
            ],
            'perKode' => $perKode,
        ];
    }
}
