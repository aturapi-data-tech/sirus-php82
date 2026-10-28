<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * SUMBER TUNGGAL baris kode Riwayat Penyakit Keluarga (Playbook RJ bab 3e, FamilyMemberHistory) —
 * pola sama dengan [[RiwayatPenyakitPribadi]]. Diisi di layar Coding SATUSEHAT, disimpan di
 * anamnesa.riwayatPenyakitKeluarga.snomedList[] (teks klinisnya di node yang sama).
 * Hubungan keluarga = HL7 v3-RoleCode (diverifikasi tx.fhir.org 2026-10-28).
 */
final class RiwayatPenyakitKeluarga
{
    /** kode v3-RoleCode => [label Indonesia, display resmi]. */
    public const HUBUNGAN = [
        'MTH'     => ['label' => 'Ibu',                   'display' => 'mother'],
        'FTH'     => ['label' => 'Ayah',                  'display' => 'father'],
        'NSIB'    => ['label' => 'Saudara Kandung',       'display' => 'natural sibling'],
        'GRPRN'   => ['label' => 'Kakek / Nenek',         'display' => 'grandparent'],
        'FAMMEMB' => ['label' => 'Anggota Keluarga Lain', 'display' => 'family member'],
    ];

    public static function barisBaru(string $hubungan, string $snomedCode, string $snomedDisplayEn, string $snomedDisplayId, string $keterangan): array
    {
        return [
            'hubungan' => $hubungan,
            'snomedCode' => trim($snomedCode),
            'snomedDisplayEn' => trim($snomedDisplayEn),
            'snomedDisplayId' => trim($snomedDisplayId),
            'keterangan' => trim($keterangan),
            'tglCatat' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
            'petugasCatat' => auth()->user()?->myuser_name ?? '',
            'petugasCatatCode' => auth()->user()?->myuser_code ?? '',
        ];
    }

    /** Satu baris unik per hubungan + penyakit (ibu & ayah boleh sama-sama DM). */
    public static function kunci(array $baris): string
    {
        return ($baris['hubungan'] ?? '') . ':' . ($baris['snomedCode'] ?? '');
    }

    public static function sudahAda(array $snomedList, string $hubungan, string $snomedCode): bool
    {
        return collect($snomedList)->contains(fn($baris) => self::kunci($baris) === $hubungan . ':' . trim($snomedCode));
    }

    public static function namaBaris(array $baris): string
    {
        return trim((string) (($baris['snomedDisplayId'] ?? '') ?: ($baris['snomedDisplayEn'] ?? ($baris['snomedCode'] ?? ''))));
    }

    public static function labelHubungan(?string $kode): string
    {
        return self::HUBUNGAN[(string) $kode]['label'] ?? '-';
    }
}
