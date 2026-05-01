<?php

/**
 * LOV Product Non-Medis (immst_productsnon).
 * Versi simplified dari lov-product:
 *   - Tabel: immst_productsnon (bukan immst_products)
 *   - Tanpa sales_price (kolom tidak ada di productsnon)
 *   - Tanpa join ke productcontents/contents (kandungan obat — medis-only)
 *   - Search by product_id (exact) atau product_name (LIKE)
 */

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $target = 'default';

    public string $label = 'Cari Barang';
    public string $placeholder = 'Ketik nama/kode barang non-medis...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    public ?array $selected = null;
    public ?string $initialProductId = null;
    public bool $readonly = false;

    public function mount(): void
    {
        if (!$this->initialProductId) return;

        $row = DB::table('immst_productsnon')
            ->select(['product_id', 'product_name', 'cost_price'])
            ->where('product_id', $this->initialProductId)
            ->where('active_status', '1')
            ->first();

        if ($row) {
            $this->selected = [
                'product_id'   => (string) $row->product_id,
                'product_name' => (string) ($row->product_name ?? ''),
                'sales_price'  => 0, // immst_productsnon tidak punya sales_price
                'cost_price'   => (int) ($row->cost_price ?? 0),
            ];
        }
    }

    public function updatedSearch(): void
    {
        if ($this->selected !== null) return;

        $keyword = trim($this->search);
        if (mb_strlen($keyword) < 2) {
            $this->closeAndResetList();
            return;
        }

        // ===== 1) exact match by product_id =====
        $exactRow = DB::table('immst_productsnon')
            ->select(['product_id', 'product_name', 'cost_price'])
            ->where('active_status', '1')
            ->where('product_id', $keyword)
            ->first();

        if ($exactRow) {
            $this->dispatchSelected([
                'product_id'   => (string) $exactRow->product_id,
                'product_name' => (string) ($exactRow->product_name ?? ''),
                'sales_price'  => 0,
                'cost_price'   => (int) ($exactRow->cost_price ?? 0),
            ]);
            return;
        }

        // ===== 2) search partial by id or name =====
        $upper = mb_strtoupper($keyword);
        $rows = DB::table('immst_productsnon')
            ->select(['product_id', 'product_name', 'cost_price'])
            ->where('active_status', '1')
            ->where(function ($q) use ($keyword, $upper) {
                $q->where('product_id', 'like', "%{$keyword}%")
                  ->orWhereRaw('UPPER(product_name) LIKE ?', ["%{$upper}%"]);
            })
            ->orderBy('product_name')
            ->limit(50)
            ->get();

        $this->options = $rows->map(function ($row) {
            $productId   = (string) $row->product_id;
            $productName = (string) ($row->product_name ?? '');
            $costPrice   = (int) ($row->cost_price ?? 0);
            $costFmt     = number_format($costPrice, 0, ',', '.');

            return [
                'product_id'   => $productId,
                'product_name' => $productName,
                'sales_price'  => 0,
                'cost_price'   => $costPrice,
                'label'        => $productName ?: '-',
                'hint'         => "ID: {$productId} • HPP: Rp {$costFmt}",
            ];
        })->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) $this->emitScroll();
    }

    public function clearSelected(): void
    {
        if ($this->readonly) return;
        $this->selected = null;
        $this->resetLov();
    }

    public function close(): void { $this->isOpen = false; }

    public function resetLov(): void
    {
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);
    }

    public function selectNext(): void
    {
        if (!$this->isOpen || count($this->options) === 0) return;
        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->emitScroll();
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || count($this->options) === 0) return;
        $this->selectedIndex--;
        if ($this->selectedIndex < 0) $this->selectedIndex = count($this->options) - 1;
        $this->emitScroll();
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) return;

        $this->dispatchSelected([
            'product_id'   => $this->options[$index]['product_id']   ?? '',
            'product_name' => $this->options[$index]['product_name'] ?? '',
            'sales_price'  => 0,
            'cost_price'   => (int) ($this->options[$index]['cost_price'] ?? 0),
        ]);
    }

    public function chooseHighlighted(): void { $this->choose($this->selectedIndex); }

    protected function closeAndResetList(): void
    {
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;
    }

    protected function dispatchSelected(array $payload): void
    {
        $this->selected = $payload;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $payload);
    }

    protected function emitScroll(): void
    {
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">
    <x-input-label :value="$label" />

    <div class="relative mt-1">
        @if ($selected === null)
            @if (!$readonly)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder" wire:model.live.debounce.250ms="search"
                    wire:keydown.escape.prevent="resetLov" wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            <div class="flex items-center gap-2">
                <x-text-input type="text" class="flex-1 block w-full" :value="$selected['product_name'] ?? ''" disabled />
                @if (!$readonly)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        @if ($isOpen && $selected === null && !$readonly)
            <div class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-product-non-{{ $option['product_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex flex-col">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $option['label'] ?? '-' }}
                                    </div>
                                    @if (!empty($option['hint']))
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $option['hint'] }}
                                        </div>
                                    @endif
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 2 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Data tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
