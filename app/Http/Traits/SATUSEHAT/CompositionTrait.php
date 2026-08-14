<?php

namespace App\Http\Traits\SATUSEHAT;

use Carbon\Carbon;
use App\Support\Terminologi\ResumeMedisSection;

/**
 * Composition — Resume Medis SATUSEHAT (playbook Bab 28, docs/satusehat-api.md §9.4).
 *
 * Dikirim SEKALI per kunjungan, sesudah Encounter di-PUT status `finished`.
 * Isinya indeks referensi resource yang sudah dikirim selama kunjungan itu, jadi
 * trait ini tidak menyentuh DB sama sekali — pemanggil menyetorkan daftar ID dari
 * node `satuSehat` miliknya.
 *
 * Aturan yang dipatuhi di sini:
 * - Section tanpa isi TIDAK dibuat. Mengirim `entry: []` = elemen objek kosong yang
 *   ditolak validator Kemkes (lihat skill satusehat-kirim §3).
 * - Induk yang seluruh anaknya kosong ikut dibuang, bukan dikirim sebagai cangkang.
 * - Waktu UTC+00 (`+00:00`), tidak boleh lebih awal dari 3 Juni 2014.
 *
 * `buildComposition()` sengaja dipisah dari `createComposition()` supaya payload bisa
 * diperiksa & dihitung tanpa memanggil API (uji dry-run, hitung section terisi di kartu).
 */
trait CompositionTrait
{
    use SatuSehatTrait;

    /** Tanggal paling awal yang diterima SATUSEHAT. */
    private const BATAS_TANGGAL = '2014-06-03';

    /**
     * Rakit payload Composition tanpa mengirim.
     *
     * @param array $data
     *  - identifier   (string) nomor lokal dokumen, mis. rjNo
     *  - patientId    (string) IHS pasien                        [wajib]
     *  - patientName  (string) nama pasien (display)
     *  - encounterId  (string) id Encounter kunjungan
     *  - authorId     (string) IHS Practitioner penyusun         [wajib]
     *  - authorName   (string) nama penyusun (display)
     *  - title        (string) judul dokumen
     *  - status       (string) default 'final'
     *  - jalur        (string) 'rj' (menentukan Composition.type)
     *  - date         (string) ISO; default sekarang UTC
     *  - entri        (array)  slug ResumeMedisSection => list referensi ('Condition/xxx')
     *  - narasi       (string) isi section Perjalanan Kunjungan (teks biasa/XHTML)
     *  - attesterMode (string) opsional — playbook menandainya wajib, tapi nilainya
     *    belum dikonfirmasi; hanya disertakan bila pemanggil mengisinya.
     *  - attesterPartyId (string) opsional, IHS Practitioner pengesah
     */
    public function buildComposition(array $data): array
    {
        $tanggal = $this->waktuComposition($data['date'] ?? null);

        $payload = [
            'resourceType' => 'Composition',
            'status' => $data['status'] ?? 'final',
            'type' => ['coding' => [ResumeMedisSection::tipeDokumen($data['jalur'] ?? 'rj')]],
            'category' => [['coding' => [ResumeMedisSection::kategoriDokumen()]]],
            'subject' => array_filter([
                'reference' => 'Patient/' . $data['patientId'],
                'display' => $data['patientName'] ?? null,
            ]),
            'date' => $tanggal,
            'author' => [array_filter([
                'reference' => 'Practitioner/' . $data['authorId'],
                'display' => $data['authorName'] ?? null,
            ])],
            'title' => $data['title'] ?? 'Resume Medis Rawat Jalan',
            'custodian' => ['reference' => 'Organization/' . $this->organizationId],
        ];

        if (!empty($data['identifier'])) {
            $payload['identifier'] = [
                'system' => 'http://sys-ids.kemkes.go.id/composition/' . $this->organizationId,
                'value' => (string) $data['identifier'],
            ];
        }

        if (!empty($data['encounterId'])) {
            $payload['encounter'] = ['reference' => 'Encounter/' . $data['encounterId']];
        }

        // Playbook menandai attester wajib, tetapi tidak menyebut nilai yang diterima.
        // Dikirim hanya bila pemanggil menetapkannya — daripada menebak lalu ditolak.
        if (!empty($data['attesterMode'])) {
            $payload['attester'] = [array_filter([
                'mode' => $data['attesterMode'],
                'time' => $tanggal,
                'party' => !empty($data['attesterPartyId'])
                    ? ['reference' => 'Practitioner/' . $data['attesterPartyId']]
                    : null,
            ])];
        }

        $section = $this->susunSectionComposition($data['entri'] ?? [], $data['narasi'] ?? '');
        if ($section !== []) {
            $payload['section'] = $section;
        }

        return $payload;
    }

    /**
     * Rakit + POST. Balikan sama dengan makeRequest (id resource di ['id']).
     */
    public function createComposition(array $data): array
    {
        return $this->makeRequest('post', '/Composition', $this->buildComposition($data));
    }

    /**
     * Slot yang tidak terisi — untuk dilaporkan di kartu pengirim (bukan disembunyikan).
     *
     * @return array<int, string> judul section yang kosong
     */
    public function sectionCompositionKosong(array $entri, string $narasi = ''): array
    {
        $kosong = [];
        foreach (ResumeMedisSection::daftarKunci() as $kunci) {
            $terisi = $kunci === 'perjalananKunjungan'
                ? trim($narasi) !== ''
                : $this->bersihkanReferensi($entri[$kunci] ?? []) !== [];

            if (!$terisi) {
                $kosong[] = ResumeMedisSection::judulKunci($kunci);
            }
        }

        return $kosong;
    }

    private function susunSectionComposition(array $entri, string $narasi): array
    {
        $section = [];

        foreach (ResumeMedisSection::daftar() as $definisi) {
            if (!empty($definisi['anak'])) {
                $anakTerisi = [];
                foreach ($definisi['anak'] as $anak) {
                    $satu = $this->bangunSectionDaun($anak, $entri, $narasi);
                    if ($satu !== null) {
                        $anakTerisi[] = $satu;
                    }
                }
                // Induk tanpa anak terisi = cangkang kosong, jangan dikirim.
                if ($anakTerisi === []) {
                    continue;
                }
                $section[] = array_filter([
                    'title' => $definisi['judul'],
                    'code' => $definisi['kode'] ? ['coding' => [$definisi['kode']]] : null,
                    'section' => $anakTerisi,
                ]);
                continue;
            }

            $satu = $this->bangunSectionDaun($definisi, $entri, $narasi);
            if ($satu !== null) {
                $section[] = $satu;
            }
        }

        return $section;
    }

    /** Satu section daun — null bila tidak ada isinya. */
    private function bangunSectionDaun(array $definisi, array $entri, string $narasi): ?array
    {
        $dasar = [
            'title' => $definisi['judul'],
            'code' => ['coding' => [$definisi['kode']]],
        ];

        if (!empty($definisi['naratif'])) {
            $teks = trim($narasi);
            if ($teks === '') {
                return null;
            }

            return $dasar + ['text' => [
                'status' => 'generated',
                'div' => $this->bungkusXhtml($teks),
            ]];
        }

        $referensi = $this->bersihkanReferensi($entri[$definisi['kunci']] ?? []);
        if ($referensi === []) {
            return null;
        }

        return $dasar + ['entry' => array_map(fn($satu) => ['reference' => $satu], $referensi)];
    }

    /**
     * Buang nilai kosong & duplikat; terima 'Condition/xxx' apa adanya.
     * Nilai yang bukan string (mis. array hasil parse JSON yang salah bentuk) diabaikan
     * — lebih baik section itu hilang dan dilaporkan daripada payload rusak.
     */
    private function bersihkanReferensi($nilai): array
    {
        $daftar = is_array($nilai) ? $nilai : [$nilai];
        $bersih = [];
        foreach ($daftar as $satu) {
            if (!is_string($satu)) {
                continue;
            }
            $satu = trim($satu);
            if ($satu !== '' && !str_contains($satu, '/')) {
                continue; // bukan referensi FHIR yang sah
            }
            if ($satu !== '' && !in_array($satu, $bersih, true)) {
                $bersih[] = $satu;
            }
        }

        return $bersih;
    }

    /** div WAJIB XHTML ber-namespace. Teks biasa dibungkus, XHTML yang sudah siap dibiarkan. */
    private function bungkusXhtml(string $teks): string
    {
        if (str_starts_with($teks, '<div')) {
            return $teks;
        }

        return '<div xmlns="http://www.w3.org/1999/xhtml">' . e($teks) . '</div>';
    }

    /** UTC+00 dan tidak lebih awal dari batas SATUSEHAT. */
    private function waktuComposition(?string $iso): string
    {
        try {
            $waktu = $iso ? Carbon::parse($iso) : Carbon::now();
        } catch (\Throwable $e) {
            $waktu = Carbon::now();
        }

        $waktu = $waktu->utc();
        if ($waktu->lt(Carbon::parse(self::BATAS_TANGGAL, 'UTC'))) {
            $waktu = Carbon::now('UTC');
        }

        return $waktu->format('Y-m-d\TH:i:sP');
    }
}
