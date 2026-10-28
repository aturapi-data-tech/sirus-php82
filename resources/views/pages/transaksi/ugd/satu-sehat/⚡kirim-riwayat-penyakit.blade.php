<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-riwayat-penyakit.blade.php
// Kirim Riwayat Penyakit (Condition / previous-condition, SNOMED CT) — UGD, Playbook IGD bab 4b (port RJ 3d).
// Pola kirim-keluhan-penyerta. Sumber (diisi di layar Coding SATUSEHAT):
//   anamnesa.riwayatPenyakitSekarangUmum.snomedList[] → active   + onsetPeriod.start
//   anamnesa.riwayatPenyakitDahulu.snomedList[]       → inactive + onsetAge
// Hasil: satusehat.riwayatPenyakitSekarangIds / riwayatPenyakitDahuluIds = [snomedCode => conditionId];
// kode yang sudah terkirim tak dikirim ulang. OPSIONAL — daftar kosong = dilewati.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\ConditionTrait;
use App\Support\RiwayatPenyakitPribadi;

new class extends Component {
    use EmrUGDTrait, ConditionTrait;

    /** Jenis → [node anamnesa, key hasil di node satusehat]. */
    private const SUMBER = [
        RiwayatPenyakitPribadi::JENIS_SEKARANG => ['riwayatPenyakitSekarangUmum', 'riwayatPenyakitSekarangIds'],
        RiwayatPenyakitPribadi::JENIS_DAHULU => ['riwayatPenyakitDahulu', 'riwayatPenyakitDahuluIds'],
    ];

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

        $data = $this->findDataUGD($this->rjNo);
        $baris = [];
        foreach (self::SUMBER as $jenis => [$node, $kunciHasil]) {
            $terkirim = $data['satusehat'][$kunciHasil] ?? [];
            foreach ($data['anamnesa'][$node]['snomedList'] ?? [] as $entri) {
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

    public function mount(?string $rjNo = null, bool $hanyaPratinjau = false): void
    {
        $this->hanyaPratinjau = $hanyaPratinjau;
        $this->pratinjauTerbuka = $hanyaPratinjau;
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
        $this->count = 0;
        $this->jumlahKode = 0;
        foreach (self::SUMBER as [$node, $kunciHasil]) {
            $this->count += count($satuSehat[$kunciHasil] ?? []);
            $this->jumlahKode += count($data['anamnesa'][$node]['snomedList'] ?? []);
        }
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
    #[On('ss-riwayat-penyakit-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        if ($this->hanyaPratinjau) {
            return;
        }
        $this->kirimInti($rjNo);
        $this->dispatch('ugd-satu-sehat.langkah-selesai', langkah: 'riwayat-penyakit');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }

            $antrean = [];
            foreach (self::SUMBER as $jenis => [$node, $kunciHasil]) {
                $terkirim = $satuSehat[$kunciHasil] ?? [];
                foreach ($dataUGD['anamnesa'][$node]['snomedList'] ?? [] as $entri) {
                    if (!isset($terkirim[$entri['snomedCode'] ?? ''])) {
                        $antrean[] = [$jenis, $kunciHasil, $entri];
                    }
                }
            }
            if (empty($antrean)) {
                $adaKode = collect(self::SUMBER)->contains(fn($sumber) => !empty($dataUGD['anamnesa'][$sumber[0]]['snomedList']));
                $this->dispatch('toast', type: 'info', message: $adaKode
                    ? 'Semua kode riwayat penyakit sudah pernah dikirim.'
                    : 'Belum ada kode riwayat penyakit — isi lewat menu Coding SATUSEHAT (opsional), dilewati.');
                return;
            }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
            $waktuIso = $this->parseDate($dataUGD['rjDate'] ?? '')->toIso8601String();

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
            $this->saveResult($rjNo, $satuSehat);

            empty($gagalList)
                ? $this->dispatch('toast', type: 'success', message: 'Riwayat penyakit berhasil dikirim (' . count($antrean) . ' kode).')
                : $this->dispatch('toast', type: 'error', message: 'Sebagian riwayat penyakit gagal: ' . implode('; ', $gagalList));
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Riwayat penyakit gagal: ' . $e->getMessage());
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

<div class="{{ $hanyaPratinjau
    ? 'p-3 border border-brand-green/30 rounded-xl bg-brand-green/5 dark:bg-brand-green/10 dark:border-brand-green/50'
    : 'p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700' }}">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            @unless ($hanyaPratinjau)
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">8</span>
            </div>
            @endunless
            <div>
                <div class="{{ $hanyaPratinjau ? 'text-xs font-semibold tracking-wide uppercase text-brand-green dark:text-brand-lime' : 'font-semibold text-ink dark:text-gray-100' }}">Riwayat Penyakit</div>
                <div class="text-xs text-muted dark:text-gray-400">Riwayat penyakit pribadi sekarang &amp; dahulu (SNOMED CT), opsional.</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }}/{{ $jumlahKode }} kode terkirim
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
        kosong="Belum ada kode riwayat penyakit — isi lewat menu Coding SATUSEHAT (opsional)." />
</div>
