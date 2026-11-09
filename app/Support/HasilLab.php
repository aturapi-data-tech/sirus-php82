<?php

namespace App\Support;

/**
 * Nilai hasil lab setelah unit_convert. `lab_result` tersimpan dalam angka alat; yang dibaca
 * klinisi dan yang keluar dari SIRUS adalah hasil × unit_convert, aktif bila lowhigh_status = 'Y'.
 * Perbandingan Tinggi/Rendah/Kritis tetap memakai nilai alat, bukan nilai dari kelas ini.
 */
class HasilLab
{
    /** Teks hasil seperti di modal Hasil Laboratorium, mis. "4,400,000" atau "12.5". */
    public static function teksTampil(mixed $labResult, mixed $unitConvert, mixed $lowhighStatus): string
    {
        $hasil = trim((string) $labResult);
        if ($hasil === '' || !is_numeric($hasil) || !self::konversiAktif($lowhighStatus)) {
            return $hasil;
        }

        $pengali = self::pengali($unitConvert);
        $nilai = (float) $hasil * $pengali;

        return $pengali >= 1000
            ? number_format($nilai, 0, ',', ',')
            : rtrim(rtrim(number_format(round($nilai, 2), 2, '.', ''), '0'), '.');
    }

    /** Nilai angka untuk dikirim keluar (FHIR valueQuantity); null bila hasil bukan angka. */
    public static function nilaiKirim(mixed $labResult, mixed $unitConvert, mixed $lowhighStatus): ?float
    {
        $hasil = str_replace(',', '.', trim((string) $labResult));
        if (!is_numeric($hasil)) {
            return null;
        }

        return round((float) $hasil * (self::konversiAktif($lowhighStatus) ? self::pengali($unitConvert) : 1), 4);
    }

    private static function konversiAktif(mixed $lowhighStatus): bool
    {
        return $lowhighStatus === 'Y';
    }

    private static function pengali(mixed $unitConvert): float
    {
        return floatval($unitConvert ?? 1) ?: 1;
    }
}
