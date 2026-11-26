<?php

/*
 * Hak akses program "Daftar Bulanan UGD" — kelompok UGD (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Daftar Bulanan UGD',
    'menu' => [
        ['group' => 'Casemix', 'groupOrder' => 8, 'order' => 3, 'route' => 'ugd.daftar-bulanan', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'tu']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'tu']]], 'title' => 'Daftar Pasien Bulanan UGD', 'desc' => 'List pasien UGD per bulan (mm/yyyy)', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'UGD-BLN'],
    ],
];
