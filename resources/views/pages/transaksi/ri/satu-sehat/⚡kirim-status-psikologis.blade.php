<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-status-psikologis.blade.php
// Kirim Pemeriksaan Psikologis (Observation survey) — RI, Playbook RI bab 5b (port RJ bab 5).
// Sumber: Pengkajian Awal perawat — status emosional (satu pilihan, AnamnesaRi::statusEmosional) →
// StatusPsikologisMap::aktifRi(): code LOINC 8693-4 Mental status, value SNOMED. "Lainnya" tak berkode →
// tidak dikirim. Hasil: satusehat.statusPsikologisIds = [key => id]. OPSIONAL.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Support\Terminologi\StatusPsikologisMap;
use App\Support\AnamnesaRi;

new class extends Component {
    use EmrRITrait, ObservationTrait;

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

        $data = $this->findDataRI($this->riHdrNo);
        $terkirim = $data['satusehat']['statusPsikologisIds'] ?? [];

        return collect(StatusPsikologisMap::aktifRi(AnamnesaRi::statusEmosional($data)[0]))
            ->map(fn($peta, $kunci) => [
                'label' => $peta['label'],
                'nilai' => 'SNOMED ' . $peta['code'],
                'ket' => $peta['display'] . (isset($terkirim[$kunci]) ? ' — sudah terkirim' : ' — akan dikirim'),
            ])
            ->values()
            ->all();
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
        $this->count = count($satuSehat['statusPsikologisIds'] ?? []);
        $this->jumlahTarget = count(StatusPsikologisMap::aktifRi(AnamnesaRi::statusEmosional($data)[0]));
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
    #[On('ss-status-psikologis-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        $this->kirimInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'status-psikologis');
    }

    public function kirimInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }

            [$kunciEmosional, $labelEmosional] = AnamnesaRi::statusEmosional($dataRI);
            $terkirim = $satuSehat['statusPsikologisIds'] ?? [];
            $belumTerkirim = array_diff_key(StatusPsikologisMap::aktifRi($kunciEmosional), $terkirim);
            if (StatusPsikologisMap::aktifRi($kunciEmosional) === []) { $this->dispatch('toast', type: 'info', message: ($kunciEmosional === '' ? 'Status emosional belum diisi di Pengkajian Awal' : 'Status emosional "' . $labelEmosional . '" tak punya kode SNOMED') . ' (opsional) — dilewati.'); return; }
            if ($belumTerkirim === []) { $this->dispatch('toast', type: 'info', message: 'Status psikologis sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $performerId = $this->getDoctorIHS($dataRI['drId'] ?? '');
            $waktuIso = $this->parseDate($dataRI['entryDate'] ?? '')->toIso8601String();
            $catatan = '';

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
            $this->saveResult($riHdrNo, $satuSehat);

            empty($gagalList)
                ? $this->dispatch('toast', type: 'success', message: 'Status psikologis berhasil dikirim (' . count($terkirim) . ').')
                : $this->dispatch('toast', type: 'error', message: 'Sebagian status psikologis gagal: ' . implode('; ', $gagalList));
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
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
                <span class="text-sm font-bold">20</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Status Psikologis</div>
                <div class="text-xs text-muted dark:text-gray-400">Pemeriksaan fungsional — status psikologis (SNOMED CT), opsional.</div>
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
        kosong="Status emosional belum diisi di Pengkajian Awal, atau tak berkode (opsional)." />
</div>
