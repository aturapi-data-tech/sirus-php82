<?php

/*
 * Hak akses kelompok GUDANG — SATU BLOK PER PROGRAM (lihat App\Support\ProgramAkses).
 *
 *  'menu'       : entri menu sidebar + Lihat/Tambah/Ubah/Hapus + tombol khusus halaman ('khusus').
 *  'aksiKhusus' : aksi di luar halaman (tombol di modal/EMR, posting/batal) — nama Gate => label, roles awal.
 *  'pintu'      : event pembuka modal => hak siapa saja yang sah membukanya.
 *
 * roles = CENTANG AWAL saja; sesudah permission dibuat (otomatis saat deploy) yang berlaku
 * centang Role Control. Pedoman: docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'judul' => 'Gudang',
    'program' => [
        'Penerimaan Barang' => [
            'menu' => [
                ['group' => 'Gudang', 'groupOrder' => 11, 'order' => 1, 'route' => 'gudang.penerimaan-medis', 'aksi' => ['lihat', 'tambah', 'ubah', 'hapus'], 'rolesAksi' => ['hapus' => ['admin', 'tu']], 'title' => 'Obat dari PBF', 'desc' => 'Penerimaan obat dari PBF / Supplier (Gudang Medis)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'RCV'],
                ['group' => 'Gudang', 'groupOrder' => 11, 'order' => 2, 'route' => 'gudang.penerimaan-non-medis', 'aksi' => ['lihat', 'tambah', 'ubah', 'hapus'], 'rolesAksi' => ['hapus' => ['admin', 'tu']], 'title' => 'Barang dari Supplier (Non-Medis)', 'desc' => 'Penerimaan barang non-medis dari supplier', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu'], 'badge' => 'RCV-N'],
            ],
            'aksiKhusus' => [
                'transaksi.batalPenerimaan' => ['label' => 'Batal penerimaan barang PBF', 'roles' => AksiRole::TRANSAKSI_BATAL_PENERIMAAN],
            ],
        ],

        'Transfer Stok' => [
            'menu' => [
                ['group' => 'Gudang', 'groupOrder' => 11, 'order' => 3, 'route' => 'gudang.transfer-stock', 'aksi' => ['lihat', 'tambah', 'ubah'], 'khusus' => ['posting' => ['Posting Transfer', ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker']]], 'title' => 'Transfer Stok Medis', 'desc' => 'Pindahkan obat antar lokasi — sumber dari Gudang Medis atau Apotek', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'TRF'],
                ['group' => 'Gudang', 'groupOrder' => 11, 'order' => 4, 'route' => 'gudang.transfer-stock-non', 'aksi' => ['lihat', 'tambah', 'ubah'], 'khusus' => ['posting' => ['Posting Transfer', ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu']]], 'title' => 'Transfer Stok Non-Medis', 'desc' => 'Pindahkan barang non-medis (ATK, RT, dll.) dari Gudang Non-Medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu'], 'badge' => 'TRF-N'],
            ],
            'aksiKhusus' => [
                'gudang.transferBatalMedis' => ['label' => 'Batal transfer stok medis', 'roles' => AksiRole::GUDANG_TRANSFER_BATAL_MEDIS],
                'gudang.transferBatalNonMedis' => ['label' => 'Batal transfer stok non-medis', 'roles' => AksiRole::GUDANG_TRANSFER_BATAL_NONMEDIS],
            ],
        ],

        'Kartu Stok & Opname' => [
            'menu' => [
                ['group' => 'Gudang', 'groupOrder' => 11, 'order' => 5, 'route' => 'gudang.kartu-stock', 'aksi' => ['lihat'], 'title' => 'Kartu Stock — Gudang Medis', 'desc' => 'Riwayat mutasi stok per produk — Gudang Medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'STK'],
                ['group' => 'Gudang', 'groupOrder' => 11, 'order' => 6, 'route' => 'gudang.kartu-stock-apt', 'aksi' => ['lihat'], 'title' => 'Kartu Stock — Apotek', 'desc' => 'Riwayat mutasi stok per produk — Apotek', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'STK-A'],
                ['group' => 'Gudang', 'groupOrder' => 11, 'order' => 7, 'route' => 'gudang.kartu-stock-non', 'aksi' => ['lihat'], 'title' => 'Kartu Stock — Non-Medis', 'desc' => 'Riwayat mutasi stok per produk — Gudang Non-Medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu'], 'badge' => 'STK-N'],
            ],
            'aksiKhusus' => [
                'gudang.opnameMedis' => ['label' => 'Stock opname medis', 'roles' => AksiRole::GUDANG_OPNAME_MEDIS],
                'gudang.opnameNonMedis' => ['label' => 'Stock opname non-medis', 'roles' => AksiRole::GUDANG_OPNAME_NONMEDIS],
            ],
        ],
    ],
];
