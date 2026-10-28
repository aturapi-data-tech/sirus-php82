<?php
// resources/views/pages/transaksi/ri/coding-fhir-ri/coding-fhir-ri.blade.php
//
// EMR RI FHIR — host layar CODING SATUSEHAT (pola sama dengan host emr-ri): modal, header,
// identitas pasien, grid SOAP, tombol Simpan. Isi tiap kelompok ada di file sendiri:
//   anamnesa/coding-anamnesa-ri       S — keluhan utama, keluhan penyerta, alergi (+ coding)
//   pemeriksaan/coding-pemeriksaan-ri O — tanda vital & nutrisi (baca)
//   diagnosa/coding-diagnosa-ri       A — ICD-10 / ICD-9-CM (baca)
//   perencanaan/coding-perencanaan-ri P — terapi, tindak lanjut, cara keluar (baca)
//
// Form EMR RI cukup teks klinis (fokus pelayanan); kode SNOMED dkk diisi di sini SESUDAH
// pelayanan oleh Dokter/Perawat/MR/manager (Gate emr.codingFhir) — keputusan user 2026-10-28.
// Lokasi data TIDAK berubah (key yang sama di datadaftarpolirj_json), jadi sender SATUSEHAT
// tidak berubah. Simpan = event 'save-coding-fhir-ri'; tiap kelompok yang punya kode menyimpan
// bagiannya sendiri (pola save-rm-* EMR). Tetap bisa diisi walau EMR terkunci.
//
// Dibuka dari titik-3 Daftar RI: dispatch 'coding-fhir-ri.open' (riHdrNo).

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public ?string $riHdrNo = null;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-coding-fhir-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-coding-fhir-ri']);
    }

    #[On('coding-fhir-ri.open')]
    public function openCoding(string $riHdrNo): void
    {
        if (!auth()->user()?->can('emr.codingFhir')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak membuka Coding SATUSEHAT.');
            return;
        }

        $this->riHdrNo = $riHdrNo;
        // Versi naik → semua kelompok di-mount ulang & membaca data terbaru dari prop riHdrNo.
        $this->incrementVersion('modal-coding-fhir-ri');
        $this->dispatch('open-modal', name: 'coding-fhir-ri');
    }

    public function simpan(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->dispatch('save-coding-fhir-ri', riHdrNo: (string) $this->riHdrNo);
    }

    public function closeModal(): void
    {
        $this->riHdrNo = null;
        $this->dispatch('close-modal', name: 'coding-fhir-ri');
    }
};
?>

<div>
    <x-modal name="coding-fhir-ri" size="full" height="full" focusable>
        {{-- Kelompok hanya di-mount saat ada pasien: tertutup = nol komponen. --}}
        @if ($riHdrNo)
            @php $versi = $renderVersions['modal-coding-fhir-ri'] ?? 0; @endphp

            <div class="flex flex-col min-h-0">
                <x-modul-dokumen.header judul="Coding SATUSEHAT" jalur="RI"
                    ikon="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />

                {{-- DISPLAY PASIEN — sama dengan EMR RI --}}
                <div class="px-6 pt-3">
                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="coding-fhir-ri-display-pasien-{{ $riHdrNo }}-{{ $versi }}" />
                </div>

                {{-- BODY — grid SOAP seperti EMR RI --}}
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
                                <livewire:pages::transaksi.ri.coding-fhir-ri.anamnesa.coding-anamnesa-ri :riHdrNo="$riHdrNo"
                                    wire:key="coding-anamnesa-ri-{{ $riHdrNo }}-{{ $versi }}" />
                            </div>

                            {{-- O --}}
                            <div>
                                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg bg-emerald-100 dark:border-emerald-700">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">O</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Objective — Pemeriksaan</span>
                                </div>
                                <livewire:pages::transaksi.ri.coding-fhir-ri.pemeriksaan.coding-pemeriksaan-ri :riHdrNo="$riHdrNo"
                                    wire:key="coding-pemeriksaan-ri-{{ $riHdrNo }}-{{ $versi }}" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2 xl:grid-cols-2">
                            {{-- A --}}
                            <div>
                                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg bg-amber-100 dark:border-amber-700">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">A</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Assessment — Diagnosis</span>
                                </div>
                                <livewire:pages::transaksi.ri.coding-fhir-ri.diagnosa.coding-diagnosa-ri :riHdrNo="$riHdrNo"
                                    wire:key="coding-diagnosa-ri-{{ $riHdrNo }}-{{ $versi }}" />
                            </div>

                            {{-- P --}}
                            <div>
                                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg bg-rose-100 dark:border-rose-700">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full bg-rose-100 text-error dark:bg-rose-900/40 dark:text-rose-300">P</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Plan — Perencanaan</span>
                                </div>
                                <livewire:pages::transaksi.ri.coding-fhir-ri.perencanaan.coding-perencanaan-ri :riHdrNo="$riHdrNo"
                                    wire:key="coding-perencanaan-ri-{{ $riHdrNo }}-{{ $versi }}" />
                            </div>
                        </div>
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
