<?php

/*
 * Hak akses program "Pendaftaran Rawat Jalan" — kelompok Rawat Jalan (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'nama' => 'Pendaftaran Rawat Jalan',
    'menu' => [
        ['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 1, 'route' => 'rj.daftar', 'khusus' => ['bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'mr', 'perawat', 'supervisor tu', 'tu']], 'bukaDiagnosa' => ['Buka Diagnosa', ['admin', 'casemix', 'dokter', 'mr']], 'bukaRiwayatKontrol' => ['Riwayat Kontrol', ['admin', 'casemix', 'mr', 'tu']], 'bukaBerkasBpjs' => ['Berkas BPJS (generate SEP/RM/SKDP)', ['admin', 'casemix', 'mr', 'tu']], 'batalTaskId99' => ['Batal antrean BPJS (TaskID 99)', ['admin', 'manager medis', 'manager umum']]], 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => AppMenu::ROLES_PENDAFTARAN, 'ubah' => AppMenu::ROLES_PENDAFTARAN], 'title' => 'Daftar Rawat Jalan', 'desc' => 'Pendaftaran & manajemen pasien poli rawat jalan harian', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'mr'], 'badge' => 'RJ'],
    ],
    'pintu' => [
        'daftar-rj.diagnosa.open' => ['rj.daftar.bukaDiagnosa'],
        'riwayat-kontrol.open' => ['rj.daftar.bukaRiwayatKontrol', 'kontrol.jadwal-kontrol.lihat'],
    ],
];
