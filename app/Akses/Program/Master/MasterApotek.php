<?php

/*
 * Hak akses program "Master Apotek" — kelompok Master Data (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'nama' => 'Master Apotek',
    'menu' => [
        ['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 1, 'route' => 'master.obat', 'title' => 'Master Obat', 'desc' => 'Kelola data obat & farmasi', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Apotek'],
        ['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 2, 'route' => 'master.obat-kronis', 'title' => 'Master Obat Kronis', 'desc' => 'Daftar obat kronis BPJS — max qty per resep & tarif klaim', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Kronis'],
        ['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 3, 'route' => 'master.signa-catatan', 'title' => 'Master Catatan Khusus Signa', 'desc' => 'LOV catatan khusus signa untuk e-resep (RJ/UGD/RI)', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Apotek'],
        ['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 4, 'route' => 'master.interaksi-obat', 'title' => 'Master Interaksi Obat', 'desc' => 'Kelola kelompok interaksi antar obat & produk anggotanya', 'roles' => [...AppMenu::MASTER_ROLES, 'apoteker'], 'badge' => 'Apotek'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 18, 'route' => 'master.automatic-stop-order', 'title' => 'Master Automatic Stop Order', 'desc' => 'Automatic Stop Order: golongan obat, batas hari order berhenti otomatis, dan pemetaan obat ke golongan', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Apotek'],
    ],
];
