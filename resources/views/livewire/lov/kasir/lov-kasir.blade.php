<?php

/**
 * resources/views/livewire/lov/employer/lov-employer.blade.php
 *
 * LOV Karyawan dari tabel IMMST_EMPLOYERS.
 *
 * Payload dispatch ke parent:
 *   [
 *     'emp_id'    => '...',
 *     'emp_name'  => '...',
 *     'emp_index' => '...',
 *     'emp_grade' => '...',
 *     'bu_id'     => '...',
 *     'phone'     => '...',
 *     'address'   => '...',
 *   ]
 *
 * Cara pakai:
 *   <livewire:lov.kasir.lov-kasir
 *       target="kasir-user-control"
 *       :initialEmpId="$emp_id"
 *       wire:key="lov-kasir-{{ $userId }}" />
 *
 *   #[On('lov.selected.kasir-user-control')]
 *   public function onEmployerSelected(string $target, ?array $payload): void
 *   {
 *       $this->emp_id   = $payload['emp_id']   ?? null;
 *       $this->emp_name = $payload['emp_name']  ?? null;
 *   }
 */

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    public string $target = 'default';
    public string $label = 'Karyawan (EMP ID)';
    public string $placeholder = 'Ketik EMP ID atau nama karyawan...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;
    public ?array $selected = null;

    #[Reactive]
    public ?string $initialEmpId = null;

    public bool $disabled = false;

    /* ── Lifecycle ── */

    public function mount(): void
    {
        if ($this->initialEmpId) {
            $this->loadSelected($this->initialEmpId);
        }
    }

    public function updatedInitialEmpId(?string $value): void
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

    protected function loadSelected(string $empId): void
    {
        $row = $this->baseQuery()->where('emp_id', $empId)->first();

        if ($row) {
            $this->selected = $this->buildPayload($row);
        }
    }

    /* ── Query dasar ── */

    protected function baseQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('immst_employers')->select('emp_id', 'emp_name', 'emp_index', 'emp_grade', 'bu_id', 'phone', 'address', 'active_record');
        //->where('active_record', '1') // hanya karyawan aktif
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

        // Exact match by emp_id → langsung pilih
        $exactRow = $this->baseQuery()->where('emp_id', $keyword)->first();

        if ($exactRow) {
            $this->dispatchSelected($this->buildPayload($exactRow));
            return;
        }

        // Partial match
        $rows = $this->baseQuery()
            ->where(
                fn($q) => $q
                    ->where('emp_id', 'like', "%{$keyword}%")
                    ->orWhereRaw('UPPER(emp_name) LIKE ?', ["%{$upperKeyword}%"])
                    ->orWhereRaw('UPPER(emp_index) LIKE ?', ["%{$upperKeyword}%"]),
            )
            ->orderBy('emp_name')
            ->limit(50)
            ->get();

        $this->options = $rows->map(fn($row) => array_merge($this->buildPayload($row), ['label' => (string) ($row->emp_name ?: $row->emp_id)]))->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    /* ── Build payload ── */

    protected function buildPayload(object $row): array
    {
        return [
            'emp_id' => (string) ($row->emp_id ?? ''),
            'emp_name' => (string) ($row->emp_name ?? ''),
            'emp_index' => (string) ($row->emp_index ?? ''),
            'emp_grade' => (string) ($row->emp_grade ?? ''),
            'bu_id' => (string) ($row->bu_id ?? ''),
            'phone' => (string) ($row->phone ?? ''),
            'address' => (string) ($row->address ?? ''),
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
            {{-- Mode selected --}}
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full font-mono" :value="$selected['emp_id'] . ' — ' . $selected['emp_name']" disabled />
                </div>
                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>

            {{-- Info subtle --}}
            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1">
                @if (!empty($selected['emp_index']))
                    <span class="text-[10px] text-gray-400 dark:text-gray-600">
                        Index: <span class="font-mono">{{ $selected['emp_index'] }}</span>
                    </span>
                @endif
                @if (!empty($selected['emp_grade']))
                    <span class="text-[10px] text-gray-400 dark:text-gray-600">
                        Grade: {{ $selected['emp_grade'] }}
                    </span>
                @endif
                @if (!empty($selected['bu_id']))
                    <span class="text-[10px] text-gray-400 dark:text-gray-600">
                        BU: {{ $selected['bu_id'] }}
                    </span>
                @endif
                @if (!empty($selected['phone']))
                    <span class="text-[10px] text-gray-400 dark:text-gray-600">
                        ☎ {{ $selected['phone'] }}
                    </span>
                @endif
            </div>
        @endif

        {{-- Dropdown list --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-emp-{{ $option['emp_id'] }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-gray-900 truncate dark:text-gray-100">
                                            {{ $option['emp_name'] ?: $option['emp_id'] }}
                                        </div>
                                        <div class="flex flex-wrap gap-x-2 gap-y-0 mt-0.5">
                                            <span class="text-xs font-mono text-gray-500 dark:text-gray-400">
                                                {{ $option['emp_id'] }}
                                            </span>
                                            @if (!empty($option['emp_grade']))
                                                <span class="text-xs text-gray-400">
                                                    · {{ $option['emp_grade'] }}
                                                </span>
                                            @endif
                                            @if (!empty($option['bu_id']))
                                                <span class="text-xs text-gray-400">
                                                    · {{ $option['bu_id'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    @if (!empty($option['phone']))
                                        <span class="text-[11px] text-gray-400 shrink-0 mt-0.5">
                                            {{ $option['phone'] }}
                                        </span>
                                    @endif
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 1 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Karyawan tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
