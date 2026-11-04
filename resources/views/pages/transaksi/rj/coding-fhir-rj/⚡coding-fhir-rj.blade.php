<?php
// resources/views/pages/transaksi/rj/coding-fhir-rj/coding-fhir-rj.blade.php
//
// EMR RJ FHIR — host layar CODING SATUSEHAT (pola sama dengan host emr-rj): modal, header,
// identitas pasien, grid SOAP, tombol Simpan. Isi tiap kelompok ada di file sendiri:
//   anamnesa/coding-anamnesa-rj       S — keluhan utama, keluhan penyerta, alergi (+ coding)
//   pemeriksaan/coding-pemeriksaan-rj O — tanda vital & nutrisi (baca)
//   diagnosa/coding-diagnosa-rj       A — ICD-10 / ICD-9-CM (baca)
//   perencanaan/coding-perencanaan-rj P — terapi, tindak lanjut, cara keluar (baca)
//
// Form EMR RJ cukup teks klinis (fokus pelayanan); kode SNOMED dkk diisi di sini SESUDAH
// pelayanan oleh Dokter/Perawat/MR/manager (Gate emr.codingFhir) — keputusan user 2026-10-28.
// Lokasi data TIDAK berubah (key yang sama di datadaftarpolirj_json), jadi sender SATUSEHAT
// tidak berubah. Simpan = event 'save-coding-fhir-rj'; tiap kelompok yang punya kode menyimpan
// bagiannya sendiri (pola save-rm-* EMR). Tetap bisa diisi walau EMR terkunci.
//
// Dibuka dari titik-3 Daftar RJ: dispatch 'coding-fhir-rj.open' (rjNo) → modal sendiri.
// Pelayanan RJ memasangnya EMBEDDED di tab "Coding SATUSEHAT" modal gabungan emr-coding-rj
// (bersama EMR RJ, keputusan user 2026-10-28): tanpa x-modal/header/identitas, rjNo dari prop.
// Body + footer ada di coding-fhir-rj-isi.blade.php, di-@include kedua mode.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public ?string $rjNo = null;

    /** true = dipasang di tab Coding modal gabungan emr-coding-rj (lihat catatan atas). */
    public bool $embedded = false;

    /**
     * Kartu SATUSEHAT RJ dalam mode pratinjau, dikelompokkan seperti grid SOAP di atasnya (tanpa
     * encounter). Kartu yang SAMA dengan modal kirim, jadi yang tampil = yang akan dikirim.
     * Kunci = huruf SOAP => [judul, kelas header, kelas lingkaran, daftar kartu]. Pola sama dengan Coding RI.
     */
    public const KARTU_PRATINJAU = [
        'S' => ['Subjective — Anamnesa', 'bg-blue-100 dark:border-blue-700', 'text-blue-700 bg-blue-100 dark:bg-blue-900/40 dark:text-blue-300',
            ['chief-complaint', 'keluhan-penyerta', 'riwayat-penyakit', 'riwayat-keluarga', 'riwayat-pengobatan', 'riwayat-perjalanan', 'allergy', 'status-psikologis']],
        'O' => ['Objective — Pemeriksaan', 'bg-emerald-100 dark:border-emerald-700', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
            ['observation', 'nyeri-kesadaran', 'penilaian', 'lab', 'radiologi']],
        'A' => ['Assessment — Diagnosis', 'bg-amber-100 dark:border-amber-700', 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
            ['condition', 'procedure', 'clinical-impression']],
        'P' => ['Plan — Perencanaan', 'bg-rose-100 dark:border-rose-700', 'bg-rose-100 text-error dark:bg-rose-900/40 dark:text-rose-300',
            ['medication-request', 'medication-dispense', 'telaah-resep', 'prognosis', 'rencana-tindak-lanjut', 'kondisi-pulang', 'resume-medis']],
    ];

    /** Bagian "Yang akan dikirim" baru dimuat saat diminta — tiap kartu membaca data sendiri. */
    public bool $tampilkanKiriman = false;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-coding-fhir-rj'];

    public function mount(?string $rjNo = null, bool $embedded = false): void
    {
        $this->registerAreas(['modal-coding-fhir-rj']);
        $this->embedded = $embedded;
        if ($embedded && $rjNo && auth()->user()?->can('emr.codingFhir')) {
            $this->rjNo = $rjNo;
            $this->incrementVersion('modal-coding-fhir-rj');
        }
    }

    #[On('coding-fhir-rj.open')]
    public function openCoding(string $rjNo): void
    {
        // Mode embedded dibuka oleh pembungkus (emr-coding-rj), bukan event ini.
        if ($this->embedded) {
            return;
        }
        if (!auth()->user()?->can('emr.codingFhir')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak membuka Coding SATUSEHAT.');
            return;
        }

        $this->rjNo = $rjNo;
        $this->tampilkanKiriman = false;
        // Versi naik → semua kelompok di-mount ulang & membaca data terbaru dari prop rjNo.
        $this->incrementVersion('modal-coding-fhir-rj');
        $this->dispatch('open-modal', name: 'coding-fhir-rj');
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
        $this->dispatch('save-coding-fhir-rj', rjNo: (string) $this->rjNo);
    }

    public function closeModal(): void
    {
        $this->rjNo = null;
        $this->tampilkanKiriman = false;
        $this->dispatch('close-modal', name: 'coding-fhir-rj');
    }
};
?>

<div @class(['flex flex-col flex-1 min-h-0' => $embedded])>
    @if ($embedded)
        @if ($rjNo)
            @php $versi = $renderVersions['modal-coding-fhir-rj'] ?? 0; @endphp
            <div class="flex flex-col flex-1 min-h-0">
                @include('pages.transaksi.rj.coding-fhir-rj.coding-fhir-rj-isi')
            </div>
        @endif
    @else
    <x-modal name="coding-fhir-rj" size="full" height="full" focusable>
        {{-- Kelompok hanya di-mount saat ada pasien: tertutup = nol komponen. --}}
        @if ($rjNo)
            @php $versi = $renderVersions['modal-coding-fhir-rj'] ?? 0; @endphp

            <div class="flex flex-col min-h-0">
                <x-modul-dokumen.header judul="Coding SATUSEHAT" jalur="RJ"
                    ikon="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />

                {{-- DISPLAY PASIEN — sama dengan EMR RJ --}}
                <div class="px-6 pt-3">
                    <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                        wire:key="coding-fhir-rj-display-pasien-{{ $rjNo }}-{{ $versi }}" />
                </div>

                @include('pages.transaksi.rj.coding-fhir-rj.coding-fhir-rj-isi')
                <div class="hidden" x-on:coding-fhir-rj-tutup.window="$wire.closeModal()"></div>
            </div>
        @endif
    </x-modal>
    @endif
</div>
