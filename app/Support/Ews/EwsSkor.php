<?php

namespace App\Support\Ews;

use Carbon\Carbon;
use Throwable;

/**
 * Mesin skor EWS — murni input → output, tidak menyentuh DB maupun Livewire.
 *
 * Masukan: varian, nilai mentah per parameter (key = PARAM_KODE), master
 * (bentuk keluaran EwsMaster::muat() / susunan EwsDefault), dan umur pasien
 * dalam bulan untuk baris acuan per usia.
 *
 * Keluaran satu array yang LANGSUNG disimpan di JSON EMR (entri Observasi
 * Lanjutan, key `ews`) — jadi cetakan & viewer tidak perlu menghitung ulang dan
 * hasilnya tetap sama walau master diubah belakangan.
 */
class EwsSkor
{
    /** Batas usia varian: ≤ 28 hari neonatus, < 16 tahun anak (kebijakan RS + NEWS2 ≥ 16 th). */
    public const BATAS_NEONATUS_HARI = 28;
    public const BATAS_ANAK_TAHUN    = 16;

    /** Skor per parameter yang dianggap "kode merah" (aturan PARAM_MERAH di respon). */
    public const SKOR_MERAH = 3;

    /**
     * Tentukan varian dari umur. Null bila umur tak diketahui — pemanggil yang
     * memutuskan default-nya (Observasi Lanjutan memakai DEWASA).
     */
    public static function varianUntukUmur(?int $umurHari, ?int $umurTahun): ?string
    {
        if ($umurHari === null || $umurTahun === null) {
            return null;
        }
        if ($umurHari <= self::BATAS_NEONATUS_HARI) {
            return 'NEONATUS';
        }
        if ($umurTahun < self::BATAS_ANAK_TAHUN) {
            return 'ANAK';
        }

        return 'DEWASA';
    }

    /**
     * Umur pasien dari birth_date (kolom umur di master hanya snapshot saat
     * pendaftaran). Null semua bila tanggal kosong / tak terbaca.
     *
     * @return array{hari: ?int, bulan: ?int, tahun: ?int}
     */
    public static function umurDari(?string $birthDate, ?Carbon $acuan = null): array
    {
        if (empty($birthDate)) {
            return ['hari' => null, 'bulan' => null, 'tahun' => null];
        }

        try {
            $lahir = Carbon::parse($birthDate)->startOfDay();
            $acuan = ($acuan ?? Carbon::now(config('app.timezone')))->copy()->startOfDay();
            if ($lahir->greaterThan($acuan)) {
                return ['hari' => null, 'bulan' => null, 'tahun' => null];
            }
            $selisih = $lahir->diff($acuan);

            return [
                'hari'  => (int) $selisih->days,
                'bulan' => (int) ($selisih->y * 12 + $selisih->m),
                'tahun' => (int) $selisih->y,
            ];
        } catch (Throwable) {
            return ['hari' => null, 'bulan' => null, 'tahun' => null];
        }
    }

    /**
     * Hitung skor.
     *
     * @param array $nilai  key = PARAM_KODE → nilai mentah (angka / kode pilihan / '')
     * @param array $master ['params' => [...], 'respons' => [...]] — boleh berisi semua varian
     */
    public static function hitung(string $varian, array $nilai, array $master, ?int $umurBulan = null): array
    {
        $params = self::paramsVarian($master, $varian);
        $hasil  = [
            'tersedia'       => $params !== [],
            'varian'         => $varian,
            'per'            => [],
            'total'          => 0,
            'adaMerah'       => false,
            'lengkap'        => true,
            'kurang'         => [],
            'kategori'       => null,
            'warna'          => null,
            'frekuensi'      => null,
            'frekuensiMenit' => null,
            'respon'         => null,
        ];

        if (!$hasil['tersedia']) {
            $hasil['lengkap'] = false;

            return $hasil;
        }

        // Parameter yang digantikan (SpO2 skala 2 terisi → SpO2 skala 1 dilewati).
        $dilewati = [];
        foreach ($params as $param) {
            if (!empty($param['gantikan_kode']) && self::terisi($nilai[$param['param_kode']] ?? null)) {
                $dilewati[$param['gantikan_kode']] = true;
            }
        }

        foreach ($params as $param) {
            $kode = $param['param_kode'];
            if ($param['tipe'] === 'REFERENSI' || isset($dilewati[$kode])) {
                continue;
            }

            $mentah = $nilai[$kode] ?? null;
            $baris  = ['nilai' => $mentah, 'skor' => null, 'label' => null, 'desc' => $param['param_desc']];

            if (!self::terisi($mentah)) {
                if (($param['wajib'] ?? '1') === '1') {
                    $hasil['lengkap']  = false;
                    $hasil['kurang'][] = $param['param_desc'];
                }
                $hasil['per'][$kode] = $baris;
                continue;
            }

            $cocok = $param['tipe'] === 'PILIHAN'
                ? self::cocokPilihan($param, (string) $mentah)
                : self::cocokAngka($param, $mentah, $nilai, $umurBulan);

            if ($cocok === null) {
                // Nilai ada tapi tak masuk rentang mana pun → master bolong / nilai salah ketik.
                $hasil['lengkap']  = false;
                $hasil['kurang'][] = $param['param_desc'] . ' (di luar rentang)';
                $hasil['per'][$kode] = $baris;
                continue;
            }

            $baris['skor']  = (int) $cocok['skor'];
            $baris['label'] = $cocok['pilihan_desc'] ?? EwsDefault::labelRentang($cocok['batas_bawah'], $cocok['batas_atas']);
            $hasil['per'][$kode] = $baris;
            $hasil['total'] += $baris['skor'];
            if ($baris['skor'] >= self::SKOR_MERAH) {
                $hasil['adaMerah'] = true;
            }
        }

        $respon = self::pilihRespon(self::responsVarian($master, $varian), $hasil['total'], $hasil['adaMerah']);
        if ($respon !== null) {
            $hasil['kategori']       = $respon['kategori'];
            $hasil['warna']          = $respon['warna'];
            $hasil['frekuensi']      = $respon['frekuensi'];
            $hasil['frekuensiMenit'] = isset($respon['frekuensi_menit']) ? (int) $respon['frekuensi_menit'] : null;
            $hasil['respon']         = $respon['respon'];
        }

        return $hasil;
    }

    /**
     * Baris respon yang cocok: (total di SKOR_MIN..SKOR_MAX) ATAU (PARAM_MERAH & ada
     * parameter merah). Bila lebih dari satu, URUTAN terbesar menang.
     */
    public static function pilihRespon(array $respons, int $total, bool $adaMerah): ?array
    {
        $terpilih = null;
        foreach ($respons as $respon) {
            $min = $respon['skor_min'];
            $max = $respon['skor_max'];
            $adaRentang  = $min !== null || $max !== null;
            $cocokTotal  = $adaRentang
                && ($min === null || $total >= (int) $min)
                && ($max === null || $total <= (int) $max);
            $cocokMerah  = ($respon['param_merah'] ?? '0') === '1' && $adaMerah;

            if (!$cocokTotal && !$cocokMerah) {
                continue;
            }
            if ($terpilih === null || (int) $respon['urutan'] > (int) $terpilih['urutan']) {
                $terpilih = $respon;
            }
        }

        return $terpilih;
    }

    /** Parameter varian yang aktif, urut URUTAN. */
    public static function paramsVarian(array $master, string $varian): array
    {
        $params = \array_filter(
            $master['params'] ?? [],
            fn(array $param) => $param['varian'] === $varian && ($param['active_status'] ?? '1') === '1',
        );
        \usort($params, fn($a, $b) => (int) $a['urutan'] <=> (int) $b['urutan']);

        return \array_values($params);
    }

    public static function responsVarian(array $master, string $varian): array
    {
        $respons = \array_filter($master['respons'] ?? [], fn(array $respon) => $respon['varian'] === $varian);
        \usort($respons, fn($a, $b) => (int) $a['urutan'] <=> (int) $b['urutan']);

        return \array_values($respons);
    }

    /**
     * Rentang acuan per usia (nadi/nafas normal PEWS) untuk umur tertentu.
     * Null bila tak ada baris usia yang cocok.
     */
    public static function acuanUsia(array $master, string $varian, string $kode, ?int $umurBulan): ?array
    {
        if ($umurBulan === null) {
            return null;
        }
        foreach (self::paramsVarian($master, $varian) as $param) {
            if ($param['param_kode'] !== $kode) {
                continue;
            }
            foreach ($param['rentang'] ?? [] as $rentang) {
                if (self::usiaCocok($rentang, $umurBulan)) {
                    return $rentang;
                }
            }
        }

        return null;
    }

    /**
     * Skor EWS TERAKHIR dari daftar entri Observasi Lanjutan (untuk badge di display
     * pasien). "Terakhir" = waktuPemeriksaan paling baru (input manual, urutan array
     * tidak dijamin kronologis); entri tanpa `ews` tersimpan dilewati. Null bila
     * belum ada skor.
     *
     * @return array{total:int, kategori:?string, warna:?string, frekuensi:?string, pantauUlang:?string,
     *               varian:string, waktu:string, lengkap:bool, terlambat:bool}|null
     */
    public static function terakhirDari(array $tandaVital, ?Carbon $sekarang = null): ?array
    {
        $terakhir = null;
        $maxTimestamp = null;
        foreach ($tandaVital as $entri) {
            if (!\is_array($entri) || !\is_array($entri['ews'] ?? null) || empty($entri['ews']['tersedia'])) {
                continue;
            }
            try {
                $timestamp = Carbon::createFromFormat('d/m/Y H:i:s', \trim((string) ($entri['waktuPemeriksaan'] ?? '')))->getTimestamp();
            } catch (Throwable) {
                $timestamp = null;
            }
            // >= : waktu sama / tak terparse → entri yang diinput belakangan menang (pola risiko jatuh)
            if ($terakhir === null || $timestamp === null || $maxTimestamp === null || $timestamp >= $maxTimestamp) {
                $terakhir = $entri;
                $maxTimestamp = $timestamp ?? $maxTimestamp;
            }
        }
        if ($terakhir === null) {
            return null;
        }

        $ews = $terakhir['ews'];
        $pantauUlang = $ews['pantauUlang'] ?? null;
        $terlambat = false;
        if ($pantauUlang) {
            try {
                $terlambat = Carbon::createFromFormat('d/m/Y H:i', $pantauUlang)
                    ->lessThan($sekarang ?? Carbon::now(config('app.timezone')));
            } catch (Throwable) {
                $terlambat = false;
            }
        }

        return [
            'total'       => (int) ($ews['total'] ?? 0),
            'kategori'    => $ews['kategori'] ?? null,
            'warna'       => $ews['warna'] ?? null,
            'frekuensi'   => $ews['frekuensi'] ?? null,
            'pantauUlang' => $pantauUlang,
            'varian'      => (string) ($ews['varian'] ?? $terakhir['ewsVarian'] ?? 'DEWASA'),
            'waktu'       => (string) ($terakhir['waktuPemeriksaan'] ?? ''),
            'lengkap'     => !empty($ews['lengkap']),
            'terlambat'   => $terlambat,
        ];
    }

    /** Label baris respon untuk tabel keterangan: "1 - 4", "≥ 7", "5 - 6 / 1 parameter merah". */
    public static function labelRespon(array $respon): string
    {
        $adaRentang = ($respon['skor_min'] ?? null) !== null || ($respon['skor_max'] ?? null) !== null;
        $bagian = [];
        if ($adaRentang) {
            $bagian[] = EwsDefault::labelRentang($respon['skor_min'], $respon['skor_max']);
        }
        if (($respon['param_merah'] ?? '0') === '1') {
            $bagian[] = '1 parameter merah';
        }

        return \implode(' / ', $bagian);
    }

    /** Kelas Tailwind untuk badge warna skor (dipakai layar & cetak). */
    public static function warnaKelas(?string $warna): string
    {
        return match ($warna) {
            'HIJAU'  => 'bg-success-tint text-success-deep dark:bg-green-900/30 dark:text-green-200',
            'KUNING' => 'bg-warning-tint text-warning-deep dark:bg-amber-900/30 dark:text-amber-200',
            'ORANYE' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-200',
            'MERAH'  => 'bg-error-tint text-error-deep dark:bg-red-900/30 dark:text-red-200',
            default  => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
        };
    }

    /** Kelas untuk sel skor per parameter (0 putih, 1 kuning, 2 oranye, 3 merah — warna NEWS2). */
    public static function skorKelas(?int $skor): string
    {
        return match (true) {
            $skor === null => 'text-muted-soft',
            $skor >= 3     => 'bg-error-tint text-error-deep dark:bg-red-900/30 dark:text-red-200',
            $skor === 2    => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-200',
            $skor === 1    => 'bg-warning-tint text-warning-deep dark:bg-amber-900/30 dark:text-amber-200',
            default        => 'text-body dark:text-gray-300',
        };
    }

    // ─────────────────────────────────────────────────────────────────────

    private static function terisi(mixed $nilai): bool
    {
        return $nilai !== null && $nilai !== '' && $nilai !== [];
    }

    private static function cocokPilihan(array $param, string $kode): ?array
    {
        foreach ($param['rentang'] ?? [] as $rentang) {
            if ((string) ($rentang['pilihan_kode'] ?? '') === $kode) {
                return $rentang;
            }
        }

        return null;
    }

    private static function cocokAngka(array $param, mixed $mentah, array $nilai, ?int $umurBulan): ?array
    {
        if (!\is_numeric($mentah)) {
            return null;
        }
        // Rentang master bertelinga 1-2 desimal (35.1-36.0); nilai diseragamkan dulu
        // supaya 36.04 tidak jatuh ke celah antara 36.0 dan 36.1.
        $angka = \round((float) $mentah, 1);

        $rentangs = $param['rentang'] ?? [];
        \usort($rentangs, fn($a, $b) => (int) $a['urutan'] <=> (int) $b['urutan']);

        foreach ($rentangs as $rentang) {
            if (!self::usiaCocok($rentang, $umurBulan)) {
                continue;
            }
            $syarat = $rentang['syarat'] ?? null;
            if ($syarat !== null && $syarat !== '' && !self::syaratTerpenuhi($syarat, $nilai)) {
                continue;
            }
            $bawah = $rentang['batas_bawah'];
            $atas  = $rentang['batas_atas'];
            if ($bawah !== null && $angka < (float) $bawah) {
                continue;
            }
            if ($atas !== null && $angka > (float) $atas) {
                continue;
            }

            return $rentang;
        }

        return null;
    }

    private static function usiaCocok(array $rentang, ?int $umurBulan): bool
    {
        $min = $rentang['usia_min_bln'] ?? null;
        $max = $rentang['usia_max_bln'] ?? null;
        if ($min === null && $max === null) {
            return true;
        }
        if ($umurBulan === null) {
            return false;
        }

        return ($min === null || $umurBulan >= (int) $min) && ($max === null || $umurBulan <= (int) $max);
    }

    /** SYARAT terpenuhi bila salah satu nilai pilihan yang diisi = kode syarat. */
    private static function syaratTerpenuhi(string $syarat, array $nilai): bool
    {
        foreach ($nilai as $isi) {
            if (\is_string($isi) && $isi === $syarat) {
                return true;
            }
        }

        return false;
    }
}
