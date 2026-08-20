<?php

namespace App\Support;

/**
 * Peringkas perubahan field untuk log aktivitas.
 *
 * Log yang cuma berbunyi "data diubah" praktis tidak berguna saat ditelusuri
 * belakangan; yang dicari orang selalu "field apa, dari apa, jadi apa". Helper ini
 * membandingkan dua potret data lalu menyusunnya jadi satu kalimat.
 *
 * Statis, bukan trait: dipakai daftar RJ, UGD, dan RI yang masing-masing memakai
 * trait EMR berbeda. Pola yang sama seperti [[LogText]] dan [[TaskIdAntrean]].
 */
class LogPerubahan
{
    /**
     * @param  array  $sebelum  potret data sebelum diubah
     * @param  array  $sesudah  potret data sesudah diubah
     * @param  array  $label    peta [kunci data => label yang dibaca manusia];
     *                          hanya kunci di peta ini yang diperiksa, supaya
     *                          field teknis (sep, taskIdPelayanan, *Status)
     *                          tidak ikut membisingkan log
     * @return string           '' bila tidak ada yang berubah
     */
    public static function ringkas(array $sebelum, array $sesudah, array $label): string
    {
        $bagian = [];

        foreach ($label as $kunci => $namaField) {
            $lama = self::teks($sebelum[$kunci] ?? null);
            $baru = self::teks($sesudah[$kunci] ?? null);

            if ($lama === $baru) {
                continue;
            }

            $bagian[] = $namaField . ': ' . ($lama === '' ? '(kosong)' : $lama)
                . ' -> ' . ($baru === '' ? '(kosong)' : $baru);
        }

        return implode('; ', $bagian);
    }

    /**
     * Normalisasi nilai jadi teks pembanding.
     *
     * Perbandingan sengaja dilakukan sebagai STRING, bukan longgar (==): '0' dan ''
     * dan null harus terbaca berbeda. Nilai bukan skalar (array/objek) dianggap
     * tidak terbandingkan dan dilaporkan kosong — field seperti itu memang tidak
     * pantas masuk peta label.
     */
    private static function teks(mixed $nilai): string
    {
        if ($nilai === null || is_array($nilai) || is_object($nilai)) {
            return '';
        }

        if (is_bool($nilai)) {
            return $nilai ? '1' : '0';
        }

        return trim((string) $nilai);
    }
}
