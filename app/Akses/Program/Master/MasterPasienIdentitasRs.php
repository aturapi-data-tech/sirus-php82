<?php

/*
 * Hak akses program "Master Pasien & Identitas RS" — kelompok Master Data (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'nama' => 'Master Pasien & Identitas RS',
    'menu' => [
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 3, 'route' => 'master.pasien', 'khusus' => ['ambilIhs' => ['Ambil IHS SATUSEHAT', ['admin', 'mr']]], 'title' => 'Master Pasien', 'desc' => 'Kelola data pasien & rekam medis', 'roles' => [...AppMenu::MASTER_ROLES, 'mr'], 'badge' => 'Pelayanan'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 7, 'route' => 'master.agama', 'title' => 'Master Agama', 'desc' => 'Kelola data agama pasien', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 16, 'route' => 'master.identitas', 'title' => 'Master Identitas RS', 'desc' => 'Kop cetakan (nama, alamat, telepon) + setelan PPN & harga pokok penerimaan', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
    ],
];
