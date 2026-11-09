<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cakupan mapping KFA pada master obat: dasar halaman Mapping SATUSEHAT dan filter "belum KFA" di Master Obat.
 *
 * Dua angka sengaja dipisah. Persen master = obat aktif yang sudah ber-KFA. Persen pakai = baris
 * resep 90 hari terakhir yang obatnya ber-KFA, yaitu yang menentukan isi kiriman SATUSEHAT.
 * Alkes, lab, radiologi, oksigen, dan umum tidak dihitung.
 */
class CakupanKfa
{
    /** immst_catproducts: 1 Obat Ringan, 2 Obat Keras, 3 Narkotik, 4 Psikotropika. */
    public const KATEGORI_OBAT = ['1', '2', '3', '4'];

    public const HARI_PEMAKAIAN = 90;

    private const KUNCI_CACHE = 'cakupan-kfa.ringkasan';
    private const DETIK_CACHE = 3600;

    /**
     * Jumlah baris resep 90 hari terakhir per obat (RJ + UGD + RI): kolom product_id_pakai + jumlah_pakai.
     * Tanpa binding: dipasang sebagai join di query yang punya binding pencarian (urutan binding
     * Oracle tak boleh tertukar).
     */
    public static function sqlPemakaian(): string
    {
        $sejak = 'SYSDATE - ' . self::HARI_PEMAKAIAN;

        return "SELECT product_id AS product_id_pakai, COUNT(*) AS jumlah_pakai FROM (
                    SELECT o.product_id FROM rstxn_rjobats o JOIN rstxn_rjhdrs h ON o.rj_no = h.rj_no WHERE h.rj_date >= {$sejak}
                    UNION ALL
                    SELECT o.product_id FROM rstxn_ugdobats o JOIN rstxn_ugdhdrs h ON o.rj_no = h.rj_no WHERE h.rj_date >= {$sejak}
                    UNION ALL
                    SELECT o.product_id FROM rstxn_riobats o WHERE o.riobat_date >= {$sejak}
                ) GROUP BY product_id";
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
            Log::warning('Cakupan KFA gagal dihitung: ' . $e->getMessage());

            return null;
        }
    }

    /** Dipanggil saat master obat disimpan, supaya angkanya langsung mengikuti. */
    public static function lupakan(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }

    private static function hitung(): array
    {
        $kategori = "'" . implode("','", self::KATEGORI_OBAT) . "'";

        $master = DB::selectOne(
            "SELECT COUNT(*) AS total, SUM(CASE WHEN TRIM(product_id_satusehat) IS NOT NULL THEN 1 ELSE 0 END) AS berkode
             FROM immst_products
             WHERE active_status = '1' AND cat_id IN ({$kategori})",
        );

        // Pemakaian tetap dihitung walau obatnya kini nonaktif: resepnya tetap ikut terkirim.
        $pakai = DB::selectOne(
            'SELECT NVL(SUM(p.jumlah_pakai), 0) AS total,
                    NVL(SUM(CASE WHEN TRIM(m.product_id_satusehat) IS NOT NULL THEN p.jumlah_pakai ELSE 0 END), 0) AS berkode
             FROM (' . self::sqlPemakaian() . ") p
             JOIN immst_products m ON m.product_id = p.product_id_pakai
             WHERE m.cat_id IN ({$kategori})",
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
