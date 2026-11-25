<?php

/*
 * Hak akses program "Master Ruang & Layanan" — kelompok Master Data (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'nama' => 'Master Ruang & Layanan',
    'menu' => [
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 1, 'route' => 'master.poli', 'title' => 'Master Poli', 'desc' => 'Kelola data poli & ruangan', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 9, 'route' => 'master.kamar', 'title' => 'Master Kamar', 'desc' => 'Kelola bangsal, kamar & bed rawat inap', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 10, 'route' => 'master.kelas', 'title' => 'Master Kelas Rawat', 'desc' => 'Kelola kelas kamar & mapping Aplicares / SIRS', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 13, 'route' => 'master.stocklocations', 'title' => 'Master Lokasi Stok', 'desc' => 'Kelola kode lokasi stok (gudang, apotek, ruangan, klinik) untuk transfer obat & barang', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 6, 'route' => 'master.others', 'title' => 'Master Lain-lain', 'desc' => 'Kelola data lain-lain', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
    ],
];
