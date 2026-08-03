<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-condition.blade.php
// Step 2: Kirim Diagnosa ICD-10 (UGD). Sumber JSON: dataDaftarUGD['diagnosis'][] { icdX/diagId, diagDesc }.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\ConditionTrait;

new class extends Component {
    use EmrUGDTrait, ConditionTrait;

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
        $this->count = count($satuSehat['conditionIds'] ?? []);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-condition-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['conditionIds'])) { $this->dispatch('toast', type: 'info', message: 'Diagnosa sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $ugdDate = $this->parseDate($dataUGD['rjDate'] ?? '');
            $diagnosaList = $dataUGD['diagnosis'] ?? [];
            if (empty($diagnosaList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data diagnosa UGD untuk dikirim.'); return; }

            $satuSehat['conditionIds'] = [];
            $count = 0;
            foreach ($diagnosaList as $diagnosa) {
                $kode = $diagnosa['icdX'] ?? ($diagnosa['diagId'] ?? '');
                $display = $diagnosa['diagDesc'] ?? '';
                if (empty($kode)) continue;

                $respons = $this->createFinalDiagnosis([
                    'patientId' => $patientId, 'encounterId' => $satuSehat['encounterId'],
                    'icd10_code' => $kode, 'icd10_display' => $display,
                    'diagnosis_text' => trim("{$kode} - {$display}", ' -'),
                    'recordedDate' => $ugdDate->toIso8601String(),
                ]);
                if (!empty($respons['id'])) { $satuSehat['conditionIds'][] = $respons['id']; $count++; }
            }

            if ($count === 0) { $this->dispatch('toast', type: 'error', message: 'Semua diagnosa tanpa kode ICD-10 — tidak ada yang dikirim.'); return; }

            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: "Diagnosa berhasil dikirim ({$count} item).");
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Diagnosa gagal: ' . $e->getMessage());
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
            <span class="text-sm font-bold">2</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Condition</div>
            <div class="text-xs text-muted dark:text-gray-400">Diagnosa pasien (ICD-10).</div>
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
