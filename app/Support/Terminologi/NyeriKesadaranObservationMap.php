<?php

namespace App\Support\Terminologi;

use App\Support\Options\NyeriOptions;

/**
 * Pemetaan Skala Nyeri & Tingkat Kesadaran ke Observation SATUSEHAT.
 *
 * Sumber kode: koleksi Postman resmi "30. Use Case - Rujukan Pasien V30062026"
 * (Observation - NRS, Observation - BPS, Observation - Total score NIPS,
 * Observation - Tingkat Kesadaran). TIDAK ada kode yang dikarang di sini —
 * skala yang belum punya contoh resmi sengaja dibiarkan kosong dan dilaporkan
 * ke pengguna, bukan dikirim dengan kode tebakan. Salah kode berarti kondisi
 * klinis pasien tercatat sebagai konsep yang keliru di SATUSEHAT — jauh lebih
 * berbahaya daripada sekadar ditolak validator.
 *
 * Dipisah dari [[PenilaianObservationMap]] karena sumber datanya beda node
 * (penilaian.nyeri[] & screening.kesadaran, bukan penilaian.resikoJatuh/gizi).
 */
class NyeriKesadaranObservationMap
{
    /**
     * Kode per skala nyeri. Kunci = kode skala di NyeriOptions.
     *
     * 'nilai' menentukan bentuk pengiriman skor:
     *   'integer'  → valueInteger        (contoh resmi NRS & BPS/Wong-Baker)
     *   'quantity' → valueQuantity {score} (contoh resmi Total score NIPS)
     *
     * BELUM ADA CONTOH RESMI: VAS, FLACC, CPOT, PAINAD. Keempatnya tetap boleh
     * dipakai petugas di EMR — hanya tidak ikut terkirim, dan jumlahnya
     * dimunculkan di kartu supaya tidak hilang diam-diam.
     */
    private const SKALA = [
        'NRS' => [
            'system'  => 'http://snomed.info/sct',
            'code'    => '1172399009',
            'display' => 'Numeric rating scale score',
            'nilai'   => 'integer',
        ],
        // NyeriOptions memakai kode 'WBS'; contoh resminya bernama "Observation - BPS"
        // tapi display-nya jelas Wong-Baker FACES, jadi dipetakan ke WBS — bukan ke
        // 'BPS' (Behavioral Pain Scale) yang instrumennya berbeda sama sekali.
        'WBS' => [
            'system'  => 'http://loinc.org',
            'code'    => '38221-8',
            'display' => 'Pain severity Wong-Baker FACES pain rating scale',
            'nilai'   => 'integer',
        ],
        'NIPS' => [
            'system'  => 'http://loinc.org',
            'code'    => '98012-8',
            'display' => 'Total score NIPS',
            'nilai'   => 'quantity',
        ],
    ];

    /** LOINC untuk tingkat kesadaran (contoh resmi "Observation - Tingkat Kesadaran"). */
    private const KESADARAN_CODE = ['system' => 'http://loinc.org', 'code' => '67775-7', 'display' => 'Level of responsiveness'];

    /**
     * Padanan SNOMED tiap pilihan kesadaran di EMR RJ.
     *
     * HANYA "Mengantuk / Gelisah" yang punya kode dari contoh resmi
     * (300202002 Response to voice = pasien merespons saat dipanggil). Dua
     * lainnya BELUM ada padanan resminya, jadi dikirim sebagai teks saja —
     * CodeableConcept dengan `text` tanpa `coding` itu sah di FHIR. Begitu kode
     * SNOMED-nya didapat dari Lampiran Terminologi SATUSEHAT, cukup isi 'code'
     * di sini; tidak ada tempat lain yang perlu disentuh.
     */
    private const KESADARAN = [
        'Sadar'                => ['code' => null, 'display' => null],
        'Mengantuk / Gelisah'  => ['code' => '300202002', 'display' => 'Response to voice'],
        'Tidak Sadar'          => ['code' => null, 'display' => null],
    ];

    public static function surveyCategory(): array
    {
        return [[
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/observation-category',
                'code'    => 'survey',
                'display' => 'Survey',
            ]],
        ]];
    }

    public static function examCategory(): array
    {
        return [[
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/observation-category',
                'code'    => 'exam',
                'display' => 'Exam',
            ]],
        ]];
    }

    /** Skala yang dipakai petugas tapi belum bisa dikirim — untuk dilaporkan, bukan disembunyikan. */
    public static function skalaTanpaKode(): array
    {
        return array_values(array_diff(array_keys(NyeriOptions::SKALA), array_keys(self::SKALA)));
    }

    public static function skalaDidukung(string $kode): bool
    {
        return isset(self::SKALA[$kode]);
    }

    /**
     * Satu entri nyeri → 0..1 Observation.
     *
     * Kosong bila: pasien menjawab tidak nyeri, skala belum dipilih, skalanya
     * belum punya kode resmi, atau skornya bukan angka.
     */
    public static function nyeri(mixed $entri): array
    {
        $baku = NyeriOptions::normalisasiEntri($entri);
        if ($baku === []) {
            return [];
        }

        $node = $baku['nyeri'] ?? [];
        // Record lama menyimpan 'nyeri' sebagai string 'Ya'/'Tidak'; yang baru array.
        $adaNyeri = is_string($node['nyeri'] ?? null) ? $node['nyeri'] : ($baku['nyeri']['nyeri'] ?? '');
        if (is_string($adaNyeri) && $adaNyeri !== '' && strcasecmp(trim($adaNyeri), 'Ya') !== 0) {
            return [];
        }

        $kodeSkala = (string) ($node['nyeriMetode']['nyeriMetode'] ?? '');
        $skor = $node['nyeriMetode']['nyeriMetodeScore'] ?? null;

        if (!self::skalaDidukung($kodeSkala) || $skor === null || $skor === '' || !is_numeric($skor)) {
            return [];
        }

        $skala = self::SKALA[$kodeSkala];
        $observation = [
            'category' => self::surveyCategory(),
            'code'     => ['system' => $skala['system'], 'code' => $skala['code'], 'display' => $skala['display']],
        ];

        if ($skala['nilai'] === 'quantity') {
            $observation['valueQuantity'] = [
                'value'  => (float) $skor,
                'unit'   => '{score}',
                'system' => 'http://unitsofmeasure.org',
                'code'   => '{score}',
            ];
        } else {
            $observation['valueInteger'] = (int) $skor;
        }

        return [$observation];
    }

    /** Tingkat kesadaran → 0..1 Observation. Kosong bila belum diisi. */
    public static function kesadaran(?string $nilai): array
    {
        $nilai = trim((string) $nilai);
        if ($nilai === '') {
            return [];
        }

        $konsep = ['text' => $nilai];
        $padanan = self::KESADARAN[$nilai] ?? null;
        if ($padanan && $padanan['code'] !== null) {
            $konsep['system'] = 'http://snomed.info/sct';
            $konsep['code'] = $padanan['code'];
            $konsep['display'] = $padanan['display'];
        }

        return [[
            'category' => self::examCategory(),
            'code'     => self::KESADARAN_CODE,
            'valueCodeableConcept' => $konsep,
        ]];
    }
}
