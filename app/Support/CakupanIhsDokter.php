<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cakupan IHS (Practitioner) pada master dokter: dasar halaman Mapping SATUSEHAT dan filter "belum IHS" di Master Dokter.
 *
 * Dua angka sengaja dipisah. Persen master = dokter aktif yang sudah punya dr_uuid. Persen pakai =
 * kunjungan 90 hari terakhir yang dokternya punya dr_uuid; yang kosong tak bisa dikirim ke SATUSEHAT.
 */
class CakupanIhsDokter
{
    public const HARI_PEMAKAIAN = 90;

    private const KUNCI_CACHE = 'cakupan-ihs-dokter.ringkasan';
    private const DETIK_CACHE = 3600;

    /**
     * Jumlah kunjungan 90 hari terakhir per dokter (RJ + UGD + RI, tanpa Batal): kolom dr_id_pakai + jumlah_pakai.
     * Tanpa binding: dipasang sebagai join di query yang punya binding pencarian (urutan binding
     * Oracle tak boleh tertukar).
     */
    public static function sqlPemakaian(): string
    {
        $sejak = 'SYSDATE - ' . self::HARI_PEMAKAIAN;

        return "SELECT dr_id AS dr_id_pakai, COUNT(*) AS jumlah_pakai FROM (
                    SELECT dr_id FROM rstxn_rjhdrs WHERE rj_date >= {$sejak} AND NVL(rj_status, 'X') <> 'F'
                    UNION ALL
                    SELECT dr_id FROM rstxn_ugdhdrs WHERE rj_date >= {$sejak} AND NVL(rj_status, 'X') <> 'F'
                    UNION ALL
                    SELECT dr_id FROM rstxn_rihdrs WHERE entry_date >= {$sejak} AND NVL(ri_status, 'X') <> 'F'
                ) GROUP BY dr_id";
    }

    /**
     * Ringkasan tersimpan sejam. Null bila gagal dihitung: halaman Mapping SATUSEHAT
     * menandai baris ini saja, baris lain tetap tampil.
     *
     * @return array{total: int, berkode: int, persen: float, pakaiTotal: int, pakaiBerkode: int, persenPakai: float}|null
     */
    public static function ringkasan(): ?array
    {
        try {
            return Cache::remember(self::KUNCI_CACHE, self::DETIK_CACHE, fn() => self::hitung());
        } catch (\Throwable $e) {
            Log::warning('Cakupan IHS dokter gagal dihitung: ' . $e->getMessage());

            return null;
        }
    }

    /** Dipanggil saat master disimpan, supaya angkanya langsung mengikuti. */
    public static function lupakan(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }

    private static function hitung(): array
    {
        $master = DB::selectOne(
            "SELECT COUNT(*) AS total, SUM(CASE WHEN TRIM(dr_uuid) IS NOT NULL THEN 1 ELSE 0 END) AS berkode
             FROM rsmst_doctors
             WHERE active_status = '1' AND TRIM(dr_name) IS NOT NULL",
        );

        // Pemakaian tetap dihitung walau masternya kini nonaktif: kunjungannya tetap ikut terkirim.
        $pakai = DB::selectOne(
            'SELECT NVL(SUM(p.jumlah_pakai), 0) AS total,
                    NVL(SUM(CASE WHEN TRIM(m.dr_uuid) IS NOT NULL THEN p.jumlah_pakai ELSE 0 END), 0) AS berkode
             FROM (' . self::sqlPemakaian() . ') p
             JOIN rsmst_doctors m ON m.dr_id = p.dr_id_pakai',
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
