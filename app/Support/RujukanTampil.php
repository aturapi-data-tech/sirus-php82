<?php

namespace App\Support;

/**
 * Perapian angka kandidat rujukan untuk DITAMPILKAN.
 *
 * Ditaruh terpisah karena dipakai dua jalur yang traitnya berbeda: Ranap/IGD
 * lewat SatuSehatRujukanTrait, Rawat Jalan lewat SisruteTrait. Menyalin
 * logikanya ke dua tempat berarti satuan & ambangnya bisa berbeda diam-diam.
 */
final class RujukanTampil
{
    /** Setengah keliling bumi — jarak di atas ini mustahil untuk rujukan pasien. */
    private const BATAS_KM = 20015.0;

    /** Seminggu perjalanan; di atas ini jelas bukan estimasi tempuh. */
    private const BATAS_MENIT = 10080.0;

    /**
     * SATUSEHAT/BPJS kadang mengirim 1.7976931348623E+308 (nilai float terbesar —
     * penanda "tak terhitung", bukan jarak) yang kalau dicetak apa adanya jadi
     * sampah di layar.
     *
     * Yang disaring HANYA nilai mustahil. Angka yang cuma MENCURIGAKAN sengaja
     * dibiarkan tampil apa adanya (pernah terlihat 634 km untuk RS sekota) —
     * itu data pusat; menyembunyikannya dengan ambang karangan justru menutupi
     * masalah yang perlu dilaporkan.
     */
    public static function jarak($nilai): string
    {
        $angka = self::angkaWajar($nilai, self::BATAS_KM);

        return $angka === null ? '—' : rtrim(rtrim(number_format($angka, 1, ',', '.'), '0'), ',') . ' km';
    }

    public static function waktu($nilai): string
    {
        $angka = self::angkaWajar($nilai, self::BATAS_MENIT);

        return $angka === null ? '—' : number_format(round($angka), 0, ',', '.') . ' menit';
    }

    private static function angkaWajar($nilai, float $batas): ?float
    {
        if ($nilai === null || $nilai === '' || !is_numeric($nilai)) {
            return null;
        }

        $angka = (float) $nilai;
        if (!is_finite($angka) || $angka < 0 || $angka > $batas) {
            return null;
        }

        return $angka;
    }
}
