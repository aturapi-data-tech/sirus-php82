<?php

/*
 * Hak akses program "EMR UGD" — kelompok UGD (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'EMR UGD',
    // SBAR UGD: aturan pemilik entri & dokter kunjungan tetap di kode; di sini pengecualiannya.
    'aksiKhusus' => [
        'ugd.emr.bukaScreening' => ['label' => 'EMR: buka Screening', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        'ugd.emr.bukaAdministrasi' => ['label' => 'EMR: buka Administrasi', 'roles' => ['Admin', 'Casemix', 'Perawat']],
        'ugd.emr.bukaEresep' => ['label' => 'EMR: buka E-Resep', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        'ugd.emr.tulisEresep' => ['label' => 'E-Resep: tambah/hapus obat', 'roles' => ['Admin', 'Dokter']],
        'ugd.emr.myiCare' => ['label' => 'EMR: i-Care BPJS', 'roles' => ['Admin', 'Dokter']],
        'ugd.emr.uploadPenunjang' => ['label' => 'Upload/hapus hasil penunjang', 'roles' => ['Admin', 'Perawat']],
        'ugd.emr.lihatUploadPenunjang' => ['label' => 'Lihat upload hasil penunjang', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        // Tombol di tampilan Rekam Medis (riwayat kunjungan pasien).
        'ugd.emr.bukaRiwayatRekamMedis' => ['label' => 'Rekam Medis: buka detail kunjungan UGD & surat kematian', 'roles' => ['Admin', 'Casemix', 'Dokter', 'Mr', 'Perawat']],
        'ugd.emr.lihatRekamMedisV1' => ['label' => 'Rekam Medis: lihat RM UGD versi lama (V1)', 'roles' => ['Admin', 'Casemix', 'Manager Medis', 'Manager Umum', 'Mr', 'Perawat']],
        'ugd.emr.bukaModulDokumen' => ['label' => 'EMR: buka Modul Dokumen', 'roles' => ['Admin', 'Casemix', 'Dokter', 'Mr', 'Perawat']],
        'ugd.emr.bukaRekamMedisNasional' => ['label' => 'EMR: rekam Medis Nasional (SSRME)', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        'ugd.sbar.hapus' => ['label' => 'SBAR: hapus entri', 'roles' => AksiRole::UGD_SBAR_HAPUS],
        'ugd.sbar.ubahSemua' => ['label' => 'SBAR: edit entri petugas lain', 'roles' => AksiRole::UGD_SBAR_UBAH_SEMUA],
        'ugd.sbar.copySemua' => ['label' => 'SBAR: copy dari profesi lain', 'roles' => AksiRole::UGD_SBAR_COPY_SEMUA],
        'ugd.sbar.reviewSemua' => ['label' => 'SBAR: review walau bukan dokter UGD', 'roles' => AksiRole::UGD_SBAR_REVIEW_SEMUA],
        'ugd.emr.logAktivitas' => ['label' => 'Lihat log aktivitas EMR', 'roles' => AksiRole::EMR_LOG_AKTIVITAS, 'menggantikan' => 'emr.logAktivitas'],
        'ugd.emr.codingFhir' => ['label' => 'Coding SATUSEHAT', 'roles' => AksiRole::EMR_CODING_FHIR, 'menggantikan' => 'emr.codingFhir'],
        'ugd.emr.cetakEresep' => ['label' => 'Cetak e-resep dari EMR', 'roles' => AksiRole::EMR_CETAK_ERESEP, 'menggantikan' => 'emr.cetakEresep'],
        'ugd.emr.rekonsiliasiObat' => ['label' => 'Rekonsiliasi obat (titik-3)', 'roles' => AksiRole::REKONSILIASI_OBAT, 'menggantikan' => 'rekonsiliasi.obat'],
    ],
    'pintu' => [
        'emr-ugd.modul-dokumen.open' => ['ugd.daftar.bukaModulDokumen', 'ugd.pelayanan.bukaModulDokumen', 'ugd.emr.bukaModulDokumen', 'penunjang.kamar-operasi.bukaDokumenBedah'],
        'emr-ugd.eresep.open' => ['ugd.emr.bukaEresep'],
        'open-rm-screening-ugd' => ['ugd.emr.bukaScreening'],
        // Lihat/cetak surat kematian dari tampilan Rekam Medis (riwayat).
        'surat-kematian-riwayat.lihat' => ['ugd.emr.bukaRiwayatRekamMedis'],
        'surat-kematian-riwayat.cetak' => ['ugd.emr.bukaRiwayatRekamMedis'],
    ],
];
