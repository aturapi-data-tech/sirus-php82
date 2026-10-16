@props([
    'name',
    'title',
    'subtitle' => null,
    'cetakId' => null,
    // showCetak null = otomatis (tampil bila cetakId terisi). Set eksplisit true/false
    // untuk dokumen objek-tunggal yg method cetak()-nya tak butuh id.
    'showCetak' => null,
    // HTML dokumen cetak yg sudah di-render (self-contained). Bila diisi, ditampilkan
    // dalam iframe → isi modal Lihat = persis tampilan cetak. Kalau null, pakai slot.
    'previewHtml' => null,
    // Navigasi antar-record (Prev/Next) — diisi komponen list; >1 → tombol muncul.
    'navTotal' => 0,
    'navPos' => 0,
])

{{-- Shell modal viewer read-only dokumen Rekam Medis RI. --}}
{{-- Tutup via Alpine (tanpa roundtrip). Cetak terikat method cetak() komponen pembungkus. --}}
<x-modal :name="$name" size="full" height="full" focusable>
    <div class="flex flex-col h-full">
        {{-- Header --}}
        <x-modul-dokumen.header :judul="$title" ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" :namaModal="$name">
            @if (filled($subtitle))
                {{ $subtitle }}
            @endif
        </x-modul-dokumen.header>

        {{-- Body --}}
        @if (filled($previewHtml))
            {{-- Preview dokumen cetak (iframe self-contained) — isi = persis tampilan Cetak PDF. --}}
            <div class="flex-1 min-h-0 p-3 bg-gray-100 dark:bg-gray-950/40">
                <iframe srcdoc="{{ $previewHtml }}" class="w-full h-full bg-white border border-hairline rounded-lg shadow-sm dark:border-gray-700"
                    title="Preview {{ $title }}"></iframe>
            </div>
        @else
            <div class="flex-1 min-h-0 px-6 py-4 overflow-y-auto text-base">
                <div class="max-w-4xl mx-auto">
                    {{ $slot }}
                </div>
            </div>
        @endif

        {{-- Footer --}}
        <div class="flex items-center justify-between gap-3 px-6 py-3 border-t border-hairline dark:border-gray-700">
            {{-- Navigasi antar-record (tanpa buka-tutup modal) --}}
            <div class="flex items-center gap-1.5">
                @if ($navTotal > 1)
                    <x-secondary-button type="button" wire:click="prevRecord" wire:loading.attr="disabled" :disabled="$navPos <= 1" class="gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                        Sebelumnya
                    </x-secondary-button>
                    <span class="px-2 text-sm font-medium tabular-nums text-muted">{{ $navPos }} / {{ $navTotal }}</span>
                    <x-secondary-button type="button" wire:click="nextRecord" wire:loading.attr="disabled" :disabled="$navPos >= $navTotal" class="gap-1">
                        Berikutnya
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </x-secondary-button>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: '{{ $name }}' })">Tutup</x-secondary-button>
                @if ($showCetak ?? filled($cetakId))
                <x-cetak-button wire:click="cetak('{{ $cetakId }}')" label="Cetak PDF" />
                @endif
            </div>
        </div>
    </div>
</x-modal>
