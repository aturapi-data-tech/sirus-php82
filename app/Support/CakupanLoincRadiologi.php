<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cakupan LOINC pada master radiologi: dasar badge topbar dan filter "belum LOINC" di Master Radiologi.
 *
 * Dua angka sengaja dipisah. Persen master = tindakan aktif bernama yang sudah ber-LOINC. Persen pakai
 * = permintaan 90 hari terakhir yang tindakannya ber-LOINC. Yang tanpa LOINC tetap terkirim dengan kode
 * umum 18748-4 (lihat kirim-radiologi), jadi angka ini mengukur ketepatan kode, bukan yang terkirim.
 */
class CakupanLoincRadiologi
{
    public const HARI_PEMAKAIAN = 90;

    private const KUNCI_CACHE = 'cakupan-loinc-radiologi.ringkasan';
    private const DETIK_CACHE = 3600;

    /**
     * Jumlah permintaan 90 hari terakhir per tindakan (RJ + UGD + RI): kolom rad_id_pakai + jumlah_pakai.
     * Tanpa binding: dipasang sebagai join di query yang punya binding pencarian (urutan binding
     * Oracle tak boleh tertukar).
     */
    public static function sqlPemakaian(): string
    {
        $sejak = 'SYSDATE - ' . self::HARI_PEMAKAIAN;

        return "SELECT rad_id AS rad_id_pakai, COUNT(*) AS jumlah_pakai FROM (
                    SELECT o.rad_id FROM rstxn_rjrads o JOIN rstxn_rjhdrs h ON o.rj_no = h.rj_no WHERE h.rj_date >= {$sejak}
                    UNION ALL
                    SELECT o.rad_id FROM rstxn_ugdrads o JOIN rstxn_ugdhdrs h ON o.rj_no = h.rj_no WHERE h.rj_date >= {$sejak}
                    UNION ALL
                    SELECT o.rad_id FROM rstxn_riradiologs o WHERE o.rirad_date >= {$sejak}
                ) GROUP BY rad_id";
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
            Log::warning('Cakupan LOINC radiologi gagal dihitung: ' . $e->getMessage());

            return null;
        }
    }

    /** Dipanggil saat master radiologi disimpan, supaya badge langsung mengikuti. */
    public static function lupakan(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }

    private static function hitung(): array
    {
        $master = DB::selectOne(
            "SELECT COUNT(*) AS total, SUM(CASE WHEN TRIM(loinc_code) IS NOT NULL THEN 1 ELSE 0 END) AS berkode
             FROM rsmst_radiologis
             WHERE active_status = '1' AND TRIM(rad_desc) IS NOT NULL",
        );

        // Pemakaian tetap dihitung walau tindakannya kini nonaktif: permintaannya tetap ikut terkirim.
        $pakai = DB::selectOne(
            'SELECT NVL(SUM(p.jumlah_pakai), 0) AS total,
                    NVL(SUM(CASE WHEN TRIM(m.loinc_code) IS NOT NULL THEN p.jumlah_pakai ELSE 0 END), 0) AS berkode
             FROM (' . self::sqlPemakaian() . ') p
             JOIN rsmst_radiologis m ON m.rad_id = p.rad_id_pakai',
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
