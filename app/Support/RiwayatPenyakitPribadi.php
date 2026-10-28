<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * SUMBER TUNGGAL bentuk baris kode SNOMED Riwayat Penyakit Pribadi (Playbook RJ bab 3d) —
 * pola sama dengan [[RekonsiliasiObat]] & [[KeluhanPenyerta]]. Diisi di layar Coding SATUSEHAT.
 *
 * Kode menempel pada teks EMR-nya masing-masing:
 *   anamnesa.riwayatPenyakitSekarangUmum.snomedList[] → jenis SEKARANG (masih berjalan):
 *       Condition clinicalStatus active + onsetPeriod.start = tglMulai (dd/mm/yyyy)
 *   anamnesa.riwayatPenyakitDahulu.snomedList[]       → jenis DAHULU (sudah sembuh):
 *       Condition clinicalStatus inactive + onsetAge = usiaTahun
 * Category kemkes `previous-condition` — disamakan dengan alur Rawat Jalan apikesehatan.com.
 */
final class RiwayatPenyakitPribadi
{
    public const JENIS_SEKARANG = 'sekarang';
    public const JENIS_DAHULU = 'dahulu';

    /** Node anamnesa tempat daftar kode tiap jenis. */
    public const NODE = [
        self::JENIS_SEKARANG => 'riwayatPenyakitSekarangUmum',
        self::JENIS_DAHULU => 'riwayatPenyakitDahulu',
    ];

    /** Baris BARU + stempel pencatat. $keterangan = tglMulai (sekarang) / usiaTahun (dahulu). */
    public static function barisBaru(string $jenis, string $snomedCode, string $snomedDisplayEn, string $snomedDisplayId, string $keterangan): array
    {
        $baris = [
            'snomedCode' => trim($snomedCode),
            'snomedDisplayEn' => trim($snomedDisplayEn),
            'snomedDisplayId' => trim($snomedDisplayId),
        ];
        $baris += $jenis === self::JENIS_SEKARANG
            ? ['tglMulai' => trim($keterangan)]
            : ['usiaTahun' => (int) $keterangan];

        return $baris + [
            'tglCatat' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
            'petugasCatat' => auth()->user()?->myuser_name ?? '',
            'petugasCatatCode' => auth()->user()?->myuser_code ?? '',
        ];
    }

    public static function sudahAda(array $snomedList, string $snomedCode): bool
    {
        return collect($snomedList)->contains(fn($baris) => ($baris['snomedCode'] ?? '') === trim($snomedCode));
    }

    public static function namaBaris(array $baris): string
    {
        return trim((string) (($baris['snomedDisplayId'] ?? '') ?: ($baris['snomedDisplayEn'] ?? ($baris['snomedCode'] ?? ''))));
    }

    /** Teks keterangan baris untuk tabel: "mulai 01/02/2024" / "usia 12 tahun". */
    public static function keteranganBaris(array $baris): string
    {
        if (filled($baris['tglMulai'] ?? null)) {
            return 'mulai ' . $baris['tglMulai'];
        }

        return isset($baris['usiaTahun']) ? 'usia ' . $baris['usiaTahun'] . ' tahun' : '-';
    }
}
