<?php

/*
 * Hak akses program "Master Tenaga & Jasa" — kelompok Master Data (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'nama' => 'Master Tenaga & Jasa',
    'menu' => [
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 2, 'route' => 'master.dokter', 'title' => 'Master Dokter', 'desc' => 'Kelola data dokter, spesialis, tarif poli/UGD/visit/konsul per kelas', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 11, 'route' => 'master.karyawan', 'title' => 'Master Karyawan', 'desc' => 'Kelola NIK karyawan untuk login user & coder iDRG (E-Klaim)', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 11, 'route' => 'master.karyawan-hr', 'title' => 'Master Karyawan HR', 'desc' => 'Kelola karyawan hrmst_employees: unit kerja, jabatan, status aktif & penanda petugas Kamar Operasi (crew OK)', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 14, 'route' => 'master.jasa-medis', 'title' => 'Master Jasa Medis', 'desc' => 'Kelola tarif jasa medis (Umum & BPJS) + paket bundling obat / lain-lain', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 15, 'route' => 'master.jasa-dokter', 'title' => 'Master Jasa Dokter', 'desc' => 'Kelola tarif jasa dokter (Umum & BPJS) + paket bundling obat / lain-lain', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 12, 'route' => 'master.setup-jadwal-bpjs', 'title' => 'Pemetaan Jadwal Dokter', 'desc' => 'Ambil & terapkan jadwal praktek dokter dari BPJS ke data RS', 'roles' => [...AppMenu::MASTER_ROLES, 'mr'], 'badge' => 'BPJS'],
    ],
];
