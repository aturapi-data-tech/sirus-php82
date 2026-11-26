<?php

/*
 * Hak akses program "Resep Rawat Inap" — kelompok Rawat Inap (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Resep Rawat Inap',
    'menu' => [
        ['group' => 'Apotek', 'groupOrder' => 9, 'order' => 4, 'route' => 'ri-resep.antrian', 'aksi' => ['lihat'], 'khusus' => ['taskIdApotek' => ['Catat TaskID 6/7 (masuk/keluar apotek)', ['admin', 'apoteker']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'apoteker', 'tu']]], 'title' => 'Antrian Apotek RI', 'desc' => 'Telaah resep & pelayanan kefarmasian rawat inap', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT-RI'],
        ['group' => 'Apotek', 'groupOrder' => 9, 'order' => 5, 'route' => 'ri.pto', 'aksi' => ['lihat'], 'title' => 'Pemantauan Terapi Obat (PTO) Rawat Inap', 'desc' => 'Pantau seluruh terapi obat pasien rawat inap dari e-resep — khusus apoteker', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'PTO'],
        ['group' => 'Kasir', 'groupOrder' => 9, 'order' => 4, 'route' => 'kasir.antrian-ri', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'manager umum', 'supervisor tu', 'tu']]], 'title' => 'Resep Inap', 'desc' => 'Kasir resep rawat inap (per resep)', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-RI'],
    ],
    'aksiKhusus' => [
        'ri.ubahDataResep' => ['label' => 'Resep: ubah tanggal/shift', 'roles' => AksiRole::RI_UBAH_DATA_RESEP],
        'kasir.resepRi.posting' => ['label' => 'Kasir Resep: posting', 'roles' => AksiRole::KASIR_RESEP_RI_POSTING],
        'kasir.resepRi.batal' => ['label' => 'Kasir Resep: batal', 'roles' => AksiRole::KASIR_RESEP_RI_BATAL],
        'kasir.resepRi.ubahJasa' => ['label' => 'Kasir Resep: ubah jasa karyawan', 'roles' => AksiRole::KASIR_RESEP_RI_UBAH_JASA],
        'apotek.resepRi.posting' => ['label' => 'Apotek Resep: posting', 'roles' => AksiRole::APOTEK_RESEP_RI_POSTING],
        'apotek.resepRi.batal' => ['label' => 'Apotek Resep: batal', 'roles' => AksiRole::APOTEK_RESEP_RI_BATAL],
        'apotek.resepRi.ubahJasa' => ['label' => 'Apotek Resep: ubah jasa karyawan', 'roles' => AksiRole::APOTEK_RESEP_RI_UBAH_JASA],
    ],
    'pintu' => [
        'administrasi-kasir-ri.open' => ['kasir.antrian-ri.bukaAdministrasi'],
        'ri-resep-administrasi.open' => ['ri-resep.antrian.bukaAdministrasi'],
        'cetak-kwitansi-ri-obat.open' => ['pintu:administrasi-kasir-ri.open', 'pintu:ri-resep-administrasi.open'],
        'open-ubah-data-resep-ri' => ['ri.ubahDataResep'],
    ],
];
