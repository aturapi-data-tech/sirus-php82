<?php

/*
 * Hak akses program "Penerimaan Barang" — kelompok Gudang (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Penerimaan Barang',
    'menu' => [
        ['group' => 'Gudang', 'groupOrder' => 11, 'order' => 1, 'route' => 'gudang.penerimaan-medis', 'aksi' => ['lihat', 'tambah', 'ubah', 'hapus'], 'rolesAksi' => ['hapus' => ['admin', 'tu']], 'title' => 'Obat dari PBF', 'desc' => 'Penerimaan obat dari PBF / Supplier (Gudang Medis)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'RCV'],
        ['group' => 'Gudang', 'groupOrder' => 11, 'order' => 2, 'route' => 'gudang.penerimaan-non-medis', 'aksi' => ['lihat', 'tambah', 'ubah', 'hapus'], 'rolesAksi' => ['hapus' => ['admin', 'tu']], 'title' => 'Barang dari Supplier (Non-Medis)', 'desc' => 'Penerimaan barang non-medis dari supplier', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu'], 'badge' => 'RCV-N'],
    ],
    'aksiKhusus' => [
        'transaksi.batalPenerimaan' => ['label' => 'Batal penerimaan barang PBF', 'roles' => AksiRole::TRANSAKSI_BATAL_PENERIMAAN],
    ],
];
