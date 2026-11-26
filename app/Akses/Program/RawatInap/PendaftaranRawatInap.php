<?php

/*
 * Hak akses program "Pendaftaran Rawat Inap" — kelompok Rawat Inap (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'nama' => 'Pendaftaran Rawat Inap',
    'menu' => [
        ['group' => 'Rawat Inap', 'groupOrder' => 7, 'order' => 1, 'route' => 'ri.daftar', 'khusus' => ['bukaRekamMedis' => ['Buka Rekam Medis', ['admin', 'apoteker', 'casemix', 'dokter', 'gizi', 'laboratorium', 'perawat', 'radiologi']], 'bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'gizi', 'laboratorium', 'mr', 'perawat', 'radiologi', 'supervisor tu', 'tu']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'gizi', 'perawat', 'tu']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'mr', 'tu']]], 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => AppMenu::ROLES_PENDAFTARAN, 'ubah' => AppMenu::ROLES_PENDAFTARAN], 'title' => 'Daftar Rawat Inap', 'desc' => 'Pendaftaran, transfer kamar, & manajemen pasien rawat inap', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor tu', 'mr', 'perawat', 'dokter', 'casemix', 'tu', 'apoteker', 'gizi', 'laboratorium', 'radiologi'], 'badge' => 'RI'],
    ],
];
