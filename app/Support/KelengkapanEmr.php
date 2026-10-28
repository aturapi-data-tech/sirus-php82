<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Aturan bersama kelengkapan EMR RJ/UGD/RI (EmrCompleteness*Trait) — murni, tanpa query DB.
 *
 * Perubahan 2026-10-28 (keputusan user):
 *   - Koding SNOMED TIDAK lagi masuk % EMR: kode diisi coder di layar Coding SATUSEHAT sesudah
 *     pelayanan, jadi bukan beban pengisi EMR. Ditampilkan terpisah lewat kodingSatuSehat().
 *   - Kondisi Saat Pulang (wajib SATUSEHAT) dihitung di Perencanaan hanya untuk kunjungan
 *     mulai 28/10/2026 (RI: pasien PULANG mulai tanggal itu) — angka lama tidak bergeser.
 *   - Kolom tambahan opsional (keluhan penyerta, riwayat keluarga, riwayat pengobatan, status
 *     psikologis, prognosis) TAMPIL di daftar periksa dengan tanda 'opsional' => true, TIDAK dihitung.
 */
final class KelengkapanEmr
{
    public const MULAI_KONDISI_PULANG = '28/10/2026';

    /** Tanggal 'd/m/Y[ H:i:s]' ≥ MULAI_KONDISI_PULANG → kondisi pulang ikut dinilai. Kosong/tak terbaca = tidak. */
    public static function wajibKondisiPulang(?string $tanggal): bool
    {
        $teks = substr(trim((string) $tanggal), 0, 10);
        if ($teks === '') {
            return false;
        }
        try {
            return Carbon::createFromFormat('d/m/Y', $teks)->startOfDay()
                ->gte(Carbon::createFromFormat('d/m/Y', self::MULAI_KONDISI_PULANG)->startOfDay());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Penilaian (nyeri / risiko jatuh / …) dianggap ada bila: daftar entri tak kosong (format kini), atau
     * objek format lama yang punya minimal satu nilai terisi DI LUAR daftar pilihan (*Options) — objek lama
     * UGD sering hanya berisi daftar pilihan kosong dan dulu keliru terhitung "terisi".
     */
    public static function adaEntriPenilaian(mixed $node): bool
    {
        if (!is_array($node) || $node === []) {
            return false;
        }
        if (array_is_list($node)) {
            return true;
        }

        return self::adaNilaiTerisi($node);
    }

    private static function adaNilaiTerisi(array $node): bool
    {
        foreach ($node as $kunci => $nilai) {
            if (is_string($kunci) && str_ends_with($kunci, 'Options')) {
                continue;
            }
            if (is_array($nilai) ? self::adaNilaiTerisi($nilai) : filled($nilai)) {
                return true;
            }
        }

        return false;
    }

    /** Status psikologis terisi: ada toggle dicentang (termasuk "tidak ada kelainan") atau keterangan lain. */
    public static function statusPsikologisTerisi(array $statusPsikologis): bool
    {
        return \App\Support\Terminologi\StatusPsikologisMap::aktif($statusPsikologis) !== []
            || filled($statusPsikologis['sebutstatusPsikologis'] ?? null);
    }

    /** Alergi ter-coding: "tidak ada" (716186003 di snomedCode) ATAU daftar alergen ber-kode (layar Coding). */
    public static function alergiTerkode(array $alergi): bool
    {
        if (filled($alergi['snomedCode'] ?? null)) {
            return true;
        }
        foreach ((array) ($alergi['alergenList'] ?? []) as $alergen) {
            if (filled($alergen['kode'] ?? $alergen['snomedCode'] ?? $alergen['kfaCode'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indikator kesiapan Coding SATUSEHAT RJ/UGD (TIDAK masuk % EMR): Keluhan Utama & Alergi yang
     * teksnya terisi — sudah dikode atau belum. Field kosong tidak dinilai (urusan EMR).
     *
     * @return array{persen: int|null, items: array<int, array{label: string, value: string, code: string, displayId: string, terkode: bool}>}
     */
    public static function kodingSatuSehat(array $anamnesa): array
    {
        $keluhan = (array) ($anamnesa['keluhanUtama'] ?? []);
        $alergi = (array) ($anamnesa['alergi'] ?? []);
        $kodeAlergen = collect((array) ($alergi['alergenList'] ?? []))
            ->map(fn($alergen) => (string) ($alergen['kode'] ?? $alergen['snomedCode'] ?? $alergen['kfaCode'] ?? ''))
            ->filter()->implode(', ');

        $items = [
            [
                'label' => 'Keluhan Utama',
                'value' => (string) ($keluhan['keluhanUtama'] ?? ''),
                'code' => (string) ($keluhan['snomedCode'] ?? ''),
                'displayId' => (string) ($keluhan['snomedDisplayId'] ?? ''),
                'terkode' => filled($keluhan['snomedCode'] ?? null),
            ],
            [
                'label' => 'Alergi',
                'value' => (string) ($alergi['alergi'] ?? ''),
                'code' => (string) (($alergi['snomedCode'] ?? '') ?: $kodeAlergen),
                'displayId' => (string) ($alergi['snomedDisplayId'] ?? ''),
                'terkode' => self::alergiTerkode($alergi),
            ],
        ];

        $dinilai = array_filter($items, fn($item) => filled($item['value']));

        return [
            'persen' => $dinilai === [] ? null : (int) round(count(array_filter($dinilai, fn($item) => $item['terkode'])) / count($dinilai) * 100),
            'items' => $items,
        ];
    }
}
