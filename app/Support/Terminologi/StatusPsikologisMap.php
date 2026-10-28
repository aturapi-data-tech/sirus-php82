<?php

namespace App\Support\Terminologi;

/**
 * Status Psikologis anamnesa RJ (anamnesa.statusPsikologis, toggle "1") → SNOMED, untuk
 * Pemeriksaan Fungsional (Playbook RJ bab 5): Observation category `survey`, code LOINC 8693-4
 * Mental status, valueCodeableConcept = SNOMED. Satu Observation per status yang dicentang.
 * Semua kode diverifikasi aktif di tx.fhir.org (2026-10-28) — jangan menambah dari hafalan.
 */
final class StatusPsikologisMap
{
    public const CODE = ['system' => 'http://loinc.org', 'code' => '8693-4', 'display' => 'Mental status'];

    /** key toggle EMR => [label, SNOMED code, display resmi]. 'ccemas' = ejaan key lama, dipertahankan. */
    public const STATUS = [
        'tidakAdaKelainan'   => ['label' => 'Tidak ada kelainan', 'code' => '134417007', 'display' => 'Normal mood'],
        'marah'              => ['label' => 'Marah',              'code' => '75408008',  'display' => 'Feeling angry'],
        'ccemas'             => ['label' => 'Cemas',              'code' => '48694002',  'display' => 'Anxiety'],
        'takut'              => ['label' => 'Takut',              'code' => '1402001',   'display' => 'Fear'],
        'sedih'              => ['label' => 'Sedih',              'code' => '420038007', 'display' => 'Feeling unhappy'],
        'cenderungBunuhDiri' => ['label' => 'Cenderung bunuh diri', 'code' => '6471006', 'display' => 'Suicidal thoughts'],
    ];

    /** @return array<string, array{label: string, code: string, display: string}> status yang dicentang */
    public static function aktif(array $statusPsikologis): array
    {
        // Toggle kosong tersimpan sebagai [] (bukan '') — hanya skalar "1" yang berarti dicentang.
        return array_filter(self::STATUS, function ($peta, $kunci) use ($statusPsikologis) {
            $nilai = $statusPsikologis[$kunci] ?? '';

            return \is_scalar($nilai) && (string) $nilai === '1';
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Status emosional RI (Pengkajian Awal — satu pilihan, key PengkajianAwalRiOptions::STATUS_EMOSIONAL)
     * → SNOMED, diverifikasi tx.fhir.org 2026-10-28. ⚠️ Alur Rawat Inap apikesehatan.com memakai
     * 276746005 untuk "Emotionally stable" — artinya *Environmental event*, SALAH; Kooperatif memakai
     * 134417007 Normal mood (sama dgn "Tidak ada kelainan" RJ). Depresi = 366979004 *Depressed mood*
     * (temuan perawat), BUKAN 35489007 *Depressive disorder* (diagnosis). "Lainnya" tak berkode → tidak dikirim.
     */
    private const STATUS_EMOSIONAL_RI = [
        'kooperatif' => ['label' => 'Kooperatif', 'code' => '134417007', 'display' => 'Normal mood'],
        'cemas'      => ['label' => 'Cemas',      'code' => '48694002',  'display' => 'Anxiety'],
        'depresi'    => ['label' => 'Depresi',    'code' => '366979004', 'display' => 'Depressed mood'],
    ];

    /** @return array<string, array{label: string, code: string, display: string}> 0..1 status, kunci = key opsi */
    public static function aktifRi(?string $kunciOpsi): array
    {
        $kunci = trim((string) $kunciOpsi);

        return isset(self::STATUS_EMOSIONAL_RI[$kunci]) ? [$kunci => self::STATUS_EMOSIONAL_RI[$kunci]] : [];
    }
}
