<?php

/*
 * Hak akses program "Daftar Bulanan Rawat Jalan" — kelompok Rawat Jalan (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Daftar Bulanan Rawat Jalan',
    'menu' => [
        ['group' => 'Casemix', 'groupOrder' => 8, 'order' => 2, 'route' => 'rj.daftar-bulanan', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'tu']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'tu']]], 'title' => 'Daftar Pasien Bulanan RJ', 'desc' => 'List pasien rawat jalan per bulan (mm/yyyy)', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'RJ-BLN'],
    ],
];
