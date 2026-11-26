<?php

/*
 * Hak akses program "Ruang Server" — kelompok Sistem (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Ruang Server',
    'menu' => [
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 7, 'route' => 'sistem.pemantauan-suhu-ruang-server', 'title' => 'Pemantauan Suhu Ruang Server', 'desc' => 'Formulir MRMIK 2.2 — suhu & status AC ruang server per bulan', 'roles' => ['admin', 'manager umum'], 'badge' => 'SRV'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 8, 'route' => 'sistem.pemantauan-akses-ruang-server', 'title' => 'Pemantauan Akses Ruang Server', 'desc' => 'Formulir MRMIK 2.2 — siapa keluar-masuk ruang server, kapan & untuk apa', 'roles' => ['admin', 'manager umum'], 'badge' => 'AKS'],
    ],
    'aksiKhusus' => [
        'sistem.pemantauanRuangServer.hapus' => ['label' => 'Hapus lembar pemantauan ruang server', 'roles' => AksiRole::SISTEM_PEMANTAUAN_RUANG_SERVER_HAPUS],
        'sistem.pemantauanRuangServer.bukaKunci' => ['label' => 'Buka kunci pemantauan ruang server', 'roles' => AksiRole::SISTEM_PEMANTAUAN_RUANG_SERVER_BUKA_KUNCI],
    ],
];
