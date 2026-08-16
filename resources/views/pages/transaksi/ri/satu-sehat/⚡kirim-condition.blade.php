<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-condition.blade.php
// Step 3 (RI): Kirim Diagnosa ICD-10.
//
// Sumber diagnosa RI = rstxn_ridtls (diag_id) JOIN rsmst_mstdiags BY diag_id.
// Lookup by diag_id (PK unik) → AMAN dari jebakan 288 icdx kembar (lihat skill diagnosa-flow).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ConditionTrait;

new class extends Component {
    use EmrRITrait, ConditionTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Isi yang AKAN dikirim, memanggil diagnosaRI() yang SAMA dengan kirim() —
     * lookup by diag_id, jadi ikut aman dari jebakan icdx kembar.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->riHdrNo)) {
            return [];
        }

        $baris = [];
        foreach ($this->diagnosaRI($this->riHdrNo) as $urutan => $diagnosa) {
            if (empty($diagnosa['code'])) {
                continue;
            }
            $baris[] = [
                'label' => 'Diagnosa ' . ($urutan + 1),
                'nilai' => $diagnosa['code'],
                'ket' => $diagnosa['display'] ?? '',
            ];
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
        $this->count = count($satuSehat['conditionIds'] ?? []);
    }

    /**
     * Ambil diagnosa RI dari rstxn_ridtls join rsmst_mstdiags by diag_id.
     * @return array<int, array{code:string, display:string}>
     */
    private function diagnosaRI(string $riHdrNo): array
    {
        return DB::table('rstxn_ridtls as d')
            ->join('rsmst_mstdiags as m', 'd.diag_id', '=', 'm.diag_id')
            ->where('d.rihdr_no', $riHdrNo)
            ->whereRaw('LENGTH(TRIM(m.icdx)) > 0')
            ->orderBy('d.ridtl_dtl')
            ->get([DB::raw('m.icdx as code'), DB::raw('m.diag_desc as display')])
            ->map(fn($entriRiwayat) => ['code' => (string) $entriRiwayat->code, 'display' => (string) ($entriRiwayat->display ?? '')])
            ->all();
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
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya — berhasil, ditolak
     * SATUSEHAT, atau berhenti di guard — langkah ini WAJIB memberi kabar, supaya
     * orkestrator bisa melanjutkan. Tanpa ini rantai menggantung diam-diam pada
     * langkah pertama yang gagal, dan petugas cuma melihat modal yang membeku.
     */
    #[On('ss-condition-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        $this->kirimInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'condition');
    }

    public function kirimInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            // Kiriman ulang sengaja DIBIARKAN: yang separuh jalan harus bisa dilengkapi.
            // Aman karena diagnosa yang sudah ada di SATUSEHAT dipungut id-nya, bukan
            // dibuat ulang. (Lihat catatan panjang di sender RJ.)

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $diagnosaList = $this->diagnosaRI($riHdrNo);
            if (empty($diagnosaList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data diagnosa untuk dikirim.'); return; }

            $recordedDate = $this->parseDate($dataRI['entryDate'] ?? '');

            // Id lama TIDAK dibuang: yang sudah terkirim tetap dihitung, sisanya dilengkapi.
            $terkumpul = array_values($satuSehat['conditionIds'] ?? []);
            $baru = 0;
            $dipungut = 0;
            $gagal = [];

            foreach ($diagnosaList as $diagnosa) {
                $kode = $diagnosa['code'];
                $display = $diagnosa['display'];
                if (empty($kode)) continue;

                // Per diagnosa: satu kegagalan tidak boleh menghanguskan yang lain,
                // karena dulu exception melompati saveResult() sehingga Condition yang
                // SUDAH terbentuk di SATUSEHAT tak pernah tercatat id-nya.
                try {
                    $respons = $this->createFinalDiagnosis([
                        'patientId' => $patientId, 'encounterId' => $satuSehat['encounterId'],
                        'icd10_code' => $kode, 'icd10_display' => $display,
                        'diagnosis_text' => "{$kode} - {$display}",
                        'recordedDate' => $recordedDate->toIso8601String(),
                    ]);
                    if (!empty($respons['id'])) { $terkumpul[] = $respons['id']; $baru++; }
                } catch (\Throwable $e) {
                    if (!$this->isDuplicateError($e)) { $gagal[] = "{$kode}: " . $this->ringkasErrorSatuSehat($e); continue; }

                    $idLama = $this->findExistingConditionId($satuSehat['encounterId'], $kode, $terkumpul, $patientId);
                    if ($idLama !== '') { $terkumpul[] = $idLama; $dipungut++; }
                    else { $gagal[] = "{$kode}: sudah ada di SATUSEHAT tapi id-nya tidak ditemukan di encounter ini"; }
                }
            }

            // SELALU disimpan, walau ada yang gagal — inti perbaikannya di sini.
            $satuSehat['conditionIds'] = array_values(array_unique($terkumpul));
            $this->saveResult($riHdrNo, $satuSehat);
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);

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
                <span class="text-sm font-bold">3</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Condition</div>
                <div class="text-xs text-muted dark:text-gray-400">Diagnosa pasien (ICD-10).</div>
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
        kosong="Belum ada diagnosa ber-ICD-10 di rstxn_ridtls — Kirim akan ditolak sampai diagnosa diisi." />
</div>
