<?php

/*
 * Hak akses kelompok MASTER DATA — SATU BERKAS PER PROGRAM di folder ini (lihat App\Support\ProgramAkses).
 *
 *  'menu'       : entri menu sidebar + Lihat/Tambah/Ubah/Hapus + tombol khusus halaman ('khusus').
 *  'aksiKhusus' : aksi di luar halaman (tombol di modal/EMR, posting/batal) — nama Gate => label, roles awal.
 *  'pintu'      : event pembuka modal => hak siapa saja yang sah membukanya.
 *
 * Blok program = pengelompokan di ROLE CONTROL saja. Letak di SIDEBAR diatur 'group' /
 * 'groupOrder' / 'order' tiap entri (mis. Master Radiologi tetap di sidebar "Master Pelayanan"
 * walau programnya Master Penunjang).
 *
 * roles = CENTANG AWAL saja; sesudah permission dibuat (otomatis saat deploy) yang berlaku
 * centang Role Control. Pedoman: docs/standar-hak-akses.md.
 */

return [
    'judul' => 'Master Data',
    // Urutan = urutan tampil di Role Control. Nama berkas program di folder ini (tanpa .php).
    'program' => [
        'MasterPasienIdentitasRs',
        'MasterTenagaJasa',
        'MasterRuangLayanan',
        'MasterKlinis',
        'MasterPenunjang',
        'MasterKodingSatusehat',
        'MasterApotek',
        'MasterAkuntansi',
    ],
];
