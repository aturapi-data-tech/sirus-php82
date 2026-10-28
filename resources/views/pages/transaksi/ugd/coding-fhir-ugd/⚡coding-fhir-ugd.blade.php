<?php
// resources/views/pages/transaksi/ugd/coding-fhir-ugd/coding-fhir-ugd.blade.php
//
// EMR UGD FHIR — host layar CODING SATUSEHAT (pola sama dengan host emr-ugd): modal, header,
// identitas pasien, grid SOAP, tombol Simpan. Isi tiap kelompok ada di file sendiri:
//   anamnesa/coding-anamnesa-ugd       S — keluhan utama, keluhan penyerta, alergi (+ coding)
//   pemeriksaan/coding-pemeriksaan-ugd O — tanda vital & nutrisi (baca)
//   diagnosa/coding-diagnosa-ugd       A — ICD-10 / ICD-9-CM (baca)
//   perencanaan/coding-perencanaan-ugd P — terapi, tindak lanjut, cara keluar (baca)
//
// Form EMR UGD cukup teks klinis (fokus pelayanan); kode SNOMED dkk diisi di sini SESUDAH
// pelayanan oleh Dokter/Perawat/MR/manager (Gate emr.codingFhir) — keputusan user 2026-10-28.
// Lokasi data TIDAK berubah (key yang sama di datadaftarpolirj_json), jadi sender SATUSEHAT
// tidak berubah. Simpan = event 'save-coding-fhir-ugd'; tiap kelompok yang punya kode menyimpan
// bagiannya sendiri (pola save-rm-* EMR). Tetap bisa diisi walau EMR terkunci.
//
// Dibuka dari titik-3 Daftar UGD & Pelayanan UGD: dispatch 'coding-fhir-ugd.open' (rjNo).

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public ?string $rjNo = null;

    /**
     * Kartu SATUSEHAT UGD dalam mode pratinjau, dikelompokkan seperti grid SOAP di atasnya (tanpa
     * encounter). Kartu yang SAMA dengan modal kirim, jadi yang tampil = yang akan dikirim.
     * Kunci = huruf SOAP => [judul, kelas header, kelas lingkaran, daftar kartu]. Pola sama dengan Coding RI.
     */
    public const KARTU_PRATINJAU = [
        'S' => ['Subjective — Anamnesa', 'bg-blue-100 dark:border-blue-700', 'text-blue-700 bg-blue-100 dark:bg-blue-900/40 dark:text-blue-300',
            ['kedatangan', 'chief-complaint', 'riwayat-penyakit', 'riwayat-keluarga', 'riwayat-pengobatan', 'riwayat-perjalanan', 'allergy', 'status-psikologis']],
        'O' => ['Objective — Pemeriksaan', 'bg-emerald-100 dark:border-emerald-700', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
            ['observation', 'nyeri-kesadaran', 'penilaian', 'lab', 'radiologi']],
        'A' => ['Assessment — Diagnosis', 'bg-amber-100 dark:border-amber-700', 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
            ['condition', 'procedure', 'clinical-impression']],
        'P' => ['Plan — Perencanaan', 'bg-rose-100 dark:border-rose-700', 'bg-rose-100 text-error dark:bg-rose-900/40 dark:text-rose-300',
            ['medication-request', 'medication-dispense', 'pemberian-obat', 'telaah-resep', 'rencana-tindak-lanjut', 'kondisi-pulang', 'resume-medis']],
    ];

    /** Bagian "Yang akan dikirim" baru dimuat saat diminta — tiap kartu membaca data sendiri. */
    public bool $tampilkanKiriman = false;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-coding-fhir-ugd'];

    public function mount(): void
    {
        $this->registerAreas(['modal-coding-fhir-ugd']);
    }

    #[On('coding-fhir-ugd.open')]
    public function openCoding(string $rjNo): void
    {
        if (!auth()->user()?->can('emr.codingFhir')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak membuka Coding SATUSEHAT.');
            return;
        }

        $this->rjNo = $rjNo;
        $this->tampilkanKiriman = false;
        // Versi naik → semua kelompok di-mount ulang & membaca data terbaru dari prop rjNo.
        $this->incrementVersion('modal-coding-fhir-ugd');
        $this->dispatch('open-modal', name: 'coding-fhir-ugd');
    }

    public function toggleKiriman(): void
    {
        $this->tampilkanKiriman = !$this->tampilkanKiriman;
    }

    public function simpan(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->dispatch('save-coding-fhir-ugd', rjNo: (string) $this->rjNo);
    }

    public function closeModal(): void
    {
        $this->rjNo = null;
        $this->tampilkanKiriman = false;
        $this->dispatch('close-modal', name: 'coding-fhir-ugd');
    }
};
?>

<div>
    <x-modal name="coding-fhir-ugd" size="full" height="full" focusable>
        {{-- Kelompok hanya di-mount saat ada pasien: tertutup = nol komponen. --}}
        @if ($rjNo)
            @php $versi = $renderVersions['modal-coding-fhir-ugd'] ?? 0; @endphp

            <div class="flex flex-col min-h-0">
                <x-modul-dokumen.header judul="Coding SATUSEHAT" jalur="UGD"
                    ikon="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />

                {{-- DISPLAY PASIEN — sama dengan EMR UGD --}}
                <div class="px-6 pt-3">
                    <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                        wire:key="coding-fhir-ugd-display-pasien-ugd-{{ $rjNo }}-{{ $versi }}" />
                </div>

                {{-- BODY — grid SOAP seperti EMR UGD --}}
                <div class="flex-1 px-4 pb-4 overflow-y-auto text-base bg-surface-soft/70 dark:bg-gray-950/20">
                    <div class="p-4 mt-3 space-y-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <p class="text-sm text-muted dark:text-gray-400">
                            Teks klinis diisi saat pelayanan (EMR) dan di sini hanya dibaca — yang perlu dibetulkan diubah di EMR.
                            Isi kode di kotak <span class="font-semibold text-brand-green dark:text-brand-lime">Coding SATUSEHAT</span>.
                        </p>

                        <div class="grid grid-cols-1 gap-2 xl:grid-cols-2">
                            {{-- S --}}
                            <div>
                                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg bg-blue-100 dark:border-blue-700">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900/40 dark:text-blue-300">S</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Subjective — Anamnesa</span>
                                </div>
                                <livewire:pages::transaksi.ugd.coding-fhir-ugd.anamnesa.coding-anamnesa-ugd :rjNo="$rjNo"
                                    wire:key="coding-anamnesa-ugd-{{ $rjNo }}-{{ $versi }}" />
                            </div>

                            {{-- O --}}
                            <div>
                                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg bg-emerald-100 dark:border-emerald-700">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">O</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Objective — Pemeriksaan</span>
                                </div>
                                <livewire:pages::transaksi.ugd.coding-fhir-ugd.pemeriksaan.coding-pemeriksaan-ugd :rjNo="$rjNo"
                                    wire:key="coding-pemeriksaan-ugd-{{ $rjNo }}-{{ $versi }}" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2 xl:grid-cols-2">
                            {{-- A --}}
                            <div>
                                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg bg-amber-100 dark:border-amber-700">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">A</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Assessment — Diagnosis</span>
                                </div>
                                <livewire:pages::transaksi.ugd.coding-fhir-ugd.diagnosa.coding-diagnosa-ugd :rjNo="$rjNo"
                                    wire:key="coding-diagnosa-ugd-{{ $rjNo }}-{{ $versi }}" />
                            </div>

                            {{-- P --}}
                            <div>
                                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg bg-rose-100 dark:border-rose-700">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full bg-rose-100 text-error dark:bg-rose-900/40 dark:text-rose-300">P</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Plan — Perencanaan</span>
                                </div>
                                <livewire:pages::transaksi.ugd.coding-fhir-ugd.perencanaan.coding-perencanaan-ugd :rjNo="$rjNo"
                                    wire:key="coding-perencanaan-ugd-{{ $rjNo }}-{{ $versi }}" />
                            </div>
                        </div>
                    </div>

                    {{-- YANG AKAN DIKIRIM — kartu SATUSEHAT UGD mode pratinjau (tanpa tombol Kirim). Dimuat saat diminta. --}}
                    <div class="p-4 mt-4 space-y-3 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-base font-semibold text-ink dark:text-gray-100">Yang akan dikirim ke SATUSEHAT</p>
                                <p class="text-sm text-muted dark:text-gray-400">
                                    Pratinjau tiap kartu, dari sumber yang sama dengan tombol Kirim di modal SATUSEHAT.
                                    Pengiriman tetap dari menu Kirim Satu Sehat.
                                </p>
                            </div>
                            <x-secondary-button type="button" wire:click="toggleKiriman" wire:loading.attr="disabled" wire:target="toggleKiriman">
                                <span wire:loading.remove wire:target="toggleKiriman">{{ $tampilkanKiriman ? 'Sembunyikan' : 'Tampilkan yang akan dikirim' }}</span>
                                <span wire:loading wire:target="toggleKiriman" class="flex items-center gap-1"><x-loading /> Memuat...</span>
                            </x-secondary-button>
                        </div>
                        @if ($tampilkanKiriman)
                            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                @foreach ($this::KARTU_PRATINJAU as $huruf => [$judulKelompok, $kelasHeader, $kelasHuruf, $kartuList])
                                    <div>
                                        <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg {{ $kelasHeader }}">
                                            <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full {{ $kelasHuruf }}">{{ $huruf }}</span>
                                            <span class="text-base font-semibold text-body dark:text-gray-300">{{ $judulKelompok }}</span>
                                        </div>
                                        <div class="space-y-2">
                                            @foreach ($kartuList as $kartu)
                                                <livewire:dynamic-component :is="'pages::transaksi.ugd.satu-sehat.kirim-' . $kartu"
                                                    :rjNo="$rjNo" :hanyaPratinjau="true"
                                                    :key="'coding-pratinjau-' . $kartu . '-' . $rjNo . '-' . $versi" />
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- FOOTER --}}
                <div class="sticky bottom-0 z-10 flex justify-end gap-2 px-6 py-2 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                    <x-primary-button type="button" wire:click="simpan">Simpan Coding</x-primary-button>
                </div>
            </div>
        @endif
    </x-modal>
</div>
