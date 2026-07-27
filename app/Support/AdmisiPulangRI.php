<?php

namespace App\Support;

/**
 * Cara masuk & cara keluar RS untuk satu episode Rawat Inap — DITURUNKAN, bukan diketik ulang.
 *
 * Keduanya sudah terekam di alur induk, jadi form dokumen cukup menampilkannya:
 *
 *   Cara masuk  ← `entryDesc` pada datadaftarri_json (diisi saat pendaftaran RI;
 *                 asalnya rstxn_rihdrs.entry_id → rsmst_entrytypes).
 *   Cara keluar ← `perencanaan.tindakLanjut.tindakLanjutKode` (diisi saat pasien pulang),
 *                 label lewat App\Support\DischargeDisposition.
 *
 * CATATAN: `rstxn_rihdrs.status_pulang` (L/H/I/F) BUKAN cara keluar klinis — itu status
 * penagihan (lunas/hutang). Jangan dipakai untuk kolom ini.
 *
 * Cara keluar wajar masih kosong selama pasien dirawat: dokumen surveilans biasanya
 * diisi jauh sebelum pasien pulang, dan nilainya menyusul sendiri karena diturunkan.
 */
class AdmisiPulangRI
{
    public static function caraMasuk(array $dataDaftarRi): string
    {
        $caraMasuk = trim((string) ($dataDaftarRi['entryDesc'] ?? ''));

        return $caraMasuk !== '' ? $caraMasuk : '-';
    }

    public static function caraKeluar(array $dataDaftarRi): string
    {
        $kode = $dataDaftarRi['perencanaan']['tindakLanjut']['tindakLanjutKode'] ?? null;
        $label = DischargeDisposition::label($kode);

        return $label !== '' ? $label : 'Belum pulang';
    }
}
