<?php

namespace App\Support;

use App\Support\Options\PengkajianAwalRiOptions;
use App\Support\Terminologi\AlergiSnomed;

/**
 * SUMBER TUNGGAL teks klinis anamnesa Rawat Inap untuk SATUSEHAT (sender RI & layar Coding SATUSEHAT RI).
 *
 * Beda dari RJ/UGD (satu node anamnesa): di RI teksnya tersebar di DUA formulir —
 *   pengkajianDokter.anamnesa              Pengkajian Dokter (terisi ±1 dari 4 rawat inap, data 2026-10-28)
 *   pengkajianAwalPasienRawatInap.bagian*  Pengkajian Awal perawat (terisi ±9 dari 10)
 * Aturan: teks dokter dipakai lebih dulu, cadangannya teks perawat.
 *
 * KODE SATUSEHAT RI tidak ditulis ke node formulir mana pun, melainkan ke node akar
 * `kodingSatuSehat` milik layar Coding — form EMR tak pernah menyentuhnya, jadi kode tak
 * tertimpa saat dokter/perawat menyimpan (RJ/UGD butuh pertahankanKodeCoding karena kodenya
 * menumpang node form).
 */
final class AnamnesaRi
{
    /** Node akar penyimpan kode Coding SATUSEHAT RI. */
    public const NODE_KODING = 'kodingSatuSehat';

    public static function keluhanUtama(array $dataRI): string
    {
        return self::pertamaTerisi(
            $dataRI['pengkajianDokter']['anamnesa']['keluhanUtama'] ?? null,
            $dataRI['pengkajianAwalPasienRawatInap']['bagian4PemeriksaanFisik']['keluhanUtama'] ?? null,
        );
    }

    public static function keluhanPenyerta(array $dataRI): string
    {
        return self::pertamaTerisi($dataRI['pengkajianDokter']['anamnesa']['keluhanTambahan'] ?? null);
    }

    public static function riwayatPenyakitSekarang(array $dataRI): string
    {
        return self::pertamaTerisi($dataRI['pengkajianDokter']['anamnesa']['riwayatPenyakit']['sekarang'] ?? null);
    }

    public static function riwayatPenyakitDahulu(array $dataRI): string
    {
        $perawat = $dataRI['pengkajianAwalPasienRawatInap']['bagian2RiwayatPasien']['riwayatPenyakitOperasiCedera'] ?? [];

        return self::pertamaTerisi(
            $dataRI['pengkajianDokter']['anamnesa']['riwayatPenyakit']['dahulu'] ?? null,
            trim(implode(' — ', array_filter([trim((string) ($perawat['deskripsi'] ?? '')), trim((string) ($perawat['keterangan'] ?? ''))]))),
        );
    }

    public static function riwayatPenyakitKeluarga(array $dataRI): string
    {
        return self::pertamaTerisi(
            $dataRI['pengkajianDokter']['anamnesa']['riwayatPenyakit']['keluarga'] ?? null,
            $dataRI['pengkajianAwalPasienRawatInap']['bagian2RiwayatPasien']['riwayatKeluarga']['keterangan'] ?? null,
        );
    }

    /** Rekonsiliasi obat Pengkajian Dokter sebagai teks konteks riwayat pengobatan. */
    public static function rekonsiliasiObat(array $dataRI): string
    {
        return collect((array) ($dataRI['pengkajianDokter']['anamnesa']['rekonsiliasiObat'] ?? []))
            ->map(fn($obat) => trim(implode(' ', array_filter([
                trim((string) ($obat['namaObat'] ?? '')),
                trim((string) ($obat['dosis'] ?? '')),
                trim((string) ($obat['rute'] ?? '')),
            ], fn($bagian) => $bagian !== ''))))
            ->filter()
            ->implode("\n");
    }

    /** Status emosional Pengkajian Awal: [kunci opsi, label] — kunci '' bila belum diisi. */
    public static function statusEmosional(array $dataRI): array
    {
        $kunci = trim((string) ($dataRI['pengkajianAwalPasienRawatInap']['bagian3PsikososialDanEkonomi']['statusEmosional']['pilihan'] ?? ''));

        return [$kunci, PengkajianAwalRiOptions::STATUS_EMOSIONAL[$kunci] ?? $kunci];
    }

    /**
     * Alergi RI dari Pengkajian Dokter, diseragamkan (adaAlergi diturunkan dari teks). null = Pengkajian
     * Dokter belum mengisi alergi sama sekali — JANGAN dianggap "tidak ada alergi" (normalisasi akan
     * menyimpulkan 'Tidak' dari teks kosong, padahal faktanya belum ditanyakan).
     *
     * @return array{adaAlergi: string, alergi: string, snomedCode: string}|null
     */
    public static function alergi(array $dataRI): ?array
    {
        $anamnesa = $dataRI['pengkajianDokter']['anamnesa'] ?? [];
        if (trim((string) ($anamnesa['adaAlergi'] ?? '')) === '' && trim((string) ($anamnesa['jenisAlergi'] ?? '')) === '') {
            return null;
        }
        $hasil = AlergiSnomed::normalisasiRi($anamnesa);

        return [
            'adaAlergi' => (string) ($hasil['adaAlergi'] ?? ''),
            'alergi' => (string) ($hasil['jenisAlergi'] ?? ''),
            'snomedCode' => (string) ($hasil['jenisAlergiSnomedCode'] ?? ''),
            'snomedDisplayEn' => (string) ($hasil['jenisAlergiSnomedDisplayEn'] ?? ''),
            'snomedDisplayId' => (string) ($hasil['jenisAlergiSnomedDisplayId'] ?? ''),
        ];
    }

    /** Isi node kodingSatuSehat (atau anaknya) — selalu array. */
    public static function koding(array $dataRI, ?string $kunci = null): array
    {
        $koding = (array) ($dataRI[self::NODE_KODING] ?? []);

        return $kunci === null ? $koding : (array) ($koding[$kunci] ?? []);
    }

    private static function pertamaTerisi(...$teksList): string
    {
        foreach ($teksList as $teks) {
            $teks = trim((string) $teks);
            if ($teks !== '') {
                return $teks;
            }
        }

        return '';
    }
}
