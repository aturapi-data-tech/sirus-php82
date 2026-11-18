<?php

namespace App\Support\Keuangan;

use Illuminate\Support\Facades\DB;

/**
 * Tutup buku tahunan saldo supplier — meniru PERSIS tutup buku Forms 6i ke
 * TKTXN_SALDOAWALHUTANGS[NON] (dibuktikan di data lokal 2026-11-18: DP 142/142 & 147/147,
 * hutang 142/142 & 145/147 — 2 sisanya berubah setelah Forms menutup buku).
 *
 *   SAH_VALUE     (T+1) = SAH_VALUE (T) + Σ TOTALALL nota H/L tahun T (TKVIEW_RCVHDRS[NON])
 *                         − Σ (cashout_value + cashout_value_topup) tahun T
 *   SAH_VALUE_TOP (T+1) = SAH_VALUE_TOP (T) + Σ setor DP tahun T − Σ cashout_value_topup tahun T
 *
 * Opname saldo DP SENGAJA tidak dimasukkan: dengan begitu tutup buku dari Forms maupun web
 * menghasilkan angka yang sama (tak saling menimpa / menghilangkan opname). Opname tetap
 * dihitung terpisah oleh SaldoDpSupplier (Σ semua opname).
 *
 * Nama tabel ditulis literal per jenis (keputusan user: auditor membaca SELECT dengan jelas).
 */
final class TutupBukuSupplier
{
    private const TABEL = [
        SaldoDpSupplier::JENIS_PBF => [
            'saldoAwal' => 'tktxn_saldoawalhutangs',
            'nota'      => 'tkview_rcvhdrs',
            'kasKeluar' => 'imtxn_cashouthdrs',
            'setor'     => 'imtxn_cashouthdrtopups',
        ],
        SaldoDpSupplier::JENIS_NON_MEDIS => [
            'saldoAwal' => 'tktxn_saldoawalhutangsnon',
            'nota'      => 'tkview_rcvhdrsnon',
            'kasKeluar' => 'imtxn_cashouthdrsnon',
            'setor'     => 'imtxn_cashouthdrtopupsnon',
        ],
    ];

    /**
     * Pratinjau per supplier untuk menutup tahun $tahun (menghasilkan saldo awal $tahun + 1).
     *
     * @return array<int, array{suppId: string, nama: string,
     *   hutangAwal: float, beli: float, bayar: float, hutangBaru: float,
     *   dpAwal: float, setor: float, pakaiDp: float, dpBaru: float,
     *   ada: bool, hutangLama: ?float, dpLama: ?float}>
     */
    public static function pratinjau(string $jenis, int $tahun): array
    {
        $t = self::TABEL[$jenis] ?? throw new \InvalidArgumentException("Jenis tidak dikenal: {$jenis}");
        $thn = (string) $tahun;
        $berikut = (string) ($tahun + 1);

        // Semua supplier yang punya saldo awal tahun ini / transaksi tahun ini / baris tahun depan.
        $sql = "
            SELECT k.supp_id, s.supp_name,
                   NVL(a.sah_value,0)     AS hutang_awal,
                   NVL(a.sah_value_top,0) AS dp_awal,
                   NVL((SELECT SUM(NVL(v.totalall,0)) FROM {$t['nota']} v
                         WHERE v.supp_id = k.supp_id AND v.rcv_status IN ('H','L')
                           AND v.rcv_date >= TO_DATE(? || '0101','YYYYMMDD')
                           AND v.rcv_date <  TO_DATE(? || '0101','YYYYMMDD')),0) AS beli,
                   NVL((SELECT SUM(NVL(c.cashout_value,0) + NVL(c.cashout_value_topup,0)) FROM {$t['kasKeluar']} c
                         WHERE c.supp_id = k.supp_id
                           AND c.cashout_date >= TO_DATE(? || '0101','YYYYMMDD')
                           AND c.cashout_date <  TO_DATE(? || '0101','YYYYMMDD')),0) AS bayar,
                   NVL((SELECT SUM(NVL(c2.cashout_value_topup,0)) FROM {$t['kasKeluar']} c2
                         WHERE c2.supp_id = k.supp_id
                           AND c2.cashout_date >= TO_DATE(? || '0101','YYYYMMDD')
                           AND c2.cashout_date <  TO_DATE(? || '0101','YYYYMMDD')),0) AS pakai_dp,
                   NVL((SELECT SUM(NVL(x.cashout_value,0)) FROM {$t['setor']} x
                         WHERE x.supp_id = k.supp_id
                           AND x.cashout_date >= TO_DATE(? || '0101','YYYYMMDD')
                           AND x.cashout_date <  TO_DATE(? || '0101','YYYYMMDD')),0) AS setor,
                   n.supp_id AS ada_baris, n.sah_value AS hutang_lama, n.sah_value_top AS dp_lama
            FROM (
                SELECT supp_id FROM {$t['saldoAwal']} WHERE sah_year IN (?, ?)
                UNION SELECT supp_id FROM {$t['nota']}
                       WHERE rcv_status IN ('H','L')
                         AND rcv_date >= TO_DATE(? || '0101','YYYYMMDD') AND rcv_date < TO_DATE(? || '0101','YYYYMMDD')
                UNION SELECT supp_id FROM {$t['kasKeluar']}
                       WHERE cashout_date >= TO_DATE(? || '0101','YYYYMMDD') AND cashout_date < TO_DATE(? || '0101','YYYYMMDD')
                UNION SELECT supp_id FROM {$t['setor']}
                       WHERE cashout_date >= TO_DATE(? || '0101','YYYYMMDD') AND cashout_date < TO_DATE(? || '0101','YYYYMMDD')
            ) k
            JOIN immst_suppliers s ON s.supp_id = k.supp_id
            LEFT JOIN {$t['saldoAwal']} a ON a.supp_id = k.supp_id AND a.sah_year = ?
            LEFT JOIN {$t['saldoAwal']} n ON n.supp_id = k.supp_id AND n.sah_year = ?
            ORDER BY s.supp_name";

        $binding = [
            $thn, $berikut, $thn, $berikut, $thn, $berikut, $thn, $berikut,   // beli, bayar, pakai_dp, setor
            $thn, $berikut,                                                   // saldo awal tahun ini & depan
            $thn, $berikut, $thn, $berikut, $thn, $berikut,                   // nota, kas keluar, setor
            $thn, $berikut,                                                   // join a, n
        ];

        // Forms menyimpan desimal apa adanya (mis. 5.060.822,99) — hitung & simpan tanpa membulatkan
        // per komponen; hanya dibulatkan 2 desimal (sen) agar sisa float tak ikut tertulis.
        return collect(DB::select($sql, $binding))->map(function ($row) {
            $hutangAwal = (float) $row->hutang_awal;
            $beli = (float) $row->beli;
            $bayar = (float) $row->bayar;
            $dpAwal = (float) $row->dp_awal;
            $setor = (float) $row->setor;
            $pakaiDp = (float) $row->pakai_dp;

            return [
                'suppId' => (string) $row->supp_id,
                'nama' => (string) ($row->supp_name ?? ''),
                'hutangAwal' => round($hutangAwal, 2),
                'beli' => round($beli, 2),
                'bayar' => round($bayar, 2),
                'hutangBaru' => round($hutangAwal + $beli - $bayar, 2),
                'dpAwal' => round($dpAwal, 2),
                'setor' => round($setor, 2),
                'pakaiDp' => round($pakaiDp, 2),
                'dpBaru' => round($dpAwal + $setor - $pakaiDp, 2),
                'ada' => $row->ada_baris !== null,
                'hutangLama' => $row->ada_baris !== null ? round((float) $row->hutang_lama, 2) : null,
                'dpLama' => $row->ada_baris !== null ? round((float) $row->dp_lama, 2) : null,
            ];
        })->all();
    }

    /** Sama bila selisih < 1 sen (sisa pembulatan float). */
    public static function sama(?float $a, ?float $b): bool
    {
        return $a !== null && $b !== null && abs($a - $b) < 0.01;
    }

    /**
     * Tulis saldo awal tahun $tahun + 1 (update bila baris ada, insert bila belum). Satu transaksi.
     *
     * @return array{tahunBaru: string, diubah: int, dibuat: int, sama: int, sebelum: array<string, array{0: ?float, 1: ?float}>}
     */
    public static function proses(string $jenis, int $tahun): array
    {
        $t = self::TABEL[$jenis] ?? throw new \InvalidArgumentException("Jenis tidak dikenal: {$jenis}");
        $berikut = (string) ($tahun + 1);

        return DB::transaction(function () use ($t, $jenis, $tahun, $berikut) {
            // Kunci baris tahun depan yang sudah ada — dua admin tak bisa menutup bersamaan.
            DB::table($t['saldoAwal'])->where('sah_year', $berikut)->lockForUpdate()->get(['supp_id']);

            $diubah = $dibuat = $sama = 0;
            $sebelum = [];
            foreach (self::pratinjau($jenis, $tahun) as $row) {
                if ($row['ada']) {
                    if (self::sama($row['hutangLama'], $row['hutangBaru']) && self::sama($row['dpLama'], $row['dpBaru'])) {
                        $sama++;
                        continue;
                    }
                    $sebelum[$row['suppId']] = [$row['hutangLama'], $row['dpLama']];
                    DB::table($t['saldoAwal'])
                        ->where('supp_id', $row['suppId'])->where('sah_year', $berikut)
                        ->update(['sah_value' => $row['hutangBaru'], 'sah_value_top' => $row['dpBaru']]);
                    $diubah++;
                } else {
                    DB::table($t['saldoAwal'])->insert([
                        'sah_year' => $berikut,
                        'supp_id' => $row['suppId'],
                        'sah_value' => $row['hutangBaru'],
                        'sah_value_top' => $row['dpBaru'],
                    ]);
                    $dibuat++;
                }
            }

            return ['tahunBaru' => $berikut, 'diubah' => $diubah, 'dibuat' => $dibuat, 'sama' => $sama, 'sebelum' => $sebelum];
        });
    }
}
