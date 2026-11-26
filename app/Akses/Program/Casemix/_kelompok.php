<?php

/*
 * Hak akses kelompok CASEMIX — SATU BERKAS PER PROGRAM di folder ini (lihat App\Support\ProgramAkses).
 *
 *  'menu'       : entri menu sidebar + Lihat/Tambah/Ubah/Hapus + tombol khusus halaman ('khusus').
 *  'aksiKhusus' : aksi di luar halaman (tombol di modal/EMR, posting/batal) — nama Gate => label, roles awal.
 *  'pintu'      : event pembuka modal => hak siapa saja yang sah membukanya.
 *
 * roles = CENTANG AWAL saja; sesudah permission dibuat (otomatis saat deploy) yang berlaku
 * centang Role Control. Pedoman: docs/standar-hak-akses.md.
 */

return [
    'judul' => 'Casemix',
    // Urutan = urutan tampil di Role Control. Nama berkas program di folder ini (tanpa .php).
    'program' => [
        'CasemixKlaim',
    ],
];
