<?php

/*
 * Hak akses program "Administrasi & Kasir UGD" — kelompok UGD (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Administrasi & Kasir UGD',
    'menu' => [
        ['group' => 'Kasir', 'groupOrder' => 9, 'order' => 3, 'route' => 'ugd.antrian-kasir', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'manager umum', 'supervisor tu', 'tu']], 'bukaTransferRi' => ['Transfer ke RI', ['admin', 'manager umum', 'perawat', 'supervisor tu', 'tu']]], 'title' => 'Antrian Kasir UGD', 'desc' => 'Antrian kasir khusus pasien UGD', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-UGD'],
    ],
    'aksiKhusus' => [
        'ugd.ubahDataKunjungan' => ['label' => 'Ubah data kunjungan (kasir)', 'roles' => AksiRole::UGD_UBAH_DATA_KUNJUNGAN],
        'kasir.ugd.posting' => ['label' => 'Kasir: posting pembayaran', 'roles' => AksiRole::KASIR_UGD_POSTING],
        'kasir.ugd.batal' => ['label' => 'Kasir: batal transaksi', 'roles' => AksiRole::KASIR_UGD_BATAL],
        'kasir.ugd.batalTransferRi' => ['label' => 'Kasir: batal transfer ke RI', 'roles' => AksiRole::KASIR_UGD_BATAL_TRANSFER_RI],
        'kasir.ugd.batalKunjungan' => ['label' => 'Kasir: batal kunjungan', 'roles' => AksiRole::KASIR_UGD_BATAL_KUNJUNGAN],
    ],
    'pintu' => [
        'emr-ugd.administrasi.open' => ['ugd.pelayanan.bukaAdministrasi', 'ugd.antrian-kasir.bukaAdministrasi', 'ugd.antrian-apotek.bukaAdministrasi', 'ugd.daftar-bulanan.bukaAdministrasi', 'ugd.emr.bukaAdministrasi', 'manajemen.rs.tu.piutang-pasien.lihat'],
        'open-ubah-data-kunjungan-ugd' => ['ugd.ubahDataKunjungan'],
    ],
];
