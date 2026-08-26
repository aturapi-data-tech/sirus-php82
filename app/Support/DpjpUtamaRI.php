<?php

namespace App\Support;

/**
 * DPJP Utama satu episode Rawat Inap, dibaca dari Leveling Dokter.
 *
 * Sumber: `pengkajianAwalPasienRawatInap.levelingDokter[]` pada datadaftarri_json —
 * baris yang `levelDokter`-nya 'Utama' (sisanya 'RawatGabung'). Diisi di
 * EMR RI → Pengkajian Awal → Leveling Dokter.
 *
 * Dipakai formulir Surveilans HAIs sebagai ISIAN AWAL baris tanda tangan
 * "Mengetahui — Dokter yang Merawat". Sengaja hanya mengisi awal, bukan mengunci:
 * nilainya tetap tersimpan di `dokterMerawat` dan boleh diganti petugas lewat
 * combobox PPA — ada kasus yang menandatangani bukan DPJP Utama.
 */
class DpjpUtamaRI
{
    /** Nama DPJP Utama; string kosong bila Leveling Dokter belum diisi. */
    public static function nama(array $dataDaftarRi): string
    {
        return self::cari($dataDaftarRi, 'drName');
    }

    /** Kode dokter DPJP Utama; string kosong bila belum ada. */
    public static function kode(array $dataDaftarRi): string
    {
        return self::cari($dataDaftarRi, 'drId');
    }

    private static function cari(array $dataDaftarRi, string $field): string
    {
        $levelingList = $dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [];
        if (!\is_array($levelingList)) {
            return '';
        }

        foreach ($levelingList as $baris) {
            if (!\is_array($baris)) {
                continue;
            }
            if (strcasecmp((string) ($baris['levelDokter'] ?? ''), 'Utama') !== 0) {
                continue;
            }

            $nilai = trim((string) ($baris[$field] ?? ''));
            if ($nilai !== '') {
                return $nilai;
            }
        }

        return '';
    }
}
