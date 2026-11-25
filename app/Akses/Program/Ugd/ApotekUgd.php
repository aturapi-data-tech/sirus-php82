<?php

/*
 * Hak akses program "Apotek UGD" — kelompok UGD (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Apotek UGD',
    'menu' => [
        ['group' => 'Apotek', 'groupOrder' => 9, 'order' => 3, 'route' => 'ugd.antrian-apotek', 'aksi' => ['lihat'], 'khusus' => ['taskIdApotek' => ['Catat TaskID 6/7 (masuk/keluar apotek)', ['admin', 'apoteker']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'apoteker', 'casemix', 'perawat']]], 'title' => 'Antrian Apotek UGD', 'desc' => 'Antrian apotek khusus pasien UGD', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT-UGD'],
    ],
];
