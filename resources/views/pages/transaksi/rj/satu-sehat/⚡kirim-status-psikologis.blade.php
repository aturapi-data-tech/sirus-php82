<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-status-psikologis.blade.php
// Step 5: Kirim Pemeriksaan Fungsional — Status Psikologis (Observation survey) — Playbook RJ bab 5.
// Sumber: anamnesa.statusPsikologis (toggle EMR) → App\Support\Terminologi\StatusPsikologisMap: satu
// Observation per status dicentang, code LOINC 8693-4 Mental status, value SNOMED, catatan dari
// sebutstatusPsikologis. Hasil: satusehat.statusPsikologisIds = [key => id]. OPSIONAL.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Support\Terminologi\StatusPsikologisMap;

new class extends Component {
    use EmrRJTrait, ObservationTrait;

    public ?string $rjNo = null;

    /** Mode pratinjau saja (layar Coding SATUSEHAT): tombol Kirim disembunyikan, kirim ditolak. */
    public bool $hanyaPratinjau = false;
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

        $data = $this->findDataRJ($this->rjNo);
        $terkirim = $data['satusehat']['statusPsikologisIds'] ?? [];

        return collect(StatusPsikologisMap::aktif($data['anamnesa']['statusPsikologis'] ?? []))
            ->map(fn($peta, $kunci) => [
                'label' => $peta['label'],
                'nilai' => 'SNOMED ' . $peta['code'],
                'ket' => $peta['display'] . (isset($terkirim[$kunci]) ? ' — sudah terkirim' : ' — akan dikirim'),
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
        $this->count = count($satuSehat['statusPsikologisIds'] ?? []);
        $this->jumlahTarget = count(StatusPsikologisMap::aktif($data['anamnesa']['statusPsikologis'] ?? []));
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
    #[On('ss-status-psikologis-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        if ($this->hanyaPratinjau) {
            return;
        }
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'status-psikologis');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }

            $statusPsikologis = $dataRJ['anamnesa']['statusPsikologis'] ?? [];
            $terkirim = $satuSehat['statusPsikologisIds'] ?? [];
            $belumTerkirim = array_diff_key(StatusPsikologisMap::aktif($statusPsikologis), $terkirim);
            if (StatusPsikologisMap::aktif($statusPsikologis) === []) { $this->dispatch('toast', type: 'info', message: 'Status psikologis belum dicentang di anamnesa (opsional) — dilewati.'); return; }
            if ($belumTerkirim === []) { $this->dispatch('toast', type: 'info', message: 'Status psikologis sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $performerId = $this->getDoctorIHS($dataRJ['drId'] ?? '');
            $waktuIso = $this->parseDate($dataRJ['rjDate'] ?? '')->toIso8601String();
            $catatan = trim((string) ($statusPsikologis['sebutstatusPsikologis'] ?? ''));

            $gagalList = [];
            foreach ($belumTerkirim as $kunci => $peta) {
                try {
                    $respons = $this->createObservation([
                        'patientId'     => $patientId,
                        'encounterId'   => $satuSehat['encounterId'],
                        'performerId'   => $performerId,
                        'effectiveDate' => $waktuIso,
                        'category'      => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'survey', 'display' => 'Survey']]]],
                        'code'          => StatusPsikologisMap::CODE,
                        'valueCodeableConcept' => ['system' => 'http://snomed.info/sct', 'code' => $peta['code'], 'display' => $peta['display'],
                            'text' => $peta['label'] . ($catatan !== '' ? ' — ' . $catatan : '')],
                    ]);
                    if (empty($respons['id'])) {
                        $gagalList[] = $peta['label'] . ' (respons tanpa id)';
                        continue;
                    }
                    $terkirim[$kunci] = $respons['id'];
                } catch (\Throwable $e) {
                    $gagalList[] = $peta['label'] . ' (' . $e->getMessage() . ')';
                }
            }

            // Yang berhasil tetap disimpan walau sebagian gagal — kirim ulang tak menggandakan.
            $satuSehat['statusPsikologisIds'] = $terkirim;
            $this->saveResult($rjNo, $satuSehat);

            empty($gagalList)
                ? $this->dispatch('toast', type: 'success', message: 'Status psikologis berhasil dikirim (' . count($terkirim) . ').')
                : $this->dispatch('toast', type: 'error', message: 'Sebagian status psikologis gagal: ' . implode('; ', $gagalList));
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Status Psikologis gagal: ' . $e->getMessage());
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
                <span class="text-sm font-bold">5</span>
            </div>
            @endunless
            <div>
                <div class="{{ $hanyaPratinjau ? 'text-xs font-semibold tracking-wide uppercase text-brand-green dark:text-brand-lime' : 'font-semibold text-ink dark:text-gray-100' }}">Status Psikologis</div>
                <div class="text-xs text-muted dark:text-gray-400">Pemeriksaan fungsional — status psikologis (SNOMED CT), opsional.</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }}/{{ $jumlahTarget }} terkirim
                    </div>
                @endif
            </div>
        </div>
        @unless ($hanyaPratinjau)
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
        @endunless
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka" :ringkas="$hanyaPratinjau"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Status psikologis belum dicentang di anamnesa EMR (opsional)." />
</div>
