<?php

namespace App\Support;

use Carbon\Carbon;
use App\Support\Terminologi\AlergiSnomed;

/**
 * SUMBER TUNGGAL baris alergen (Playbook RJ bab 3c, AllergyIntolerance) — dipakai bila EMR
 * menyatakan "Ada alergi = Ya". Disamakan dengan alur Rawat Jalan apikesehatan.com: satu
 * AllergyIntolerance per alergen; jenis Obat = kode KFA (master obat), Makanan/Lingkungan =
 * SNOMED zat/organisme; keparahan = criticality. Disimpan di anamnesa.alergi.alergenList[];
 * diisi di layar Coding SATUSEHAT. "Tidak ada alergi" (716186003) tetap milik EMR, bukan di sini.
 *
 * Kode tunggal LAMA (anamnesa.alergi.snomedCode) dibaca lewat dariKodeLama() supaya kunjungan
 * yang dikode sebelum daftar ini ada tidak kehilangan kodenya.
 */
final class AlergiAlergen
{
    public const SISTEM_KFA = 'http://sys-ids.kemkes.go.id/kfa';
    public const SISTEM_SNOMED = 'http://snomed.info/sct';

    /** AllergyIntolerance.category => label Indonesia. */
    public const JENIS = [
        'medication'  => 'Obat',
        'food'        => 'Makanan',
        'environment' => 'Lingkungan',
    ];

    /** AllergyIntolerance.criticality => label Indonesia. */
    public const KEPARAHAN = [
        'low'              => 'Ringan',
        'high'             => 'Berat / berpotensi mengancam jiwa',
        'unable-to-assess' => 'Belum dapat dinilai',
    ];

    /** $productId hanya untuk jenis Obat (asal kode KFA). */
    public static function barisBaru(string $jenis, string $kode, string $display, string $nama, string $keparahan, string $productId = ''): array
    {
        return [
            'jenis' => $jenis,
            'sistem' => $jenis === 'medication' ? self::SISTEM_KFA : self::SISTEM_SNOMED,
            'kode' => trim($kode),
            'display' => trim($display),
            'nama' => trim($nama),
            'keparahan' => $keparahan,
            'productId' => $productId,
            'tglCatat' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
            'petugasCatat' => auth()->user()?->myuser_name ?? '',
            'petugasCatatCode' => auth()->user()?->myuser_code ?? '',
        ];
    }

    /** Baris dari kode SNOMED tunggal lama — null bila kosong / pernyataan "tidak ada alergi". */
    public static function dariKodeLama(array $alergi): ?array
    {
        $kode = trim((string) ($alergi['snomedCode'] ?? ''));
        if ($kode === '' || AlergiSnomed::adalahTidakAdaAlergi($kode)) {
            return null;
        }

        $jenis = AlergiSnomed::kategoriFhir($kode);

        return [
            'jenis' => array_key_exists($jenis, self::JENIS) ? $jenis : 'environment',
            'sistem' => self::SISTEM_SNOMED,
            'kode' => $kode,
            'display' => (string) ($alergi['snomedDisplayEn'] ?? ''),
            'nama' => (string) (($alergi['snomedDisplayId'] ?? '') ?: ($alergi['snomedDisplayEn'] ?? '')),
            'keparahan' => 'low',
            'productId' => '',
            'tglCatat' => '',
            'petugasCatat' => '',
            'petugasCatatCode' => '',
        ];
    }

    public static function kunci(array $baris): string
    {
        return ($baris['jenis'] ?? '') . ':' . ($baris['kode'] ?? '');
    }

    public static function sudahAda(array $alergenList, string $jenis, string $kode): bool
    {
        return collect($alergenList)->contains(fn($baris) => self::kunci($baris) === $jenis . ':' . trim($kode));
    }

    public static function namaBaris(array $baris): string
    {
        return trim((string) (($baris['nama'] ?? '') ?: ($baris['display'] ?? ($baris['kode'] ?? ''))));
    }

    public static function labelJenis(?string $jenis): string
    {
        return self::JENIS[(string) $jenis] ?? '-';
    }

    public static function labelKeparahan(?string $keparahan): string
    {
        return self::KEPARAHAN[(string) $keparahan] ?? '-';
    }
}
