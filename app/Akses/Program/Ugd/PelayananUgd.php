<?php

/*
 * Hak akses program "Pelayanan UGD" — kelompok UGD (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Pelayanan UGD',
    'menu' => [
        ['group' => 'Unit Gawat Darurat', 'groupOrder' => 6, 'order' => 2, 'route' => 'ugd.pelayanan', 'aksi' => ['lihat'], 'khusus' => ['bukaCekPesertaBpjs' => ['Cek Peserta BPJS', ['admin', 'casemix', 'dokter', 'mr', 'perawat']], 'bukaRekamMedis' => ['Buka Rekam Medis', ['admin', 'apoteker', 'casemix', 'dokter', 'laboratorium', 'mr', 'perawat']], 'bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'mr', 'perawat']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'perawat', 'tu']], 'bukaTransferRi' => ['Transfer ke RI', ['admin', 'manager umum', 'perawat', 'supervisor tu', 'tu']]], 'title' => 'Pelayanan UGD', 'desc' => 'EMR UGD — triase, asesmen, tindakan & resep oleh dokter/perawat UGD', 'roles' => ['admin', 'manager medis', 'dokter', 'perawat', 'apoteker', 'laboratorium'], 'badge' => 'PEL-UGD'],
    ],
    'pintu' => [
        'emr-ugd.rekam-medis.open' => ['ugd.pelayanan.bukaRekamMedis'],
        'cek-peserta-bpjs.open' => ['ugd.pelayanan.bukaCekPesertaBpjs'],
        'open-transfer-ugd-ke-ri' => ['ugd.pelayanan.bukaTransferRi', 'ugd.antrian-kasir.bukaTransferRi'],
    ],
];
