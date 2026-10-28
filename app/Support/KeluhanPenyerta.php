<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * SUMBER TUNGGAL bentuk satu baris kode SNOMED Keluhan Penyerta (anamnesa RJ).
 * Pola sama dengan [[RekonsiliasiObat]]: baris dibentuk & distempel pencatat di sini,
 * bukan ditulis ulang di tiap pemakai.
 *
 * Node: anamnesa.keluhanPenyerta = [
 *     'keluhanPenyerta' => 'teks bebas (boleh banyak baris) — yang tampil di cetak & Rekam Medis',
 *     'snomedList'      => [ barisBaru(), ... ],   // Satu Sehat, Playbook RJ bab 3b — boleh > 1
 * ]
 */
final class KeluhanPenyerta
{
    /** Baris BARU dari pilihan LOV SNOMED + stempel pencatat (user login, jam sekarang). */
    public static function barisBaru(string $snomedCode, string $snomedDisplayEn, string $snomedDisplayId): array
    {
        return [
            'snomedCode' => trim($snomedCode),
            'snomedDisplayEn' => trim($snomedDisplayEn),
            'snomedDisplayId' => trim($snomedDisplayId),
            'tglCatat' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
            'petugasCatat' => auth()->user()?->myuser_name ?? '',
            'petugasCatatCode' => auth()->user()?->myuser_code ?? '',
        ];
    }

    /** Kode yang sama tak boleh dua kali di satu kunjungan. */
    public static function sudahAda(array $snomedList, string $snomedCode): bool
    {
        return collect($snomedList)->contains(fn($baris) => ($baris['snomedCode'] ?? '') === trim($snomedCode));
    }

    /** Nama tampil satu baris: Indonesia dulu, Inggris bila kosong. */
    public static function namaBaris(array $baris): string
    {
        return trim((string) (($baris['snomedDisplayId'] ?? '') ?: ($baris['snomedDisplayEn'] ?? ($baris['snomedCode'] ?? ''))));
    }
}
