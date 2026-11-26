<?php

/*
 * Hak akses program "Transfer Stok" — kelompok Gudang (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Transfer Stok',
    'menu' => [
        ['group' => 'Gudang', 'groupOrder' => 11, 'order' => 3, 'route' => 'gudang.transfer-stock', 'aksi' => ['lihat', 'tambah', 'ubah'], 'khusus' => ['posting' => ['Posting Transfer', ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker']]], 'title' => 'Transfer Stok Medis', 'desc' => 'Pindahkan obat antar lokasi — sumber dari Gudang Medis atau Apotek', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'TRF'],
        ['group' => 'Gudang', 'groupOrder' => 11, 'order' => 4, 'route' => 'gudang.transfer-stock-non', 'aksi' => ['lihat', 'tambah', 'ubah'], 'khusus' => ['posting' => ['Posting Transfer', ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu']]], 'title' => 'Transfer Stok Non-Medis', 'desc' => 'Pindahkan barang non-medis (ATK, RT, dll.) dari Gudang Non-Medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu'], 'badge' => 'TRF-N'],
    ],
    'aksiKhusus' => [
        'gudang.transferBatalMedis' => ['label' => 'Batal transfer stok medis', 'roles' => AksiRole::GUDANG_TRANSFER_BATAL_MEDIS],
        'gudang.transferBatalNonMedis' => ['label' => 'Batal transfer stok non-medis', 'roles' => AksiRole::GUDANG_TRANSFER_BATAL_NONMEDIS],
    ],
];
