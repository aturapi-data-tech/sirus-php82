<?php

/*
 * Hak akses kelompok SISTEM — SATU BLOK PER PROGRAM (lihat App\Support\ProgramAkses).
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
    'judul' => 'Sistem',
    'program' => [
        // Formulir & daftar tarif TERBUKA untuk semua role: saat SIMRS bermasalah, siapa pun yang
        // masih bisa membuka aplikasi harus dapat mengunduh formulir cadangannya.
        'Down Time' => [
            'menu' => [
                ['group' => 'Down Time', 'groupOrder' => 14, 'order' => 1, 'route' => 'downtime.formulir-manual', 'aksi' => ['lihat'], 'title' => 'Formulir Manual Down Time', 'desc' => 'Formulir cadangan saat SIMRS tidak dapat diakses — EMR, administrasi, kasir & apotek RJ/UGD/RI + log & rekonsiliasi IT', 'roles' => [AppMenu::SEMUA_ROLE], 'badge' => 'DT'],
                ['group' => 'Down Time', 'groupOrder' => 14, 'order' => 2, 'route' => 'downtime.daftar-tarif', 'aksi' => ['lihat'], 'title' => 'Daftar Tarif Down Time', 'desc' => 'Price list acuan pengisian nominal formulir manual — kamar, visite & konsul, jasa medis/dokter/karyawan, lab, radiologi, obat & lain-lain', 'roles' => [AppMenu::SEMUA_ROLE], 'badge' => 'TRF'],
                ['group' => 'Down Time', 'groupOrder' => 14, 'order' => 3, 'route' => 'downtime.pelaporan', 'aksi' => ['lihat', 'tambah', 'ubah'], 'title' => 'Pelaporan Down Time SIMRS', 'desc' => 'Rekam formulir DT-01 sesudah layanan pulih — kejadian, penanganan, dampak & evaluasi', 'roles' => ['admin', 'manager umum'], 'badge' => 'DT-01'],
            ],
            'aksiKhusus' => [
                'downtime.pelaporanHapus' => ['label' => 'Hapus pelaporan down time', 'roles' => AksiRole::DOWNTIME_PELAPORAN_HAPUS],
            ],
        ],

        'Monitoring Sistem' => [
            'menu' => [
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 1, 'route' => 'database-monitor.monitoring-dashboard', 'title' => 'Oracle Session Monitor', 'desc' => 'Locks, long-running SQL & kill session', 'roles' => ['admin'], 'badge' => 'DB'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 2, 'route' => 'database-monitor.monitoring-mount-control', 'title' => 'Mounting Control', 'desc' => 'Mount/unmount share folder jaringan (CIFS/SMB)', 'roles' => ['admin', 'perawat'], 'badge' => 'MNT'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 5, 'route' => 'database-monitor.user-online', 'title' => 'User Online', 'desc' => 'Daftar user yang sedang aktif login (last_seen_at < threshold menit)', 'roles' => ['admin'], 'badge' => 'ONL'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 6, 'route' => 'database-monitor.log-bpjs', 'title' => 'Log BPJS / E-Klaim API', 'desc' => 'Riwayat pemanggilan V-Claim, Antrean, Aplicares, I-Care, SIRS, iDRG/INACBG', 'roles' => ['admin'], 'badge' => 'LOG'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 18, 'route' => 'database-monitor.monitor-server', 'title' => 'Monitor Server', 'desc' => 'Worker PHP-FPM, timeout Apache (504), slowlog & error Laravel', 'roles' => ['admin'], 'badge' => 'FPM'],
            ],
        ],

        'User & Hak Akses' => [
            'menu' => [
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 3, 'route' => 'database-monitor.user-control', 'title' => 'User Control', 'desc' => 'Kelola user & hak akses sistem', 'roles' => ['admin'], 'badge' => 'USR'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 4, 'route' => 'database-monitor.role-control', 'title' => 'Role Control', 'desc' => 'Kelola role & permission sistem', 'roles' => ['admin'], 'badge' => 'ROL'],
            ],
        ],

        'Ruang Server' => [
            'menu' => [
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 7, 'route' => 'sistem.pemantauan-suhu-ruang-server', 'title' => 'Pemantauan Suhu Ruang Server', 'desc' => 'Formulir MRMIK 2.2 — suhu & status AC ruang server per bulan', 'roles' => ['admin', 'manager umum'], 'badge' => 'SRV'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 8, 'route' => 'sistem.pemantauan-akses-ruang-server', 'title' => 'Pemantauan Akses Ruang Server', 'desc' => 'Formulir MRMIK 2.2 — siapa keluar-masuk ruang server, kapan & untuk apa', 'roles' => ['admin', 'manager umum'], 'badge' => 'AKS'],
            ],
            'aksiKhusus' => [
                'sistem.pemantauanRuangServer.hapus' => ['label' => 'Hapus lembar pemantauan ruang server', 'roles' => AksiRole::SISTEM_PEMANTAUAN_RUANG_SERVER_HAPUS],
                'sistem.pemantauanRuangServer.bukaKunci' => ['label' => 'Buka kunci pemantauan ruang server', 'roles' => AksiRole::SISTEM_PEMANTAUAN_RUANG_SERVER_BUKA_KUNCI],
            ],
        ],

        'Panduan Developer' => [
            'menu' => [
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 9, 'route' => 'panduan-dev', 'title' => 'Standarisasi UI', 'desc' => 'Acuan design system internal — warna, tipografi & komponen', 'roles' => ['admin'], 'badge' => 'UI'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 10, 'route' => 'panduan-dev.koding-master', 'title' => 'Tutorial Koding Master', 'desc' => 'Panduan membangun modul master (2-file, event, LOV, CRUD)', 'roles' => ['admin'], 'badge' => 'DOC'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 11, 'route' => 'panduan-dev.koding-transaksi', 'title' => 'Tutorial Koding Transaksi', 'desc' => 'Pola modul transaksi RJ/UGD/RI — JSON CLOB, EMR, dokumen, administrasi', 'roles' => ['admin'], 'badge' => 'DOC'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 12, 'route' => 'panduan-dev.koding-satusehat', 'title' => 'Tutorial Koding SATUSEHAT', 'desc' => 'Kirim data FHIR R4 ke SATUSEHAT — resource, kode, checklist kolom wajib', 'roles' => ['admin'], 'badge' => 'DOC'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 13, 'route' => 'panduan-dev.koding-administrasi', 'title' => 'Tutorial Koding Administrasi', 'desc' => 'Konsep administrasi/kasir RJ/UGD/RI sampai pulang + transfer & model batal', 'roles' => ['admin'], 'badge' => 'DOC'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 14, 'route' => 'panduan-dev.alur-pelayanan', 'title' => 'Tutorial Alur Pelayanan', 'desc' => 'Alur pasien pendaftaran → EMR → apotek → kasir per jalur RJ/UGD/RI', 'roles' => ['admin'], 'badge' => 'DOC'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 15, 'route' => 'panduan-dev.koding-struktur', 'title' => 'Standar Struktur Folder', 'desc' => 'Di mana berkas ditaruh & dinamai — prefix ⚡, suffix peran/jalur, Trait vs Support, prefix URL', 'roles' => ['admin'], 'badge' => 'DOC'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 16, 'route' => 'panduan-dev.koding-skill', 'title' => 'Katalog Skill', 'desc' => 'Daftar skill repo (.claude/skills/*) + kapan dibaca', 'roles' => ['admin'], 'badge' => 'DOC'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 17, 'route' => 'panduan-dev.mobile-jkn', 'title' => 'Panduan Mobile JKN', 'desc' => 'Cara kerja API Antrean Online BPJS — booking, checkin, kuota, host API, penanganan masalah', 'roles' => ['admin'], 'badge' => 'DOC'],
                ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 18, 'route' => 'panduan-dev.siap-api', 'title' => 'Panduan API SIAP', 'desc' => 'Bridging penggajian ke SIAP (SIRUS sebagai server) — akun & JWT, endpoint gaji dokter, pengaman, setup, uji', 'roles' => ['admin'], 'badge' => 'DOC'],
            ],
        ],
    ],
];
