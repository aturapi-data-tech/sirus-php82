@props([
'title' => '',
'align' => 'start',
'bgcolor' => 'bg-canvas',
'class' => '',
'titleClass' => '',
'padding' => 'p-4',
'collapsible' => false,
'open' => true,
])

@php
// Set alignment class
$alignClass = match($align) {
'center' => 'text-center',
'end' => 'text-right',
'right' => 'text-right',
default => 'text-left',
};
@endphp

{{-- Model kartu outline standar UI ("Input teks & pilihan"):
     latar canvas + border hairline, judul = uppercase hijau brand + garis aksen kiri (ds-form-title),
     di atas, TANPA header bar terisi. Hijau membedakan judul bingkai dari label isian (hitam).
     Collapsible: panah di kiri judul, baris judul ber-hover, dan "klik untuk membuka" saat tertutup —
     dulu panahnya kecil di ujung kanan sehingga judul dikira label biasa. --}}
<div {{ $attributes->merge(['class' => "border border-hairline rounded-2xl shadow-sm dark:border-gray-700 {$bgcolor} dark:bg-gray-900 {$class}"]) }}
    @if($collapsible) x-data="{ open: @js($open) }" @endif>
    <div class="{{ $padding }}">
        @if($title)
            @if($collapsible)
                <button type="button" @click="open = !open" :aria-expanded="open"
                    class="flex items-center w-full gap-2 px-2 py-1.5 mb-4 rounded-lg cursor-pointer transition-colors hover:bg-brand-green/10 dark:hover:bg-brand-lime/10">
                    <span class="ds-form-title inline-flex items-center gap-1.5 {{ $alignClass }} {{ $titleClass }}">
                        <svg class="w-4 h-4 transition-transform shrink-0" :class="open ? 'rotate-90' : ''"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                        {{ $title }}
                    </span>
                    <span x-show="!open" class="ml-auto text-xs text-gray-600 dark:text-gray-400">klik untuk membuka</span>
                </button>
                <div x-show="open" x-collapse>
                    {{ $slot }}
                </div>
            @else
                <div class="ds-form-title mb-4 {{ $alignClass }} {{ $titleClass }}">{{ $title }}</div>
                {{ $slot }}
            @endif
        @else
            {{ $slot }}
        @endif
    </div>
</div>
