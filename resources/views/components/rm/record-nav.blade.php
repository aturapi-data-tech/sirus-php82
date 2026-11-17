@props([
    'pos' => 0,
    'total' => 0,
])

{{-- Navigasi antar-kunjungan (Prev/Next) di footer modal RM. --}}
{{-- wire:click terikat komponen pembungkus (harus punya navPrev()/navNext()). --}}
@if ($total > 1)
    {{-- Daftar urut DESC (terbaru di atas): tombol dibalik letaknya biar persepsinya --}}
    {{-- sama — "Berikutnya" (lebih baru) di kiri, "Sebelumnya" (lebih lama) di kanan. --}}
    {{-- Ikon mengikuti ARAH di layar (chevron kiri di tombol kiri), bukan nama tombolnya. --}}
    <div class="flex items-center gap-2">
        <x-ghost-button type="button" wire:click="navNext" wire:loading.attr="disabled" :disabled="$pos <= 1">
            <x-ikon nama="sebelumnya" />
            Berikutnya
        </x-ghost-button>
        <span class="px-1 text-sm font-semibold tabular-nums text-brand-green dark:text-brand-lime">{{ $pos }} / {{ $total }}</span>
        <x-ghost-button type="button" wire:click="navPrev" wire:loading.attr="disabled" :disabled="$pos >= $total">
            Sebelumnya
            <x-ikon nama="berikutnya" />
        </x-ghost-button>
    </div>
@else
    <div></div>
@endif
