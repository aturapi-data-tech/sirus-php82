<?php

namespace App\Support\AutomaticStopOrder;

use Carbon\Carbon;

/**
 * Mesin hitung Automatic Stop Order — MURNI (tanpa DB), diuji di
 * tests/Unit/AutomaticStopOrderHitungTest.php. Pola sama dengan EwsSkor.
 *
 * Masukan: daftar e-resep RI dari JSON (`eresepHdr[]`) + master dari
 * AutomaticStopOrderMaster::muat(). Keluaran: satu baris per obat yang
 * DIPETAKAN ke golongan, berisi hari berjalan, batas, dan status.
 *
 * Aturan yang disepakati user (2026-09-07):
 *   - Hanya resep yang sudah DITANDATANGANI dokter atau sudah TERKIRIM ke apotek
 *     (slsNo) yang dihitung — sama dengan definisi "obat aktif" di PTO.
 *   - Hitungan memakai JAM resep (resepDate 'd/m/Y H:i:s'), bukan tanggal saja:
 *     batas stop = resep pertama + N x 24 jam; hari ke-1 = 24 jam pertama.
 *   - Dihitung dari resep PERTAMA dalam rantai tak terputus. Rantai dianggap
 *     putus bila jeda antar resep yang memuat obat itu melebihi JEDA_MAKSIMAL_HARI
 *     x 24 jam; setelah putus, hitungan mulai lagi dari resep berikutnya.
 *     Resep ulang harian TIDAK mengulang hitungan — itu justru yang dicegah ASO.
 *   - Hasil TIDAK disimpan: berubah tiap hari, dihitung saat tampil.
 *   - Keputusan kaji ulang (LANJUT/STOP) belum ada — tahap berikutnya.
 */
class AutomaticStopOrderHitung
{
    /** Jeda antar resep (hari) yang masih dianggap satu rantai pemberian. */
    public const JEDA_MAKSIMAL_HARI = 2;

    public const STATUS_AMAN = 'AMAN';
    public const STATUS_MENDEKATI = 'MENDEKATI';
    public const STATUS_LEWAT = 'LEWAT';

    /**
     * @param  array<int, array>  $eresepHdr  node `eresepHdr` dari JSON RI
     * @param  array  $master  keluaran AutomaticStopOrderMaster::muat()
     * @return array{tersedia: bool, baris: array<int, array>, tidakDipetakan: array<int, array>, ringkasan: array<string, int>}
     */
    public static function nilai(array $eresepHdr, array $master, ?Carbon $sekarang = null): array
    {
        $sekarang = ($sekarang ?? Carbon::now(config('app.timezone')))->copy();

        $perObat = self::kumpulkanPerObat($eresepHdr);

        $baris = [];
        $tidakDipetakan = [];
        foreach ($perObat as $productId => $obat) {
            $golongan = AutomaticStopOrderMaster::golonganUntukProduk($master, $productId);
            if ($golongan === null) {
                $tidakDipetakan[] = ['productId' => $productId, 'productName' => $obat['productName']];
                continue;
            }

            $rantai = self::rantaiTerakhir($obat['tanggal']);
            $tglMulai = $rantai[0];
            $tglResepTerakhir = end($rantai);
            // Carbon 3 mengembalikan float & bertanda; jam sebelum resep pertama dianggap 0.
            $jamBerjalan = max(0, (int) floor($tglMulai->diffInHours($sekarang, false)));
            $hariBerjalan = intdiv($jamBerjalan, 24) + 1;
            $batas = (int) $golongan['batas_hari'];
            $batasMinimal = $golongan['batas_minimal_hari'] === null ? null : (int) $golongan['batas_minimal_hari'];

            $baris[] = [
                'productId' => $productId,
                'productName' => $obat['productName'],
                'signa' => $obat['signa'],
                'resepNoList' => $obat['resepNoList'],
                'golonganKode' => $golongan['golongan_kode'],
                'golonganNama' => $golongan['golongan_nama'],
                'keterangan' => $golongan['keterangan'],
                'catatanProduk' => $golongan['catatan_produk'],
                'tglMulai' => $tglMulai->format('d/m/Y H:i'),
                'tglResepTerakhir' => $tglResepTerakhir->format('d/m/Y H:i'),
                // Batas N hari = N x 24 jam sejak resep pertama (jam ikut dihitung).
                'tglBatasMinimal' => $batasMinimal === null ? null : $tglMulai->copy()->addDays($batasMinimal)->format('d/m/Y H:i'),
                'tglBatasStop' => $tglMulai->copy()->addDays($batas)->format('d/m/Y H:i'),
                'jamBerjalan' => $jamBerjalan,
                'hariBerjalan' => $hariBerjalan,
                'batasHari' => $batas,
                'batasMinimalHari' => $batasMinimal,
                'sisaHari' => max(0, $batas - $hariBerjalan + 1),
                'belumMinimal' => $batasMinimal !== null && $hariBerjalan < $batasMinimal,
                'status' => self::status($hariBerjalan, $batas),
            ];
        }

        // Yang paling mendesak di atas: LEWAT, MENDEKATI, lalu sisa hari tersedikit.
        usort($baris, fn(array $a, array $b) => [self::bobotStatus($a['status']), $a['sisaHari'], $a['productName']] <=> [self::bobotStatus($b['status']), $b['sisaHari'], $b['productName']]);
        usort($tidakDipetakan, fn(array $a, array $b) => strcmp($a['productName'], $b['productName']));

        return [
            'tersedia' => (bool) ($master['tersedia'] ?? false),
            'baris' => $baris,
            'tidakDipetakan' => $tidakDipetakan,
            'ringkasan' => self::ringkasan($baris),
        ];
    }

    /** Jumlah obat per status — untuk badge di header / display pasien. */
    public static function ringkasan(array $baris): array
    {
        $ringkasan = [self::STATUS_LEWAT => 0, self::STATUS_MENDEKATI => 0, self::STATUS_AMAN => 0];
        foreach ($baris as $obat) {
            $ringkasan[$obat['status']] = ($ringkasan[$obat['status']] ?? 0) + 1;
        }

        return $ringkasan;
    }

    /** LEWAT = sudah melewati N x 24 jam (hari ke-N+1); MENDEKATI = sedang di hari ke-N (24 jam terakhir). */
    public static function status(int $hariBerjalan, int $batasHari): string
    {
        if ($hariBerjalan > $batasHari) {
            return self::STATUS_LEWAT;
        }
        if ($hariBerjalan === $batasHari) {
            return self::STATUS_MENDEKATI;
        }

        return self::STATUS_AMAN;
    }

    public static function labelStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_LEWAT => 'Lewat batas',
            self::STATUS_MENDEKATI => 'Mendekati batas',
            default => 'Aman',
        };
    }

    /** Kelas Tailwind badge per status — token yang sudah ada di build (lihat x-badge). */
    public static function kelasStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_LEWAT => 'bg-error-tint text-error-deep dark:bg-red-900/30 dark:text-red-200',
            self::STATUS_MENDEKATI => 'bg-warning-tint text-warning-deep dark:bg-amber-900/30 dark:text-amber-200',
            default => 'bg-success-tint text-success-deep dark:bg-green-900/30 dark:text-green-200',
        };
    }

    /**
     * Waktu resep dari JSON: 'd/m/Y H:i:s' (eresep-ri), JAM IKUT DIPAKAI — toleran
     * 'd/m/Y' saja (dianggap 00:00). Null bila kosong / tak terbaca, dan resep itu dilewati.
     */
    public static function tanggalResep(?string $resepDate): ?Carbon
    {
        $resepDate = trim((string) $resepDate);
        if ($resepDate === '') {
            return null;
        }
        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y'] as $format) {
            try {
                $waktu = Carbon::createFromFormat($format, $resepDate);

                return $format === 'd/m/Y' ? $waktu->startOfDay() : $waktu;
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /** Resep aktif = sudah TTD dokter atau sudah terkirim ke apotek (definisi PTO). */
    public static function resepAktif(array $hdr): bool
    {
        return !empty($hdr['tandaTanganDokter']['dokterPeresep'] ?? null) || !empty($hdr['slsNo'] ?? null);
    }

    private static function bobotStatus(string $status): int
    {
        return match ($status) { self::STATUS_LEWAT => 0, self::STATUS_MENDEKATI => 1, default => 2 };
    }

    /**
     * Kelompokkan resep aktif per productId: daftar waktu resep (unik, urut naik),
     * nama & signa dari resep TERBARU, nomor resep yang memuatnya.
     * Racikan dilewati: bahan racikan tidak punya order per obat yang bisa di-stop.
     *
     * @return array<string, array{productName: string, signa: string, tanggal: array<int, Carbon>, resepNoList: array<int, mixed>}>
     */
    private static function kumpulkanPerObat(array $eresepHdr): array
    {
        $perObat = [];
        foreach ($eresepHdr as $hdr) {
            if (!is_array($hdr) || !self::resepAktif($hdr)) {
                continue;
            }
            $tanggal = self::tanggalResep($hdr['resepDate'] ?? null);
            if ($tanggal === null) {
                continue;
            }
            foreach ($hdr['eresep'] ?? [] as $item) {
                $productId = trim((string) ($item['productId'] ?? ''));
                if ($productId === '') {
                    continue;
                }
                $signa = trim(($item['signaX'] ?? '') . ' dd ' . ($item['signaHari'] ?? ''), ' d');
                $perObat[$productId] ??= ['productName' => '', 'signa' => '', 'tanggal' => [], 'resepNoList' => [], 'tanggalTerbaru' => null];
                $perObat[$productId]['tanggal'][$tanggal->format('YmdHis')] = $tanggal;
                $perObat[$productId]['resepNoList'][] = $hdr['resepNo'] ?? null;
                if ($perObat[$productId]['tanggalTerbaru'] === null || $tanggal->gte($perObat[$productId]['tanggalTerbaru'])) {
                    $perObat[$productId]['tanggalTerbaru'] = $tanggal;
                    $perObat[$productId]['productName'] = (string) ($item['productName'] ?? $productId);
                    $perObat[$productId]['signa'] = $signa !== '' ? 'S ' . $signa : '';
                }
            }
        }

        foreach ($perObat as &$obat) {
            ksort($obat['tanggal']);
            $obat['tanggal'] = array_values($obat['tanggal']);
            $obat['resepNoList'] = array_values(array_unique(array_filter($obat['resepNoList'], fn($no) => $no !== null)));
            unset($obat['tanggalTerbaru']);
        }
        unset($obat);

        return $perObat;
    }

    /**
     * Rantai TERAKHIR yang tak terputus dari daftar waktu resep urut naik (jeda dalam jam).
     *
     * @param  array<int, Carbon>  $tanggal
     * @return array<int, Carbon>
     */
    private static function rantaiTerakhir(array $tanggal): array
    {
        $rantai = [];
        $sebelumnya = null;
        foreach ($tanggal as $hari) {
            if ($sebelumnya !== null && $sebelumnya->diffInHours($hari, false) > self::JEDA_MAKSIMAL_HARI * 24) {
                $rantai = [];
            }
            $rantai[] = $hari;
            $sebelumnya = $hari;
        }

        return $rantai;
    }
}
