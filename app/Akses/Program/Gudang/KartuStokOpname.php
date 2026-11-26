<?php

/*
 * Hak akses program "Kartu Stok & Opname" — kelompok Gudang (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Kartu Stok & Opname',
    'menu' => [
        ['group' => 'Gudang', 'groupOrder' => 11, 'order' => 5, 'route' => 'gudang.kartu-stock', 'aksi' => ['lihat'], 'title' => 'Kartu Stock — Gudang Medis', 'desc' => 'Riwayat mutasi stok per produk — Gudang Medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'STK'],
        ['group' => 'Gudang', 'groupOrder' => 11, 'order' => 6, 'route' => 'gudang.kartu-stock-apt', 'aksi' => ['lihat'], 'title' => 'Kartu Stock — Apotek', 'desc' => 'Riwayat mutasi stok per produk — Apotek', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'STK-A'],
        ['group' => 'Gudang', 'groupOrder' => 11, 'order' => 7, 'route' => 'gudang.kartu-stock-non', 'aksi' => ['lihat'], 'title' => 'Kartu Stock — Non-Medis', 'desc' => 'Riwayat mutasi stok per produk — Gudang Non-Medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu'], 'badge' => 'STK-N'],
    ],
    'aksiKhusus' => [
        'gudang.opnameMedis' => ['label' => 'Stock opname medis', 'roles' => AksiRole::GUDANG_OPNAME_MEDIS],
        'gudang.opnameNonMedis' => ['label' => 'Stock opname non-medis', 'roles' => AksiRole::GUDANG_OPNAME_NONMEDIS],
    ],
];
