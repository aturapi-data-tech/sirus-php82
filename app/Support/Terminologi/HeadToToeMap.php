<?php

namespace App\Support\Terminologi;

use App\Support\AnatomiPemeriksaan;

/**
 * SUMBER TUNGGAL peta Pemeriksaan Fisik Head to Toe (Observation kategori exam) untuk kartu
 * kirim-head-to-toe RJ/UGD/RI. Kunci = kunci node anatomi EMR.
 *
 * Disalin dari Playbook SATUSEHAT Resume Medis Rawat Jalan bab Hasil Pemeriksaan Fisik (28 bagian,
 * sama dengan Playbook IGD). Dua beda antar halaman playbook diputuskan lewat tx.fhir.org:
 * Kuku Kaki = 770805009 (77085009 di halaman IGD tidak ada di SNOMED) dan Payudara = "Breasts".
 *
 * Bagian form yang tak punya baris di playbook (Faring) sengaja tidak dipetakan: tidak dikirim,
 * tetapi dilaporkan lewat takTerpetakan().
 */
final class HeadToToeMap
{
    public const KATEGORI = [[
        'coding' => [[
            'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
            'code' => 'exam',
            'display' => 'Exam',
        ]],
    ]];

    /** kunci anatomi => [label, LOINC, display LOINC, SNOMED bodySite|null, display bodySite|null] */
    private const BAGIAN = [
        'kepala' => ['Kepala', '10199-8', 'Physical findings of Head Narrative', null, null],
        'mata' => ['Mata', '10197-2', 'Physical findings of Eye Narrative', null, null],
        'telinga' => ['Telinga', '10195-6', 'Physical findings of Ear Narrative', null, null],
        'hidung' => ['Hidung', '10203-8', 'Physical findings of Nose Narrative', null, null],
        'rambut' => ['Rambut', '32436-8', 'Physical findings of Hair', null, null],
        'bibir' => ['Bibir', '32446-7', 'Physical findings of Lip', null, null],
        'gigiGeligi' => ['Gigi Geligi', '85910-8', 'Physical findings of Teeth and Gum Narrative', null, null],
        'lidah' => ['Lidah', '32483-0', 'Physical findings of Tongue', null, null],
        'langitLangit' => ['Langit-Langit', '10201-2', 'Physical findings of Mouth and Throat and Teeth Narrative', '72914001', 'Palatal structure'],
        'leher' => ['Leher', '11411-6', 'Physical findings of Neck Narrative', null, null],
        'tenggorokan' => ['Tenggorokan', '56867-5', 'Physical findings of Throat Narrative', null, null],
        'tonsil' => ['Tonsil', '10201-2', 'Physical findings of Mouth and Throat and Teeth Narrative', '91636008', 'Bilateral palatine tonsils'],
        'dada' => ['Dada', '11391-0', 'Physical findings of Chest Narrative', null, null],
        'payudara' => ['Payudara', '10193-1', 'Physical findings of Breasts Narrative', null, null],
        'punggung' => ['Punggung', '10192-3', 'Physical findings of Back Narrative', null, null],
        'perut' => ['Perut', '10191-5', 'Physical findings of Abdomen Narrative', null, null],
        'genital' => ['Genital', '11400-9', 'Physical findings of Genitalia Narrative', null, null],
        'anus' => ['Anus/Dubur', '11388-6', 'Physical findings of Buttocks Narrative', '53505006', 'Anal structure'],
        'lenganAtas' => ['Lengan Atas', '11386-0', 'Physical findings of Upper Arm Narrative', null, null],
        'lenganBawah' => ['Lengan Bawah', '11398-5', 'Physical findings of Forearm Narrative', null, null],
        'jariTangan' => ['Jari Tangan', '11404-1', 'Physical findings of Hand Narrative', '7569003', 'Finger structure'],
        'kukuTangan' => ['Kuku Tangan', '32456-6', 'Physical findings of Nail', '770812000', 'Entire nail unit of finger'],
        'persendianTangan' => ['Persendian Tangan', '11415-7', 'Physical findings of Wrist Narrative', null, null],
        'tungkaiAtas' => ['Tungkai Atas', '11414-0', 'Physical findings of Thigh Narrative', null, null],
        'tungkaiBawah' => ['Tungkai Bawah', '11389-4', 'Physical findings of Calf Narrative', null, null],
        'jariKaki' => ['Jari Kaki', '11397-7', 'Physical findings of Foot Narrative', '29707007', 'Toe structure'],
        'kukuKaki' => ['Kuku Kaki', '32456-6', 'Physical findings of Nail', '770805009', 'Structure of nail unit of toe'],
        'persendianKaki' => ['Persendian Kaki', '11385-2', 'Physical findings of Ankle Narrative', '26552008', 'Foot joint structure'],
    ];

    /**
     * Bagian yang diperiksa dan punya kode, siap dikirim. "Tidak Diperiksa" berarti tidak ada
     * hasil pemeriksaan, jadi tidak dikirim.
     *
     * @return array<string, array{label: string, code: array, bodySite: ?array, nilai: string}>
     */
    public static function target(array $anatomi): array
    {
        $anatomi = AnatomiPemeriksaan::rapikanKunci($anatomi);
        $hasil = [];

        foreach (self::BAGIAN as $kunci => [$label, $loinc, $display, $kodeLokasi, $displayLokasi]) {
            $nilai = self::nilai($anatomi[$kunci] ?? null);
            if ($nilai === null) {
                continue;
            }

            $hasil[$kunci] = [
                'label' => $label,
                'code' => ['system' => 'http://loinc.org', 'code' => $loinc, 'display' => $display],
                'bodySite' => $kodeLokasi === null ? null : ['system' => 'http://snomed.info/sct', 'code' => $kodeLokasi, 'display' => $displayLokasi],
                'nilai' => $nilai,
            ];
        }

        return $hasil;
    }

    /** Label bagian yang sudah diperiksa di EMR tetapi tak punya kode di playbook. @return list<string> */
    public static function takTerpetakan(array $anatomi): array
    {
        $hasil = [];
        foreach (AnatomiPemeriksaan::rapikanKunci($anatomi) as $kunci => $bagian) {
            if (!isset(self::BAGIAN[$kunci]) && self::nilai($bagian) !== null) {
                $hasil[] = ucfirst((string) $kunci);
            }
        }

        return $hasil;
    }

    /** Teks hasil untuk valueString; null bila bagian ini tidak diperiksa. */
    private static function nilai(mixed $bagian): ?string
    {
        if (!is_array($bagian)) {
            return null;
        }

        $kelainan = trim((string) ($bagian['kelainan'] ?? ''));
        if ($kelainan === '' || $kelainan === 'Tidak Diperiksa') {
            return null;
        }

        $deskripsi = trim((string) ($bagian['desc'] ?? ''));

        if ($kelainan === 'Tidak Ada Kelainan') {
            return $deskripsi === '' ? 'Tidak ada kelainan' : 'Tidak ada kelainan: ' . $deskripsi;
        }

        return $deskripsi === '' ? 'Ada kelainan' : 'Ada kelainan: ' . $deskripsi;
    }
}
