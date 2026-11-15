<?php

use Livewire\Component;
use App\Http\Traits\SATUSEHAT\KfaTrait;

/**
 * LOV KFA — cari produk farmasi langsung ke API KFA v2 Kemkes (tanpa cache lokal).
 *
 * Pemakaian:
 *   <livewire:lov.kfa.lov-kfa target="masterObatKfa" label="Cari KFA dari Nama Obat" />
 *
 * Event: lov.selected.{target} → payload: [kfa_code, name, sediaan, zat_aktif, pabrik, aktif]
 * Setelah dipilih, kotak pencarian kembali kosong: nilai terpilih disimpan oleh pemanggil
 * (mis. isian Kode KFA / Nama KFA di Master Obat), bukan oleh LOV ini.
 */
new class extends Component {
    use KfaTrait;

    public string $target = 'default';
    public string $label = 'Cari KFA';
    public string $placeholder = 'Ketik nama obat / zat aktif, min. 3 huruf...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;
    public bool $pencarianGagal = false;
    public bool $disabled = false;

    public function updatedSearch(): void
    {
        $keyword = trim($this->search);
        if (mb_strlen($keyword) < 3) {
            $this->closeAndResetList();
            return;
        }

        $produkList = $this->cariProdukKfa($keyword, 15);
        $this->pencarianGagal = $produkList === null;
        $this->options = $produkList ?? [];

        // Tetap terbuka walau kosong/gagal: pesannya ada di dalam dropdown.
        $this->isOpen = true;
        $this->selectedIndex = 0;
        $this->dispatch('lov-scroll', id: $this->getId(), index: 0);
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) {
            return;
        }

        $payload = $this->options[$index];
        $this->resetLov();
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $payload);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function resetLov(): void
    {
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex', 'pencarianGagal']);
    }

    public function selectNext(): void
    {
        if (!$this->isOpen || count($this->options) === 0) {
            return;
        }
        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || count($this->options) === 0) {
            return;
        }
        $this->selectedIndex = ($this->selectedIndex - 1 + count($this->options)) % count($this->options);
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }

    protected function closeAndResetList(): void
    {
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;
        $this->pencarianGagal = false;
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">
    <x-input-label :value="$label" />

    <div class="relative mt-1">
        @if (!$disabled)
            <x-text-input type="text" class="block w-full" :placeholder="$placeholder"
                wire:model.live.debounce.600ms="search" wire:keydown.escape.prevent="resetLov"
                wire:keydown.arrow-down.prevent="selectNext" wire:keydown.arrow-up.prevent="selectPrevious"
                wire:keydown.enter.prevent="chooseHighlighted" autocomplete="off" />

            <div wire:loading wire:target="search"
                class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <x-loading />
            </div>
        @else
            <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                :placeholder="$placeholder" disabled />
        @endif

        @if ($isOpen && !$disabled)
            <div wire:ignore.self x-anchor.fixed.bottom-start.offset.8="$el.parentElement" x-melayang.lebar.daftar
                class="z-50 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-kfa-{{ $option['kfa_code'] }}-{{ $index }}" x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $option['name'] }}
                                    <span class="font-mono text-xs font-normal text-gray-600 dark:text-gray-400">({{ $option['kfa_code'] }})</span>
                                    @if (!$option['aktif'])
                                        <x-badge variant="warning">tidak aktif</x-badge>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                    {{ collect([$option['zat_aktif'], $option['sediaan'], $option['pabrik']])->filter()->implode(' · ') }}
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if ($pencarianGagal)
                    <div class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                        Server KFA tidak merespons. Coba lagi beberapa saat.
                    </div>
                @elseif (count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                        Produk KFA tidak ditemukan. Coba kata lain, mis. nama zat aktif.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
