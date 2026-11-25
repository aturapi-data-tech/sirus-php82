<?php

/*
 * Hak akses kelompok UGD — SATU BLOK PER PROGRAM (lihat App\Support\ProgramAkses).
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
    'judul' => 'UGD',
    'program' => [
        'Pendaftaran UGD' => [
            'menu' => [
                ['group' => 'Unit Gawat Darurat', 'groupOrder' => 6, 'order' => 1, 'route' => 'ugd.daftar', 'khusus' => ['bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'mr', 'perawat', 'supervisor tu', 'tu']], 'bukaDiagnosa' => ['Buka Diagnosa', ['admin', 'casemix', 'dokter', 'mr']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'mr', 'tu']]], 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => AppMenu::ROLES_PENDAFTARAN, 'ubah' => AppMenu::ROLES_PENDAFTARAN], 'title' => 'Daftar UGD', 'desc' => 'Pendaftaran & manajemen pasien Unit Gawat Darurat harian', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'mr'], 'badge' => 'UGD'],
            ],
            'pintu' => [
                'daftar-ugd.diagnosa.open' => ['ugd.daftar.bukaDiagnosa'],
            ],
        ],

        'Pelayanan UGD' => [
            'menu' => [
                ['group' => 'Unit Gawat Darurat', 'groupOrder' => 6, 'order' => 2, 'route' => 'ugd.pelayanan', 'aksi' => ['lihat'], 'khusus' => ['bukaCekPesertaBpjs' => ['Cek Peserta BPJS', ['admin', 'casemix', 'dokter', 'mr', 'perawat']], 'bukaRekamMedis' => ['Buka Rekam Medis', ['admin', 'apoteker', 'casemix', 'dokter', 'laboratorium', 'mr', 'perawat']], 'bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'mr', 'perawat']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'perawat', 'tu']], 'bukaTransferRi' => ['Transfer ke RI', ['admin', 'manager umum', 'perawat', 'supervisor tu', 'tu']]], 'title' => 'Pelayanan UGD', 'desc' => 'EMR UGD — triase, asesmen, tindakan & resep oleh dokter/perawat UGD', 'roles' => ['admin', 'manager medis', 'dokter', 'perawat', 'apoteker', 'laboratorium'], 'badge' => 'PEL-UGD'],
            ],
            'pintu' => [
                'emr-ugd.rekam-medis.open' => ['ugd.pelayanan.bukaRekamMedis'],
                'cek-peserta-bpjs.open' => ['ugd.pelayanan.bukaCekPesertaBpjs'],
                'open-transfer-ugd-ke-ri' => ['ugd.pelayanan.bukaTransferRi', 'ugd.antrian-kasir.bukaTransferRi'],
            ],
        ],

        // SBAR UGD: aturan pemilik entri & dokter kunjungan tetap di kode; di sini pengecualiannya.
        'EMR UGD' => [
            'aksiKhusus' => [
                'ugd.emr.bukaAdministrasi' => ['label' => 'EMR: buka Administrasi', 'roles' => ['Admin', 'Casemix', 'Perawat']],
                'ugd.emr.bukaEresep' => ['label' => 'EMR: buka E-Resep', 'roles' => ['Admin', 'Dokter', 'Perawat']],
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
            ],
        ],

        // Hak modul dokumen JALUR INI (dulu satu hak untuk RJ/UGD/RI). Sel Aksi: <x-modul-dokumen.aksi-entri jalur="..">.
        'Modul Dokumen UGD' => [
            'aksiKhusus' => [
                'ugd.dokumen.hapus' => ['label' => 'Hapus entri dokumen', 'roles' => AksiRole::DOKUMEN_HAPUS, 'menggantikan' => 'dokumen.hapus'],
                'ugd.dokumen.bukaKunci' => ['label' => 'Buka kunci (cabut TTD) dokumen', 'roles' => AksiRole::DOKUMEN_BUKA_KUNCI, 'menggantikan' => 'dokumen.bukaKunci'],
                'ugd.dokumen.ttdPerwakilan' => ['label' => 'TTD atas nama pejabat (perwakilan)', 'roles' => AksiRole::DOKUMEN_TTD_PERWAKILAN, 'menggantikan' => 'dokumen.ttdPerwakilan'],
            ],
        ],

        'Bridging UGD' => [
            'aksiKhusus' => [
                'ugd.satusehat.kirim' => ['label' => 'Kirim SATUSEHAT', 'roles' => AksiRole::SATUSEHAT_KIRIM, 'menggantikan' => 'satusehat.kirim'],
            ],
            'pintu' => [
                'daftar-ugd.satu-sehat.open' => ['ugd.satusehat.kirim'],
            ],
        ],

        'Apotek UGD' => [
            'menu' => [
                ['group' => 'Apotek', 'groupOrder' => 9, 'order' => 3, 'route' => 'ugd.antrian-apotek', 'aksi' => ['lihat'], 'khusus' => ['taskIdApotek' => ['Catat TaskID 6/7 (masuk/keluar apotek)', ['admin', 'apoteker']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'apoteker', 'casemix', 'perawat']]], 'title' => 'Antrian Apotek UGD', 'desc' => 'Antrian apotek khusus pasien UGD', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT-UGD'],
            ],
        ],

        'Administrasi & Kasir UGD' => [
            'menu' => [
                ['group' => 'Kasir', 'groupOrder' => 9, 'order' => 3, 'route' => 'ugd.antrian-kasir', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'manager umum', 'supervisor tu', 'tu']], 'bukaTransferRi' => ['Transfer ke RI', ['admin', 'manager umum', 'perawat', 'supervisor tu', 'tu']]], 'title' => 'Antrian Kasir UGD', 'desc' => 'Antrian kasir khusus pasien UGD', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-UGD'],
            ],
            'aksiKhusus' => [
                'ugd.ubahDataKunjungan' => ['label' => 'Ubah data kunjungan (kasir)', 'roles' => AksiRole::UGD_UBAH_DATA_KUNJUNGAN],
                'kasir.ugd.posting' => ['label' => 'Kasir: posting pembayaran', 'roles' => AksiRole::KASIR_UGD_POSTING],
                'kasir.ugd.batal' => ['label' => 'Kasir: batal transaksi', 'roles' => AksiRole::KASIR_UGD_BATAL],
                'kasir.ugd.batalTransferRi' => ['label' => 'Kasir: batal transfer ke RI', 'roles' => AksiRole::KASIR_UGD_BATAL_TRANSFER_RI],
                'kasir.ugd.batalKunjungan' => ['label' => 'Kasir: batal kunjungan', 'roles' => AksiRole::KASIR_UGD_BATAL_KUNJUNGAN],
            ],
            'pintu' => [
                'emr-ugd.administrasi.open' => ['ugd.pelayanan.bukaAdministrasi', 'ugd.antrian-kasir.bukaAdministrasi', 'ugd.antrian-apotek.bukaAdministrasi', 'ugd.daftar-bulanan.bukaAdministrasi', 'ugd.emr.bukaAdministrasi', 'manajemen.rs.tu.piutang-pasien.lihat'],
                'open-ubah-data-kunjungan-ugd' => ['ugd.ubahDataKunjungan'],
            ],
        ],

        'Daftar Bulanan UGD' => [
            'menu' => [
                ['group' => 'Casemix', 'groupOrder' => 8, 'order' => 3, 'route' => 'ugd.daftar-bulanan', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'tu']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'tu']]], 'title' => 'Daftar Pasien Bulanan UGD', 'desc' => 'List pasien UGD per bulan (mm/yyyy)', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'UGD-BLN'],
            ],
        ],
    ],
];
