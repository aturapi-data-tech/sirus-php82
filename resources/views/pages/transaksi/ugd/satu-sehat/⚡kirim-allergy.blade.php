<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-allergy.blade.php
// Kirim Alergi (AllergyIntolerance, SNOMED CT) — UGD.
// Port dari RJ. Sumber: anamnesa.alergi.{alergi, snomedCode, snomedDisplayEn, snomedDisplayId}.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\AllergyIntoleranceTrait;
use App\Support\AlergiSnomed;

new class extends Component {
    use EmrUGDTrait, AllergyIntoleranceTrait;

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
        $this->count = !empty($satuSehat['allergyId']) ? 1 : 0;
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-allergy-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['allergyId'])) { $this->dispatch('toast', type: 'info', message: 'Alergi sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $recorderId = $this->getDoctorIHS($dataUGD['drId'] ?? '');
            if (empty($recorderId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong — lengkapi di master dokter.'); return; }

            $alergi = $dataUGD['anamnesa']['alergi'] ?? [];
            $alergiText = trim((string) ($alergi['alergi'] ?? ''));
            $snomedCode = trim((string) ($alergi['snomedCode'] ?? ''));

            $tidakAdaAlergi = AlergiSnomed::adalahTidakAdaAlergi($snomedCode);
            // Teks boleh kosong SELAMA kodenya pernyataan "tidak ada alergi" — di situ
            // kode-nya yang bermakna, bukan teksnya.
            if ($alergiText === '' && !$tidakAdaAlergi) { $this->dispatch('toast', type: 'error', message: 'Data alergi belum diisi di anamnesa.'); return; }
            if ($snomedCode === '') { $this->dispatch('toast', type: 'error', message: 'Kode SNOMED Alergi belum diisi di anamnesa (wajib utk Satu Sehat).'); return; }

            $respons = $this->createAllergyIntolerance([
                'patientId'   => $patientId,
                'encounterId' => $satuSehat['encounterId'],
                'recorderId'  => $recorderId,
                'code'        => $snomedCode,
                'display'     => $alergi['snomedDisplayEn'] ?? ($alergi['snomedDisplayId'] ?? $alergiText),
                // "Tidak ada alergi" (mis. 716186003) = pernyataan TIADA alergi → type &
                // criticality DIHILANGKAN (null), keduanya atribut alergi yang ADA.
                // category TETAP dikirim: SATUSEHAT mewajibkannya (RuleNumber 10075),
                // pemetaannya di AlergiSnomed::kategoriFhir().
                'type'        => $tidakAdaAlergi ? null : 'allergy',
                'category'    => AlergiSnomed::kategoriFhir($snomedCode),
                'criticality' => $tidakAdaAlergi ? null : 'low',
                'note'        => $alergiText,
                'onset'       => $this->parseDate($dataUGD['rjDate'] ?? '')->toIso8601String(),
            ]);

            if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'Alergi gagal: respons tanpa id.'); return; }

            $satuSehat['allergyId'] = $respons['id'];
            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Alergi berhasil dikirim.');
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Alergi gagal: ' . $e->getMessage());
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function getDoctorIHS(string $drId): string
    {
        if (empty($drId)) return '';
        return (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '');
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
            <span class="text-sm font-bold">11</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Allergy Intolerance</div>
            <div class="text-xs text-muted dark:text-gray-400">Riwayat alergi pasien (SNOMED CT).</div>
            @if ($count > 0)
                <div class="mt-1 font-mono text-xs text-success dark:text-success">
                    terkirim
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
