<?php

/*
 * Hak akses program "Daftar Bulanan & Klaim Rawat Inap" — kelompok Rawat Inap (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Daftar Bulanan & Klaim Rawat Inap',
    'menu' => [
        ['group' => 'Casemix', 'groupOrder' => 8, 'order' => 4, 'route' => 'ri.daftar-bulanan', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'tu']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'tu']]], 'title' => 'Daftar Pasien Bulanan RI', 'desc' => 'List pasien RI per bulan berdasarkan tgl pulang', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'RI-BLN'],
        ['group' => 'Casemix', 'groupOrder' => 8, 'order' => 8, 'route' => 'ri.kontrol-klaim', 'aksi' => ['lihat'], 'title' => 'Kontrol Klaim Rawat Inap', 'desc' => 'Pantau klaim pasien yang sedang dirawat inap', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'KK-RI'],
    ],
];
