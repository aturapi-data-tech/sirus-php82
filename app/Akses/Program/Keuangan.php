<?php

/*
 * Hak akses kelompok KEUANGAN — SATU BLOK PER PROGRAM (lihat App\Support\ProgramAkses).
 *
 *  'menu'       : entri menu sidebar + Lihat/Tambah/Ubah/Hapus + tombol khusus halaman ('khusus').
 *  'aksiKhusus' : aksi di luar halaman (tombol di modal/EMR, posting/batal) — nama Gate => label, roles awal.
 *  'pintu'      : event pembuka modal => hak siapa saja yang sah membukanya.
 *
 * roles = CENTANG AWAL saja; sesudah permission dibuat (otomatis saat deploy) yang berlaku
 * centang Role Control. Pedoman: docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;
use App\Support\AksiRole;

return [
    'judul' => 'Keuangan',
    'program' => [
        'Kas TU' => [
            'menu' => [
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 1, 'route' => 'keuangan.penerimaan-kas-tu', 'aksi' => ['lihat', 'tambah'], 'title' => 'Penerimaan Kas TU', 'desc' => 'Catat penerimaan kas di luar transaksi pelayanan RS', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'CI'],
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 2, 'route' => 'keuangan.pengeluaran-kas-tu', 'aksi' => ['lihat', 'tambah'], 'title' => 'Pengeluaran Kas TU', 'desc' => 'Catat pengeluaran kas di luar transaksi pelayanan RS', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'CO'],
            ],
            'aksiKhusus' => [
                'kasTu.koreksi' => ['label' => 'Kas TU: koreksi transaksi', 'roles' => AksiRole::KAS_TU_KOREKSI],
                'kasTu.hapus' => ['label' => 'Kas TU: hapus transaksi', 'roles' => AksiRole::KAS_TU_HAPUS],
            ],
        ],

        'Piutang' => [
            'menu' => [
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 3, 'route' => 'keuangan.pembayaran-piutang-bpjs', 'aksi' => ['lihat'], 'title' => 'Pelunasan Klaim BPJS & Kronis', 'desc' => 'Lunasi tagihan belum lunas per bulan — tab BPJS RJ/UGD/RI & Obat Kronis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'BPJS'],
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 4, 'route' => 'keuangan.pembayaran-piutang-pasien', 'aksi' => ['lihat'], 'title' => 'Pembayaran Piutang Per Pasien', 'desc' => 'Pelunasan / angsuran nota pasien (umum) — alokasi FIFO', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'PIUT'],
            ],
            'aksiKhusus' => [
                'piutang.bayar' => ['label' => 'Piutang: catat pembayaran / pelunasan', 'roles' => AksiRole::PIUTANG_BAYAR],
                'piutang.hapusBayar' => ['label' => 'Piutang: hapus pembayaran salah', 'roles' => AksiRole::PIUTANG_HAPUS_BAYAR],
            ],
        ],

        'Hutang & DP Supplier' => [
            'menu' => [
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 5, 'route' => 'keuangan.pembayaran-hutang-pbf', 'aksi' => ['lihat', 'tambah', 'hapus'], 'rolesAksi' => ['hapus' => AppMenu::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Pembayaran Hutang PBF', 'desc' => 'Pelunasan / angsuran hutang ke supplier obat (PBF)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'HTG'],
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 6, 'route' => 'keuangan.pembayaran-hutang-non-medis', 'aksi' => ['lihat', 'tambah', 'hapus'], 'rolesAksi' => ['hapus' => AppMenu::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Pembayaran Hutang Non-Medis', 'desc' => 'Pelunasan / angsuran hutang ke supplier non-medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'HTG-N'],
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 7, 'route' => 'keuangan.topup-supplier-pbf', 'aksi' => ['lihat', 'tambah', 'hapus'], 'rolesAksi' => ['hapus' => AppMenu::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Setor DP Supplier PBF', 'desc' => 'Setor uang muka / DP ke supplier obat (PBF)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'DP'],
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 8, 'route' => 'keuangan.topup-supplier-non-medis', 'aksi' => ['lihat', 'tambah', 'hapus'], 'rolesAksi' => ['hapus' => AppMenu::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Setor DP Supplier Non-Medis', 'desc' => 'Setor uang muka / DP ke supplier non-medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'DP-N'],
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 15, 'route' => 'keuangan.kartu-dp-supplier', 'aksi' => ['lihat', 'ubah'], 'title' => 'Kartu DP Supplier', 'desc' => 'Saldo awal, setor, pemakaian, opname & saldo berjalan uang muka per supplier (PBF / Non-Medis)', 'roles' => ['admin'], 'badge' => 'K-DP'],
            ],
        ],

        'Buku Besar & Laporan Keuangan' => [
            'menu' => [
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 14, 'route' => 'keuangan.tutup-buku', 'aksi' => ['lihat', 'ubah'], 'title' => 'Tutup Buku Tahunan', 'desc' => 'Saldo awal tahun berikutnya: akun (Kas & Neraca), stok, hutang & DP supplier', 'roles' => ['admin'], 'badge' => 'TB'],
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 9, 'route' => 'keuangan.saldo-kas', 'aksi' => ['lihat', 'ubah'], 'rolesAksi' => ['ubah' => AppMenu::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Saldo Kas', 'desc' => 'Posisi saldo kas/bank per tanggal — riwayat transaksi & edit saldo awal tahun (admin)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'SK'],
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 10, 'route' => 'keuangan.buku-besar', 'aksi' => ['lihat'], 'title' => 'Buku Besar', 'desc' => 'Mutasi & saldo per akun dalam periode tertentu', 'roles' => ['admin', 'manager umum'], 'badge' => 'BB'],
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 11, 'route' => 'keuangan.laba-rugi', 'aksi' => ['lihat'], 'title' => 'Laporan Laba Rugi', 'desc' => 'Pendapatan vs beban — laba/rugi periode berjalan', 'roles' => ['admin', 'manager umum'], 'badge' => 'LR'],
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 12, 'route' => 'keuangan.neraca', 'aksi' => ['lihat'], 'title' => 'Laporan Neraca', 'desc' => 'Posisi aktiva, kewajiban & modal per tanggal', 'roles' => ['admin', 'manager umum'], 'badge' => 'NRC'],
                ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 13, 'route' => 'keuangan.analisa-perputaran-obat', 'aksi' => ['lihat'], 'title' => 'Analisa Perputaran Obat', 'desc' => 'Fast / slow / dead moving per lokasi — sorot modal stok yang mengendap', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'STOK'],
            ],
        ],
    ],
];
