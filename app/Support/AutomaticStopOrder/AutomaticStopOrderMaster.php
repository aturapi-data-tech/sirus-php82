<?php

namespace App\Support\AutomaticStopOrder;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pembaca master Automatic Stop Order (RSMST_STOP_ORDER_GOLONGANS + RSMST_STOP_ORDER_PRODUCTS) -> array.
 *
 * Di-cache 10 menit karena kelak dibaca tiap kali PTO / display pasien RI dibuka;
 * modul master memanggil flush() setelah menyimpan. Pola sama dengan EwsMaster.
 *
 * Bila tabel belum ada di environment (DDL belum dijalankan), muat() mengembalikan
 * master KOSONG tanpa melempar — pemakai tinggal memeriksa 'tersedia'.
 */
class AutomaticStopOrderMaster
{
    private const CACHE_KEY = 'automatic-stop-order-master-v1';
    private const CACHE_TTL = 600;

    /**
     * @return array{golongan: array<int, array>, produk: array<string, array>, tersedia: bool}
     *   golongan: golongan_id => baris golongan
     *   produk:   product_id => ['golongan_id', 'catatan', 'active_status']
     */
    public static function muat(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $master = self::bacaDb();
        } catch (QueryException) {
            return ['golongan' => [], 'produk' => [], 'tersedia' => false];
        }

        Cache::put(self::CACHE_KEY, $master, self::CACHE_TTL);

        return $master;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** PK baru = MAX+1 di dalam DB::transaction pemanggil (pola EwsMaster::idBaru). */
    public static function idBaru(string $tabel, string $kolomPk): int
    {
        return (int) (DB::table($tabel)->max($kolomPk) ?? 0) + 1;
    }

    /**
     * Golongan AKTIF untuk sebuah obat, atau null bila obat tidak dipetakan /
     * pemetaannya nonaktif / golongannya nonaktif. Ini satu-satunya pintu bagi
     * mesin Automatic Stop Order untuk bertanya "obat ini kena Automatic Stop Order berapa hari?".
     */
    public static function golonganUntukProduk(array $master, ?string $productId): ?array
    {
        $productId = trim((string) $productId);
        if ($productId === '') {
            return null;
        }

        $pemetaan = $master['produk'][$productId] ?? null;
        if ($pemetaan === null || ($pemetaan['active_status'] ?? '1') !== '1') {
            return null;
        }

        $golongan = $master['golongan'][$pemetaan['golongan_id']] ?? null;
        if ($golongan === null || ($golongan['active_status'] ?? '1') !== '1') {
            return null;
        }

        return [...$golongan, 'catatan_produk' => $pemetaan['catatan']];
    }

    private static function bacaDb(): array
    {
        $golongans = DB::table('rsmst_stop_order_golongans')
            ->select('golongan_id', 'golongan_kode', 'golongan_nama', 'batas_hari', 'batas_minimal_hari', 'keterangan', 'urutan', 'active_status')
            ->orderBy('urutan')->orderBy('golongan_id')
            ->get();

        $produks = DB::table('rsmst_stop_order_products')
            ->select('product_id', 'golongan_id', 'catatan', 'active_status')
            ->get();

        $daftarGolongan = [];
        foreach ($golongans as $golongan) {
            $daftarGolongan[(int) $golongan->golongan_id] = [
                'golongan_id'        => (int) $golongan->golongan_id,
                'golongan_kode'      => (string) $golongan->golongan_kode,
                'golongan_nama'      => (string) $golongan->golongan_nama,
                'batas_hari'    => (int) $golongan->batas_hari,
                'batas_minimal_hari' => $golongan->batas_minimal_hari === null ? null : (int) $golongan->batas_minimal_hari,
                'keterangan'    => (string) ($golongan->keterangan ?? ''),
                'urutan'        => (int) $golongan->urutan,
                'active_status' => (string) $golongan->active_status,
            ];
        }

        $daftarProduk = [];
        foreach ($produks as $produk) {
            $daftarProduk[(string) $produk->product_id] = [
                'golongan_id'        => (int) $produk->golongan_id,
                'catatan'       => (string) ($produk->catatan ?? ''),
                'active_status' => (string) $produk->active_status,
            ];
        }

        return ['golongan' => $daftarGolongan, 'produk' => $daftarProduk, 'tersedia' => $daftarGolongan !== []];
    }
}
