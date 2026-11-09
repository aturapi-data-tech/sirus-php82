<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cakupan Location pada kamar rawat inap: dasar badge topbar dan filter "belum Location" di Master Kamar.
 *
 * Dua angka sengaja dipisah. Persen master = kamar aktif yang sudah punya room_uuid. Persen pakai = masuk
 * rawat 90 hari terakhir yang kamarnya punya room_uuid; yang kosong tak bisa memulai Encounter RI.
 * Kolom room_uuid butuh migrasi 2026_07_14_100000: bila belum jalan, badge tersembunyi.
 */
class CakupanLocationKamar
{
    public const HARI_PEMAKAIAN = 90;

    private const KUNCI_CACHE = 'cakupan-location-kamar.ringkasan';
    private const DETIK_CACHE = 3600;

    /**
     * Jumlah masuk rawat 90 hari terakhir per kamar, tanpa Batal: kolom room_id_pakai + jumlah_pakai. Kamar = kamar terakhir di header RI.
     * Tanpa binding: dipasang sebagai join di query yang punya binding pencarian (urutan binding
     * Oracle tak boleh tertukar).
     */
    public static function sqlPemakaian(): string
    {
        $sejak = 'SYSDATE - ' . self::HARI_PEMAKAIAN;

        return "SELECT room_id AS room_id_pakai, COUNT(*) AS jumlah_pakai
                FROM rstxn_rihdrs
                WHERE entry_date >= {$sejak} AND NVL(ri_status, 'X') <> 'F'
                GROUP BY room_id";
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
            Log::warning('Cakupan Location kamar gagal dihitung: ' . $e->getMessage());

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
            "SELECT COUNT(*) AS total, SUM(CASE WHEN TRIM(room_uuid) IS NOT NULL THEN 1 ELSE 0 END) AS berkode
             FROM rsmst_rooms
             WHERE active_status = '1' AND TRIM(room_name) IS NOT NULL",
        );

        // Pemakaian tetap dihitung walau masternya kini nonaktif: kunjungannya tetap ikut terkirim.
        $pakai = DB::selectOne(
            'SELECT NVL(SUM(p.jumlah_pakai), 0) AS total,
                    NVL(SUM(CASE WHEN TRIM(m.room_uuid) IS NOT NULL THEN p.jumlah_pakai ELSE 0 END), 0) AS berkode
             FROM (' . self::sqlPemakaian() . ') p
             JOIN rsmst_rooms m ON m.room_id = p.room_id_pakai',
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
