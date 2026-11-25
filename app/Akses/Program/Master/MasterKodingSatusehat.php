<?php

/*
 * Hak akses program "Master Koding & SATUSEHAT" — kelompok Master Data (urutan & judul: _kelompok.php).
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'nama' => 'Master Koding & SATUSEHAT',
    'menu' => [
        ['group' => 'Master Laboratorium', 'groupOrder' => 2, 'order' => 2, 'route' => 'master.loinc', 'title' => 'Master LOINC', 'desc' => 'Kode LOINC pemeriksaan lab & observasi — LOV + pengiriman SATUSEHAT', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'SATUSEHAT'],
        ['group' => 'Master Laboratorium', 'groupOrder' => 2, 'order' => 3, 'route' => 'master.snomed', 'title' => 'Master SNOMED CT', 'desc' => 'Kode SNOMED CT keluhan / prosedur / alergi — LOV + pengiriman SATUSEHAT', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'SATUSEHAT'],
        ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 19, 'route' => 'master.mapping-satusehat', 'aksi' => ['lihat'], 'khusus' => ['lihatObat' => ['Baris Obat (KFA)', ['admin', 'apoteker']], 'lihatLaborat' => ['Baris Item Laboratorium (LOINC)', ['admin', 'laboratorium']], 'lihatRadiologi' => ['Baris Tindakan Radiologi (LOINC)', ['admin', 'radiologi']], 'lihatDokter' => ['Baris Dokter (IHS)', ['admin']], 'lihatPoli' => ['Baris Poli (Location)', ['admin']], 'lihatKamar' => ['Baris Kamar RI (Location)', ['admin']], 'lihatPasien' => ['Baris Pasien (IHS)', ['admin', 'mr']]], 'title' => 'Mapping SATUSEHAT', 'desc' => 'Cakupan KFA, LOINC, IHS dan Location pada master, dengan tautan ke yang belum terisi', 'roles' => ['admin', 'apoteker', 'laboratorium', 'radiologi', 'mr'], 'badge' => 'SATUSEHAT'],
    ],
];
