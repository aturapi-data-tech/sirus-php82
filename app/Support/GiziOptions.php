<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Sumber tunggal definisi PROGRAM DIET + pembacaan riwayat penilaian gizi
 * (`penilaian.gizi[]` di datadaftarri_json / datadaftar*_json).
 *
 * Daftar diet diporting persis dari list item GIZIM_DIET Oracle Forms
 * rit003gizi.fmb (ANTRI_RI_GIZI → RSTXN_RIGIZIS) — urutan & redaksi legacy.
 *
 * Dipakai oleh:
 *   - form Penilaian Gizi EMR RI (tab Penilaian → Gizi)
 *   - worklist Gizi Rawat Inap (/ri/gizi) — entri diet harian + rekap produksi
 */
class GiziOptions
{
    public const PROGRAM_DIET = [
        'Diet Makan Biasa',
        'Diet Makan Lunak',
        'Diet Makan Saring',
        'Diet Makan Cair',
        'TKTP / Tinggi Kalori Tinggi Protein',
        'Diet Rendah Kalori',
        'Diet Rendah Garam',
        'Diet Tinggi Serat',
        'Diet Pra Bedah',
        'Diet Pasca Bedah',
        'Diet Hiperemesis',
        'Diet Puasa Sementara',
        'Diet Rendah Gula',
        'Diet Rendah Serat',
        'Diet Makan Halus',
        'DM',
    ];

    /**
     * Riwayat penilaian gizi sebagai DAFTAR entri.
     *
     * Record lama bisa menyimpan `penilaian.gizi` sebagai SATU entri assoc
     * (bukan daftar); bila dinilai lagi lewat EMR, entri baru berkunci angka
     * duduk di samping key lama. Entri lama ditaruh paling depan supaya
     * urutan tetap kronologis. (Pola sama dgn NyeriOptions::daftarEntri.)
     */
    public static function daftarEntri(mixed $riwayat): array
    {
        if (!is_array($riwayat) || $riwayat === []) {
            return [];
        }

        $daftar = [];
        $entriLama = [];
        foreach ($riwayat as $kunci => $nilai) {
            if (is_int($kunci)) {
                if (is_array($nilai) && $nilai !== []) {
                    $daftar[] = $nilai;
                }
                continue;
            }
            $entriLama[$kunci] = $nilai;
        }

        if ($entriLama !== []) {
            array_unshift($daftar, $entriLama);
        }

        return $daftar;
    }

    /**
     * Entri TERAKHIR yang field gizi-nya terisi (mis. 'programDiet',
     * 'kategoriGizi'). "Terakhir" = tglPenilaian paling baru;
     * tgl sama / tak terparse → entri yang diinput belakangan menang
     * (pola sama dgn hitungResikoJatuhTerakhir di daftar-ri).
     *
     * @return array{} | array{tglPenilaian:string, petugasPenilai:string, nilai:mixed, entri:array}
     */
    public static function entriTerakhirDengan(mixed $riwayat, string $field): array
    {
        $terakhir = null;
        $maxTimestamp = null;
        foreach (self::daftarEntri($riwayat) as $entri) {
            $nilai = data_get($entri, 'gizi.' . $field);
            if (!filled($nilai)) {
                continue;
            }
            try {
                $timestamp = Carbon::createFromFormat('d/m/Y H:i:s', trim((string) ($entri['tglPenilaian'] ?? '')))->getTimestamp();
            } catch (\Throwable) {
                $timestamp = null;
            }
            if ($terakhir === null || $timestamp === null || $maxTimestamp === null || $timestamp >= $maxTimestamp) {
                $terakhir = $entri;
                $maxTimestamp = $timestamp ?? $maxTimestamp;
            }
        }

        if ($terakhir === null) {
            return [];
        }

        return [
            'tglPenilaian' => (string) ($terakhir['tglPenilaian'] ?? ''),
            'petugasPenilai' => (string) ($terakhir['petugasPenilai'] ?? ''),
            'nilai' => data_get($terakhir, 'gizi.' . $field),
            'entri' => $terakhir,
        ];
    }
}
