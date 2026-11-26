<?php

/*
 * Hak akses program "Antrian Kasir Gabungan" — kelompok Bersama (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Antrian Kasir Gabungan',
    'menu' => [
        ['group' => 'Kasir', 'groupOrder' => 9, 'order' => 1, 'route' => 'kasir', 'gabungan' => ['rj.antrian-kasir', 'ugd.antrian-kasir', 'kasir.antrian-ri', 'kasir.daftar-ri', 'keuangan.penerimaan-kas-tu', 'keuangan.pengeluaran-kas-tu', 'keuangan.pembayaran-piutang-pasien', 'manajemen.rs.tu.piutang-pasien', 'keuangan.pembayaran-hutang-pbf', 'keuangan.pembayaran-hutang-non-medis', 'keuangan.topup-supplier-pbf', 'keuangan.topup-supplier-non-medis'], 'title' => 'Antrian Kasir', 'desc' => 'Kasir — tab RJ, UGD, RI', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR'],
    ],
];
