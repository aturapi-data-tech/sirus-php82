<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Dokter';
    public string $placeholder = 'Ketik nama/kode dokter...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state (buat mode selected + edit) */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim dr_id yang sudah tersimpan.
     * Cukup kirim initialDrId, sisanya akan di-load dari DB.
     */
    #[Reactive]
    public ?string $initialDrId = null;

    /**
     * Filter berdasarkan poli_id jika diperlukan
     * Berguna untuk form yang hanya ingin menampilkan dokter dari poli tertentu
     */
    #[Reactive]
    public ?string $filterPoliId = null;

    /**
     * Mode disabled: jika true, tombol "Ubah" akan hilang saat selected.
     * Berguna untuk form yang sudah selesai/tidak boleh diedit.
     */
    public bool $disabled = false;

    public function mount(): void
    {
        if (!$this->initialDrId) {
            return;
        }

        $row = DB::table('rsmst_doctors as a')->join('rsmst_polis as b', 'a.poli_id', '=', 'b.poli_id')->select('a.dr_id', 'a.dr_name', 'a.poli_id', 'b.poli_desc', 'a.dr_phone', 'a.dr_address', 'a.basic_salary', 'a.active_status')->where('a.dr_id', $this->initialDrId)->first();

        if ($row) {
            $this->selected = [
                'dr_id' => (string) $row->dr_id,
                'dr_name' => (string) ($row->dr_name ?? ''),
                'poli_id' => (string) ($row->poli_id ?? ''),
                'poli_desc' => (string) ($row->poli_desc ?? ''),
                'dr_phone' => (string) ($row->dr_phone ?? ''),
                'dr_address' => (string) ($row->dr_address ?? ''),
                'basic_salary' => (string) ($row->basic_salary ?? ''),
                'active_status' => (string) ($row->active_status ?? ''),
            ];
        }
    }

    public function updatedSearch(): void
    {
        // kalau sudah selected, jangan cari lagi
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        // minimal 2 char
        if (mb_strlen($keyword) < 2) {
            $this->closeAndResetList();
            return;
        }

        // ===== 1) exact match by dr_id =====
        if (ctype_digit($keyword)) {
            $exactQuery = DB::table('rsmst_doctors as a')->join('rsmst_polis as b', 'a.poli_id', '=', 'b.poli_id')->select('a.dr_id', 'a.dr_name', 'a.poli_id', 'b.poli_desc', 'a.dr_phone', 'a.dr_address', 'a.basic_salary', 'a.active_status')->where('a.dr_id', $keyword);

            // Tambah filter poli jika ada
            if ($this->filterPoliId) {
                $exactQuery->where('a.poli_id', $this->filterPoliId);
            }

            $exactRow = $exactQuery->first();

            if ($exactRow) {
                $this->dispatchSelected([
                    'dr_id' => (string) $exactRow->dr_id,
                    'dr_name' => (string) ($exactRow->dr_name ?? ''),
                    'poli_id' => (string) ($exactRow->poli_id ?? ''),
                    'poli_desc' => (string) ($exactRow->poli_desc ?? ''),
                    'dr_phone' => (string) ($exactRow->dr_phone ?? ''),
                    'dr_address' => (string) ($exactRow->dr_address ?? ''),
                    'basic_salary' => (string) ($exactRow->basic_salary ?? ''),
                    'active_status' => (string) ($exactRow->active_status ?? ''),
                ]);
                return;
            }
        }

        // ===== 2) search by dr_name / poli_desc partial =====
        $upperKeyword = mb_strtoupper($keyword);

        $query = DB::table('rsmst_doctors as a')
            ->join('rsmst_polis as b', 'a.poli_id', '=', 'b.poli_id')
            ->select('a.dr_id', 'a.dr_name', 'a.poli_id', 'b.poli_desc', 'a.dr_phone', 'a.dr_address', 'a.basic_salary', 'a.active_status')
            ->where(function ($q) use ($keyword, $upperKeyword) {
                if (ctype_digit($keyword)) {
                    $q->orWhere('a.dr_id', 'like', "%{$keyword}%");
                }

                $q->orWhereRaw('UPPER(a.dr_name) LIKE ?', ["%{$upperKeyword}%"]);
                $q->orWhereRaw('UPPER(b.poli_desc) LIKE ?', ["%{$upperKeyword}%"]);
            })
            ->orderBy('a.dr_name');

        // Tambah filter poli jika ada
        if ($this->filterPoliId) {
            $query->where('a.poli_id', $this->filterPoliId);
        }

        $rows = $query->limit(50)->get();

        $this->options = $rows
            ->map(function ($row) {
                $drId = (string) $row->dr_id;
                $drName = (string) ($row->dr_name ?? '');
                $poliDesc = (string) ($row->poli_desc ?? '');
                $activeStatus = (string) ($row->active_status ?? '');

                $statusLabel = $activeStatus === '1' ? 'Aktif' : 'Tidak Aktif';
                $statusClass = $activeStatus === '1' ? 'text-green-600' : 'text-red-600';

                $parts = array_filter([$drId ? "ID {$drId}" : null, $poliDesc ? "Poli {$poliDesc}" : null]);

                return [
                    // payload
                    'dr_id' => $drId,
                    'dr_name' => $drName,
                    'poli_id' => (string) ($row->poli_id ?? ''),
                    'poli_desc' => $poliDesc,
                    'dr_phone' => (string) ($row->dr_phone ?? ''),
                    'dr_address' => (string) ($row->dr_address ?? ''),
                    'basic_salary' => (string) ($row->basic_salary ?? ''),
                    'active_status' => $activeStatus,

                    // UI
                    'label' => $drName ?: '-',
                    'hint' => implode(' • ', $parts),
                    'status' => $statusLabel,
                    'status_class' => $statusClass,
                ];
            })
            ->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    public function clearSelected(): void
    {
        // Jika disabled, tidak bisa clear selected
        if ($this->disabled) {
            return;
        }

        $this->selected = null;
        $this->resetLov();
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
            'dr_id' => $this->options[$index]['dr_id'] ?? '',
            'dr_name' => $this->options[$index]['dr_name'] ?? '',
            'poli_id' => $this->options[$index]['poli_id'] ?? '',
            'poli_desc' => $this->options[$index]['poli_desc'] ?? '',
            'dr_phone' => $this->options[$index]['dr_phone'] ?? '',
            'dr_address' => $this->options[$index]['dr_address'] ?? '',
            'basic_salary' => $this->options[$index]['basic_salary'] ?? '',
            'active_status' => $this->options[$index]['active_status'] ?? '',
        ];

        $this->dispatchSelected($payload);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    /* helpers */

    protected function closeAndResetList(): void
    {
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;
    }

    protected function dispatchSelected(array $payload): void
    {
        // set selected -> UI berubah jadi nama + tombol ubah
        $this->selected = $payload;

        // bersihkan mode search
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;

        // emit ke parent
        $this->dispatch('lov.selected', target: $this->target, payload: $payload);
    }

    protected function emitScroll(): void
    {
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }

    public function updatedInitialDrId($value): void
    {
        // Reset state
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        if (empty($value)) {
            return;
        }

        $row = DB::table('rsmst_doctors as a')->join('rsmst_polis as b', 'a.poli_id', '=', 'b.poli_id')->select('a.dr_id', 'a.dr_name', 'a.poli_id', 'b.poli_desc', 'a.dr_phone', 'a.dr_address', 'a.basic_salary', 'a.active_status')->where('a.dr_id', $value)->first();

        if ($row) {
            $this->selected = [
                'dr_id' => (string) $row->dr_id,
                'dr_name' => (string) ($row->dr_name ?? ''),
                'poli_id' => (string) ($row->poli_id ?? ''),
                'poli_desc' => (string) ($row->poli_desc ?? ''),
                'dr_phone' => (string) ($row->dr_phone ?? ''),
                'dr_address' => (string) ($row->dr_address ?? ''),
                'basic_salary' => (string) ($row->basic_salary ?? ''),
                'active_status' => (string) ($row->active_status ?? ''),
            ];
        }
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">
    <x-input-label :value="$label" />

    <div class="relative mt-1">
        @if ($selected === null)
            {{-- Mode cari --}}
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
                    <x-text-input type="text" class="block w-full" :value="$selected['dr_name'] . ' - ' . $selected['poli_desc']" disabled />
                </div>

                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>

            {{-- Info tambahan jika diperlukan --}}
            @if ($selected && ($selected['dr_phone'] || $selected['dr_address']))
                <div class="mt-1 text-xs text-gray-500">
                    @if ($selected['dr_phone'])
                        <span class="mr-3">📞 {{ $selected['dr_phone'] }}</span>
                    @endif
                </div>
            @endif
        @endif

        {{-- dropdown hanya saat mode cari dan tidak disabled --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-dr-{{ $option['dr_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex items-center justify-between">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $option['label'] ?? '-' }}
                                    </div>
                                    <div class="text-xs {{ $option['status_class'] ?? 'text-gray-500' }}">
                                        {{ $option['status'] ?? '' }}
                                    </div>
                                </div>

                                @if (!empty($option['hint']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $option['hint'] }}
                                    </div>
                                @endif
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 2 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Data dokter tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
