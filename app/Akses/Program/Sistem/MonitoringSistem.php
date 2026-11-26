<?php

/*
 * Hak akses program "Monitoring Sistem" — kelompok Sistem (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Monitoring Sistem',
    'menu' => [
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 1, 'route' => 'database-monitor.monitoring-dashboard', 'title' => 'Oracle Session Monitor', 'desc' => 'Locks, long-running SQL & kill session', 'roles' => ['admin'], 'badge' => 'DB'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 2, 'route' => 'database-monitor.monitoring-mount-control', 'title' => 'Mounting Control', 'desc' => 'Mount/unmount share folder jaringan (CIFS/SMB)', 'roles' => ['admin', 'perawat'], 'badge' => 'MNT'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 5, 'route' => 'database-monitor.user-online', 'title' => 'User Online', 'desc' => 'Daftar user yang sedang aktif login (last_seen_at < threshold menit)', 'roles' => ['admin'], 'badge' => 'ONL'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 6, 'route' => 'database-monitor.log-bpjs', 'title' => 'Log BPJS / E-Klaim API', 'desc' => 'Riwayat pemanggilan V-Claim, Antrean, Aplicares, I-Care, SIRS, iDRG/INACBG', 'roles' => ['admin'], 'badge' => 'LOG'],
        ['group' => 'Sistem', 'groupOrder' => 15, 'order' => 18, 'route' => 'database-monitor.monitor-server', 'title' => 'Monitor Server', 'desc' => 'Worker PHP-FPM, timeout Apache (504), slowlog & error Laravel', 'roles' => ['admin'], 'badge' => 'FPM'],
    ],
];
