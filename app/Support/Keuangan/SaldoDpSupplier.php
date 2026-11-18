<?php

namespace App\Support\Keuangan;

use Illuminate\Support\Facades\DB;

/**
 * Saldo DP (uang muka) supplier — SATU sumber rumus untuk Topup, Pembayaran Hutang, dan rekap.
 *
 *   saldo = saldo awal tahun terakhir yang ada  (TKTXN_SALDOAWALHUTANGS[NON].SAH_VALUE_TOP, ≤ tahun ini)
 *         + setor   sejak 1 Jan tahun itu        (IMTXN_CASHOUTHDRTOPUPS[NON].CASHOUT_VALUE)
 *         − terpakai sejak 1 Jan tahun itu       (IMTXN_CASHOUTHDRS[NON].CASHOUT_VALUE_TOPUP)
 *         + Σ SEMUA opname                        (TKTXN_SODPSUPPS[NON].SO_D − SO_K)
 *
 * Saldo awal dibuat tutup buku Forms 6i = saldo awal lalu + setor − pakai (koreksi di Forms,
 * mis. AAM 3.235.822, ikut terbawa). Tutup buku itu tidak mengenal tabel opname web, sehingga
 * opname dijumlah semua tahun. Tutup buku tahun berjalan belum dijalankan → saldo awal tahun
 * sebelumnya dipakai + transaksi sejak tahun itu. Tanpa saldo awal sama sekali → semua transaksi.
 *
 * Opname TIDAK dijurnal (Saldo Kas / Neraca tak membaca tabel opname).
 * Nama tabel ditulis literal per jenis (keputusan user: auditor membaca SELECT dengan jelas).
 */
final class SaldoDpSupplier
{
    public const JENIS_PBF = 'pbf';
    public const JENIS_NON_MEDIS = 'non-medis';

    private const TABEL = [
        self::JENIS_PBF => [
            'saldoAwal' => 'tktxn_saldoawalhutangs',
            'setor'     => 'imtxn_cashouthdrtopups',
            'pakai'     => 'imtxn_cashouthdrs',
            'opname'    => 'tktxn_sodpsupps',
            'seq'       => 'sodpsupp_seq',
        ],
        self::JENIS_NON_MEDIS => [
            'saldoAwal' => 'tktxn_saldoawalhutangsnon',
            'setor'     => 'imtxn_cashouthdrtopupsnon',
            'pakai'     => 'imtxn_cashouthdrsnon',
            'opname'    => 'tktxn_sodpsuppsnon',
            'seq'       => 'sodpsuppnon_seq',
        ],
    ];

    public static function tabel(string $jenis, string $peran): string
    {
        $peta = self::TABEL[$jenis] ?? null;
        if ($peta === null || !isset($peta[$peran])) {
            throw new \InvalidArgumentException("Jenis/peran tabel DP tidak dikenal: {$jenis}/{$peran}");
        }

        return $peta[$peran];
    }

    /**
     * Rincian saldo DP satu supplier.
     *
     * @return array{tahunAwal: ?string, awal: int, setor: int, pakai: int, opname: int, saldo: int, dari: string}
     *   dari = 'YYYY-MM-DD' batas transaksi yang dihitung (1 Jan tahun saldo awal, atau 1900-01-01)
     */
    public static function rincian(string $jenis, string $suppId, ?int $tahun = null): array
    {
        $tahun ??= (int) now()->year;
        $t = self::TABEL[$jenis] ?? self::TABEL[self::JENIS_PBF];

        $awalRow = DB::table($t['saldoAwal'])
            ->where('supp_id', $suppId)
            ->where('sah_year', '<=', (string) $tahun)
            ->orderByDesc('sah_year')
            ->first(['sah_year', DB::raw('NVL(sah_value_top,0) as nilai')]);

        $tahunAwal = $awalRow ? (string) $awalRow->sah_year : null;
        $dari = $tahunAwal ? $tahunAwal . '-01-01' : '1900-01-01';

        $setor = (float) DB::table($t['setor'])->where('supp_id', $suppId)
            ->whereRaw("cashout_date >= TO_DATE(?,'YYYY-MM-DD')", [$dari])->sum('cashout_value');
        $pakai = (float) DB::table($t['pakai'])->where('supp_id', $suppId)
            ->whereRaw("cashout_date >= TO_DATE(?,'YYYY-MM-DD')", [$dari])->sum('cashout_value_topup');
        $opname = (float) DB::table($t['opname'])->where('supp_id', $suppId)
            ->sum(DB::raw('NVL(so_d,0) - NVL(so_k,0)'));

        $awal = (int) round((float) ($awalRow->nilai ?? 0));
        $setor = (int) round($setor);
        $pakai = (int) round($pakai);
        $opname = (int) round($opname);

        return [
            'tahunAwal' => $tahunAwal,
            'awal' => $awal,
            'setor' => $setor,
            'pakai' => $pakai,
            'opname' => $opname,
            'saldo' => $awal + $setor - $pakai + $opname,
            'dari' => $dari,
        ];
    }

    public static function saldo(string $jenis, string $suppId): int
    {
        return self::rincian($jenis, $suppId)['saldo'];
    }

    /**
     * Rekap semua supplier yang pernah punya DP (saldo awal / setor / opname), satu query.
     * $semua = false → hanya saldo ≠ 0.
     *
     * @return array<int, array{suppId: string, nama: string, tahunAwal: ?string, awal: int, setor: int, pakai: int, opname: int, saldo: int, terakhir: string}>
     */
    public static function rekap(string $jenis, string $cari = '', bool $semua = false): array
    {
        $tahun = (string) now()->year;
        $t = self::TABEL[$jenis] ?? self::TABEL[self::JENIS_PBF];

        // Saldo awal tahun terakhir ≤ tahun ini per supplier; batas transaksi = 1 Jan tahun itu.
        $sql = "
            SELECT x.supp_id, s.supp_name, x.tahun_awal, x.awal, x.setor, x.pakai, x.opname,
                   x.awal + x.setor - x.pakai + x.opname AS saldo, x.terakhir
            FROM (
                SELECT k.supp_id, sa.sah_year AS tahun_awal, NVL(sa.sah_value_top,0) AS awal,
                       NVL((SELECT SUM(t.cashout_value) FROM {$t['setor']} t
                            WHERE t.supp_id = k.supp_id
                              AND t.cashout_date >= TO_DATE(NVL(sa.sah_year,'1900') || '0101','YYYYMMDD')),0) AS setor,
                       NVL((SELECT SUM(c.cashout_value_topup) FROM {$t['pakai']} c
                            WHERE c.supp_id = k.supp_id
                              AND c.cashout_date >= TO_DATE(NVL(sa.sah_year,'1900') || '0101','YYYYMMDD')),0) AS pakai,
                       NVL((SELECT SUM(NVL(o.so_d,0) - NVL(o.so_k,0)) FROM {$t['opname']} o
                            WHERE o.supp_id = k.supp_id),0) AS opname,
                       (SELECT TO_CHAR(MAX(t2.cashout_date),'dd/mm/yyyy') FROM {$t['setor']} t2
                            WHERE t2.supp_id = k.supp_id) AS terakhir
                FROM (
                    -- tahun saldo awal terakhir ≤ tahun ini dihitung dulu (Oracle 10g menolak
                    -- subquery di syarat outer join — ORA-01799)
                    SELECT u.supp_id,
                           (SELECT MAX(b.sah_year) FROM {$t['saldoAwal']} b
                             WHERE b.supp_id = u.supp_id AND b.sah_year <= ?) AS tahun_awal
                    FROM (
                        SELECT supp_id FROM {$t['setor']}
                        UNION SELECT supp_id FROM {$t['opname']}
                        UNION SELECT supp_id FROM {$t['saldoAwal']} WHERE NVL(sah_value_top,0) <> 0
                    ) u
                ) k
                LEFT JOIN {$t['saldoAwal']} sa
                       ON sa.supp_id = k.supp_id AND sa.sah_year = k.tahun_awal
            ) x
            JOIN immst_suppliers s ON s.supp_id = x.supp_id
            WHERE 1 = 1";
        $binding = [$tahun];

        if (!$semua) {
            $sql .= ' AND x.awal + x.setor - x.pakai + x.opname <> 0';
        }
        $cari = trim($cari);
        if ($cari !== '') {
            $sql .= ' AND (UPPER(s.supp_name) LIKE ? OR UPPER(x.supp_id) LIKE ?)';
            $binding[] = '%' . mb_strtoupper($cari) . '%';
            $binding[] = '%' . mb_strtoupper($cari) . '%';
        }
        $sql .= ' ORDER BY x.awal + x.setor - x.pakai + x.opname DESC, s.supp_name';

        return collect(DB::select($sql, $binding))->map(fn ($row) => [
            'suppId' => (string) $row->supp_id,
            'nama' => (string) ($row->supp_name ?? ''),
            'tahunAwal' => $row->tahun_awal !== null ? (string) $row->tahun_awal : null,
            'awal' => (int) round((float) $row->awal),
            'setor' => (int) round((float) $row->setor),
            'pakai' => (int) round((float) $row->pakai),
            'opname' => (int) round((float) $row->opname),
            'saldo' => (int) round((float) $row->saldo),
            'terakhir' => (string) ($row->terakhir ?? ''),
        ])->all();
    }
}
