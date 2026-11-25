<?php

/*
 * Hak akses kelompok RAWAT INAP — SATU BLOK PER PROGRAM (lihat App\Support\ProgramAkses).
 *
 *  'menu'       : entri menu sidebar + Lihat/Tambah/Ubah/Hapus + tombol khusus halaman ('khusus').
 *  'aksiKhusus' : aksi di luar halaman (tombol di modal/EMR, posting/batal) — nama Gate => label, roles awal.
 *  'pintu'      : event pembuka modal => hak siapa saja yang sah membukanya.
 *
 * roles = CENTANG AWAL saja; sesudah permission dibuat (otomatis saat deploy) yang berlaku
 * centang Role Control. Pedoman: docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;
use App\Support\AksiRole;

return [
    'judul' => 'Rawat Inap',
    'program' => [
        'Pendaftaran Rawat Inap' => [
            'menu' => [
                ['group' => 'Rawat Inap', 'groupOrder' => 7, 'order' => 1, 'route' => 'ri.daftar', 'khusus' => ['bukaRekamMedis' => ['Buka Rekam Medis', ['admin', 'apoteker', 'casemix', 'dokter', 'gizi', 'laboratorium', 'perawat', 'radiologi']], 'bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'gizi', 'laboratorium', 'mr', 'perawat', 'radiologi', 'supervisor tu', 'tu']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'gizi', 'perawat', 'tu']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'mr', 'tu']]], 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => AppMenu::ROLES_PENDAFTARAN, 'ubah' => AppMenu::ROLES_PENDAFTARAN], 'title' => 'Daftar Rawat Inap', 'desc' => 'Pendaftaran, transfer kamar, & manajemen pasien rawat inap', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor tu', 'mr', 'perawat', 'dokter', 'casemix', 'tu', 'apoteker', 'gizi', 'laboratorium', 'radiologi'], 'badge' => 'RI'],
            ],
        ],

        'Kamar & Tempat Tidur' => [
            'menu' => [
                ['group' => 'Rawat Inap', 'groupOrder' => 7, 'order' => 2, 'route' => 'ri.update-tt', 'aksi' => ['lihat'], 'khusus' => ['kirim' => ['Kirim ke Aplicares BPJS & SIRS', ['admin', 'mr', 'perawat', 'dokter']]], 'title' => 'Sinkronisasi Tempat Tidur', 'desc' => 'Sync ketersediaan TT rawat inap → Aplicares BPJS & SIRS Kemenkes', 'roles' => ['admin', 'mr', 'perawat', 'dokter'], 'badge' => 'TT'],
            ],
            'aksiKhusus' => [
                'ri.pindahKamar' => ['label' => 'Pindah kamar', 'roles' => AksiRole::RI_PINDAH_KAMAR],
            ],
            'pintu' => [
                'emr-ri.pindah-kamar.open' => ['ri.pindahKamar'],
            ],
        ],

        // CPPT & SBAR: aturan pemilik entri (edit = penulisnya) dan DPJP (review = DPJP Utama) tetap di
        // kode modul — yang di sini hanya PENGECUALIAN-nya.
        'EMR Rawat Inap' => [
            'aksiKhusus' => [
                'ri.caseManager' => ['label' => 'Case Manager (MPP)', 'roles' => AksiRole::RI_CASE_MANAGER],
                'ri.emr.bukaModulDokumen' => ['label' => 'EMR: buka Modul Dokumen', 'roles' => ['Admin', 'Apoteker', 'Casemix', 'Dokter', 'Gizi', 'Laboratorium', 'Perawat', 'Radiologi']],
                'ri.emr.bukaAdministrasi' => ['label' => 'EMR: buka Administrasi', 'roles' => ['Admin', 'Apoteker', 'Casemix', 'Gizi', 'Perawat']],
                'ri.emr.bukaEresep' => ['label' => 'EMR: buka E-Resep', 'roles' => ['Admin', 'Dokter', 'Perawat']],
                'ri.emr.bukaRingkasanPulang' => ['label' => 'EMR: ringkasan Pulang', 'roles' => ['Admin', 'Perawat']],
                'ri.emr.bukaRekamMedisNasional' => ['label' => 'EMR: rekam Medis Nasional (SSRME)', 'roles' => ['Admin', 'Dokter', 'Perawat']],
                'ri.emr.bukaHasilPenunjang' => ['label' => 'EMR: lihat Hasil Penunjang', 'roles' => ['Admin', 'Dokter', 'Perawat']],
                'ri.cppt.hapus' => ['label' => 'CPPT: hapus entri', 'roles' => AksiRole::RI_CPPT_HAPUS],
                'ri.cppt.ubahSemua' => ['label' => 'CPPT: edit entri petugas lain', 'roles' => AksiRole::RI_CPPT_UBAH_SEMUA],
                'ri.cppt.copySemua' => ['label' => 'CPPT: copy dari profesi lain', 'roles' => AksiRole::RI_CPPT_COPY_SEMUA],
                'ri.cppt.reviewSemua' => ['label' => 'CPPT: review walau bukan DPJP', 'roles' => AksiRole::RI_CPPT_REVIEW_SEMUA],
                'ri.sbar.hapus' => ['label' => 'SBAR: hapus entri', 'roles' => AksiRole::RI_SBAR_HAPUS],
                'ri.sbar.ubahSemua' => ['label' => 'SBAR: edit entri petugas lain', 'roles' => AksiRole::RI_SBAR_UBAH_SEMUA],
                'ri.sbar.copySemua' => ['label' => 'SBAR: copy dari profesi lain', 'roles' => AksiRole::RI_SBAR_COPY_SEMUA],
                'ri.sbar.reviewSemua' => ['label' => 'SBAR: review walau bukan DPJP', 'roles' => AksiRole::RI_SBAR_REVIEW_SEMUA],
                'ri.emr.logAktivitas' => ['label' => 'Lihat log aktivitas EMR', 'roles' => AksiRole::EMR_LOG_AKTIVITAS, 'menggantikan' => 'emr.logAktivitas'],
                'ri.emr.codingFhir' => ['label' => 'Coding SATUSEHAT', 'roles' => AksiRole::EMR_CODING_FHIR, 'menggantikan' => 'emr.codingFhir'],
                'ri.emr.rekonsiliasiObat' => ['label' => 'Rekonsiliasi obat (titik-3)', 'roles' => AksiRole::REKONSILIASI_OBAT, 'menggantikan' => 'rekonsiliasi.obat'],
            ],
            'pintu' => [
                'emr-ri.modul-dokumen.open' => ['ri.daftar.bukaModulDokumen', 'ri.gizi.bukaModulDokumen', 'ri.gizi.bukaModulDokumenEntri', 'ri.emr.bukaModulDokumen', 'penunjang.kamar-operasi.bukaDokumenBedah'],
                'emr-ri.rekam-medis.open' => ['ri.daftar.bukaRekamMedis'],
                'emr-ri.eresep.open' => ['ri.emr.bukaEresep', 'pintu:emr-ri.rekam-medis.open', 'ri.gizi.lihat'],
                'ringkasan-pulang-ri.open' => ['ri.emr.bukaRingkasanPulang'],
            ],
        ],

        // Hak modul dokumen JALUR INI (dulu satu hak untuk RJ/UGD/RI). Sel Aksi: <x-modul-dokumen.aksi-entri jalur="..">.
        'Modul Dokumen Rawat Inap' => [
            'aksiKhusus' => [
                'ri.dokumen.hapus' => ['label' => 'Hapus entri dokumen', 'roles' => AksiRole::DOKUMEN_HAPUS, 'menggantikan' => 'dokumen.hapus'],
                'ri.dokumen.bukaKunci' => ['label' => 'Buka kunci (cabut TTD) dokumen', 'roles' => AksiRole::DOKUMEN_BUKA_KUNCI, 'menggantikan' => 'dokumen.bukaKunci'],
                'ri.dokumen.ttdPerwakilan' => ['label' => 'TTD atas nama pejabat (perwakilan)', 'roles' => AksiRole::DOKUMEN_TTD_PERWAKILAN, 'menggantikan' => 'dokumen.ttdPerwakilan'],
            ],
        ],

        'Bridging Rawat Inap' => [
            'aksiKhusus' => [
                'ri.satusehat.kirim' => ['label' => 'Kirim SATUSEHAT', 'roles' => AksiRole::SATUSEHAT_KIRIM, 'menggantikan' => 'satusehat.kirim'],
                'ri.bpjs.hapusSuratKontrol' => ['label' => 'Hapus surat kontrol BPJS', 'roles' => AksiRole::BPJS_HAPUS_SURAT_KONTROL, 'menggantikan' => 'bpjs.hapusSuratKontrol'],
            ],
        ],

        'Gizi Rawat Inap' => [
            'menu' => [
                ['group' => 'Rawat Inap', 'groupOrder' => 7, 'order' => 3, 'route' => 'ri.gizi', 'aksi' => ['lihat'], 'khusus' => ['bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'gizi', 'mr', 'perawat']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'gizi', 'perawat', 'tu']], 'bukaModulDokumenEntri' => ['Buka Modul Dokumen (panel entri)', ['admin', 'apoteker', 'casemix', 'dokter', 'gizi', 'perawat']], 'bukaAdministrasiEntri' => ['Buka Administrasi (panel entri)', ['admin', 'apoteker', 'casemix', 'gizi', 'perawat']]], 'title' => 'Gizi Rawat Inap', 'desc' => 'Program diet harian pasien RI & rekap porsi dapur', 'roles' => ['admin', 'manager medis', 'manager umum', 'gizi'], 'badge' => 'GIZI'],
            ],
        ],

        'Resep Rawat Inap' => [
            'menu' => [
                ['group' => 'Apotek', 'groupOrder' => 9, 'order' => 4, 'route' => 'ri-resep.antrian', 'aksi' => ['lihat'], 'khusus' => ['taskIdApotek' => ['Catat TaskID 6/7 (masuk/keluar apotek)', ['admin', 'apoteker']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'apoteker', 'tu']]], 'title' => 'Antrian Apotek RI', 'desc' => 'Telaah resep & pelayanan kefarmasian rawat inap', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT-RI'],
                ['group' => 'Apotek', 'groupOrder' => 9, 'order' => 5, 'route' => 'ri.pto', 'aksi' => ['lihat'], 'title' => 'Pemantauan Terapi Obat (PTO) Rawat Inap', 'desc' => 'Pantau seluruh terapi obat pasien rawat inap dari e-resep — khusus apoteker', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'PTO'],
                ['group' => 'Kasir', 'groupOrder' => 9, 'order' => 4, 'route' => 'kasir.antrian-ri', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'manager umum', 'supervisor tu', 'tu']]], 'title' => 'Resep Inap', 'desc' => 'Kasir resep rawat inap (per resep)', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-RI'],
            ],
            'aksiKhusus' => [
                'ri.ubahDataResep' => ['label' => 'Resep: ubah tanggal/shift', 'roles' => AksiRole::RI_UBAH_DATA_RESEP],
                'kasir.resepRi.posting' => ['label' => 'Kasir Resep: posting', 'roles' => AksiRole::KASIR_RESEP_RI_POSTING],
                'kasir.resepRi.batal' => ['label' => 'Kasir Resep: batal', 'roles' => AksiRole::KASIR_RESEP_RI_BATAL],
                'kasir.resepRi.ubahJasa' => ['label' => 'Kasir Resep: ubah jasa karyawan', 'roles' => AksiRole::KASIR_RESEP_RI_UBAH_JASA],
                'apotek.resepRi.posting' => ['label' => 'Apotek Resep: posting', 'roles' => AksiRole::APOTEK_RESEP_RI_POSTING],
                'apotek.resepRi.batal' => ['label' => 'Apotek Resep: batal', 'roles' => AksiRole::APOTEK_RESEP_RI_BATAL],
                'apotek.resepRi.ubahJasa' => ['label' => 'Apotek Resep: ubah jasa karyawan', 'roles' => AksiRole::APOTEK_RESEP_RI_UBAH_JASA],
            ],
            'pintu' => [
                'administrasi-kasir-ri.open' => ['kasir.antrian-ri.bukaAdministrasi'],
                'ri-resep-administrasi.open' => ['ri-resep.antrian.bukaAdministrasi'],
                'cetak-kwitansi-ri-obat.open' => ['pintu:administrasi-kasir-ri.open', 'pintu:ri-resep-administrasi.open'],
                'open-ubah-data-resep-ri' => ['ri.ubahDataResep'],
            ],
        ],

        'Administrasi & Kasir Pulang Rawat Inap' => [
            'menu' => [
                ['group' => 'Kasir', 'groupOrder' => 9, 'order' => 5, 'route' => 'kasir.daftar-ri', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'manager umum', 'supervisor tu', 'tu']]], 'title' => 'Pasien Rawat Inap', 'desc' => 'List pasien rawat inap untuk administrasi & pembayaran', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-DAFTAR'],
            ],
            'aksiKhusus' => [
                'ri.ubahDataKunjungan' => ['label' => 'Ubah data kunjungan (klaim)', 'roles' => AksiRole::RI_UBAH_DATA_KUNJUNGAN],
                'ri.resetAdministrasi' => ['label' => 'Reset tab administrasi', 'roles' => AksiRole::RI_RESET_ADMINISTRASI],
                'kasir.ri.posting' => ['label' => 'Kasir: posting pembayaran pulang', 'roles' => AksiRole::KASIR_RI_POSTING],
                'kasir.ri.batal' => ['label' => 'Kasir: batal transaksi pulang', 'roles' => AksiRole::KASIR_RI_BATAL],
                'kasir.ri.batalInap' => ['label' => 'Kasir: batal inap', 'roles' => AksiRole::KASIR_RI_BATAL_INAP],
                'ri.angsuranAwal.terima' => ['label' => 'Angsuran Awal (titipan): terima', 'roles' => AksiRole::RI_ANGSURAN_AWAL_TERIMA],
                'ri.angsuranAwal.hapus' => ['label' => 'Angsuran Awal (titipan): hapus', 'roles' => AksiRole::RI_ANGSURAN_AWAL_HAPUS],
            ],
            'pintu' => [
                'emr-ri.administrasi.open' => ['ri.daftar.bukaAdministrasi', 'ri.daftar-bulanan.bukaAdministrasi', 'ri.gizi.bukaAdministrasi', 'ri.gizi.bukaAdministrasiEntri', 'kasir.daftar-ri.bukaAdministrasi', 'ri.emr.bukaAdministrasi', 'manajemen.rs.tu.piutang-pasien.lihat'],
                'open-ubah-data-kunjungan-ri' => ['ri.ubahDataKunjungan'],
            ],
        ],

        'Daftar Bulanan & Klaim Rawat Inap' => [
            'menu' => [
                ['group' => 'Casemix', 'groupOrder' => 8, 'order' => 4, 'route' => 'ri.daftar-bulanan', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'tu']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'tu']]], 'title' => 'Daftar Pasien Bulanan RI', 'desc' => 'List pasien RI per bulan berdasarkan tgl pulang', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'RI-BLN'],
                ['group' => 'Casemix', 'groupOrder' => 8, 'order' => 8, 'route' => 'ri.kontrol-klaim', 'aksi' => ['lihat'], 'title' => 'Kontrol Klaim Rawat Inap', 'desc' => 'Pantau klaim pasien yang sedang dirawat inap', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'KK-RI'],
            ],
        ],
    ],
];
