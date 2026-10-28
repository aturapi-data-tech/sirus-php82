<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-riwayat-keluarga.blade.php
// Step 6d: Kirim Riwayat Penyakit Keluarga (FamilyMemberHistory, SNOMED CT) — Playbook RJ bab 3e.
// Pola kirim-keluhan-penyerta. Sumber: anamnesa.riwayatPenyakitKeluarga.snomedList[] (baris dari
// App\Support\RiwayatPenyakitKeluarga, diisi di layar Coding SATUSEHAT). Hasil:
// satusehat.riwayatPenyakitKeluargaIds = ['HUBUNGAN:snomed' => id]; yang sudah terkirim tak dikirim
// ulang. OPSIONAL — daftar kosong = dilewati.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\FamilyMemberHistoryTrait;
use App\Support\RiwayatPenyakitKeluarga;

new class extends Component {
    use EmrRJTrait, FamilyMemberHistoryTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;
    public int $jumlahKode = 0;

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

        $data = $this->findDataRJ($this->rjNo);
        $terkirim = $data['satusehat']['riwayatPenyakitKeluargaIds'] ?? [];

        return collect($data['anamnesa']['riwayatPenyakitKeluarga']['snomedList'] ?? [])
            ->map(fn($baris) => [
                'label' => RiwayatPenyakitKeluarga::labelHubungan($baris['hubungan'] ?? '') . ' — ' . RiwayatPenyakitKeluarga::namaBaris($baris),
                'nilai' => (string) ($baris['snomedCode'] ?? ''),
                'ket' => isset($terkirim[RiwayatPenyakitKeluarga::kunci($baris)]) ? 'sudah terkirim' : 'akan dikirim',
            ])
            ->values()
            ->all();
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
        $this->count = count($satuSehat['riwayatPenyakitKeluargaIds'] ?? []);
        $this->jumlahKode = count($data['anamnesa']['riwayatPenyakitKeluarga']['snomedList'] ?? []);
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
    #[On('ss-riwayat-keluarga-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'riwayat-keluarga');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            $snomedList = $dataRJ['anamnesa']['riwayatPenyakitKeluarga']['snomedList'] ?? [];
            if (empty($snomedList)) { $this->dispatch('toast', type: 'info', message: 'Belum ada kode riwayat penyakit keluarga — isi lewat menu Coding SATUSEHAT (opsional), dilewati.'); return; }

            $terkirim = $satuSehat['riwayatPenyakitKeluargaIds'] ?? [];
            $belumTerkirim = collect($snomedList)->filter(fn($baris) => !isset($terkirim[RiwayatPenyakitKeluarga::kunci($baris)]));
            if ($belumTerkirim->isEmpty()) { $this->dispatch('toast', type: 'info', message: 'Semua riwayat penyakit keluarga sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $tanggalIso = $this->parseDate($dataRJ['rjDate'] ?? '')->toIso8601String();

            $gagalList = [];
            foreach ($belumTerkirim as $baris) {
                $namaBaris = RiwayatPenyakitKeluarga::labelHubungan($baris['hubungan'] ?? '') . ' — ' . RiwayatPenyakitKeluarga::namaBaris($baris);
                try {
                    $respons = $this->createFamilyMemberHistory([
                        'patientId'     => $patientId,
                        'hubungan'      => $baris['hubungan'] ?? '',
                        'snomedCode'    => $baris['snomedCode'] ?? '',
                        'snomedDisplay' => $baris['snomedDisplayEn'] ?? '',
                        'teks'          => $baris['snomedDisplayId'] ?? '',
                        'keterangan'    => $baris['keterangan'] ?? '',
                        'tanggalIso'    => $tanggalIso,
                    ]);
                    if (empty($respons['id'])) {
                        $gagalList[] = $namaBaris . ' (respons tanpa id)';
                        continue;
                    }
                    $terkirim[RiwayatPenyakitKeluarga::kunci($baris)] = $respons['id'];
                } catch (\Throwable $e) {
                    $gagalList[] = $namaBaris . ' (' . $e->getMessage() . ')';
                }
            }

            // Yang berhasil tetap disimpan walau sebagian gagal — kirim ulang tak menggandakan.
            $satuSehat['riwayatPenyakitKeluargaIds'] = $terkirim;
            $this->saveResult($rjNo, $satuSehat);

            empty($gagalList)
                ? $this->dispatch('toast', type: 'success', message: 'Riwayat penyakit keluarga berhasil dikirim (' . count($terkirim) . ').')
                : $this->dispatch('toast', type: 'error', message: 'Sebagian riwayat penyakit keluarga gagal: ' . implode('; ', $gagalList));
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Riwayat penyakit keluarga gagal: ' . $e->getMessage());
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
                <span class="text-sm font-bold">6d</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Riwayat Penyakit Keluarga</div>
                <div class="text-xs text-muted dark:text-gray-400">FamilyMemberHistory (SNOMED CT), opsional.</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }}/{{ $jumlahKode }} terkirim
                    </div>
                @endif
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 && $count >= $jumlahKode ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent,kirim">
                <span class="inline-flex items-center gap-1.5">
                    <x-satu-sehat.ikon-tombol :selesai="$count > 0 && $count >= $jumlahKode" jenis="kirim" />
                    {{ $count > 0 && $count >= $jumlahKode ? 'Terkirim' : 'Kirim' }}
                </span>
            </span>
            <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
        </x-primary-button>
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Belum ada kode riwayat penyakit keluarga — isi lewat menu Coding SATUSEHAT (opsional)." />
</div>
