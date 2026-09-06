<?php

namespace App\Support\Ews;

/**
 * Isi AWAL master EWS — satu sumber kebenaran untuk `php artisan ews:seed`,
 * unit test EwsSkor, dan dokumentasi.
 *
 * Acuan: formulir manual RSUD dr. Iskak Tulungagung rev 2024
 * (RM 93a Dewasa/NEWS2, RM 93b Anak/PEWS, RM 93c MEOWS, RM 93d Neonatus).
 * Bukan versi 5 warna 2018 yang masih beredar di materi pelatihan.
 *
 * Bentuk datanya meniru tabel Oracle (docs/ddl-ews.sql) supaya seed tinggal
 * memindahkan apa adanya:
 *   params[]  → RSMST_EWS_PARAMS  (+ 'rentang' => RSMST_EWS_RENTANGS)
 *   respons[] → RSMST_EWS_RESPONS
 *
 * Rentang: [bawah, atas, skor] untuk ANGKA (null = tak terbatas, inklusif);
 *          [kode, label, skor]  untuk PILIHAN;
 *          bisa ditambah 'syarat' (pilihan param lain) dan 'usia' => [minBln, maxBln].
 */
class EwsDefault
{
    public const VARIAN = [
        'DEWASA'   => 'EWS Dewasa (NEWS2)',
        'ANAK'     => 'EWS Anak (PEWS)',
        'NEONATUS' => 'EWS Neonatus',
        'MEOWS'    => 'EWS Obstetri (MEOWS)',
    ];

    /** Untuk siapa tiap varian — ditampilkan di pilihan dropdown Observasi Lanjutan. Selaras EwsSkor::varianUntukUmur(). */
    public const VARIAN_UNTUK = [
        'DEWASA'   => 'pasien 16 tahun ke atas',
        'ANAK'     => 'anak 29 hari s.d. 15 tahun',
        'NEONATUS' => 'bayi baru lahir 0-28 hari',
        'MEOWS'    => 'ibu hamil / bersalin / nifas',
    ];

    /** Label opsi dropdown: "EWS Anak (PEWS) - untuk anak 29 hari s.d. 15 tahun". */
    public static function labelVarianLengkap(string $kode): string
    {
        $label = self::VARIAN[$kode] ?? $kode;
        $untuk = self::VARIAN_UNTUK[$kode] ?? null;

        return $untuk ? $label . ' - untuk ' . $untuk : $label;
    }

    /** Kesadaran ACVPU — dipakai DEWASA & MEOWS dengan kode sama supaya input seragam. */
    private const KESADARAN = [
        ['A', 'A: Sadar penuh (Alert)', 0],
        ['C', 'C: Bingung, delirium, disorientasi (Confusion)', 3],
        ['V', 'V: Reaksi terhadap perintah / suara (Verbal)', 3],
        ['P', 'P: Reaksi terhadap nyeri (Pain)', 3],
        ['U', 'U: Tidak bereaksi (Unresponsive)', 3],
    ];

    private const OKSIGEN = [
        ['ROOM_AIR', 'Room air (tanpa O2)', 0],
        ['O2', 'O2 tambahan (lpm)', 2],
    ];

    public static function params(): array
    {
        return array_merge(self::dewasa(), self::anak(), self::neonatus(), self::meows());
    }

    public static function respons(): array
    {
        return [
            // ── DEWASA (NEWS2) ── urutan dari ringan ke berat; PARAM_MERAH menyisip di antara 1-4 dan 5-6.
            self::respon('DEWASA', 1, 0, 0, '0', 'Rendah', 'PUTIH', 'Minimal tiap 12 jam', 720,
                'Rawat inap biasa dan monitoring EWS sesuai frekuensi.'),
            self::respon('DEWASA', 2, 1, 4, '0', 'Rendah', 'HIJAU', 'Minimal tiap 6 jam', 360,
                'Rawat inap biasa dan monitoring EWS sesuai frekuensi.'),
            self::respon('DEWASA', 3, null, null, '1', 'Rendah - Sedang', 'KUNING', 'Minimal tiap 1 jam', 60,
                'Skor 3 (kode merah) pada salah satu parameter: PJ Shift / Katim konsul ke dokter jaga ruangan, dokter jaga ruangan konsul ke DPJP. Selanjutnya DPJP meninjau dan memutuskan kebutuhan eskalasi perawatan.'),
            self::respon('DEWASA', 4, 5, 6, '0', 'Sedang', 'ORANYE', 'Minimal tiap 1 jam', 60,
                'PJ Shift / Katim segera konsul ke dokter jaga ruangan, dokter jaga ruangan konsul ke DPJP untuk tindak lanjut perawatan intensif (HCU/ICU) dan/atau konsultasi dokter anestesiologi.'),
            self::respon('DEWASA', 5, 7, null, '0', 'Tinggi', 'MERAH', 'Minimal tiap 1 jam', 60,
                'PJ Shift / Katim segera konsul ke dokter jaga ruangan, dokter jaga ruangan konsul ke DPJP untuk tindak lanjut perawatan intensif (ICU) dan/atau konsultasi dokter anestesiologi. Bila henti nafas/jantung: RJP + aktivasi code blue.'),

            // ── ANAK (PEWS) ──
            self::respon('ANAK', 1, 0, 2, '0', 'Rendah', 'HIJAU', 'Minimal tiap 4 jam', 240,
                'Pasien dalam keadaan stabil, lakukan evaluasi dan skoring ulang setiap 4 jam.'),
            self::respon('ANAK', 2, 3, 4, '0', 'Sedang', 'ORANYE', 'Minimal tiap 2 jam', 120,
                'Ada perubahan kondisi pasien: evaluasi ulang setiap 2 jam atau lebih cepat. Lapor ke penanggung jawab tim jaga / dokter jaga, konsultasi ke DPJP.'),
            self::respon('ANAK', 3, 5, null, '0', 'Tinggi', 'MERAH', 'Minimal tiap 1 jam', 60,
                'Ada perubahan signifikan: monitoring setiap 1 jam atau lebih cepat, konsultasi ke DPJP, aktivasi tim medis reaksi cepat. Bila henti jantung: RJP + aktivasi code blue.'),

            // ── NEONATUS ──
            self::respon('NEONATUS', 1, 0, 2, '0', 'Rendah', 'HIJAU', 'Minimal tiap 4 jam', 240,
                'Pasien dalam keadaan stabil, evaluasi setiap 4 jam.'),
            self::respon('NEONATUS', 2, 3, 4, '0', 'Sedang', 'KUNING', 'Minimal tiap 2 jam', 120,
                'Ada perubahan kondisi: evaluasi ulang setiap 2 jam, lapor dokter jaga, konsul DPJP.'),
            self::respon('NEONATUS', 3, 5, null, '0', 'Tinggi', 'MERAH', 'Minimal tiap 1 jam', 60,
                'Ada perubahan yang signifikan: monitoring setiap 1 jam, lapor dokter jaga, kolaborasi dengan DPJP untuk perawatan intensif di NICU. Bila henti jantung: RJP.'),

            // ── MEOWS ──
            self::respon('MEOWS', 1, 0, 0, '0', 'Rendah', 'PUTIH', 'Minimal tiap 12 jam', 720,
                'Bidan monitoring sesuai frekuensi.'),
            self::respon('MEOWS', 2, 1, 4, '0', 'Rendah', 'KUNING', 'Minimal tiap 4 jam', 240,
                'Bidan PJ Shift / Katim konsul dokter jaga, dokter jaga konsul DPJP, monitoring sesuai frekuensi.'),
            self::respon('MEOWS', 3, 5, 6, '1', 'Sedang', 'ORANYE', 'Minimal tiap 1 jam', 60,
                'Skor 5-6 atau salah satu parameter merah: Bidan PJ Shift / Katim konsul dokter jaga, dokter jaga konsul DPJP untuk tatalaksana lanjutan dan/atau perawatan intensif (HCU/ICU) dan/atau konsul dokter anestesi. Tingkatkan monitoring sesuai frekuensi.'),
            self::respon('MEOWS', 4, 7, null, '0', 'Tinggi', 'MERAH', 'Minimal tiap 15 menit', 15,
                'Bidan PJ Shift / Katim konsul dokter jaga, dokter jaga konsul DPJP untuk tatalaksana lanjutan dan/atau perawatan intensif (HCU/ICU) dan/atau konsul dokter anestesi. Tingkatkan monitoring sesuai frekuensi. Bila henti nafas/jantung: RJP + aktivasi code blue.'),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // DEWASA — NEWS2 (RM 93a)
    // ─────────────────────────────────────────────────────────────────────
    private static function dewasa(): array
    {
        $v = 'DEWASA';

        return [
            self::angka($v, 1, 'frekuensiNafas', 'Pernafasan', 'x/mnt', [
                [null, 8, 3], [9, 11, 1], [12, 20, 0], [21, 24, 2], [25, null, 3],
            ]),
            self::angka($v, 2, 'spo2', 'SpO2 skala 1', '%', [
                [null, 91, 3], [92, 93, 2], [94, 95, 1], [96, null, 0],
            ]),
            self::angka($v, 3, 'spo2Skala2', 'SpO2 skala 2 (gagal nafas tipe 2, mis. PPOK)', '%', [
                [null, 83, 3], [84, 85, 2], [86, 87, 1], [88, 92, 0],
                [93, null, 0, 'syarat' => 'ROOM_AIR'],
                [93, 94, 1, 'syarat' => 'O2'],
                [95, 96, 2, 'syarat' => 'O2'],
                [97, null, 3, 'syarat' => 'O2'],
            ], wajib: '0', gantikan: 'spo2'),
            self::pilihan($v, 4, 'oksigen', 'Penggunaan oksigen', self::OKSIGEN),
            self::angka($v, 5, 'sistolik', 'Tekanan darah sistolik', 'mmHg', [
                [null, 90, 3], [91, 100, 2], [101, 110, 1], [111, 219, 0], [220, null, 3],
            ]),
            self::angka($v, 6, 'frekuensiNadi', 'Denyut nadi', 'x/mnt', [
                [null, 40, 3], [41, 50, 1], [51, 90, 0], [91, 110, 1], [111, 130, 2], [131, null, 3],
            ]),
            self::pilihan($v, 7, 'kesadaran', 'Tingkat kesadaran (ACVPU)', self::KESADARAN),
            self::angka($v, 8, 'suhu', 'Suhu', '°C', [
                [null, 35.0, 3], [35.1, 36.0, 1], [36.1, 38.0, 0], [38.1, 39.0, 1], [39.1, null, 2],
            ]),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // ANAK — PEWS (RM 93b), 29 hari s.d. < 16 tahun
    // Yang diskor tiga pilihan deskriptif; nadi & nafas dicatat sebagai angka
    // dan dibandingkan ke tabel acuan per usia (REFERENSI) — tidak ikut total.
    // ─────────────────────────────────────────────────────────────────────
    private static function anak(): array
    {
        $v = 'ANAK';

        return [
            self::pilihan($v, 1, 'keadaanUmum', 'Keadaan umum', [
                ['INTERAKSI_BIASA', 'Interaksi biasa', 0],
                ['SOMNOLEN', 'Somnolen', 1],
                ['IRITABEL', 'Iritabel', 2],
                ['LETARGI', 'Letargi, gelisah, penurunan respon terhadap nyeri', 3],
            ]),
            self::pilihan($v, 2, 'kardiovaskular', 'Kardiovaskular', [
                ['NORMAL', 'Tidak sianosis, atau pengisian kapiler < 2 detik', 0],
                ['PUCAT', 'Tampak pucat, atau pengisian kapiler 2 detik', 1],
                ['SIANOTIK', 'Tampak sianotik, atau pengisian kapiler > 2 detik, atau takikardi > 20x di atas nadi normal usia', 2],
                ['MOTLET', 'Sianotik dan motlet, atau pengisian kapiler > 5 detik, atau takikardi > 30x di atas nadi normal usia', 3],
            ]),
            self::pilihan($v, 3, 'respirasi', 'Respirasi', [
                ['NORMAL', 'Dalam batas normal, tidak ada retraksi', 0],
                ['TAKIPNEA_10', 'Takipnea > 10x di atas RR normal usia, atau otot bantu napas, atau FiO2 > 30% (~ 3 L/mnt nasal kanul)', 1],
                ['TAKIPNEA_20', 'Takipnea > 20x di atas RR normal usia, atau ada retraksi, atau FiO2 > 40% (~ 6 L/mnt simple mask)', 2],
                ['TAKIPNEA_RETRAKSI', 'Takipnea >= 5x di atas RR normal usia dengan retraksi, atau merintih, atau FiO2 > 50% (~ 8 L/mnt simple mask)', 3],
            ]),
            self::referensi($v, 4, 'nadiNormal', 'Nadi normal saat istirahat (acuan usia)', 'x/mnt', [
                [100, 180, 0, 'usia' => [0, 1],     'label' => 'Neonatus 0-1 bulan'],
                [100, 180, 0, 'usia' => [2, 12],    'label' => 'Bayi 1-12 bulan'],
                [70, 110, 0,  'usia' => [13, 47],   'label' => 'Balita 13-36 bulan'],
                [70, 110, 0,  'usia' => [48, 83],   'label' => 'Pra-sekolah 4-6 tahun'],
                [70, 110, 0,  'usia' => [84, 155],  'label' => 'Sekolah 7-12 tahun'],
                [55, 90, 0,   'usia' => [156, 239], 'label' => 'Remaja 13-19 tahun'],
            ]),
            self::referensi($v, 5, 'nafasNormal', 'Nafas normal saat istirahat (acuan usia)', 'x/mnt', [
                [40, 60, 0, 'usia' => [0, 1],     'label' => 'Neonatus 0-1 bulan'],
                [35, 40, 0, 'usia' => [2, 12],    'label' => 'Bayi 1-12 bulan'],
                [25, 30, 0, 'usia' => [13, 47],   'label' => 'Balita 13-36 bulan'],
                [21, 23, 0, 'usia' => [48, 83],   'label' => 'Pra-sekolah 4-6 tahun'],
                [19, 21, 0, 'usia' => [84, 155],  'label' => 'Sekolah 7-12 tahun'],
                [16, 18, 0, 'usia' => [156, 239], 'label' => 'Remaja 13-19 tahun'],
            ]),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // NEONATUS (RM 93d), 0-28 hari
    // ─────────────────────────────────────────────────────────────────────
    private static function neonatus(): array
    {
        $v = 'NEONATUS';

        return [
            self::pilihan($v, 1, 'keadaanUmum', 'Keadaan umum', [
                ['MENANGIS_KUAT', 'Menangis kuat, gerak aktif', 0],
                ['MENANGIS_LEMAH', 'Menangis lemah, gerak kurang aktif', 1],
                ['GELISAH', 'Gelisah', 2],
                ['LETARGI', 'Letargi, merintih', 3],
            ]),
            self::pilihan($v, 2, 'kardiovaskular', 'Kardiovaskular', [
                ['NORMAL', 'Tidak sianosis, atau CRT < 2 detik', 0],
                ['PUCAT', 'Tampak pucat, atau CRT 2 detik', 1],
                ['CRT_LAMBAT', 'Tidak sianosis, atau CRT > 2 detik', 2],
                ['SIANOSIS', 'Sianosis dan motlet, atau CRT > 5 detik', 3],
            ]),
            self::pilihan($v, 3, 'respirasi', 'Respirasi', [
                ['NORMAL', 'Tidak ada retraksi dada, RR 30-60 x/mnt', 0],
                ['TAKIPNEA_70', 'Takipnea > 70 x/mnt, atau menggunakan FiO2 > 30%', 1],
                ['TAKIPNEA_80', 'Takipnea > 80 x/mnt', 2],
                ['RETRAKSI', 'Retraksi dada', 3],
            ]),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // MEOWS — obstetri (RM 93c)
    // ─────────────────────────────────────────────────────────────────────
    private static function meows(): array
    {
        $v = 'MEOWS';

        return [
            self::angka($v, 1, 'frekuensiNafas', 'Respirasi', 'x/mnt', [
                [null, 11, 3], [12, 20, 0], [21, 25, 2], [26, null, 3],
            ]),
            self::angka($v, 2, 'spo2', 'Saturasi O2', '%', [
                [null, 91, 3], [92, 93, 2], [94, 95, 1], [96, null, 0],
            ]),
            self::pilihan($v, 3, 'oksigen', 'Suplemen O2', self::OKSIGEN),
            self::angka($v, 4, 'suhu', 'Temperatur', '°C', [
                [null, 35.0, 3], [35.1, 36.0, 1], [36.1, 37.0, 0], [37.1, 38.0, 1], [38.1, 39.0, 2], [39.1, null, 3],
            ]),
            self::angka($v, 5, 'sistolik', 'Tekanan darah sistolik', 'mmHg', [
                [null, 89, 3], [90, 139, 0], [140, 149, 1], [150, 160, 2], [161, null, 3],
            ]),
            self::angka($v, 6, 'distolik', 'Tekanan darah diastolik', 'mmHg', [
                [null, 59, 1], [60, 99, 0], [100, 110, 2], [111, null, 3],
            ]),
            self::angka($v, 7, 'frekuensiNadi', 'Nadi', 'x/mnt', [
                [null, 49, 3], [50, 59, 1], [60, 99, 0], [100, 109, 1], [110, 120, 2], [121, null, 3],
            ]),
            self::pilihan($v, 8, 'kesadaran', 'Kesadaran (ACVPU)', self::KESADARAN),
            self::angka($v, 9, 'nyeri', 'Skala nyeri (0-10)', '', [
                [0, 3, 0], [4, 6, 2], [7, 10, 3],
            ]),
            self::pilihan($v, 10, 'perdarahan', 'Perdarahan pervaginam', [
                ['TIDAK', 'Tidak', 0],
                ['YA', 'Ya', 3],
            ]),
            self::pilihan($v, 11, 'lochea', 'Lochea', [
                ['SESUAI', 'Sesuai', 0],
                ['HPP', 'HPP (perdarahan pasca persalinan)', 3],
            ], wajib: '0'),
            self::pilihan($v, 12, 'produksiUrine', 'Produksi urine', [
                ['NORMAL', '0,5 - 1,5 cc/kgBB/jam', 0],
                ['RENDAH', '< 0,5 cc/kgBB/jam', 1],
            ], wajib: '0'),
            self::pilihan($v, 13, 'proteinUrine', 'Protein urine', [
                ['NEGATIF', 'Negatif / trace / positif 1', 0],
                ['POSITIF_2', 'Positif 2 atau lebih', 1],
            ], wajib: '0'),
            self::angka($v, 14, 'djj', 'Denyut jantung janin (DJJ)', 'x/mnt', [
                [null, 119, 3], [120, 160, 0], [161, null, 1],
            ], wajib: '0'),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Pembangun baris
    // ─────────────────────────────────────────────────────────────────────
    private static function angka(string $varian, int $urutan, string $kode, string $desc, string $satuan, array $rentang, string $wajib = '1', ?string $gantikan = null): array
    {
        return self::param($varian, $urutan, $kode, $desc, 'ANGKA', $satuan, $wajib, $gantikan, array_map(
            fn(array $r, int $i) => [
                'urutan'       => $i + 1,
                'batas_bawah'  => $r[0],
                'batas_atas'   => $r[1],
                'pilihan_kode' => null,
                'pilihan_desc' => $r['label'] ?? self::labelRentang($r[0], $r[1]),
                'syarat'       => $r['syarat'] ?? null,
                'usia_min_bln' => $r['usia'][0] ?? null,
                'usia_max_bln' => $r['usia'][1] ?? null,
                'skor'         => $r[2],
            ],
            $rentang,
            array_keys($rentang),
        ));
    }

    private static function referensi(string $varian, int $urutan, string $kode, string $desc, string $satuan, array $rentang): array
    {
        $param = self::angka($varian, $urutan, $kode, $desc, $satuan, $rentang, wajib: '0');
        $param['tipe'] = 'REFERENSI';

        return $param;
    }

    private static function pilihan(string $varian, int $urutan, string $kode, string $desc, array $pilihan, string $wajib = '1'): array
    {
        return self::param($varian, $urutan, $kode, $desc, 'PILIHAN', null, $wajib, null, array_map(
            fn(array $p, int $i) => [
                'urutan'       => $i + 1,
                'batas_bawah'  => null,
                'batas_atas'   => null,
                'pilihan_kode' => $p[0],
                'pilihan_desc' => $p[1],
                'syarat'       => null,
                'usia_min_bln' => null,
                'usia_max_bln' => null,
                'skor'         => $p[2],
            ],
            $pilihan,
            array_keys($pilihan),
        ));
    }

    private static function param(string $varian, int $urutan, string $kode, string $desc, string $tipe, ?string $satuan, string $wajib, ?string $gantikan, array $rentang): array
    {
        return [
            'varian'        => $varian,
            'param_kode'    => $kode,
            'param_desc'    => $desc,
            'tipe'          => $tipe,
            'satuan'        => $satuan,
            'urutan'        => $urutan,
            'wajib'         => $wajib,
            'gantikan_kode' => $gantikan,
            'active_status' => '1',
            'rentang'       => $rentang,
        ];
    }

    private static function respon(string $varian, int $urutan, ?int $min, ?int $max, string $merah, string $kategori, string $warna, string $frekuensi, ?int $menit, string $respon): array
    {
        return [
            'varian'          => $varian,
            'urutan'          => $urutan,
            'skor_min'        => $min,
            'skor_max'        => $max,
            'param_merah'     => $merah,
            'kategori'        => $kategori,
            'warna'           => $warna,
            'frekuensi'       => $frekuensi,
            'frekuensi_menit' => $menit,
            'respon'          => $respon,
        ];
    }

    /**
     * Label baku rentang angka: "<= 8", "9 - 11", ">= 25".
     * Sengaja ASCII: Oracle di sini WE8ISO8859P1 — karakter ≤ ≥ ₂ ≈ tersimpan jadi "¿".
     */
    public static function labelRentang(int|float|null $bawah, int|float|null $atas): string
    {
        if ($bawah === null && $atas === null) {
            return 'semua nilai';
        }
        if ($bawah === null) {
            return '<= ' . $atas;
        }
        if ($atas === null) {
            return '>= ' . $bawah;
        }
        if ($bawah == $atas) {
            return (string) $bawah;
        }

        return $bawah . ' - ' . $atas;
    }
}
