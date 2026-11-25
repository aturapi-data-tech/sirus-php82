<?php

/*
 * Hak akses program "Booking & Jadwal Kontrol" — kelompok Rawat Jalan (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Booking & Jadwal Kontrol',
    'menu' => [
        ['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 3, 'route' => 'rj.booking', 'aksi' => ['lihat', 'tambah', 'ubah', 'hapus'], 'title' => 'Booking Rawat Jalan', 'desc' => 'Pasien booking poli via Mobile JKN — siap diaktivasi jadi pendaftaran', 'roles' => ['admin', 'mr', 'manager umum', 'supervisor tu'], 'badge' => 'BKG'],
        ['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 4, 'route' => 'kontrol.jadwal-kontrol', 'aksi' => ['lihat'], 'title' => 'Jadwal Kontrol Pasien', 'desc' => 'Lihat & geser tanggal rencana kontrol (SKDP) RJ/RI — update otomatis ke BPJS', 'roles' => ['admin', 'mr', 'tu', 'supervisor tu', 'manager umum'], 'badge' => 'SKDP'],
    ],
];
