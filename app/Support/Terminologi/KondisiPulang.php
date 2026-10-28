<?php

namespace App\Support\Terminologi;

/**
 * Kondisi Saat Meninggalkan Fasyankes (Playbook RJ bab 25) — SUMBER TUNGGAL pilihan EMR
 * (perencanaan.kondisiPulang.kondisiPulang), tampilan Coding SATUSEHAT, sender
 * kirim-kondisi-pulang, dan cetak. Dikirim sebagai Condition problem-list-item SNOMED.
 *
 * ⚠️ JANGAN menyalin kode alur apikesehatan.com untuk bab ini: 3 dari 4 kodenya SALAH ARTI
 * (diperiksa via tx.fhir.org 2026-10-28) — "Membaik" 271299001 = *worsened*, "Memburuk"
 * 271593001 = *Moribund*, "Tidak Berubah" 162467007 = *Free of symptoms*. Kode di bawah
 * sudah dicari & diverifikasi aktif di tx.fhir.org.
 */
final class KondisiPulang
{
    /** label EMR => [kode SNOMED, display resmi] — urutan = urutan pilihan di form. */
    public const OPTIONS = [
        'Stabil'        => ['code' => '359746009', 'display' => "Patient's condition stable"],
        'Membaik'       => ['code' => '268910001', 'display' => "Patient's condition improved"],
        'Memburuk'      => ['code' => '271299001', 'display' => "Patient's condition worsened"],
        'Tidak Berubah' => ['code' => '359748005', 'display' => 'Patient condition unchanged'],
    ];

    /** @return array{code: string, display: string}|null null bila kosong/tak dikenal (jangan menebak) */
    public static function kode(?string $label): ?array
    {
        return self::OPTIONS[trim((string) $label)] ?? null;
    }
}
