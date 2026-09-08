@props([
    'id',
    'title',
    'date' => null,
    'sub' => null,
])

{{-- Baris entri dokumen di display Rekam Medis RI: tombol Lihat (buka modal) + Cetak (PDF). --}}
{{-- wire:click terikat ke komponen Livewire pembungkus (harus punya method lihat() & cetak()). --}}
<div class="flex items-center justify-between gap-3 py-2.5 border-b border-hairline-soft last:border-0 dark:border-gray-800">
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="text-base font-semibold text-ink dark:text-gray-200">{{ $title }}</span>
            @if (filled($date))
                <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-brand-green/10 text-brand-green dark:bg-brand-green/20 dark:text-brand-lime">{{ $date }}</span>
            @endif
        </div>
        @if (filled($sub))
            <div class="mt-0.5 text-sm text-muted">{{ $sub }}</div>
        @endif
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <x-lihat-button wire:click="lihat('{{ $id }}')" />
        <x-cetak-button wire:click="cetak('{{ $id }}')" />
    </div>
</div>
