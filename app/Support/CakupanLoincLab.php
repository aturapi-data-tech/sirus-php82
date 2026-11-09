<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cakupan LOINC pada master item lab: dasar badge topbar dan filter "belum LOINC" di Master Lab.
 *
 * Dua angka sengaja dipisah. Persen master = item yang sudah ber-LOINC. Persen pakai = hasil lab
 * 90 hari terakhir yang itemnya ber-LOINC, yaitu yang menentukan isi kiriman SATUSEHAT.
 * lbmst_clabitems tak punya active_status dan kolom status-nya bukan penanda aktif (item ber-status
 * 0 masih dipakai), jadi yang dihitung = tidak hidden, bukan paket, dan bernama.
 */
class CakupanLoincLab
{
    public const HARI_PEMAKAIAN = 90;

    private const KUNCI_CACHE = 'cakupan-loinc-lab.ringkasan';
    private const DETIK_CACHE = 3600;

    /** Syarat item yang dihitung. Alias diisi bila dipakai di query ber-join yang kolomnya bisa tertukar. */
    public static function syaratItem(string $alias = ''): string
    {
        $kolom = $alias === '' ? '' : "{$alias}.";

        return "NVL({$kolom}hidden_status, 'N') NOT IN ('Y', '1') AND NVL({$kolom}is_group, 'N') NOT IN ('Y', '1') AND TRIM({$kolom}clabitem_desc) IS NOT NULL";
    }

    /**
     * Jumlah hasil 90 hari terakhir per item: kolom clabitem_id_pakai + jumlah_pakai. Hasil terisi dan
     * paket bukan Terdaftar/Batal, sama dengan yang berangkat di kirim-lab. Tanpa binding: dipasang
     * sebagai join di query yang punya binding pencarian (urutan binding Oracle tak boleh tertukar).
     */
    public static function sqlPemakaian(): string
    {
        $sejak = 'SYSDATE - ' . self::HARI_PEMAKAIAN;

        return "SELECT d.clabitem_id AS clabitem_id_pakai, COUNT(*) AS jumlah_pakai
                FROM lbtxn_checkupdtls d
                JOIN lbtxn_checkuphdrs h ON d.checkup_no = h.checkup_no
                WHERE h.checkup_date >= {$sejak} AND h.checkup_status NOT IN ('P', 'F') AND TRIM(d.lab_result) IS NOT NULL
                GROUP BY d.clabitem_id";
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
            Log::warning('Cakupan LOINC lab gagal dihitung: ' . $e->getMessage());

            return null;
        }
    }

    /** Dipanggil saat master item lab disimpan, supaya badge langsung mengikuti. */
    public static function lupakan(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }

    private static function hitung(): array
    {
        $master = DB::selectOne(
            'SELECT COUNT(*) AS total, SUM(CASE WHEN TRIM(m.loinc_code) IS NOT NULL THEN 1 ELSE 0 END) AS berkode
             FROM lbmst_clabitems m
             WHERE ' . self::syaratItem('m'),
        );

        $pakai = DB::selectOne(
            'SELECT NVL(SUM(p.jumlah_pakai), 0) AS total,
                    NVL(SUM(CASE WHEN TRIM(m.loinc_code) IS NOT NULL THEN p.jumlah_pakai ELSE 0 END), 0) AS berkode
             FROM (' . self::sqlPemakaian() . ') p
             JOIN lbmst_clabitems m ON m.clabitem_id = p.clabitem_id_pakai
             WHERE ' . self::syaratItem('m'),
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
