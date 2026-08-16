<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-penilaian.blade.php
// Step 12 (RJ): Kirim Penilaian (Observation) — Risiko Jatuh + Gizi.
//
// Sumber = datadaftarpolirj_json → penilaian.resikoJatuh[] & penilaian.gizi[] (MULTI-ENTRI).
// Node penilaian RJ/RI/UGD IDENTIK (jarang di repo ini) → pemetaan LOINC dipakai bareng
// lewat App\Support\Terminologi\PenilaianObservationMap. Beda RJ: trait EmrRJTrait, PK rj_no,
// kolom CLOB datadaftarpolirj_json, event ss-penilaian-rj.kirim, fallback waktu rjDate.
//
// CATATAN DATA: mayoritas entri RJ tersimpan tanpa skala (metode='', skor=0,
// kategori='') karena penilaian dijawab "Tidak berisiko" → helper melewatinya dan
// kartu ini wajar menampilkan 0 terkirim. Hanya entri ber-skala yang punya isi.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Support\Terminologi\PenilaianObservationMap;

new class extends Component {
    use EmrRJTrait, ObservationTrait;

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

        $data = $this->findDataRJ($this->rjNo);
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

    /**
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya — berhasil, ditolak
     * SATUSEHAT, atau berhenti di guard — langkah ini WAJIB memberi kabar, supaya
     * orkestrator bisa melanjutkan. Tanpa ini rantai menggantung diam-diam pada
     * langkah pertama yang gagal, dan petugas cuma melihat modal yang membeku.
     */
    #[On('ss-penilaian-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'penilaian');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['penilaianObservationIds'])) { $this->dispatch('toast', type: 'info', message: 'Penilaian sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');

            $jatuhEntries = $this->resikoJatuhEntries($dataRJ);
            $giziEntries = $this->giziEntries($dataRJ);
            if (empty($jatuhEntries) && empty($giziEntries)) {
                $this->dispatch('toast', type: 'error', message: 'Tidak ada data Penilaian (risiko jatuh / gizi).');
                return;
            }

            $idList = [];

            foreach ($jatuhEntries as $entri) {
                $payloadDasar = $this->baseFor($entri, $dataRJ, $patientId, $satuSehat['encounterId'], $practitionerId);
                foreach (PenilaianObservationMap::resikoJatuh($entri) as $observation) {
                    $respons = $this->createObservation(array_merge($payloadDasar, $observation));
                    if (!empty($respons['id'])) $idList[] = $respons['id'];
                }
            }

            foreach ($giziEntries as $entri) {
                $payloadDasar = $this->baseFor($entri, $dataRJ, $patientId, $satuSehat['encounterId'], $practitionerId);
                foreach (PenilaianObservationMap::gizi($entri) as $observation) {
                    $respons = $this->createObservation(array_merge($payloadDasar, $observation));
                    if (!empty($respons['id'])) $idList[] = $respons['id'];
                }
            }

            // Wajar kosong: entri tanpa skala (jawaban "Tidak berisiko") sengaja dilewati.
            if (empty($idList)) { $this->dispatch('toast', type: 'info', message: 'Tidak ada penilaian ber-skala untuk dikirim (entri tanpa skala dilewati).'); return; }

            $satuSehat['penilaianObservationIds'] = $idList;
            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Penilaian berhasil dikirim (' . count($idList) . ' observation).');
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
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
    private function baseFor(array $entry, array $dataRJ, string $patientId, string $encounterId, string $practitionerId): array
    {
        $waktu = trim((string) ($entry['tglPenilaian'] ?? ''));
        $isoDate = ($waktu !== '' ? $this->parseDate($waktu) : $this->parseDate($dataRJ['rjDate'] ?? ''))->toIso8601String();

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
        kosong="Belum ada penilaian risiko jatuh maupun gizi — Kirim akan ditolak sampai salah satunya diisi." />
</div>
