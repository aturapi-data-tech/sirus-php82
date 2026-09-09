<?php

namespace App\Support\Keuangan;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Rumus saldo kas Oracle Forms 6i (MASTER_KAS → hitung_saldo_tanggal), dipakai
 * halaman Cek Saldo Kas supaya angkanya dapat dibandingkan langsung dengan form legacy.
 *
 * Sumber : SELECT LANGSUNG ke tabel transaksi (bukan TKVIEW_ACCOUNTS / _NERACA1).
 *          Hanya 12 cabang view yang memuat kolom akun kas dari tabel sumbernya
 *          (lihat cabang()); cabang lain memakai akun konfigurasi (piutang, pendapatan,
 *          hutang, HPP) sehingga tidak pernah menyentuh akun kas. Bentuk kolom hasil
 *          dibuat SAMA dengan view (TXN_NAME, TXN_ACC, TXN_ACC_K, SHIFT, TXN_DATE,
 *          TXN_D, TXN_K) termasuk baris cerminnya, agar rumus sisi 6i tetap berlaku.
 * Akun D : baris dengan txn_acc   = akun, arus = txn_d - txn_k.
 * Akun K : baris dengan txn_acc_k = akun, arus = txn_k - txn_d.
 * Saldo  : saldo awal tahun (TKTXN_SALDOAWALAKUNS) + arus 1 Januari s/d tanggal;
 *          hari terakhir dipotong sampai shift yang diminta (6i: yyyymmdd||shift).
 *
 * 6i juga membuang baris yang D dan K-nya nol (to_number(abs(txn_d)||abs(txn_k))>0);
 * itu tidak mengubah penjumlahan sehingga tidak ditiru.
 */
final class SaldoKas
{
    public static function saldoAwalTahun(string $accId, string $dk, int $tahun): float
    {
        $sa = DB::table('tktxn_saldoawalakuns')
            ->where('acc_id', $accId)
            ->where('sa_year', (string) $tahun)
            ->first();

        return $dk === 'D'
            ? (float) ($sa->sa_acc_d ?? 0)
            : (float) ($sa->sa_acc_k ?? 0);
    }

    /** Kolom sisi 6i: akun D dibaca dari baris txn_acc, akun K dari baris txn_acc_k. */
    public static function sisi(string $dk): array
    {
        return $dk === 'D'
            ? ['filter' => 'txn_acc',   'lawan' => 'txn_acc_k', 'debit' => 'txn_d', 'kredit' => 'txn_k']
            : ['filter' => 'txn_acc_k', 'lawan' => 'txn_acc',   'debit' => 'txn_k', 'kredit' => 'txn_d'];
    }

    /**
     * Cabang TKVIEW_ACCOUNTS yang memuat akun kas langsung dari tabel sumber.
     * Label, tabel, filter status, kolom tanggal/shift/nominal, dan arah kas ditiru
     * apa adanya dari definisi view (database/sql/2026_08_01_view_tkview_accounts_ok_rj_ugd.sql).
     *
     * arah 'masuk'  : kas di-debit (baris view: txn_acc = kas, txn_d = nominal).
     * arah 'keluar' : kas di-kredit (baris view: txn_acc = kas, txn_k = nominal).
     * lawanKas true : kolom lawan juga kolom akun bebas (bisa kas lain), bukan akun konfigurasi.
     */
    private static function cabang(): array
    {
        $conf = fn (string $id) => "(select z.acc_id from tkacc_confacctxns z where z.conf_id = '{$id}')";

        return [
            // ---- Kas TU: penerimaan (CI) & pengeluaran (CO) ----
            [
                'label'    => "'CI '||a.tucashk_desc||'('||a.tucashk_no||')'",
                'kas'      => 'a.acc_id_kas',
                'lawan'    => 'a.acc_id',
                'lawanKas' => true,
                'shift'    => 'a.shift',
                'tanggal'  => 'a.tucashk_date',
                'nominal'  => 'a.tucashk_nominal',
                'arah'     => 'masuk',
                'from'     => 'rstxn_tucashds a',
                'where'    => "a.tucashk_status = 'L'",
            ],
            [
                'label'    => "'CO '||a.tucashk_desc||'('||a.tucashk_no||')'",
                'kas'      => 'a.acc_id_kas',
                'lawan'    => 'a.acc_id',
                'lawanKas' => true,
                'shift'    => 'a.shift',
                'tanggal'  => 'a.tucashk_date',
                'nominal'  => 'a.tucashk_nominal',
                'arah'     => 'keluar',
                'from'     => 'rstxn_tucashks a',
                'where'    => "a.tucashk_status = 'L'",
            ],
            // ---- Pembayaran pasien ----
            [
                'label'   => "'BAYAR_RJ ('||a.rjc_desc||')'",
                'kas'     => 'a.acc_id',
                'lawan'   => $conf('RJ1'),
                'shift'   => 'a.shift',
                'tanggal' => 'a.rjc_date',
                'nominal' => 'a.rjc_nominal',
                'arah'    => 'masuk',
                'from'    => 'rstxn_rjcashins a, rstxn_rjhdrs b',
                'where'   => "a.rj_no = b.rj_no and b.rj_status not in ('A','F')",
            ],
            [
                'label'   => "'BAYAR_UGD ('||a.rjc_desc||')'",
                'kas'     => 'a.acc_id',
                'lawan'   => $conf('UGD1'),
                'shift'   => 'a.shift',
                'tanggal' => 'a.rjc_date',
                'nominal' => 'a.rjc_nominal',
                'arah'    => 'masuk',
                'from'    => 'rstxn_ugdcashins a, rstxn_ugdhdrs b',
                'where'   => "a.rj_no = b.rj_no and b.rj_status not in ('A','F')",
            ],
            [
                'label'   => "'BAYAR_RESEP ('||a.sls_no||' '||a.reg_no||')'",
                'kas'     => 'a.acc_id',
                'lawan'   => $conf('RESEP1'),
                'shift'   => 'a.shift',
                'tanggal' => 'a.sls_date',
                'nominal' => 'a.sls_bayar',
                'arah'    => 'masuk',
                'from'    => 'imtxn_slshdrs a',
                'where'   => "a.status = 'L'",
            ],
            [
                'label'   => "'BAYAR_RI ('||(select x.reg_name||' / '||x.reg_no from rsmst_pasiens x where x.reg_no = b.reg_no)||')'",
                'kas'     => 'a.acc_id',
                'lawan'   => $conf('RI1'),
                'shift'   => 'a.shift',
                'tanggal' => 'a.ripay_date',
                'nominal' => 'a.ripay_bayar',
                'arah'    => 'masuk',
                'from'    => 'rstxn_ripaymentpdtls a, rstxn_rihdrs b',
                'where'   => "a.rihdr_no = b.rihdr_no and b.ri_status = 'P'",
            ],
            // ---- Angsuran awal RI & pengembaliannya ----
            [
                'label'   => "'ANGSURAN AWAL ('||a.rihdr_no||')'",
                'kas'     => 'a.acc_id',
                'lawan'   => $conf('RIANGAWAL'),
                'shift'   => 'a.shift',
                'tanggal' => 'a.ripay_date',
                'nominal' => 'a.ripay_bayar',
                'arah'    => 'masuk',
                'from'    => 'rstxn_ripaymentdtls a',
                'where'   => '',
            ],
            [
                'label'   => "'PENGEMBALIAN ANGSURAN AWAL ('||a.rihdr_no||'/'||a.reg_no||')'",
                'kas'     => 'a.acc_id',
                'lawan'   => $conf('RIANGAWAL'),
                'shift'   => 'a.shift',
                'tanggal' => 'a.exit_date',
                'nominal' => '(select nvl(sum(d.ripay_bayar),0) from rstxn_ripaymentdtls d where d.rihdr_no = a.rihdr_no)',
                'arah'    => 'keluar',
                'from'    => 'rstxn_rihdrs a',
                'where'   => "a.ri_status = 'P'",
            ],
            [
                'label'   => "'PENGEMBALIAN ANGSURAN AWAL P ('||a.rihdr_no||')'",
                'kas'     => 'b.acc_id',
                'lawan'   => $conf('R1'),
                'shift'   => 'b.shift',
                'tanggal' => 'b.ripay_date',
                'nominal' => 'nvl(b.ripay_bayar,0)',
                'arah'    => 'keluar',
                'from'    => 'rstxn_rihdrs a, rstxn_ripaymentpkdtls b',
                'where'   => "a.rihdr_no = b.rihdr_no and a.ri_status = 'P'",
            ],
            // ---- Pembayaran ke PBF ----
            [
                'label'   => "'BAYAR PBF / '||(select x.supp_name from immst_suppliers x where x.supp_id = a.supp_id)",
                'kas'     => 'a.acc_id',
                'lawan'   => $conf('RCV1'),
                'shift'   => 'a.shift',
                'tanggal' => 'a.cashout_date',
                'nominal' => 'a.cashout_value',
                'arah'    => 'keluar',
                'from'    => 'imtxn_cashouthdrs a',
                'where'   => 'nvl(a.cashout_value,0) > 0',
            ],
            [
                'label'   => "'BAYAR DIMUKA PBF / '||(select x.supp_name from immst_suppliers x where x.supp_id = a.supp_id)",
                'kas'     => 'a.acc_id',
                'lawan'   => $conf('RCV6'),
                'shift'   => 'a.shift',
                'tanggal' => 'a.cashout_date',
                'nominal' => 'a.cashout_value',
                'arah'    => 'keluar',
                'from'    => 'imtxn_cashouthdrtopups a',
                'where'   => '',
            ],
            // ---- Retur obat RJ (kas dikembalikan ke pasien) ----
            [
                'label'   => "'RTN OBAT ('||a.rtn_desc||'/'||a.reg_no||')'",
                'kas'     => 'a.acc_id',
                'lawan'   => $conf('PAPOTEK'),
                'shift'   => 'a.shift',
                'tanggal' => 'a.rtn_date',
                'nominal' => '(select nvl(sum(d.qty*d.rtn_prise),0) from imtxn_rtndtls d where d.rtn_no = a.rtn_no)',
                'arah'    => 'keluar',
                'from'    => 'imtxn_rtnhdrs a',
                'where'   => "a.rtn_status = 'L'",
            ],
        ];
    }

    /**
     * SQL sumber (UNION ALL) berbentuk kolom view untuk SATU akun: tiap cabang menghasilkan
     * baris kas dan baris cerminnya, disaring di tabel sumber lewat kolom akunnya sendiri
     * sehingga tidak ada full scan. Mengembalikan [sql, bindings].
     */
    private static function sumber(string $accId): array
    {
        $parts = [];
        $bind  = [];

        foreach (self::cabang() as $c) {
            $masuk  = $c['arah'] === 'masuk';
            $shift  = "nvl({$c['shift']},'1')";
            $filter = ($c['lawanKas'] ?? false)
                ? "({$c['kas']} = ? or {$c['lawan']} = ?)"
                : "{$c['kas']} = ?";
            $where  = 'where ' . $filter . ($c['where'] !== '' ? " and {$c['where']}" : '');

            // Baris kas: txn_acc = kas.
            $parts[] = "select {$c['label']} txn_name, {$c['kas']} txn_acc, {$c['lawan']} txn_acc_k, {$shift} shift, {$c['tanggal']} txn_date, "
                . ($masuk ? "{$c['nominal']} txn_d, 0 txn_k" : "0 txn_d, {$c['nominal']} txn_k")
                . " from {$c['from']} {$where}";
            // Baris cermin: txn_acc = lawan.
            $parts[] = "select {$c['label']} txn_name, {$c['lawan']} txn_acc, {$c['kas']} txn_acc_k, {$shift} shift, {$c['tanggal']} txn_date, "
                . ($masuk ? "0 txn_d, {$c['nominal']} txn_k" : "{$c['nominal']} txn_d, 0 txn_k")
                . " from {$c['from']} {$where}";

            $n = ($c['lawanKas'] ?? false) ? 2 : 1;
            array_push($bind, ...array_fill(0, $n * 2, $accId));
        }

        return [implode("\nunion all\n", $parts), $bind];
    }

    /** Query dasar: baris akun ini pada rentang tanggal (inklusif); hari terakhir dipotong per shift bila diminta. */
    public static function query(string $accId, string $dk, string $dari, string $sampai, ?string $shift = null): Builder
    {
        $sisi = self::sisi($dk);
        [$sql, $bind] = self::sumber($accId);

        // Predikat tanggal ditulis sargable (>= dan < tgl+1) — Oracle mendorongnya ke tiap cabang.
        $q = DB::table(DB::raw("({$sql}) k"))
            ->addBinding($bind, 'from')
            ->where($sisi['filter'], $accId)
            ->whereRaw("txn_date >= TO_DATE(?,'YYYY-MM-DD')", [$dari])
            ->whereRaw("txn_date < TO_DATE(?,'YYYY-MM-DD') + 1", [$sampai]);

        if ($shift !== null && $shift !== '') {
            $q->whereRaw("(txn_date < TO_DATE(?,'YYYY-MM-DD') OR shift <= ?)", [$sampai, $shift]);
        }

        return $q;
    }

    /** Arus (mutasi bersih) akun pada rentang tanggal. */
    public static function arus(string $accId, string $dk, string $dari, string $sampai, ?string $shift = null): float
    {
        $sisi = self::sisi($dk);

        return (float) self::query($accId, $dk, $dari, $sampai, $shift)
            ->sum(DB::raw("NVL({$sisi['debit']},0) - NVL({$sisi['kredit']},0)"));
    }

    /** Saldo per tanggal (s/d shift tertentu bila diminta) — padanan hitung_saldo_tanggal 6i. */
    public static function hitung(string $accId, string $dk, string $tanggal, ?string $shift = null): float
    {
        $tahun = (int) substr($tanggal, 0, 4);

        return self::saldoAwalTahun($accId, $dk, $tahun)
            + self::arus($accId, $dk, sprintf('%04d-01-01', $tahun), $tanggal, $shift);
    }

    /** Arus satu tahun penuh (Jan–Des), dipakai back-calc Edit Saldo. */
    public static function arusTahun(string $accId, string $dk, int $tahun): float
    {
        return self::arus($accId, $dk, sprintf('%04d-01-01', $tahun), sprintf('%04d-12-31', $tahun));
    }

    /** Nomor shift sesuai jam sekarang (rstxn_shiftctls), fallback '1'. */
    public static function shiftSekarang(): string
    {
        $shift = DB::table('rstxn_shiftctls')
            ->select('shift')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [now()->format('H:i:s')])
            ->first();

        return (string) ($shift?->shift ?? '1');
    }

    /** Daftar nomor shift yang terdefinisi, urut jam mulai. */
    public static function daftarShift(): array
    {
        return DB::table('rstxn_shiftctls')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->orderBy('shift_start')
            ->pluck('shift')
            ->map(fn ($s) => (string) $s)
            ->unique()
            ->values()
            ->all();
    }
}
