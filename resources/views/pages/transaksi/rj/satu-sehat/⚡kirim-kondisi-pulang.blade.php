<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-kondisi-pulang.blade.php
// Step 24: Kirim Kondisi Saat Meninggalkan Fasyankes (Condition / problem-list-item, SNOMED CT) —
// Playbook RJ bab 25, WAJIB. Pola kirim-chief-complaint. Sumber: perencanaan.kondisiPulang.kondisiPulang
// (pilihan dokter di EMR) → kode tetap dari App\Support\Terminologi\KondisiPulang (tanpa coding).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ConditionTrait;
use App\Support\Terminologi\KondisiPulang;

new class extends Component {
    use EmrRJTrait, ConditionTrait;

    public ?string $rjNo = null;

    /** Mode pratinjau saja (layar Coding SATUSEHAT): tombol Kirim disembunyikan, kirim ditolak. */
    public bool $hanyaPratinjau = false;
    public bool $hasEncounter = false;
    public int $count = 0;

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

        $label = trim((string) ($this->findDataRJ($this->rjNo)['perencanaan']['kondisiPulang']['kondisiPulang'] ?? ''));
        $kode = KondisiPulang::kode($label);

        return [
            ['label' => 'Kondisi saat pulang', 'nilai' => $label ?: '(belum diisi — Kirim akan ditolak)',
             'ket' => $kode !== null ? 'SNOMED ' . $kode['code'] . ' ' . $kode['display'] : ''],
        ];
    }

    public function mount(?string $rjNo = null, bool $hanyaPratinjau = false): void
    {
        $this->hanyaPratinjau = $hanyaPratinjau;
        $this->pratinjauTerbuka = $hanyaPratinjau;
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
        $this->count = !empty($satuSehat['kondisiPulangId']) ? 1 : 0;
    }

    public function kirimForCurrent(): void
    {
        if ($this->hanyaPratinjau) {
            return;
        }
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
    #[On('ss-kondisi-pulang-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        if ($this->hanyaPratinjau) {
            return;
        }
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'kondisi-pulang');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['kondisiPulangId'])) { $this->dispatch('toast', type: 'info', message: 'Kondisi pulang sudah pernah dikirim.'); return; }

            $label = trim((string) ($dataRJ['perencanaan']['kondisiPulang']['kondisiPulang'] ?? ''));
            $kode = KondisiPulang::kode($label);
            if ($kode === null) { $this->dispatch('toast', type: 'error', message: 'Kondisi Saat Pulang belum diisi di Perencanaan EMR (wajib utk Satu Sehat).'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            // Waktu = jam layanan berakhir (task 7 → task 5), sama dengan finish Encounter.
            $task = $dataRJ['taskIdPelayanan'] ?? [];
            $waktuSelesai = trim((string) ($task['taskId7'] ?? '')) ?: trim((string) ($task['taskId5'] ?? '')) ?: (string) ($dataRJ['rjDate'] ?? '');

            $respons = $this->createKondisiPulang([
                'patientId'      => $patientId,
                'encounterId'    => $satuSehat['encounterId'],
                'snomedCode'     => $kode['code'],
                'snomedDisplay'  => $kode['display'],
                'teks'           => $label,
                'waktuIso'       => $this->parseDate($waktuSelesai)->toIso8601String(),
                'practitionerId' => (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? ''),
            ]);

            if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'Kondisi pulang gagal: respons tanpa id.'); return; }

            $satuSehat['kondisiPulangId'] = $respons['id'];
            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Kondisi pulang berhasil dikirim.');
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Kondisi pulang gagal: ' . $e->getMessage());
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
        if (empty($teksTanggal)) throw new \RuntimeException('tanggal sumber kosong — lengkapi datanya di EMR dulu (tidak diisi jam sekarang).');
        try { return Carbon::createFromFormat('d/m/Y H:i:s', $teksTanggal); } catch (\Throwable) {
            try { return Carbon::parse($teksTanggal); } catch (\Throwable) { throw new \RuntimeException("tanggal '{$teksTanggal}' tidak terbaca — betulkan datanya di EMR dulu (tidak diisi jam sekarang)."); }
        }
    }
};
?>

<div class="{{ $hanyaPratinjau
    ? 'p-3 border border-brand-green/30 rounded-xl bg-brand-green/5 dark:bg-brand-green/10 dark:border-brand-green/50'
    : 'p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700' }}">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            @unless ($hanyaPratinjau)
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">24</span>
            </div>
            @endunless
            <div>
                <div class="{{ $hanyaPratinjau ? 'text-xs font-semibold tracking-wide uppercase text-brand-green dark:text-brand-lime' : 'font-semibold text-ink dark:text-gray-100' }}">Kondisi Pulang</div>
                <div class="text-xs text-muted dark:text-gray-400">Kondisi saat meninggalkan fasyankes (SNOMED CT), wajib.</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        terkirim
                    </div>
                @endif
            </div>
        </div>
        @unless ($hanyaPratinjau)
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
        @endunless
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka" :ringkas="$hanyaPratinjau"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Kondisi Saat Pulang belum diisi di Perencanaan EMR — Kirim akan ditolak." />
</div>
