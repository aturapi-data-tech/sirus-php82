<?php

/*
 * Hak akses program "Piutang" — kelompok Keuangan (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Piutang',
    'menu' => [
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 3, 'route' => 'keuangan.pembayaran-piutang-bpjs', 'aksi' => ['lihat'], 'title' => 'Pelunasan Klaim BPJS & Kronis', 'desc' => 'Lunasi tagihan belum lunas per bulan — tab BPJS RJ/UGD/RI & Obat Kronis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'BPJS'],
        ['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 4, 'route' => 'keuangan.pembayaran-piutang-pasien', 'aksi' => ['lihat'], 'title' => 'Pembayaran Piutang Per Pasien', 'desc' => 'Pelunasan / angsuran nota pasien (umum) — alokasi FIFO', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'PIUT'],
    ],
    'aksiKhusus' => [
        'piutang.bayar' => ['label' => 'Piutang: catat pembayaran / pelunasan', 'roles' => AksiRole::PIUTANG_BAYAR],
        'piutang.hapusBayar' => ['label' => 'Piutang: hapus pembayaran salah', 'roles' => AksiRole::PIUTANG_HAPUS_BAYAR],
    ],
];
