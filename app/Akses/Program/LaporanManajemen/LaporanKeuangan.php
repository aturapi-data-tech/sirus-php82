<?php

/*
 * Hak akses program "Laporan Keuangan" — kelompok Laporan Manajemen (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Laporan Keuangan',
    'menu' => [
        ['group' => 'Laporan Keuangan', 'groupOrder' => 0, 'order' => 401, 'route' => 'manajemen.rs.tu.pendapatan-rs', 'aksi' => ['lihat'], 'title' => 'Pendapatan RS Keseluruhan', 'desc' => 'Total revenue RJ + UGD + RI — tahunan / multi-tahun, breakdown per modul', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'KEU'],
        ['group' => 'Laporan Keuangan', 'groupOrder' => 0, 'order' => 402, 'route' => 'manajemen.rs.tu.pendapatan-jasa-dokter', 'aksi' => ['lihat'], 'title' => 'Pendapatan Jasa Dokter', 'desc' => 'Rekap jasa medis dokter per bulan — disetujui / belum disetujui BPJS', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'KEU'],
        ['group' => 'Laporan Keuangan', 'groupOrder' => 0, 'order' => 403, 'route' => 'manajemen.rs.tu.monitoring-jasa-dokter', 'aksi' => ['lihat'], 'title' => 'Monitoring Jasa Dokter', 'desc' => 'Pergerakan transaksi jasa per dokter & jalur vs bulan lalu — data yang dipasok ke SIAP', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'KEU'],
        ['group' => 'Laporan Keuangan', 'groupOrder' => 0, 'order' => 404, 'route' => 'manajemen.rs.tu.pendapatan-jasa-medis', 'aksi' => ['lihat'], 'title' => 'Pendapatan Jasa Medis', 'desc' => 'Revenue paket jasa medis per bulan — RJ / UGD / RI', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'KEU'],
        ['group' => 'Laporan Keuangan', 'groupOrder' => 0, 'order' => 405, 'route' => 'manajemen.rs.tu.pendapatan-jasa-karyawan', 'aksi' => ['lihat'], 'title' => 'Pendapatan Jasa Karyawan', 'desc' => 'Revenue paket jasa karyawan per bulan — RJ / UGD', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'KEU'],
        ['group' => 'Laporan Keuangan', 'groupOrder' => 0, 'order' => 406, 'route' => 'manajemen.rs.tu.laporan-operasi', 'aksi' => ['lihat'], 'title' => 'Laporan Bulanan Operasi', 'desc' => 'Tindakan kamar operasi per bulan — dokter operator, anestesi & tarif sampai ON LOOP', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'OK'],
        ['group' => 'Laporan Keuangan', 'groupOrder' => 0, 'order' => 407, 'route' => 'manajemen.rs.tu.piutang-pasien', 'aksi' => ['lihat'], 'title' => 'Piutang Pasien (Cicilan / Bon)', 'desc' => 'Transaksi belum lunas (Hutang/bon) — RJ, UGD & RI, sisa piutang per pasien', 'roles' => ['admin', 'manager medis', 'manager umum', 'tu'], 'badge' => 'KEU'],
    ],
];
