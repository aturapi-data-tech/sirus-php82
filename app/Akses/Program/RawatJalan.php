<?php

/*
 * Hak akses kelompok RAWAT JALAN — SATU BLOK PER PROGRAM (lihat App\Support\ProgramAkses).
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
    'judul' => 'Rawat Jalan',
    'program' => [
        'Pendaftaran Rawat Jalan' => [
            'menu' => [
                ['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 1, 'route' => 'rj.daftar', 'khusus' => ['bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'mr', 'perawat', 'supervisor tu', 'tu']], 'bukaDiagnosa' => ['Buka Diagnosa', ['admin', 'casemix', 'dokter', 'mr']], 'bukaRiwayatKontrol' => ['Riwayat Kontrol', ['admin', 'casemix', 'mr', 'tu']], 'bukaBerkasBpjs' => ['Berkas BPJS (generate SEP/RM/SKDP)', ['admin', 'casemix', 'mr', 'tu']], 'batalTaskId99' => ['Batal antrean BPJS (TaskID 99)', ['admin', 'manager medis', 'manager umum']]], 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => AppMenu::ROLES_PENDAFTARAN, 'ubah' => AppMenu::ROLES_PENDAFTARAN], 'title' => 'Daftar Rawat Jalan', 'desc' => 'Pendaftaran & manajemen pasien poli rawat jalan harian', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'mr'], 'badge' => 'RJ'],
            ],
            'pintu' => [
                'daftar-rj.diagnosa.open' => ['rj.daftar.bukaDiagnosa'],
                'riwayat-kontrol.open' => ['rj.daftar.bukaRiwayatKontrol', 'kontrol.jadwal-kontrol.lihat'],
            ],
        ],

        'Booking & Jadwal Kontrol' => [
            'menu' => [
                ['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 3, 'route' => 'rj.booking', 'aksi' => ['lihat', 'tambah', 'ubah', 'hapus'], 'title' => 'Booking Rawat Jalan', 'desc' => 'Pasien booking poli via Mobile JKN — siap diaktivasi jadi pendaftaran', 'roles' => ['admin', 'mr', 'manager umum', 'supervisor tu'], 'badge' => 'BKG'],
                ['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 4, 'route' => 'kontrol.jadwal-kontrol', 'aksi' => ['lihat'], 'title' => 'Jadwal Kontrol Pasien', 'desc' => 'Lihat & geser tanggal rencana kontrol (SKDP) RJ/RI — update otomatis ke BPJS', 'roles' => ['admin', 'mr', 'tu', 'supervisor tu', 'manager umum'], 'badge' => 'SKDP'],
            ],
        ],

        'Pelayanan Rawat Jalan' => [
            'menu' => [
                ['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 2, 'route' => 'rj.pelayanan', 'aksi' => ['lihat'], 'khusus' => ['bukaRekamMedis' => ['Buka Rekam Medis', ['admin', 'casemix', 'dokter', 'mr', 'perawat']], 'bukaPengkajianMedis' => ['Pengkajian Medis', ['admin', 'dokter', 'mr', 'perawat']], 'bukaPrmrj' => ['PRMRJ', ['admin', 'dokter', 'mr', 'perawat']], 'bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'mr', 'perawat']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'perawat', 'tu']], 'bukaTransferUgd' => ['Transfer ke UGD', ['admin', 'manager umum', 'perawat', 'supervisor tu', 'tu']], 'taskIdPoli' => ['Catat TaskID 4/5 (masuk/keluar poli)', ['admin', 'perawat']]], 'title' => 'Pelayanan Rawat Jalan', 'desc' => 'EMR poli — anamnesis, pemeriksaan, diagnosa & resep oleh dokter/perawat', 'roles' => ['admin', 'manager medis', 'dokter', 'perawat'], 'badge' => 'PEL-RJ'],
            ],
            'aksiKhusus' => [
                'rj.detailKunjungan' => ['label' => 'Lihat detail kunjungan', 'roles' => AksiRole::RJ_DETAIL_KUNJUNGAN],
            ],
            'pintu' => [
                'emr-rj.rekam-medis.open' => ['rj.pelayanan.bukaRekamMedis'],
                'pelayanan-rj.pengkajian-medis.open' => ['rj.pelayanan.bukaPengkajianMedis'],
                'pelayanan-rj.prmrj.open' => ['rj.pelayanan.bukaPrmrj'],
                'open-transfer-rj-ke-ugd' => ['rj.pelayanan.bukaTransferUgd', 'rj.antrian-kasir.bukaTransferUgd'],
            ],
        ],

        // Tombol di DALAM EMR (komponen lintas halaman, tak terikat route). Roles = salinan persis
        // @hasanyrole lama di emr-*-isi (T2, 2026-11-25).
        'EMR Rawat Jalan' => [
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
        ],

        // Hak modul dokumen JALUR INI (dulu satu hak untuk RJ/UGD/RI). Sel Aksi: <x-modul-dokumen.aksi-entri jalur="..">.
        'Modul Dokumen Rawat Jalan' => [
            'aksiKhusus' => [
                'rj.dokumen.hapus' => ['label' => 'Hapus entri dokumen', 'roles' => AksiRole::DOKUMEN_HAPUS, 'menggantikan' => 'dokumen.hapus'],
                'rj.dokumen.bukaKunci' => ['label' => 'Buka kunci (cabut TTD) dokumen', 'roles' => AksiRole::DOKUMEN_BUKA_KUNCI, 'menggantikan' => 'dokumen.bukaKunci'],
                'rj.dokumen.ttdPerwakilan' => ['label' => 'TTD atas nama pejabat (perwakilan)', 'roles' => AksiRole::DOKUMEN_TTD_PERWAKILAN, 'menggantikan' => 'dokumen.ttdPerwakilan'],
            ],
        ],

        'Bridging Rawat Jalan' => [
            'aksiKhusus' => [
                'rj.satusehat.kirim' => ['label' => 'Kirim SATUSEHAT', 'roles' => AksiRole::SATUSEHAT_KIRIM, 'menggantikan' => 'satusehat.kirim'],
                'rj.bpjs.hapusSuratKontrol' => ['label' => 'Hapus surat kontrol BPJS', 'roles' => AksiRole::BPJS_HAPUS_SURAT_KONTROL, 'menggantikan' => 'bpjs.hapusSuratKontrol'],
            ],
        ],

        'Apotek Rawat Jalan' => [
            'menu' => [
                ['group' => 'Apotek', 'groupOrder' => 9, 'order' => 2, 'route' => 'rj.antrian-apotek', 'aksi' => ['lihat'], 'khusus' => ['taskIdApotek' => ['Catat TaskID 6/7 (masuk/keluar apotek)', ['admin', 'apoteker']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'apoteker', 'casemix', 'perawat']]], 'title' => 'Antrian Apotek RJ', 'desc' => 'Antrian apotek khusus pasien rawat jalan', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT-RJ'],
            ],
        ],

        'Administrasi & Kasir Rawat Jalan' => [
            'menu' => [
                ['group' => 'Kasir', 'groupOrder' => 9, 'order' => 2, 'route' => 'rj.antrian-kasir', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'manager umum', 'supervisor tu', 'tu']], 'bukaTransferUgd' => ['Transfer ke UGD', ['admin', 'manager umum', 'perawat', 'supervisor tu', 'tu']]], 'title' => 'Antrian Kasir RJ', 'desc' => 'Antrian kasir khusus pasien rawat jalan', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-RJ'],
            ],
            'aksiKhusus' => [
                'rj.ubahDataKunjungan' => ['label' => 'Ubah data kunjungan (kasir)', 'roles' => AksiRole::RJ_UBAH_DATA_KUNJUNGAN],
                'rj.kembalikanBatal' => ['label' => 'Kembalikan kunjungan batal', 'roles' => AksiRole::RJ_KEMBALIKAN_BATAL],
                'kasir.rj.posting' => ['label' => 'Kasir: posting pembayaran', 'roles' => AksiRole::KASIR_RJ_POSTING],
                'kasir.rj.batal' => ['label' => 'Kasir: batal transaksi', 'roles' => AksiRole::KASIR_RJ_BATAL],
                'kasir.rj.batalTransferUgd' => ['label' => 'Kasir: batal transfer ke UGD', 'roles' => AksiRole::KASIR_RJ_BATAL_TRANSFER_UGD],
                'kasir.rj.batalKunjungan' => ['label' => 'Kasir: batal kunjungan', 'roles' => AksiRole::KASIR_RJ_BATAL_KUNJUNGAN],
            ],
            'pintu' => [
                'emr-rj.administrasi.open' => ['rj.pelayanan.bukaAdministrasi', 'rj.antrian-kasir.bukaAdministrasi', 'rj.antrian-apotek.bukaAdministrasi', 'rj.daftar-bulanan.bukaAdministrasi', 'rj.emr.bukaAdministrasi', 'manajemen.rs.tu.piutang-pasien.lihat'],
                'open-ubah-data-kunjungan-rj' => ['rj.ubahDataKunjungan'],
            ],
        ],

        'Daftar Bulanan Rawat Jalan' => [
            'menu' => [
                ['group' => 'Casemix', 'groupOrder' => 8, 'order' => 2, 'route' => 'rj.daftar-bulanan', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'tu']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'tu']]], 'title' => 'Daftar Pasien Bulanan RJ', 'desc' => 'List pasien rawat jalan per bulan (mm/yyyy)', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'RJ-BLN'],
            ],
        ],

        'Kirim DINKESTA Rawat Jalan' => [
            'menu' => [
                ['group' => 'Dinas Kesehatan Tulungagung', 'groupOrder' => 8, 'order' => 1, 'route' => 'dinkesta.kirim-rj', 'aksi' => ['lihat'], 'khusus' => ['kirim' => ['Kirim ke Portal DINKESTA', ['admin', 'casemix', 'tu']]], 'title' => 'Kirim DINKESTA Rawat Jalan', 'desc' => 'Kirim bulk diagnosis ICD-10 kunjungan RJ per bulan ke Portal Satu Data Kesehatan Tulungagung', 'roles' => ['admin', 'casemix', 'tu'], 'badge' => 'DINKES'],
            ],
        ],
    ],
];
