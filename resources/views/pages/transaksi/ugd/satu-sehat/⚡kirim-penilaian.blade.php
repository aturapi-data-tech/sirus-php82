<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-penilaian.blade.php
// Step 12 (UGD): Kirim Penilaian (Observation) — Risiko Jatuh + Gizi.
//
// Sumber = datadaftarugd_json → penilaian.resikoJatuh[] & penilaian.gizi[] (MULTI-ENTRI).
// Node penilaian di UGD IDENTIK dengan RI (jarang: modul lain biasanya beda struktur),
// jadi pemetaan LOINC dipakai bareng lewat App\Support\Terminologi\PenilaianObservationMap.
// Beda dari RI: trait EmrUGDTrait, PK rj_no, event ss-penilaian-ugd.kirim,
// dan fallback waktu = rjDate (RI: entryDate).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Support\Terminologi\PenilaianObservationMap;

new class extends Component {
    use EmrUGDTrait, ObservationTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;       // jumlah Observation terkirim

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Isi yang AKAN dikirim, memanggil helper yang SAMA dengan kirim() —
     * resikoJatuhEntries() & giziEntries(). Tiap entri jadi satu Observation.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $data = $this->findDataUGD($this->rjNo);
        $baris = [];

        foreach ($this->resikoJatuhEntries($data) as $urutan => $entri) {
            $skor = $entri['skor'] ?? ($entri['totalSkor'] ?? null);
            $kategori = $entri['kategori'] ?? ($entri['hasil'] ?? '');
            $baris[] = [
                'label' => 'Risiko jatuh ' . ($urutan + 1),
                'nilai' => trim(($skor !== null ? 'skor ' . $skor : '') . ($kategori !== '' ? ' · ' . $kategori : '')) ?: '(nilai kosong)',
                'ket' => trim((string) ($entri['waktuPemeriksaan'] ?? ($entri['tglPenilaian'] ?? ''))),
            ];
        }

        foreach ($this->giziEntries($data) as $urutan => $entri) {
            $bb = $entri['beratBadan'] ?? ($entri['bb'] ?? null);
            $tb = $entri['tinggiBadan'] ?? ($entri['tb'] ?? null);
            $imt = $entri['imt'] ?? null;
            $isi = [];
            if (!empty($bb)) { $isi[] = "BB {$bb} kg"; }
            if (!empty($tb)) { $isi[] = "TB {$tb} cm"; }
            if (!empty($imt)) { $isi[] = "IMT {$imt}"; }
            $baris[] = [
                'label' => 'Gizi ' . ($urutan + 1),
                'nilai' => implode(' · ', $isi) ?: '(nilai kosong)',
                'ket' => trim((string) ($entri['waktuPemeriksaan'] ?? ($entri['tglPenilaian'] ?? ''))),
            ];
        }

        return $baris;
    }

    public int $jatuhCount = 0;  // entri risiko jatuh tersedia
    public int $giziCount = 0;   // entri gizi tersedia

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
        $this->count = count($satuSehat['penilaianObservationIds'] ?? []);
        $this->jatuhCount = count($this->resikoJatuhEntries($data));
        $this->giziCount = count($this->giziEntries($data));
    }

    /** @return array<int, array> */
    private function resikoJatuhEntries(array $data): array
    {
        return $data['penilaian']['resikoJatuh'] ?? [];
    }

    /** @return array<int, array> */
    private function giziEntries(array $data): array
    {
        return $data['penilaian']['gizi'] ?? [];
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-penilaian-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['penilaianObservationIds'])) { $this->dispatch('toast', type: 'info', message: 'Penilaian sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');

            $jatuhEntries = $this->resikoJatuhEntries($dataUGD);
            $giziEntries = $this->giziEntries($dataUGD);
            if (empty($jatuhEntries) && empty($giziEntries)) {
                $this->dispatch('toast', type: 'error', message: 'Tidak ada data Penilaian (risiko jatuh / gizi).');
                return;
            }

            $idList = [];

            foreach ($jatuhEntries as $entri) {
                $payloadDasar = $this->baseFor($entri, $dataUGD, $patientId, $satuSehat['encounterId'], $practitionerId);
                foreach (PenilaianObservationMap::resikoJatuh($entri) as $observation) {
                    $respons = $this->createObservation(array_merge($payloadDasar, $observation));
                    if (!empty($respons['id'])) $idList[] = $respons['id'];
                }
            }

            foreach ($giziEntries as $entri) {
                $payloadDasar = $this->baseFor($entri, $dataUGD, $patientId, $satuSehat['encounterId'], $practitionerId);
                foreach (PenilaianObservationMap::gizi($entri) as $observation) {
                    $respons = $this->createObservation(array_merge($payloadDasar, $observation));
                    if (!empty($respons['id'])) $idList[] = $respons['id'];
                }
            }

            if (empty($idList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada nilai penilaian valid untuk dikirim.'); return; }

            $satuSehat['penilaianObservationIds'] = $idList;
            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Penilaian berhasil dikirim (' . count($idList) . ' observation).');
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal. Tanpa ini id-nya hangus padahal resource-nya SUDAH ada di sana,
            // lalu percobaan berikutnya menumpuk resource yatim — persis penyebab
            // diagnosa macet permanen dulu (lihat sender Condition). Dibungkus try
            // sendiri supaya kegagalan menyimpan tidak menutupi error aslinya.
            try { if (isset($satuSehat)) { $this->saveResult($rjNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Penilaian gagal: ' . $e->getMessage());
        }
    }

    /** Payload dasar (subject/encounter/performer/waktu) untuk satu entri penilaian. */
    private function baseFor(array $entry, array $dataUGD, string $patientId, string $encounterId, string $practitionerId): array
    {
        $waktu = trim((string) ($entry['tglPenilaian'] ?? ''));
        $isoDate = ($waktu !== '' ? $this->parseDate($waktu) : $this->parseDate($dataUGD['rjDate'] ?? ''))->toIso8601String();

        return [
            'patientId'     => $patientId,
            'encounterId'   => $encounterId,
            'performerId'   => $practitionerId,
            'effectiveDate' => $isoDate,
        ];
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
                <span class="text-sm font-bold">12</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Penilaian</div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Risiko jatuh (skor &amp; kategori) dan gizi (BB, TB, IMT).
                    @if ($jatuhCount > 0 || $giziCount > 0)
                        <span class="text-muted-soft">{{ $jatuhCount }} risiko jatuh, {{ $giziCount }} gizi.</span>
                    @endif
                </div>
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
            kosong="Belum ada penilaian risiko jatuh maupun gizi — Kirim akan ditolak sampai salah satunya diisi." />
    @endif
</div>
