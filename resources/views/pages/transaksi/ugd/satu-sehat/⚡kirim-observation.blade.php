<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-observation.blade.php
// Step 3: Kirim Tanda Vital (Observation, LOINC). Sumbernya pemeriksaan.tandaVital —
// sama untuk RJ, UGD, dan RI (RI per entri Observasi Lanjutan).

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;

new class extends Component {
    use EmrUGDTrait, ObservationTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

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
        $this->count = count($satuSehat['observationIds'] ?? []);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-observation-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['observationIds'])) { $this->dispatch('toast', type: 'info', message: 'Tanda vital sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
            $isoDate = $this->parseDate($dataUGD['rjDate'] ?? '')->toIso8601String();

            // UGD: tanda vital bersarang di pemeriksaan.tandaVital.
            $tandaVital = $dataUGD['pemeriksaan']['tandaVital'] ?? [];
            if (empty($tandaVital)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data tanda vital.'); return; }

            $satuSehat['observationIds'] = [];
            $payloadDasar = ['patientId' => $patientId, 'encounterId' => $satuSehat['encounterId'], 'performerId' => $practitionerId, 'effectiveDate' => $isoDate];

            // Tekanan darah (panel) — key JSON EMR: sistolik / distolik.
            $sistolik = $tandaVital['sistolik'] ?? null; $distolik = $tandaVital['distolik'] ?? null;
            if (!empty($sistolik) && !empty($distolik)) {
                $respons = $this->createObservation(array_merge($payloadDasar, [
                    'code' => ['system' => 'http://loinc.org', 'code' => '85354-9', 'display' => 'Blood pressure panel with all children optional'],
                    'components' => [
                        ['code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8480-6', 'display' => 'Systolic blood pressure']]], 'valueQuantity' => ['value' => (float) $sistolik, 'unit' => 'mm[Hg]', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]']],
                        ['code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8462-4', 'display' => 'Diastolic blood pressure']]], 'valueQuantity' => ['value' => (float) $distolik, 'unit' => 'mm[Hg]', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]']],
                    ],
                ]));
                if (!empty($respons['id'])) $satuSehat['observationIds'][] = $respons['id'];
            }

            // Nadi, Suhu, RR, SpO2 — pemetaan disamakan dengan RM RI (kirim-observation RI).
            $vitalTunggal = [
                ['val' => $tandaVital['frekuensiNadi'] ?? null,  'loinc' => '8867-4',  'display' => 'Heart rate',       'unit' => 'beats/minute',   'ucum' => '/min'],
                ['val' => $tandaVital['suhu'] ?? null,           'loinc' => '8310-5',  'display' => 'Body temperature', 'unit' => 'C',              'ucum' => 'Cel'],
                ['val' => $tandaVital['frekuensiNafas'] ?? null, 'loinc' => '9279-1',  'display' => 'Respiratory rate', 'unit' => 'breaths/minute', 'ucum' => '/min'],
                ['val' => $tandaVital['spo2'] ?? null,           'loinc' => '59408-5', 'display' => 'Oxygen saturation in Arterial blood by Pulse oximetry', 'unit' => '%', 'ucum' => '%'],
            ];
            foreach ($vitalTunggal as $vital) {
                if (empty($vital['val'])) continue;
                $respons = $this->createObservation(array_merge($payloadDasar, [
                    'code' => ['system' => 'http://loinc.org', 'code' => $vital['loinc'], 'display' => $vital['display']],
                    'valueQuantity' => ['value' => (float) $vital['val'], 'unit' => $vital['unit'], 'system' => 'http://unitsofmeasure.org', 'code' => $vital['ucum']],
                ]));
                if (!empty($respons['id'])) $satuSehat['observationIds'][] = $respons['id'];
            }

            if (empty($satuSehat['observationIds'])) { $this->dispatch('toast', type: 'error', message: 'Tidak ada nilai vital valid untuk dikirim.'); return; }

            $this->saveResult($rjNo, $satuSehat);
            $count = count($satuSehat['observationIds']);
            $this->dispatch('toast', type: 'success', message: "Tanda vital berhasil dikirim ({$count} item).");
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Tanda vital gagal: ' . $e->getMessage());
        }
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

<div class="flex items-center justify-between p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">3</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Observation</div>
            <div class="text-xs text-muted dark:text-gray-400">Tanda vital (TD/nadi/suhu/RR).</div>
            @if ($count > 0)
                <div class="mt-1 font-mono text-xs text-success dark:text-success">
                    {{ $count }} terkirim
                </div>
            @endif
        </div>
    </div>
    <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
        class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
        <span wire:loading.remove wire:target="kirimForCurrent">{{ $count > 0 ? 'Terkirim' : 'Kirim' }}</span>
        <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
    </x-primary-button>
</div>
