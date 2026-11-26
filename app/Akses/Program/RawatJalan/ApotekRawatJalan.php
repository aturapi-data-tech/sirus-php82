<?php

/*
 * Hak akses program "Apotek Rawat Jalan" — kelompok Rawat Jalan (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Apotek Rawat Jalan',
    'menu' => [
        ['group' => 'Apotek', 'groupOrder' => 9, 'order' => 2, 'route' => 'rj.antrian-apotek', 'aksi' => ['lihat'], 'khusus' => ['taskIdApotek' => ['Catat TaskID 6/7 (masuk/keluar apotek)', ['admin', 'apoteker']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'apoteker', 'casemix', 'perawat']]], 'title' => 'Antrian Apotek RJ', 'desc' => 'Antrian apotek khusus pasien rawat jalan', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT-RJ'],
    ],
];
