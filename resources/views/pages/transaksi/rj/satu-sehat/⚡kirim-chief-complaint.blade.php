<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-chief-complaint.blade.php
// Step 6: Kirim Keluhan Utama (Condition / problem-list-item, SNOMED CT)
// Meniru pola kirim-condition. Sumber: anamnesa.keluhanUtama.{keluhanUtama, snomedCode, snomedDisplayEn, snomedDisplayId}.
// Catatan: struktur NESTED di EMR (bukan key flat yang dipakai KirimRawatJalanTrait blueprint).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ConditionTrait;

new class extends Component {
    use EmrRJTrait, ConditionTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Isi yang AKAN dikirim, dari sumber yang SAMA dengan kirim() —
     * anamnesa.keluhanUtama. Kode SNOMED ditampilkan karena kirim() MENOLAK
     * bila kosong, jadi petugas bisa melihat sebabnya sebelum menekan tombol.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $keluhan = $this->findDataRJ($this->rjNo)['anamnesa']['keluhanUtama'] ?? [];
        $teks = trim((string) ($keluhan['keluhanUtama'] ?? ''));
        $kode = trim((string) ($keluhan['snomedCode'] ?? ''));
        if ($teks === '' && $kode === '') {
            return [];
        }

        return [
            ['label' => 'Keluhan utama', 'nilai' => $teks ?: '(kosong)'],
            ['label' => 'Kode SNOMED', 'nilai' => $kode ?: '(belum diisi — Kirim akan ditolak)',
             'ket' => trim((string) ($keluhan['snomedDisplayEn'] ?? ($keluhan['snomedDisplayId'] ?? '')))],
        ];
    }


    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->reloadState();
    }

    #[On('rj-satu-sehat.refresh')]
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
        $data = $this->findDataRJ($this->rjNo);
        if (empty($data)) {
            return;
        }
        $satuSehat = $data['satusehat'] ?? [];
        $this->hasEncounter = !empty($satuSehat['encounterId']);
        $this->count = !empty($satuSehat['chiefComplaintId']) ? 1 : 0;
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    /**
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya — berhasil, ditolak
     * SATUSEHAT, atau berhenti di guard — langkah ini WAJIB memberi kabar, supaya
     * orkestrator bisa melanjutkan. Tanpa ini rantai menggantung diam-diam pada
     * langkah pertama yang gagal, dan petugas cuma melihat modal yang membeku.
     */
    #[On('ss-chief-complaint-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'chief-complaint');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['chiefComplaintId'])) { $this->dispatch('toast', type: 'info', message: 'Keluhan utama sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            // Struktur NESTED anamnesa (bukan key flat blueprint).
            $keluhanUtama = $dataRJ['anamnesa']['keluhanUtama'] ?? [];
            $keluhanText = trim((string) ($keluhanUtama['keluhanUtama'] ?? ''));
            $snomedCode  = trim((string) ($keluhanUtama['snomedCode'] ?? ''));

            if ($keluhanText === '') { $this->dispatch('toast', type: 'error', message: 'Keluhan utama belum diisi di anamnesa.'); return; }
            // createChiefComplaint WAJIB snomed_code (throw bila kosong) → guard ramah dulu.
            if ($snomedCode === '') { $this->dispatch('toast', type: 'error', message: 'Kode SNOMED Keluhan Utama belum diisi di anamnesa (wajib utk Satu Sehat).'); return; }

            $rjDate = $this->parseDate($dataRJ['rjDate'] ?? '');

            $respons = $this->createChiefComplaint([
                'patientId'      => $patientId,
                'encounterId'    => $satuSehat['encounterId'],
                'snomed_code'    => $snomedCode,
                'snomed_display' => $keluhanUtama['snomedDisplayEn'] ?? '',
                'complaint_text' => $keluhanUtama['snomedDisplayId'] ?? $keluhanText,
                'recordedDate'   => $rjDate->toIso8601String(),
            ]);

            if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'Keluhan utama gagal: respons tanpa id.'); return; }

            $satuSehat['chiefComplaintId'] = $respons['id'];
            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Keluhan utama berhasil dikirim.');
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Keluhan utama gagal: ' . $e->getMessage());
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
            $this->lockRJRow($rjNo);
            $data = $this->findDataRJ($rjNo);
            $data['satusehat'] = $satuSehat;
            $this->updateJsonRJ($rjNo, $data);
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
                <span class="text-sm font-bold">6</span>
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
            <span wire:loading.remove wire:target="kirimForCurrent">
                    <span class="inline-flex items-center gap-1.5">
                        <x-satu-sehat.ikon-tombol :selesai="$count > 0" jenis="kirim" />
                        {{ $count > 0 ? 'Terkirim' : 'Kirim' }}
                    </span>
                </span>
            <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
        </x-primary-button>
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Keluhan utama atau kode SNOMED-nya belum diisi di anamnesa — Kirim akan ditolak." />
</div>
