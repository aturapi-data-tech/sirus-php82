<?php

namespace App\Support;

/**
 * Perapian node anatomi di JSON EMR (pemeriksaan.anatomi / pengkajianDokter.anatomi).
 *
 * Kunci baku bagian payudara = `payudara`. JSON lama RJ, UGD, dan sebagian RI menyimpan
 * salah tulis `payudarah`, dan datanya tidak dimigrasi: kunci dirapikan saat dibaca, lalu
 * JSON ikut baku begitu kunjungan itu disimpan ulang dari form.
 */
class AnatomiPemeriksaan
{
    private const KUNCI_LAMA = 'payudarah';
    private const KUNCI_BAKU = 'payudara';

    /**
     * Ganti kunci lama menjadi kunci baku pada posisi yang sama, isinya tetap. Bila kedua
     * kunci ada, yang dipakai kunci baku kecuali kosong sementara kunci lama berisi hasil.
     */
    public static function rapikanKunci(array $anatomi): array
    {
        if (!array_key_exists(self::KUNCI_LAMA, $anatomi)) {
            return $anatomi;
        }

        $nilaiBaku = $anatomi[self::KUNCI_BAKU] ?? null;
        $nilaiTerpakai = is_array($nilaiBaku) && self::berisiHasil($nilaiBaku) ? $nilaiBaku : $anatomi[self::KUNCI_LAMA];

        $hasil = [];
        foreach ($anatomi as $kunci => $nilai) {
            if ($kunci === self::KUNCI_BAKU) {
                continue;
            }
            if ($kunci === self::KUNCI_LAMA) {
                $hasil[self::KUNCI_BAKU] = $nilaiTerpakai;
                continue;
            }
            $hasil[$kunci] = $nilai;
        }

        return $hasil;
    }

    private static function berisiHasil(array $bagian): bool
    {
        return ($bagian['kelainan'] ?? 'Tidak Diperiksa') !== 'Tidak Diperiksa' || filled($bagian['desc'] ?? null);
    }
}
