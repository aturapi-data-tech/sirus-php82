<?php

/*
 * Hak akses program "Master Akuntansi" — kelompok Master Data (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'nama' => 'Master Akuntansi',
    'menu' => [
        ['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 1, 'route' => 'master.group-akun', 'title' => 'Master Group Akun', 'desc' => 'Kelola group akun (Aktiva, Kewajiban, Modal, Pendapatan, Beban)', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Akuntansi'],
        ['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 2, 'route' => 'master.akun', 'title' => 'Master Akun', 'desc' => 'Kelola COA (Chart of Account) — daftar akun & saldo awal', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Akuntansi'],
        ['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 3, 'route' => 'master.konf-akun-trans', 'title' => 'Master Konfigurasi Akun Transaksi', 'desc' => 'Mapping akun debit/kredit untuk tiap jenis transaksi (RJ, UGD, RI, Resep, dll.)', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Akuntansi'],
    ],
];
