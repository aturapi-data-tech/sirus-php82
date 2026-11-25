<?php

/*
 * Hak akses program "Modul Dokumen Rawat Jalan" — kelompok Rawat Jalan (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Modul Dokumen Rawat Jalan',
    // Hak modul dokumen JALUR INI (dulu satu hak untuk RJ/UGD/RI). Sel Aksi: <x-modul-dokumen.aksi-entri jalur="..">.
    'aksiKhusus' => [
        'rj.dokumen.hapus' => ['label' => 'Hapus entri dokumen', 'roles' => AksiRole::DOKUMEN_HAPUS, 'menggantikan' => 'dokumen.hapus'],
        'rj.dokumen.bukaKunci' => ['label' => 'Buka kunci (cabut TTD) dokumen', 'roles' => AksiRole::DOKUMEN_BUKA_KUNCI, 'menggantikan' => 'dokumen.bukaKunci'],
        'rj.dokumen.ttdPerwakilan' => ['label' => 'TTD atas nama pejabat (perwakilan)', 'roles' => AksiRole::DOKUMEN_TTD_PERWAKILAN, 'menggantikan' => 'dokumen.ttdPerwakilan'],
    ],
];
