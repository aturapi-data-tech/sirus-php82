<?php

namespace App\Support\Options;

/**
 * Sumber tunggal Kriteria Robson (Klasifikasi 10 Kelompok, WHO 2017).
 *
 * Kelompok TIDAK dipilih petugas: ia diturunkan dari enam variabel obstetri
 * (paritas, riwayat SC, awal persalinan, jumlah janin, usia kehamilan, presentasi
 * janin) lewat tentukanKelompok(). Dipakai form EMR (modul dokumen VK), cetak PDF,
 * dan viewer Rekam Medis — label jangan diduplikasi di tempat lain.
 *
 * Hasil kelompok DISIMPAN di entri saat simpan/TTD (bukan dihitung ulang saat
 * tampil), supaya entri lama tidak berubah bila aturan di sini direvisi.
 */
class KriteriaRobsonOptions
{
    public const PARITAS = [
        'nulipara' => 'Nulipara (belum pernah melahirkan)',
        'multipara' => 'Multipara (pernah melahirkan)',
    ];

    public const RIWAYAT_SC = [
        'tidak' => 'Tidak ada bekas SC',
        'satu' => 'Satu kali bekas SC',
        'duaAtauLebih' => 'Dua kali atau lebih bekas SC',
    ];

    public const AWAL_PERSALINAN = [
        'spontan' => 'Persalinan spontan',
        'induksi' => 'Induksi persalinan',
        'scSebelumPersalinan' => 'SC sebelum persalinan (belum in partu)',
    ];

    public const JUMLAH_JANIN = [
        'tunggal' => 'Tunggal',
        'ganda' => 'Ganda (gemelli atau lebih)',
    ];

    public const PRESENTASI_JANIN = [
        'kepala' => 'Presentasi kepala',
        'bokong' => 'Presentasi bokong (sungsang)',
        'lintangOblik' => 'Letak lintang / oblik',
    ];

    public const CARA_PERSALINAN = [
        'pervaginam' => 'Pervaginam (spontan / dengan tindakan)',
        'sc' => 'Sectio Caesarea',
    ];

    /** Batas aterm (minggu) — di bawah ini masuk kelompok 10 bila tunggal presentasi kepala. */
    public const BATAS_ATERM_MINGGU = 37;

    public const KELOMPOK = [
        '1' => 'Nulipara, janin tunggal, presentasi kepala, usia kehamilan >= 37 minggu, persalinan spontan',
        '2' => 'Nulipara, janin tunggal, presentasi kepala, usia kehamilan >= 37 minggu, induksi persalinan atau SC sebelum persalinan',
        '3' => 'Multipara tanpa bekas SC, janin tunggal, presentasi kepala, usia kehamilan >= 37 minggu, persalinan spontan',
        '4' => 'Multipara tanpa bekas SC, janin tunggal, presentasi kepala, usia kehamilan >= 37 minggu, induksi persalinan atau SC sebelum persalinan',
        '5' => 'Multipara dengan bekas SC (satu kali atau lebih), janin tunggal, presentasi kepala, usia kehamilan >= 37 minggu',
        '6' => 'Semua nulipara dengan janin tunggal presentasi bokong',
        '7' => 'Semua multipara dengan janin tunggal presentasi bokong, termasuk yang mempunyai bekas SC',
        '8' => 'Semua kehamilan ganda, termasuk yang mempunyai bekas SC',
        '9' => 'Semua janin tunggal letak lintang atau oblik, termasuk yang mempunyai bekas SC',
        '10' => 'Semua janin tunggal presentasi kepala, usia kehamilan < 37 minggu, termasuk yang mempunyai bekas SC',
    ];

    public const SUB_KELOMPOK = [
        '2a' => 'Induksi persalinan',
        '2b' => 'SC sebelum persalinan',
        '4a' => 'Induksi persalinan',
        '4b' => 'SC sebelum persalinan',
        '5.1' => 'Satu kali bekas SC',
        '5.2' => 'Dua kali atau lebih bekas SC',
    ];

    /** Peta label untuk cetak & viewer (satu pintu, lihat skill modul-dokumen aturan 5). */
    public static function labels(): array
    {
        return [
            'paritas' => self::PARITAS,
            'riwayatSc' => self::RIWAYAT_SC,
            'awalPersalinan' => self::AWAL_PERSALINAN,
            'jumlahJanin' => self::JUMLAH_JANIN,
            'presentasiJanin' => self::PRESENTASI_JANIN,
            'caraPersalinan' => self::CARA_PERSALINAN,
            'kelompok' => self::KELOMPOK,
            'subKelompok' => self::SUB_KELOMPOK,
        ];
    }

    /** Nulipara tidak mungkin mempunyai bekas SC — kombinasi ini ditolak, bukan ditebak. */
    public static function kombinasiMustahil(array $form): bool
    {
        return ($form['paritas'] ?? '') === 'nulipara' && in_array($form['riwayatSc'] ?? '', ['satu', 'duaAtauLebih'], true);
    }

    /**
     * Turunkan kelompok Robson dari variabel obstetri.
     * Mengembalikan ['kelompok' => '1'..'10', 'subKelompok' => '2a'|…|''], atau null bila
     * variabel yang diperlukan cabangnya belum lengkap / kombinasinya mustahil.
     * Urutan uji mengikuti bagan alur WHO: janin ganda → letak lintang → bokong → kepala.
     */
    public static function tentukanKelompok(array $form): ?array
    {
        $paritas = (string) ($form['paritas'] ?? '');
        $riwayatSc = (string) ($form['riwayatSc'] ?? '');
        $awalPersalinan = (string) ($form['awalPersalinan'] ?? '');
        $jumlahJanin = (string) ($form['jumlahJanin'] ?? '');
        $presentasiJanin = (string) ($form['presentasiJanin'] ?? '');
        $usiaKehamilanMinggu = $form['usiaKehamilanMinggu'] ?? '';

        if (self::kombinasiMustahil($form)) {
            return null;
        }

        if ($jumlahJanin === 'ganda') {
            return ['kelompok' => '8', 'subKelompok' => ''];
        }
        if ($jumlahJanin !== 'tunggal') {
            return null;
        }

        if ($presentasiJanin === 'lintangOblik') {
            return ['kelompok' => '9', 'subKelompok' => ''];
        }
        if ($presentasiJanin === 'bokong') {
            if ($paritas === 'nulipara') {
                return ['kelompok' => '6', 'subKelompok' => ''];
            }
            if ($paritas === 'multipara') {
                return ['kelompok' => '7', 'subKelompok' => ''];
            }
            return null;
        }
        if ($presentasiJanin !== 'kepala') {
            return null;
        }

        if (!is_numeric($usiaKehamilanMinggu)) {
            return null;
        }
        if ((int) $usiaKehamilanMinggu < self::BATAS_ATERM_MINGGU) {
            return ['kelompok' => '10', 'subKelompok' => ''];
        }

        if ($paritas === 'multipara' && $riwayatSc === 'satu') {
            return ['kelompok' => '5', 'subKelompok' => '5.1'];
        }
        if ($paritas === 'multipara' && $riwayatSc === 'duaAtauLebih') {
            return ['kelompok' => '5', 'subKelompok' => '5.2'];
        }
        if ($riwayatSc !== 'tidak') {
            return null;
        }

        if ($paritas === 'nulipara') {
            if ($awalPersalinan === 'spontan') {
                return ['kelompok' => '1', 'subKelompok' => ''];
            }
            if ($awalPersalinan === 'induksi') {
                return ['kelompok' => '2', 'subKelompok' => '2a'];
            }
            if ($awalPersalinan === 'scSebelumPersalinan') {
                return ['kelompok' => '2', 'subKelompok' => '2b'];
            }
            return null;
        }

        if ($paritas === 'multipara') {
            if ($awalPersalinan === 'spontan') {
                return ['kelompok' => '3', 'subKelompok' => ''];
            }
            if ($awalPersalinan === 'induksi') {
                return ['kelompok' => '4', 'subKelompok' => '4a'];
            }
            if ($awalPersalinan === 'scSebelumPersalinan') {
                return ['kelompok' => '4', 'subKelompok' => '4b'];
            }
        }

        return null;
    }

    /** Teks ringkas satu baris: "Kelompok 2a" / "Kelompok 5.1" / "Kelompok 8". */
    public static function teksKelompok(?string $kelompok, ?string $subKelompok = ''): string
    {
        if (!filled($kelompok)) {
            return '-';
        }
        return 'Kelompok ' . (filled($subKelompok) ? $subKelompok : $kelompok);
    }
}
