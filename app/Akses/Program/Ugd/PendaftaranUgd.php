<?php

/*
 * Hak akses program "Pendaftaran UGD" — kelompok UGD (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'nama' => 'Pendaftaran UGD',
    'menu' => [
        ['group' => 'Unit Gawat Darurat', 'groupOrder' => 6, 'order' => 1, 'route' => 'ugd.daftar', 'khusus' => ['bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'mr', 'perawat', 'supervisor tu', 'tu']], 'bukaDiagnosa' => ['Buka Diagnosa', ['admin', 'casemix', 'dokter', 'mr']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'mr', 'tu']]], 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => AppMenu::ROLES_PENDAFTARAN, 'ubah' => AppMenu::ROLES_PENDAFTARAN], 'title' => 'Daftar UGD', 'desc' => 'Pendaftaran & manajemen pasien Unit Gawat Darurat harian', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'mr'], 'badge' => 'UGD'],
    ],
    'pintu' => [
        'daftar-ugd.diagnosa.open' => ['ugd.daftar.bukaDiagnosa'],
    ],
];
