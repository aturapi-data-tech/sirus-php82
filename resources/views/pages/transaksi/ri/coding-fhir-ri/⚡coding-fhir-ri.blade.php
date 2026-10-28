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
// Dibuka dari titik-3 Daftar RI: dispatch 'coding-fhir-ri.open' (riHdrNo) — ditangkap modal gabungan
// emr-coding-ri yang memasang komponen ini EMBEDDED di tab "Coding SATUSEHAT" (tanpa modal/header/identitas).
// Mode modal sendiri tetap ada. Body + footer ada di coding-fhir-ri-isi.blade.php, di-@include kedua mode.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public ?string $riHdrNo = null;

    /** true = dipasang di tab Coding modal gabungan emr-coding-ri (lihat catatan atas). */
    public bool $embedded = false;

    /**
     * Kartu SATUSEHAT RI dalam mode pratinjau, dikelompokkan seperti grid SOAP di atasnya (tanpa
     * encounter/episode). Kartu yang SAMA dengan modal kirim, jadi yang tampil = yang akan dikirim.
     * Kunci = huruf SOAP => [judul, kelas header, kelas lingkaran, daftar kartu].
     */
    public const KARTU_PRATINJAU = [
        'S' => ['Subjective — Anamnesa', 'bg-blue-100 dark:border-blue-700', 'text-blue-700 bg-blue-100 dark:bg-blue-900/40 dark:text-blue-300',
            ['chief-complaint', 'keluhan-penyerta', 'riwayat-penyakit', 'riwayat-keluarga', 'riwayat-pengobatan', 'riwayat-perjalanan', 'allergy', 'status-psikologis']],
        'O' => ['Objective — Pemeriksaan', 'bg-emerald-100 dark:border-emerald-700', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
            ['observation', 'observasi-lanjutan', 'penilaian', 'lab', 'radiologi']],
        'A' => ['Assessment — Diagnosis', 'bg-amber-100 dark:border-amber-700', 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
            ['condition', 'procedure', 'cppt']],
        'P' => ['Plan — Perencanaan', 'bg-rose-100 dark:border-rose-700', 'bg-rose-100 text-error dark:bg-rose-900/40 dark:text-rose-300',
            ['medication-request', 'medication-dispense', 'diet', 'edukasi', 'perencanaan-pulang', 'prognosis', 'rencana-tindak-lanjut', 'kondisi-pulang', 'resume-medis']],
    ];

    /** Bagian "Yang akan dikirim" baru dimuat saat diminta — 25 kartu masing-masing membaca data sendiri. */
    public bool $tampilkanKiriman = false;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-coding-fhir-ri'];

    public function mount(?string $riHdrNo = null, bool $embedded = false): void
    {
        $this->registerAreas(['modal-coding-fhir-ri']);
        $this->embedded = $embedded;
        if ($embedded && $riHdrNo && auth()->user()?->can('emr.codingFhir')) {
            $this->riHdrNo = $riHdrNo;
            $this->incrementVersion('modal-coding-fhir-ri');
        }
    }

    #[On('coding-fhir-ri.open')]
    public function openCoding(string $riHdrNo): void
    {
        // Mode embedded dibuka oleh pembungkus (emr-coding-ri), bukan event ini.
        if ($this->embedded) {
            return;
        }
        if (!auth()->user()?->can('emr.codingFhir')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak membuka Coding SATUSEHAT.');
            return;
        }

        $this->riHdrNo = $riHdrNo;
        $this->tampilkanKiriman = false;
        // Versi naik → semua kelompok di-mount ulang & membaca data terbaru dari prop riHdrNo.
        $this->incrementVersion('modal-coding-fhir-ri');
        $this->dispatch('open-modal', name: 'coding-fhir-ri');
    }

    public function toggleKiriman(): void
    {
        $this->tampilkanKiriman = !$this->tampilkanKiriman;
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
        $this->tampilkanKiriman = false;
        $this->dispatch('close-modal', name: 'coding-fhir-ri');
    }
};
?>

<div @class(['flex flex-col flex-1 min-h-0' => $embedded])>
    @if ($embedded)
        @if ($riHdrNo)
            @php $versi = $renderVersions['modal-coding-fhir-ri'] ?? 0; @endphp
            <div class="flex flex-col flex-1 min-h-0">
                @include('pages.transaksi.ri.coding-fhir-ri.coding-fhir-ri-isi')
            </div>
        @endif
    @else
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

                @include('pages.transaksi.ri.coding-fhir-ri.coding-fhir-ri-isi')
                <div class="hidden" x-on:coding-fhir-ri-tutup.window="$wire.closeModal()"></div>
            </div>
        @endif
    </x-modal>
    @endif
</div>
