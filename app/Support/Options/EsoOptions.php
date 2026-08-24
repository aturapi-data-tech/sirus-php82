<?php

namespace App\Support\Options;

/**
 * SUMBER TUNGGAL pilihan & label Formulir Pelaporan Efek Samping Obat (RM 37).
 *
 * Dipakai bersama oleh form entri, blade cetak, dan viewer rekam medis — jangan
 * menyalin daftarnya ke salah satu dari tiga tempat itu (aturan modul-dokumen #5).
 *
 * ACUAN: Form Kuning MESO BPOM edisi 2026 (docs/referensi/bpom-form-kuning-meso-2026.pdf).
 * RM 37 cetakan RS adalah versi lama dan KURANG beberapa kolom — yang hilang di sana
 * (No. Bets, Obat JKN, masalah mutu produk, tgl pemeriksaan lab, blok Pengirim)
 * SENGAJA diikutkan supaya laporan bisa langsung dipakai lapor ke e-MESO BPOM
 * tanpa petugas mengisi ulang di formulir lain.
 */
final class EsoOptions
{
    /** Dipakai DUA kali: kesudahan penyakit utama & kesudahan ESO. Urutan mengikuti BPOM. */
    public const KESUDAHAN = ['Sembuh', 'Sembuh dengan gejala sisa', 'Belum sembuh', 'Meninggal', 'Tidak tahu'];

    public const KELAMIN = ['Pria', 'Wanita'];

    /** Hanya relevan bila kelamin = Wanita. */
    public const STATUS_KEHAMILAN = ['Hamil', 'Tidak hamil', 'Tidak tahu'];

    /**
     * Penyakit / kondisi lain yang menyertai — MULTI pilih.
     * Tiga yang terakhir tidak tercetak di RM 37 lama; petugas selama ini menulisnya
     * tangan di formulir, jadi memang dibutuhkan.
     */
    public const KONDISI_MENYERTAI = [
        'gangguanGinjal' => 'Gangguan Ginjal',
        'gangguanHati' => 'Gangguan Hati',
        'alergi' => 'Alergi',
        'kondisiMedisLainnya' => 'Kondisi medis lainnya',
        'faktorIndustri' => 'Faktor Industri, pertanian, kimia',
        'lainLain' => 'Lain-lain',
    ];

    /** Kolom "Cara" pada tabel obat (rute pemberian). */
    public const CARA_PEMBERIAN = ['Oral', 'Sublingual', 'IV', 'IM', 'SC', 'Inhalasi', 'Topikal', 'Rektal', 'Tetes Mata', 'Tetes Telinga', 'Lainnya'];

    public const BENTUK_SEDIAAN = ['Tablet', 'Kaplet', 'Kapsul', 'Sirup', 'Suspensi', 'Emulsi', 'Injeksi', 'Infus', 'Salep', 'Krim', 'Gel', 'Tetes', 'Supositoria', 'Ovula', 'Inhaler', 'Patch', 'Lainnya'];

    /** Jawaban dua nilai untuk kolom bertanda (x): Obat JKN & Obat yang Dicurigai. */
    public const YA_TIDAK = ['Ya', 'Tidak'];

    /** Satu baris kosong tabel obat — bentuknya dipatok di sini supaya form & cetak sama. */
    public static function barisObatKosong(): array
    {
        return [
            'namaObat' => '',
            'bentukSediaan' => '',
            'obatJkn' => 'Tidak',
            'noBets' => '',
            'dicurigai' => 'Tidak',
            'cara' => '',
            'dosisWaktu' => '',
            'tglMula' => '',
            'tglAkhir' => '',
            'indikasi' => '',
        ];
    }

    /** Peta label untuk viewer & cetak — satu panggilan, tidak diduplikasi per berkas. */
    public static function labels(): array
    {
        return [
            'kesudahan' => self::KESUDAHAN,
            'kelamin' => self::KELAMIN,
            'statusKehamilan' => self::STATUS_KEHAMILAN,
            'kondisiMenyertai' => self::KONDISI_MENYERTAI,
            'caraPemberian' => self::CARA_PEMBERIAN,
            'bentukSediaan' => self::BENTUK_SEDIAAN,
        ];
    }
}
