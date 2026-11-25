<?php

/*
 * Hak akses kelompok CASEMIX — SATU BLOK PER PROGRAM (lihat App\Support\ProgramAkses).
 *
 *  'menu'       : entri menu sidebar + Lihat/Tambah/Ubah/Hapus + tombol khusus halaman ('khusus').
 *  'aksiKhusus' : aksi di luar halaman (tombol di modal/EMR, posting/batal) — nama Gate => label, roles awal.
 *  'pintu'      : event pembuka modal => hak siapa saja yang sah membukanya.
 *
 * roles = CENTANG AWAL saja; sesudah permission dibuat (otomatis saat deploy) yang berlaku
 * centang Role Control. Pedoman: docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'judul' => 'Casemix',
    'program' => [
        'Casemix & Klaim' => [
            'menu' => [
                ['group' => 'Casemix', 'groupOrder' => 8, 'order' => 1, 'route' => 'casemix', 'aksi' => ['lihat'], 'title' => 'Casemix', 'desc' => 'Daftar pasien bulanan — tab RJ, UGD, RI dalam 1 halaman', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'CSX'],
                ['group' => 'Casemix', 'groupOrder' => 8, 'order' => 7, 'route' => 'manajemen.rs.rekam-medis.arsip-modul-dokumen', 'title' => 'Arsip Modul Dokumen', 'desc' => 'Kunjungan RJ/UGD/RI per bulan — buka Rekam Medis untuk lihat & cetak modul dokumen', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'Casemix'],
                ['group' => 'Casemix', 'groupOrder' => 8, 'order' => 5, 'route' => 'casemix', 'aksi' => ['lihat'], 'params' => ['tab' => 'rekap-rj'], 'title' => 'Rekap iDRG Rawat Jalan', 'desc' => 'Rekap klaim iDRG/INA-CBG pasien rawat jalan per SEP (get_claim_data)', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'IDRG-RJ'],
                ['group' => 'Casemix', 'groupOrder' => 8, 'order' => 6, 'route' => 'casemix', 'aksi' => ['lihat'], 'params' => ['tab' => 'rekap'], 'title' => 'Rekap iDRG Rawat Inap', 'desc' => 'Rekap klaim iDRG/INA-CBG pasien rawat inap per SEP (get_claim_data)', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'IDRG-RI'],
            ],
            'aksiKhusus' => [
                'idrg.kirim' => ['label' => 'Kirim iDRG / INA-CBG', 'roles' => AksiRole::IDRG_KIRIM],
            ],
            'pintu' => [
                'berkas-bpjs.open' => ['ri.daftar.bukaBerkasBpjs', 'ri.daftar-bulanan.bukaBerkasBpjs', 'rj.daftar-bulanan.bukaBerkasBpjs', 'ugd.daftar.bukaBerkasBpjs', 'ugd.daftar-bulanan.bukaBerkasBpjs'],
            ],
        ],
    ],
];
