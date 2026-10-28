<?php

namespace App\Http\Traits\Txn\Ugd;

use App\Support\KelengkapanEmr;

/**
 * Hitung persentase kelengkapan EMR UGD dari JSON datadaftarugd_json.
 *
 * Bobot section (weighted) — sejak 2026-10-28 (lihat App\Support\KelengkapanEmr):
 *   S — Anamnesa         15%
 *   O — Pemeriksaan      15%
 *   A — Diagnosa         20%
 *   P — Perencanaan      25%  (+ Kondisi Saat Keluar untuk kunjungan mulai 28/10/2026)
 *   N — Penilaian        10%
 *   T — Triase/Screening 15%  (khusus UGD — wajib sebelum tindakan)
 * Koding SNOMED TIDAK masuk % — indikator terpisah 'koding' (KelengkapanEmr::kodingSatuSehat).
 *
 * Aturan "terisi" — sama dengan RJ: field screening (alergi, RPD) boleh
 * teks "Tidak ada", asal NON-EMPTY. Dokter wajib explicit isi negatif.
 *
 * Pure function — tidak query DB. Aman dipanggil per-row di list.
 */
trait EmrCompletenessUGDTrait
{
    /**
     * @return array{emr: int, sections: array{s: int, o: int, a: int, p: int, n: int, t: int}, koding: int|null}
     */
    public function calculateEmrPercentUGD(array $json): array
    {
        $s = $this->scoreAnamnesaUGD($json['anamnesa'] ?? []);
        $o = $this->scorePemeriksaanUGD($json['pemeriksaan'] ?? []);
        $a = $this->scoreDiagnosaUGD($json);
        $p = $this->scorePerencanaanUGD($json['perencanaan'] ?? [], KelengkapanEmr::wajibKondisiPulang($json['rjDate'] ?? null));
        $n = $this->scorePenilaianUGD($json['penilaian'] ?? []);
        $t = $this->scoreTriaseUGD($json['screening'] ?? []);

        $total = ($s * 0.15) + ($o * 0.15) + ($a * 0.20) + ($p * 0.25) + ($n * 0.10) + ($t * 0.15);

        return [
            'emr' => (int) round($total),
            'sections' => [
                's' => (int) round($s),
                'o' => (int) round($o),
                'a' => (int) round($a),
                'p' => (int) round($p),
                'n' => (int) round($n),
                't' => (int) round($t),
            ],
            // Kesiapan Coding SATUSEHAT — terpisah, TIDAK masuk 'emr'. null = belum ada teks yang bisa dikode.
            'koding' => KelengkapanEmr::kodingSatuSehat($json['anamnesa'] ?? [])['persen'],
        ];
    }

    /**
     * S — Anamnesa: 5 field wajib (sama dengan RJ).
     */
    private function scoreAnamnesaUGD(array $a): int
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
     * O — Pemeriksaan: 8 field tanda vital + nutrisi + kesadaran (sama dengan RJ).
     */
    private function scorePemeriksaanUGD(array $p): int
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
     * A — Diagnosa: minimal 1 entry (sama dengan RJ).
     */
    private function scoreDiagnosaUGD(array $json): int
    {
        $hasIcd = !empty($json['diagnosis']) && is_array($json['diagnosis']);
        $hasFreeText = filled($json['diagnosisFreeText'] ?? null);

        return ($hasIcd || $hasFreeText) ? 100 : 0;
    }

    /**
     * P — Perencanaan: 2 komponen (+1 untuk kunjungan mulai 28/10/2026), sama dengan RJ.
     *   - Treatment plan (terapi ATAU tindakLanjut)
     *   - TTD dokter pemeriksa
     *   - Kondisi Saat Keluar (wajib SATUSEHAT) — hanya bila $wajibKondisiPulang
     */
    private function scorePerencanaanUGD(array $p, bool $wajibKondisiPulang = false): int
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
     * N — Penilaian: nyeri + risikoJatuh (sama dengan RJ).
     */
    private function scorePenilaianUGD(array $p): int
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
     * T — Triase / Screening awal UGD: 5 field wajib (keluhan, pernafasan,
     * kesadaran, nyeri dada, prioritas pelayanan). Sumber rules:
     * rm-screening-ugd-actions.blade.php → required di rules().
     *
     * Standar IGD: triase wajib SEBELUM tindakan apapun.
     */
    private function scoreTriaseUGD(array $s): int
    {
        if (empty($s)) {
            return 0;
        }

        $checks = [
            filled($s['keluhanUtama'] ?? null),
            filled($s['pernafasan'] ?? null),
            filled($s['kesadaran'] ?? null),
            filled($s['nyeriDada'] ?? null),
            filled($s['prioritasPelayanan'] ?? null),
        ];

        return (int) round((array_sum($checks) / count($checks)) * 100);
    }

    /**
     * Checklist per-section: label + filled status untuk setiap field yang
     * dihitung di score* methods. Dipakai modal info-kelengkapan-emr agar
     * user tahu field mana yang sudah/belum diisi (bukan cuma % total).
     *
     * Item ber-'opsional' => true hanya tampil (kolom tambahan) — TIDAK dihitung di score*.
     *
     * @return array<string, array{label: string, weight: int, items: array<int, array{label: string, filled: bool, opsional?: bool}>}>
     */
    public function collectChecklistUGD(array $json): array
    {
        $a = $json['anamnesa'] ?? [];
        $p = $json['pemeriksaan'] ?? [];
        $r = $json['perencanaan'] ?? [];
        $n = $json['penilaian'] ?? [];
        $sc = $json['screening'] ?? [];
        $tv = $p['tandaVital'] ?? [];
        $nutrisi = $p['nutrisi'] ?? [];

        return [
            't' => [
                'label' => 'Triase / Screening',
                'weight' => 15,
                'items' => [
                    ['label' => 'Keluhan utama (versi triase)', 'filled' => filled($sc['keluhanUtama'] ?? null)],
                    ['label' => 'Pernafasan', 'filled' => filled($sc['pernafasan'] ?? null)],
                    ['label' => 'Kesadaran', 'filled' => filled($sc['kesadaran'] ?? null)],
                    ['label' => 'Nyeri dada', 'filled' => filled($sc['nyeriDada'] ?? null)],
                    ['label' => 'Prioritas pelayanan', 'filled' => filled($sc['prioritasPelayanan'] ?? null)],
                ],
            ],
            's' => [
                'label' => 'Anamnesa',
                'weight' => 15,
                'items' => [
                    ['label' => 'Keluhan utama', 'filled' => filled($a['keluhanUtama']['keluhanUtama'] ?? null)],
                    ['label' => 'Riwayat penyakit sekarang', 'filled' => filled($a['riwayatPenyakitSekarangUmum']['riwayatPenyakitSekarangUmum'] ?? null)],
                    ['label' => 'Alergi (boleh "Tidak ada")', 'filled' => filled($a['alergi']['alergi'] ?? null) || filled($a['alergi']['adaAlergi'] ?? null)],
                    ['label' => 'Riwayat penyakit dahulu (boleh "Tidak ada")', 'filled' => filled($a['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? null)],
                    ['label' => 'Waktu datang', 'filled' => filled($a['pengkajianPerawatan']['jamDatang'] ?? null)],
                    // Opsional — tampil, TIDAK dihitung ke persentase.
                    ['label' => 'Riwayat penyakit keluarga', 'filled' => filled($a['riwayatPenyakitKeluarga']['riwayatPenyakitKeluarga'] ?? null), 'opsional' => true],
                    ['label' => 'Status psikologis', 'filled' => KelengkapanEmr::statusPsikologisTerisi((array) ($a['statusPsikologis'] ?? [])), 'opsional' => true],
                    ['label' => 'Kedatangan — sarana transportasi', 'filled' => filled($a['pengkajianPerawatan']['saranaTransportasiId'] ?? null) || filled($a['pengkajianPerawatan']['saranaTransportasiDesc'] ?? null), 'opsional' => true],
                    ['label' => 'Kedatangan — cara masuk / surat rujukan', 'filled' => filled($a['pengkajianPerawatan']['caraMasukIgd'] ?? null), 'opsional' => true],
                ],
            ],
            'o' => [
                'label' => 'Pemeriksaan',
                'weight' => 15,
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
                'weight' => 20,
                'items' => [
                    [
                        'label' => 'Minimal 1 diagnosa (ICD-10 atau free-text)',
                        'filled' => (!empty($json['diagnosis']) && is_array($json['diagnosis'])) || filled($json['diagnosisFreeText'] ?? null),
                    ],
                ],
            ],
            'p' => [
                'label' => 'Perencanaan',
                'weight' => 25,
                'items' => array_merge([
                    [
                        'label' => 'Terapi ATAU tindak lanjut',
                        'filled' => filled($r['terapi']['terapi'] ?? null) || filled($r['tindakLanjut']['tindakLanjut'] ?? null),
                    ],
                    ['label' => 'TTD dokter pemeriksa', 'filled' => filled($r['pengkajianMedis']['drPemeriksa'] ?? null)],
                ], KelengkapanEmr::wajibKondisiPulang($json['rjDate'] ?? null)
                    ? [['label' => 'Kondisi saat keluar (wajib SATUSEHAT)', 'filled' => filled($r['kondisiPulang']['kondisiPulang'] ?? null)]]
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
