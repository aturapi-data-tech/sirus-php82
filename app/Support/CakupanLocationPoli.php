<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cakupan Location pada master poli: dasar badge topbar dan filter "belum Location" di Master Poli.
 *
 * Master berisi juga unit yang bukan tempat kunjungan RJ (Rontgen, Laboratorium, OK), jadi angka yang
 * ditampilkan badge = persen pakai: kunjungan RJ 90 hari terakhir yang poli-nya punya poli_uuid.
 * UGD tidak dihitung karena Location IGD tidak diambil dari master ini.
 */
class CakupanLocationPoli
{
    public const HARI_PEMAKAIAN = 90;

    private const KUNCI_CACHE = 'cakupan-location-poli.ringkasan';
    private const DETIK_CACHE = 3600;

    /**
     * Jumlah kunjungan RJ 90 hari terakhir per poli, tanpa Batal: kolom poli_id_pakai + jumlah_pakai.
     * Tanpa binding: dipasang sebagai join di query yang punya binding pencarian (urutan binding
     * Oracle tak boleh tertukar).
     */
    public static function sqlPemakaian(): string
    {
        $sejak = 'SYSDATE - ' . self::HARI_PEMAKAIAN;

        return "SELECT poli_id AS poli_id_pakai, COUNT(*) AS jumlah_pakai
                FROM rstxn_rjhdrs
                WHERE rj_date >= {$sejak} AND NVL(rj_status, 'X') <> 'F'
                GROUP BY poli_id";
    }

    /**
     * Ringkasan tersimpan sejam. Null bila gagal dihitung: badge disembunyikan, karena topbar
     * ada di semua halaman dan tidak boleh ikut rusak gara-gara angka ini.
     *
     * @return array{total: int, berkode: int, persen: float, pakaiTotal: int, pakaiBerkode: int, persenPakai: float}|null
     */
    public static function ringkasan(): ?array
    {
        try {
            return Cache::remember(self::KUNCI_CACHE, self::DETIK_CACHE, fn() => self::hitung());
        } catch (\Throwable $e) {
            Log::warning('Cakupan Location poli gagal dihitung: ' . $e->getMessage());

            return null;
        }
    }

    /** Dipanggil saat master disimpan, supaya badge langsung mengikuti. */
    public static function lupakan(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }

    private static function hitung(): array
    {
        $master = DB::selectOne(
            "SELECT COUNT(*) AS total, SUM(CASE WHEN TRIM(poli_uuid) IS NOT NULL THEN 1 ELSE 0 END) AS berkode
             FROM rsmst_polis
             WHERE TRIM(poli_desc) IS NOT NULL",
        );

        $pakai = DB::selectOne(
            'SELECT NVL(SUM(p.jumlah_pakai), 0) AS total,
                    NVL(SUM(CASE WHEN TRIM(m.poli_uuid) IS NOT NULL THEN p.jumlah_pakai ELSE 0 END), 0) AS berkode
             FROM (' . self::sqlPemakaian() . ') p
             JOIN rsmst_polis m ON m.poli_id = p.poli_id_pakai',
        );

        return [
            'total' => (int) $master->total,
            'berkode' => (int) $master->berkode,
            'persen' => self::persen((int) $master->berkode, (int) $master->total),
            'pakaiTotal' => (int) $pakai->total,
            'pakaiBerkode' => (int) $pakai->berkode,
            'persenPakai' => self::persen((int) $pakai->berkode, (int) $pakai->total),
        ];
    }

    private static function persen(int $bagian, int $total): float
    {
        return $total > 0 ? round($bagian / $total * 100, 1) : 0.0;
    }
}
