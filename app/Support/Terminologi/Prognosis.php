<?php

namespace App\Support\Terminologi;

/**
 * Prognosis (Playbook RJ bab 22) — SUMBER TUNGGAL pilihan EMR (perencanaan.prognosis.prognosis),
 * tampilan Coding SATUSEHAT, dan sender kirim-prognosis. Dikirim sebagai ClinicalImpression code
 * 20481000 Determination of prognosis + prognosisCodeableConcept SNOMED.
 *
 * ⚠️ JANGAN menyalin kode alur apikesehatan.com untuk bab ini: 3 dari 4 SALAH (diperiksa
 * tx.fhir.org 2026-10-28) — "Cukup/Sedang" 170969009 = *Prognosis bad*, "Buruk" 170970005 =
 * *Prognosis uncertain*, "Belum Dapat Ditentukan" 65872001 = tidak ada (salah ketik dari
 * 65872000 Fair prognosis). Kode di bawah sudah diverifikasi aktif.
 */
final class Prognosis
{
    public const CODE = ['system' => 'http://snomed.info/sct', 'code' => '20481000', 'display' => 'Determination of prognosis'];

    /** label EMR => [kode SNOMED, display resmi] — urutan = urutan pilihan di form. */
    public const OPTIONS = [
        'Baik'                   => ['code' => '170968001', 'display' => 'Prognosis good'],
        'Sedang'                 => ['code' => '65872000',  'display' => 'Fair prognosis'],
        'Buruk'                  => ['code' => '170969009', 'display' => 'Prognosis bad'],
        'Belum Dapat Ditentukan' => ['code' => '170970005', 'display' => 'Prognosis uncertain'],
    ];

    /** @return array{code: string, display: string}|null */
    public static function kode(?string $label): ?array
    {
        return self::OPTIONS[trim((string) $label)] ?? null;
    }
}
