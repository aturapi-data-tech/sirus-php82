<?php

/*
 * Hak akses program "Down Time" — kelompok Sistem (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;
use App\Support\AksiRole;

return [
    'nama' => 'Down Time',
    // Formulir & daftar tarif TERBUKA untuk semua role: saat SIMRS bermasalah, siapa pun yang
    // masih bisa membuka aplikasi harus dapat mengunduh formulir cadangannya.
    'menu' => [
        ['group' => 'Down Time', 'groupOrder' => 15, 'order' => 1, 'route' => 'downtime.formulir-manual', 'aksi' => ['lihat'], 'title' => 'Formulir Manual Down Time', 'desc' => 'Formulir cadangan saat SIMRS tidak dapat diakses — EMR, administrasi, kasir & apotek RJ/UGD/RI + log & rekonsiliasi IT', 'roles' => [AppMenu::SEMUA_ROLE], 'badge' => 'DT'],
        ['group' => 'Down Time', 'groupOrder' => 15, 'order' => 2, 'route' => 'downtime.daftar-tarif', 'aksi' => ['lihat'], 'title' => 'Daftar Tarif Down Time', 'desc' => 'Price list acuan pengisian nominal formulir manual — kamar, visite & konsul, jasa medis/dokter/karyawan, lab, radiologi, obat & lain-lain', 'roles' => [AppMenu::SEMUA_ROLE], 'badge' => 'TRF'],
        ['group' => 'Down Time', 'groupOrder' => 15, 'order' => 3, 'route' => 'downtime.pelaporan', 'aksi' => ['lihat', 'tambah', 'ubah'], 'title' => 'Pelaporan Down Time SIMRS', 'desc' => 'Rekam formulir DT-01 sesudah layanan pulih — kejadian, penanganan, dampak & evaluasi', 'roles' => ['admin', 'manager umum'], 'badge' => 'DT-01'],
    ],
    'aksiKhusus' => [
        'downtime.pelaporanHapus' => ['label' => 'Hapus pelaporan down time', 'roles' => AksiRole::DOWNTIME_PELAPORAN_HAPUS],
    ],
];
