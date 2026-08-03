<?php

namespace App\Support;

/**
 * Sumber tunggal definisi SKALA NYERI: sasaran populasi, rentang skor,
 * item penilaian, interpretasi, dan tata laksana berjenjang.
 *
 * Acuan: materi IHT PAP "Manajemen Nyeri" (dr. M. Rudita, Sp.An-TI, 30/07/2026)
 * — pemetaan skala per populasi + tata laksana per rentang skor.
 *
 * Dipakai oleh form Penilaian Nyeri EMR (RI; menyusul RJ/UGD) supaya definisi
 * skala tidak diduplikasi per jalur. Kode skala ('NRS','VAS',...) DISIMPAN ke
 * JSON EMR; label & interpretasi diturunkan saat render, tidak ikut tersimpan.
 *
 * Tipe skala:
 *   'angka' → skor diketik langsung (NRS)
 *   'pilih' → satu tombol dipilih dari daftar nilai (VAS, Wong-Baker)
 *   'item'  → skor = jumlah item yang dipilih (FLACC, NIPS, BPS, CPOT, PAINAD)
 */
class NyeriOptions
{
    /** Tingkat nyeri baku — dipakai untuk warna badge & penentuan tata laksana. */
    public const TINGKAT_TIDAK = 'tidak';
    public const TINGKAT_RINGAN = 'ringan';
    public const TINGKAT_SEDANG = 'sedang';
    public const TINGKAT_BERAT = 'berat';
    public const TINGKAT_SANGAT_BERAT = 'sangatBerat';

    /**
     * Definisi skala. Urutan = urutan tampil di dropdown (dewasa → anak → khusus).
     */
    public const SKALA = [
        'NRS' => [
            'nama' => 'Numeric Rating Scale',
            'sasaran' => 'Dewasa & anak > 8 th, sadar dan mampu menyebutkan sendiri nyerinya',
            'usiaMin' => 8,
            'usiaMax' => null,
            'tipe' => 'angka',
            'min' => 0,
            'max' => 10,
            'catatan' => 'Pasien menyebut sendiri angka 0–10. Bila pasien tidak dapat berkomunikasi, pakai skala perilaku (FLACC/BPS/CPOT/PAINAD).',
            'interpretasi' => [
                [0, 0, 'Tidak Nyeri', self::TINGKAT_TIDAK],
                [1, 3, 'Nyeri Ringan', self::TINGKAT_RINGAN],
                [4, 6, 'Nyeri Sedang', self::TINGKAT_SEDANG],
                [7, 10, 'Nyeri Berat', self::TINGKAT_BERAT],
            ],
        ],

        'VAS' => [
            'nama' => 'Visual Analogue Scale',
            'sasaran' => 'Dewasa & anak > 8 th, sadar dan mampu menunjuk pada garis/angka',
            'usiaMin' => 8,
            'usiaMax' => null,
            'tipe' => 'pilih',
            'min' => 0,
            'max' => 10,
            'nilai' => [
                [0, '0'], [1, '1'], [2, '2'], [3, '3'], [4, '4'], [5, '5'],
                [6, '6'], [7, '7'], [8, '8'], [9, '9'], [10, '10'],
            ],
            'catatan' => 'Pasien menunjuk titik pada garis 0–10 sesuai nyeri yang dirasakan.',
            'interpretasi' => [
                [0, 0, 'Tidak Nyeri', self::TINGKAT_TIDAK],
                [1, 3, 'Nyeri Ringan', self::TINGKAT_RINGAN],
                [4, 6, 'Nyeri Sedang', self::TINGKAT_SEDANG],
                [7, 10, 'Nyeri Berat', self::TINGKAT_BERAT],
            ],
        ],

        'WBS' => [
            'nama' => 'Wong-Baker FACES Pain Rating Scale',
            'sasaran' => 'Anak 3–8 th (juga dewasa yang kesulitan dengan angka)',
            'usiaMin' => 3,
            'usiaMax' => 8,
            'tipe' => 'pilih',
            'min' => 0,
            'max' => 10,
            'nilai' => [
                [0, '0 — Tidak nyeri'],
                [2, '2 — Sedikit nyeri'],
                [4, '4 — Agak mengganggu'],
                [6, '6 — Mengganggu aktivitas'],
                [8, '8 — Sangat mengganggu'],
                [10, '10 — Tidak tertahankan'],
            ],
            'catatan' => 'Anak menunjuk gambar wajah yang paling menggambarkan rasa nyerinya. Skor hanya bernilai genap 0–10.',
            'interpretasi' => [
                [0, 0, 'Tidak Nyeri', self::TINGKAT_TIDAK],
                [1, 3, 'Nyeri Ringan', self::TINGKAT_RINGAN],
                [4, 6, 'Nyeri Sedang', self::TINGKAT_SEDANG],
                [7, 10, 'Nyeri Berat', self::TINGKAT_BERAT],
            ],
        ],

        'FLACC' => [
            'nama' => 'Face, Legs, Activity, Cry, Consolability',
            'sasaran' => 'Bayi s/d anak 3 th, atau anak yang belum mampu melaporkan nyeri',
            'usiaMin' => 0,
            'usiaMax' => 3,
            'tipe' => 'item',
            'min' => 0,
            'max' => 10,
            'catatan' => 'Lima aspek perilaku, masing-masing 0–2. Semua aspek wajib dinilai.',
            'items' => [
                'face' => [
                    'label' => 'Face (wajah)',
                    'opsi' => [
                        [0, 'Tidak ada ekspresi tertentu atau tersenyum'],
                        [1, 'Sesekali meringis / mengerutkan kening, menarik diri, tidak tertarik'],
                        [2, 'Sering sampai konstan mengerutkan kening, rahang tertutup, dagu gemetar'],
                    ],
                ],
                'legs' => [
                    'label' => 'Legs (kaki)',
                    'opsi' => [
                        [0, 'Posisi normal atau santai'],
                        [1, 'Cemas, gelisah, tegang'],
                        [2, 'Menendang atau menarik kaki'],
                    ],
                ],
                'activity' => [
                    'label' => 'Activity (aktivitas)',
                    'opsi' => [
                        [0, 'Berbaring tenang, posisi normal, bergerak dengan mudah'],
                        [1, 'Menggeliat, mondar-mandir, tegang'],
                        [2, 'Melengkung, kaku, atau menyentak'],
                    ],
                ],
                'cry' => [
                    'label' => 'Cry (tangis)',
                    'opsi' => [
                        [0, 'Tidak ada teriakan (terjaga atau tertidur)'],
                        [1, 'Mengerang atau merintih, sesekali mengeluh'],
                        [2, 'Menangis terus, berteriak atau isak tangis, sering mengeluh'],
                    ],
                ],
                'consolability' => [
                    'label' => 'Consolability (dapat ditenangkan)',
                    'opsi' => [
                        [0, 'Puas/senang, santai'],
                        [1, 'Sesekali diyakinkan dengan sentuhan, pelukan, diajak bicara, dialihkan'],
                        [2, 'Sulit untuk dihibur atau dibuat nyaman'],
                    ],
                ],
            ],
            'interpretasi' => [
                [0, 0, 'Tidak Nyeri (santai & nyaman)', self::TINGKAT_TIDAK],
                [1, 3, 'Nyeri Ringan', self::TINGKAT_RINGAN],
                [4, 6, 'Nyeri Sedang', self::TINGKAT_SEDANG],
                [7, 10, 'Nyeri Berat', self::TINGKAT_BERAT],
            ],
        ],

        'NIPS' => [
            'nama' => 'Neonatal Infant Pain Scale',
            'sasaran' => 'Neonatus (bayi baru lahir s/d 1 bulan)',
            'usiaMin' => 0,
            'usiaMax' => 0,
            'tipe' => 'item',
            'min' => 0,
            'max' => 7,
            'catatan' => 'Rentang skor 0–7 (bukan 0–10). Tangisan bernilai 0–2, aspek lain 0–1. Bayi terintubasi: beri skor tangisan bila tampak menangis tanpa suara.',
            // Slide IHT hanya memuat 5 aspek (tanpa Lengan) sehingga total maksimalnya 6,
            // padahal interpretasinya sampai 7. Aspek Lengan dikembalikan sesuai NIPS asli
            // (Lawrence et al., 1993) supaya total maksimal 7 dan interpretasi 6–7 terpakai.
            'items' => [
                'ekspresiWajah' => [
                    'label' => 'Ekspresi wajah',
                    'opsi' => [
                        [0, 'Otot relaks — wajah tenang, ekspresi netral'],
                        [1, 'Meringis — otot wajah tegang, alis berkerut'],
                    ],
                ],
                'tangisan' => [
                    'label' => 'Tangisan',
                    'opsi' => [
                        [0, 'Tidak menangis — tenang'],
                        [1, 'Merengek — merengang lemah intermiten'],
                        [2, 'Menangis keras — melengking terus-menerus'],
                    ],
                ],
                'polaNapas' => [
                    'label' => 'Pola napas',
                    'opsi' => [
                        [0, 'Relaks — bernapas biasa'],
                        [1, 'Perubahan napas — tarikan ireguler, lebih cepat, menahan napas, tersedak'],
                    ],
                ],
                'lengan' => [
                    'label' => 'Lengan',
                    'opsi' => [
                        [0, 'Relaks — tidak ada kekakuan otot, gerakan lengan biasa'],
                        [1, 'Fleksi / ekstensi — tegang kaku, lengan lurus atau menekuk cepat'],
                    ],
                ],
                'tungkai' => [
                    'label' => 'Tungkai',
                    'opsi' => [
                        [0, 'Relaks — tidak ada kekakuan otot, gerakan tungkai biasa'],
                        [1, 'Fleksi / ekstensi — tegang kaku'],
                    ],
                ],
                'tingkatKesadaran' => [
                    'label' => 'Tingkat kesadaran',
                    'opsi' => [
                        [0, 'Tidur / bangun — tenang, tidur lelap atau bangun'],
                        [1, 'Gelisah — sadar atau gelisah'],
                    ],
                ],
            ],
            'interpretasi' => [
                [0, 0, 'Tidak Nyeri — tidak perlu intervensi', self::TINGKAT_TIDAK],
                [1, 3, 'Nyeri Ringan — intervensi non-farmakologis', self::TINGKAT_RINGAN],
                [4, 5, 'Nyeri Sedang — terapi analgetik non-opioid', self::TINGKAT_SEDANG],
                [6, 7, 'Nyeri Berat — terapi opioid', self::TINGKAT_BERAT],
            ],
        ],

        'BPS' => [
            'nama' => 'Behavioral Pain Scale',
            'sasaran' => 'Pasien terpasang ventilator / tersedasi & tidak dapat berkomunikasi',
            'usiaMin' => null,
            'usiaMax' => null,
            'tipe' => 'item',
            'min' => 3,
            'max' => 12,
            'catatan' => 'Rentang skor 3–12 — skor terendah 3, BUKAN 0, karena tiap aspek bernilai 1–4.',
            'items' => [
                'ekspresiWajah' => [
                    'label' => 'Ekspresi wajah',
                    'opsi' => [
                        [1, 'Relaks'],
                        [2, 'Sebagian menegang (mis. alis menurun)'],
                        [3, 'Sepenuhnya menegang (mis. kelopak mata menutup)'],
                        [4, 'Meringis'],
                    ],
                ],
                'ekstremitasAtas' => [
                    'label' => 'Ekstremitas atas',
                    'opsi' => [
                        [1, 'Tidak ada gerakan'],
                        [2, 'Menekuk sebagian'],
                        [3, 'Menekuk penuh dengan fleksi jari'],
                        [4, 'Retraksi permanen'],
                    ],
                ],
                'toleransiVentilasi' => [
                    'label' => 'Toleransi terhadap ventilasi mekanik',
                    'opsi' => [
                        [1, 'Toleran terhadap gerakan ventilator'],
                        [2, 'Batuk tetapi toleran sebagian besar waktu'],
                        [3, 'Melawan ventilator'],
                        [4, 'Tidak dapat mengendalikan ventilasi'],
                    ],
                ],
            ],
            'interpretasi' => [
                [3, 4, 'Nyeri Ringan', self::TINGKAT_RINGAN],
                [5, 7, 'Nyeri Sedang', self::TINGKAT_SEDANG],
                [8, 9, 'Nyeri Berat', self::TINGKAT_BERAT],
                [10, 12, 'Nyeri Sangat Berat', self::TINGKAT_SANGAT_BERAT],
            ],
        ],

        'CPOT' => [
            'nama' => 'Critical-Care Pain Observation Tool',
            'sasaran' => 'Pasien perawatan intensif (ICU), terintubasi maupun tidak',
            'usiaMin' => null,
            'usiaMax' => null,
            'tipe' => 'item',
            'min' => 0,
            'max' => 8,
            'catatan' => 'Rentang skor 0–8. Aspek ke-4 dinilai sebagai kepatuhan ventilator (pasien terintubasi) ATAU vokalisasi (pasien tanpa intubasi) — pilih salah satu sesuai kondisi. Skor > 2 menandakan nyeri bermakna.',
            // Slide IHT menulis interpretasi 0 / 1–3 / 4–6 / 7–10 untuk CPOT, tersalin dari
            // skala 0–10 — padahal skor maksimal CPOT hanya 8 sehingga "berat" tak pernah
            // tercapai. Dipakai rentang 0–8 (keputusan user, 30/07/2026) dengan ambang
            // sesuai literatur: skor > 2 = nyeri bermakna. JANGAN dikembalikan ke 7–10.
            'items' => [
                'ekspresiWajah' => [
                    'label' => 'Ekspresi wajah',
                    'opsi' => [
                        [0, 'Relaks, netral — tidak tampak ketegangan otot'],
                        [1, 'Tegang — mengerutkan dahi, alis menurun, orbita menyempit'],
                        [2, 'Meringis — seluruh gerakan di atas ditambah kelopak mata menutup rapat'],
                    ],
                ],
                'gerakanTubuh' => [
                    'label' => 'Gerakan tubuh',
                    'opsi' => [
                        [0, 'Tidak ada gerakan (belum tentu berarti tanpa nyeri)'],
                        [1, 'Perlindungan — gerakan lambat & hati-hati, menyentuh/menggosok area nyeri'],
                        [2, 'Gelisah — menarik selang, mencoba duduk, meronta, tidak menuruti perintah'],
                    ],
                ],
                'keteganganOtot' => [
                    'label' => 'Ketegangan otot (fleksi-ekstensi pasif lengan)',
                    'opsi' => [
                        [0, 'Relaks — tidak ada tahanan terhadap gerakan pasif'],
                        [1, 'Tegang, kaku — ada tahanan terhadap gerakan pasif'],
                        [2, 'Sangat tegang atau kaku — tahanan kuat, gerakan pasif tidak dapat diselesaikan'],
                    ],
                ],
                'ventilasiAtauVokalisasi' => [
                    'label' => 'Kepatuhan ventilator (terintubasi) / vokalisasi (tanpa intubasi)',
                    'opsi' => [
                        [0, 'Toleran ventilator — alarm tidak aktif / bicara nada normal atau tanpa suara'],
                        [1, 'Batuk tetapi masih toleran / mendesah, mengerang'],
                        [2, 'Melawan ventilator, alarm sering aktif / menangis, terisak'],
                    ],
                ],
            ],
            'interpretasi' => [
                [0, 0, 'Tidak Nyeri', self::TINGKAT_TIDAK],
                [1, 2, 'Nyeri Ringan', self::TINGKAT_RINGAN],
                [3, 5, 'Nyeri Sedang', self::TINGKAT_SEDANG],
                [6, 8, 'Nyeri Berat', self::TINGKAT_BERAT],
            ],
        ],

        'PAINAD' => [
            'nama' => 'Pain Assessment in Advanced Dementia',
            'sasaran' => 'Pasien geriatri dengan demensia lanjut / gangguan kognitif',
            'usiaMin' => 60,
            'usiaMax' => null,
            'tipe' => 'item',
            'min' => 0,
            'max' => 10,
            'catatan' => 'Dinilai selama 5 menit observasi, idealnya saat pasien beraktivitas/dimobilisasi.',
            'items' => [
                'pernapasan' => [
                    'label' => 'Pernapasan (terlepas dari vokalisasi)',
                    'opsi' => [
                        [0, 'Normal'],
                        [1, 'Kadang sulit bernapas / periode hiperventilasi singkat'],
                        [2, 'Napas berisik & sulit, periode hiperventilasi panjang, Cheyne-Stokes'],
                    ],
                ],
                'vokalisasiNegatif' => [
                    'label' => 'Vokalisasi negatif',
                    'opsi' => [
                        [0, 'Tidak ada'],
                        [1, 'Mengerang/merintih sesekali, bicara pelan dengan nada negatif'],
                        [2, 'Memanggil-manggil berulang, mengerang keras, menangis'],
                    ],
                ],
                'ekspresiWajah' => [
                    'label' => 'Ekspresi wajah',
                    'opsi' => [
                        [0, 'Tersenyum atau datar'],
                        [1, 'Sedih, ketakutan, cemberut'],
                        [2, 'Meringis'],
                    ],
                ],
                'bahasaTubuh' => [
                    'label' => 'Bahasa tubuh',
                    'opsi' => [
                        [0, 'Relaks'],
                        [1, 'Tegang, mondar-mandir gelisah, meremas tangan'],
                        [2, 'Kaku, mengepalkan tangan, lutut ditarik, menarik/mendorong, memukul'],
                    ],
                ],
                'konsolabilitas' => [
                    'label' => 'Dapat ditenangkan',
                    'opsi' => [
                        [0, 'Tidak perlu ditenangkan'],
                        [1, 'Dapat ditenangkan dengan suara atau sentuhan'],
                        [2, 'Sulit ditenangkan atau dialihkan'],
                    ],
                ],
            ],
            'interpretasi' => [
                [0, 0, 'Tidak Nyeri', self::TINGKAT_TIDAK],
                [1, 3, 'Nyeri Ringan', self::TINGKAT_RINGAN],
                [4, 6, 'Nyeri Sedang', self::TINGKAT_SEDANG],
                [7, 10, 'Nyeri Berat', self::TINGKAT_BERAT],
            ],
        ],
    ];

    /**
     * Tata laksana berjenjang menurut tingkat nyeri (materi IHT PAP).
     * 0–3 perawat · 4–6 dokter umum/DPJP · 7–10 DPJP atau Tim Nyeri RS.
     */
    public const TATA_LAKSANA = [
        self::TINGKAT_TIDAK => 'Dilaksanakan oleh perawat',
        self::TINGKAT_RINGAN => 'Dilaksanakan oleh perawat',
        self::TINGKAT_SEDANG => 'Dilaksanakan oleh dokter umum atau dokter DPJP',
        self::TINGKAT_BERAT => 'Dilaksanakan oleh dokter DPJP atau Tim Nyeri Rumah Sakit',
        self::TINGKAT_SANGAT_BERAT => 'Dilaksanakan oleh dokter DPJP atau Tim Nyeri Rumah Sakit',
    ];

    /** Kelas badge per tingkat nyeri (kontras ≥ 4.5:1 di terang & gelap). */
    public const BADGE = [
        self::TINGKAT_TIDAK => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200',
        self::TINGKAT_RINGAN => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-200',
        self::TINGKAT_SEDANG => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-200',
        self::TINGKAT_BERAT => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200',
        self::TINGKAT_SANGAT_BERAT => 'bg-red-200 text-red-900 dark:bg-red-900/50 dark:text-red-100',
    ];

    /** Definisi satu skala; null bila kode tidak dikenal (mis. entri lama). */
    public static function skala(?string $kode): ?array
    {
        return self::SKALA[$kode ?? ''] ?? null;
    }

    /** Label ringkas untuk dropdown: "FLACC — Bayi s/d anak 3 th". */
    public static function labelPilihan(string $kode): string
    {
        $skala = self::skala($kode);

        return $skala ? $kode . ' — ' . $skala['sasaran'] : $kode;
    }

    /** Rentang skor valid satu skala; default 0–10 bila kode tak dikenal. */
    public static function rentang(?string $kode): array
    {
        $skala = self::skala($kode);

        return ['min' => $skala['min'] ?? 0, 'max' => $skala['max'] ?? 10];
    }

    /**
     * Kerangka dataNyeri untuk satu skala — dipakai saat metode dipilih.
     * 'item'  → [kategori => ['label'=>, 'opsi'=> [ ['score','description','active'], ... ]]]
     * 'pilih' → [ ['score','description','active'], ... ]
     * 'angka' → []
     */
    public static function kerangkaData(?string $kode): array
    {
        $skala = self::skala($kode);
        if (!$skala) {
            return [];
        }

        if (($skala['tipe'] ?? '') === 'item') {
            $hasil = [];
            foreach ($skala['items'] as $kategori => $definisi) {
                $hasil[$kategori] = [
                    'label' => $definisi['label'],
                    'opsi' => array_map(fn($opsi) => ['score' => $opsi[0], 'description' => $opsi[1], 'active' => false], $definisi['opsi']),
                ];
            }

            return $hasil;
        }

        if (($skala['tipe'] ?? '') === 'pilih') {
            return array_map(fn($nilai) => ['score' => $nilai[0], 'description' => $nilai[1], 'active' => false], $skala['nilai']);
        }

        return [];
    }

    /** Jumlahkan skor item terpilih. Kategori yang belum dipilih diabaikan. */
    public static function totalSkorItem(array $dataNyeri): int
    {
        $total = 0;
        foreach ($dataNyeri as $kategori) {
            foreach ($kategori['opsi'] ?? [] as $opsi) {
                if (!empty($opsi['active'])) {
                    $total += (int) $opsi['score'];
                    break;
                }
            }
        }

        return $total;
    }

    /** Kategori pada skala 'item' yang belum dipilih sama sekali (label-nya). */
    public static function itemBelumDinilai(array $dataNyeri): array
    {
        $belum = [];
        foreach ($dataNyeri as $kategori) {
            $adaAktif = false;
            foreach ($kategori['opsi'] ?? [] as $opsi) {
                if (!empty($opsi['active'])) {
                    $adaAktif = true;
                    break;
                }
            }
            if (!$adaAktif) {
                $belum[] = $kategori['label'] ?? '-';
            }
        }

        return $belum;
    }

    /**
     * Interpretasi skor → ['label','tingkat','badge','tataLaksana'].
     * Skor di luar rentang atau kode tak dikenal → label '-' tanpa tingkat.
     */
    public static function interpretasi(?string $kode, int|string|null $skor): array
    {
        $skala = self::skala($kode);
        $kosong = ['label' => '-', 'tingkat' => '', 'badge' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200', 'tataLaksana' => ''];

        if (!$skala || $skor === null || $skor === '') {
            return $kosong;
        }

        $skor = (int) $skor;
        foreach ($skala['interpretasi'] as [$min, $max, $label, $tingkat]) {
            if ($skor >= $min && $skor <= $max) {
                return [
                    'label' => $label,
                    'tingkat' => $tingkat,
                    'badge' => self::BADGE[$tingkat] ?? $kosong['badge'],
                    'tataLaksana' => self::TATA_LAKSANA[$tingkat] ?? '',
                ];
            }
        }

        return $kosong;
    }

    /**
     * Keterangan bawaan versi lama yang PERNAH dihasilkan otomatis oleh sistem.
     * Dipakai memilah mana isi nyeriKet yang label turunan (boleh ditimpa) dan
     * mana yang catatan bebas tulisan petugas (WAJIB dipertahankan).
     */
    public const LABEL_LAMA = ['Santai dan nyaman', 'Ketidaknyamanan ringan'];

    /**
     * Isi nyeriKet yang merupakan catatan bebas petugas — string kosong bila
     * nilainya cuma label turunan sistem.
     *
     * Data lama menyimpan campuran: sebagian label otomatis ("Nyeri Sedang"),
     * sebagian tulisan tangan petugas ("hilang timbul", "nyeri akut", "Nyeri
     * saat bergerak"). Tulisan petugas tidak boleh hilang dari layar & cetakan.
     */
    public static function catatanLama(?string $keterangan): string
    {
        $keterangan = trim((string) $keterangan);
        if ($keterangan === '') {
            return '';
        }

        $labelSistem = self::LABEL_LAMA;
        foreach (self::SKALA as $skala) {
            foreach ($skala['interpretasi'] as [, , $label]) {
                $labelSistem[] = $label;
            }
        }

        foreach ($labelSistem as $label) {
            if (mb_strtolower($keterangan) === mb_strtolower($label)) {
                return '';
            }
        }

        return $keterangan;
    }

    /**
     * Samakan satu entri penilaian ke bentuk baku ['nyeri' => [...]].
     *
     * Tiga bentuk yang beredar di JSON EMR:
     *  1. entri baku      : ['nyeri' => ['nyeriMetode' => ['nyeriMetode' => 'NRS', 'nyeriMetodeScore' => 5], ...]]
     *  2. node nyeri saja : ['nyeriMetode' => [...], ...]           — dipakai RM RI
     *  3. record lama     : nyeriMetode masih berupa string, skor di skalaNyeri / vas.vas,
     *                       dan nyeriKet berisi Akut/Kronis (bukan label interpretasi)
     * Parameter sengaja mixed: record lama menyimpan `penilaian.nyeri` sebagai SATU
     * entri (assoc), sehingga end() di pemanggil bisa mengirim string ke sini.
     */
    public static function normalisasiEntri(mixed $entri): array
    {
        if (!is_array($entri) || $entri === []) {
            return [];
        }

        // Entri baku punya key 'nyeri' berisi array; pada record lama key 'nyeri'
        // justru berisi string 'Ya'/'Tidak', jadi entri itu sendiri adalah node-nya.
        $adalahEntriBaku = is_array($entri['nyeri'] ?? null);
        $node = $adalahEntriBaku ? $entri['nyeri'] : $entri;

        if (!is_array($node['nyeriMetode'] ?? null)) {
            $skor = $node['skalaNyeri'] ?? null;
            if ($skor === null || $skor === '') {
                $skor = data_get($node, 'vas.vas');
            }
            $node['nyeriMetode'] = [
                'nyeriMetode' => is_string($node['nyeriMetode'] ?? null) ? $node['nyeriMetode'] : '',
                'nyeriMetodeScore' => $skor,
            ];
        }

        return ['nyeri' => $node] + ($adalahEntriBaku ? $entri : []);
    }

    /**
     * Riwayat penilaian nyeri sebagai DAFTAR entri baku.
     *
     * Record lama menyimpan `penilaian.nyeri` sebagai SATU entri (assoc), bukan
     * daftar; bila record itu dinilai lagi lewat EMR, entri baru ditambahkan
     * berkunci angka di samping key lama sehingga isinya campuran. Entri lama
     * ditaruh paling depan supaya urutannya tetap kronologis.
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
                    $daftar[] = self::normalisasiEntri($nilai);
                }
                continue;
            }
            $entriLama[$kunci] = $nilai;
        }

        if ($entriLama !== []) {
            array_unshift($daftar, self::normalisasiEntri($entriLama));
        }

        return $daftar;
    }

    /** Entri penilaian nyeri terakhir (bentuk baku), [] bila belum dinilai. */
    public static function entriTerakhir(mixed $riwayat): array
    {
        $daftar = self::daftarEntri($riwayat);

        return $daftar === [] ? [] : end($daftar);
    }

    /**
     * Tafsir satu entri riwayat: interpretasi dihitung ulang dari metode + skor,
     * plus catatan bebas petugas (bila ada) supaya tidak tertimpa.
     *
     * @return array{label:string, tingkat:string, badge:string, tataLaksana:string, catatan:string}
     */
    public static function tafsirEntri(mixed $entri): array
    {
        $entri = self::normalisasiEntri($entri);

        $kode = data_get($entri, 'nyeri.nyeriMetode.nyeriMetode');
        $skor = data_get($entri, 'nyeri.nyeriMetode.nyeriMetodeScore');
        $keterangan = trim((string) data_get($entri, 'nyeri.nyeriKet', ''));

        $tafsir = self::interpretasi($kode, $skor);
        $catatan = self::catatanLama($keterangan);

        // Skor di luar rentang skala (data lama) & keterangan tersimpan berupa
        // label baku → pakai label itu apa adanya.
        if ($tafsir['tingkat'] === '' && $catatan === '' && $keterangan !== '') {
            $tafsir['label'] = $keterangan;
        }

        $tafsir['catatan'] = $catatan;

        return $tafsir;
    }

    /**
     * Ringkasan satu entri penilaian nyeri untuk display & cetak Rekam Medis.
     *
     * @param  mixed  $entri  satu entri penilaian — bentuk apa pun yang dikenali normalisasiEntri()
     * @return array{metode:string, sasaran:string, skor:string, label:string, tataLaksana:string, catatan:string}
     */
    public static function ringkasEntri(mixed $entri): array
    {
        $entri = self::normalisasiEntri($entri);

        $kode = data_get($entri, 'nyeri.nyeriMetode.nyeriMetode');
        $skor = data_get($entri, 'nyeri.nyeriMetode.nyeriMetodeScore');
        $skala = self::skala($kode);
        $tafsir = self::tafsirEntri($entri);

        return [
            'metode' => filled($kode) ? $kode : '-',
            'sasaran' => $skala['sasaran'] ?? '',
            'skor' => $skor === null || $skor === '' ? '-' : ($skala ? $skor . '/' . $skala['max'] : (string) $skor),
            'label' => $tafsir['label'],
            'tataLaksana' => $tafsir['tataLaksana'],
            'catatan' => $tafsir['catatan'],
        ];
    }

    /**
     * Kode skala yang disarankan untuk umur tertentu (tahun).
     * Skala tanpa batas usia (BPS/CPOT) tidak pernah disarankan otomatis —
     * pemakaiannya ditentukan kondisi pasien, bukan umur.
     */
    public static function saranUntukUmur(?int $umurTahun): array
    {
        if ($umurTahun === null) {
            return [];
        }

        $saran = [];
        foreach (self::SKALA as $kode => $skala) {
            if ($skala['usiaMin'] === null && $skala['usiaMax'] === null) {
                continue;
            }
            $memenuhiMin = $skala['usiaMin'] === null || $umurTahun >= $skala['usiaMin'];
            $memenuhiMax = $skala['usiaMax'] === null || $umurTahun <= $skala['usiaMax'];
            if ($memenuhiMin && $memenuhiMax) {
                $saran[] = $kode;
            }
        }

        return $saran;
    }
}
