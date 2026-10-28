<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-rencana-tindak-lanjut.blade.php
// Step 23: Kirim Rencana Tindak Lanjut (ServiceRequest kontrol) — Playbook RJ bab 23, disamakan dgn
// alur apikesehatan.com: category SNOMED 3457005 Patient referral, code 185389009 Follow-up visit
// (diverifikasi tx.fhir.org), occurrenceDateTime = tanggal kontrol. Sumber: node kontrol (SKDP) —
// tglKontrol, drKontrol, poliKontrolDesc, catatan, noSKDPBPJS. Hasil: satusehat.rencanaTindakLanjutId.
// OPSIONAL — tanpa tanggal kontrol = dilewati.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ServiceRequestTrait;

new class extends Component {
    use EmrRJTrait, ServiceRequestTrait;

    public ?string $rjNo = null;
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
        if (empty($this->rjNo)) {
            return [];
        }

        $kontrol = $this->findDataRJ($this->rjNo)['kontrol'] ?? [];
        if (blank($kontrol['tglKontrol'] ?? null)) {
            return [];
        }

        return [
            ['label' => 'Kontrol', 'nilai' => (string) $kontrol['tglKontrol'], 'ket' => trim(($kontrol['poliKontrolDesc'] ?? '') . ' — ' . ($kontrol['drKontrolDesc'] ?? ''), ' —')],
            ['label' => 'Kode', 'nilai' => 'SNOMED 185389009 Follow-up visit', 'ket' => 'category 3457005 Patient referral'],
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
        $this->count = !empty($satuSehat['rencanaTindakLanjutId']) ? 1 : 0;
        $this->jumlahTarget = 1;
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
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya langkah ini WAJIB memberi
     * kabar, supaya orkestrator bisa melanjutkan ke langkah berikutnya.
     */
    #[On('ss-rencana-tindak-lanjut-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'rencana-tindak-lanjut');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }

            if (!empty($satuSehat['rencanaTindakLanjutId'])) { $this->dispatch('toast', type: 'info', message: 'Rencana tindak lanjut sudah pernah dikirim.'); return; }

            $kontrol = $dataRJ['kontrol'] ?? [];
            $tglKontrol = trim((string) ($kontrol['tglKontrol'] ?? ''));
            if ($tglKontrol === '') { $this->dispatch('toast', type: 'info', message: 'Belum ada tanggal kontrol (SKDP) — rencana tindak lanjut dilewati (opsional).'); return; }
            try {
                $tglKontrolIso = Carbon::createFromFormat('d/m/Y', $tglKontrol)->format('Y-m-d');
            } catch (\Throwable) {
                $this->dispatch('toast', type: 'error', message: 'Format tanggal kontrol tidak dikenali: ' . $tglKontrol);
                return;
            }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }
            $requesterId = $this->getDoctorIHS($dataRJ['drId'] ?? '');
            if (empty($requesterId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong — lengkapi di master dokter.'); return; }
            $dokterKontrolId = $this->getDoctorIHS((string) ($kontrol['drKontrol'] ?? '')) ?: $requesterId;

            $uraian = trim('Kontrol kembali ' . $tglKontrol
                . (filled($kontrol['poliKontrolDesc'] ?? null) ? ' di ' . $kontrol['poliKontrolDesc'] : '')
                . (filled($kontrol['drKontrolDesc'] ?? null) ? ' — ' . $kontrol['drKontrolDesc'] : ''));
            $instruksi = trim(implode('. ', array_filter([
                trim((string) ($kontrol['catatan'] ?? '')),
                filled($kontrol['noSKDPBPJS'] ?? null) ? 'No. SKDP ' . $kontrol['noSKDPBPJS'] : null,
            ])));
            $waktuIso = $this->parseDate($dataRJ['rjDate'] ?? '')->toIso8601String();

            $respons = $this->postServiceRequest([
                'identifier' => ['system' => 'http://sys-ids.kemkes.go.id/servicerequest/' . env('SATUSEHAT_ORGANIZATION_ID'), 'value' => 'RJ-' . $rjNo . '-KONTROL'],
                'status' => 'active', 'intent' => 'original-order', 'priority' => 'routine',
                'category' => ['system' => 'http://snomed.info/sct', 'code' => '3457005', 'display' => 'Patient referral'],
                'code' => ['system' => 'http://snomed.info/sct', 'code' => '185389009', 'display' => 'Follow-up visit'],
                'codeText' => $uraian,
                'subject' => "Patient/{$patientId}", 'encounter' => "Encounter/{$satuSehat['encounterId']}",
                'occurrenceDateTime' => $tglKontrolIso, 'authoredOn' => $waktuIso,
                'requester' => "Practitioner/{$requesterId}", 'requesterDisplay' => (string) ($dataRJ['drDesc'] ?? ''),
                'performer' => "Practitioner/{$dokterKontrolId}", 'performerDisplay' => (string) ($kontrol['drKontrolDesc'] ?? ''),
                'patientInstruction' => $instruksi !== '' ? $instruksi : $uraian,
            ]);
            if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'Rencana tindak lanjut gagal: respons tanpa id.'); return; }

            $satuSehat['rencanaTindakLanjutId'] = $respons['id'];
            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Rencana tindak lanjut berhasil dikirim.');
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
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
                <span class="text-sm font-bold">23</span>
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
