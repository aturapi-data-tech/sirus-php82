<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-encounter.blade.php
// Step 1: Kirim Kunjungan UGD (Encounter, class EMER).
// BEDA dari RJ: class EMER; UGD tanpa poli (insert poli_id=null) → lokasi dari
//   rstxn_ugdhdrs.poli_id bila ada, jika tidak dari env SATUSEHAT_IGD_LOCATION_ID.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\EncounterTrait;

new class extends Component {
    use EmrUGDTrait, EncounterTrait;

    public ?string $rjNo = null;
    public ?string $encounterId = null;
    public bool $encounterInProgress = false;
    public bool $encounterFinished = false;

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
        $this->encounterId = $satuSehat['encounterId'] ?? null;
        $this->encounterInProgress = !empty($satuSehat['encounterInProgress']);
        $this->encounterFinished = !empty($satuSehat['encounterFinished']);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    public function finishForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->finish($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-encounter-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }
            $satuSehat = $dataUGD['satusehat'] ?? [];

            $regNo = $dataUGD['regNo'] ?? '';
            $patientId = $regNo ? (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '') : '';
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS kosong. Daftarkan pasien ke SATUSEHAT via Master Pasien.'); return; }

            $drId = $dataUGD['drId'] ?? '';
            $practitionerId = $drId ? (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '') : '';
            if (empty($practitionerId)) { $this->dispatch('toast', type: 'error', message: 'Dokter IHS (dr_uuid) kosong.'); return; }

            // Lokasi UGD: coba poli_id dari header (kadang null) → poli_uuid; fallback env IGD.
            $locationId = $this->resolveUgdLocation($rjNo);
            if (empty($locationId)) { $this->dispatch('toast', type: 'error', message: 'Location IHS IGD kosong. Set env SATUSEHAT_IGD_LOCATION_ID atau poli_uuid IGD.'); return; }

            $ugdDate = $this->parseDate($dataUGD['rjDate'] ?? '');

            if (empty($satuSehat['encounterId'])) {
                $respons = $this->createNewEncounter([
                    'encounterId' => 'UGD-' . $rjNo,
                    'patientId' => $patientId,
                    'patientName' => $dataUGD['regName'] ?? '',
                    'practitionerId' => $practitionerId,
                    'practitionerName' => $dataUGD['drDesc'] ?? '',
                    'locationId' => $locationId,
                    'class_code' => 'EMER',
                    'startDate' => $ugdDate->toIso8601String(),
                ]);
                $satuSehat['encounterId'] = $respons['id'] ?? null;
            }

            if (!empty($satuSehat['encounterId']) && empty($satuSehat['encounterInProgress'])) {
                $this->startRoomEncounter($satuSehat['encounterId'], [
                    'startDate' => $ugdDate->toIso8601String(),
                    'locationId' => $locationId,
                ]);
                $satuSehat['encounterInProgress'] = true;
            }

            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Encounter UGD dikirim: ' . ($satuSehat['encounterId'] ?? '-'));
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Encounter gagal: ' . $e->getMessage());
        }
    }

    #[On('ss-encounter-ugd.finish')]
    public function finish(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Encounter belum dibuat.'); return; }
            if (!empty($satuSehat['encounterFinished'])) { $this->dispatch('toast', type: 'info', message: 'Encounter sudah finished.'); return; }

            // Waktu selesai = jam layanan berakhir, bukan now() (jam petugas mengklik).
            // UGD TIDAK memakai task 5 — probe 150 kunjungan: task5 terisi 0x, sedangkan
            // "Selesai Pemeriksaan" (perencanaan.pengkajianMedis) terisi 91x.
            $task = $dataUGD['taskIdPelayanan'] ?? [];
            $waktuSelesai = trim((string) ($task['taskId7'] ?? ''))
                ?: trim((string) ($dataUGD['perencanaan']['pengkajianMedis']['selesaiPemeriksaan'] ?? ''));
            $akhirIso = $waktuSelesai !== ''
                ? $this->parseDate($waktuSelesai)->toIso8601String()
                : now()->toIso8601String();

            // Encounter.diagnosis wajib (RuleNumber 10457) dan harus merujuk Condition yang
            // sudah dikirim — tolak lebih dulu, jangan kirim lalu pasti ditolak server.
            $conditionIdList = $satuSehat['conditionIds'] ?? [];
            if (empty($conditionIdList)) {
                $this->dispatch('toast', type: 'error',
                    message: 'Kirim Diagnosa (Condition) dulu — SATUSEHAT mewajibkan Encounter.diagnosis saat finish.');
                return;
            }

            // statusHistory tiap entri wajib start+end (Rule 10122) — dirapikan di trait.
            $encounterTersimpan = $this->siapkanFinishEncounter(
                $this->getEncounter($satuSehat['encounterId']), $akhirIso, $conditionIdList
            );
            $this->makeRequest('put', "Encounter/{$satuSehat['encounterId']}", $encounterTersimpan);

            $satuSehat['encounterFinished'] = true;

            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Encounter finished.');
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Finish Encounter gagal: ' . $e->getMessage());
        }
    }

    // Location IGD (UGD tak punya poli) — di-HARDCODE. Env boleh override utk sandbox.
    private const UGD_LOCATION_UUID = '7cfc8905-ed98-4077-afcd-e2c48890b765';

    private function resolveUgdLocation(string $rjNo): string
    {
        return (string) (env('SATUSEHAT_IGD_LOCATION_ID') ?: self::UGD_LOCATION_UUID);
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
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $encounterId ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">1</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Encounter <span class="text-xs font-normal text-muted">(EMER)</span></div>
            <div class="text-xs text-muted dark:text-gray-400">Kunjungan UGD — akar, wajib pertama.</div>
            @if ($encounterId)
                <div class="mt-1 font-mono text-xs text-success dark:text-success">
                    {{ $encounterFinished ? 'finished' : ($encounterInProgress ? 'in-progress' : 'arrived') }}
                </div>
            @endif
        </div>
    </div>
    <div class="flex items-center gap-2">
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $encounterId ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent">{{ $encounterId ? 'Terkirim' : 'Kirim' }}</span>
            <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
        </x-primary-button>
        @if ($encounterId && !$encounterFinished)
            <x-secondary-button type="button" wire:click="finishForCurrent" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="finishForCurrent">Finish</span>
                <span wire:loading wire:target="finishForCurrent"><x-loading />...</span>
            </x-secondary-button>
        @endif
    </div>
</div>
