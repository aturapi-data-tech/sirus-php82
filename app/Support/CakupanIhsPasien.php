<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cakupan IHS pada pasien yang berkunjung: dasar badge topbar dan filter "belum IHS" di Master Pasien.
 *
 * Sengaja tidak memakai seluruh rsmst_pasiens: sebagian besar pasien lama tak datang lagi, jadi angkanya
 * menyesatkan. Persen = pasien berkunjung 90 hari terakhir yang punya patient_uuid. Persen pakai =
 * kunjungan 90 hari terakhir yang pasiennya punya patient_uuid; yang kosong tak bisa dikirim ke SATUSEHAT.
 */
class CakupanIhsPasien
{
    public const HARI_PEMAKAIAN = 90;

    private const KUNCI_CACHE = 'cakupan-ihs-pasien.ringkasan';
    private const DETIK_CACHE = 3600;

    /**
     * Jumlah kunjungan 90 hari terakhir per pasien (RJ + UGD + RI, tanpa Batal): kolom reg_no_pakai + jumlah_pakai.
     * Tanpa binding: dipasang sebagai join di query yang punya binding pencarian (urutan binding
     * Oracle tak boleh tertukar).
     */
    public static function sqlPemakaian(): string
    {
        $sejak = 'SYSDATE - ' . self::HARI_PEMAKAIAN;

        return "SELECT reg_no AS reg_no_pakai, COUNT(*) AS jumlah_pakai FROM (
                    SELECT reg_no FROM rstxn_rjhdrs WHERE rj_date >= {$sejak} AND NVL(rj_status, 'X') <> 'F'
                    UNION ALL
                    SELECT reg_no FROM rstxn_ugdhdrs WHERE rj_date >= {$sejak} AND NVL(rj_status, 'X') <> 'F'
                    UNION ALL
                    SELECT reg_no FROM rstxn_rihdrs WHERE entry_date >= {$sejak} AND NVL(ri_status, 'X') <> 'F'
                ) GROUP BY reg_no";
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
            Log::warning('Cakupan IHS pasien gagal dihitung: ' . $e->getMessage());

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
        $baris = DB::selectOne(
            'SELECT COUNT(*) AS pasien_total,
                    SUM(CASE WHEN TRIM(m.patient_uuid) IS NOT NULL THEN 1 ELSE 0 END) AS pasien_berkode,
                    NVL(SUM(p.jumlah_pakai), 0) AS kunjungan_total,
                    NVL(SUM(CASE WHEN TRIM(m.patient_uuid) IS NOT NULL THEN p.jumlah_pakai ELSE 0 END), 0) AS kunjungan_berkode
             FROM (' . self::sqlPemakaian() . ') p
             JOIN rsmst_pasiens m ON m.reg_no = p.reg_no_pakai',
        );

        $master = (object) ['total' => $baris->pasien_total, 'berkode' => $baris->pasien_berkode];
        $pakai = (object) ['total' => $baris->kunjungan_total, 'berkode' => $baris->kunjungan_berkode];

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
