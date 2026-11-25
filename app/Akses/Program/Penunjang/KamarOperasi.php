<?php

/*
 * Hak akses program "Kamar Operasi" — kelompok Penunjang (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'nama' => 'Kamar Operasi',
    'menu' => [
        ['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 4, 'route' => 'penunjang.kamar-operasi', 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => AppMenu::ROLES_TRANSAKSI_OK, 'ubah' => AppMenu::ROLES_TRANSAKSI_OK], 'khusus' => ['batal' => ['Batal transaksi operasi', ['admin', 'supervisor penunjang', 'supervisor tu']], 'bukaDokumenBedah' => ['Menu titik-3 & Dokumen Pelayanan Bedah', ['admin', 'manager umum', 'supervisor penunjang', 'supervisor tu', 'tu', 'perawat', 'dokter', 'casemix', 'mr', 'gizi']]], 'title' => 'Transaksi Tindakan Operasi', 'desc' => 'Tindakan operasi, tarif, & transfer biaya ke rawat inap — juga ada di grup Operasi', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'supervisor tu', 'tu', 'dokter', 'perawat'], 'badge' => 'OK'],
        ['group' => 'Operasi', 'groupOrder' => 13, 'order' => 1, 'route' => 'operasi.jadwal-operasi', 'aksi' => ['lihat', 'tambah', 'ubah', 'hapus'], 'title' => 'Jadwal Operasi', 'desc' => 'Booking & manajemen jadwal operasi pasien', 'roles' => ['admin', 'manager medis', 'dokter', 'perawat'], 'badge' => 'JDW'],
        ['group' => 'Operasi', 'groupOrder' => 13, 'order' => 2, 'route' => 'penunjang.kamar-operasi', 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => AppMenu::ROLES_TRANSAKSI_OK, 'ubah' => AppMenu::ROLES_TRANSAKSI_OK], 'khusus' => ['batal' => ['Batal transaksi operasi', ['admin', 'supervisor penunjang', 'supervisor tu']], 'bukaDokumenBedah' => ['Menu titik-3 & Dokumen Pelayanan Bedah', ['admin', 'manager umum', 'supervisor penunjang', 'supervisor tu', 'tu', 'perawat', 'dokter', 'casemix', 'mr', 'gizi']]], 'title' => 'Transaksi Tindakan Operasi', 'desc' => 'Tindakan operasi, tarif, & transfer biaya ke rawat inap', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'supervisor tu', 'tu', 'dokter', 'perawat'], 'badge' => 'OK'],
    ],
];
