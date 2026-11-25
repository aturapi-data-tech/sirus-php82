<?php

/*
 * Hak akses program "Administrasi & Kasir Rawat Jalan" — kelompok Rawat Jalan (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Administrasi & Kasir Rawat Jalan',
    'menu' => [
        ['group' => 'Kasir', 'groupOrder' => 9, 'order' => 2, 'route' => 'rj.antrian-kasir', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'manager umum', 'supervisor tu', 'tu']], 'bukaTransferUgd' => ['Transfer ke UGD', ['admin', 'manager umum', 'perawat', 'supervisor tu', 'tu']]], 'title' => 'Antrian Kasir RJ', 'desc' => 'Antrian kasir khusus pasien rawat jalan', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-RJ'],
    ],
    'aksiKhusus' => [
        'rj.ubahDataKunjungan' => ['label' => 'Ubah data kunjungan (kasir)', 'roles' => AksiRole::RJ_UBAH_DATA_KUNJUNGAN],
        'rj.kembalikanBatal' => ['label' => 'Kembalikan kunjungan batal', 'roles' => AksiRole::RJ_KEMBALIKAN_BATAL],
        'kasir.rj.posting' => ['label' => 'Kasir: posting pembayaran', 'roles' => AksiRole::KASIR_RJ_POSTING],
        'kasir.rj.batal' => ['label' => 'Kasir: batal transaksi', 'roles' => AksiRole::KASIR_RJ_BATAL],
        'kasir.rj.batalTransferUgd' => ['label' => 'Kasir: batal transfer ke UGD', 'roles' => AksiRole::KASIR_RJ_BATAL_TRANSFER_UGD],
        'kasir.rj.batalKunjungan' => ['label' => 'Kasir: batal kunjungan', 'roles' => AksiRole::KASIR_RJ_BATAL_KUNJUNGAN],
    ],
    'pintu' => [
        'emr-rj.administrasi.open' => ['rj.pelayanan.bukaAdministrasi', 'rj.antrian-kasir.bukaAdministrasi', 'rj.antrian-apotek.bukaAdministrasi', 'rj.daftar-bulanan.bukaAdministrasi', 'rj.emr.bukaAdministrasi', 'manajemen.rs.tu.piutang-pasien.lihat'],
        'open-ubah-data-kunjungan-rj' => ['rj.ubahDataKunjungan'],
    ],
];
