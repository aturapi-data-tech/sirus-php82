<?php

namespace App\Support;

use Carbon\Carbon;
use App\Support\Terminologi\ObatKfa;

/**
 * SUMBER TUNGGAL baris obat Riwayat Pengobatan (Playbook RJ bab 3f, MedicationStatement) — pola
 * sama dengan [[RiwayatPenyakitKeluarga]]. Diisi di layar Coding SATUSEHAT, disimpan di
 * anamnesa.riwayatPengobatan.obatList[] (teks klinisnya di node yang sama).
 *
 * Kode KFA diambil dari master obat (immst_products.product_id_satusehat) — sumber yang sama
 * dengan [[ObatKfa]]. Obat tanpa KFA di master DITOLAK saat Tambah (bukan dikirim tanpa kode).
 */
final class RiwayatPengobatan
{
    /** MedicationStatement.status => label Indonesia. */
    public const STATUS = [
        'active'    => 'Masih dikonsumsi',
        'completed' => 'Sudah selesai',
        'stopped'   => 'Dihentikan',
    ];

    /** @return array{kfaCode: string, kfaDisplay: string}|null null bila master belum punya KFA */
    public static function kfaDariMaster(string $productId): ?array
    {
        return ObatKfa::kfaProduk($productId);
    }

    public static function barisBaru(string $productId, string $namaObat, string $kfaCode, string $kfaDisplay, string $status, string $aturanPakai): array
    {
        return [
            'productId' => $productId,
            'namaObat' => trim($namaObat),
            'kfaCode' => $kfaCode,
            'kfaDisplay' => $kfaDisplay,
            'status' => $status,
            'aturanPakai' => trim($aturanPakai),
            'tglCatat' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
            'petugasCatat' => auth()->user()?->myuser_name ?? '',
            'petugasCatatCode' => auth()->user()?->myuser_code ?? '',
        ];
    }

    public static function sudahAda(array $obatList, string $productId): bool
    {
        return collect($obatList)->contains(fn($baris) => (string) ($baris['productId'] ?? '') === $productId);
    }

    public static function labelStatus(?string $status): string
    {
        return self::STATUS[(string) $status] ?? '-';
    }
}
