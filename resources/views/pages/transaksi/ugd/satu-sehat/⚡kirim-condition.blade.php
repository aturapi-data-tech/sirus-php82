<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-condition.blade.php
// Step 2: Kirim Diagnosa ICD-10 (UGD). Sumber JSON: dataDaftarUGD['diagnosis'][] { icdX/diagId, diagDesc }.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\ConditionTrait;

new class extends Component {
    use EmrUGDTrait, ConditionTrait;

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
     * Isi yang AKAN dikirim, dibaca dari sumber yang SAMA dengan kirim() —
     * dataUGD['diagnosis'] — supaya pratinjau tak bisa berbeda dari kenyataan.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $baris = [];
        foreach (($this->findDataUGD($this->rjNo)['diagnosis'] ?? []) as $urutan => $diagnosa) {
            $kode = $diagnosa['icdX'] ?? ($diagnosa['diagId'] ?? '');
            if (empty($kode)) {
                continue;
            }
            $baris[] = [
                'label' => 'Diagnosa ' . ($urutan + 1),
                'nilai' => $kode,
                'ket' => $diagnosa['diagDesc'] ?? '',
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
        $this->count = count($satuSehat['conditionIds'] ?? []);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-condition-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            // Kiriman ulang sengaja DIBIARKAN: yang separuh jalan harus bisa dilengkapi.
            // Aman karena diagnosa yang sudah ada di SATUSEHAT dipungut id-nya, bukan
            // dibuat ulang. (Lihat catatan panjang di sender RJ.)

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $ugdDate = $this->parseDate($dataUGD['rjDate'] ?? '');
            $diagnosaList = $dataUGD['diagnosis'] ?? [];
            if (empty($diagnosaList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data diagnosa UGD untuk dikirim.'); return; }

            // Id lama TIDAK dibuang: yang sudah terkirim tetap dihitung, sisanya dilengkapi.
            $terkumpul = array_values($satuSehat['conditionIds'] ?? []);
            $baru = 0;
            $dipungut = 0;
            $gagal = [];

            foreach ($diagnosaList as $diagnosa) {
                $kode = $diagnosa['icdX'] ?? ($diagnosa['diagId'] ?? '');
                $display = $diagnosa['diagDesc'] ?? '';
                if (empty($kode)) continue;

                // Per diagnosa: satu kegagalan tidak boleh menghanguskan yang lain,
                // karena dulu exception melompati saveResult() sehingga Condition yang
                // SUDAH terbentuk di SATUSEHAT tak pernah tercatat id-nya.
                try {
                    $respons = $this->createFinalDiagnosis([
                        'patientId' => $patientId, 'encounterId' => $satuSehat['encounterId'],
                        'icd10_code' => $kode, 'icd10_display' => $display,
                        'diagnosis_text' => trim("{$kode} - {$display}", ' -'),
                        'recordedDate' => $ugdDate->toIso8601String(),
                    ]);
                    if (!empty($respons['id'])) { $terkumpul[] = $respons['id']; $baru++; }
                } catch (\Throwable $e) {
                    if (!$this->isDuplicateError($e)) { $gagal[] = "{$kode}: " . $this->ringkasErrorSatuSehat($e); continue; }

                    $idLama = $this->findExistingConditionId($satuSehat['encounterId'], $kode, $terkumpul, $patientId);
                    if ($idLama !== '') { $terkumpul[] = $idLama; $dipungut++; }
                    else { $gagal[] = "{$kode}: sudah ada di SATUSEHAT tapi id-nya tidak ditemukan di encounter ini"; }
                }
            }

            if (empty($terkumpul)) {
                $pesanKosong = empty($gagal)
                    ? 'Semua diagnosa tanpa kode ICD-10 — tidak ada yang dikirim.'
                    : 'Diagnosa gagal — ' . implode('; ', $gagal);
                $this->dispatch('toast', type: 'error', message: $pesanKosong);
                return;
            }

            // SELALU disimpan, walau ada yang gagal — inti perbaikannya di sini.
            $satuSehat['conditionIds'] = array_values(array_unique($terkumpul));
            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);

            if (!empty($gagal)) {
                $this->dispatch('toast', type: 'error', message: 'Sebagian diagnosa gagal — ' . implode('; ', $gagal));
                return;
            }

            $pesan = "Diagnosa berhasil dikirim ({$baru} item).";
            if ($dipungut > 0) { $pesan = "Diagnosa lengkap: {$baru} baru, {$dipungut} sudah ada di SATUSEHAT dan id-nya dipulihkan."; }
            $this->dispatch('toast', type: 'success', message: $pesan);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Diagnosa gagal: ' . $e->getMessage());
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
                <span class="text-sm font-bold">2</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Condition</div>
                <div class="text-xs text-muted dark:text-gray-400">Diagnosa pasien (ICD-10).</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }} terkirim
                    </div>
                @endif
                {{-- wire:click, bukan x-show Alpine: kartu ini ikut di-morph tiap kali
                     daftar langkah disegarkan, dan state Alpine bisa putus di situ. --}}
                <button type="button" wire:click="togglePratinjau" wire:loading.attr="disabled"
                    wire:target="togglePratinjau"
                    class="mt-1 text-xs font-medium underline text-info-deep hover:no-underline dark:text-blue-300">
                    {{ $pratinjauTerbuka ? 'Sembunyikan data' : 'Lihat data yang akan dikirim' }}
                </button>
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent">{{ $count > 0 ? 'Terkirim' : 'Kirim' }}</span>
            <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
        </x-primary-button>
    </div>

    @if ($pratinjauTerbuka)
        <x-satu-sehat.pratinjau :baris="$this->pratinjau"
            kosong="Belum ada diagnosa ber-ICD-10 di EMR — Kirim akan ditolak sampai diagnosa diisi." />
    @endif
</div>
