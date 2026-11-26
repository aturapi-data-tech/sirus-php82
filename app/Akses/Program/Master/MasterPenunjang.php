<?php

/*
 * Hak akses program "Master Penunjang" — kelompok Master Data (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'nama' => 'Master Penunjang',
    'menu' => [
        ['group' => 'Master Laboratorium', 'groupOrder' => 2, 'order' => 1, 'route' => 'master.laborat', 'title' => 'Master Laboratorium', 'desc' => 'Kelola kategori lab & item pemeriksaan', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Lab'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 5, 'route' => 'master.radiologis', 'title' => 'Master Radiologi', 'desc' => 'Kelola data radiologi', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
    ],
];
