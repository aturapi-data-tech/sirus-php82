<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-medication-request.blade.php
// Step 6 (RI): Kirim Resep Obat (MedicationRequest).
//
// Sumber = datadaftarri_json → eresepHdr[].eresep[] (obat non-racikan).
// JSON RI hanya simpan productId/productName → KFA (product_id_satusehat) DIAMBIL via
// join ke master immst_products. Item tanpa KFA di-SKIP (tak bisa dikirim ke SATUSEHAT).
// Racikan (eresepRacikan[]) BELUM ditangani di MVP ini (compound perlu contained ingredients).

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\MedicationRequestTrait;
use App\Support\EresepJson;
use App\Support\RacikanKfa;

new class extends Component {
    use EmrRITrait, MedicationRequestTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;
    public int $racikanSiap = 0;    // grup racikan yang semua bahannya ber-KFA
    public int $racikanTakSiap = 0; // grup racikan yang bahannya belum bisa dipetakan

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
        $this->count = count($satuSehat['medicationRequestIds'] ?? []);

        // Racikan dikirim sebagai compound bila SEMUA bahannya bisa dipetakan ke
        // KFA; sisanya dilaporkan di kartu supaya tak hilang diam-diam. Dihitung per
        // GRUP noRacikan (= satu obat racikan), bukan per baris bahan.
        $ringkasRacikan = RacikanKfa::ringkas($data);
        $this->racikanSiap = $ringkasRacikan['siap'];
        $this->racikanTakSiap = $ringkasRacikan['takSiap'];
    }

    /**
     * Kumpulkan item obat non-racikan dari semua eresepHdr, sudah di-enrich KFA.
     * @return array<int, array{code:string, display:string}>
     */
    private function obatList(array $dataRI, ?int &$obatTanpaKfa = null): array
    {
        $obatTanpaKfa = 0;
        $itemList = [];
        foreach ($dataRI['eresepHdr'] ?? [] as $resepHeader) {
            foreach ($resepHeader['eresep'] ?? [] as $obat) {
                $productId = trim((string) ($obat['productId'] ?? ''));
                if ($productId === '') { $obatTanpaKfa++; continue; }
                $itemList[] = ['productId' => $productId, 'productName' => (string) ($obat['productName'] ?? '')];
            }
        }
        if (empty($itemList)) return [];

        // Bulk lookup KFA dari master obat
        $productIdList = array_values(array_unique(array_column($itemList, 'productId')));
        $kfaMap = DB::table('immst_products')
            ->whereIn('product_id', $productIdList)
            ->get(['product_id', 'product_id_satusehat', 'product_name_satusehat'])
            ->keyBy('product_id');

        $obatKfaList = [];
        foreach ($itemList as $obat) {
            $master = $kfaMap->get($obat['productId']);
            $kfaCode = (string) ($master->product_id_satusehat ?? '');
            if ($kfaCode === '') { $obatTanpaKfa++; continue; } // tak ada KFA → dilewati, dilaporkan di toast
            $obatKfaList[] = [
                'code'    => $kfaCode,
                'display' => (string) ($master->product_name_satusehat ?? '') ?: $obat['productName'],
            ];
        }
        return $obatKfaList;
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    #[On('ss-medication-request-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['medicationRequestIds'])) { $this->dispatch('toast', type: 'info', message: 'Resep obat sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');
            $authoredOn = $this->parseDate($dataRI['entryDate'] ?? '')->toIso8601String();
            $orgId = env('SATUSEHAT_ORGANIZATION_ID');
            $drDesc = $dataRI['drDesc'] ?? '';
            $patientName = $dataRI['regName'] ?? '';

            $obatTanpaKfa = 0;
            $obatList = $this->obatList($dataRI, $obatTanpaKfa);
            $racikanList = RacikanKfa::grupList($dataRI);
            $adaRacikanSiap = array_filter($racikanList, fn($grup) => $grup['siap']) !== [];

            if (empty($obatList) && !$adaRacikanSiap) {
                $this->dispatch('toast', type: 'error',
                    message: 'Tidak ada obat yang bisa dikirim: belum ada padanan kode KFA di Master Obat.');
                return;
            }

            $satuSehat['medicationRequestIds'] = [];
            foreach ($obatList as $indeks => $obat) {
                $kfaCode = $obat['code'];
                $kfaDisplay = $obat['display'];
                $nomorUrut = $indeks + 1;
                $itemId = "{$riHdrNo}-{$nomorUrut}";

                $respons = $this->createMedicationRequest([
                    'registrationId' => $kfaCode, 'orgId' => $orgId, 'medContainedId' => "med-{$itemId}",
                    'medicationCode' => $kfaCode, 'medicationDisplay' => $kfaDisplay,
                    'medicationFormCode' => 'BS066', 'medicationFormDisplay' => 'Tablet',
                    'medicationTypeCode' => 'NC', 'medicationTypeDisplay' => 'Non-compound',
                    'prescriptionId' => $riHdrNo, 'prescriptionItemId' => $itemId,
                    'patientId' => $patientId, 'patientName' => $patientName,
                    'encounterId' => $satuSehat['encounterId'], 'requesterId' => $practitionerId, 'requesterName' => $drDesc,
                    'authoredOn' => $authoredOn, 'category' => 'inpatient',
                    'dosageInstruction' => [], 'dispenseRequest' => [], 'reasonReference' => [],
                ]);
                if (!empty($respons['id'])) $satuSehat['medicationRequestIds'][] = $respons['id'];
            }


            // ── RACIKAN (compound) ──────────────────────────────────────────
            // Campurannya tak punya kode KFA; yang ber-KFA adalah tiap bahannya.
            // Grup yang bahannya tak lengkap TIDAK dikirim dan dilaporkan apa adanya.
            $racikanTerkirim = 0;
            $racikanGagal = [];
            foreach ($racikanList as $grup) {
                if (!$grup['siap']) {
                    $racikanGagal[] = "{$grup['noRacikan']} ({$grup['alasan']})";
                    continue;
                }

                $nomorItem = count($satuSehat['medicationRequestIds']) + 1;
                $itemIdRacikan = "$riHdrNo-{$nomorItem}";
                $namaRacikan = 'Racikan ' . $grup['noRacikan'] . ' (' . $grup['jumlahBahan'] . ' bahan)';

                $respons = $this->createMedicationRequest([
                    'registrationId' => "RACIKAN-{$itemIdRacikan}", 'orgId' => $orgId,
                    'medContainedId' => "med-{$itemIdRacikan}",
                    'medicationCode' => '', 'medicationDisplay' => $namaRacikan,
                    'ingredient' => RacikanKfa::fhirIngredient($grup['bahanList']),
                    'medicationFormCode' => 'BS066', 'medicationFormDisplay' => 'Tablet',
                    'medicationTypeCode' => 'SD', 'medicationTypeDisplay' => 'Compound',
                    'prescriptionId' => $riHdrNo, 'prescriptionItemId' => $itemIdRacikan,
                    'patientId' => $patientId, 'patientName' => $patientName,
                    'encounterId' => $satuSehat['encounterId'], 'requesterId' => $practitionerId, 'requesterName' => $drDesc,
                    'authoredOn' => $authoredOn, 'category' => 'inpatient',
                    'dosageInstruction' => [], 'dispenseRequest' => [], 'reasonReference' => [],
                ]);
                if (!empty($respons['id'])) {
                    $satuSehat['medicationRequestIds'][] = $respons['id'];
                    $racikanTerkirim++;
                }
            }

            $this->saveResult($riHdrNo, $satuSehat);

            $pesan = 'Resep obat berhasil dikirim (' . count($satuSehat['medicationRequestIds']) . ' item).';
            if ($racikanTerkirim > 0) {
                $pesan .= " Termasuk {$racikanTerkirim} obat racikan.";
            }
            if ($racikanGagal !== []) {
                $pesan .= ' ' . count($racikanGagal) . ' racikan tidak dikirim — ' . implode('; ', $racikanGagal) . '.';
            }
            if ($obatTanpaKfa > 0) {
                $pesan .= " {$obatTanpaKfa} obat dilewati karena belum ada kode KFA di Master Obat.";
            }
            $this->dispatch('toast', type: 'success', message: $pesan);
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Resep obat gagal: ' . $e->getMessage());
        }
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

<div class="flex items-center justify-between p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">6</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">MedicationRequest</div>
            <div class="text-xs text-muted dark:text-gray-400">
                Resep obat (KFA). Non-racikan.
                @if ($racikanSiap > 0)
                    Racikan: {{ $racikanSiap }} siap kirim.
                @endif
                @if ($racikanTakSiap > 0)
                    <span class="text-amber-600 dark:text-amber-400">{{ $racikanTakSiap }} racikan bahannya belum ber-KFA.</span>
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
