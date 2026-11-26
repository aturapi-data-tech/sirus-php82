<?php

/*
 * Hak akses program "Buku Besar & Laporan Keuangan" — kelompok Keuangan (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'nama' => 'Buku Besar & Laporan Keuangan',
    'menu' => [
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 14, 'route' => 'keuangan.tutup-buku', 'aksi' => ['lihat', 'ubah'], 'title' => 'Tutup Buku Tahunan', 'desc' => 'Saldo awal tahun berikutnya: akun (Kas & Neraca), stok, hutang & DP supplier', 'roles' => ['admin'], 'badge' => 'TB'],
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 9, 'route' => 'keuangan.saldo-kas', 'aksi' => ['lihat', 'ubah'], 'rolesAksi' => ['ubah' => AppMenu::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Saldo Kas', 'desc' => 'Posisi saldo kas/bank per tanggal — riwayat transaksi & edit saldo awal tahun (admin)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'SK'],
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 10, 'route' => 'keuangan.buku-besar', 'aksi' => ['lihat'], 'title' => 'Buku Besar', 'desc' => 'Mutasi & saldo per akun dalam periode tertentu', 'roles' => ['admin', 'manager umum'], 'badge' => 'BB'],
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 11, 'route' => 'keuangan.laba-rugi', 'aksi' => ['lihat'], 'title' => 'Laporan Laba Rugi', 'desc' => 'Pendapatan vs beban — laba/rugi periode berjalan', 'roles' => ['admin', 'manager umum'], 'badge' => 'LR'],
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 12, 'route' => 'keuangan.neraca', 'aksi' => ['lihat'], 'title' => 'Laporan Neraca', 'desc' => 'Posisi aktiva, kewajiban & modal per tanggal', 'roles' => ['admin', 'manager umum'], 'badge' => 'NRC'],
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 13, 'route' => 'keuangan.analisa-perputaran-obat', 'aksi' => ['lihat'], 'title' => 'Analisa Perputaran Obat', 'desc' => 'Fast / slow / dead moving per lokasi — sorot modal stok yang mengendap', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'STOK'],
    ],
];
