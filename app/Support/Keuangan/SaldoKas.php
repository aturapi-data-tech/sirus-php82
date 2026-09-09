<?php

namespace App\Support\Keuangan;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Rumus saldo kas Oracle Forms 6i (MASTER_KAS → hitung_saldo_tanggal), dipakai
 * halaman Cek Saldo Kas supaya angkanya dapat dibandingkan langsung dengan form legacy.
 *
 * Sumber : App\Support\Keuangan\Jurnal — jurnal dibaca LANGSUNG dari tabel transaksi
 *          (bukan TKVIEW_ACCOUNTS / _NERACA1). Untuk akun kas, Jurnal hanya menyisakan
 *          12 cabang yang memuat kolom akun kas dari tabel sumbernya (CI/CO kas TU,
 *          BAYAR RJ/UGD/RESEP/RI, angsuran awal RI + 2 pengembalian, bayar PBF + bayar
 *          dimuka PBF, retur obat); cabang lain ber-akun konfigurasi dan dibuang di PHP.
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
            ? ['filter' => Jurnal::SISI_ACC,  'lawan' => Jurnal::SISI_ACCK, 'debit' => 'txn_d', 'kredit' => 'txn_k']
            : ['filter' => Jurnal::SISI_ACCK, 'lawan' => Jurnal::SISI_ACC,  'debit' => 'txn_k', 'kredit' => 'txn_d'];
    }

    /** Query dasar: baris akun ini pada rentang tanggal (inklusif); hari terakhir dipotong per shift bila diminta. */
    public static function query(string $accId, string $dk, string $dari, string $sampai, ?string $shift = null): Builder
    {
        $q = Jurnal::query($accId, self::sisi($dk)['filter'], $dari, $sampai);

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
