<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Obat';
    public string $placeholder = 'Ketik nama/kode obat...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /**
     * Struktur payload yang akan dikirim ke parent:
     * [
     *   'product_id' => '...',
     *   'product_name' => '...',
     *   'sales_price' => 0,
     * ]
     */

    public function updatedSearch(): void
    {
        $keyword = trim($this->search);

        // minimal 2 char
        if (mb_strlen($keyword) < 2) {
            $this->closeAndResetList();
            return;
        }

        // 1) exact match by product_id (kalau numeric / id style)
        //   - ini meniru behavior trait lama kamu: kalau ketemu, langsung set dan reset LOV
        $exactRow = DB::table('immst_products')->select('product_id', 'product_name', 'sales_price')->where('active_status', '1')->where('product_id', $keyword)->first();

        if ($exactRow) {
            $this->dispatchSelected([
                'product_id' => (string) $exactRow->product_id,
                'product_name' => (string) $exactRow->product_name,
                'sales_price' => (int) ($exactRow->sales_price ?? 0),
            ]);
            return;
        }

        // 2) search by name (dan juga boleh by id partial jika kamu mau)
        $upperKeyword = mb_strtoupper($keyword);

        // NOTE: query kamu sebelumnya ada string_agg konten; aku sederhanakan dulu biar stabil.
        // Kalau kamu mau konten/product_content balik lagi, bilang aja nanti aku sambungkan.
        $rows = DB::table('immst_products')
            ->select('product_id', 'product_name', 'sales_price')
            ->where('active_status', '1')
            ->where(function ($query) use ($keyword, $upperKeyword) {
                // bila user mengetik angka, izinkan cari id mengandung
                if (ctype_digit($keyword)) {
                    $query->orWhere('product_id', 'like', "%{$keyword}%");
                }

                $query->orWhereRaw('UPPER(product_name) LIKE ?', ["%{$upperKeyword}%"]);
            })
            ->orderBy('product_name')
            ->limit(50)
            ->get();

        $this->options = $rows
            ->map(
                fn($row) => [
                    'product_id' => (string) $row->product_id,
                    'product_name' => (string) $row->product_name,
                    'sales_price' => (int) ($row->sales_price ?? 0),

                    // untuk tampilan dropdown (standard base)
                    'label' => (string) $row->product_name,
                    'hint' => (string) $row->product_id . ' • ' . number_format((int) ($row->sales_price ?? 0)),
                ],
            )
            ->toArray();
        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function resetLov(): void
    {
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);
    }

    public function selectNext(): void
    {
        if (!$this->isOpen || count($this->options) === 0) {
            return;
        }
        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->emitScroll();
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || count($this->options) === 0) {
            return;
        }

        $this->selectedIndex--;
        if ($this->selectedIndex < 0) {
            $this->selectedIndex = count($this->options) - 1;
        }
        $this->emitScroll();
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) {
            return;
        }

        $payload = [
            'product_id' => $this->options[$index]['product_id'] ?? '',
            'product_name' => $this->options[$index]['product_name'] ?? '',
            'sales_price' => (int) ($this->options[$index]['sales_price'] ?? 0),
        ];

        $this->dispatchSelected($payload);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    /* -------------------------
     | helpers
     * ------------------------- */

    protected function closeAndResetList(): void
    {
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;
    }

    protected function dispatchSelected(array $payload): void
    {
        $this->dispatch('lov.selected', target: $this->target, payload: $payload);
        $this->resetLov();
    }

    protected function emitScroll(): void
    {
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex">
    <x-input-label :value="$label" />

    <div class="relative mt-1">
        <x-text-input type="text" class="block w-full" :placeholder="$placeholder" wire:model.live.debounce.250ms="search"
            wire:keydown.escape.prevent="resetLov" wire:keydown.arrow-down.prevent="selectNext"
            wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted" />

        @if ($isOpen)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-product-{{ $option['product_id'] }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $option['label'] ?? '-' }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $option['hint'] ?? '' }}
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
