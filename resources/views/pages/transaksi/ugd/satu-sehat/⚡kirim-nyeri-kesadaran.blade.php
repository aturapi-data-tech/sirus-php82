<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-nyeri-kesadaran.blade.php
// Kirim Skala Nyeri (multi-entri) + Tingkat Kesadaran sebagai Observation.
//
// Sumber data — DUA node berbeda, keduanya sudah diisi petugas dan selama ini
// tidak pernah dikirim ke SATUSEHAT:
//   penilaian.nyeri[]     → multi-entri, bentuk lama & baru bercampur; WAJIB
//                           lewat NyeriOptions::daftarEntri() yang menyatukannya
//   screening.kesadaran   → tunggal, LIMA pilihan dan SELURUHNYA berbeda dari RJ:
//                           'Sadar Penuh' | 'Tampak Mengantuk' | 'Gelisah' |
//                           'Bicara Tidak Jelas' | 'Tidak Ada Respons'
//                           (skrining gawat darurat memang lebih rinci)
//
// Kode terminologi ada di App\Support\Terminologi\NyeriKesadaranObservationMap,
// seluruhnya dari koleksi Postman resmi. Skala tanpa contoh resmi (VAS, FLACC,
// BPS, CPOT, PAINAD) sengaja TIDAK dikirim — dan jumlahnya dimunculkan di kartu
// serta disebut di toast, sesuai aturan "yang tak terkirim wajib dilaporkan".
//
// BELUM SATU PUN pilihan kesadaran UGD punya padanan SNOMED resmi, jadi kelimanya
// dikirim sebagai teks. Bandingkan RJ yang punya satu ('Mengantuk / Gelisah').
//
// CATATAN: GCS TIDAK ikut di sini. EMR UGD tidak punya penilaian GCS umum —
// sama seperti RJ, satu-satunya field bernama 'gcs' milik blok penunjang lain.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Support\Options\NyeriOptions;
use App\Support\Terminologi\NyeriKesadaranObservationMap;

new class extends Component {
    use EmrUGDTrait, ObservationTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;          // Observation terkirim
    public int $nyeriSiap = 0;      // entri nyeri yang BISA dikirim
    public int $nyeriDilewati = 0;  // entri nyeri ber-skala tanpa kode resmi
    public bool $adaKesadaran = false;

    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /** Isi yang AKAN dikirim — memakai helper yang SAMA dengan kirim(). */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $data = $this->findDataUGD($this->rjNo);
        $baris = [];

        foreach (NyeriOptions::daftarEntri($data['penilaian']['nyeri'] ?? []) as $urutan => $entri) {
            $node = $entri['nyeri'] ?? [];
            $kodeSkala = (string) ($node['nyeriMetode']['nyeriMetode'] ?? '');
            $skor = $node['nyeriMetode']['nyeriMetodeScore'] ?? null;
            $terkirim = NyeriKesadaranObservationMap::nyeri($entri) !== [];

            $baris[] = [
                'label' => 'Nyeri ' . ($urutan + 1) . ($kodeSkala !== '' ? ' (' . $kodeSkala . ')' : ''),
                'nilai' => $terkirim
                    ? 'skor ' . $skor
                    : ($kodeSkala !== '' && !NyeriKesadaranObservationMap::skalaDidukung($kodeSkala)
                        ? '(skala ' . $kodeSkala . ' belum punya kode resmi — dilewati)'
                        : '(tidak dikirim)'),
                'ket' => trim((string) ($entri['tglPenilaian'] ?? '')),
            ];
        }

        $kesadaran = trim((string) ($data['screening']['kesadaran'] ?? ''));
        if ($kesadaran !== '') {
            $baris[] = ['label' => 'Tingkat kesadaran', 'nilai' => $kesadaran, 'ket' => ''];
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
        $this->count = count($satuSehat['nyeriKesadaranObservationIds'] ?? []);

        $siap = 0;
        $lewat = 0;
        foreach (NyeriOptions::daftarEntri($data['penilaian']['nyeri'] ?? []) as $entri) {
            if (NyeriKesadaranObservationMap::nyeri($entri) !== []) {
                $siap++;
                continue;
            }
            $kodeSkala = (string) ($entri['nyeri']['nyeriMetode']['nyeriMetode'] ?? '');
            // Hanya dihitung "dilewati" bila petugas MEMANG memilih skala; entri
            // tanpa skala (dijawab tidak nyeri) bukan kehilangan data.
            if ($kodeSkala !== '' && !NyeriKesadaranObservationMap::skalaDidukung($kodeSkala)) {
                $lewat++;
            }
        }
        $this->nyeriSiap = $siap;
        $this->nyeriDilewati = $lewat;
        $this->adaKesadaran = trim((string) ($data['screening']['kesadaran'] ?? '')) !== '';
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    /** Pembungkus rantai "Kirim Semua" — wajib melapor apa pun hasilnya. */
    #[On('ss-nyeri-kesadaran-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('ugd-satu-sehat.langkah-selesai', langkah: 'nyeri-kesadaran');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['nyeriKesadaranObservationIds'])) { $this->dispatch('toast', type: 'info', message: 'Nyeri & kesadaran sudah pernah dikirim.'); return; }

            $patientId = (string) (DB::table('rsmst_pasiens')->where('reg_no', $dataUGD['regNo'] ?? '')->value('patient_uuid') ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');

            $entriNyeri = NyeriOptions::daftarEntri($dataUGD['penilaian']['nyeri'] ?? []);
            $kesadaran = trim((string) ($dataUGD['screening']['kesadaran'] ?? ''));
            if (empty($entriNyeri) && $kesadaran === '') {
                $this->dispatch('toast', type: 'error', message: 'Tidak ada data Skala Nyeri maupun Tingkat Kesadaran.');
                return;
            }

            $idList = [];
            $dilewati = [];

            foreach ($entriNyeri as $entri) {
                $observationList = NyeriKesadaranObservationMap::nyeri($entri);
                if ($observationList === []) {
                    $kodeSkala = (string) ($entri['nyeri']['nyeriMetode']['nyeriMetode'] ?? '');
                    if ($kodeSkala !== '' && !NyeriKesadaranObservationMap::skalaDidukung($kodeSkala)) {
                        $dilewati[$kodeSkala] = true;
                    }
                    continue;
                }
                $payloadDasar = $this->baseFor($entri['tglPenilaian'] ?? '', $dataUGD, $patientId, $satuSehat['encounterId'], $practitionerId);
                foreach ($observationList as $observation) {
                    $respons = $this->createObservation(array_merge($payloadDasar, $observation));
                    if (!empty($respons['id'])) { $idList[] = $respons['id']; }
                }
            }

            // Kesadaran diambil dari skrining awal, jadi waktunya mengikuti kunjungan.
            foreach (NyeriKesadaranObservationMap::kesadaran($kesadaran) as $observation) {
                $payloadDasar = $this->baseFor('', $dataUGD, $patientId, $satuSehat['encounterId'], $practitionerId);
                $respons = $this->createObservation(array_merge($payloadDasar, $observation));
                if (!empty($respons['id'])) { $idList[] = $respons['id']; }
            }

            $catatanLewat = $dilewati === [] ? '' : ' ' . count($dilewati) . ' skala dilewati (' . implode(', ', array_keys($dilewati)) . ') — belum ada kode resmi SATUSEHAT.';

            if (empty($idList)) {
                $this->dispatch('toast', type: 'info', message: 'Tidak ada yang bisa dikirim.' . $catatanLewat);
                return;
            }

            $satuSehat['nyeriKesadaranObservationIds'] = $idList;
            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: $dilewati === [] ? 'success' : 'warning', message: 'Nyeri & kesadaran terkirim (' . count($idList) . ' observation).' . $catatanLewat);
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu id yang TERLANJUR terbentuk di SATUSEHAT sebelum melapor gagal —
            // tanpa ini resource-nya jadi yatim dan percobaan berikutnya menumpuk duplikat.
            try { if (isset($satuSehat)) { $this->saveResult($rjNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Nyeri & kesadaran gagal: ' . $e->getMessage());
        }
    }

    private function baseFor(string $waktu, array $dataUGD, string $patientId, string $encounterId, string $practitionerId): array
    {
        $waktu = trim($waktu);
        $isoDate = ($waktu !== '' ? $this->parseDate($waktu) : $this->parseDate($dataUGD['rjDate'] ?? ''))->toIso8601String();

        return [
            'patientId'     => $patientId,
            'encounterId'   => $encounterId,
            'performerId'   => $practitionerId,
            'effectiveDate' => $isoDate,
        ];
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
        if (empty($teksTanggal)) { return Carbon::now(); }
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
                <span class="text-sm font-bold">13</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Nyeri &amp; Kesadaran</div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Skala nyeri per penilaian dan tingkat kesadaran dari skrining awal.
                    @if ($nyeriSiap > 0 || $adaKesadaran)
                        <span class="text-muted-soft">
                            {{ $nyeriSiap }} nyeri{{ $adaKesadaran ? ', 1 kesadaran' : '' }}.
                        </span>
                    @endif
                </div>
                @if ($nyeriDilewati > 0)
                    {{-- Yang tak terkirim tidak boleh hilang diam-diam --}}
                    <div class="mt-1 text-xs text-warning-deep dark:text-amber-400">
                        {{ $nyeriDilewati }} entri memakai skala yang belum punya kode resmi SATUSEHAT — tidak ikut terkirim.
                    </div>
                @endif
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
        kosong="Belum ada penilaian nyeri maupun tingkat kesadaran — Kirim akan ditolak sampai salah satunya diisi." />
</div>
