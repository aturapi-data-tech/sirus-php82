<?php

/*
 * Hak akses program "Modul Dokumen UGD" — kelompok UGD (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'nama' => 'Modul Dokumen UGD',
    // Hak modul dokumen JALUR INI (dulu satu hak untuk RJ/UGD/RI). Sel Aksi: <x-modul-dokumen.aksi-entri jalur="..">.
    'aksiKhusus' => [
        'ugd.dokumen.hapus' => ['label' => 'Hapus entri dokumen', 'roles' => AksiRole::DOKUMEN_HAPUS, 'menggantikan' => 'dokumen.hapus'],
        'ugd.dokumen.bukaKunci' => ['label' => 'Buka kunci (cabut TTD) dokumen', 'roles' => AksiRole::DOKUMEN_BUKA_KUNCI, 'menggantikan' => 'dokumen.bukaKunci'],
        'ugd.dokumen.ttdPerwakilan' => ['label' => 'TTD atas nama pejabat (perwakilan)', 'roles' => AksiRole::DOKUMEN_TTD_PERWAKILAN, 'menggantikan' => 'dokumen.ttdPerwakilan'],
    ],
];
