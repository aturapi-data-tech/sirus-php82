<?php

namespace App\Http\Traits\Txn\Rj;

use App\Support\KelengkapanEmr;

/**
 * Hitung persentase kelengkapan EMR RJ dari JSON datadaftarpolirj_json.
 *
 * Bobot section (weighted) — sejak 2026-10-28 (lihat App\Support\KelengkapanEmr):
 *   S — Anamnesa       15%
 *   O — Pemeriksaan    20%
 *   A — Diagnosa       25%
 *   P — Perencanaan    30%  (+ Kondisi Saat Pulang untuk kunjungan mulai 28/10/2026)
 *   N — Penilaian      10%
 * Koding SNOMED TIDAK masuk % — indikator terpisah 'koding' (KelengkapanEmr::kodingSatuSehat).
 *
 * Aturan "terisi":
 *   - Field "screening" (alergi, riwayat penyakit dahulu) — boleh berisi
 *     teks "Tidak ada" / "-"; yang penting NON-EMPTY. Dokter wajib explicit
 *     mengisi negatif, jangan dibiarkan kosong begitu saja.
 *   - Field "konten" (keluhan utama, RPS) — tetap pakai filled() biasa,
 *     karena memang harus berisi konten klinis real.
 *   - Penilaian (nyeri/risiko jatuh) — terisi kalau ada minimal 1 entry
 *     pada array.
 *
 * Pure function — tidak query DB, aman dipanggil per-row di list (N rows).
 */
trait EmrCompletenessRJTrait
{
    /**
     * @return array{emr: int, sections: array{s: int, o: int, a: int, p: int, n: int}, koding: int|null}
     */
    public function calculateEmrPercentRJ(array $json): array
    {
        $s = $this->scoreAnamnesaRJ($json['anamnesa'] ?? []);
        $o = $this->scorePemeriksaanRJ($json['pemeriksaan'] ?? []);
        $a = $this->scoreDiagnosaRJ($json);
        $p = $this->scorePerencanaanRJ($json['perencanaan'] ?? [], KelengkapanEmr::wajibKondisiPulang($json['rjDate'] ?? null));
        $n = $this->scorePenilaianRJ($json['penilaian'] ?? []);

        $total = ($s * 0.15) + ($o * 0.20) + ($a * 0.25) + ($p * 0.30) + ($n * 0.10);

        return [
            'emr' => (int) round($total),
            'sections' => [
                's' => (int) round($s),
                'o' => (int) round($o),
                'a' => (int) round($a),
                'p' => (int) round($p),
                'n' => (int) round($n),
            ],
            // Kesiapan Coding SATUSEHAT — terpisah, TIDAK masuk 'emr'. null = belum ada teks yang bisa dikode.
            'koding' => KelengkapanEmr::kodingSatuSehat($json['anamnesa'] ?? [])['persen'],
        ];
    }

    /**
     * S — Anamnesa: 5 field wajib.
     *   - keluhanUtama, RPS  → konten klinis (harus berisi konten real)
     *   - alergi, RPD        → screening (boleh "Tidak ada", asal NON-EMPTY)
     *   - jamDatang          → audit trail (waktu pasien datang)
     */
    private function scoreAnamnesaRJ(array $a): int
    {
        if (empty($a)) {
            return 0;
        }

        $checks = [
            filled($a['keluhanUtama']['keluhanUtama'] ?? null),
            filled($a['riwayatPenyakitSekarangUmum']['riwayatPenyakitSekarangUmum'] ?? null),
            filled($a['alergi']['alergi'] ?? null) || filled($a['alergi']['adaAlergi'] ?? null),
            filled($a['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? null),
            filled($a['pengkajianPerawatan']['jamDatang'] ?? null),
        ];

        return (int) round((array_sum($checks) / count($checks)) * 100);
    }

    /**
     * O — Pemeriksaan: 8 field wajib (tanda vital + nutrisi + kesadaran).
     */
    private function scorePemeriksaanRJ(array $p): int
    {
        if (empty($p)) {
            return 0;
        }

        $tv = $p['tandaVital'] ?? [];
        $nutrisi = $p['nutrisi'] ?? [];

        $checks = [
            filled($tv['frekuensiNadi'] ?? null),
            filled($tv['frekuensiNafas'] ?? null),
            filled($tv['suhu'] ?? null),
            filled($tv['sistolik'] ?? null),
            filled($tv['distolik'] ?? null),
            filled($tv['tingkatKesadaran'] ?? null),
            filled($nutrisi['bb'] ?? null),
            filled($nutrisi['tb'] ?? null),
        ];

        return (int) round((array_sum($checks) / count($checks)) * 100);
    }

    /**
     * A — Diagnosa: minimal 1 entry (ICD-10 ATAU free-text).
     */
    private function scoreDiagnosaRJ(array $json): int
    {
        $hasIcd = !empty($json['diagnosis']) && is_array($json['diagnosis']);
        $hasFreeText = filled($json['diagnosisFreeText'] ?? null);

        return ($hasIcd || $hasFreeText) ? 100 : 0;
    }

    /**
     * P — Perencanaan: 2 komponen (+1 untuk kunjungan mulai 28/10/2026).
     *   - Treatment plan (terapi ATAU tindakLanjut, salah satu cukup)
     *   - TTD dokter pemeriksa (drPemeriksa)
     *   - Kondisi Saat Pulang (wajib SATUSEHAT) — hanya bila $wajibKondisiPulang
     */
    private function scorePerencanaanRJ(array $p, bool $wajibKondisiPulang = false): int
    {
        if (empty($p)) {
            return 0;
        }

        $checks = [
            filled($p['terapi']['terapi'] ?? null) || filled($p['tindakLanjut']['tindakLanjut'] ?? null),
            filled($p['pengkajianMedis']['drPemeriksa'] ?? null),
        ];
        if ($wajibKondisiPulang) {
            $checks[] = filled($p['kondisiPulang']['kondisiPulang'] ?? null);
        }

        return (int) round((array_sum($checks) / count($checks)) * 100);
    }

    /**
     * N — Penilaian: nyeri + risiko jatuh (minimal 1 entry per assessment).
     */
    private function scorePenilaianRJ(array $p): int
    {
        if (empty($p)) {
            return 0;
        }

        $hasNyeri = KelengkapanEmr::adaEntriPenilaian($p['nyeri'] ?? null);
        $hasResikoJatuh = KelengkapanEmr::adaEntriPenilaian($p['resikoJatuh'] ?? null);

        $score = ($hasNyeri ? 1 : 0) + ($hasResikoJatuh ? 1 : 0);
        return (int) round(($score / 2) * 100);
    }

    /**
     * Checklist per-section: label + filled status untuk setiap field yang
     * dihitung di score* methods. Dipakai modal info-kelengkapan-emr agar
     * user tahu field mana yang sudah/belum diisi (bukan cuma % total).
     *
     * @return array<string, array{label: string, weight: int, items: array<int, array{label: string, filled: bool}>}>
     */
    public function collectChecklistRJ(array $json): array
    {
        $a = $json['anamnesa'] ?? [];
        $p = $json['pemeriksaan'] ?? [];
        $r = $json['perencanaan'] ?? [];
        $n = $json['penilaian'] ?? [];
        $tv = $p['tandaVital'] ?? [];
        $nutrisi = $p['nutrisi'] ?? [];

        return [
            's' => [
                'label' => 'Anamnesa',
                'weight' => 15,
                'items' => [
                    ['label' => 'Keluhan utama', 'filled' => filled($a['keluhanUtama']['keluhanUtama'] ?? null)],
                    ['label' => 'Riwayat penyakit sekarang', 'filled' => filled($a['riwayatPenyakitSekarangUmum']['riwayatPenyakitSekarangUmum'] ?? null)],
                    ['label' => 'Alergi (boleh "Tidak ada")', 'filled' => filled($a['alergi']['alergi'] ?? null) || filled($a['alergi']['adaAlergi'] ?? null)],
                    ['label' => 'Riwayat penyakit dahulu (boleh "Tidak ada")', 'filled' => filled($a['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? null)],
                    ['label' => 'Waktu datang', 'filled' => filled($a['pengkajianPerawatan']['jamDatang'] ?? null)],
                ],
            ],
            'o' => [
                'label' => 'Pemeriksaan',
                'weight' => 20,
                'items' => [
                    ['label' => 'Frekuensi nadi', 'filled' => filled($tv['frekuensiNadi'] ?? null)],
                    ['label' => 'Frekuensi napas', 'filled' => filled($tv['frekuensiNafas'] ?? null)],
                    ['label' => 'Suhu', 'filled' => filled($tv['suhu'] ?? null)],
                    ['label' => 'Tekanan darah sistolik', 'filled' => filled($tv['sistolik'] ?? null)],
                    ['label' => 'Tekanan darah distolik', 'filled' => filled($tv['distolik'] ?? null)],
                    ['label' => 'Tingkat kesadaran', 'filled' => filled($tv['tingkatKesadaran'] ?? null)],
                    ['label' => 'Berat badan', 'filled' => filled($nutrisi['bb'] ?? null)],
                    ['label' => 'Tinggi badan', 'filled' => filled($nutrisi['tb'] ?? null)],
                ],
            ],
            'a' => [
                'label' => 'Diagnosa',
                'weight' => 25,
                'items' => [
                    [
                        'label' => 'Minimal 1 diagnosa (ICD-10 atau free-text)',
                        'filled' => (!empty($json['diagnosis']) && is_array($json['diagnosis'])) || filled($json['diagnosisFreeText'] ?? null),
                    ],
                ],
            ],
            'p' => [
                'label' => 'Perencanaan',
                'weight' => 30,
                'items' => array_merge([
                    [
                        'label' => 'Terapi ATAU tindak lanjut',
                        'filled' => filled($r['terapi']['terapi'] ?? null) || filled($r['tindakLanjut']['tindakLanjut'] ?? null),
                    ],
                    ['label' => 'TTD dokter pemeriksa', 'filled' => filled($r['pengkajianMedis']['drPemeriksa'] ?? null)],
                ], KelengkapanEmr::wajibKondisiPulang($json['rjDate'] ?? null)
                    ? [['label' => 'Kondisi saat pulang (wajib SATUSEHAT)', 'filled' => filled($r['kondisiPulang']['kondisiPulang'] ?? null)]]
                    : []),
            ],
            'n' => [
                'label' => 'Penilaian',
                'weight' => 10,
                'items' => [
                    ['label' => 'Penilaian nyeri (min 1 entry)', 'filled' => KelengkapanEmr::adaEntriPenilaian($n['nyeri'] ?? null)],
                    ['label' => 'Risiko jatuh (min 1 entry)', 'filled' => KelengkapanEmr::adaEntriPenilaian($n['resikoJatuh'] ?? null)],
                ],
            ],
        ];
    }
}
