<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-diet.blade.php
// Kirim Instruksi Gizi / diet → NutritionOrder — RI.
//
// Sumber = datadaftarri_json → pengkajianDokter.rencana.diet (FREE-TEXT).
// MVP text-only: kirim oralDiet.type.text (tanpa coding SNOMED) + instruction.
// Upgrade nanti: master diet ber-SNOMED → isi dietCode.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\NutritionOrderTrait;

new class extends Component {
    use EmrRITrait, NutritionOrderTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /** Teks diet yang AKAN dikirim, dari sumber yang SAMA dengan kirim(). */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->riHdrNo)) {
            return [];
        }

        $diet = trim((string) ($this->findDataRI($this->riHdrNo)['pengkajianDokter']['rencana']['diet'] ?? ''));
        if ($diet === '') {
            return [];
        }

        return [
            ['label' => 'Instruksi diet', 'nilai' => $diet,
             'ket' => 'dikirim sebagai NutritionOrder free-text — belum ber-SNOMED'],
        ];
    }

    public bool $hasDiet = false;

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
        $this->count = !empty($satuSehat['nutritionOrderId']) ? 1 : 0;
        $this->hasDiet = trim((string) ($data['pengkajianDokter']['rencana']['diet'] ?? '')) !== '';
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
    #[On('ss-diet-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        $this->kirimInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'diet');
    }

    public function kirimInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['nutritionOrderId'])) { $this->dispatch('toast', type: 'info', message: 'Instruksi gizi sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $ordererId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($ordererId)) { $this->dispatch('toast', type: 'error', message: 'IHS DPJP (dr_uuid) kosong — lengkapi di master dokter.'); return; }

            $dietText = trim((string) ($dataRI['pengkajianDokter']['rencana']['diet'] ?? ''));
            if ($dietText === '') { $this->dispatch('toast', type: 'error', message: 'Diet belum diisi di Pengkajian Dokter (Rencana).'); return; }

            $dateTime = $this->parseDate($dataRI['entryDate'] ?? '')->toIso8601String();

            $respons = $this->createNutritionOrder([
                'patientId'   => $patientId,
                'encounterId' => $satuSehat['encounterId'],
                'ordererId'   => $ordererId,
                'dietText'    => $dietText,
                'instruction' => $dietText,
                'dateTime'    => $dateTime,
            ]);

            if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'Instruksi gizi gagal: respons tanpa id.'); return; }

            $satuSehat['nutritionOrderId'] = $respons['id'];
            $this->saveResult($riHdrNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Instruksi gizi (diet) berhasil dikirim.');
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Instruksi gizi gagal: ' . $e->getMessage());
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

<div class="p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">11</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">NutritionOrder <span class="text-xs font-normal text-muted">(Diet)</span></div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Instruksi gizi dari Pengkajian Dokter. Text-only (tanpa kode SNOMED).
                    @if (!$hasDiet)
                        <span class="text-amber-600 dark:text-amber-400">Diet belum diisi.</span>
                    @endif
                </div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        terkirim
                    </div>
                @endif
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter || !$hasDiet"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent,kirim">
                    <span class="inline-flex items-center gap-1.5">
                        <x-satu-sehat.ikon-tombol :selesai="$count > 0" jenis="kirim" />
                        {{ $count > 0 ? 'Terkirim' : 'Kirim' }}
                    </span>
                </span>
            <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
        </x-primary-button>
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Diet belum diisi di Pengkajian Dokter (Rencana) — Kirim akan ditolak." />
</div>
