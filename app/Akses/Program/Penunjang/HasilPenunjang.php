<?php

/*
 * Hak akses program "Hasil Penunjang" — kelompok Penunjang (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Hasil Penunjang',
    // Hasil lab/radiologi per PASIEN lintas kunjungan RJ/UGD/RI (rekam medis, modul Laborat/Radiologi)
    // — bukan milik satu jalur, jadi tetap satu hak.
    'aksiKhusus' => [
        'penunjang.lihatHasil' => ['label' => 'Lihat & cetak hasil penunjang', 'roles' => AksiRole::PENUNJANG_LIHAT_HASIL],
    ],
];
