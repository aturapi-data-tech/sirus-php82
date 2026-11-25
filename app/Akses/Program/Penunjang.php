<?php

/*
 * Hak akses kelompok PENUNJANG — SATU BLOK PER PROGRAM (lihat App\Support\ProgramAkses).
 *
 *  'menu'       : entri menu sidebar + Lihat/Tambah/Ubah/Hapus + tombol khusus halaman ('khusus').
 *  'aksiKhusus' : aksi di luar halaman (tombol di modal/EMR, posting/batal) — nama Gate => label, roles awal.
 *  'pintu'      : event pembuka modal => hak siapa saja yang sah membukanya.
 *
 * roles = CENTANG AWAL saja; sesudah permission dibuat (otomatis saat deploy) yang berlaku
 * centang Role Control. Pedoman: docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;
use App\Support\AksiRole;

return [
    'judul' => 'Penunjang',
    'program' => [
        'Laboratorium' => [
            'menu' => [
                ['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 1, 'route' => 'penunjang.laborat', 'aksi' => ['lihat', 'tambah', 'ubah'], 'khusus' => ['prosesStatus' => ['Proses / ubah status pemeriksaan', ['admin', 'laboratorium']], 'batal' => ['Batal pendaftaran / transaksi lab', ['admin', 'supervisor penunjang']]], 'title' => 'Transaksi Laboratorium', 'desc' => 'Input hasil pemeriksaan laboratorium pasien', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'laboratorium'], 'badge' => 'LAB'],
                ['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 2, 'route' => 'penunjang.laborat.lab-luar', 'aksi' => ['lihat', 'ubah'], 'title' => 'Upload Hasil Lab Luar', 'desc' => 'Upload PDF hasil lab luar yang sudah Selesai', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'laboratorium'], 'badge' => 'LAB-LUAR'],
            ],
        ],

        'Radiologi' => [
            'menu' => [
                ['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 3, 'route' => 'penunjang.radiologi.upload', 'aksi' => ['lihat', 'tambah', 'ubah'], 'khusus' => ['batal' => ['Batal order radiologi', ['admin', 'supervisor penunjang']]], 'title' => 'Upload Hasil Radiologi', 'desc' => 'Upload foto radiologi & hasil bacaan PDF', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'radiologi'], 'badge' => 'RAD'],
            ],
        ],

        'Kamar Operasi' => [
            'menu' => [
                ['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 4, 'route' => 'penunjang.kamar-operasi', 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => AppMenu::ROLES_TRANSAKSI_OK, 'ubah' => AppMenu::ROLES_TRANSAKSI_OK], 'khusus' => ['batal' => ['Batal transaksi operasi', ['admin', 'supervisor penunjang', 'supervisor tu']], 'bukaDokumenBedah' => ['Menu titik-3 & Dokumen Pelayanan Bedah', ['admin', 'manager umum', 'supervisor penunjang', 'supervisor tu', 'tu', 'perawat', 'dokter', 'casemix', 'mr', 'gizi']]], 'title' => 'Transaksi Tindakan Operasi', 'desc' => 'Tindakan operasi, tarif, & transfer biaya ke rawat inap — juga ada di grup Operasi', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'supervisor tu', 'tu', 'dokter', 'perawat'], 'badge' => 'OK'],
                ['group' => 'Operasi', 'groupOrder' => 13, 'order' => 1, 'route' => 'operasi.jadwal-operasi', 'aksi' => ['lihat', 'tambah', 'ubah', 'hapus'], 'title' => 'Jadwal Operasi', 'desc' => 'Booking & manajemen jadwal operasi pasien', 'roles' => ['admin', 'manager medis', 'dokter', 'perawat'], 'badge' => 'JDW'],
                ['group' => 'Operasi', 'groupOrder' => 13, 'order' => 2, 'route' => 'penunjang.kamar-operasi', 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => AppMenu::ROLES_TRANSAKSI_OK, 'ubah' => AppMenu::ROLES_TRANSAKSI_OK], 'khusus' => ['batal' => ['Batal transaksi operasi', ['admin', 'supervisor penunjang', 'supervisor tu']], 'bukaDokumenBedah' => ['Menu titik-3 & Dokumen Pelayanan Bedah', ['admin', 'manager umum', 'supervisor penunjang', 'supervisor tu', 'tu', 'perawat', 'dokter', 'casemix', 'mr', 'gizi']]], 'title' => 'Transaksi Tindakan Operasi', 'desc' => 'Tindakan operasi, tarif, & transfer biaya ke rawat inap', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'supervisor tu', 'tu', 'dokter', 'perawat'], 'badge' => 'OK'],
            ],
        ],

        // Hasil lab/radiologi per PASIEN lintas kunjungan RJ/UGD/RI (rekam medis, modul Laborat/Radiologi)
        // — bukan milik satu jalur, jadi tetap satu hak.
        'Hasil Penunjang' => [
            'aksiKhusus' => [
                'penunjang.lihatHasil' => ['label' => 'Lihat & cetak hasil penunjang', 'roles' => AksiRole::PENUNJANG_LIHAT_HASIL],
            ],
        ],
    ],
];
