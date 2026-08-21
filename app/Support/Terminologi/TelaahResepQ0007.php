<?php

namespace App\Support\Terminologi;

/**
 * Pemetaan telaah resep SIMRS -> QuestionnaireResponse Q0007 SATUSEHAT.
 *
 * Kuesioner baku: https://fhir.kemkes.go.id/Questionnaire/Q0007
 * Bentuk item MENGIKUTI PERSIS contoh resmi Postman V30062026 — grup 2, grup 3,
 * dan butir 4 BERSARANG di dalam grup 1, bukan bersaudara. Terlihat aneh, tapi
 * validator Kemkes itu kotak hitam: menyimpang dari contoh resmi berarti menebak.
 *
 * SATU KODE MASIH KOSONG — lihat TIDAK_SESUAI di bawah.
 */
class TelaahResepQ0007
{
    public const CANONICAL = 'https://fhir.kemkes.go.id/Questionnaire/Q0007';

    private const SISTEM = 'http://terminology.kemkes.go.id/CodeSystem/clinical-term';

    /** Jawaban "sudah sesuai" — satu-satunya kode Q0007 yang ada di contoh resmi. */
    private const SESUAI = ['system' => self::SISTEM, 'code' => 'OV000052', 'display' => 'Sesuai'];

    /**
     * Jawaban "TIDAK sesuai" — KODENYA BELUM DIKETAHUI.
     *
     * Seluruh koleksi Postman resmi hanya memuat tiga kode clinical-term
     * (OC000034, OI000020, OV000052); tidak ada padanan untuk "Tidak Sesuai".
     * Berbeda dari CodeableConcept, tipe `Coding` TIDAK punya field `text`, jadi
     * tidak bisa diakali dengan mengirim teksnya saja.
     *
     * CARA MENGISI: ganti null di bawah dengan
     *     ['system' => self::SISTEM, 'code' => '<KODE>', 'display' => 'Tidak Sesuai']
     * Tidak ada tempat lain yang perlu disentuh — penjagaan di bangun() otomatis
     * berhenti menolak begitu konstanta ini terisi.
     *
     * Selama masih null, telaah yang memuat jawaban "Tidak" pada butir ber-valueCoding
     * DITOLAK KIRIM. Sengaja: melewati butir yang dijawab "Tidak" berarti telaah
     * bermasalah terkirim TANPA masalahnya — rekam medis berbohong lewat kelalaian,
     * justru pada kasus yang paling penting.
     */
    private const TIDAK_SESUAI = null;

    /**
     * Butir ber-valueCoding: [linkId, teks, field telaah kita].
     * 'tepatRute' + 'tepatWaktu' sama-sama memberi makan linkId 2.4 (aturan & cara
     * penggunaan) — dua field kita, satu pertanyaan Kemkes; keduanya harus 'Ya'.
     */
    private const PILIHAN = [
        '1.1' => ['Apakah nama, umur, jenis kelamin, berat badan dan tinggi badan pasien sudah sesuai?', ['bbPasienAnak']],
        '1.2' => ['Apakah nama, nomor ijin, alamat dan paraf dokter sudah sesuai?', ['identitasDokter']],
        '1.3' => ['Apakah tanggal resep sudah sesuai?', ['tanggalResep']],
        '1.4' => ['Apakah ruangan/unit asal resep sudah sesuai?', ['ruanganAsalResep']],
        '2.1' => ['Apakah nama obat, bentuk dan kekuatan sediaan sudah sesuai?', ['tepatObat']],
        '2.2' => ['Apakah dosis dan jumlah obat sudah sesuai?', ['tepatDosis']],
        '2.3' => ['Apakah stabilitas obat sudah sesuai?', ['stabilitasObat']],
        '2.4' => ['Apakah aturan dan cara penggunaan obat sudah sesuai?', ['tepatRute', 'tepatWaktu']],
        '3.1' => ['Apakah ketepatan indikasi, dosis, dan waktu penggunaan obat sudah sesuai?', ['ketepatanIndikasi']],
    ];

    /**
     * Butir ber-valueBoolean: [linkId, teks, field telaah kita].
     * Di sini 'Ya' berarti MASALAH ADA -> true. Tidak ada persoalan kode: boolean
     * tidak butuh terminologi, jadi keempat butir ini selalu bisa dikirim.
     */
    private const BOOLEAN = [
        '3.2' => ['Apakah terdapat duplikasi pengobatan?', 'duplikasi'],
        '3.3' => ['Apakah terdapat alergi dan reaksi obat yang tidak diinginkan?', 'alergi'],
        '3.4' => ['Apakah terdapat kontraindikasi pengobatan?', 'kontraIndikasiLain'],
        '3.5' => ['Apakah terdapat dampak interaksi obat?', 'interaksiObat'],
    ];

    /** Label untuk pesan penolakan — supaya petugas tahu butir mana yang menghalangi. */
    private const LABEL = [
        'bbPasienAnak' => 'BB Pasien Anak',
        'identitasDokter' => 'Identitas & Paraf Dokter',
        'tanggalResep' => 'Tanggal Resep',
        'ruanganAsalResep' => 'Ruangan/Unit Asal Resep',
        'tepatObat' => 'Tepat Obat',
        'tepatDosis' => 'Tepat Dosis',
        'stabilitasObat' => 'Stabilitas Obat',
        'tepatRute' => 'Tepat Rute',
        'tepatWaktu' => 'Tepat Waktu',
        'ketepatanIndikasi' => 'Ketepatan Indikasi & Waktu Penggunaan',
        'duplikasi' => 'Duplikasi Obat',
        'alergi' => 'Riwayat Alergi',
        'kontraIndikasiLain' => 'Kontra Indikasi Lain',
        'interaksiObat' => 'Interaksi Obat',
    ];

    /**
     * Butir yang TIDAK ADA nilainya di telaah — penghalang kirim.
     *
     * Kasus nyatanya: telaah yang dibuat SEBELUM lima butir Q0007 ditambahkan
     * (21/08/2026) hanya menyimpan 10 butir. Untuk telaah yang sudah ditandatangani,
     * formnya terkunci sehingga lima key baru tak pernah ikut tersimpan.
     *
     * Tanpa penjagaan ini, butir kosong lolos sebagai "Sesuai" (valueCoding) atau
     * "tidak ada masalah" (valueBoolean) — SIMRS mengarang jawaban atas pertanyaan
     * yang tidak pernah diajukan ke apoteker, lalu menuliskannya ke rekam medis
     * nasional. Jauh lebih buruk daripada tidak mengirim.
     *
     * @return array<int, string> label butir; kosong = semua terjawab
     */
    public static function butirBelumDijawab(array $telaah): array
    {
        $kosong = [];

        foreach (self::PILIHAN as [$teks, $fieldList]) {
            foreach ($fieldList as $field) {
                if (self::nilai($telaah, $field) === '') {
                    $kosong[] = self::LABEL[$field] ?? $field;
                }
            }
        }

        foreach (self::BOOLEAN as [$teks, $field]) {
            if (self::nilai($telaah, $field) === '') {
                $kosong[] = self::LABEL[$field] ?? $field;
            }
        }

        return array_values(array_unique($kosong));
    }

    public static function kodeTidakSesuaiTersedia(): bool
    {
        return self::TIDAK_SESUAI !== null;
    }

    /**
     * Butir telaah yang dijawab "Tidak" tapi belum punya kode — penghalang kirim.
     *
     * @return array<int, string> label butir; kosong = tidak ada penghalang
     */
    public static function butirTanpaKode(array $telaah): array
    {
        if (self::kodeTidakSesuaiTersedia()) {
            return [];
        }

        $penghalang = [];
        foreach (self::PILIHAN as [$teks, $fieldList]) {
            foreach ($fieldList as $field) {
                if (self::nilai($telaah, $field) === 'Tidak') {
                    $penghalang[] = self::LABEL[$field] ?? $field;
                }
            }
        }

        return array_values(array_unique($penghalang));
    }

    /**
     * Pohon item Q0007 — bersarang persis seperti contoh resmi.
     *
     * @param  array       $telaah              node telaahResep dari JSON kunjungan
     * @param  string|null $medicationRequestId id MedicationRequest yang dikaji (butir 4)
     */
    public static function item(array $telaah, ?string $medicationRequestId = null): array
    {
        $grup3 = [];
        foreach (self::PILIHAN as $linkId => [$teks, $fieldList]) {
            if (!str_starts_with($linkId, '3.')) {
                continue;
            }
            $grup3[] = self::butirPilihan($linkId, $teks, $telaah, $fieldList);
        }
        foreach (self::BOOLEAN as $linkId => [$teks, $field]) {
            $grup3[] = [
                'linkId' => $linkId,
                'text'   => $teks,
                'answer' => [['valueBoolean' => self::nilai($telaah, $field) === 'Ya']],
            ];
        }

        $grup2 = [];
        foreach (self::PILIHAN as $linkId => [$teks, $fieldList]) {
            if (!str_starts_with($linkId, '2.')) {
                continue;
            }
            $grup2[] = self::butirPilihan($linkId, $teks, $telaah, $fieldList);
        }

        $grup1 = [];
        foreach (self::PILIHAN as $linkId => [$teks, $fieldList]) {
            if (!str_starts_with($linkId, '1.')) {
                continue;
            }
            $grup1[] = self::butirPilihan($linkId, $teks, $telaah, $fieldList);
        }

        $grup1[] = ['linkId' => '2', 'text' => 'Persyaratan Farmasetik', 'item' => $grup2];
        $grup1[] = ['linkId' => '3', 'text' => 'Persyaratan Klinis', 'item' => $grup3];

        // Butir 4 hanya disertakan bila resepnya memang sudah terkirim ke SATUSEHAT —
        // reference ke resource yang tidak ada akan ditolak validator.
        if (!empty($medicationRequestId)) {
            $grup1[] = [
                'linkId' => '4',
                'text'   => 'Resep yang dilakukan pengkajian resep',
                'answer' => [['valueReference' => ['reference' => 'MedicationRequest/' . $medicationRequestId]]],
            ];
        }

        return [[
            'linkId' => '1',
            'text'   => 'Persyaratan Administrasi',
            'item'   => $grup1,
        ]];
    }

    /**
     * Satu butir ber-valueCoding. Semua field pemberi makannya harus 'Ya' supaya
     * dijawab Sesuai — pada 2.4 satu saja yang 'Tidak' membuat jawabannya tidak sesuai.
     */
    private static function butirPilihan(string $linkId, string $teks, array $telaah, array $fieldList): array
    {
        $sesuai = true;
        foreach ($fieldList as $field) {
            if (self::nilai($telaah, $field) === 'Tidak') {
                $sesuai = false;
            }
        }

        $coding = $sesuai ? self::SESUAI : self::TIDAK_SESUAI;

        return [
            'linkId' => $linkId,
            'text'   => $teks,
            'answer' => [['valueCoding' => $coding]],
        ];
    }

    /** Nilai satu butir telaah; bentuknya ['<field>' => 'Ya'|'Tidak', 'desc' => '...']. */
    private static function nilai(array $telaah, string $field): string
    {
        return (string) ($telaah[$field][$field] ?? '');
    }
}
