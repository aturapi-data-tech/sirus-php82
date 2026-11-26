<?php

/*
 * Hak akses program "Master Klinis" — kelompok Master Data (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'nama' => 'Master Klinis',
    'menu' => [
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 4, 'route' => 'master.diagnosa', 'title' => 'Master Diagnosa', 'desc' => 'Kelola data diagnosa ICD-10', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 8, 'route' => 'master.diag-keperawatan', 'title' => 'Master Diagnosis Keperawatan', 'desc' => 'Kelola data SDKI, SLKI, SIKI asuhan keperawatan', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 17, 'route' => 'master.ews', 'title' => 'Master EWS', 'desc' => 'Parameter, rentang skor & respon Early Warning System (Dewasa / Anak / Neonatus / MEOWS) untuk Observasi Lanjutan', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'EMR'],
    ],
];
