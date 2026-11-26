<?php

/*
 * Hak akses program "Panduan Developer" — kelompok Sistem (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Panduan Developer',
    'menu' => [
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 9, 'route' => 'panduan-dev', 'title' => 'Standarisasi UI', 'desc' => 'Acuan design system internal — warna, tipografi & komponen', 'roles' => ['admin'], 'badge' => 'UI'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 10, 'route' => 'panduan-dev.koding-master', 'title' => 'Tutorial Koding Master', 'desc' => 'Panduan membangun modul master (2-file, event, LOV, CRUD)', 'roles' => ['admin'], 'badge' => 'DOC'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 11, 'route' => 'panduan-dev.koding-transaksi', 'title' => 'Tutorial Koding Transaksi', 'desc' => 'Pola modul transaksi RJ/UGD/RI — JSON CLOB, EMR, dokumen, administrasi', 'roles' => ['admin'], 'badge' => 'DOC'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 12, 'route' => 'panduan-dev.koding-satusehat', 'title' => 'Tutorial Koding SATUSEHAT', 'desc' => 'Kirim data FHIR R4 ke SATUSEHAT — resource, kode, checklist kolom wajib', 'roles' => ['admin'], 'badge' => 'DOC'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 13, 'route' => 'panduan-dev.koding-administrasi', 'title' => 'Tutorial Koding Administrasi', 'desc' => 'Konsep administrasi/kasir RJ/UGD/RI sampai pulang + transfer & model batal', 'roles' => ['admin'], 'badge' => 'DOC'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 14, 'route' => 'panduan-dev.alur-pelayanan', 'title' => 'Tutorial Alur Pelayanan', 'desc' => 'Alur pasien pendaftaran → EMR → apotek → kasir per jalur RJ/UGD/RI', 'roles' => ['admin'], 'badge' => 'DOC'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 15, 'route' => 'panduan-dev.koding-struktur', 'title' => 'Standar Struktur Folder', 'desc' => 'Di mana berkas ditaruh & dinamai — prefix ⚡, suffix peran/jalur, Trait vs Support, prefix URL', 'roles' => ['admin'], 'badge' => 'DOC'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 16, 'route' => 'panduan-dev.koding-skill', 'title' => 'Katalog Skill', 'desc' => 'Daftar skill repo (.claude/skills/*) + kapan dibaca', 'roles' => ['admin'], 'badge' => 'DOC'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 17, 'route' => 'panduan-dev.rujukan-kompetensi', 'title' => 'Rujukan Kompetensi (SRBK)', 'desc' => 'Alur rujukan berbasis kompetensi dua jalur (BPJS & FHIR) + FAQ permasalahan', 'roles' => ['admin'], 'badge' => 'DOC'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 18, 'route' => 'panduan-dev.mobile-jkn', 'title' => 'Panduan Mobile JKN', 'desc' => 'Cara kerja API Antrean Online BPJS — booking, checkin, kuota, host API, penanganan masalah', 'roles' => ['admin'], 'badge' => 'DOC'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 19, 'route' => 'panduan-dev.siap-api', 'title' => 'Panduan API SIAP', 'desc' => 'Bridging penggajian ke SIAP (SIRUS sebagai server) — akun & JWT, endpoint gaji dokter, pengaman, setup, uji', 'roles' => ['admin'], 'badge' => 'DOC'],
    ],
];
