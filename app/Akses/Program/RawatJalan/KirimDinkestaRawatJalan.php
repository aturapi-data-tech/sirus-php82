<?php

/*
 * Hak akses program "Kirim DINKESTA Rawat Jalan" — kelompok Rawat Jalan (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Kirim DINKESTA Rawat Jalan',
    'menu' => [
        ['group' => 'Dinas Kesehatan Tulungagung', 'groupOrder' => 8, 'order' => 1, 'route' => 'dinkesta.kirim-rj', 'aksi' => ['lihat'], 'khusus' => ['kirim' => ['Kirim ke Portal DINKESTA', ['admin', 'casemix', 'tu']]], 'title' => 'Kirim DINKESTA Rawat Jalan', 'desc' => 'Kirim bulk diagnosis ICD-10 kunjungan RJ per bulan ke Portal Satu Data Kesehatan Tulungagung', 'roles' => ['admin', 'casemix', 'tu'], 'badge' => 'DINKES'],
    ],
];
