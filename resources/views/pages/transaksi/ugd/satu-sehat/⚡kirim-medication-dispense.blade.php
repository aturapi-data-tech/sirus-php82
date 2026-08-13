<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-medication-dispense.blade.php
// Step 6: Kirim Obat Diserahkan (MedicationDispense). MVP dari eresep; WAJIB Resep dikirim dulu.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\MedicationDispenseTrait;
use App\Support\Terminologi\MedicationRequestItem;
use App\Support\Terminologi\RacikanKfa;

new class extends Component {
    use EmrUGDTrait, MedicationDispenseTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public bool $hasResep = false;
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
        $this->hasResep     = !empty($satuSehat['medicationRequestIds']);
        $this->count        = count($satuSehat['medicationDispenseIds'] ?? []);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-medication-dispense-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            $mrIds = $satuSehat['medicationRequestIds'] ?? [];
            if (empty($mrIds)) { $this->dispatch('toast', type: 'error', message: 'Kirim Resep (MedicationRequest) terlebih dahulu.'); return; }
            if (!empty($satuSehat['medicationDispenseIds'])) { $this->dispatch('toast', type: 'info', message: 'Obat diserahkan sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $performerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($performerId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $orgId       = env('SATUSEHAT_ORGANIZATION_ID');
            $patientName = $dataUGD['regName'] ?? '';
            $nowIso      = Carbon::now()->toIso8601String();

            // Sumber pasangan resep→penyerahan: peta yang dicatat saat MedicationRequest
            // dikirim. Sebelumnya dipasangkan lewat URUTAN daftar — geser satu item saja,
            // obat tertaut ke resep yang salah tanpa ada yang tahu.
            // Resep yang dikirim sebelum peta ini ada disusun ulang dari urutan
            // pengirimannya (non-racikan dulu, lalu racikan) — dan ditolak bila
            // jumlahnya tak cocok, daripada memasangkan obat ke resep yang salah.
            $itemList = MedicationRequestItem::ambil($satuSehat, $dataUGD);
            if (empty($itemList)) {
                $this->dispatch('toast', type: 'error',
                    message: 'Rincian item resep tak bisa dipastikan (daftar obat berubah setelah resep dikirim). Dispense dibatalkan.');
                return;
            }

            $racikanPerNomor = [];
            foreach (RacikanKfa::grupList($dataUGD) as $grup) {
                $racikanPerNomor[$grup['noRacikan']] = $grup;
            }

            $satuSehat['medicationDispenseIds'] = [];
            $dilewati = [];
            foreach ($itemList as $indeks => $item) {
                $adalahRacikan = ($item['jenis'] ?? '') === 'racikan';
                $ingredient = [];

                if ($adalahRacikan) {
                    $grup = $racikanPerNomor[$item['kunci']] ?? null;
                    if ($grup === null || !$grup['siap']) {
                        $dilewati[] = 'racikan ' . $item['kunci'];
                        continue;
                    }
                    $ingredient = RacikanKfa::fhirIngredient($grup['bahanList']);
                }

                $itemId = "$rjNo-" . ($indeks + 1);
                $quantity = (int) ($item['qty'] ?? 1) ?: 1;

                $respons = $this->createMedicationDispense([
                    'orgId' => $orgId,
                    'registrationId' => $adalahRacikan ? "RACIKAN-{$itemId}" : $item['kode'],
                    'prescriptionItemId' => $itemId,
                    'medContainedId' => "meddisp-{$itemId}",
                    'medicationCode' => $item['kode'], 'medicationDisplay' => $item['display'],
                    'ingredient' => $ingredient,
                    'medicationFormCode' => 'BS066', 'medicationFormDisplay' => 'Tablet',
                    'medicationTypeCode' => $adalahRacikan ? 'SD' : 'NC',
                    'medicationTypeDisplay' => $adalahRacikan ? 'Compound' : 'Non-compound',
                    'patientId' => $patientId, 'patientName' => $patientName, 'encounterId' => $satuSehat['encounterId'],
                    'status' => 'completed', 'category' => 'outpatient',
                    'whenPrepared' => $nowIso, 'whenHandedOver' => $nowIso,
                    'performer' => [['actor' => ['reference' => "Practitioner/{$performerId}"]]],
                    'dosageInstruction' => [],
                    'authorizingPrescription' => ['reference' => "MedicationRequest/{$item['id']}"],
                    // Satuan pakai v3-orderableDrugForm (pola yang sudah dipakai
                    // KirimRawatJalanTrait). CodeSystem kfa-satuan DITOLAK SATUSEHAT:
                    // "Invalid coding system ... kfa-satuan (RuleNumber: 10050)".
                    'quantity' => ['value' => $quantity, 'unit' => 'Tablet', 'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm', 'code' => 'TAB'],
                    'daysSupply' => ['value' => 1, 'unit' => 'Hari', 'system' => 'http://unitsofmeasure.org', 'code' => 'd'],
                    'receiver' => ['reference' => "Patient/{$patientId}", 'display' => $patientName],
                ]);
                if (!empty($respons['id'])) $satuSehat['medicationDispenseIds'][] = $respons['id'];
            }

            $this->saveResult($rjNo, $satuSehat);
            $pesan = 'Obat diserahkan berhasil dikirim (' . count($satuSehat['medicationDispenseIds']) . ' item).';
            if ($dilewati !== []) {
                $pesan .= ' Dilewati: ' . implode(', ', $dilewati) . '.';
            }
            $this->dispatch('toast', type: 'success', message: $pesan);
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Obat diserahkan gagal: ' . $e->getMessage());
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
            <span class="text-sm font-bold">6</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Medication Dispense</div>
            <div class="text-xs text-muted dark:text-gray-400">Obat diserahkan (butuh resep dikirim dulu).</div>
            @if ($count > 0)
                <div class="mt-1 font-mono text-xs text-success dark:text-success">
                    {{ $count }} terkirim
                </div>
            @elseif (!$hasResep)
                <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">Kirim Resep dulu</div>
            @endif
        </div>
    </div>
    <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter || !$hasResep"
        class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
        <span wire:loading.remove wire:target="kirimForCurrent">{{ $count > 0 ? 'Terkirim' : 'Kirim' }}</span>
        <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
    </x-primary-button>
</div>
