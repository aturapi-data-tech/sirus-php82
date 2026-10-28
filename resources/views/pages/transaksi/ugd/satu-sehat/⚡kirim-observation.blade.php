<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-observation.blade.php
// Kirim Tanda Vital (Observation, LOINC) — Playbook IGD 5e (boleh berulang). Dua sumber:
//   pemeriksaan.tandaVital (+ nutrisi BB/TB)    pemeriksaan awal         → satusehat.observationIds
//   observasi.observasiLanjutan.tandaVital[]   tiap pengukuran ulang     → satusehat.observasiLanjutanIds
//                                              [kunci entri => [id, ...]], jam = waktuPemeriksaan entri
// Yang sudah terkirim tak dikirim ulang; entri observasi baru tetap bisa menyusul (pola RI multi-entri).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\Terminologi\TandaVitalObservationMap;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;

new class extends Component {
    use EmrUGDTrait, ObservationTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;

    /** Entri Observasi Lanjutan yang belum terkirim — tombol baru 'Terkirim' bila nol. */
    public int $antreanLanjutan = 0;
    public int $count = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Isi yang AKAN dikirim, dibaca dari sumber yang SAMA dengan kirim() —
     * pemeriksaan.tandaVital. Nilai kosong sengaja TIDAK ditampilkan karena
     * kirim() juga melewatinya.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $dataUGD = $this->findDataUGD($this->rjNo);
        $pemeriksaan = $dataUGD['pemeriksaan'] ?? [];
        $baris = $this->barisTandaVital($pemeriksaan['tandaVital'] ?? []);

        // Antropometri BB/TB — sumber & aturan lewati sama dengan kirimInti().
        foreach (TandaVitalObservationMap::ANTROPOMETRI as $kunci => $peta) {
            $nilai = $pemeriksaan['nutrisi'][$kunci] ?? null;
            if (empty($nilai) || !is_numeric($nilai)) {
                continue;
            }
            $baris[] = ['label' => $peta['label'], 'nilai' => "{$nilai} {$peta['satuanTampil']}", 'ket' => 'LOINC ' . $peta['loinc']];
        }

        // Observasi lanjutan — tiap entri dengan jamnya sendiri.
        $terkirim = $dataUGD['satusehat']['observasiLanjutanIds'] ?? [];
        foreach ($this->entriObservasiLanjutan($dataUGD) as $kunci => $entri) {
            $awalan = 'Observasi ' . trim((string) ($entri['waktuPemeriksaan'] ?? '')) . (isset($terkirim[$kunci]) ? ' (terkirim) · ' : ' · ');
            array_push($baris, ...$this->barisTandaVital($entri, $awalan));
        }

        return $baris;
    }

    /**
     * Entri Observasi Lanjutan ber-kunci stabil: jam pemeriksaan (unik per entri di form);
     * jam kembar / kosong diberi akhiran urutan supaya tak saling menimpa.
     *
     * @return array<string, array>
     */
    private function entriObservasiLanjutan(array $dataUGD): array
    {
        $hasil = [];
        foreach (array_values((array) ($dataUGD['observasi']['observasiLanjutan']['tandaVital'] ?? [])) as $urutan => $entri) {
            $kunci = trim((string) ($entri['waktuPemeriksaan'] ?? '')) ?: 'urutan-' . $urutan;
            if (isset($hasil[$kunci])) {
                $kunci .= '#' . $urutan;
            }
            $hasil[$kunci] = (array) $entri;
        }

        return $hasil;
    }

    /** Pemetaan vital → baris pratinjau; dipakai bersama supaya labelnya seragam. */
    private function barisTandaVital(array $tandaVital, string $awalan = ''): array
    {
        $baris = [];
        $sistolik = $tandaVital['sistolik'] ?? null;
        $distolik = $tandaVital['distolik'] ?? null;
        if (!empty($sistolik) && !empty($distolik)) {
            $baris[] = ['label' => $awalan . 'Tekanan darah', 'nilai' => "{$sistolik}/{$distolik} mm[Hg]", 'ket' => 'LOINC 85354-9'];
        }

        foreach ([
            ['frekuensiNadi', 'Nadi', 'x/menit', '8867-4'],
            ['suhu', 'Suhu', '°C', '8310-5'],
            ['frekuensiNafas', 'Pernapasan', 'x/menit', '9279-1'],
            ['spo2', 'Saturasi O₂', '%', '59408-5'],
        ] as [$key, $label, $satuan, $loinc]) {
            $nilai = $tandaVital[$key] ?? null;
            if (empty($nilai)) {
                continue;
            }
            $baris[] = ['label' => $awalan . $label, 'nilai' => "{$nilai} {$satuan}", 'ket' => 'LOINC ' . $loinc];
        }

        return $baris;
    }


    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->reloadState();
    }

    #[On('ugd-satu-sehat.refresh')]
    public function onRefresh(string $rjNo): void
    {
        if ((string) $this->rjNo !== $rjNo) {
            return;
        }
        $this->reloadState();
    }

    private function reloadState(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $data = $this->findDataUGD($this->rjNo);
        if (empty($data)) {
            return;
        }
        $satuSehat = $data['satusehat'] ?? [];
        $this->hasEncounter = !empty($satuSehat['encounterId']);
        $this->count = count($satuSehat['observationIds'] ?? []) + count($satuSehat['observasiLanjutanIds'] ?? []);
        $this->antreanLanjutan = count(array_diff_key($this->entriObservasiLanjutan($data), $satuSehat['observasiLanjutanIds'] ?? []));
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    /**
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya — berhasil, ditolak
     * SATUSEHAT, atau berhenti di guard — langkah ini WAJIB memberi kabar, supaya
     * orkestrator bisa melanjutkan. Tanpa ini rantai menggantung diam-diam pada
     * langkah pertama yang gagal, dan petugas cuma melihat modal yang membeku.
     */
    #[On('ss-observation-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('ugd-satu-sehat.langkah-selesai', langkah: 'observation');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            $tandaVital = $dataUGD['pemeriksaan']['tandaVital'] ?? [];
            $nutrisi = $dataUGD['pemeriksaan']['nutrisi'] ?? [];
            $kirimAwal = empty($satuSehat['observationIds']) && (!empty($tandaVital) || !empty($nutrisi));
            $terkirimLanjutan = $satuSehat['observasiLanjutanIds'] ?? [];
            $antreanLanjutan = array_diff_key($this->entriObservasiLanjutan($dataUGD), $terkirimLanjutan);
            if (!$kirimAwal && empty($antreanLanjutan)) {
                $this->dispatch('toast', type: 'info', message: !empty($satuSehat['observationIds'])
                    ? 'Tanda vital & observasi lanjutan sudah pernah dikirim.'
                    : 'Tidak ada data tanda vital.');
                return;
            }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
            $payloadDasar = ['patientId' => $patientId, 'encounterId' => $satuSehat['encounterId'], 'performerId' => $practitionerId];
            $jumlahBaru = 0;

            // 1) Pemeriksaan awal (+ antropometri BB/TB) — sekali.
            if ($kirimAwal) {
                $dasarAwal = [...$payloadDasar, 'effectiveDate' => $this->parseDate($dataUGD['rjDate'] ?? '')->toIso8601String()];
                $satuSehat['observationIds'] = $this->kirimSetVital($tandaVital, $dasarAwal);

                // Antropometri BB/TB (pemeriksaan.nutrisi) — Playbook IGD 5e; empty() sekaligus melewati "0".
                foreach (TandaVitalObservationMap::ANTROPOMETRI as $kunci => $peta) {
                    if (empty($nutrisi[$kunci]) || !is_numeric($nutrisi[$kunci])) continue;
                    $respons = $this->createObservation(array_merge($dasarAwal, [
                        'code' => ['system' => 'http://loinc.org', 'code' => $peta['loinc'], 'display' => $peta['display']],
                        'valueQuantity' => ['value' => (float) $nutrisi[$kunci], 'unit' => $peta['unit'], 'system' => 'http://unitsofmeasure.org', 'code' => $peta['ucum']],
                    ]));
                    if (!empty($respons['id'])) $satuSehat['observationIds'][] = $respons['id'];
                }
                $jumlahBaru += count($satuSehat['observationIds']);
            }

            // 2) Observasi lanjutan — per entri, jam = waktuPemeriksaan entri. Bila gagal di tengah,
            //    blok catch tetap menyimpan entri yang sudah terbentuk → kirim ulang tak menggandakan.
            foreach ($antreanLanjutan as $kunci => $entri) {
                $idList = $this->kirimSetVital($entri, [...$payloadDasar, 'effectiveDate' => $this->parseDate((string) ($entri['waktuPemeriksaan'] ?? ''))->toIso8601String()]);
                if ($idList !== []) {
                    $satuSehat['observasiLanjutanIds'][$kunci] = $idList;
                    $jumlahBaru += count($idList);
                }
            }

            if ($jumlahBaru === 0) { $this->dispatch('toast', type: 'error', message: 'Tidak ada nilai vital valid untuk dikirim.'); return; }

            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: "Tanda vital berhasil dikirim ({$jumlahBaru} item"
                . (count($antreanLanjutan) > 0 ? ', ' . count($antreanLanjutan) . ' entri observasi lanjutan' : '') . ').');
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal. Tanpa ini id-nya hangus padahal resource-nya SUDAH ada di sana,
            // lalu percobaan berikutnya menumpuk resource yatim — persis penyebab
            // diagnosa macet permanen dulu (lihat sender Condition). Dibungkus try
            // sendiri supaya kegagalan menyimpan tidak menutupi error aslinya.
            try { if (isset($satuSehat)) { $this->saveResult($rjNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Tanda vital gagal: ' . $e->getMessage());
        }
    }

    /**
     * Satu set tanda vital (TD panel + nadi/suhu/RR/SpO2) → Observation; key JSON sama untuk
     * pemeriksaan awal & entri observasi lanjutan. @return string[] id yang terbentuk
     */
    private function kirimSetVital(array $tandaVital, array $payloadDasar): array
    {
        $idList = [];

        // Tekanan darah (panel) — key JSON EMR: sistolik / distolik.
        $sistolik = $tandaVital['sistolik'] ?? null; $distolik = $tandaVital['distolik'] ?? null;
        if (!empty($sistolik) && !empty($distolik)) {
            $tekananDarah = TandaVitalObservationMap::TEKANAN_DARAH;
            $respons = $this->createObservation(array_merge($payloadDasar, [
                'code' => $tekananDarah['panel'],
                'components' => [
                    ['code' => ['coding' => [$tekananDarah['sistolik']]], 'valueQuantity' => ['value' => (float) $sistolik, 'unit' => $tekananDarah['unit'], 'system' => 'http://unitsofmeasure.org', 'code' => $tekananDarah['unit']]],
                    ['code' => ['coding' => [$tekananDarah['distolik']]], 'valueQuantity' => ['value' => (float) $distolik, 'unit' => $tekananDarah['unit'], 'system' => 'http://unitsofmeasure.org', 'code' => $tekananDarah['unit']]],
                ],
            ]));
            if (!empty($respons['id'])) $idList[] = $respons['id'];
        }

        // Nadi, Suhu, RR, SpO2 — peta bersama RJ di TandaVitalObservationMap.
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

    private function saveResult(string $rjNo, array $satuSehat): void
    {
        DB::transaction(function () use ($rjNo, $satuSehat) {
            $this->lockUGDRow($rjNo);
            $data = $this->findDataUGD($rjNo);
            $data['satusehat'] = $satuSehat;
            $this->updateJsonUGD((int) $rjNo, $data);
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
                <span class="text-sm font-bold">4</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Observation</div>
                <div class="text-xs text-muted dark:text-gray-400">Tanda vital pemeriksaan awal, BB/TB, dan tiap entri Observasi Lanjutan.</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }} terkirim
                    </div>
                @endif
                @if ($count > 0 && $antreanLanjutan > 0)
                    <div class="text-xs text-warning-deep dark:text-amber-200">{{ $antreanLanjutan }} entri observasi lanjutan belum dikirim.</div>
                @endif
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 && $antreanLanjutan === 0 ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent,kirim">
                    <span class="inline-flex items-center gap-1.5">
                        <x-satu-sehat.ikon-tombol :selesai="$count > 0 && $antreanLanjutan === 0" jenis="kirim" />
                        {{ $count > 0 && $antreanLanjutan === 0 ? 'Terkirim' : 'Kirim' }}
                    </span>
                </span>
            <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
        </x-primary-button>
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Belum ada tanda vital di EMR — Kirim akan ditolak sampai tanda vital diisi." />
</div>
