<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-medication-request.blade.php
// Step 5: Kirim Resep Obat (MedicationRequest, KFA). Sumber: dataDaftarUGD['eresep'].

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\MedicationRequestTrait;
use App\Support\EresepJson;

new class extends Component {
    use EmrUGDTrait, MedicationRequestTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;
    public int $racikanSkipped = 0; // grup racikan yang belum didukung (compound)
    public int $siapKirim = 0;      // obat non-racikan yang sudah punya kode KFA
    public int $obatTanpaKfa = 0;   // non-racikan yang dilewati: productId/KFA kosong

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
        $this->count = count($satuSehat['medicationRequestIds'] ?? []);
        // Racikan belum didukung (compound). Dihitung & ditampilkan supaya tidak
        // hilang diam-diam — sebelumnya UGD sama sekali tak menyadarinya.
        $this->racikanSkipped = EresepJson::jumlahRacikan($data);

        $tanpaKfa = 0;
        $this->siapKirim = count($this->obatList($data, $tanpaKfa));
        $this->obatTanpaKfa = $tanpaKfa;
    }

    /**
     * Obat non-racikan yang siap dikirim: kode KFA diambil dari master obat
     * (immst_products.product_id_satusehat) lewat productId — JSON e-resep sendiri
     * tidak menyimpan kode KFA.
     *
     * $obatTanpaKfa diisi jumlah item yang terpaksa dilewati (productId kosong atau
     * master belum punya KFA) supaya bisa dilaporkan, bukan hilang diam-diam.
     *
     * @return array<int, array{code:string, display:string}>
     */
    private function obatList(array $dataUGD, ?int &$obatTanpaKfa = null): array
    {
        $obatTanpaKfa = 0;
        $itemList = [];
        foreach (EresepJson::lembar($dataUGD) as $lembar) {
            foreach ($lembar['nonRacikan'] as $obat) {
                $productId = trim((string) ($obat['productId'] ?? ''));
                if ($productId === '') {
                    $obatTanpaKfa++;
                    continue;
                }
                $itemList[] = ['productId' => $productId, 'productName' => (string) ($obat['productName'] ?? '')];
            }
        }
        if (empty($itemList)) {
            return [];
        }

        $kfaMap = DB::table('immst_products')
            ->whereIn('product_id', array_values(array_unique(array_column($itemList, 'productId'))))
            ->get(['product_id', 'product_id_satusehat', 'product_name_satusehat'])
            ->keyBy('product_id');

        $obatKfaList = [];
        foreach ($itemList as $obat) {
            $master = $kfaMap->get($obat['productId']);
            $kfaCode = trim((string) ($master->product_id_satusehat ?? ''));
            if ($kfaCode === '') {
                $obatTanpaKfa++;
                continue;
            }
            $obatKfaList[] = [
                'code' => $kfaCode,
                'display' => trim((string) ($master->product_name_satusehat ?? '')) ?: $obat['productName'],
            ];
        }

        return $obatKfaList;
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-medication-request-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['medicationRequestIds'])) { $this->dispatch('toast', type: 'info', message: 'Resep obat sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
            $ugdDate = $this->parseDate($dataUGD['rjDate'] ?? '');
            $orgId = env('SATUSEHAT_ORGANIZATION_ID');
            $drDesc = $dataUGD['drDesc'] ?? '';
            $patientName = $dataUGD['regName'] ?? '';

            $obatTanpaKfa = 0;
            $obatList = $this->obatList($dataUGD, $obatTanpaKfa);
            if (empty($obatList)) {
                $pesan = $this->racikanSkipped > 0 && $obatTanpaKfa === 0
                    ? 'Resep pasien ini hanya racikan — pengiriman racikan (compound) belum didukung.'
                    : 'Tidak ada obat non-racikan ber-KFA untuk dikirim. Pastikan product_id_satusehat terisi di Master Obat.';
                $this->dispatch('toast', type: 'error', message: $pesan);
                return;
            }

            $satuSehat['medicationRequestIds'] = [];
            foreach ($obatList as $indeks => $obat) {
                $kfaCode = $obat['code'];
                $kfaDisplay = $obat['display'];

                $itemId = "{$rjNo}-" . ($indeks + 1);
                $respons = $this->createMedicationRequest([
                    'registrationId' => $kfaCode, 'orgId' => $orgId, 'medContainedId' => "med-{$itemId}",
                    'medicationCode' => $kfaCode, 'medicationDisplay' => $kfaDisplay,
                    'medicationFormCode' => 'BS066', 'medicationFormDisplay' => 'Tablet',
                    'medicationTypeCode' => 'NC', 'medicationTypeDisplay' => 'Non-compound',
                    'prescriptionId' => $rjNo, 'patientId' => $patientId, 'patientName' => $patientName,
                    'encounterId' => $satuSehat['encounterId'], 'requesterId' => $practitionerId, 'requesterName' => $drDesc,
                    // Encounter UGD kelasnya EMER (rawat jalan darurat), bukan rawat inap —
                    // kategorinya ikut 'outpatient' seperti RJ, bukan 'inpatient'.
                    'authoredOn' => $ugdDate->toIso8601String(), 'category' => 'outpatient',
                    'dosageInstruction' => [], 'dispenseRequest' => [], 'reasonReference' => [],
                ]);
                if (!empty($respons['id'])) $satuSehat['medicationRequestIds'][] = $respons['id'];
            }

            $this->saveResult($rjNo, $satuSehat);

            // Yang tak terkirim WAJIB dilaporkan — lihat catatan App\Support\EresepJson.
            $pesan = 'Resep obat berhasil dikirim (' . count($satuSehat['medicationRequestIds']) . ' item).';
            if ($this->racikanSkipped > 0) {
                $pesan .= " Catatan: {$this->racikanSkipped} obat racikan belum dikirim (belum didukung).";
            }
            if ($obatTanpaKfa > 0) {
                $pesan .= " {$obatTanpaKfa} obat dilewati karena belum ada kode KFA di Master Obat.";
            }
            $this->dispatch('toast', type: 'success', message: $pesan);
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Resep obat gagal: ' . $e->getMessage());
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
            <span class="text-sm font-bold">5</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Medication Request</div>
            <div class="text-xs text-muted dark:text-gray-400">
                Resep obat (KFA). Non-racikan: {{ $siapKirim }} siap kirim.
                @if ($racikanSkipped > 0)
                    <span class="text-amber-600 dark:text-amber-400">{{ $racikanSkipped }} racikan belum didukung.</span>
                @endif
                @if ($obatTanpaKfa > 0)
                    <span class="text-amber-600 dark:text-amber-400">{{ $obatTanpaKfa }} obat belum ber-KFA di Master Obat.</span>
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
        class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
        <span wire:loading.remove wire:target="kirimForCurrent">{{ $count > 0 ? 'Terkirim' : 'Kirim' }}</span>
        <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
    </x-primary-button>
</div>
