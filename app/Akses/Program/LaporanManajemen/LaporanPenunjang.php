<?php

/*
 * Hak akses program "Laporan Penunjang" — kelompok Laporan Manajemen (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Laporan Penunjang',
    'menu' => [
        ['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 201, 'route' => 'manajemen.rs.penunjang.lab.laporan-permintaan-lab', 'aksi' => ['lihat'], 'title' => 'Permintaan Lab', 'desc' => 'Gabungan RJ/UGD/RI — total, BPJS/UMUM, revenue, top dokter pengirim', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor penunjang'], 'badge' => 'Lab'],
        ['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 202, 'route' => 'manajemen.rs.penunjang.lab.laporan-pemeriksaan-lab', 'aksi' => ['lihat'], 'title' => 'Pemeriksaan Lab', 'desc' => 'Item-level — volume servis, top 20 item lab terbanyak (Hb, GDS, dst.)', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor penunjang'], 'badge' => 'Lab'],
        ['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 203, 'route' => 'manajemen.rs.penunjang.lab.laporan-pemeriksaan-dalam-luar', 'aksi' => ['lihat'], 'title' => 'Lab Internal & Rujukan', 'desc' => 'Detail per pasien + rekap — lab internal vs rujukan', 'roles' => ['admin', 'manager umum', 'supervisor penunjang'], 'badge' => 'Lab'],
        ['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 204, 'route' => 'manajemen.rs.penunjang.lab.laporan-nilai-kritis', 'aksi' => ['lihat'], 'title' => 'Nilai Kritis Laboratorium', 'desc' => 'Hasil melewati ambang (Tinggi/Rendah) — perlu perhatian dokter', 'roles' => ['admin', 'manager umum', 'supervisor penunjang'], 'badge' => 'Lab'],
        ['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 205, 'route' => 'manajemen.rs.penunjang.rad.laporan-permintaan-rad', 'aksi' => ['lihat'], 'title' => 'Permintaan Radiologi', 'desc' => 'Gabungan RJ/UGD/RI — total, BPJS/UMUM, revenue, top dokter pengirim', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor penunjang'], 'badge' => 'Rad'],
        ['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 206, 'route' => 'manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad', 'aksi' => ['lihat'], 'title' => 'Pemeriksaan Radiologi', 'desc' => 'Item-level — volume servis, top 20 item radiologi terbanyak (Foto Thorax, USG, dst.)', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor penunjang'], 'badge' => 'Rad'],
        ['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 207, 'route' => 'manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad-detail', 'aksi' => ['lihat'], 'title' => 'Radiologi Detail per Pasien', 'desc' => 'Detail per pasien + rekap per unit — RJ / UGD / RI', 'roles' => ['admin', 'manager umum', 'supervisor penunjang'], 'badge' => 'Rad'],
        ['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 208, 'route' => 'manajemen.mutasi-obat', 'aksi' => ['lihat'], 'title' => 'Mutasi Obat', 'desc' => 'Keluar masuk obat — bulanan & tahunan, per gudang & per unit', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor penunjang'], 'badge' => 'OBT'],
    ],
];
