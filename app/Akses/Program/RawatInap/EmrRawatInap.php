<?php

/*
 * Hak akses program "EMR Rawat Inap" — kelompok Rawat Inap (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'EMR Rawat Inap',
    // CPPT & SBAR: aturan pemilik entri (edit = penulisnya) dan DPJP (review = DPJP Utama) tetap di
    // kode modul — yang di sini hanya PENGECUALIAN-nya.
    'aksiKhusus' => [
        'ri.caseManager' => ['label' => 'Case Manager (MPP)', 'roles' => AksiRole::RI_CASE_MANAGER],
        'ri.emr.bukaModulDokumen' => ['label' => 'EMR: buka Modul Dokumen', 'roles' => ['Admin', 'Apoteker', 'Casemix', 'Dokter', 'Gizi', 'Laboratorium', 'Perawat', 'Radiologi']],
        'ri.emr.bukaAdministrasi' => ['label' => 'EMR: buka Administrasi', 'roles' => ['Admin', 'Apoteker', 'Casemix', 'Gizi', 'Perawat']],
        'ri.emr.bukaEresep' => ['label' => 'EMR: buka E-Resep', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        'ri.emr.tulisEresep' => ['label' => 'E-Resep: tambah/hapus obat, CITO, TTD, copy resep', 'roles' => ['Admin', 'Dokter']],
        'ri.emr.uploadPenunjang' => ['label' => 'Upload/hapus hasil penunjang', 'roles' => ['Admin', 'Perawat']],
        'ri.emr.bukaResumeMedis' => ['label' => 'EMR: buka Resume Medis', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        // Tombol di tampilan Rekam Medis (riwayat kunjungan pasien).
        'ri.emr.bukaRiwayatRekamMedis' => ['label' => 'Rekam Medis: buka detail kunjungan RI & surat kematian', 'roles' => ['Admin', 'Casemix', 'Dokter', 'Mr', 'Perawat']],
        // Tombol di tampilan Rekam Medis: salin asesmen kunjungan UGD ke pengkajian awal RI.
        'ri.emr.salinAsesmenPerawatUgd' => ['label' => 'Rekam Medis: copy asesmen perawat UGD', 'roles' => ['Admin', 'Perawat']],
        'ri.emr.salinAsesmenDokterUgd' => ['label' => 'Rekam Medis: copy asesmen dokter UGD', 'roles' => ['Admin', 'Dokter']],
        'ri.emr.bukaRingkasanPulang' => ['label' => 'EMR: ringkasan Pulang', 'roles' => ['Admin', 'Perawat']],
        'ri.emr.bukaRekamMedisNasional' => ['label' => 'EMR: rekam Medis Nasional (SSRME)', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        'ri.emr.bukaHasilPenunjang' => ['label' => 'EMR: lihat Hasil Penunjang', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        'ri.cppt.hapus' => ['label' => 'CPPT: hapus entri', 'roles' => AksiRole::RI_CPPT_HAPUS],
        'ri.cppt.ubahSemua' => ['label' => 'CPPT: edit entri petugas lain', 'roles' => AksiRole::RI_CPPT_UBAH_SEMUA],
        'ri.cppt.copySemua' => ['label' => 'CPPT: copy dari profesi lain', 'roles' => AksiRole::RI_CPPT_COPY_SEMUA],
        'ri.cppt.reviewSemua' => ['label' => 'CPPT: review walau bukan DPJP', 'roles' => AksiRole::RI_CPPT_REVIEW_SEMUA],
        'ri.cppt.cetak' => ['label' => 'CPPT: cetak entri', 'roles' => AksiRole::RI_CPPT_CETAK],
        'ri.sbar.hapus' => ['label' => 'SBAR: hapus entri', 'roles' => AksiRole::RI_SBAR_HAPUS],
        'ri.sbar.ubahSemua' => ['label' => 'SBAR: edit entri petugas lain', 'roles' => AksiRole::RI_SBAR_UBAH_SEMUA],
        'ri.sbar.copySemua' => ['label' => 'SBAR: copy dari profesi lain', 'roles' => AksiRole::RI_SBAR_COPY_SEMUA],
        'ri.sbar.reviewSemua' => ['label' => 'SBAR: review walau bukan DPJP', 'roles' => AksiRole::RI_SBAR_REVIEW_SEMUA],
        'ri.sbar.cetak' => ['label' => 'SBAR: cetak entri', 'roles' => AksiRole::RI_SBAR_CETAK],
        'ri.emr.logAktivitas' => ['label' => 'Lihat log aktivitas EMR', 'roles' => AksiRole::EMR_LOG_AKTIVITAS, 'menggantikan' => 'emr.logAktivitas'],
        'ri.emr.codingFhir' => ['label' => 'Coding SATUSEHAT', 'roles' => AksiRole::EMR_CODING_FHIR, 'menggantikan' => 'emr.codingFhir'],
        'ri.emr.rekonsiliasiObat' => ['label' => 'Rekonsiliasi obat (titik-3)', 'roles' => AksiRole::REKONSILIASI_OBAT, 'menggantikan' => 'rekonsiliasi.obat'],
    ],
    'pintu' => [
        'emr-ri.modul-dokumen.open' => ['ri.daftar.bukaModulDokumen', 'ri.gizi.bukaModulDokumen', 'ri.gizi.bukaModulDokumenEntri', 'ri.emr.bukaModulDokumen', 'penunjang.kamar-operasi.bukaDokumenBedah'],
        'emr-ri.rekam-medis.open' => ['ri.daftar.bukaRekamMedis'],
        'emr-ri.eresep.open' => ['ri.emr.bukaEresep', 'pintu:emr-ri.rekam-medis.open', 'ri.gizi.lihat'],
        'ringkasan-pulang-ri.open' => ['ri.emr.bukaRingkasanPulang'],
        'resume-medis-ri.open' => ['ri.emr.bukaResumeMedis', 'ri.dokumen.ttdPerwakilan'],
        'surat-kematian-riwayat-ri.lihat' => ['ri.emr.bukaRiwayatRekamMedis'],
        'surat-kematian-riwayat-ri.cetak' => ['ri.emr.bukaRiwayatRekamMedis'],
    ],
];
