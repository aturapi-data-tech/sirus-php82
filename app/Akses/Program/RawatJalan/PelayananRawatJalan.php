<?php

/*
 * Hak akses program "Pelayanan Rawat Jalan" — kelompok Rawat Jalan (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Pelayanan Rawat Jalan',
    'menu' => [
        ['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 2, 'route' => 'rj.pelayanan', 'aksi' => ['lihat'], 'khusus' => ['bukaRekamMedis' => ['Buka Rekam Medis', ['admin', 'casemix', 'dokter', 'mr', 'perawat']], 'bukaPengkajianMedis' => ['Pengkajian Medis', ['admin', 'dokter', 'mr', 'perawat']], 'bukaPrmrj' => ['PRMRJ', ['admin', 'dokter', 'mr', 'perawat']], 'bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'mr', 'perawat']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'perawat', 'tu']], 'bukaTransferUgd' => ['Transfer ke UGD', ['admin', 'manager umum', 'perawat', 'supervisor tu', 'tu']], 'taskIdPoli' => ['Catat TaskID 4/5 (masuk/keluar poli)', ['admin', 'perawat']]], 'title' => 'Pelayanan Rawat Jalan', 'desc' => 'EMR poli — anamnesis, pemeriksaan, diagnosa & resep oleh dokter/perawat', 'roles' => ['admin', 'manager medis', 'dokter', 'perawat'], 'badge' => 'PEL-RJ'],
    ],
    'aksiKhusus' => [
        'rj.detailKunjungan' => ['label' => 'Lihat detail kunjungan', 'roles' => AksiRole::RJ_DETAIL_KUNJUNGAN],
    ],
    'pintu' => [
        'emr-rj.rekam-medis.open' => ['rj.pelayanan.bukaRekamMedis'],
        'pelayanan-rj.pengkajian-medis.open' => ['rj.pelayanan.bukaPengkajianMedis'],
        'pelayanan-rj.prmrj.open' => ['rj.pelayanan.bukaPrmrj'],
        'open-transfer-rj-ke-ugd' => ['rj.pelayanan.bukaTransferUgd', 'rj.antrian-kasir.bukaTransferUgd'],
    ],
];
