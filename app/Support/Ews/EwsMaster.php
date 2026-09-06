<?php

namespace App\Support\Ews;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pembaca master EWS (RSMST_EWS_PARAMS + RENTANGS + RESPONS) → array untuk EwsSkor.
 *
 * Di-cache 10 menit karena dibaca tiap kali form Observasi Lanjutan dibuka;
 * modul master memanggil flush() setelah menyimpan.
 *
 * Bila tabel belum ada di environment (DDL belum dijalankan), muat() mengembalikan
 * master KOSONG tanpa melempar — Observasi Lanjutan tetap bisa dipakai, hanya
 * skornya tidak dihitung (EwsSkor::hitung → 'tersedia' = false).
 */
class EwsMaster
{
    private const CACHE_KEY = 'ews-master-v1';
    private const CACHE_TTL = 600;

    public static function muat(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $master = self::bacaDb();
        } catch (QueryException) {
            // Tabel belum ada / DB bermasalah — jangan di-cache, coba lagi request berikutnya.
            return ['params' => [], 'respons' => [], 'tersedia' => false];
        }

        Cache::put(self::CACHE_KEY, $master, self::CACHE_TTL);

        return $master;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * PK baru = MAX+1 (pola master-jasa-medis). Panggil DI DALAM DB::transaction
     * pemanggil. Tanpa sequence Oracle supaya netral driver (bisa diuji di sqlite);
     * volume tulis master ini kecil sehingga tabrakan praktis tak terjadi.
     */
    public static function idBaru(string $tabel, string $kolomPk): int
    {
        return (int) (DB::table($tabel)->max($kolomPk) ?? 0) + 1;
    }

    /** Daftar varian: kode → label. Varian yang ada di master saja, urut baku. */
    public static function varianTersedia(array $master): array
    {
        $ada = array_unique(array_column($master['params'] ?? [], 'varian'));

        return array_filter(EwsDefault::VARIAN, fn($label, $kode) => in_array($kode, $ada, true), ARRAY_FILTER_USE_BOTH);
    }

    /** Opsi pilihan satu parameter: [kode => label]. Kosong bila param bukan PILIHAN. */
    public static function pilihan(array $master, string $varian, string $kode): array
    {
        foreach (EwsSkor::paramsVarian($master, $varian) as $param) {
            if ($param['param_kode'] !== $kode || $param['tipe'] !== 'PILIHAN') {
                continue;
            }
            $opsi = [];
            foreach ($param['rentang'] as $rentang) {
                $opsi[(string) $rentang['pilihan_kode']] = (string) $rentang['pilihan_desc'];
            }

            return $opsi;
        }

        return [];
    }

    /** Parameter varian yang ikut diskor, dikelompokkan per kode (tanpa REFERENSI). */
    public static function paramsDiskor(array $master, string $varian): array
    {
        $hasil = [];
        foreach (EwsSkor::paramsVarian($master, $varian) as $param) {
            if ($param['tipe'] === 'REFERENSI') {
                continue;
            }
            $hasil[$param['param_kode']] = $param;
        }

        return $hasil;
    }

    private static function bacaDb(): array
    {
        $params = DB::table('rsmst_ews_params')
            ->select('param_id', 'varian', 'param_kode', 'param_desc', 'tipe', 'satuan', 'urutan', 'wajib', 'gantikan_kode', 'active_status')
            ->orderBy('varian')->orderBy('urutan')
            ->get();

        $rentangs = DB::table('rsmst_ews_rentangs')
            ->select('rentang_id', 'param_id', 'urutan', 'batas_bawah', 'batas_atas', 'pilihan_kode', 'pilihan_desc', 'syarat', 'usia_min_bln', 'usia_max_bln', 'skor')
            ->orderBy('param_id')->orderBy('urutan')
            ->get()
            ->groupBy('param_id');

        $respons = DB::table('rsmst_ews_respons')
            ->select('respon_id', 'varian', 'urutan', 'skor_min', 'skor_max', 'param_merah', 'kategori', 'warna', 'frekuensi', 'frekuensi_menit', 'respon')
            ->orderBy('varian')->orderBy('urutan')
            ->get();

        $daftarParam = [];
        foreach ($params as $param) {
            $daftarRentang = [];
            foreach ($rentangs->get($param->param_id, collect()) as $rentang) {
                $daftarRentang[] = [
                    'rentang_id'   => (int) $rentang->rentang_id,
                    'urutan'       => (int) $rentang->urutan,
                    'batas_bawah'  => $rentang->batas_bawah === null ? null : (float) $rentang->batas_bawah,
                    'batas_atas'   => $rentang->batas_atas === null ? null : (float) $rentang->batas_atas,
                    'pilihan_kode' => $rentang->pilihan_kode,
                    'pilihan_desc' => $rentang->pilihan_desc,
                    'syarat'       => $rentang->syarat,
                    'usia_min_bln' => $rentang->usia_min_bln === null ? null : (int) $rentang->usia_min_bln,
                    'usia_max_bln' => $rentang->usia_max_bln === null ? null : (int) $rentang->usia_max_bln,
                    'skor'         => (int) $rentang->skor,
                ];
            }
            $daftarParam[] = [
                'param_id'      => (int) $param->param_id,
                'varian'        => (string) $param->varian,
                'param_kode'    => (string) $param->param_kode,
                'param_desc'    => (string) $param->param_desc,
                'tipe'          => (string) $param->tipe,
                'satuan'        => $param->satuan,
                'urutan'        => (int) $param->urutan,
                'wajib'         => (string) $param->wajib,
                'gantikan_kode' => $param->gantikan_kode,
                'active_status' => (string) $param->active_status,
                'rentang'       => $daftarRentang,
            ];
        }

        $daftarRespon = [];
        foreach ($respons as $respon) {
            $daftarRespon[] = [
                'respon_id'       => (int) $respon->respon_id,
                'varian'          => (string) $respon->varian,
                'urutan'          => (int) $respon->urutan,
                'skor_min'        => $respon->skor_min === null ? null : (int) $respon->skor_min,
                'skor_max'        => $respon->skor_max === null ? null : (int) $respon->skor_max,
                'param_merah'     => (string) $respon->param_merah,
                'kategori'        => (string) $respon->kategori,
                'warna'           => (string) $respon->warna,
                'frekuensi'       => (string) $respon->frekuensi,
                'frekuensi_menit' => $respon->frekuensi_menit === null ? null : (int) $respon->frekuensi_menit,
                'respon'          => (string) $respon->respon,
            ];
        }

        return ['params' => $daftarParam, 'respons' => $daftarRespon, 'tersedia' => $daftarParam !== []];
    }
}
