<?php

namespace App\Support\Terminologi;

use Illuminate\Support\Facades\DB;

/**
 * KodeIm — penanda kode ICD-10 Indonesian Modification (IM).
 *
 * Kode IM dipakai grouper iDRG, tetapi DITOLAK E-Klaim INACBG. Masalahnya di master
 * 1.413 dari 1.416 kode IM ber-`valid_code`=1, jadi guard "valid_code" di LOV
 * meloloskannya — kode IM baru ketahuan salah setelah klaim dikirim dan dibalas
 * validcode=0. Karena itu penandaannya dipakai di tiga tempat:
 *
 * - LOV diagnosa   : prop `blockIm` → tandai di dropdown + tolak di choose()
 * - Coder INACBG   : guard server-side saat add() / sync dari iDRG
 * - Tampilan coder : badge "IM tidak berlaku" pada baris hasil
 *
 * Ditaruh sebagai helper statis (bukan trait) karena lintas komponen — pola sama
 * dengan App\Support\LogText.
 *
 * Dua sumber penanda, keduanya harus dicek: flag master `im`=1 DAN deskripsi
 * berakhiran "(IM)" — sebagian baris seed E-Klaim hanya membawa penanda di
 * deskripsinya, sebagian lagi hanya di kolom `im`.
 */
class KodeIm
{
    /**
     * Cek dari data yang sudah di tangan (payload LOV / baris coder).
     *
     * @param array<string, mixed> $baris
     */
    public static function adalah(array $baris): bool
    {
        if ((int) ($baris['im'] ?? 0) === 1) {
            return true;
        }

        $deskripsi = (string) ($baris['diag_desc'] ?? $baris['description'] ?? $baris['desc'] ?? '');

        return self::deskripsiBerakhiranIm($deskripsi);
    }

    /**
     * Cek ke master untuk satu kode (icdx ATAU diag_id).
     *
     * Sengaja memakai exists() atas SEMUA baris berkode sama: satu icdx bisa punya
     * baris kembar (seed E-Klaim + legacy) dan baris legacy sering ber-`im`=0
     * default. Untuk keputusan MEMBLOKIR, cukup satu baris menandai IM.
     */
    public static function adalahKode(string $kode, string $deskripsi = ''): bool
    {
        $kode = trim($kode);

        if ($kode === '') {
            return false;
        }

        if (self::deskripsiBerakhiranIm($deskripsi)) {
            return true;
        }

        return DB::table('rsmst_mstdiags')
            ->where(function ($subQuery) use ($kode) {
                $subQuery->where('icdx', $kode)->orWhere('diag_id', $kode);
            })
            ->where('im', 1)
            ->exists();
    }

    private static function deskripsiBerakhiranIm(string $deskripsi): bool
    {
        return (bool) preg_match('/\(IM\)\s*$/i', $deskripsi);
    }
}
