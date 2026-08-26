<?php

namespace App\Support\Options;

/**
 * Sumber tunggal daftar pilihan & ambang PEMANTAUAN SUHU ruang server — model 1
 * dari dua model Pemantauan Ruang Server (Akreditasi MRMIK 2.2 - Perlindungan Data).
 *
 * Model 2 (siapa keluar-masuk ruang server dan untuk apa) punya daftar pilihannya
 * sendiri di AksesRuangServerOptions. Identitas fisik ruangnya (nama, gedung,
 * perangkat, kapasitas AC) dipakai KEDUA modul, jadi tinggal di RuangServerOptions
 * — bukan disalin ke sini.
 *
 * HANYA SUHU. Formulir kertas menyebut standar kelembaban (40%-60% RH), tapi RS
 * belum punya thermohygrometer — jadi kelembaban tidak direkam sama sekali,
 * bukan direkam sebagai kolom kosong yang menuntut diisi. Kalau alatnya kelak
 * ada, yang perlu ditambah: kolom 'kelembaban' di entri, dua ambang standarnya
 * di node ruang, dan satu cabang di hitungKondisi().
 *
 * Dipakai bersama oleh formulir entry, kartu ringkasan, dan blade cetak - supaya
 * label tak pernah berbeda antara layar dan kertas.
 */
class SuhuRuangServerOptions
{
    /** Standar suhu ruang server menurut formulir: 18-27 derajat Celcius (ASHRAE / SNI). */
    public const SUHU_MIN_DEFAULT = '18';

    public const SUHU_MAX_DEFAULT = '27';

    /** Alat ukur baku. Formulir menulis "Thermohygrometer / Thermometer ruangan"
     *  - yang dipakai di sini thermometer saja, sesuai alat yang benar-benar ada. */
    public const ALAT_UKUR_DEFAULT = 'Thermometer ruangan';

    /**
     * Format waktu satu baris pemantauan: "dd/mm/yyyy HH:MM:SS".
     *
     * Tanggal & jam disatukan jadi SATU field — petugas mencatat satu momen
     * pengukuran, bukan dua keterangan terpisah, dan tombol jam mengisinya
     * sekali klik. Cetakannya tetap dua kolom (Tanggal | Jam) karena begitulah
     * bentuk formulir kertasnya - dipecah saat mencetak lewat pecahWaktu().
     */
    public const FORMAT_WAKTU = 'd/m/Y H:i:s';

    /**
     * Status AC saat pemantauan. Kunci sengaja pendek & stabil: yang tersimpan di
     * JSON adalah kuncinya, jadi redaksi label boleh diperbaiki tanpa merusak
     * record lama.
     */
    public const STATUS_AC = [
        'normal' => 'Normal / menyala',
        'sebagian' => 'Menyala sebagian',
        'mati' => 'Mati',
        'gangguan' => 'Gangguan / tidak dingin',
        'perbaikan' => 'Dalam perbaikan',
    ];

    /**
     * Kondisi ruang. 'N' = Normal (di dalam rentang standar), 'TN' = Tidak Normal
     * (di luar rentang, wajib tindak lanjut). Nilainya DIHITUNG, bukan dipilih -
     * daftar ini hanya untuk melabelinya di layar & kertas.
     */
    public const KONDISI = [
        'N' => 'Normal',
        'TN' => 'Tidak Normal',
    ];

    /**
     * "dd/mm/yyyy HH:MM:SS" -> ['tanggal' => 'dd/mm/yyyy', 'jam' => 'HH:MM:SS'].
     *
     * Dipakai cetakan & tabel layar. Teks yang tak sesuai bentuk dikembalikan
     * apa adanya di 'tanggal' - lebih baik tercetak utuh walau janggal daripada
     * hilang diam-diam.
     *
     * @return array{tanggal: string, jam: string}
     */
    public static function pecahWaktu(?string $waktu): array
    {
        $teks = trim((string) $waktu);

        if ($teks === '') {
            return ['tanggal' => '', 'jam' => ''];
        }

        $bagian = explode(' ', $teks, 2);

        return [
            'tanggal' => $bagian[0],
            'jam' => $bagian[1] ?? '',
        ];
    }

    /** Kunci status AC -> label terbaca. Kunci tak dikenal jadi tanda hubung, bukan kunci mentah. */
    public static function labelStatusAc(?string $kunci): string
    {
        return self::STATUS_AC[$kunci] ?? '-';
    }

    /** Kunci kondisi -> label terbaca. */
    public static function labelKondisi(?string $kunci): string
    {
        return self::KONDISI[$kunci] ?? '-';
    }

    /**
     * Kondisi N/TN dari suhu satu entri terhadap standar lembar.
     *
     * Entri tanpa suhu terbaca dianggap Tidak Normal supaya baris setengah terisi
     * tidak lolos sebagai "aman".
     *
     * @param  array  $entri  entri harian ('suhu')
     * @param  array  $ruang  Bagian A ('standarSuhuMin', 'standarSuhuMax')
     */
    public static function hitungKondisi(array $entri, array $ruang): string
    {
        $suhu = self::angka($entri['suhu'] ?? null);

        if ($suhu === null) {
            return 'TN';
        }

        $suhuMin = self::angka($ruang['standarSuhuMin'] ?? null) ?? (float) self::SUHU_MIN_DEFAULT;
        $suhuMax = self::angka($ruang['standarSuhuMax'] ?? null) ?? (float) self::SUHU_MAX_DEFAULT;

        return ($suhu < $suhuMin || $suhu > $suhuMax) ? 'TN' : 'N';
    }

    /**
     * Teks angka -> float, atau null bila tak terbaca.
     *
     * Koma diterima sebagai pemisah desimal: petugas mengetik "22,5" sesering
     * "22.5". Nol adalah nilai yang SAH - karena itu pengujiannya lewat
     * is_numeric, bukan empty(): suhu "0" tak boleh diam-diam jadi "tak diisi".
     */
    public static function angka(mixed $nilai): ?float
    {
        if ($nilai === null) {
            return null;
        }

        $teks = str_replace(',', '.', trim((string) $nilai));

        return is_numeric($teks) ? (float) $teks : null;
    }
}
