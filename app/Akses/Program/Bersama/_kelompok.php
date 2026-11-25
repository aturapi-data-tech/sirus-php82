<?php

/*
 * Hal BERSAMA beberapa jalur yang TIDAK punya centang sendiri — kelompok ini tak tampil sebagai
 * hak di Role Control (keputusan user 2026-11-25: hak dipetakan per kelompok RJ/UGD/RI/...).
 *
 *  - Halaman bertab gabungan (Antrian Apotek, Antrian Kasir): menu + tabnya mengikuti hak
 *    halaman asli tiap tab, yang didefinisikan di kelompok jalurnya masing-masing.
 *  - Pintu modal yang dibuka dari lebih dari satu jalur: daftar pembukanya = hak per jalur.
 *
 * Lihat App\Support\ProgramAkses & docs/standar-hak-akses.md.
 */

return [
    'judul' => 'Bersama',
    // Urutan = urutan tampil di Role Control. Nama berkas program di folder ini (tanpa .php).
    'program' => [
        'AntrianApotekGabungan',
        'AntrianKasirGabungan',
        'PintuModalLintasJalur',
    ],
];
