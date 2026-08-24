<?php

namespace App\Support\Options;

/**
 * SUMBER TUNGGAL pilihan terminologi Rujukan Berbasis Kompetensi (jalur FHIR
 * SATUSEHAT: Ranap & IGD). Dipakai bersama panel RI, UGD, dan RJ-FHIR —
 * jangan menyalin daftarnya ke salah satu panel.
 *
 * ACUAN: Buku Panduan SATUSEHAT "Rujukan Pasien — Rawat Jalan, Rawat Inap, dan
 * Rawat Darurat" versi 6.1 (21 Agustus 2026).
 */
final class RujukanOptions
{
    /**
     * Kelompok Layanan — Task.input.valueCoding pada Task Pencarian Kandidat
     * Fasyankes Rujukan (playbook v6.0, Lampiran 4).
     *
     * TK000562 adalah kode TIPE input-nya ("Kelompok Layanan"), BUKAN salah satu
     * nilai di daftar ini — jangan sampai tertukar.
     */
    public const KELOMPOK_LAYANAN = [
        'TK000563' => 'Jantung dan Pembuluh Darah',
        'TK000564' => 'Paru-Pernapasan',
        'TK000565' => 'Uronefro-Ginjal',
        'TK000566' => 'Neonatus',
        'TK000567' => 'Neoplasma',
        'TK000568' => 'Ibu dan Ginekologi',
        'TK000569' => 'Muskuloskeletal dan Jaringan Lunak',
        'TK000570' => 'THT',
        'TK000571' => 'Mata',
        'TK000572' => 'Kulit dan Kelamin',
        'TK000573' => 'Saraf Neurosain',
        'TK000574' => 'Infeksi dan Parasit',
        'TK000575' => 'Pencernaan dan Hepatobiliar',
        'TK000576' => 'Hematologi',
        'TK000577' => 'Alergi dan Rhematologi',
        'TK000578' => 'Rekonstruksi dan Estetika',
        'TK000579' => 'Keracunan',
        'TK000580' => 'Endocrine, Nutrition, dan Metabolik',
        'TK000581' => 'Luka Bakar - Burn',
        'TK000582' => 'Trauma',
        'TK000583' => 'Jiwa',
        'TK000584' => 'Gigi dan Mulut',
        'TK000585' => 'Forensik',
        'TK000586' => 'Rehabilitasi',
    ];

    /** Label lengkap seperti tertulis di playbook (dipakai sebagai display FHIR). */
    public static function kelompokLayananDisplay(string $kodeKelompok): string
    {
        $namaKelompok = self::KELOMPOK_LAYANAN[$kodeKelompok] ?? '';

        return $namaKelompok === '' ? '' : 'Kelompok Layanan ' . $namaKelompok;
    }

    /**
     * Jenis Tenaga Kesehatan Pelaksana Rujukan — ServiceRequest.performerType,
     * kode SNOMED dari sheet "HealthcareProfessional ECL" (Lampiran Terminologi
     * Occupation SNOMED).
     *
     * DAFTAR INI SENGAJA BELUM LENGKAP. Sheet occupation-nya tidak ikut dibagikan
     * di grup — yang ada barulah satu kode yang TERBUKTI diterima SATUSEHAT
     * (dipakai di tiga contoh Postman resmi dan pada payload faskes lain yang
     * kirimannya berhasil 21/08/2026).
     *
     * Menebak kode SNOMED lain berbahaya di dua sisi: ditolak validator karena
     * edisi SNOMED SATUSEHAT tertinggal, atau lolos tapi mencatat jenis tenaga
     * kesehatan yang KELIRU di rekam medis nasional. Karena itu bila petugas tidak
     * memilih, field-nya tidak dikirim sama sekali (dan itu sah — contoh payload
     * faskes lain yang berhasil pun tanpa field ini).
     *
     * Lengkapi daftar ini begitu sheet resminya didapat.
     */
    public const PERFORMER_TYPE = [
        '39677007' => 'Internal medicine specialist',
    ];

    /**
     * Kode layanan `clinical-speciality` untuk CarePlan.activity[].detail.code.
     *
     * Katalog lengkapnya ada di "Dokumen Lampiran Standar Terminologi SATUSEHAT"
     * yang JUGA belum dibagikan ke grup — daftar di bawah hanya kode yang benar-benar
     * terlihat dipakai (payload faskes lain & contoh resmi), bukan hasil menebak.
     *
     * Karena itu petugas TETAP boleh mengetik kode sendiri lewat pilihan "ketik
     * manual": daftar ini pintasan, bukan pembatas — memaksa memilih dari sini akan
     * memblokir rujukan ke layanan yang kodenya sah tapi belum tercatat di sini.
     */
    public const CLINICAL_SPECIALITY = [
        'L03' => 'Pelayanan Gawat Darurat',
        'LY010' => 'Spesialis - Penyakit Dalam',
        'LY133' => 'Syaraf - Stroke dan Cerebro Vaskuler',
        'LY246' => 'Penyakit Dalam - Ginjal dan Hipertensi',
    ];
}
