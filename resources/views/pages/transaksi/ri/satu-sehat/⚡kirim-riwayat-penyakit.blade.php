<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-riwayat-penyakit.blade.php
// Kirim Riwayat Penyakit Pribadi (Condition / previous-condition, SNOMED CT) — Playbook RI bab 3d.
// Pola kirim-keluhan-penyerta. Sumber (diisi di layar Coding SATUSEHAT):
//   kodingSatuSehat.riwayatPenyakitSekarang.snomedList[] → active   + onsetPeriod.start
//   kodingSatuSehat.riwayatPenyakitDahulu.snomedList[]       → inactive + onsetAge
// Hasil: satusehat.riwayatPenyakitSekarangIds / riwayatPenyakitDahuluIds = [snomedCode => conditionId];
// kode yang sudah terkirim tak dikirim ulang. OPSIONAL — daftar kosong = dilewati.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Support\AnamnesaRi;
use App\Http\Traits\SATUSEHAT\ConditionTrait;
use App\Support\RiwayatPenyakitPribadi;

new class extends Component {
    use EmrRITrait, ConditionTrait;

    /** Jenis → [key daftar di node kodingSatuSehat, key hasil di node satusehat]. */
    private const SUMBER = [
        RiwayatPenyakitPribadi::JENIS_SEKARANG => ['riwayatPenyakitSekarang', 'riwayatPenyakitSekarangIds'],
        RiwayatPenyakitPribadi::JENIS_DAHULU => ['riwayatPenyakitDahulu', 'riwayatPenyakitDahuluIds'],
    ];

    public ?string $riHdrNo = null;
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
        if (empty($this->riHdrNo)) {
            return [];
        }

        $data = $this->findDataRI($this->riHdrNo);
        $baris = [];
        foreach (self::SUMBER as $jenis => [$node, $kunciHasil]) {
            $terkirim = $data['satusehat'][$kunciHasil] ?? [];
            foreach (AnamnesaRi::koding($data, $node)['snomedList'] ?? [] as $entri) {
                $baris[] = [
                    'label' => RiwayatPenyakitPribadi::namaBaris($entri),
                    'nilai' => (string) ($entri['snomedCode'] ?? ''),
                    'ket' => ($jenis === RiwayatPenyakitPribadi::JENIS_SEKARANG ? 'sekarang, ' : 'dahulu, ') . RiwayatPenyakitPribadi::keteranganBaris($entri)
                        . (isset($terkirim[$entri['snomedCode'] ?? '']) ? ' — sudah terkirim' : ' — akan dikirim'),
                ];
            }
        }

        return $baris;
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
        $this->count = 0;
        $this->jumlahKode = 0;
        foreach (self::SUMBER as [$node, $kunciHasil]) {
            $this->count += count($satuSehat[$kunciHasil] ?? []);
            $this->jumlahKode += count(AnamnesaRi::koding($data, $node)['snomedList'] ?? []);
        }
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
    #[On('ss-riwayat-penyakit-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        $this->kirimInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'riwayat-penyakit');
    }

    public function kirimInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }

            $antrean = [];
            foreach (self::SUMBER as $jenis => [$node, $kunciHasil]) {
                $terkirim = $satuSehat[$kunciHasil] ?? [];
                foreach (AnamnesaRi::koding($dataRI, $node)['snomedList'] ?? [] as $entri) {
                    if (!isset($terkirim[$entri['snomedCode'] ?? ''])) {
                        $antrean[] = [$jenis, $kunciHasil, $entri];
                    }
                }
            }
            if (empty($antrean)) {
                $adaKode = collect(self::SUMBER)->contains(fn($sumber) => !empty(AnamnesaRi::koding($dataRI, $sumber[0])['snomedList']));
                $this->dispatch('toast', type: 'info', message: $adaKode
                    ? 'Semua kode riwayat penyakit sudah pernah dikirim.'
                    : 'Belum ada kode riwayat penyakit — isi lewat menu Coding SATUSEHAT (opsional), dilewati.');
                return;
            }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');
            $waktuIso = $this->parseDate($dataRI['entryDate'] ?? '')->toIso8601String();

            $gagalList = [];
            foreach ($antrean as [$jenis, $kunciHasil, $entri]) {
                $namaPenyakit = RiwayatPenyakitPribadi::namaBaris($entri);
                try {
                    $respons = $this->createRiwayatPenyakitPribadi([
                        'patientId'      => $patientId,
                        'encounterId'    => $satuSehat['encounterId'],
                        'jenis'          => $jenis,
                        'snomedCode'     => $entri['snomedCode'],
                        'snomedDisplay'  => $entri['snomedDisplayEn'] ?? '',
                        'teks'           => $entri['snomedDisplayId'] ?? '',
                        'tglMulaiIso'    => $jenis === RiwayatPenyakitPribadi::JENIS_SEKARANG
                            ? Carbon::createFromFormat('d/m/Y', (string) ($entri['tglMulai'] ?? ''))->format('Y-m-d') : null,
                        'usiaTahun'      => $entri['usiaTahun'] ?? null,
                        'waktuIso'       => $waktuIso,
                        'practitionerId' => $practitionerId,
                    ]);
                    if (empty($respons['id'])) {
                        $gagalList[] = $namaPenyakit . ' (respons tanpa id)';
                        continue;
                    }
                    $satuSehat[$kunciHasil][$entri['snomedCode']] = $respons['id'];
                } catch (\Throwable $e) {
                    $gagalList[] = $namaPenyakit . ' (' . $e->getMessage() . ')';
                }
            }

            // Yang berhasil tetap disimpan walau sebagian gagal — kirim ulang tak menggandakan.
            $this->saveResult($riHdrNo, $satuSehat);

            empty($gagalList)
                ? $this->dispatch('toast', type: 'success', message: 'Riwayat penyakit berhasil dikirim (' . count($antrean) . ' kode).')
                : $this->dispatch('toast', type: 'error', message: 'Sebagian riwayat penyakit gagal: ' . implode('; ', $gagalList));
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Riwayat penyakit gagal: ' . $e->getMessage());
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
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
                <span class="text-sm font-bold">9</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Riwayat Penyakit</div>
                <div class="text-xs text-muted dark:text-gray-400">Riwayat penyakit pribadi sekarang &amp; dahulu (SNOMED CT), opsional.</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }}/{{ $jumlahKode }} kode terkirim
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
        kosong="Belum ada kode riwayat penyakit — isi lewat menu Coding SATUSEHAT (opsional)." />
</div>
