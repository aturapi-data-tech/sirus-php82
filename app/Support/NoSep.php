<?php

namespace App\Support;

/**
 * Ekstraksi No. SEP dari kolom RSTXN_RJHDRS.VNO_SEP.
 *
 * KENAPA PERLU. Kolom itu bukan kolom nomor — petugas memakainya sebagai CATATAN
 * BEBAS sejak sistem lama. Sebaran isinya pada Juli 2026 (2.594 kunjungan ber-isi):
 *
 *   19 karakter  2.464  0184R0060726V001524          ← nomor SEP sah
 *   24 karakter     24  ITER/0184R0060626V000043     ← SEP + penanda iterasi
 *                       0184R0060626V000617/ITER        (penanda bisa di depan/belakang)
 *    3-13 karakter  87  ITER · ITER 1X · ITTER · BATAL · LAB · INR · INTERNAL
 *                       RUJUKAN HABIS                ← sama sekali bukan SEP
 *
 * Memakai isi kolom apa adanya sebagai REFASALSJP membuat 111 baris terkirim salah:
 * yang bergabung dengan "ITER" jadi 24 karakter, dan yang teks bebas jelas bukan
 * nomor. Validator apotek_sep() sendiri menuntut TEPAT 19 karakter, jadi keduanya
 * ditolak — tapi baru ketahuan setelah petugas menekan Kirim.
 *
 * POLANYA. 19 karakter: 4 digit kode faskes, 1 huruf, 3 digit, 4 digit bulan-tahun,
 * 1 huruf, 6 digit urut. Contoh 0184R0060726V001524.
 *
 * Jangan menyaring dengan LENGTH(TRIM(vno_sep)) = 19 — itu membuang 24 baris yang
 * SEP-nya sebenarnya ada, cuma bergabung dengan penanda ITER.
 */
final class NoSep
{
    private const POLA = '/[0-9]{4}[A-Z][0-9]{3}[0-9]{4}[A-Z][0-9]{6}/';

    /** Nomor SEP di dalam isi kolom, atau '' bila tidak ada. */
    public static function ekstrak(?string $isiKolom): string
    {
        $isi = strtoupper(trim((string) $isiKolom));

        if ($isi === '') {
            return '';
        }

        return preg_match(self::POLA, $isi, $cocok) === 1 ? $cocok[0] : '';
    }

    /** Kolom ini memuat nomor SEP yang bisa dikirim ke BPJS? */
    public static function sah(?string $isiKolom): bool
    {
        return self::ekstrak($isiKolom) !== '';
    }

    /**
     * Isi kolom yang BUKAN bagian dari nomor SEP — mis. "ITER" pada
     * "ITER/0184R0060626V000043". Dipakai untuk memberi tahu petugas bahwa
     * catatan itu ada, tanpa ikut mengirimkannya.
     */
    public static function catatan(?string $isiKolom): string
    {
        $isi = strtoupper(trim((string) $isiKolom));
        $sep = self::ekstrak($isi);

        if ($isi === '' || $sep === '') {
            return $isi;
        }

        return trim(str_replace($sep, '', $isi), " \t\n\r\0\x0B/-|,;");
    }
}
