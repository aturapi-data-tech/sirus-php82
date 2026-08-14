<?php

namespace App\Support\Terminologi;

/**
 * Definisi section Resume Medis (Composition) SATUSEHAT, per jalur layanan.
 *
 * Sumber: playbook "Resume Medis - Rawat Jalan" Bab 28 (sunting 2 Desember 2025) dan
 * playbook "Pelayanan Instalasi Gawat Darurat (IGD)"; salinan tabelnya di
 * docs/satusehat-api.md §9.4.
 *
 * Composition BUKAN dokumen naratif — ia indeks resource yang sudah dikirim selama
 * kunjungan, dirangkai lewat section[].entry. Hanya section "Perjalanan Kunjungan
 * Pasien" yang benar-benar naratif lewat section.text.div.
 *
 * SUSUNAN TIAP JALUR BEDA, jangan disamakan:
 * - RJ  : 13 section, ada Diet & Edukasi, tanpa asesmen awal.
 * - UGD : 13 section, diawali Asesmen Awal IGD + Skrining, TANPA Diet & Edukasi.
 * Kode leaf yang sama namanya kebetulan identik di kedua jalur — makanya kamusnya
 * dipakai bersama, yang berbeda hanya susunannya.
 *
 * Ditaruh sebagai helper statis (bukan trait) mengikuti pola PenilaianObservationMap.
 * JANGAN mengganti kode dari hafalan: kode tipe dokumen pernah berubah (changelog
 * playbook RJ v6.1, 24/10/2024).
 */
class ResumeMedisSection
{
    public const SISTEM_LOINC = 'http://loinc.org';
    public const SISTEM_KEMKES = 'http://terminology.kemkes.go.id';

    /** Composition.type per jalur layanan. */
    private const TIPE_DOKUMEN = [
        'rj' => ['88645-7', 'Outpatient hospital Discharge summary'],
        'ugd' => ['97663-9', 'Emergency medicine Emergency department Discharge summary'],
    ];

    /**
     * Kamus simpul: kunci => [judul, sistem, kode, display].
     * Sistem null = simpul tanpa kode (hanya judul) — dipakai induk "Diet" di jalur RJ,
     * yang di playbook memang tidak diberi kode (tampak salah tulis, lihat docs §9.4).
     */
    private const KAMUS = [
        'asesmenAwalIgd' => ['Asesmen Awal IGD', self::SISTEM_LOINC, '97667-0', 'Emergency medicine Emergency department Initial evaluation note'],
        'skrining' => ['Skrining', self::SISTEM_KEMKES, 'TK000129', 'Skrining'],

        'anamnesis' => ['Anamnesis', self::SISTEM_KEMKES, 'TK000003', 'Anamnesis'],
        'keluhanUtama' => ['Keluhan Utama', self::SISTEM_LOINC, '10154-3', 'Chief complaint Narrative - Reported'],
        'keluhanPenyerta' => ['Keluhan Penyerta', self::SISTEM_LOINC, '11450-4', 'Problem list - Reported'],
        'riwayatAlergi' => ['Riwayat Alergi', self::SISTEM_LOINC, '48765-2', 'Allergies'],
        'riwayatPenyakitTerdahulu' => ['Riwayat Penyakit Pribadi Terdahulu', self::SISTEM_LOINC, '11348-0', 'History of Past illness Narrative'],
        'riwayatPenyakitSekarang' => ['Riwayat Penyakit Pribadi Sekarang', self::SISTEM_LOINC, '10164-2', 'History of Present illness Narrative'],
        'riwayatPenyakitKeluarga' => ['Riwayat Penyakit Keluarga', self::SISTEM_LOINC, '10157-6', 'History of family member diseases Narrative'],
        'riwayatPengobatan' => ['Riwayat Pengobatan', self::SISTEM_LOINC, '10160-0', 'History of Medication use Narrative'],

        'pemeriksaanFisik' => ['Pemeriksaan Fisik', self::SISTEM_KEMKES, 'TK000007', 'Pemeriksaan Fisik'],
        'tandaVital' => ['Tanda Vital', self::SISTEM_LOINC, '8716-3', 'Vital signs'],
        'headToToe' => ['Pemeriksaan Fisik Head to Toe', self::SISTEM_LOINC, '10187-3', 'Review of systems Narrative - Reported'],

        'pemeriksaanFungsional' => ['Pemeriksaan Fungsional', self::SISTEM_LOINC, '47420-5', 'Functional status assessment note'],
        'perencanaanPerawatan' => ['Perencanaan Perawatan', self::SISTEM_LOINC, '18776-5', 'Plan of care note'],

        'pemeriksaanPenunjang' => ['Pemeriksaan Penunjang', self::SISTEM_KEMKES, 'TK000009', 'Hasil Pemeriksaan Penunjang'],
        'hasilLab' => ['Hasil Pemeriksaan Laboratorium', self::SISTEM_LOINC, '11502-2', 'Laboratory report'],
        'hasilRadiologi' => ['Hasil Pemeriksaan Radiologi', self::SISTEM_LOINC, '18782-3', 'Radiology Study observation (narrative)'],

        'diagnosis' => ['Diagnosis', self::SISTEM_KEMKES, 'TK000004', 'Diagnosis'],
        'diagnosisAwal' => ['Diagnosis Awal', self::SISTEM_LOINC, '42347-5', 'Admission diagnosis (narrative)'],
        'diagnosisAkhir' => ['Diagnosis Akhir', self::SISTEM_LOINC, '78375-3', 'Discharge diagnosis Narrative'],

        'tindakan' => ['Tindakan/Prosedur Medis', self::SISTEM_KEMKES, 'TK000005', 'Tindakan/Prosedur Medis'],

        'farmasi' => ['Farmasi', self::SISTEM_KEMKES, 'TK000013', 'Obat'],
        'obatSaatKunjungan' => ['Obat Saat Kunjungan', self::SISTEM_LOINC, '42346-7', 'Medications on admission (narrative)'],
        'obatPulang' => ['Obat Pulang', self::SISTEM_LOINC, '75311-1', 'Discharge medications Narrative'],

        'diet' => ['Diet', null, null, null],
        'rekomendasiDiet' => ['Rekomendasi Diet', self::SISTEM_LOINC, '42344-2', 'Discharge diet (narrative)'],
        'dietDiberikan' => ['Diet yang diberikan', self::SISTEM_LOINC, '61144-2', 'Diet and nutrition Narrative'],

        'edukasi' => ['Edukasi', self::SISTEM_LOINC, '34895-3', 'Education note'],
        'kondisiPulang' => ['Kondisi Saat Meninggalkan Rumah Sakit', self::SISTEM_LOINC, '10184-0', 'Hospital discharge physical findings Narrative'],
        'rencanaTindakLanjut' => ['Rencana Tindak Lanjut', self::SISTEM_LOINC, '8653-8', 'Hospital Discharge instructions'],
        'perjalananKunjungan' => ['Perjalanan Kunjungan Pasien', self::SISTEM_LOINC, '8648-8', 'Hospital course Narrative'],
    ];

    /** Anak-anak baku yang dipakai kedua jalur. */
    private const ANAK_ANAMNESIS = [
        'keluhanUtama', 'keluhanPenyerta', 'riwayatAlergi', 'riwayatPenyakitTerdahulu',
        'riwayatPenyakitSekarang', 'riwayatPenyakitKeluarga', 'riwayatPengobatan',
    ];

    /**
     * Susunan section per jalur, urut sesuai playbook masing-masing.
     * Nilai string = section daun; array = induk => daftar kunci anaknya.
     */
    private const SUSUNAN = [
        'rj' => [
            ['anamnesis' => self::ANAK_ANAMNESIS],
            ['pemeriksaanFisik' => ['tandaVital', 'headToToe']],
            'pemeriksaanFungsional',
            'perencanaanPerawatan',
            ['pemeriksaanPenunjang' => ['hasilLab', 'hasilRadiologi']],
            ['diagnosis' => ['diagnosisAwal', 'diagnosisAkhir']],
            'tindakan',
            ['farmasi' => ['obatSaatKunjungan', 'obatPulang']],
            ['diet' => ['rekomendasiDiet', 'dietDiberikan']],
            'edukasi',
            'kondisiPulang',
            'rencanaTindakLanjut',
            'perjalananKunjungan',
        ],
        // IGD: dua section khusus di depan, dan TIDAK punya Diet maupun Edukasi.
        'ugd' => [
            'asesmenAwalIgd',
            'skrining',
            ['anamnesis' => self::ANAK_ANAMNESIS],
            ['pemeriksaanFisik' => ['tandaVital', 'headToToe']],
            'pemeriksaanFungsional',
            'perencanaanPerawatan',
            ['pemeriksaanPenunjang' => ['hasilLab', 'hasilRadiologi']],
            ['diagnosis' => ['diagnosisAwal', 'diagnosisAkhir']],
            'tindakan',
            ['farmasi' => ['obatSaatKunjungan', 'obatPulang']],
            'kondisiPulang',
            'rencanaTindakLanjut',
            'perjalananKunjungan',
        ],
    ];

    /** Kunci section naratif (diisi section.text.div, bukan entry). */
    public const KUNCI_NARATIF = 'perjalananKunjungan';

    /** Composition.category — sama untuk semua jalur. */
    public static function kategoriDokumen(): array
    {
        return ['system' => self::SISTEM_LOINC, 'code' => 'LP173421-1', 'display' => 'Report'];
    }

    /**
     * Composition.type. Jalur yang playbook-nya belum dibaca sengaja melempar,
     * bukan diam-diam memakai kode jalur lain.
     */
    public static function tipeDokumen(string $jalur = 'rj'): array
    {
        [$kode, $display] = self::TIPE_DOKUMEN[$jalur]
            ?? throw new \InvalidArgumentException(
                "Tipe dokumen Resume Medis untuk jalur '{$jalur}' belum ditetapkan — baca playbook jalur tsb dulu."
            );

        return ['system' => self::SISTEM_LOINC, 'code' => $kode, 'display' => $display];
    }

    /**
     * Susunan section siap pakai.
     *
     * Tiap simpul: ['kunci', 'judul', 'kode' => [system, code, display]|null, 'naratif' => bool]
     * Simpul induk membawa 'anak' berisi simpul-simpul daun dan TIDAK menampung entry sendiri.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function daftar(string $jalur = 'rj'): array
    {
        if (!isset(self::SUSUNAN[$jalur])) {
            throw new \InvalidArgumentException(
                "Susunan section Resume Medis untuk jalur '{$jalur}' belum ditetapkan — baca playbook jalur tsb dulu."
            );
        }

        $daftar = [];
        foreach (self::SUSUNAN[$jalur] as $baris) {
            if (is_string($baris)) {
                $daftar[] = self::simpul($baris);
                continue;
            }

            $kunciInduk = array_key_first($baris);
            $daftar[] = self::simpul($kunciInduk) + [
                'anak' => array_map(fn($anak) => self::simpul($anak), $baris[$kunciInduk]),
            ];
        }

        return $daftar;
    }

    /** Semua kunci slot yang bisa diisi pemanggil (termasuk anak), urut tampil. */
    public static function daftarKunci(string $jalur = 'rj'): array
    {
        $kunci = [];
        foreach (self::daftar($jalur) as $section) {
            if (!empty($section['anak'])) {
                foreach ($section['anak'] as $anak) {
                    $kunci[] = $anak['kunci'];
                }
                continue;
            }
            $kunci[] = $section['kunci'];
        }

        return $kunci;
    }

    /** Judul slot untuk pesan "section kosong" di kartu pengirim. */
    public static function judulKunci(string $kunci, string $jalur = 'rj'): string
    {
        foreach (self::daftar($jalur) as $section) {
            if ($section['kunci'] === $kunci) {
                return $section['judul'];
            }
            foreach ($section['anak'] ?? [] as $anak) {
                if ($anak['kunci'] === $kunci) {
                    return $section['judul'] . ' — ' . $anak['judul'];
                }
            }
        }

        return $kunci;
    }

    private static function simpul(string $kunci): array
    {
        [$judul, $sistem, $kode, $display] = self::KAMUS[$kunci]
            ?? throw new \InvalidArgumentException("Simpul Resume Medis '{$kunci}' tidak dikenal.");

        return [
            'kunci' => $kunci,
            'judul' => $judul,
            'kode' => $sistem ? ['system' => $sistem, 'code' => $kode, 'display' => $display] : null,
            'naratif' => $kunci === self::KUNCI_NARATIF,
        ];
    }
}
