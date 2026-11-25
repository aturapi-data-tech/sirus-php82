<?php

/*
 * Hak akses program "Laporan SIRS Online" — kelompok Laporan Manajemen (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Laporan SIRS Online',
    'menu' => [
        ['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 301, 'route' => 'manajemen.sirs.ri.laporan-rl-3-2-rawat-inap', 'aksi' => ['lihat'], 'title' => 'RL 3.2 Rawat Inap', 'desc' => 'Rekap RI per jenis pelayanan, per bulan', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL'],
        ['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 302, 'route' => 'manajemen.sirs.ugd.laporan-rl-3-3-rawat-darurat', 'aksi' => ['lihat'], 'title' => 'RL 3.3 Rawat Darurat', 'desc' => 'Rekap UGD/IGD per jenis pelayanan, per bulan', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL'],
        ['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 303, 'route' => 'manajemen.sirs.rj.laporan-rl-3-4-pengunjung', 'aksi' => ['lihat'], 'title' => 'RL 3.4 Pengunjung', 'desc' => 'Pasien unik per bulan: Baru / Lama (lintas RJ+UGD+RI)', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL'],
        ['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 304, 'route' => 'manajemen.sirs.rj.laporan-rl-3-5-kunjungan', 'aksi' => ['lihat'], 'title' => 'RL 3.5 Kunjungan', 'desc' => 'Rekap visit RJ + UGD per jenis kegiatan, dalam/luar kota', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL'],
        ['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 305, 'route' => 'manajemen.sirs.penunjang.laporan-rl-3-8-laboratorium', 'aksi' => ['lihat'], 'title' => 'RL 3.8 Laboratorium', 'desc' => 'Rekap pemeriksaan lab per jenis (gender, rata-rata/hari)', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor penunjang'], 'badge' => 'RL'],
        ['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 306, 'route' => 'manajemen.sirs.penunjang.laporan-rl-3-9-radiologi', 'aksi' => ['lihat'], 'title' => 'RL 3.9 Radiologi', 'desc' => 'Rekap rad per jenis (Foto/CT/USG/MRI/Radioterapi)', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor penunjang'], 'badge' => 'RL'],
        ['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 307, 'route' => 'manajemen.sirs.rj.laporan-rl-3-15-kesehatan-jiwa', 'aksi' => ['lihat'], 'title' => 'RL 3.15 Kesehatan Jiwa', 'desc' => 'Tahunan — rekap kunjungan POLI PSIKIATRI per jenis kegiatan', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL'],
        ['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 308, 'route' => 'manajemen.sirs.ri.laporan-rl-3-19-cara-bayar', 'aksi' => ['lihat'], 'title' => 'RL 3.19 Cara Bayar', 'desc' => 'Tahunan — rekap pasien per cara bayar (BPJS/UMUM/Asuransi/dll)', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor tu'], 'badge' => 'RL'],
        ['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 309, 'route' => 'manajemen.sirs.ri.laporan-rl-4-1-morbiditas', 'aksi' => ['lihat'], 'title' => 'RL 4.1 Morbiditas RI', 'desc' => 'Tahunan — morbiditas per ICD-10 × kelompok umur × gender × hidup/mati', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL'],
        ['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 310, 'route' => 'manajemen.sirs.ri.laporan-rl-4-2-10besar', 'aksi' => ['lihat'], 'title' => 'RL 4.2 Top 10 Penyakit RI', 'desc' => 'Tahunan — 10 ICD-10 terbanyak di RI (gender + jumlah meninggal)', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL'],
        ['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 311, 'route' => 'manajemen.sirs.ri.laporan-rl-4-3-10besar-mati', 'aksi' => ['lihat'], 'title' => 'RL 4.3 Top 10 Kematian RI', 'desc' => 'Tahunan — 10 ICD-10 penyebab kematian terbanyak di RI', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL'],
        ['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 312, 'route' => 'manajemen.sirs.rj.laporan-rl-5-1-morbiditas', 'aksi' => ['lihat'], 'title' => 'RL 5.1 Morbiditas RJ', 'desc' => 'Tahunan — morbiditas RJ per ICD-10 × umur × gender (kasus baru & kunjungan)', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL'],
        ['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 313, 'route' => 'manajemen.sirs.rj.laporan-rl-5-3-10besar-kunjungan', 'aksi' => ['lihat'], 'title' => 'RL 5.3 Top 10 Kunjungan RJ', 'desc' => 'Tahunan — 10 ICD-10 dengan kunjungan terbanyak di RJ', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL'],
    ],
];
