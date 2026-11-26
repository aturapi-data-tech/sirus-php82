<?php

/*
 * Hak akses program "Kamar & Tempat Tidur" — kelompok Rawat Inap (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Kamar & Tempat Tidur',
    'menu' => [
        ['group' => 'Rawat Inap', 'groupOrder' => 7, 'order' => 2, 'route' => 'ri.update-tt', 'aksi' => ['lihat'], 'khusus' => ['kirim' => ['Kirim ke Aplicares BPJS & SIRS', ['admin', 'mr', 'perawat', 'dokter']]], 'title' => 'Sinkronisasi Tempat Tidur', 'desc' => 'Sync ketersediaan TT rawat inap → Aplicares BPJS & SIRS Kemenkes', 'roles' => ['admin', 'mr', 'perawat', 'dokter'], 'badge' => 'TT'],
    ],
    'aksiKhusus' => [
        'ri.pindahKamar' => ['label' => 'Pindah kamar', 'roles' => AksiRole::RI_PINDAH_KAMAR],
    ],
    'pintu' => [
        'emr-ri.pindah-kamar.open' => ['ri.pindahKamar'],
    ],
];
