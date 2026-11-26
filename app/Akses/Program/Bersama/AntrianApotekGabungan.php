<?php

/*
 * Hak akses program "Antrian Apotek Gabungan" — kelompok Bersama (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Antrian Apotek Gabungan',
    'menu' => [
        ['group' => 'Apotek', 'groupOrder' => 9, 'order' => 1, 'route' => 'apotek', 'gabungan' => ['rj.antrian-apotek', 'ugd.antrian-apotek', 'ri-resep.antrian'], 'title' => 'Antrian Apotek', 'desc' => 'Telaah resep & pelayanan kefarmasian — tab RJ, UGD, RI', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT'],
    ],
];
