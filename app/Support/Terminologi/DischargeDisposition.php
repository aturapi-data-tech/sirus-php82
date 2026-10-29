<?php

namespace App\Support\Terminologi;

/**
 * Status pulang RI (`perencanaan.tindakLanjut.tindakLanjutKode`) → SNOMED CT
 * untuk `Encounter.hospitalization.dischargeDisposition` (SATUSEHAT).
 *
 * Kode SNOMED-nya sudah tersimpan di EMR (opsi di rm-perencanaan-ri-actions), helper ini
 * hanya melengkapi `display` + menambal kode lama yang salah arti.
 *
 * ⚠️ RIWAYAT BUG (ditemukan 2026-07-15, semua diverifikasi via tx.fhir.org):
 *   - `266707007` dipakai untuk "Pulang atas Permintaan Sendiri", padahal artinya
 *     **"Drug addiction therapy"** — sama sekali tak berhubungan.
 *   - `371828006` dipakai untuk "Pulang Tanpa Perbaikan", padahal artinya
 *     **"Patient deceased during stay"** — pasien yang pulang HIDUP akan terlapor MENINGGAL.
 * Opsi di modul sudah dibetulkan, tapi kode lama TERLANJUR TERSIMPAN di record lama
 * (saat ditemukan: 1 record 266707007, 2 record 371828006). LEGACY_FIX menambalnya
 * saat kirim, supaya record lama tak ikut salah lapor. Belum ada yang terkirim ke
 * SATUSEHAT waktu itu, jadi tak ada data nasional yang perlu diralat.
 *
 * "Pulang Tanpa Perbaikan" tidak punya padanan SNOMED yang bersih → dipetakan ke
 * `371827001` (Patient discharged alive): benar secara fakta (pasien pulang hidup),
 * nuansa "tanpa perbaikan" tetap tersimpan di EMR lewat label Indonesianya.
 */
class DischargeDisposition
{
    /**
     * SUMBER TUNGGAL opsi status pulang RI.
     *
     * `kode` = KUNCI INTERNAL (value radio + tersimpan di JSON 2.613 record) — bentuknya
     * kebetulan SNOMED, tapi 2 di antaranya salah arti; JANGAN dibetulkan di sini, kunci
     * wajib unik & stabil. SNOMED yang benar diresolusi fromKode(). `bpjs` = status pulang BPJS.
     *
     * Sebelumnya peta ini disalin di 3 tempat (form perencanaan, cetak ringkasan pulang,
     * helper ini) dengan label yang BERTENTANGAN — cetakan menulis `371828006` = "Membaik",
     * form menulis "Pulang Tanpa Perbaikan" (berlawanan!), SNOMED sendiri berarti "Patient
     * deceased during stay". Label yang benar = versi form (keputusan user 2026-07-15).
     */
    public const OPTIONS = [
        ['tindakLanjut' => 'Pulang Sehat',                     'tindakLanjutKode' => '371827001', 'tindakLanjutKodeBpjs' => 1],
        ['tindakLanjut' => 'Pulang dengan Permintaan Sendiri', 'tindakLanjutKode' => '266707007', 'tindakLanjutKodeBpjs' => 3],
        ['tindakLanjut' => 'Pulang Pindah / Rujuk',            'tindakLanjutKode' => '306206005', 'tindakLanjutKodeBpjs' => 5],
        ['tindakLanjut' => 'Pulang Tanpa Perbaikan',           'tindakLanjutKode' => '371828006', 'tindakLanjutKodeBpjs' => 5],
        ['tindakLanjut' => 'Meninggal',                        'tindakLanjutKode' => '419099009', 'tindakLanjutKodeBpjs' => 4],
        ['tindakLanjut' => 'Lain-lain',                        'tindakLanjutKode' => '74964007',  'tindakLanjutKodeBpjs' => 5],
    ];

    /** Label Indonesia untuk kunci internal — dipakai form & cetakan. */
    public static function label(?string $kode): string
    {
        $k = trim((string) $kode);
        foreach (self::OPTIONS as $o) {
            if ($o['tindakLanjutKode'] === $k) {
                return $o['tindakLanjut'];
            }
        }

        return '';
    }

    /** Kode SNOMED sah → display resmi (dari terminology server). */
    private const SNOMED = [
        '371827001' => 'Patient discharged alive',
        '225928004' => 'Patient self-discharge against medical advice',
        '306206005' => 'Referral to service',
        '419099009' => 'Dead',
        '74964007'  => 'Other',
    ];

    /** Kode lama yang salah arti → kode yang benar. Lihat catatan kelas. */
    private const LEGACY_FIX = [
        '266707007' => '225928004', // "Drug addiction therapy" → self-discharge against medical advice
        '371828006' => '371827001', // "Patient deceased during stay" → discharged alive
    ];

    /**
     * @return array{code: string, display: string}|null null bila kode kosong/tak dikenal
     */
    public static function fromKode(?string $kode): ?array
    {
        $k = trim((string) $kode);
        if ($k === '') {
            return null;
        }

        $k = self::LEGACY_FIX[$k] ?? $k;

        if (!isset(self::SNOMED[$k])) {
            return null; // tak dikenal → jangan tebak, lebih baik tanpa dischargeDisposition
        }

        return ['code' => $k, 'display' => self::SNOMED[$k]];
    }

    /**
     * Tindak Lanjut RAWAT JALAN (`perencanaan.tindakLanjut.tindakLanjut`) → CodeSystem HL7
     * discharge-disposition — Playbook RJ bab 26–27 (Cara Keluar & Pembaharuan Kunjungan).
     *
     * Beda sistem dengan RI (SNOMED, fromKode()) DISENGAJA: RJ menyamakan dengan alur
     * Rawat Jalan apikesehatan.com, yang memakai HL7 dan memberi contoh "oth" untuk
     * pasien yang dirawat inap. Kode diverifikasi di tx.fhir.org (2026-10-28).
     * MRS & Lain-lain tak punya padanan khusus → `oth`, maknanya dibawa `text`.
     */
    private const RAWAT_JALAN = [
        'Kontrol'           => ['code' => 'home',      'display' => 'Home'],
        'Perawatan Selesai' => ['code' => 'home',      'display' => 'Home'],
        'PRB'               => ['code' => 'home',      'display' => 'Home'],
        'Rujuk'             => ['code' => 'other-hcf', 'display' => 'Other healthcare facility'],
        'MRS'               => ['code' => 'oth',       'display' => 'Other'],
        'Lain-lain'         => ['code' => 'oth',       'display' => 'Other'],
    ];

    private const LABEL_RAWAT_JALAN = [
        'Kontrol'           => 'Pulang, kontrol kembali',
        'Perawatan Selesai' => 'Pulang, perawatan selesai',
        'PRB'               => 'Pulang, program rujuk balik (PRB)',
        'Rujuk'             => 'Dirujuk ke fasyankes lain',
        'MRS'               => 'Dirawat inap (MRS)',
        'Lain-lain'         => 'Lain-lain',
    ];

    /**
     * @return array|null CodeableConcept siap pakai; null bila tindak lanjut kosong/tak dikenal
     *                    (jangan menebak — lebih baik tanpa dischargeDisposition)
     */
    public static function rawatJalan(?string $tindakLanjut, ?string $keterangan = null): ?array
    {
        $tindakLanjut = trim((string) $tindakLanjut);
        if (!isset(self::RAWAT_JALAN[$tindakLanjut])) {
            return null;
        }

        $teks = self::LABEL_RAWAT_JALAN[$tindakLanjut];
        $keterangan = trim((string) $keterangan);
        if ($keterangan !== '') {
            $teks .= ' — ' . $keterangan;
        }

        return [
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/discharge-disposition',
                'code'    => self::RAWAT_JALAN[$tindakLanjut]['code'],
                'display' => self::RAWAT_JALAN[$tindakLanjut]['display'],
            ]],
            'text' => $teks,
        ];
    }

    /**
     * Pasien RJ yang ditransfer ke IGD lewat kasir (rstxn_ugdbiayaselamadirjs). Tindak Lanjut RJ
     * tak punya opsi "ke IGD", dan tabel cara keluar Kemkes tak punya kode khusus perpindahan
     * internal → `oth` + teks (pola sama "Dirawat inap (MRS)"). Data transfer kasir = sumber pasti,
     * jadi mengalahkan Tindak Lanjut dokter.
     */
    public static function pindahKeIgd(string $ugdRjNo): array
    {
        return [
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/discharge-disposition',
                'code'    => 'oth',
                'display' => 'Other',
            ]],
            'text' => 'Dipindahkan ke IGD (UGD #' . $ugdRjNo . ')',
        ];
    }

    /**
     * Pilihan khusus UGD di luar peta rawat jalan. "Meninggal" = `exp` (alur IGD apikesehatan.com);
     * KRS & APS = nilai daftar opsi LAMA yang masih tersimpan di record UGD lama (lihat catatan
     * tindakLanjutOptions di rm-perencanaan-ugd-actions). Kode diverifikasi tx.fhir.org 2026-10-28.
     */
    private const GAWAT_DARURAT = [
        'Meninggal' => ['code' => 'exp',     'display' => 'Expired',             'teks' => 'Meninggal'],
        'KRS'       => ['code' => 'home',    'display' => 'Home',                'teks' => 'Pulang (KRS)'],
        'APS'       => ['code' => 'aadvice', 'display' => 'Left against advice', 'teks' => 'Pulang atas permintaan sendiri (APS)'],
    ];

    /**
     * Tindak Lanjut UGD (`perencanaan.tindakLanjut.tindakLanjut`) → HL7 discharge-disposition —
     * Playbook IGD bab 26–27. Pilihan yang sama dengan RJ memakai peta rawat jalan supaya RJ &
     * UGD tak berbeda diam-diam; sisanya dari GAWAT_DARURAT.
     */
    public static function gawatDarurat(?string $tindakLanjut, ?string $keterangan = null): ?array
    {
        $peta = self::GAWAT_DARURAT[trim((string) $tindakLanjut)] ?? null;
        if ($peta === null) {
            return self::rawatJalan($tindakLanjut, $keterangan);
        }

        $keterangan = trim((string) $keterangan);

        return [
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/discharge-disposition',
                'code'    => $peta['code'],
                'display' => $peta['display'],
            ]],
            'text' => $peta['teks'] . ($keterangan !== '' ? ' — ' . $keterangan : ''),
        ];
    }

    /**
     * Status pulang RI (tindakLanjutKode, kunci internal ber-bentuk SNOMED) → HL7 discharge-disposition,
     * Playbook RI bab 27–28. Keputusan user 2026-10-28: RI DISAMAKAN dengan RJ/UGD & alur Rawat Inap
     * apikesehatan.com (HL7), menggantikan SNOMED fromKode() di Encounter. Kode SNOMED di EMR tetap
     * tersimpan apa adanya (dipakai cetak & status BPJS). Kode lama salah arti ikut dipetakan menurut
     * MAKSUD labelnya: 266707007 (APS) → aadvice, 371828006 (Pulang Tanpa Perbaikan) → home.
     */
    private const RAWAT_INAP = [
        '371827001' => ['code' => 'home',      'display' => 'Home'],
        '371828006' => ['code' => 'home',      'display' => 'Home'],
        '266707007' => ['code' => 'aadvice',   'display' => 'Left against advice'],
        '225928004' => ['code' => 'aadvice',   'display' => 'Left against advice'],
        '306206005' => ['code' => 'other-hcf', 'display' => 'Other healthcare facility'],
        '419099009' => ['code' => 'exp',       'display' => 'Expired'],
        '74964007'  => ['code' => 'oth',       'display' => 'Other'],
    ];

    /** @return array|null CodeableConcept HL7; null bila kosong/tak dikenal (jangan menebak) */
    public static function rawatInap(?string $kode, ?string $keterangan = null): ?array
    {
        $kode = trim((string) $kode);
        $peta = self::RAWAT_INAP[$kode] ?? null;
        if ($peta === null) {
            return null;
        }
        $keterangan = trim((string) $keterangan);

        return [
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/discharge-disposition',
                'code'    => $peta['code'],
                'display' => $peta['display'],
            ]],
            'text' => (self::label($kode) ?: $peta['display']) . ($keterangan !== '' ? ' — ' . $keterangan : ''),
        ];
    }
}
