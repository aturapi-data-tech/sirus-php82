<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-riwayat-pengobatan.blade.php
// Step 6e: Kirim Riwayat Pengobatan (MedicationStatement, KFA) — Playbook RJ bab 3f.
// Pola kirim-riwayat-keluarga. Sumber: anamnesa.riwayatPengobatan.obatList[] (baris dari
// App\Support\RiwayatPengobatan, KFA dari master obat, diisi di layar Coding SATUSEHAT).
// Hasil: satusehat.riwayatPengobatanIds = [productId => id]; yang sudah terkirim tak dikirim
// ulang. OPSIONAL — daftar kosong = dilewati.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\MedicationStatementTrait;
use App\Support\RiwayatPengobatan;

new class extends Component {
    use EmrRJTrait, MedicationStatementTrait;

    public ?string $rjNo = null;

    /** Mode pratinjau saja (layar Coding SATUSEHAT): tombol Kirim disembunyikan, kirim ditolak. */
    public bool $hanyaPratinjau = false;
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
        $terkirim = $data['satusehat']['riwayatPengobatanIds'] ?? [];

        return collect($data['anamnesa']['riwayatPengobatan']['obatList'] ?? [])
            ->map(fn($baris) => [
                'label' => (string) ($baris['namaObat'] ?? '-'),
                'nilai' => 'KFA ' . ($baris['kfaCode'] ?? ''),
                'ket' => RiwayatPengobatan::labelStatus($baris['status'] ?? '') . (isset($terkirim[$baris['productId'] ?? '']) ? ' — sudah terkirim' : ' — akan dikirim'),
            ])
            ->values()
            ->all();
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
        $this->count = count($satuSehat['riwayatPengobatanIds'] ?? []);
        $this->jumlahKode = count($data['anamnesa']['riwayatPengobatan']['obatList'] ?? []);
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
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya langkah ini WAJIB memberi
     * kabar, supaya orkestrator bisa melanjutkan ke langkah berikutnya.
     */
    #[On('ss-riwayat-pengobatan-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        if ($this->hanyaPratinjau) {
            return;
        }
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'riwayat-pengobatan');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            $obatList = $dataRJ['anamnesa']['riwayatPengobatan']['obatList'] ?? [];
            if (empty($obatList)) { $this->dispatch('toast', type: 'info', message: 'Belum ada obat riwayat pengobatan — isi lewat menu Coding SATUSEHAT (opsional), dilewati.'); return; }

            $terkirim = $satuSehat['riwayatPengobatanIds'] ?? [];
            $belumTerkirim = collect($obatList)->filter(fn($baris) => !isset($terkirim[$baris['productId'] ?? '']));
            if ($belumTerkirim->isEmpty()) { $this->dispatch('toast', type: 'info', message: 'Semua riwayat pengobatan sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
            $waktuIso = $this->parseDate($dataRJ['rjDate'] ?? '')->toIso8601String();

            $gagalList = [];
            foreach ($belumTerkirim as $baris) {
                $namaObat = (string) ($baris['namaObat'] ?? '-');
                try {
                    $respons = $this->createMedicationStatement([
                        'patientId'      => $patientId,
                        'encounterId'    => $satuSehat['encounterId'],
                        'status'         => $baris['status'] ?? 'active',
                        'kfaCode'        => $baris['kfaCode'] ?? '',
                        'kfaDisplay'     => $baris['kfaDisplay'] ?? '',
                        'namaObat'       => $namaObat,
                        'aturanPakai'    => $baris['aturanPakai'] ?? '',
                        'waktuIso'       => $waktuIso,
                        'practitionerId' => $practitionerId,
                    ]);
                    if (empty($respons['id'])) {
                        $gagalList[] = $namaObat . ' (respons tanpa id)';
                        continue;
                    }
                    $terkirim[$baris['productId']] = $respons['id'];
                } catch (\Throwable $e) {
                    $gagalList[] = $namaObat . ' (' . $e->getMessage() . ')';
                }
            }

            // Yang berhasil tetap disimpan walau sebagian gagal — kirim ulang tak menggandakan.
            $satuSehat['riwayatPengobatanIds'] = $terkirim;
            $this->saveResult($rjNo, $satuSehat);

            empty($gagalList)
                ? $this->dispatch('toast', type: 'success', message: 'Riwayat pengobatan berhasil dikirim (' . count($terkirim) . ' obat).')
                : $this->dispatch('toast', type: 'error', message: 'Sebagian riwayat pengobatan gagal: ' . implode('; ', $gagalList));
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Riwayat pengobatan gagal: ' . $e->getMessage());
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

<div class="{{ $hanyaPratinjau
    ? 'p-3 border border-brand-green/30 rounded-xl bg-brand-green/5 dark:bg-brand-green/10 dark:border-brand-green/50'
    : 'p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700' }}">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            @unless ($hanyaPratinjau)
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">6e</span>
            </div>
            @endunless
            <div>
                <div class="{{ $hanyaPratinjau ? 'text-xs font-semibold tracking-wide uppercase text-brand-green dark:text-brand-lime' : 'font-semibold text-ink dark:text-gray-100' }}">Riwayat Pengobatan</div>
                <div class="text-xs text-muted dark:text-gray-400">MedicationStatement (KFA), opsional.</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }}/{{ $jumlahKode }} terkirim
                    </div>
                @endif
            </div>
        </div>
        @unless ($hanyaPratinjau)
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
        @endunless
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka" :ringkas="$hanyaPratinjau"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Belum ada obat riwayat pengobatan — isi lewat menu Coding SATUSEHAT (opsional)." />
</div>
