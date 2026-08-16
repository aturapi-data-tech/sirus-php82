<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-clinical-impression.blade.php
// Step 11: Kirim Impresi Klinik (ClinicalImpression, asesmen "A" SOAP)
//
// CATATAN: EMR RJ tak punya satu field "narasi impresi"; penilaian = alat skoring.
// Sumber summary = ringkasan daftar diagnosa (icdX - diagDesc dari dataRJ['diagnosis']) + catatan penilaian bila ada.
// findings SNOMED dikosongkan (diagnosa = ICD-10, bukan SNOMED).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ClinicalImpressionTrait;

new class extends Component {
    use EmrRJTrait, ClinicalImpressionTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /** Ringkasan diagnosa yang AKAN dirangkum, dari sumber yang SAMA dengan kirim(). */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $baris = [];
        foreach (($this->findDataRJ($this->rjNo)['diagnosis'] ?? []) as $urutan => $diagnosa) {
            $kode = $diagnosa['icdX'] ?? ($diagnosa['diagId'] ?? '');
            if (empty($kode)) {
                continue;
            }
            $baris[] = ['label' => 'Diagnosa ' . ($urutan + 1), 'nilai' => $kode, 'ket' => $diagnosa['diagDesc'] ?? ''];
        }

        return $baris;
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
        $this->count = !empty($satuSehat['clinicalImpressionId']) ? 1 : 0;
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-clinical-impression-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['clinicalImpressionId'])) { $this->dispatch('toast', type: 'info', message: 'Impresi klinik sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $assessorId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($assessorId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            // Ringkas diagnosa jadi teks impresi. Sumber JSON: dataRJ['diagnosis'][]
            // { icdX/diagId, diagDesc } — ditulis rm-diagnosa-rj-actions; sama dengan
            // sender UGD. (Key lama diagnpinaList tidak pernah ditulis siapa pun.)
            $diagnosaList = $dataRJ['diagnosis'] ?? [];
            $bagianList = [];
            foreach ($diagnosaList as $diagnosa) {
                $kode = $diagnosa['icdX'] ?? ($diagnosa['diagId'] ?? '');
                $deskripsi = $diagnosa['diagDesc'] ?? '';
                if ($kode !== '' || $deskripsi !== '') { $bagianList[] = trim("{$kode} - {$deskripsi}", ' -'); }
            }
            $summary = $bagianList ? ('Kesimpulan klinis: ' . implode('; ', $bagianList)) : '';
            if ($summary === '') { $this->dispatch('toast', type: 'error', message: 'Belum ada diagnosa sebagai dasar impresi klinik.'); return; }

            $waktu = $this->parseDate($dataRJ['rjDate'] ?? '')->toIso8601String();

            $respons = $this->createClinicalImpression([
                'patientId'   => $patientId,
                'encounterId' => $satuSehat['encounterId'],
                'assessorId'  => $assessorId,
                'summary'     => $summary,
                'description' => 'Asesmen kunjungan rawat jalan',
                'effective'   => $waktu,
            ]);

            if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'Impresi klinik gagal: respons tanpa id.'); return; }

            $satuSehat['clinicalImpressionId'] = $respons['id'];
            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Impresi klinik berhasil dikirim.');
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal. Tanpa ini id-nya hangus padahal resource-nya SUDAH ada di sana,
            // lalu percobaan berikutnya menumpuk resource yatim — persis penyebab
            // diagnosa macet permanen dulu (lihat sender Condition). Dibungkus try
            // sendiri supaya kegagalan menyimpan tidak menutupi error aslinya.
            try { if (isset($satuSehat)) { $this->saveResult($rjNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Impresi klinik gagal: ' . $e->getMessage());
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
                <span class="text-sm font-bold">11</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Clinical Impression</div>
                <div class="text-xs text-muted dark:text-gray-400">Impresi klinik (ringkasan diagnosa).</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        terkirim
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
            kosong="Belum ada diagnosa di EMR — Clinical Impression merangkumnya, jadi Kirim akan ditolak." />
    @endif
</div>
