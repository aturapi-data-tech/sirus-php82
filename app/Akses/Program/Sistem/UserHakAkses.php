<?php

/*
 * Hak akses program "User & Hak Akses" — kelompok Sistem (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'User & Hak Akses',
    'menu' => [
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 3, 'route' => 'database-monitor.user-control', 'title' => 'User Control', 'desc' => 'Kelola user & hak akses sistem', 'roles' => ['admin'], 'badge' => 'USR'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 4, 'route' => 'database-monitor.role-control', 'title' => 'Role Control', 'desc' => 'Kelola role & permission sistem', 'roles' => ['admin'], 'badge' => 'ROL'],
    ],
];
