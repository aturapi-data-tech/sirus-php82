<?php

namespace App\Support\Options;

/**
 * Opsi & peta label Pengkajian Medis (Dokter) Rawat Inap (node JSON pengkajianDokter).
 * SATU sumber untuk form EMR (rm-pengkajian-dokter-ri) + cetak (RM-03.12).
 */
class PengkajianDokterRiOptions
{
    /** Bagian anatomi (pengkajianDokter.anatomi.<kunci>) — urutan tampil di form & cetak. */
    public const ANATOMI = [
        'kepala' => 'Kepala',
        'mata' => 'Mata',
        'telinga' => 'Telinga',
        'hidung' => 'Hidung',
        'rambut' => 'Rambut',
        'bibir' => 'Bibir',
        'gigiGeligi' => 'Gigi Geligi',
        'lidah' => 'Lidah',
        'langitLangit' => 'Langit-Langit',
        'leher' => 'Leher',
        'tenggorokan' => 'Tenggorokan',
        'tonsil' => 'Tonsil',
        'dada' => 'Dada',
        'payudara' => 'Payudara',
        'punggung' => 'Punggung',
        'perut' => 'Perut',
        'genital' => 'Genital',
        'anus' => 'Anus',
        'lenganAtas' => 'Lengan Atas',
        'lenganBawah' => 'Lengan Bawah',
        'jariTangan' => 'Jari Tangan',
        'kukuTangan' => 'Kuku Tangan',
        'persendianTangan' => 'Persendian Tangan',
        'tungkaiAtas' => 'Tungkai Atas',
        'tungkaiBawah' => 'Tungkai Bawah',
        'jariKaki' => 'Jari Kaki',
        'kukuKaki' => 'Kuku Kaki',
        'persendianKaki' => 'Persendian Kaki',
        'faring' => 'Faring',
    ];

    /** Nilai kelainan anatomi => label. */
    public const KELAINAN = ['Tidak Diperiksa' => 'Tidak Diperiksa', 'Tidak Ada Kelainan' => 'Tidak Ada Kelainan', 'Ada' => 'Ada Kelainan'];

    /** Rencana (pengkajianDokter.rencana.<kunci>) — urutan tampil. */
    public const RENCANA = [
        'penegakanDiagnosa' => 'Penegakan Diagnosis',
        'terapi' => 'Terapi',
        'terapiPulang' => 'Terapi Pulang',
        'diet' => 'Diet',
        'edukasi' => 'Edukasi',
        'monitoring' => 'Monitoring',
    ];

    /**
     * Bagian anatomi yang DIPERIKSA (kelainan ≠ "Tidak Diperiksa") — untuk cetak, supaya 29 baris
     * "Tidak Diperiksa" tidak memenuhi kertas. @return list<array{label: string, kelainan: string, deskripsi: string}>
     */
    public static function anatomiDiperiksa(array $anatomi): array
    {
        $hasil = [];
        foreach (self::ANATOMI as $kunci => $label) {
            $kelainan = (string) data_get($anatomi, "{$kunci}.kelainan", 'Tidak Diperiksa');
            if ($kelainan === '' || $kelainan === 'Tidak Diperiksa') {
                continue;
            }
            $hasil[] = [
                'label' => $label,
                'kelainan' => self::KELAINAN[$kelainan] ?? $kelainan,
                'deskripsi' => $kelainan === 'Ada' ? (string) data_get($anatomi, "{$kunci}.desc", '') : '',
            ];
        }

        return $hasil;
    }
}
