@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white dark:bg-gray-700', 'melayang' => false])

{{-- melayang = menu tidak terpotong wadah ber-overflow (tabel/kartu): posisi terhadap layar lewat
     x-anchor.fixed, otomatis membuka ke ATAS bila ruang bawah kurang (baris terbawah tabel), tinggi
     ikut ruang tersisa lewat x-melayang (resources/js/app.js). Opt-in — default perilaku lama. --}}
@php
    $alignmentClasses = match ($align) {
        'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
        'top' => 'origin-top',
        default => 'ltr:origin-top-right rtl:origin-top-left end-0',
    };

    $width = match ($width) {
        '48' => 'w-48',
        default => $width,
    };

    // Menu rata kiri pemicu → menempel di kiri; selain itu rata kanan (sama dengan end-0 lama).
    $posisiMelayang = $align === 'left' ? 'bottom-start' : 'bottom-end';
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div x-ref="pemicu" @click="open = ! open">
        {{ $trigger }}
    </div>

    @if ($melayang)
        {{-- wire:ignore.self: morph Livewire (mis. daftar ber-poll) tak boleh menghapus style posisi. --}}
        <div x-show="open" wire:ignore.self x-anchor.fixed.{{ $posisiMelayang }}.offset.8="$refs.pemicu"
            x-melayang="$refs.pemicu"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
            class="z-50 {{ $width }} rounded-md shadow-lg"
            style="display: none;" @click="open = false">
            <div class="rounded-md ring-1 ring-black ring-opacity-5 {{ $contentClasses }}">
                {{ $content }}
            </div>
        </div>
    @else
        <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-2 {{ $width }} rounded-md shadow-lg {{ $alignmentClasses }}"
            style="display: none;" @click="open = false">
            <div class="rounded-md ring-1 ring-black ring-opacity-5 {{ $contentClasses }}">
                {{ $content }}
            </div>
        </div>
    @endif
</div>
