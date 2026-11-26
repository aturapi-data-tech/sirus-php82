<?php

/*
 * Hak akses program "Kas TU" — kelompok Keuangan (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Kas TU',
    'menu' => [
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 1, 'route' => 'keuangan.penerimaan-kas-tu', 'aksi' => ['lihat', 'tambah'], 'title' => 'Penerimaan Kas TU', 'desc' => 'Catat penerimaan kas di luar transaksi pelayanan RS', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'CI'],
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 2, 'route' => 'keuangan.pengeluaran-kas-tu', 'aksi' => ['lihat', 'tambah'], 'title' => 'Pengeluaran Kas TU', 'desc' => 'Catat pengeluaran kas di luar transaksi pelayanan RS', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'CO'],
    ],
    'aksiKhusus' => [
        'kasTu.koreksi' => ['label' => 'Kas TU: koreksi transaksi', 'roles' => AksiRole::KAS_TU_KOREKSI],
        'kasTu.hapus' => ['label' => 'Kas TU: hapus transaksi', 'roles' => AksiRole::KAS_TU_HAPUS],
    ],
];
