{{--
    Pratinjau PDF dengan zoom, rotasi, cetak & unduh — pasangan <x-pratinjau-gambar> di modal
    hasil penunjang (EMR RJ/UGD/RI + Riwayat Upload). Render pakai pdf.js (Alpine.data
    'pratinjauPdf' di resources/js/app.js, lazy-load) supaya toolbar seragam di semua browser.

    src: data URI base64 atau URL route files.show.
    Pakai: <x-pratinjau-pdf :src="$viewFilePDF" />
--}}
@props(['src', 'namaFile' => 'hasil-penunjang.pdf'])

<div x-data="pratinjauPdf({ src: @js($src), namaFile: @js($namaFile) })"
    x-on:resize.window.debounce.200ms="render()" class="flex flex-col w-full h-full">

    {{-- Toolbar --}}
    <div
        class="flex flex-wrap items-center justify-center gap-2 px-4 py-2 border-b shrink-0 bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
        <x-icon-button color="gray" x-on:click="perkecil()" title="Perkecil">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
            </svg>
        </x-icon-button>
        <span class="w-14 text-sm text-center tabular-nums text-muted" x-text="Math.round(zoom * 100) + '%'"></span>
        <x-icon-button color="gray" x-on:click="perbesar()" title="Perbesar">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
        </x-icon-button>

        <span class="w-px h-6 mx-1 bg-hairline dark:bg-gray-700"></span>

        <x-icon-button color="gray" x-on:click="putar(-90)" title="Putar ke kiri">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
            </svg>
        </x-icon-button>
        <x-icon-button color="gray" x-on:click="putar(90)" title="Putar ke kanan">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 10H11a8 8 0 00-8 8v2m18-10l-6 6m6-6l-6-6" />
            </svg>
        </x-icon-button>

        <span class="w-px h-6 mx-1 bg-hairline dark:bg-gray-700"></span>

        <x-secondary-button type="button" class="!px-3 !py-1.5" x-on:click="pasLebar()">
            Pas Lebar
        </x-secondary-button>

        <span class="w-px h-6 mx-1 bg-hairline dark:bg-gray-700"></span>

        <x-icon-button color="gray" x-on:click="cetak()" title="Cetak">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
        </x-icon-button>
        <x-icon-button color="gray" x-on:click="unduh()" title="Unduh">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
        </x-icon-button>

        <span class="text-sm text-muted" x-show="jumlahHalaman" x-text="jumlahHalaman + ' halaman'"></span>
    </div>

    {{-- Area halaman — Ctrl + scroll untuk zoom --}}
    <div x-ref="area" class="relative flex-1 min-h-0 overflow-auto" style="overscroll-behavior: contain"
        x-on:wheel.ctrl.prevent="$event.deltaY < 0 ? perbesar() : perkecil()">
        <div x-show="memuat" class="py-10 text-sm text-center text-muted">Memuat PDF…</div>
        <div x-show="galat" x-cloak class="py-10 text-sm text-center text-red-600" x-text="galat"></div>
        {{-- Kanvas diisi pdf.js; wire:ignore supaya morph Livewire tak menghapusnya --}}
        <div x-ref="halaman" wire:ignore style="padding:16px;width:max-content;min-width:100%;box-sizing:border-box"></div>
    </div>
</div>
