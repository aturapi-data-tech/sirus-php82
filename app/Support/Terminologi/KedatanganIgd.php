<?php

namespace App\Support\Terminologi;

/**
 * Kedatangan pasien IGD — Playbook IGD bab 3a–3c, SUMBER TUNGGAL kode untuk sender
 * kirim-kedatangan UGD dan layar Coding SATUSEHAT UGD. Sumber data: anamnesa.pengkajianPerawatan
 * (sarana transportasi, cara masuk, tingkat kegawatan) yang sudah diisi perawat — tanpa isian baru.
 *
 * Semua kode diverifikasi di tx.fhir.org 2026-10-28. ⚠️ Kode CTAS alur apikesehatan.com
 * DUA salah: level 5 `LA6116-3` tidak ada di LOINC (yang benar `LA10137-0`), dan level 2 di
 * answer list resmi LL3059-4 memang `LA6113-0` dengan display resmi "2 or more" — display
 * itu dipakai apa adanya, jangan "dibetulkan" menjadi "2".
 */
final class KedatanganIgd
{
    /** 3a — Observation code. */
    public const KODE_TRANSPORTASI = ['system' => 'http://loinc.org', 'code' => '74286-6', 'display' => 'Transport mode to hospital [NTDS]'];

    /** 3a — saranaTransportasiId EMR → SNOMED. "Lain-lain" (4) tak punya padanan → dikirim sebagai teks. */
    public const TRANSPORTASI = [
        '1' => ['code' => '49122002', 'display' => 'Ambulance'],
        '2' => ['code' => '71783008', 'display' => 'Automobile'],
        '3' => ['code' => '90748009', 'display' => 'Motorcycle'],
    ];

    /** 3b — Observation code (CodeSystem clinical-term Kemkes, dari playbook; tak bisa dicek di tx.fhir.org). */
    public const KODE_SURAT_RUJUKAN = ['system' => 'http://terminology.kemkes.go.id/CodeSystem/clinical-term', 'code' => 'OC000034', 'display' => 'Surat Pengantar Rujukan'];

    /** 3c — Observation code. */
    public const KODE_TRIASE = ['system' => 'http://loinc.org', 'code' => '75910-0', 'display' => 'Canadian triage and acuity scale [CTAS]'];

    /** 3c — answer list resmi LL3059-4: level CTAS → kode LA + display resmi. */
    public const CTAS = [
        1 => ['code' => 'LA6112-2', 'display' => '1'],
        2 => ['code' => 'LA6113-0', 'display' => '2 or more'],
        3 => ['code' => 'LA6114-8', 'display' => '3'],
        4 => ['code' => 'LA6115-5', 'display' => '4'],
        5 => ['code' => 'LA10137-0', 'display' => '5'],
    ];

    /**
     * 3c — tingkat kegawatan UGD (P0–P3) → level CTAS. P0 (meninggal saat tiba) TIDAK dipetakan:
     * CTAS menilai pasien hidup, jadi tak dikirim (dilaporkan di kartu).
     * ⚠️ USULAN, menunggu konfirmasi user/komite medis: P1 → 1, P2 → 3, P3 → 4.
     */
    public const TRIASE_KE_CTAS = [
        'P1' => 1,
        'P2' => 3,
        'P3' => 4,
    ];

    private const LABEL_TRIASE = [
        'P0' => 'P0 — meninggal saat tiba',
        'P1' => 'P1 — gawat darurat',
        'P2' => 'P2 — darurat / gawat tidak darurat',
        'P3' => 'P3 — tidak gawat tidak darurat',
    ];

    /** @return array{system?: string, code?: string, display?: string, text: string}|null null bila belum diisi */
    public static function transportasi(?string $saranaTransportasiId, ?string $deskripsi, ?string $keterangan = null): ?array
    {
        $id = trim((string) $saranaTransportasiId);
        $teks = trim((string) $deskripsi);
        if ($id === '' && $teks === '') {
            return null;
        }
        $keterangan = trim((string) $keterangan);
        $teksLengkap = ($teks ?: 'Lain-lain') . ($keterangan !== '' ? ' — ' . $keterangan : '');

        $kode = self::TRANSPORTASI[$id] ?? null;

        return $kode === null
            ? ['text' => $teksLengkap]
            : ['system' => 'http://snomed.info/sct', 'code' => $kode['code'], 'display' => $kode['display'], 'text' => $teksLengkap];
    }

    /** 3b — cara masuk "Rujuk" = membawa surat pengantar rujukan. null bila cara masuk belum diisi. */
    public static function membawaSuratRujukan(?string $caraMasukIgd): ?bool
    {
        $caraMasuk = trim((string) $caraMasukIgd);

        return $caraMasuk === '' ? null : $caraMasuk === 'Rujuk';
    }

    /** @return array{system: string, code: string, display: string, text: string}|null null bila kosong / P0 / tak dikenal */
    public static function triase(?string $tingkatKegawatan): ?array
    {
        $tingkat = strtoupper(trim((string) $tingkatKegawatan));
        $level = self::TRIASE_KE_CTAS[$tingkat] ?? null;
        if ($level === null) {
            return null;
        }

        return ['system' => 'http://loinc.org', ...self::CTAS[$level], 'text' => self::labelTriase($tingkat) . ' (CTAS ' . $level . ')'];
    }

    /**
     * Tingkat triase yang dikirim: keputusan perawat di EMR (anamnesa.pengkajianPerawatan.tingkatKegawatan)
     * lebih dulu; bila kosong, saran hitungan Screening UGD (screening.triaseSaran — hanya usulan, P0
     * tak pernah disimpulkan otomatis di sana).
     *
     * @return array{tingkat: string, sumber: string} sumber '' bila keduanya kosong
     */
    public static function tingkatTriase(array $dataUGD): array
    {
        $keputusan = strtoupper(trim((string) ($dataUGD['anamnesa']['pengkajianPerawatan']['tingkatKegawatan'] ?? '')));
        if ($keputusan !== '') {
            return ['tingkat' => $keputusan, 'sumber' => 'EMR'];
        }
        $saran = strtoupper(trim((string) ($dataUGD['screening']['triaseSaran'] ?? '')));

        return ['tingkat' => $saran, 'sumber' => $saran !== '' ? 'saran Screening' : ''];
    }

    public static function labelTriase(?string $tingkatKegawatan): string
    {
        $tingkat = strtoupper(trim((string) $tingkatKegawatan));

        return self::LABEL_TRIASE[$tingkat] ?? $tingkat;
    }
}
