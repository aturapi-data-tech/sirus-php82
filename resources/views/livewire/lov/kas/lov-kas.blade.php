<?php

/**
 * resources/views/livewire/lov/kas/lov-kas.blade.php
 *
 * LOV Akun Kas — pakai user_kas (bukan smmst_kases).
 *
 * Payload dispatch ke parent:
 *   [
 *     'acc_id'   => '...',
 *     'acc_name' => '...',
 *     'emp_id'   => '...',   // langsung dari users.emp_id auth user
 *     'tipe_rj'  => true|false,
 *     'tipe_ugd' => true|false,
 *     'tipe_ri'  => true|false,
 *   ]
 *
 * Di parent, tidak perlu query emp_id lagi:
 *   #[On('lov.selected.kas-kasir-rj')]
 *   public function onKasSelected(string $target, ?array $payload): void
 *   {
 *       $this->accId  = $payload['acc_id']  ?? null;
 *       $this->empId  = $payload['emp_id']  ?? null;  // <-- langsung pakai
 *   }
 */

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    public string $target = 'default';

    /**
     * Filter tipe kas: 'rj' | 'ugd' | 'ri' | '' (semua)
     */
    public string $tipe = 'rj';

    public string $label = 'Akun Kas';
    public string $placeholder = 'Ketik kode/nama kas...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;
    public ?array $selected = null;

    #[Reactive]
    public ?string $initialAccId = null;

    public bool $disabled = false;

    /* ── Lifecycle ── */

    public function mount(): void
    {
        if ($this->initialAccId) {
            $this->loadSelected($this->initialAccId);
        }
    }

    public function updatedInitialAccId(?string $value): void
    {
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        if (!empty($value)) {
            $this->loadSelected($value);
        }
    }

    /* ── Load mode edit ── */

    protected function loadSelected(string $accId): void
    {
        $row = $this->baseQuery()->where('a.acc_id', $accId)->first();

        if ($row) {
            $this->selected = $this->buildPayload($row);
        }
    }

    /* ── Pencarian real-time ── */

    public function updatedSearch(): void
    {
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        if (mb_strlen($keyword) < 1) {
            $this->closeAndResetList();
            return;
        }

        $upperKeyword = mb_strtoupper($keyword);

        // Exact match → langsung pilih tanpa dropdown
        $exactRow = $this->baseQuery()->where('a.acc_id', $keyword)->first();

        if ($exactRow) {
            $this->dispatchSelected($this->buildPayload($exactRow));
            return;
        }

        // Partial match
        $rows = $this->baseQuery()->where(fn($q) => $q->where('a.acc_id', 'like', "%{$keyword}%")->orWhereRaw('UPPER(a.acc_name) LIKE ?', ["%{$upperKeyword}%"]))->orderBy('a.acc_id')->limit(50)->get();

        $this->options = $rows->map(fn($row) => array_merge($this->buildPayload($row), ['label' => (string) ($row->acc_name ?: $row->acc_id)]))->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    /* ── Query dasar — DRY ── */

    protected function baseQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('acmst_accounts as a')
            ->join('acmst_kases as b', 'a.acc_id', '=', 'b.acc_id')
            ->select('a.acc_id', 'a.acc_name', 'b.rj', 'b.ugd', 'b.ri')
            ->when($this->tipe !== '', fn($q) => $q->where('b.' . $this->tipe, '1'))
            ->whereIn(
                'a.acc_id',
                fn($q) => $q
                    ->select('acc_id')
                    ->from('user_kas')
                    ->where('user_id', auth()->id()),
            );
    }

    /* ── Build payload ── */

    /**
     * emp_id diambil dari auth()->user()->emp_id (kolom baru di tabel users).
     * Parent tidak perlu query emp_id lagi ke Oracle.
     */
    protected function buildPayload(object $row): array
    {
        return [
            'acc_id' => (string) $row->acc_id,
            'acc_name' => (string) ($row->acc_name ?? ''),
            'emp_id' => (string) (auth()->user()->emp_id ?? ''),
            'tipe_rj' => ($row->rj ?? '') === '1',
            'tipe_ugd' => ($row->ugd ?? '') === '1',
            'tipe_ri' => ($row->ri ?? '') === '1',
        ];
    }

    /* ── Navigasi ── */

    public function clearSelected(): void
    {
        if ($this->disabled) {
            return;
        }
        $this->selected = null;
        $this->resetLov();
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: null);
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
        if (!$this->isOpen || !count($this->options)) {
            return;
        }
        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->emitScroll();
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || !count($this->options)) {
            return;
        }
        if (--$this->selectedIndex < 0) {
            $this->selectedIndex = count($this->options) - 1;
        }
        $this->emitScroll();
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) {
            return;
        }
        $this->dispatchSelected($this->options[$index]);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    /* ── Helpers ── */

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
            @if (!$disabled)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder" wire:model.live.debounce.250ms="search"
                    wire:keydown.escape.prevent="resetLov" wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full" :value="$selected['acc_id'] . ' — ' . $selected['acc_name']" disabled />
                </div>
                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>

            {{-- Info subtle: emp_id + badge tipe --}}
            <div class="flex items-center gap-1.5 mt-1">
                @if (!empty($selected['emp_id']))
                    <span class="text-[10px] font-mono text-gray-400 dark:text-gray-600">
                        emp: {{ $selected['emp_id'] }}
                    </span>
                    <span class="text-gray-300 dark:text-gray-700">·</span>
                @endif
                @if (!empty($selected['tipe_rj']))
                    <span
                        class="px-1.5 text-[10px] font-semibold rounded bg-blue-100 text-blue-500 dark:bg-blue-900/30 dark:text-blue-400">RJ</span>
                @endif
                @if (!empty($selected['tipe_ugd']))
                    <span
                        class="px-1.5 text-[10px] font-semibold rounded bg-rose-100 text-rose-500 dark:bg-rose-900/30 dark:text-rose-400">UGD</span>
                @endif
                @if (!empty($selected['tipe_ri']))
                    <span
                        class="px-1.5 text-[10px] font-semibold rounded bg-violet-100 text-violet-500 dark:bg-violet-900/30 dark:text-violet-400">RI</span>
                @endif
            </div>
        @endif

        {{-- Dropdown --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-kas-{{ $option['acc_id'] }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $option['label'] }}
                                    </span>
                                    <div class="flex gap-1 shrink-0">
                                        @if (!empty($option['tipe_rj']))
                                            <span
                                                class="px-1.5 text-[10px] font-semibold rounded bg-blue-100 text-blue-500 dark:bg-blue-900/30 dark:text-blue-400">RJ</span>
                                        @endif
                                        @if (!empty($option['tipe_ugd']))
                                            <span
                                                class="px-1.5 text-[10px] font-semibold rounded bg-rose-100 text-rose-500 dark:bg-rose-900/30 dark:text-rose-400">UGD</span>
                                        @endif
                                        @if (!empty($option['tipe_ri']))
                                            <span
                                                class="px-1.5 text-[10px] font-semibold rounded bg-violet-100 text-violet-500 dark:bg-violet-900/30 dark:text-violet-400">RI</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-xs font-mono text-gray-400 dark:text-gray-500">
                                    {{ $option['acc_id'] }}
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 1 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Data tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
