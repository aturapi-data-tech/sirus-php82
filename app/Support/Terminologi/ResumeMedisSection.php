<?php

namespace App\Support\Terminologi;

/**
 * Definisi 13 section Resume Medis (Composition) SATUSEHAT.
 *
 * Sumber: playbook "Resume Medis - Rawat Jalan" Bab 28 (sunting 2 Desember 2025);
 * salinan tabel lengkapnya ada di docs/satusehat-api.md §9.4.
 *
 * Composition BUKAN dokumen naratif — ia indeks resource yang sudah dikirim selama
 * kunjungan, dirangkai lewat section[].entry. Hanya section 13 (Perjalanan Kunjungan
 * Pasien) yang benar-benar naratif lewat section.text.div.
 *
 * Ditaruh sebagai helper statis (bukan trait) mengikuti pola PenilaianObservationMap:
 * dipakai bersama RJ/RI/UGD tanpa menambah risiko tabrakan nama method di komponen EMR.
 *
 * JANGAN mengganti kode dari hafalan. Kode `TK*` bersistem terminology.kemkes.go.id,
 * sisanya LOINC. Kode tipe dokumen pernah berubah (changelog playbook v6.1 24/10/2024)
 * — sebelum mengubah, baca ulang playbooknya.
 */
class ResumeMedisSection
{
    public const SISTEM_LOINC = 'http://loinc.org';
    public const SISTEM_KEMKES = 'http://terminology.kemkes.go.id';

    /** Tipe dokumen per jalur layanan (Composition.type). */
    private const TIPE_DOKUMEN = [
        'rj' => ['code' => '88645-7', 'display' => 'Outpatient hospital Discharge summary'],
    ];

    /** Composition.category — sama untuk semua jalur. */
    public static function kategoriDokumen(): array
    {
        return [
            'system' => self::SISTEM_LOINC,
            'code' => 'LP173421-1',
            'display' => 'Report',
        ];
    }

    /**
     * Composition.type. Jalur di luar 'rj' belum dibaca playbook-nya — sengaja
     * melempar, bukan diam-diam memakai kode rawat jalan untuk rawat inap.
     */
    public static function tipeDokumen(string $jalur = 'rj'): array
    {
        if (!isset(self::TIPE_DOKUMEN[$jalur])) {
            throw new \InvalidArgumentException(
                "Tipe dokumen Resume Medis untuk jalur '{$jalur}' belum ditetapkan — baca playbook jalur tsb dulu."
            );
        }

        return array_merge(['system' => self::SISTEM_LOINC], self::TIPE_DOKUMEN[$jalur]);
    }

    /**
     * Susunan section resume medis, urut sesuai playbook.
     *
     * Tiap simpul: ['kunci', 'judul', 'kode' => [system, code, display]|null, 'anak' => [...]]
     * - Simpul ber-'anak' TIDAK menampung entry sendiri; entry ada di anaknya.
     * - 'kunci' = nama slot yang dipakai pemanggil untuk menyetorkan referensi resource.
     * - Section 9 (Diet) sengaja TANPA kode di level induk: playbook memang tidak
     *   memberikannya (tampak salah tulis — lihat catatan di docs §9.4). Kalau nanti
     *   dikonfirmasi, tambahkan di sini saja.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function daftar(): array
    {
        return [
            [
                'kunci' => 'anamnesis',
                'judul' => 'Anamnesis',
                'kode' => self::kemkes('TK000003', 'Anamnesis'),
                'anak' => [
                    self::simpul('keluhanUtama', 'Keluhan Utama', self::loinc('10154-3', 'Chief complaint Narrative - Reported')),
                    self::simpul('keluhanPenyerta', 'Keluhan Penyerta', self::loinc('11450-4', 'Problem list - Reported')),
                    self::simpul('riwayatAlergi', 'Riwayat Alergi', self::loinc('48765-2', 'Allergies')),
                    self::simpul('riwayatPenyakitTerdahulu', 'Riwayat Penyakit Pribadi Terdahulu', self::loinc('11348-0', 'History of Past illness Narrative')),
                    self::simpul('riwayatPenyakitSekarang', 'Riwayat Penyakit Pribadi Sekarang', self::loinc('10164-2', 'History of Present illness Narrative')),
                    self::simpul('riwayatPenyakitKeluarga', 'Riwayat Penyakit Keluarga', self::loinc('10157-6', 'History of family member diseases Narrative')),
                    self::simpul('riwayatPengobatan', 'Riwayat Pengobatan', self::loinc('10160-0', 'History of Medication use Narrative')),
                ],
            ],
            [
                'kunci' => 'pemeriksaanFisik',
                'judul' => 'Pemeriksaan Fisik',
                'kode' => self::kemkes('TK000007', 'Pemeriksaan Fisik'),
                'anak' => [
                    self::simpul('tandaVital', 'Tanda Vital', self::loinc('8716-3', 'Vital signs')),
                    self::simpul('headToToe', 'Pemeriksaan Fisik Head to Toe', self::loinc('10187-3', 'Review of systems Narrative - Reported')),
                ],
            ],
            self::simpul('pemeriksaanFungsional', 'Pemeriksaan Fungsional', self::loinc('47420-5', 'Functional status assessment note')),
            self::simpul('perencanaanPerawatan', 'Perencanaan Perawatan', self::loinc('18776-5', 'Plan of care note')),
            [
                'kunci' => 'pemeriksaanPenunjang',
                'judul' => 'Pemeriksaan Penunjang',
                'kode' => self::kemkes('TK000009', 'Hasil Pemeriksaan Penunjang'),
                'anak' => [
                    self::simpul('hasilLab', 'Hasil Pemeriksaan Laboratorium', self::loinc('11502-2', 'Laboratory report')),
                    self::simpul('hasilRadiologi', 'Hasil Pemeriksaan Radiologi', self::loinc('18782-3', 'Radiology Study observation (narrative)')),
                ],
            ],
            [
                'kunci' => 'diagnosis',
                'judul' => 'Diagnosis',
                'kode' => self::kemkes('TK000004', 'Diagnosis'),
                'anak' => [
                    self::simpul('diagnosisAwal', 'Diagnosis Awal', self::loinc('42347-5', 'Admission diagnosis (narrative)')),
                    self::simpul('diagnosisAkhir', 'Diagnosis Akhir', self::loinc('78375-3', 'Discharge diagnosis Narrative')),
                ],
            ],
            self::simpul('tindakan', 'Tindakan/Prosedur Medis', self::kemkes('TK000005', 'Tindakan/Prosedur Medis')),
            [
                'kunci' => 'farmasi',
                'judul' => 'Farmasi',
                'kode' => self::kemkes('TK000013', 'Obat'),
                'anak' => [
                    self::simpul('obatSaatKunjungan', 'Obat Saat Kunjungan', self::loinc('42346-7', 'Medications on admission (narrative)')),
                    self::simpul('obatPulang', 'Obat Pulang', self::loinc('75311-1', 'Discharge medications Narrative')),
                ],
            ],
            [
                'kunci' => 'diet',
                'judul' => 'Diet',
                'kode' => null,
                'anak' => [
                    self::simpul('rekomendasiDiet', 'Rekomendasi Diet', self::loinc('42344-2', 'Discharge diet (narrative)')),
                    self::simpul('dietDiberikan', 'Diet yang diberikan', self::loinc('61144-2', 'Diet and nutrition Narrative')),
                ],
            ],
            self::simpul('edukasi', 'Edukasi', self::loinc('34895-3', 'Education note')),
            self::simpul('kondisiPulang', 'Kondisi Saat Meninggalkan Rumah Sakit', self::loinc('10184-0', 'Hospital discharge physical findings Narrative')),
            self::simpul('rencanaTindakLanjut', 'Rencana Tindak Lanjut', self::loinc('8653-8', 'Hospital Discharge instructions')),
            // Satu-satunya section naratif: diisi section.text.div, bukan entry.
            self::simpul('perjalananKunjungan', 'Perjalanan Kunjungan Pasien', self::loinc('8648-8', 'Hospital course Narrative'), true),
        ];
    }

    /** Semua kunci slot yang bisa diisi pemanggil (termasuk anak), urut tampil. */
    public static function daftarKunci(): array
    {
        $kunci = [];
        foreach (self::daftar() as $section) {
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
    public static function judulKunci(string $kunci): string
    {
        foreach (self::daftar() as $section) {
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

    private static function simpul(string $kunci, string $judul, array $kode, bool $naratif = false): array
    {
        return ['kunci' => $kunci, 'judul' => $judul, 'kode' => $kode, 'naratif' => $naratif];
    }

    private static function loinc(string $kode, string $display): array
    {
        return ['system' => self::SISTEM_LOINC, 'code' => $kode, 'display' => $display];
    }

    private static function kemkes(string $kode, string $display): array
    {
        return ['system' => self::SISTEM_KEMKES, 'code' => $kode, 'display' => $display];
    }
}
