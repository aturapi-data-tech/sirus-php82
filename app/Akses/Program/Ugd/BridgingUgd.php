<?php

/*
 * Hak akses program "Bridging UGD" — kelompok UGD (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Bridging UGD',
    'aksiKhusus' => [
        'ugd.satusehat.kirim' => ['label' => 'Kirim SATUSEHAT', 'roles' => AksiRole::SATUSEHAT_KIRIM, 'menggantikan' => 'satusehat.kirim'],
    ],
    'pintu' => [
        'daftar-ugd.satu-sehat.open' => ['ugd.satusehat.kirim'],
    ],
];
