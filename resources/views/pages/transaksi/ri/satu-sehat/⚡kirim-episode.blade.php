<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-episode.blade.php
// Kirim EpisodeOfCare — RI (1 rawat inap = 1 episode).
//
// episodeNo = rihdr_no, careManager = DPJP, start = tgl masuk, end = tgl pulang.
// Setelah dibuat, Encounter RI di-link ke episode (Encounter.episodeOfCare[]).
// Finish: saat pasien pulang → PUT status 'finished' + period.end = exitDate.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\EpisodeOfCareTrait;
use App\Http\Traits\SATUSEHAT\EncounterTrait;

new class extends Component {
    use EmrRITrait, EpisodeOfCareTrait, EncounterTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /** EpisodeOfCare merangkum satu episode rawat inap dari Encounter yang sudah ada. */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->riHdrNo)) {
            return [];
        }

        $data = $this->findDataRI($this->riHdrNo);
        $satuSehat = $data['satusehat'] ?? [];
        if (empty($satuSehat['encounterId'])) {
            return [];
        }

        return [
            ['label' => 'Pasien', 'nilai' => (string) ($data['regName'] ?? '-'),
             'ket' => 'No. RM ' . ($data['regNo'] ?? '-')],
            ['label' => 'Encounter dirujuk', 'nilai' => (string) $satuSehat['encounterId']],
            ['label' => 'Status episode', 'nilai' => 'active'],
            ['label' => 'Mulai', 'nilai' => trim((string) ($data['entryDate'] ?? '-'))],
        ];
    }

    public ?string $episodeId = null;
    public bool $episodeFinished = false;

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
        $this->episodeId = $satuSehat['episodeOfCareId'] ?? null;
        $this->episodeFinished = !empty($satuSehat['episodeOfCareFinished']);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    public function finishForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->finish($this->riHdrNo);
        $this->reloadState();
    }

    /**
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya — berhasil, ditolak
     * SATUSEHAT, atau berhenti di guard — langkah ini WAJIB memberi kabar, supaya
     * orkestrator bisa melanjutkan. Tanpa ini rantai menggantung diam-diam pada
     * langkah pertama yang gagal, dan petugas cuma melihat modal yang membeku.
     */
    #[On('ss-episode-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        $this->kirimInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'episode');
    }

    public function kirimInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['episodeOfCareId'])) { $this->dispatch('toast', type: 'info', message: 'EpisodeOfCare sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $careManagerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($careManagerId)) { $this->dispatch('toast', type: 'error', message: 'IHS DPJP (dr_uuid) kosong — lengkapi di master dokter.'); return; }

            $start = $this->parseDate($dataRI['entryDate'] ?? '')->toIso8601String();

            $respons = $this->createEpisodeOfCare([
                'episodeNo'     => (string) $riHdrNo,
                'patientId'     => $patientId,
                'careManagerId' => $careManagerId,
                'start'         => $start,
                'status'        => 'active',
            ]);
            $episodeId = $respons['id'] ?? null;
            if (empty($episodeId)) { $this->dispatch('toast', type: 'error', message: 'EpisodeOfCare gagal: respons tanpa id.'); return; }

            $satuSehat['episodeOfCareId'] = $episodeId;

            // Link Encounter → EpisodeOfCare (Encounter.episodeOfCare[]).
            try {
                $enc = $this->getEncounter($satuSehat['encounterId']);
                $ref = 'EpisodeOfCare/' . $episodeId;
                $existingRefs = collect($enc['episodeOfCare'] ?? [])->pluck('reference')->all();
                if (!in_array($ref, $existingRefs, true)) {
                    $enc['episodeOfCare'][] = ['reference' => $ref];
                    $this->makeRequest('put', "Encounter/{$satuSehat['encounterId']}", $enc);
                }
            } catch (\Throwable $e) {
                // link opsional — episode tetap tersimpan meski link gagal
            }

            $this->saveResult($riHdrNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'EpisodeOfCare berhasil dikirim: ' . $episodeId);
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'EpisodeOfCare gagal: ' . $e->getMessage());
        }
    }

    /** Pembungkus rantai — lihat catatan di kirim(). */
    #[On('ss-episode-ri.finish')]
    public function finish(string $riHdrNo): void
    {
        $this->finishInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'episode-selesai');
    }

    public function finishInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['episodeOfCareId'])) { $this->dispatch('toast', type: 'error', message: 'EpisodeOfCare belum dibuat.'); return; }
            if (!empty($satuSehat['episodeOfCareFinished'])) { $this->dispatch('toast', type: 'info', message: 'EpisodeOfCare sudah finished.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            $careManagerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');

            $exitStr = trim((string) ($dataRI['exitDate'] ?? ''));
            $end = ($exitStr !== '' ? $this->parseDate($exitStr) : Carbon::now())->toIso8601String();
            $start = $this->parseDate($dataRI['entryDate'] ?? '')->toIso8601String();

            $this->updateEpisodeOfCare($satuSehat['episodeOfCareId'], [
                'episodeNo'     => (string) $riHdrNo,
                'patientId'     => $patientId,
                'careManagerId' => $careManagerId,
                'start'         => $start,
                'end'           => $end,
                'status'        => 'finished',
            ]);
            $satuSehat['episodeOfCareFinished'] = true;

            $this->saveResult($riHdrNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'EpisodeOfCare finished.');
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Finish EpisodeOfCare gagal: ' . $e->getMessage());
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

<div class="space-y-3">
    <div class="p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($episodeId) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                    <span class="text-sm font-bold">2</span>
                </div>
                <div>
                    <div class="font-semibold text-ink dark:text-gray-100">EpisodeOfCare</div>
                    <div class="text-xs text-muted dark:text-gray-400">Episode rawat inap (1 RI = 1 episode). Link ke Encounter.</div>
                    @if (!empty($episodeId))
                        <div class="mt-1 font-mono text-xs text-success dark:text-success">ID: {{ $episodeId }}</div>
                    @endif
                </div>
            </div>
            <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
                class="!bg-teal-600 hover:!bg-teal-700 {{ !empty($episodeId) ? '!bg-emerald-600' : '' }}">
                <span wire:loading.remove wire:target="kirimForCurrent">
                    <span class="inline-flex items-center gap-1.5">
                        <x-satu-sehat.ikon-tombol :selesai="!empty($episodeId)" jenis="kirim" />
                        {{ !empty($episodeId) ? 'Terkirim' : 'Kirim' }}
                    </span>
                </span>
                <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
            </x-primary-button>
        </div>

        <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka"
            :baris="$pratinjauTerbuka ? $this->pratinjau : []"
            kosong="Encounter belum dikirim — EpisodeOfCare merujuk padanya." />
    </div>

    @if (!empty($episodeId))
        <div class="flex items-center justify-between p-4 bg-canvas border-2 border-teal-300 shadow-sm rounded-xl dark:bg-gray-900 dark:border-teal-700">
            <div class="flex items-center gap-3">
                <div
                    class="flex items-center justify-center w-8 h-8 rounded-full {{ $episodeFinished ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-ink dark:text-gray-100">Selesaikan Episode</div>
                    <div class="text-xs text-muted dark:text-gray-400">Status finished + period.end saat pasien pulang.</div>
                </div>
            </div>
            <x-primary-button type="button" wire:click="finishForCurrent" wire:loading.attr="disabled"
                class="{{ $episodeFinished ? '!bg-emerald-600' : '!bg-teal-600 hover:!bg-teal-700' }}">
                <span wire:loading.remove wire:target="finishForCurrent">
                    <span class="inline-flex items-center gap-1.5">
                        <x-satu-sehat.ikon-tombol :selesai="$episodeFinished" jenis="finish" />
                        {{ $episodeFinished ? 'Selesai' : 'Finish' }}
                    </span>
                </span>
                <span wire:loading wire:target="finishForCurrent"><x-loading />...</span>
            </x-primary-button>
        </div>
    @endif
</div>
