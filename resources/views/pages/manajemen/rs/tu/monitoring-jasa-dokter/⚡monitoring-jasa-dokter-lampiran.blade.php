<?php
// resources/views/pages/manajemen/rs/tu/monitoring-jasa-dokter/monitoring-jasa-dokter-lampiran.blade.php
//
// Rincian baris pasien — TAMPIL DI LAYAR, bukan diunduh sebagai PDF.
//
// Turunan modal lampiran slip gaji (2026-08). Yang berubah sejak penggajian pindah
// ke SIAP: kuncinya DR_ID + bulan jasa (bukan nomor slip), tidak ada rekonsiliasi
// dengan slip, dan tidak ada penandaan grup kapita — tarif kapita kini disetel di
// SIAP, SIRUS tidak tahu dokter mana yang dibayar per kepala.
//
// Versi cetak PDF lampiran ada di riwayat git pada commit 7d2b9298 bila kelak
// dibutuhkan; dibatalkan karena dompdf 14 detik untuk satu dokter.
//
// Mesin datanya App\Support\GajiDokter\GajiDokterLampiran::susunPerDokter —
// baris yang SAMA dengan yang dikirim ke SIAP lewat /api/siap/.../transaksi.

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Support\GajiDokter\GajiDokterLampiran;

new class extends Component {
    /** DR_ID yang sedang ditampilkan. Selalu larik walau isinya satu — satu & semua memakai jalur yang sama. */
    public array $drIdList = [];

    public string $tahunJasa = '';

    public string $bulanJasa = '';

    /** Judul modal ikut berubah supaya jelas sedang melihat satu atau semua. */
    public bool $modeSemua = false;

    /**
     * Kunci cache hasil susunPerDokter() selama modal terbuka. Kueri view jasa
     * makan 1-2 detik dan hasilnya (ribuan baris) terlalu besar untuk properti
     * Livewire, jadi disimpan di cache BERKAS — bukan store bawaan — lalu dibuang
     * saat modal ditutup. Tiap buka dapat kunci baru sehingga data selalu segar.
     */
    public string $cacheKey = '';

    /**
     * Komponen (dr_id|desc_doc) yang barisnya sengaja ditampilkan walau melebihi
     * BATAS_LIPAT. Komponen besar dilipat dulu supaya HTML awal tidak berisi
     * ribuan baris uang periksa yang tak seorang pun membacanya.
     */
    public array $komponenTerbuka = [];

    /** Komponen dengan baris lebih dari ini dilipat sampai diminta. */
    public const BATAS_LIPAT = 300;

    #[On('jasa.dokter.openRincian')]
    public function openRincian(array $drIdList, string $tahunJasa, string $bulanJasa, bool $modeSemua = false): void
    {
        $this->lupakanCache();
        $this->drIdList = array_values(array_filter(array_map(fn ($drId) => trim((string) $drId), $drIdList), fn ($drId) => $drId !== ''));
        $this->tahunJasa = $tahunJasa;
        $this->bulanJasa = $bulanJasa;
        $this->modeSemua = $modeSemua;
        $this->komponenTerbuka = [];
        $this->cacheKey = 'monitoring-jasa-dokter-lampiran:' . Str::uuid();

        // Computed di-cache satu request; tanpa dibuang, membuka dokter kedua
        // akan menampilkan data dokter pertama.
        unset($this->dataLampiran);

        if ($this->dataLampiran->isEmpty()) {
            $this->lupakanCache();
            $this->drIdList = [];
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada transaksi pasien pada periode ini.');
            return;
        }

        $this->dispatch('open-modal', name: 'monitoring-jasa-dokter-lampiran');
    }

    /** Tutup = kosongkan daftar, bukan sekadar menyembunyikan modal — supaya request tutup tidak mengulang kueri 1-2 detik. */
    public function closeModal(): void
    {
        $this->lupakanCache();
        $this->drIdList = [];
        $this->komponenTerbuka = [];
        unset($this->dataLampiran);
        $this->dispatch('close-modal', name: 'monitoring-jasa-dokter-lampiran');
    }

    public function toggleKomponen(string $kunci): void
    {
        if (in_array($kunci, $this->komponenTerbuka, true)) {
            $this->komponenTerbuka = array_values(array_diff($this->komponenTerbuka, [$kunci]));
        } else {
            $this->komponenTerbuka[] = $kunci;
        }
    }

    protected function lupakanCache(): void
    {
        if ($this->cacheKey !== '') {
            Cache::store('file')->forget($this->cacheKey);
            $this->cacheKey = '';
        }
    }

    /**
     * Unduh isi yang sedang tampil sebagai CSV siap Excel — pipih, satu baris per
     * transaksi pasien, kolom Dokter & Periode di setiap baris supaya langsung
     * bisa dipivot. Penulisnya GajiDokterLampiran::tulisCsv, sama dengan yang
     * dulu dipakai ZIP slip.
     */
    public function unduhCsv(): mixed
    {
        $data = $this->dataLampiran;

        if ($data->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada data untuk diunduh.');
            return null;
        }

        $namaBerkas = $this->modeSemua
            ? 'rincian-jasa-dokter-' . $this->tahunJasa . $this->bulanJasa . '-semua.csv'
            : 'rincian-jasa-dokter-' . $data->first()['header']->dr_id . '-' . $this->tahunJasa . $this->bulanJasa . '.csv';

        return response()->streamDownload(function () use ($data) {
            $keluaran = fopen('php://output', 'w');
            GajiDokterLampiran::tulisCsv($keluaran, $data);
            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Satu entri per dokter: header (identitas + periode), baris pasien, perKomponen, total. */
    #[Computed]
    public function dataLampiran()
    {
        if ($this->drIdList === []) {
            return collect();
        }

        if ($this->cacheKey !== '') {
            $tersimpan = Cache::store('file')->get($this->cacheKey);
            if ($tersimpan !== null) {
                return $tersimpan;
            }
        }

        $data = GajiDokterLampiran::susunPerDokter($this->drIdList, $this->tahunJasa, $this->bulanJasa);

        if ($this->cacheKey !== '' && $data->isNotEmpty()) {
            Cache::store('file')->put($this->cacheKey, $data, now()->addMinutes(30));
        }

        return $data;
    }
};
?>

<div>
    <x-modal name="monitoring-jasa-dokter-lampiran" size="full" height="full" focusable>
        <div class="flex flex-col h-full">

            {{-- HEADER MODAL --}}
            <x-modul-dokumen.header judul="Rincian Transaksi Jasa Dokter" ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                @if ($this->modeSemua)
                    {{ $this->dataLampiran->count() }} dokter
                @else
                    {{ $this->dataLampiran->first()['header']->dr_name ?? '' }}
                @endif
            </x-modul-dokumen.header>

            {{-- BODY --}}
            <div class="flex-1 px-4 pt-2 pb-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                {{-- Tertutup = tidak merender apa pun (daftar dikosongkan di closeModal). --}}
                @if ($drIdList)
                    @include('pages.components.manajemen.monitoring-jasa-dokter.isi-rincian-layar', [
                        'dataLampiran' => $this->dataLampiran,
                        'komponenTerbuka' => $komponenTerbuka,
                        'batasLipat' => self::BATAS_LIPAT,
                    ])
                @endif
            </div>

            {{-- FOOTER --}}
            <div class="px-6 py-3 border-t border-hairline bg-surface-soft dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-2">
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>

                    {{-- Unduh mengekspor APA YANG SEDANG TAMPIL — satu dokter atau semua,
                         mengikuti cara modal ini dibuka — supaya isi berkas tidak pernah
                         berbeda dari yang barusan dilihat. --}}
                    <x-info-button type="button" class="gap-2" wire:click="unduhCsv"
                        wire:loading.attr="disabled" wire:target="unduhCsv"
                        title="Unduh sebagai CSV — siap dibuka di Excel">
                        <span wire:loading.remove wire:target="unduhCsv" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Unduh Excel
                        </span>
                        <span wire:loading wire:target="unduhCsv" class="inline-flex items-center gap-2">
                            <x-loading /> Menyiapkan...
                        </span>
                    </x-info-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
