<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-medication-dispense.blade.php
// Step 8: Kirim Obat Dibawa Pulang (MedicationDispense)
//
// ASUMSI (MVP) — dispense sesungguhnya terjadi di alur APOTEK; di sini kita
// aproksimasi dari node 'eresep' + waktu kirim:
//   - authorizingPrescription  → MedicationRequest yang sudah dikirim (index-match), maka WAJIB resep dikirim dulu
//   - performer                → IHS dokter (idealnya apoteker; belum ada IHS apoteker)
//   - whenPrepared/HandedOver   → jam taskId6 / taskId7 (cadangan: sekarang)
//   - quantity                 → qty eresep
// Item tanpa KFA di-skip (pola sama MedicationRequest).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\MedicationDispenseTrait;
use App\Support\Terminologi\MedicationRequestItem;
use App\Support\Terminologi\RacikanKfa;

new class extends Component {
    use EmrRJTrait, MedicationDispenseTrait;

    public ?string $rjNo = null;

    /** Mode pratinjau saja (layar Coding SATUSEHAT): tombol Kirim disembunyikan, kirim ditolak. */
    public bool $hanyaPratinjau = false;
    public bool $hasEncounter = false;
    public bool $hasResep = false;
    public int $count = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Obat yang AKAN diserahkan, memakai peta yang SAMA dengan kirim() —
     * MedicationRequestItem::ambil(). Peta inilah yang menautkan tiap penyerahan
     * ke resep yang benar; kalau ia kosong, kirim() memang membatalkan diri.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $data = $this->findDataRJ($this->rjNo);
        $itemList = MedicationRequestItem::ambil($data['satusehat'] ?? [], $data);
        if (empty($itemList)) {
            return [];
        }

        $baris = [];
        foreach ($itemList as $urutan => $item) {
            $jenis = ($item['jenis'] ?? '') === 'racikan' ? 'Racikan' : 'Non-racikan';
            $baris[] = [
                'label' => $jenis . ' ' . ($urutan + 1),
                'nilai' => (string) ($item['display'] ?? ($item['kunci'] ?? '-')),
                'ket' => trim('qty ' . ($item['qty'] ?? 1) . ' · ' . ($item['kode'] ? 'KFA ' . $item['kode'] : 'racikan')),
            ];
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
        $this->hasResep     = !empty($satuSehat['medicationRequestIds']);
        $this->count        = count($satuSehat['medicationDispenseIds'] ?? []);
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
    #[On('ss-medication-dispense-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        if ($this->hanyaPratinjau) {
            return;
        }
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'medication-dispense');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            $mrIds = $satuSehat['medicationRequestIds'] ?? [];
            if (empty($mrIds)) { $this->dispatch('toast', type: 'error', message: 'Kirim Resep (MedicationRequest) terlebih dahulu.'); return; }
            if (!empty($satuSehat['medicationDispenseIds'])) { $this->dispatch('toast', type: 'info', message: 'Obat pulang sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $performerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($performerId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $orgId       = env('SATUSEHAT_ORGANIZATION_ID');
            $patientName = $dataRJ['regName'] ?? '';
            // Jam layanan apotek yang sebenarnya (task 6 = resep diterima/disiapkan, task 7 = obat
            // diserahkan), bukan jam petugas mengklik; kosong → jam sekarang (perilaku lama).
            $task = $dataRJ['taskIdPelayanan'] ?? [];
            $nowIso = Carbon::now()->toIso8601String();
            $jamSiapIso = filled($task['taskId6'] ?? null) ? $this->parseDate((string) $task['taskId6'])->toIso8601String() : $nowIso;
            $jamSerahIso = filled($task['taskId7'] ?? null) ? $this->parseDate((string) $task['taskId7'])->toIso8601String() : $nowIso;

            // Sumber pasangan resep→penyerahan: peta yang dicatat saat MedicationRequest
            // dikirim. Sebelumnya dipasangkan lewat URUTAN daftar — geser satu item saja,
            // obat tertaut ke resep yang salah tanpa ada yang tahu.
            // Resep yang dikirim sebelum peta ini ada disusun ulang dari urutan
            // pengirimannya (non-racikan dulu, lalu racikan) — dan ditolak bila
            // jumlahnya tak cocok, daripada memasangkan obat ke resep yang salah.
            $itemList = MedicationRequestItem::ambil($satuSehat, $dataRJ);
            if (empty($itemList)) {
                $this->dispatch('toast', type: 'error',
                    message: 'Rincian item resep tak bisa dipastikan (daftar obat berubah setelah resep dikirim). Dispense dibatalkan.');
                return;
            }

            $racikanPerNomor = [];
            foreach (RacikanKfa::grupList($dataRJ) as $grup) {
                $racikanPerNomor[$grup['noRacikan']] = $grup;
            }

            $satuSehat['medicationDispenseIds'] = [];
            $dilewati = [];
            foreach ($itemList as $indeks => $item) {
                $adalahRacikan = ($item['jenis'] ?? '') === 'racikan';
                $ingredient = [];

                if ($adalahRacikan) {
                    $grup = $racikanPerNomor[$item['kunci']] ?? null;
                    if ($grup === null || !$grup['siap']) {
                        $dilewati[] = 'racikan ' . $item['kunci'];
                        continue;
                    }
                    $ingredient = RacikanKfa::fhirIngredient($grup['bahanList']);
                }

                $itemId = "$rjNo-" . ($indeks + 1);
                $quantity = (int) ($item['qty'] ?? 1) ?: 1;

                $respons = $this->createMedicationDispense([
                    'orgId' => $orgId,
                    'registrationId' => $adalahRacikan ? "RACIKAN-{$itemId}" : $item['kode'],
                    'prescriptionItemId' => $itemId,
                    'medContainedId' => "meddisp-{$itemId}",
                    'medicationCode' => $item['kode'], 'medicationDisplay' => $item['display'],
                    'ingredient' => $ingredient,
                    'medicationFormCode' => 'BS066', 'medicationFormDisplay' => 'Tablet',
                    'medicationTypeCode' => $adalahRacikan ? 'SD' : 'NC',
                    'medicationTypeDisplay' => $adalahRacikan ? 'Compound' : 'Non-compound',
                    'patientId' => $patientId, 'patientName' => $patientName, 'encounterId' => $satuSehat['encounterId'],
                    'status' => 'completed', 'category' => 'outpatient',
                    'whenPrepared' => $jamSiapIso, 'whenHandedOver' => $jamSerahIso,
                    'performer' => [['actor' => ['reference' => "Practitioner/{$performerId}"]]],
                    'dosageInstruction' => [],
                    'authorizingPrescription' => ['reference' => "MedicationRequest/{$item['id']}"],
                    // Satuan pakai v3-orderableDrugForm (pola yang sudah dipakai
                    // KirimRawatJalanTrait). CodeSystem kfa-satuan DITOLAK SATUSEHAT:
                    // "Invalid coding system ... kfa-satuan (RuleNumber: 10050)".
                    'quantity' => ['value' => $quantity, 'unit' => 'Tablet', 'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm', 'code' => 'TAB'],
                    // daysSupply TIDAK dikirim — dulu di-hardcode 1 hari (angka karangan). Contoh alur
                    // apikesehatan.com pun tak mengirimnya.
                    'receiver' => ['reference' => "Patient/{$patientId}", 'display' => $patientName],
                ]);
                if (!empty($respons['id'])) $satuSehat['medicationDispenseIds'][] = $respons['id'];
            }

            $this->saveResult($rjNo, $satuSehat);
            $pesan = 'Obat pulang berhasil dikirim (' . count($satuSehat['medicationDispenseIds']) . ' item).';
            if ($dilewati !== []) {
                $pesan .= ' Dilewati: ' . implode(', ', $dilewati) . '.';
            }
            $this->dispatch('toast', type: 'success', message: $pesan);
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal. Tanpa ini id-nya hangus padahal resource-nya SUDAH ada di sana,
            // lalu percobaan berikutnya menumpuk resource yatim — persis penyebab
            // diagnosa macet permanen dulu (lihat sender Condition). Dibungkus try
            // sendiri supaya kegagalan menyimpan tidak menutupi error aslinya.
            try { if (isset($satuSehat)) { $this->saveResult($rjNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Obat pulang gagal: ' . $e->getMessage());
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    /** Teks waktu task-id (d/m/Y H:i:s) → Carbon; gagal → sekarang. Pola sama dgn sender RJ lain. */
    private function parseDate(string $teksTanggal): Carbon
    {
        if (empty($teksTanggal)) return Carbon::now();
        try { return Carbon::createFromFormat('d/m/Y H:i:s', $teksTanggal); } catch (\Throwable) {
            try { return Carbon::parse($teksTanggal); } catch (\Throwable) { return Carbon::now(); }
        }
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
                <div class="{{ $hanyaPratinjau ? 'text-xs font-semibold tracking-wide uppercase text-brand-green dark:text-brand-lime' : 'font-semibold text-ink dark:text-gray-100' }}">Medication Dispense</div>
                <div class="text-xs text-muted dark:text-gray-400">Obat dibawa pulang (butuh resep dikirim dulu).</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }} terkirim
                    </div>
                @elseif (!$hasResep)
                    <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">Kirim Resep dulu</div>
                @endif
            </div>
        </div>
        @unless ($hanyaPratinjau)
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter || !$hasResep"
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
        kosong="Resep belum dikirim atau rincian itemnya tak bisa dipastikan — Dispense akan ditolak." />
</div>
