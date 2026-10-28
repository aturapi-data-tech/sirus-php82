<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-kedatangan.blade.php
// Kirim Kedatangan Pasien IGD — Playbook IGD bab 3a–3c, tiga Observation (category survey):
//   3a Sarana transportasi   pengkajianPerawatan.saranaTransportasiId/Desc/Ket → LOINC 74286-6, nilai SNOMED
//   3b Surat pengantar rujukan pengkajianPerawatan.caraMasukIgd ('Rujuk' = ya)  → OC000034, valueBoolean
//   3c Kondisi tiba (triase)  pengkajianPerawatan.tingkatKegawatan (P1–P3; kosong → saran Screening
//                             screening.triaseSaran)                          → LOINC 75910-0 CTAS
// Kode di App\Support\Terminologi\KedatanganIgd (diverifikasi tx.fhir.org; dua kode CTAS apikesehatan
// salah). Waktu = jam datang. Hasil: satusehat.kedatanganIds = [transportasi|suratRujukan|triase => id];
// yang sudah terkirim tak dikirim ulang. P0 (meninggal saat tiba) tidak punya padanan CTAS → dilewati.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Support\Terminologi\KedatanganIgd;
use App\Support\Terminologi\NyeriKesadaranObservationMap;

new class extends Component {
    use EmrUGDTrait, ObservationTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;
    public int $jumlahTarget = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Observation yang AKAN dikirim (sumber tunggal untuk pratinjau & kirim).
     *
     * @return array<string, array> jenis => potongan payload + 'label' & 'tampil'
     */
    private function rencanaKirim(array $dataUGD): array
    {
        $perawatan = $dataUGD['anamnesa']['pengkajianPerawatan'] ?? [];
        $rencana = [];

        $transportasi = KedatanganIgd::transportasi($perawatan['saranaTransportasiId'] ?? null, $perawatan['saranaTransportasiDesc'] ?? null, $perawatan['saranaTransportasiKet'] ?? null);
        if ($transportasi !== null) {
            $rencana['transportasi'] = ['label' => 'Sarana transportasi', 'code' => KedatanganIgd::KODE_TRANSPORTASI,
                'valueCodeableConcept' => $transportasi,
                'tampil' => $transportasi['text'] . (isset($transportasi['code']) ? ' · SNOMED ' . $transportasi['code'] : ' · teks (tanpa kode)')];
        }

        $suratRujukan = KedatanganIgd::membawaSuratRujukan($perawatan['caraMasukIgd'] ?? null);
        if ($suratRujukan !== null) {
            $rencana['suratRujukan'] = ['label' => 'Surat pengantar rujukan', 'code' => KedatanganIgd::KODE_SURAT_RUJUKAN,
                'valueBoolean' => $suratRujukan,
                'note' => trim((string) ($perawatan['caraMasukIgdDesc'] ?? '')) ?: null,
                'tampil' => ($suratRujukan ? 'Ya' : 'Tidak') . ' (cara masuk: ' . $perawatan['caraMasukIgd'] . ')'];
        }

        ['tingkat' => $tingkat, 'sumber' => $sumberTriase] = KedatanganIgd::tingkatTriase($dataUGD);
        $triase = KedatanganIgd::triase($tingkat);
        if ($triase !== null) {
            $rencana['triase'] = ['label' => 'Kondisi tiba (triase)', 'code' => KedatanganIgd::KODE_TRIASE,
                'valueCodeableConcept' => $triase,
                'tampil' => $triase['text'] . ' · ' . $triase['code'] . ' · dari ' . $sumberTriase];
        }

        return $rencana;
    }

    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $data = $this->findDataUGD($this->rjNo);
        $terkirim = $data['satusehat']['kedatanganIds'] ?? [];
        $rencanaList = $this->rencanaKirim($data);
        $baris = [];
        foreach ($rencanaList as $jenis => $rencana) {
            $baris[] = ['label' => $rencana['label'], 'nilai' => $rencana['tampil'], 'ket' => isset($terkirim[$jenis]) ? 'sudah terkirim' : 'akan dikirim'];
        }
        // Triase terisi tapi tak punya padanan CTAS (P0) — dilaporkan, bukan hilang diam-diam.
        $tingkat = KedatanganIgd::tingkatTriase($data)['tingkat'];
        if ($tingkat !== '' && !isset($rencanaList['triase'])) {
            $baris[] = ['label' => 'Kondisi tiba (triase)', 'nilai' => KedatanganIgd::labelTriase($tingkat), 'ket' => 'tidak dikirim: tak ada padanan CTAS'];
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
        $this->count = count($satuSehat['kedatanganIds'] ?? []);
        $this->jumlahTarget = count($this->rencanaKirim($data));
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
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya langkah ini WAJIB memberi
     * kabar, supaya orkestrator bisa melanjutkan ke langkah berikutnya.
     */
    #[On('ss-kedatangan-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('ugd-satu-sehat.langkah-selesai', langkah: 'kedatangan');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }

            $terkirim = $satuSehat['kedatanganIds'] ?? [];
            $antrean = array_diff_key($this->rencanaKirim($dataUGD), $terkirim);
            if (empty($antrean)) {
                $this->dispatch('toast', type: 'info', message: $terkirim !== []
                    ? 'Data kedatangan sudah pernah dikirim.'
                    : 'Sarana transportasi, cara masuk, dan triase belum diisi di Pengkajian Perawatan — dilewati.');
                return;
            }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
            $jamDatang = trim((string) ($dataUGD['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? '')) ?: (string) ($dataUGD['rjDate'] ?? '');
            $waktuIso = $this->parseDate($jamDatang)->toIso8601String();

            $gagalList = [];
            foreach ($antrean as $jenis => $rencana) {
                try {
                    $respons = $this->createObservation([
                        'patientId'     => $patientId,
                        'encounterId'   => $satuSehat['encounterId'],
                        'performerId'   => $practitionerId,
                        'effectiveDate' => $waktuIso,
                        'issued'        => $waktuIso,
                        'category'      => NyeriKesadaranObservationMap::surveyCategory(),
                        'code'          => $rencana['code'],
                        ...array_intersect_key($rencana, array_flip(['valueCodeableConcept', 'valueBoolean', 'note'])),
                    ]);
                    if (empty($respons['id'])) {
                        $gagalList[] = $rencana['label'] . ' (respons tanpa id)';
                        continue;
                    }
                    $terkirim[$jenis] = $respons['id'];
                } catch (\Throwable $e) {
                    $gagalList[] = $rencana['label'] . ' (' . $e->getMessage() . ')';
                }
            }

            // Yang berhasil tetap disimpan walau sebagian gagal — kirim ulang tak menggandakan.
            $satuSehat['kedatanganIds'] = $terkirim;
            $this->saveResult($rjNo, $satuSehat);

            empty($gagalList)
                ? $this->dispatch('toast', type: 'success', message: 'Data kedatangan berhasil dikirim (' . count($antrean) . ' observasi).')
                : $this->dispatch('toast', type: 'error', message: 'Sebagian data kedatangan gagal: ' . implode('; ', $gagalList));
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Data kedatangan gagal: ' . $e->getMessage());
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
                <div class="font-semibold text-ink dark:text-gray-100">Kedatangan &amp; Triase</div>
                <div class="text-xs text-muted dark:text-gray-400">Sarana transportasi, surat rujukan, kondisi tiba (CTAS).</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }}/{{ $jumlahTarget }} observasi terkirim
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
        kosong="Sarana transportasi, cara masuk, dan triase belum diisi di Pengkajian Perawatan." />
</div>
