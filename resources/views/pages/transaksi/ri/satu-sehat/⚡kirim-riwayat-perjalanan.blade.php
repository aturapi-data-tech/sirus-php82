<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-riwayat-perjalanan.blade.php
// Kirim Riwayat Perjalanan Penyakit (ClinicalImpression) — Playbook RI bab 6, disamakan dgn
// alur apikesehatan.com: status completed, code SNOMED 312850006 (H/O: Disorder), summary = narasi.
// Sumber narasi: AnamnesaRi::riwayatPenyakitSekarang() (Pengkajian Dokter) (tanpa isian baru).
// Hasil: satusehat.riwayatPerjalananId. OPSIONAL — teks kosong = dilewati.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Support\AnamnesaRi;
use App\Http\Traits\SATUSEHAT\ClinicalImpressionTrait;

new class extends Component {
    use EmrRITrait, ClinicalImpressionTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;
    public int $jumlahTarget = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /** Isi yang AKAN dikirim, dari sumber yang SAMA dengan kirim(). */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->riHdrNo)) {
            return [];
        }

        $narasi = AnamnesaRi::riwayatPenyakitSekarang($this->findDataRI($this->riHdrNo));

        return $narasi === '' ? [] : [['label' => 'Narasi', 'nilai' => mb_strimwidth($narasi, 0, 120, '…'), 'ket' => 'SNOMED 312850006 H/O: Disorder']];
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
        $this->count = !empty($satuSehat['riwayatPerjalananId']) ? 1 : 0;
        $this->jumlahTarget = 1;
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
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya langkah ini WAJIB memberi
     * kabar, supaya orkestrator bisa melanjutkan ke langkah berikutnya.
     */
    #[On('ss-riwayat-perjalanan-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        $this->kirimInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'riwayat-perjalanan');
    }

    public function kirimInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }

            if (!empty($satuSehat['riwayatPerjalananId'])) { $this->dispatch('toast', type: 'info', message: 'Riwayat perjalanan penyakit sudah pernah dikirim.'); return; }

            $narasi = AnamnesaRi::riwayatPenyakitSekarang($dataRI);
            if ($narasi === '') { $this->dispatch('toast', type: 'info', message: 'Riwayat Penyakit Sekarang belum diisi di Pengkajian Dokter (opsional) — dilewati.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }
            $assessorId = $this->getDoctorIHS($dataRI['drId'] ?? '');
            if (empty($assessorId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong — lengkapi di master dokter.'); return; }

            $respons = $this->createClinicalImpression([
                'patientId'   => $patientId,
                'encounterId' => $satuSehat['encounterId'],
                'assessorId'  => $assessorId,
                'effective'   => $this->parseDate($dataRI['entryDate'] ?? '')->toIso8601String(),
                'code'        => ['system' => 'http://snomed.info/sct', 'code' => '312850006', 'display' => 'H/O: Disorder'],
                'summary'     => $narasi,
            ]);
            if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'Riwayat perjalanan penyakit gagal: respons tanpa id.'); return; }

            $satuSehat['riwayatPerjalananId'] = $respons['id'];
            $this->saveResult($riHdrNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Riwayat perjalanan penyakit berhasil dikirim.');
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Riwayat Perjalanan Penyakit gagal: ' . $e->getMessage());
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
                <span class="text-sm font-bold">12</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Riwayat Perjalanan Penyakit</div>
                <div class="text-xs text-muted dark:text-gray-400">Narasi perjalanan penyakit (ClinicalImpression), opsional.</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }}/{{ $jumlahTarget }} terkirim
                    </div>
                @endif
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 && $count >= $jumlahTarget ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent,kirim">
                <span class="inline-flex items-center gap-1.5">
                    <x-satu-sehat.ikon-tombol :selesai="$count > 0 && $count >= $jumlahTarget" jenis="kirim" />
                    {{ $count > 0 && $count >= $jumlahTarget ? 'Terkirim' : 'Kirim' }}
                </span>
            </span>
            <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
        </x-primary-button>
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Riwayat Penyakit Sekarang belum diisi di Pengkajian Dokter (opsional)." />
</div>
