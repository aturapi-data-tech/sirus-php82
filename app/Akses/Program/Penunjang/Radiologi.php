<?php

/*
 * Hak akses program "Radiologi" — kelompok Penunjang (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Radiologi',
    'menu' => [
        ['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 3, 'route' => 'penunjang.radiologi.upload', 'aksi' => ['lihat', 'tambah', 'ubah'], 'khusus' => ['batal' => ['Batal order radiologi', ['admin', 'supervisor penunjang']]], 'title' => 'Upload Hasil Radiologi', 'desc' => 'Upload foto radiologi & hasil bacaan PDF', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'radiologi'], 'badge' => 'RAD'],
    ],
];
