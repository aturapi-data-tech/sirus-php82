<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Shift kerja RS dari tabel rstxn_shiftctls (shift, shift_start, shift_end — jam 'HH24:MI:SS').
 * Dipakai bersama oleh kasir, keuangan, dan filter daftar transaksi — satu sumber
 * supaya nomor shift dan aturan jamnya tidak ditulis ulang di tiap komponen.
 */
final class Shift
{
    /** Nomor shift sesuai jam sekarang, fallback '1'. */
    public static function sekarang(): string
    {
        $shift = DB::table('rstxn_shiftctls')
            ->select('shift')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [now()->format('H:i:s')])
            ->first();

        return (string) ($shift?->shift ?? '1');
    }

    /** Daftar nomor shift yang terdefinisi, urut jam mulai — untuk pilihan/filter. */
    public static function daftar(): array
    {
        return DB::table('rstxn_shiftctls')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->orderBy('shift_start')
            ->pluck('shift')
            ->map(fn ($nomorShift) => (string) $nomorShift)
            ->unique()
            ->values()
            ->all();
    }
}
