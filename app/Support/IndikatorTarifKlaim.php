<?php

namespace App\Support;

/**
 * Indikator merah/kuning/hijau: tarif berjalan RS dibanding tarif klaim INA-CBG.
 *
 *   persentase = tarif RS ÷ tarif INA-CBG × 100
 *     ≤ 60 %        → hijau   (aman)
 *     > 60 – < 70 % → kuning  (waspada)
 *     ≥ 70 %        → merah   (mendekati / melewati tarif klaim)
 *
 * Satu-satunya tempat ambang batas & rumus — dipakai komponen
 * <x-klaim.indikator-tarif> dan halaman mana pun yang memantau klaim.
 * Mengubah ambang cukup di konstanta di bawah.
 */
class IndikatorTarifKlaim
{
    /** Persentase tertinggi yang masih hijau. */
    public const BATAS_HIJAU = 60;

    /** Persentase mulai merah; di antara BATAS_HIJAU dan ini = kuning. */
    public const BATAS_MERAH = 70;

    /** Tarif RS sebagai persen dari tarif klaim; null bila tarif klaim belum ada. */
    public static function persentase(int $tarifRs, int $tarifKlaim): ?float
    {
        if ($tarifKlaim <= 0) {
            return null;
        }
        return round($tarifRs / $tarifKlaim * 100, 1);
    }

    /** 'hijau' | 'kuning' | 'merah', atau 'kosong' bila persentase tak bisa dihitung. */
    public static function warna(?float $persentase): string
    {
        if ($persentase === null) {
            return 'kosong';
        }
        if ($persentase <= self::BATAS_HIJAU) {
            return 'hijau';
        }
        if ($persentase < self::BATAS_MERAH) {
            return 'kuning';
        }
        return 'merah';
    }

    /**
     * Tarif berjalan RS rawat inap dari hasil EmrRITrait::calculateRICosts().
     * Komponennya sama dengan pengisian tarif_rs klaim iDRG RI
     * (⚡kirim-set-data::autoBuildFromKasir) supaya angka di layar = angka yang dikirim
     * ke E-Klaim: obat = obatPinjam + bonResep − rtnObat; resep lunas tunai tidak ikut.
     */
    public static function tarifBerjalanRi(array $biaya): int
    {
        $obat = max(0, ($biaya['obatPinjam'] ?? 0) + ($biaya['bonResep'] ?? 0) - ($biaya['rtnObat'] ?? 0));

        return (int) (
            ($biaya['jasaMedis'] ?? 0) + ($biaya['ok'] ?? 0)
            + ($biaya['visit'] ?? 0) + ($biaya['konsul'] ?? 0) + ($biaya['adminAge'] ?? 0) + ($biaya['adminStatus'] ?? 0) + ($biaya['trfUgdRj'] ?? 0)
            + ($biaya['jasaDokter'] ?? 0) + ($biaya['lainLain'] ?? 0) + ($biaya['rad'] ?? 0) + ($biaya['lab'] ?? 0)
            + ($biaya['room'] ?? 0) + ($biaya['commonService'] ?? 0) + ($biaya['perawatan'] ?? 0)
            + $obat
        );
    }

    /**
     * Tarif INA-CBG dari node JSON `idrg`: stage 2 bila sudah ada, kalau tidak stage 1.
     * Pembacaan toleran sama dengan panel grouping (⚡kirim-group-inacbg-1/2):
     * tariff angka / tariff.total / total_tariff, cadangan base_rate × cost_weight.
     * 0 = belum grouping INA-CBG.
     */
    public static function tarifInacbgDariIdrg(array $idrg): int
    {
        foreach (['inacbgStage2', 'inacbgStage1'] as $tahap) {
            $hasilGrouping = $idrg[$tahap] ?? [];
            if (!\is_array($hasilGrouping) || empty($hasilGrouping)) {
                continue;
            }
            $tarif = self::tarifDariHasilGrouping($hasilGrouping);
            if ($tarif > 0) {
                return $tarif;
            }
        }
        return 0;
    }

    private static function tarifDariHasilGrouping(array $hasilGrouping): int
    {
        $ambil = fn(string $key) => data_get($hasilGrouping, $key) ?? data_get($hasilGrouping, "response_inacbg.{$key}");

        $tarifMentah = $ambil('tariff');
        $tarif = is_numeric($tarifMentah)
            ? (int) $tarifMentah
            : (int) (data_get($tarifMentah, 'total') ?? $ambil('total_tariff') ?? 0);

        if ($tarif === 0) {
            $bobotBiaya = data_get($ambil('cbg') ?? [], 'cost_weight');
            $tarifDasar = (int) ($ambil('base_rate') ?? $ambil('nbr') ?? 0);
            if (is_numeric($bobotBiaya) && $tarifDasar > 0) {
                $tarif = (int) round($tarifDasar * (float) $bobotBiaya);
            }
        }
        return $tarif;
    }
}
