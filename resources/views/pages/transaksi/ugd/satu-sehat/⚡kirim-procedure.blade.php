<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-procedure.blade.php
// Step 4: Kirim Tindakan (Procedure, ICD-9-CM).
// Sumber JSON: dataUGD['procedure'][] { procedureId = ICD-9, procedureDesc } —
//   ditulis rm-diagnosa-ugd-actions (identik RJ/RI); kalau kosong → guard (bukan error fatal).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\ProcedureTrait;

new class extends Component {
    use EmrUGDTrait, ProcedureTrait;

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
     * Isi yang AKAN dikirim, dari sumber yang SAMA dengan kirim() — node 'procedure'
     * berisi {procedureId = ICD-9-CM, procedureDesc}.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $baris = [];
        foreach (($this->findDataUGD($this->rjNo)['procedure'] ?? []) as $urutan => $tindakan) {
            $kode = trim((string) ($tindakan['procedureId'] ?? ''));
            if ($kode === '') {
                continue;
            }
            $baris[] = [
                'label' => 'Tindakan ' . ($urutan + 1),
                'nilai' => $kode,
                'ket' => (string) ($tindakan['procedureDesc'] ?? ''),
            ];
        }

        return $baris;
    }


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
        $this->count = count($satuSehat['procedureIds'] ?? []);
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
    #[On('ss-procedure-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('ugd-satu-sehat.langkah-selesai', langkah: 'procedure');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['procedureIds'])) { $this->dispatch('toast', type: 'info', message: 'Tindakan sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
            $ugdDate = $this->parseDate($dataUGD['rjDate'] ?? '');

            // Sumber JSON: dataUGD['procedure'][] { procedureId = ICD-9, procedureDesc } —
            // ditulis rm-diagnosa-ugd-actions; sama dengan sender RI. (Key lama
            // tindakanList/kodeIcd9 tidak pernah ditulis siapa pun → selalu "tidak ada data".)
            $tindakanList = $dataUGD['procedure'] ?? [];
            if (empty($tindakanList) || !is_array($tindakanList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data tindakan ber-ICD9 di UGD.'); return; }

            $satuSehat['procedureIds'] = [];
            foreach ($tindakanList as $tindakan) {
                if (!is_array($tindakan)) continue;
                $kode = $tindakan['procedureId'] ?? '';
                $display = $tindakan['procedureDesc'] ?? '';
                if (empty($kode)) continue;

                $respons = $this->createProcedure([
                    'patientId' => $patientId, 'encounterId' => $satuSehat['encounterId'], 'performerId' => $practitionerId,
                    'code' => $kode, 'display' => $display, 'codeSystem' => 'http://hl7.org/fhir/sid/icd-9-cm',
                    'performedDateTime' => $ugdDate->toIso8601String(),
                ]);
                if (!empty($respons['id'])) $satuSehat['procedureIds'][] = $respons['id'];
            }

            if (empty($satuSehat['procedureIds'])) { $this->dispatch('toast', type: 'error', message: 'Tindakan tanpa kode ICD-9 — tidak ada yang dikirim.'); return; }

            $this->saveResult($rjNo, $satuSehat);
            $count = count($satuSehat['procedureIds']);
            $this->dispatch('toast', type: 'success', message: "Tindakan berhasil dikirim ({$count} item).");
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal. Tanpa ini id-nya hangus padahal resource-nya SUDAH ada di sana,
            // lalu percobaan berikutnya menumpuk resource yatim — persis penyebab
            // diagnosa macet permanen dulu (lihat sender Condition). Dibungkus try
            // sendiri supaya kegagalan menyimpan tidak menutupi error aslinya.
            try { if (isset($satuSehat)) { $this->saveResult($rjNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Tindakan gagal: ' . $e->getMessage());
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

<div class="p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">4</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Procedure</div>
                <div class="text-xs text-muted dark:text-gray-400">Tindakan medis (ICD-9-CM).</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }} terkirim
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
        kosong="Belum ada tindakan ber-ICD-9-CM di EMR — Kirim akan ditolak." />
</div>
