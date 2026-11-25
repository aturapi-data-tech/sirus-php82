<?php

/*
 * Hak akses program "Gizi Rawat Inap" — kelompok Rawat Inap (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Gizi Rawat Inap',
    'menu' => [
        ['group' => 'Rawat Inap', 'groupOrder' => 7, 'order' => 3, 'route' => 'ri.gizi', 'aksi' => ['lihat'], 'khusus' => ['bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'gizi', 'mr', 'perawat']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'gizi', 'perawat', 'tu']], 'bukaModulDokumenEntri' => ['Buka Modul Dokumen (panel entri)', ['admin', 'apoteker', 'casemix', 'dokter', 'gizi', 'perawat']], 'bukaAdministrasiEntri' => ['Buka Administrasi (panel entri)', ['admin', 'apoteker', 'casemix', 'gizi', 'perawat']]], 'title' => 'Gizi Rawat Inap', 'desc' => 'Program diet harian pasien RI & rekap porsi dapur', 'roles' => ['admin', 'manager medis', 'manager umum', 'gizi'], 'badge' => 'GIZI'],
    ],
];
