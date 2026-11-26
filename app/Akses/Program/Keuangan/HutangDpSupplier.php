<?php

/*
 * Hak akses program "Hutang & DP Supplier" — kelompok Keuangan (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'nama' => 'Hutang & DP Supplier',
    'menu' => [
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 5, 'route' => 'keuangan.pembayaran-hutang-pbf', 'aksi' => ['lihat', 'tambah', 'hapus'], 'rolesAksi' => ['hapus' => AppMenu::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Pembayaran Hutang PBF', 'desc' => 'Pelunasan / angsuran hutang ke supplier obat (PBF)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'HTG'],
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 6, 'route' => 'keuangan.pembayaran-hutang-non-medis', 'aksi' => ['lihat', 'tambah', 'hapus'], 'rolesAksi' => ['hapus' => AppMenu::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Pembayaran Hutang Non-Medis', 'desc' => 'Pelunasan / angsuran hutang ke supplier non-medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'HTG-N'],
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 7, 'route' => 'keuangan.topup-supplier-pbf', 'aksi' => ['lihat', 'tambah', 'hapus'], 'rolesAksi' => ['hapus' => AppMenu::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Setor DP Supplier PBF', 'desc' => 'Setor uang muka / DP ke supplier obat (PBF)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'DP'],
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 8, 'route' => 'keuangan.topup-supplier-non-medis', 'aksi' => ['lihat', 'tambah', 'hapus'], 'rolesAksi' => ['hapus' => AppMenu::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Setor DP Supplier Non-Medis', 'desc' => 'Setor uang muka / DP ke supplier non-medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'DP-N'],
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 15, 'route' => 'keuangan.kartu-dp-supplier', 'aksi' => ['lihat', 'ubah'], 'title' => 'Kartu DP Supplier', 'desc' => 'Saldo awal, setor, pemakaian, opname & saldo berjalan uang muka per supplier (PBF / Non-Medis)', 'roles' => ['admin'], 'badge' => 'K-DP'],
    ],
];
