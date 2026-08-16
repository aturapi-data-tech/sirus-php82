<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-chief-complaint.blade.php
// Kirim Keluhan Utama (Condition / problem-list-item, SNOMED CT) — RI.
// Sumber: pengkajianDokter.anamnesa.{keluhanUtama, keluhanUtamaSnomedCode, ...DisplayEn/Id}.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ConditionTrait;

new class extends Component {
    use EmrRITrait, ConditionTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /** Isi yang AKAN dikirim, dari pengkajianDokter.anamnesa — sumber yang SAMA dengan kirim(). */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->riHdrNo)) {
            return [];
        }

        $anamnesa = $this->findDataRI($this->riHdrNo)['pengkajianDokter']['anamnesa'] ?? [];
        $teks = trim((string) ($anamnesa['keluhanUtama'] ?? ''));
        $kode = trim((string) ($anamnesa['keluhanUtamaSnomedCode'] ?? ''));
        if ($teks === '' && $kode === '') {
            return [];
        }

        return [
            ['label' => 'Keluhan utama', 'nilai' => $teks ?: '(kosong)'],
            ['label' => 'Kode SNOMED', 'nilai' => $kode ?: '(belum diisi — Kirim akan ditolak)',
             'ket' => trim((string) ($anamnesa['keluhanUtamaSnomedDisplayEn'] ?? ($anamnesa['keluhanUtamaSnomedDisplayId'] ?? '')))],
        ];
    }


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
        $this->count = !empty($satuSehat['chiefComplaintId']) ? 1 : 0;
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
    #[On('ss-chief-complaint-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        $this->kirimInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'chief-complaint');
    }

    public function kirimInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['chiefComplaintId'])) { $this->dispatch('toast', type: 'info', message: 'Keluhan utama sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $anamnesa = $dataRI['pengkajianDokter']['anamnesa'] ?? [];
            $keluhanText = trim((string) ($anamnesa['keluhanUtama'] ?? ''));
            $snomedCode  = trim((string) ($anamnesa['keluhanUtamaSnomedCode'] ?? ''));

            if ($keluhanText === '') { $this->dispatch('toast', type: 'error', message: 'Keluhan utama belum diisi di Pengkajian Dokter.'); return; }
            if ($snomedCode === '') { $this->dispatch('toast', type: 'error', message: 'Kode SNOMED Keluhan Utama belum diisi di Pengkajian Dokter (wajib utk Satu Sehat).'); return; }

            $recordedDate = $this->parseDate($dataRI['entryDate'] ?? '');

            $respons = $this->createChiefComplaint([
                'patientId'      => $patientId,
                'encounterId'    => $satuSehat['encounterId'],
                'snomed_code'    => $snomedCode,
                'snomed_display' => $anamnesa['keluhanUtamaSnomedDisplayEn'] ?? '',
                'complaint_text' => $anamnesa['keluhanUtamaSnomedDisplayId'] ?? $keluhanText,
                'recordedDate'   => $recordedDate->toIso8601String(),
            ]);

            if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'Keluhan utama gagal: respons tanpa id.'); return; }

            $satuSehat['chiefComplaintId'] = $respons['id'];
            $this->saveResult($riHdrNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Keluhan utama berhasil dikirim.');
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Keluhan utama gagal: ' . $e->getMessage());
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
                <span class="text-sm font-bold">14</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Chief Complaint</div>
                <div class="text-xs text-muted dark:text-gray-400">Keluhan utama pasien (SNOMED CT).</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        terkirim
                    </div>
                @endif
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
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
        kosong="Keluhan utama atau kode SNOMED-nya belum diisi di Pengkajian Dokter — Kirim akan ditolak." />
</div>
