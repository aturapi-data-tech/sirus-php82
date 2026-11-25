<?php

/*
 * Hak akses program "Administrasi & Kasir Pulang Rawat Inap" — kelompok Rawat Inap (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Administrasi & Kasir Pulang Rawat Inap',
    'menu' => [
        ['group' => 'Kasir', 'groupOrder' => 9, 'order' => 5, 'route' => 'kasir.daftar-ri', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'manager umum', 'supervisor tu', 'tu']]], 'title' => 'Pasien Rawat Inap', 'desc' => 'List pasien rawat inap untuk administrasi & pembayaran', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-DAFTAR'],
    ],
    'aksiKhusus' => [
        'ri.ubahDataKunjungan' => ['label' => 'Ubah data kunjungan (klaim)', 'roles' => AksiRole::RI_UBAH_DATA_KUNJUNGAN],
        'ri.resetAdministrasi' => ['label' => 'Reset tab administrasi', 'roles' => AksiRole::RI_RESET_ADMINISTRASI],
        'kasir.ri.posting' => ['label' => 'Kasir: posting pembayaran pulang', 'roles' => AksiRole::KASIR_RI_POSTING],
        'kasir.ri.batal' => ['label' => 'Kasir: batal transaksi pulang', 'roles' => AksiRole::KASIR_RI_BATAL],
        'kasir.ri.batalInap' => ['label' => 'Kasir: batal inap', 'roles' => AksiRole::KASIR_RI_BATAL_INAP],
        'ri.angsuranAwal.terima' => ['label' => 'Angsuran Awal (titipan): terima', 'roles' => AksiRole::RI_ANGSURAN_AWAL_TERIMA],
        'ri.angsuranAwal.hapus' => ['label' => 'Angsuran Awal (titipan): hapus', 'roles' => AksiRole::RI_ANGSURAN_AWAL_HAPUS],
    ],
    'pintu' => [
        'emr-ri.administrasi.open' => ['ri.daftar.bukaAdministrasi', 'ri.daftar-bulanan.bukaAdministrasi', 'ri.gizi.bukaAdministrasi', 'ri.gizi.bukaAdministrasiEntri', 'kasir.daftar-ri.bukaAdministrasi', 'ri.emr.bukaAdministrasi', 'manajemen.rs.tu.piutang-pasien.lihat'],
        'open-ubah-data-kunjungan-ri' => ['ri.ubahDataKunjungan'],
    ],
];
