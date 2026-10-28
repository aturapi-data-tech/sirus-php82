<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-observation.blade.php
// Step 5 (RI): Kirim Hasil Pemeriksaan Fisik (Observation) — Playbook RI bab 4 (boleh berulang). Dua sumber:
//   pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik   pemeriksaan awal perawat (terisi ±85%):
//       tandaVital (TD/nadi/suhu/RR/SpO2) + BB/TB + kesadaran neurologi     → satusehat.pemeriksaanAwalIds
//       jam = bagian5CatatanDanTandaTangan.jamPengkaji (cadangan: tanggal masuk)
//   observasi.observasiLanjutan.tandaVital[]   tiap pengukuran ulang         → satusehat.tandaVitalLanjutanIds
//                                              [kunci entri => [id, ...]], jam = waktuPemeriksaan entri
// Peta LOINC di TandaVitalObservationMap (sama dengan RJ/UGD); kesadaran → NyeriKesadaranObservationMap::kesadaranRi.
// Record LAMA yang dikirim sebelum pemisahan ini (satusehat.observationIds tanpa tandaVitalLanjutanIds)
// dianggap sudah mengirim seluruh observasi lanjutannya — tidak dikirim ulang (cegah dobel).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Support\Options\PengkajianAwalRiOptions;
use App\Support\Terminologi\NyeriKesadaranObservationMap;
use App\Support\Terminologi\TandaVitalObservationMap;

new class extends Component {
    use EmrRITrait, ObservationTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;      // jumlah Observation terkirim
    public int $entryCount = 0; // jumlah entri waktu vital tersedia

    /** Pemeriksaan awal belum terkirim + entri observasi lanjutan yang belum terkirim. */
    public int $antrean = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /** Isi yang AKAN dikirim, memakai sumber & penyaring yang SAMA dengan kirim(). */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->riHdrNo)) {
            return [];
        }

        $dataRI = $this->findDataRI($this->riHdrNo);
        $satuSehat = $dataRI['satusehat'] ?? [];
        $baris = [];

        $pemeriksaanAwal = $this->pemeriksaanAwal($dataRI);
        $statusAwal = !empty($satuSehat['pemeriksaanAwalIds']) ? ' (terkirim)' : '';
        array_push($baris, ...$this->barisTandaVital($pemeriksaanAwal['tandaVital'], 'Pengkajian awal' . $statusAwal . ' · '));
        foreach (TandaVitalObservationMap::ANTROPOMETRI as $kunci => $peta) {
            $nilai = $pemeriksaanAwal['tandaVital'][$kunci] ?? null;
            if (!empty($nilai) && is_numeric($nilai)) {
                $baris[] = ['label' => 'Pengkajian awal' . $statusAwal . ' · ' . $peta['label'], 'nilai' => "{$nilai} {$peta['satuanTampil']}", 'ket' => 'LOINC ' . $peta['loinc']];
            }
        }
        foreach ($pemeriksaanAwal['kesadaran'] as $kesadaran) {
            $nilai = $kesadaran['valueCodeableConcept'];
            $baris[] = ['label' => 'Pengkajian awal' . $statusAwal . ' · Kesadaran', 'nilai' => $nilai['text'],
                'ket' => 'LOINC 67775-7' . (isset($nilai['code']) ? ' · SNOMED ' . $nilai['code'] : ' · teks (tanpa kode)')];
        }

        $terkirim = $this->lanjutanTerkirim($dataRI);
        foreach ($this->entriObservasiLanjutan($dataRI) as $kunci => $entri) {
            $awalan = (trim((string) ($entri['waktuPemeriksaan'] ?? '')) ?: 'Observasi') . (isset($terkirim[$kunci]) ? ' (terkirim)' : '') . ' · ';
            array_push($baris, ...$this->barisTandaVital($entri, $awalan));
        }

        return $baris;
    }

    /** Pemetaan vital → baris pratinjau; dipakai bersama supaya labelnya seragam. */
    private function barisTandaVital(array $tandaVital, string $awalan = ''): array
    {
        $baris = [];
        if (!empty($tandaVital['sistolik']) && !empty($tandaVital['distolik'])) {
            $baris[] = ['label' => $awalan . 'Tekanan darah', 'nilai' => "{$tandaVital['sistolik']}/{$tandaVital['distolik']} mm[Hg]", 'ket' => 'LOINC 85354-9'];
        }
        foreach (TandaVitalObservationMap::VITAL_TUNGGAL as $kunci => $peta) {
            $nilai = $tandaVital[$kunci] ?? null;
            if (!empty($nilai) && is_numeric($nilai)) {
                $baris[] = ['label' => $awalan . $peta['label'], 'nilai' => "{$nilai} {$peta['satuanTampil']}", 'ket' => 'LOINC ' . $peta['loinc']];
            }
        }

        return $baris;
    }

    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->reloadState();
    }

    #[On('ri-satu-sehat.refresh')]
    public function onRefresh(string $riHdrNo): void
    {
        if ((string) $this->riHdrNo !== $riHdrNo) {
            return;
        }
        $this->reloadState();
    }

    private function reloadState(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $data = $this->findDataRI($this->riHdrNo);
        if (empty($data)) {
            return;
        }
        $satuSehat = $data['satusehat'] ?? [];
        $this->hasEncounter = !empty($satuSehat['encounterId']);
        $this->count = count($satuSehat['pemeriksaanAwalIds'] ?? []) + count($satuSehat['observationIds'] ?? [])
            + count(collect($satuSehat['tandaVitalLanjutanIds'] ?? [])->flatten());
        $entri = $this->entriObservasiLanjutan($data);
        $this->entryCount = count($entri);
        $this->antrean = count(array_diff_key($entri, $this->lanjutanTerkirim($data)))
            + ((empty($satuSehat['pemeriksaanAwalIds']) && $this->adaPemeriksaanAwal($data)) ? 1 : 0);
    }

    /**
     * Pemeriksaan awal dari Pengkajian Awal Rawat Inap: tanda vital (+bb/tb) & kesadaran siap kirim.
     *
     * @return array{tandaVital: array, kesadaran: array, jam: string}
     */
    private function pemeriksaanAwal(array $dataRI): array
    {
        $fisik = $dataRI['pengkajianAwalPasienRawatInap']['bagian4PemeriksaanFisik'] ?? [];
        $kunciKesadaran = (string) ($fisik['pemeriksaanSistemOrgan']['neurologi']['tingkatKesadaran']['pilihan'] ?? '');

        return [
            'tandaVital' => (array) ($fisik['tandaVital'] ?? []),
            'kesadaran' => NyeriKesadaranObservationMap::kesadaranRi($kunciKesadaran, PengkajianAwalRiOptions::TINGKAT_KESADARAN[$kunciKesadaran] ?? null),
            'jam' => trim((string) ($dataRI['pengkajianAwalPasienRawatInap']['bagian5CatatanDanTandaTangan']['jamPengkaji'] ?? '')),
        ];
    }

    private function adaPemeriksaanAwal(array $dataRI): bool
    {
        $awal = $this->pemeriksaanAwal($dataRI);

        return $awal['kesadaran'] !== [] || $this->barisTandaVital($awal['tandaVital']) !== []
            || collect(TandaVitalObservationMap::ANTROPOMETRI)->keys()->contains(fn($kunci) => !empty($awal['tandaVital'][$kunci]) && is_numeric($awal['tandaVital'][$kunci]));
    }

    /**
     * Entri Observasi Lanjutan ber-kunci stabil: jam pemeriksaan; jam kembar / kosong diberi akhiran urutan.
     *
     * @return array<string, array>
     */
    private function entriObservasiLanjutan(array $dataRI): array
    {
        $hasil = [];
        foreach (array_values((array) ($dataRI['observasi']['observasiLanjutan']['tandaVital'] ?? [])) as $urutan => $entri) {
            $kunci = trim((string) ($entri['waktuPemeriksaan'] ?? '')) ?: 'urutan-' . $urutan;
            if (isset($hasil[$kunci])) {
                $kunci .= '#' . $urutan;
            }
            $hasil[$kunci] = (array) $entri;
        }

        return $hasil;
    }

    /** Entri yang dianggap sudah terkirim — termasuk record lama (observationIds tanpa peta per entri). */
    private function lanjutanTerkirim(array $dataRI): array
    {
        $satuSehat = $dataRI['satusehat'] ?? [];
        if (!isset($satuSehat['tandaVitalLanjutanIds']) && !empty($satuSehat['observationIds'])) {
            return $this->entriObservasiLanjutan($dataRI);
        }

        return (array) ($satuSehat['tandaVitalLanjutanIds'] ?? []);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    /**
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya — berhasil, ditolak
     * SATUSEHAT, atau berhenti di guard — langkah ini WAJIB memberi kabar, supaya
     * orkestrator bisa melanjutkan. Tanpa ini rantai menggantung diam-diam pada
     * langkah pertama yang gagal, dan petugas cuma melihat modal yang membeku.
     */
    #[On('ss-observation-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        $this->kirimInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'observation');
    }

    public function kirimInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }

            $kirimAwal = empty($satuSehat['pemeriksaanAwalIds']) && $this->adaPemeriksaanAwal($dataRI);
            $antreanLanjutan = array_diff_key($this->entriObservasiLanjutan($dataRI), $this->lanjutanTerkirim($dataRI));
            if (!$kirimAwal && empty($antreanLanjutan)) {
                $this->dispatch('toast', type: 'info', message: ($this->count > 0 || !empty($satuSehat['observationIds']))
                    ? 'Pemeriksaan fisik & observasi lanjutan sudah pernah dikirim.'
                    : 'Belum ada tanda vital (Pengkajian Awal / Observasi Lanjutan).');
                return;
            }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');
            $payloadDasar = ['patientId' => $patientId, 'encounterId' => $satuSehat['encounterId'], 'performerId' => $practitionerId];
            $jumlahBaru = 0;

            // Record lama: peta per entri dimulai dari entri yang SUDAH terkirim lewat observationIds.
            if (!isset($satuSehat['tandaVitalLanjutanIds']) && !empty($satuSehat['observationIds'])) {
                $satuSehat['tandaVitalLanjutanIds'] = array_map(fn() => [], $this->entriObservasiLanjutan($dataRI));
            }

            // 1) Pemeriksaan awal (Pengkajian Awal) — sekali.
            if ($kirimAwal) {
                $awal = $this->pemeriksaanAwal($dataRI);
                $dasarAwal = [...$payloadDasar, 'effectiveDate' => $this->parseDate($awal['jam'] ?: (string) ($dataRI['entryDate'] ?? ''))->toIso8601String()];
                $idList = $this->kirimSetVital($awal['tandaVital'], $dasarAwal);
                foreach (TandaVitalObservationMap::ANTROPOMETRI as $kunci => $peta) {
                    $nilai = $awal['tandaVital'][$kunci] ?? null;
                    if (empty($nilai) || !is_numeric($nilai)) continue;
                    $respons = $this->createObservation(array_merge($dasarAwal, [
                        'code' => ['system' => 'http://loinc.org', 'code' => $peta['loinc'], 'display' => $peta['display']],
                        'valueQuantity' => ['value' => (float) $nilai, 'unit' => $peta['unit'], 'system' => 'http://unitsofmeasure.org', 'code' => $peta['ucum']],
                    ]));
                    if (!empty($respons['id'])) $idList[] = $respons['id'];
                }
                foreach ($awal['kesadaran'] as $kesadaran) {
                    $respons = $this->createObservation(array_merge($dasarAwal, $kesadaran));
                    if (!empty($respons['id'])) $idList[] = $respons['id'];
                }
                $satuSehat['pemeriksaanAwalIds'] = $idList;
                $jumlahBaru += count($idList);
            }

            // 2) Observasi lanjutan — per entri, jam = waktuPemeriksaan entri. Bila gagal di tengah,
            //    blok catch tetap menyimpan entri yang sudah terbentuk → kirim ulang tak menggandakan.
            foreach ($antreanLanjutan as $kunci => $entri) {
                $waktu = trim((string) ($entri['waktuPemeriksaan'] ?? ''));
                $idList = $this->kirimSetVital($entri, [...$payloadDasar, 'effectiveDate' => $this->parseDate($waktu ?: (string) ($dataRI['entryDate'] ?? ''))->toIso8601String()]);
                if ($idList !== []) {
                    $satuSehat['tandaVitalLanjutanIds'][$kunci] = $idList;
                    $jumlahBaru += count($idList);
                }
            }

            if ($jumlahBaru === 0) { $this->dispatch('toast', type: 'error', message: 'Tidak ada nilai vital valid untuk dikirim.'); return; }

            $this->saveResult($riHdrNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: "Pemeriksaan fisik berhasil dikirim ({$jumlahBaru} observation"
                . (count($antreanLanjutan) > 0 ? ', ' . count($antreanLanjutan) . ' waktu observasi' : '') . ').');
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor gagal — tanpa ini
            // id-nya hangus dan percobaan berikutnya menumpuk resource yatim (lihat sender Condition).
            try { if (isset($satuSehat)) { $this->saveResult($riHdrNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Tanda vital gagal: ' . $e->getMessage());
        }
    }

    /**
     * Satu set tanda vital (TD panel + nadi/suhu/RR/SpO2) → Observation; key JSON sama untuk
     * pengkajian awal & entri observasi lanjutan. @return string[] id yang terbentuk
     */
    private function kirimSetVital(array $tandaVital, array $payloadDasar): array
    {
        $idList = [];

        if (!empty($tandaVital['sistolik']) && !empty($tandaVital['distolik'])) {
            $tekananDarah = TandaVitalObservationMap::TEKANAN_DARAH;
            $respons = $this->createObservation(array_merge($payloadDasar, [
                'code' => $tekananDarah['panel'],
                'components' => [
                    ['code' => ['coding' => [$tekananDarah['sistolik']]], 'valueQuantity' => ['value' => (float) $tandaVital['sistolik'], 'unit' => $tekananDarah['unit'], 'system' => 'http://unitsofmeasure.org', 'code' => $tekananDarah['unit']]],
                    ['code' => ['coding' => [$tekananDarah['distolik']]], 'valueQuantity' => ['value' => (float) $tandaVital['distolik'], 'unit' => $tekananDarah['unit'], 'system' => 'http://unitsofmeasure.org', 'code' => $tekananDarah['unit']]],
                ],
            ]));
            if (!empty($respons['id'])) $idList[] = $respons['id'];
        }

        foreach (TandaVitalObservationMap::VITAL_TUNGGAL as $kunci => $vital) {
            $nilai = $tandaVital[$kunci] ?? null;
            if (empty($nilai) || !is_numeric($nilai)) continue;
            $respons = $this->createObservation(array_merge($payloadDasar, [
                'code' => ['system' => 'http://loinc.org', 'code' => $vital['loinc'], 'display' => $vital['display']],
                'valueQuantity' => ['value' => (float) $nilai, 'unit' => $vital['unit'], 'system' => 'http://unitsofmeasure.org', 'code' => $vital['ucum']],
            ]));
            if (!empty($respons['id'])) $idList[] = $respons['id'];
        }

        return $idList;
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }
    private function saveResult(string $riHdrNo, array $satuSehat): void
    {
        DB::transaction(function () use ($riHdrNo, $satuSehat) {
            $this->lockRIRow($riHdrNo);
            $data = $this->findDataRI($riHdrNo);
            $data['satusehat'] = $satuSehat;
            $this->updateJsonRI((int) $riHdrNo, $data);
        });
    }

    private function parseDate(string $teksTanggal): Carbon
    {
        if (empty($teksTanggal)) return Carbon::now();
        try { return Carbon::createFromFormat('d/m/Y H:i:s', $teksTanggal); } catch (\Throwable) {
            try { return Carbon::parse($teksTanggal); } catch (\Throwable) { return Carbon::now(); }
        }
    }
};
?>

<div class="p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">5</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Observation</div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Pemeriksaan awal (TTV, BB/TB, kesadaran) &amp; tiap entri Observasi Lanjutan.
                    @if ($entryCount > 0)
                        <span class="text-muted-soft">{{ $entryCount }} waktu ukur.</span>
                    @endif
                    @if ($count > 0 && $antrean > 0)
                        <span class="text-warning-deep dark:text-amber-200">{{ $antrean }} belum dikirim.</span>
                    @endif
                </div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }} terkirim
                    </div>
                @endif
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 && $antrean === 0 ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent,kirim">
                    <span class="inline-flex items-center gap-1.5">
                        <x-satu-sehat.ikon-tombol :selesai="$count > 0 && $antrean === 0" jenis="kirim" />
                        {{ $count > 0 && $antrean === 0 ? 'Terkirim' : 'Kirim' }}
                    </span>
                </span>
            <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
        </x-primary-button>
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Belum ada tanda vital (Pengkajian Awal / Observasi Lanjutan) — Kirim akan ditolak sampai diisi." />
</div>
