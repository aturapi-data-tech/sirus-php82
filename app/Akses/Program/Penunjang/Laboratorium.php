<?php

/*
 * Hak akses program "Laboratorium" — kelompok Penunjang (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Laboratorium',
    'menu' => [
        ['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 1, 'route' => 'penunjang.laborat', 'aksi' => ['lihat', 'tambah', 'ubah'], 'khusus' => ['prosesStatus' => ['Proses / ubah status pemeriksaan', ['admin', 'laboratorium']], 'batal' => ['Batal pendaftaran / transaksi lab', ['admin', 'supervisor penunjang']]], 'title' => 'Transaksi Laboratorium', 'desc' => 'Input hasil pemeriksaan laboratorium pasien', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'laboratorium'], 'badge' => 'LAB'],
        ['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 2, 'route' => 'penunjang.laborat.lab-luar', 'aksi' => ['lihat', 'ubah'], 'title' => 'Upload Hasil Lab Luar', 'desc' => 'Upload PDF hasil lab luar yang sudah Selesai', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'laboratorium'], 'badge' => 'LAB-LUAR'],
    ],
];
