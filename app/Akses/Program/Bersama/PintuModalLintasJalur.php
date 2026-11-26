<?php

/*
 * Hak akses program "Pintu Modal Lintas Jalur" — kelompok Bersama (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Pintu Modal Lintas Jalur',
    'pintu' => [
        'rekam-medis-nasional.open' => ['rj.emr.bukaRekamMedisNasional', 'ugd.emr.bukaRekamMedisNasional', 'ri.emr.bukaRekamMedisNasional'],
        'daftar-surat-kontrol-bpjs.open' => ['rj.bpjs.hapusSuratKontrol', 'ri.bpjs.hapusSuratKontrol'],
    ],
];
