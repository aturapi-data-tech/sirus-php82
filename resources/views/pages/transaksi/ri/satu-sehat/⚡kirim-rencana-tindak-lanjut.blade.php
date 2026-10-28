<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-rencana-tindak-lanjut.blade.php
// Kirim Rencana Tindak Lanjut (ServiceRequest kontrol) — Playbook RI bab 24 (port RJ 23), disamakan dgn
// alur apikesehatan.com: category SNOMED 3457005 Patient referral, code 185389009 Follow-up visit
// (diverifikasi tx.fhir.org), occurrenceDateTime = tanggal kontrol. Sumber: node kontrol (SKDP) —
// tglKontrol, drKontrol, poliKontrolDesc, catatan, noSKDPBPJS. Hasil: satusehat.rencanaTindakLanjutId.
// OPSIONAL — tanpa tanggal kontrol = dilewati.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ServiceRequestTrait;

new class extends Component {
    use EmrRITrait, ServiceRequestTrait;

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

        $kontrol = $this->findDataRI($this->riHdrNo)['kontrol'] ?? [];
        if (blank($kontrol['tglKontrol'] ?? null)) {
            return [];
        }

        return [
            ['label' => 'Kontrol', 'nilai' => (string) $kontrol['tglKontrol'], 'ket' => trim(($kontrol['poliKontrolDesc'] ?? '') . ' — ' . ($kontrol['drKontrolDesc'] ?? ''), ' —')],
            ['label' => 'Kode', 'nilai' => 'SNOMED 185389009 Follow-up visit', 'ket' => 'category 3457005 Patient referral'],
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
        $this->count = !empty($satuSehat['rencanaTindakLanjutId']) ? 1 : 0;
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
    #[On('ss-rencana-tindak-lanjut-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        $this->kirimInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'rencana-tindak-lanjut');
    }

    public function kirimInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }

            if (!empty($satuSehat['rencanaTindakLanjutId'])) { $this->dispatch('toast', type: 'info', message: 'Rencana tindak lanjut sudah pernah dikirim.'); return; }

            $kontrol = $dataRI['kontrol'] ?? [];
            $tglKontrol = trim((string) ($kontrol['tglKontrol'] ?? ''));
            if ($tglKontrol === '') { $this->dispatch('toast', type: 'info', message: 'Belum ada tanggal kontrol (SKDP) — rencana tindak lanjut dilewati (opsional).'); return; }
            try {
                $tglKontrolIso = Carbon::createFromFormat('d/m/Y', $tglKontrol)->format('Y-m-d');
            } catch (\Throwable) {
                $this->dispatch('toast', type: 'error', message: 'Format tanggal kontrol tidak dikenali: ' . $tglKontrol);
                return;
            }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }
            $requesterId = $this->getDoctorIHS($dataRI['drId'] ?? '');
            if (empty($requesterId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong — lengkapi di master dokter.'); return; }
            $dokterKontrolId = $this->getDoctorIHS((string) ($kontrol['drKontrol'] ?? '')) ?: $requesterId;

            $uraian = trim('Kontrol kembali ' . $tglKontrol
                . (filled($kontrol['poliKontrolDesc'] ?? null) ? ' di ' . $kontrol['poliKontrolDesc'] : '')
                . (filled($kontrol['drKontrolDesc'] ?? null) ? ' — ' . $kontrol['drKontrolDesc'] : ''));
            $instruksi = trim(implode('. ', array_filter([
                trim((string) ($kontrol['catatan'] ?? '')),
                filled($kontrol['noSKDPBPJS'] ?? null) ? 'No. SKDP ' . $kontrol['noSKDPBPJS'] : null,
            ])));
            $waktuIso = $this->parseDate($dataRI['entryDate'] ?? '')->toIso8601String();

            $respons = $this->postServiceRequest([
                'identifier' => ['system' => 'http://sys-ids.kemkes.go.id/servicerequest/' . env('SATUSEHAT_ORGANIZATION_ID'), 'value' => 'RI-' . $riHdrNo . '-KONTROL'],
                'status' => 'active', 'intent' => 'original-order', 'priority' => 'routine',
                'category' => ['system' => 'http://snomed.info/sct', 'code' => '3457005', 'display' => 'Patient referral'],
                'code' => ['system' => 'http://snomed.info/sct', 'code' => '185389009', 'display' => 'Follow-up visit'],
                'codeText' => $uraian,
                'subject' => "Patient/{$patientId}", 'encounter' => "Encounter/{$satuSehat['encounterId']}",
                'occurrenceDateTime' => $tglKontrolIso, 'authoredOn' => $waktuIso,
                'requester' => "Practitioner/{$requesterId}", 'requesterDisplay' => (string) ($dataRI['drDesc'] ?? ''),
                'performer' => "Practitioner/{$dokterKontrolId}", 'performerDisplay' => (string) ($kontrol['drKontrolDesc'] ?? ''),
                'patientInstruction' => $instruksi !== '' ? $instruksi : $uraian,
            ]);
            if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'Rencana tindak lanjut gagal: respons tanpa id.'); return; }

            $satuSehat['rencanaTindakLanjutId'] = $respons['id'];
            $this->saveResult($riHdrNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Rencana tindak lanjut berhasil dikirim.');
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Rencana tindak lanjut gagal: ' . $e->getMessage());
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
                <span class="text-sm font-bold">25</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Rencana Tindak Lanjut</div>
                <div class="text-xs text-muted dark:text-gray-400">Kontrol kembali (ServiceRequest), dari SKDP — opsional.</div>
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
        kosong="Belum ada tanggal kontrol (SKDP) di EMR (opsional)." />
</div>
