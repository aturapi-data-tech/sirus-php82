<?php

/*
 * Hak akses program "EMR Rawat Jalan" — kelompok Rawat Jalan (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'EMR Rawat Jalan',
    // Tombol di DALAM EMR (komponen lintas halaman, tak terikat route). Roles = salinan persis
    // @hasanyrole lama di emr-*-isi (T2, 2026-11-25).
    'aksiKhusus' => [
        'rj.emr.bukaScreening' => ['label' => 'EMR: buka Screening', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        'rj.emr.bukaEresep' => ['label' => 'EMR: buka E-Resep', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        'rj.emr.tulisEresep' => ['label' => 'E-Resep: tambah/hapus obat', 'roles' => ['Admin', 'Dokter']],
        'rj.emr.myiCare' => ['label' => 'EMR: i-Care BPJS', 'roles' => ['Admin', 'Dokter']],
        'rj.emr.uploadPenunjang' => ['label' => 'Upload/hapus hasil penunjang', 'roles' => ['Admin', 'Perawat']],
        'rj.emr.lihatUploadPenunjang' => ['label' => 'Lihat upload hasil penunjang', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        // Tombol di tampilan Rekam Medis (riwayat kunjungan pasien).
        'rj.emr.bukaRiwayatRekamMedis' => ['label' => 'Rekam Medis: buka detail kunjungan RJ', 'roles' => ['Admin', 'Casemix', 'Dokter', 'Mr', 'Perawat']],
        'rj.emr.salinResep' => ['label' => 'Rekam Medis: copy resep ke kunjungan ini', 'roles' => ['Admin', 'Dokter']],
        'rj.emr.lihatRekamMedisV1' => ['label' => 'Rekam Medis: lihat RM RJ versi lama (V1)', 'roles' => ['Admin', 'Casemix', 'Manager Medis', 'Manager Umum', 'Mr', 'Perawat']],
        'rj.emr.bukaAdministrasi' => ['label' => 'EMR: buka Administrasi', 'roles' => ['Admin', 'Casemix', 'Perawat']],
        'rj.emr.bukaModulDokumen' => ['label' => 'EMR: buka Modul Dokumen', 'roles' => ['Admin', 'Casemix', 'Dokter', 'Mr', 'Perawat']],
        'rj.emr.bukaRekamMedisNasional' => ['label' => 'EMR: rekam Medis Nasional (SSRME)', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        'rj.emr.logAktivitas' => ['label' => 'Lihat log aktivitas EMR', 'roles' => AksiRole::EMR_LOG_AKTIVITAS, 'menggantikan' => 'emr.logAktivitas'],
        'rj.emr.codingFhir' => ['label' => 'Coding SATUSEHAT', 'roles' => AksiRole::EMR_CODING_FHIR, 'menggantikan' => 'emr.codingFhir'],
        'rj.emr.cetakEresep' => ['label' => 'Cetak e-resep dari EMR', 'roles' => AksiRole::EMR_CETAK_ERESEP, 'menggantikan' => 'emr.cetakEresep'],
    ],
    'pintu' => [
        'emr-rj.modul-dokumen.open' => ['rj.daftar.bukaModulDokumen', 'rj.pelayanan.bukaModulDokumen', 'rj.emr.bukaModulDokumen', 'penunjang.kamar-operasi.bukaDokumenBedah'],
        'open-rm-screening-rj' => ['rj.emr.bukaScreening'],
        // Copy Resep di tampilan Rekam Medis juga membuka E-Resep kunjungan tujuan.
        'emr-rj.eresep.open' => ['rj.emr.bukaEresep', 'pintu:emr-rj.rekam-medis.open'],
    ],
];
