<?php

/*
 * Hak akses program "Bridging Rawat Inap" — kelompok Rawat Inap (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Bridging Rawat Inap',
    'aksiKhusus' => [
        'ri.satusehat.kirim' => ['label' => 'Kirim SATUSEHAT', 'roles' => AksiRole::SATUSEHAT_KIRIM, 'menggantikan' => 'satusehat.kirim'],
        'ri.bpjs.hapusSuratKontrol' => ['label' => 'Hapus surat kontrol BPJS', 'roles' => AksiRole::BPJS_HAPUS_SURAT_KONTROL, 'menggantikan' => 'bpjs.hapusSuratKontrol'],
    ],
];
