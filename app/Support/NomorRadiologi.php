<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class NomorRadiologi
{
    /**
     * Generate RADNUM_NO unik untuk order radiologi.
     * Format: R-YYMMDD-NNNNN (15 karakter, pas VARCHAR2(15)).
     * Sequence per hari, atomic via SELECT MAX + 1 dalam transaksi.
     *
     * Dipanggil di dalam DB::transaction() — caller harus memastikan itu.
     */
    public static function generate(): string
    {
        $tanggal = now()->format('ymd');
        $prefix = "R-{$tanggal}-";

        $max = DB::scalar(
            "SELECT MAX(RADNUM_NO) FROM ("
            . " SELECT RADNUM_NO FROM RSTXN_RJRADS WHERE RADNUM_NO LIKE ?"
            . " UNION ALL"
            . " SELECT RADNUM_NO FROM RSTXN_UGDRADS WHERE RADNUM_NO LIKE ?"
            . " UNION ALL"
            . " SELECT RADNUM_NO FROM RSTXN_RIRADIOLOGS WHERE RADNUM_NO LIKE ?"
            . ")",
            ["{$prefix}%", "{$prefix}%", "{$prefix}%"]
        );

        if ($max) {
            $seq = (int) substr($max, -5) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
