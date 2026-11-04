<?php
// resources/views/pages/manajemen/rs/tu/gaji-dokter/gaji-dokter-lampiran.blade.php
//
// Lampiran rincian pasien — TAMPIL DI LAYAR, bukan diunduh sebagai PDF.
//
// Semula lampiran ini dicetak lewat dompdf. Dibatalkan 2026-08-02: dompdf harus
// mencocokkan seluruh build Tailwind (~188 KB) ke tiap elemen, dan lampiran
// berisi ratusan baris — satu dokter dengan 123 baris butuh 14 detik, dan
// sebelum batas waktunya dinaikkan malah berujung FatalError 60 detik. Tabel di
// layar tidak punya biaya itu, dan kalau tetap butuh kertas, Ctrl+P milik
// peramban mencetak apa yang terlihat.
//
// Versi cetak PDF-nya masih ada di riwayat git pada commit 7d2b9298 bila kelak
// dibutuhkan lagi.
//
// Mesin datanya tetap App\Support\GajiDokter\GajiDokterLampiran — yang berubah hanya cara
// menyajikannya.

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Support\GajiDokter\GajiDokterLampiran;

new class extends Component {
    /**
     * Nomor slip yang sedang ditampilkan. Selalu larik walau isinya satu —
     * tampilan satu dokter dan banyak dokter memakai jalur yang sama, jadi
     * tidak ada dua cabang yang bisa berbeda hasil.
     */
    public array $gajidoctorNoList = [];

    /** Judul modal ikut berubah supaya jelas sedang melihat satu atau semua. */
    public bool $modeSemua = false;

    /**
     * Kunci cache hasil barisMassal() selama modal terbuka. Kueri view
     * penggajian makan 1-2 detik dan hasilnya (ribuan baris) terlalu besar
     * untuk properti Livewire, jadi disimpan di cache BERKAS — bukan store
     * bawaan (database/Oracle) — lalu dibuang saat modal ditutup. Tiap buka
     * dapat kunci baru sehingga data selalu segar saat dibuka.
     */
    public string $cacheKey = '';

    /**
     * Komponen (dr_id|desc_doc) yang barisnya sengaja ditampilkan walau
     * melebihi BATAS_LIPAT. Komponen besar dilipat dulu supaya HTML awal
     * tidak berisi ribuan baris — dr. Predito Agustus 2026: 1.870 baris uang
     * periksa bernominal 0 yang tak seorang pun membacanya.
     */
    public array $komponenTerbuka = [];

    /** Komponen dengan baris lebih dari ini dilipat sampai diminta. */
    public const BATAS_LIPAT = 300;

    #[On('gaji.dokter.openLampiran')]
    public function openLampiran(int $gajidoctorNo): void
    {
        $this->siapkan([$gajidoctorNo], false);
    }

    #[On('gaji.dokter.openLampiranSemua')]
    public function openLampiranSemua(array $gajidoctorNoList): void
    {
        $this->siapkan($gajidoctorNoList, true);
    }

    protected function siapkan(array $gajidoctorNoList, bool $modeSemua): void
    {
        $this->lupakanCache();
        $this->gajidoctorNoList = array_values(array_filter(array_map('intval', $gajidoctorNoList)));
        $this->modeSemua = $modeSemua;
        $this->komponenTerbuka = [];
        $this->cacheKey = 'gaji-dokter-lampiran:' . Str::uuid();

        // Computed di-cache satu request; tanpa dibuang, membuka dokter kedua
        // akan menampilkan data dokter pertama.
        unset($this->dataLampiran);

        if ($this->dataLampiran->isEmpty()) {
            $this->lupakanCache();
            $this->gajidoctorNoList = [];
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada transaksi pasien pada periode ini.');
            return;
        }

        $this->dispatch('open-modal', name: 'gaji-dokter-lampiran');
    }

    /**
     * Tutup = kosongkan daftar slip, bukan sekadar menyembunyikan modal.
     * Dulu daftar dibiarkan terisi, sehingga request tutup mengulang kueri
     * view 1-2 detik dan merender ulang ribuan baris yang tidak akan dilihat.
     */
    public function closeModal(): void
    {
        $this->lupakanCache();
        $this->gajidoctorNoList = [];
        $this->komponenTerbuka = [];
        unset($this->dataLampiran);
        $this->dispatch('close-modal', name: 'gaji-dokter-lampiran');
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
     * Unduh isi lampiran yang sedang tampil sebagai CSV yang siap dibuka Excel.
     *
     * CSV, bukan .xlsx: proyek ini belum memuat PhpSpreadsheet sama sekali, dan
     * data di sini tabel datar tanpa rumus/format — menambah dependensi hanya
     * untuk itu tidak sepadan. Barisnya ditulis langsung ke keluaran, jadi mode
     * Semua (5.000+ baris) tidak menumpuk sebagai satu untai raksasa di memori.
     *
     * BENTUKNYA SENGAJA PIPIH, TIDAK MENIRU PENGELOMPOKAN DI LAYAR.
     * Di layar lampiran dikelompokkan per komponen berikut subtotalnya; kalau
     * itu disalin ke CSV, baris judul & subtotal menyelip di tengah data dan
     * merusak pivot — padahal mem-pivot itulah alasan orang membukanya di Excel.
     * Kolom Dokter & Periode ikut di SETIAP baris supaya ekspor banyak dokter
     * langsung bisa dipivot tanpa dirapikan dulu.
     */
    public function unduhCsv(): mixed
    {
        $data = $this->dataLampiran;

        if ($data->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada data untuk diunduh.');
            return null;
        }

        $pertama = $data->first()['header'];

        $namaBerkas = $this->modeSemua
            ? 'lampiran-pasien-' . $pertama->tahun_gaji . $pertama->bulan_gaji . '-semua.csv'
            : 'lampiran-pasien-' . $pertama->dr_id . '-' . $pertama->tahun_gaji . $pertama->bulan_gaji . '.csv';

        // Isi CSV ditulis GajiDokterLampiran::tulisCsv() — jalur yang sama dengan ZIP slip per dokter.
        return response()->streamDownload(function () use ($data) {
            $keluaran = fopen('php://output', 'w');
            GajiDokterLampiran::tulisCsv($keluaran, $data);
            fclose($keluaran);
        }, $namaBerkas, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Satu entri per dokter: header slip, detail slip, baris lampiran, grup kapita,
     * dan angka rekonsiliasinya.
     *
     * Transaksi ditarik lewat barisMassal() — sekali untuk semua dokter, bukan
     * sekali per dokter. Lihat catatan di GajiDokterLampiran.
     */
    #[Computed]
    public function dataLampiran()
    {
        if ($this->gajidoctorNoList === []) {
            return collect();
        }

        if ($this->cacheKey !== '') {
            $tersimpan = Cache::store('file')->get($this->cacheKey);
            if ($tersimpan !== null) {
                return $tersimpan;
            }
        }

        $data = GajiDokterLampiran::susun($this->gajidoctorNoList);

        if ($this->cacheKey !== '' && $data->isNotEmpty()) {
            Cache::store('file')->put($this->cacheKey, $data, now()->addMinutes(30));
        }

        return $data;
    }
};
?>

<div>
    <x-modal name="gaji-dokter-lampiran" size="full" height="full" focusable>
        <div class="flex flex-col h-full">

            {{-- HEADER MODAL --}}
            <x-modul-dokumen.header judul="Lampiran Rincian Pasien" ikon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                @if ($this->modeSemua)
                    {{ $this->dataLampiran->count() }} dokter
                @else
                    {{ $this->dataLampiran->first()['header']->dr_name ?? '' }}
                @endif
            </x-modul-dokumen.header>

            {{-- BODY --}}
            <div class="flex-1 px-4 pt-2 pb-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                {{-- Tertutup = tidak merender apa pun (daftar slip dikosongkan di closeModal). --}}
                @if ($gajidoctorNoList)
                    @include('pages.components.manajemen.gaji-dokter-lampiran.isi-lampiran-layar', [
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

                    {{-- Unduh mengekspor APA YANG SEDANG TAMPIL — satu dokter atau
                         semua, mengikuti cara modal ini dibuka. Tidak ada tombol
                         unduh terpisah di daftar supaya isi berkas tidak pernah
                         bisa berbeda dari yang barusan dilihat. --}}
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
