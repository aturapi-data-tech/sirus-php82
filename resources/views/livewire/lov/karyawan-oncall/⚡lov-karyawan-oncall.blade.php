<?php
use Livewire\Component;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;

/**
 * LOV Karyawan On Call — memilih ORANG dari `hrmst_employees`.
 *
 * ⚠️ JANGAN TERTUKAR dengan `lov.jasa-karyawan.lov-jasa-karyawan`:
 *
 *   lov-karyawan-oncall → `hrmst_employees`  → SIAPA petugasnya (emp_id, name)
 *   lov-jasa-karyawan   → `rsmst_actemps`    → JENIS TARIF jasa karyawan (acte_id, harga)
 *
 * Namanya mirip tapi isinya beda sama sekali: yang satu daftar pegawai, yang satu
 * daftar tindakan/jasa berikut tarifnya. Payload-nya pun beda (`emp_id`/`name`
 * vs `acte_id`/`acte_desc`/`acte_price`), jadi salah pilih komponen akan membuat
 * listener `lov.selected.*` menerima key yang tidak ada dan diam-diam menyimpan null.
 *
 * Dipakai di Kamar Operasi untuk mengisi crew: asisten operator, asisten anestesi,
 * instrument, pengganti anestesi, dan daftar crew OM LOP. Untuk crew OM LOP,
 * tiap petugas punya dua kolom jasa — jasa biasa (`omlop_fee`) dan **jasa on call**
 * (`oncallomlop_fee`) — yang keduanya jasa petugas dan TIDAK ditagihkan ke pasien.
 *
 * Hanya karyawan aktif (`status_ed = 'E'`). Prop `omlopOnly` mempersempit ke
 * karyawan bertanda `omlop_status = 'Y'` (15 orang) untuk pemilihan crew OM LOP.
 */
new class extends Component {
    public string $target = 'default';
    public string $label = 'Cari Karyawan';
    public string $placeholder = 'Ketik NIK/nama karyawan...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    public ?array $selected = null;

    #[Reactive]
    public ?string $initialEmpId = null;

    public bool $disabled = false;

    /** Batasi ke karyawan bertanda crew OM LOP. */
    public bool $omlopOnly = false;

    public function mount(): void
    {
        if (!$this->initialEmpId) {
            return;
        }
        $this->loadSelected($this->initialEmpId);
    }

    public function updatedInitialEmpId($value): void
    {
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        if (empty($value)) {
            return;
        }
        $this->loadSelected($value);
    }

    /**
     * Baris terpilih dibaca TANPA filter aktif/omlop — karyawan yang sudah
     * terlanjur tersimpan di transaksi lama harus tetap tampil namanya walau
     * sekarang sudah nonaktif, supaya data lama tidak terlihat kosong.
     */
    protected function loadSelected(string $empId): void
    {
        $row = DB::table('hrmst_employees')->select('emp_id', 'name', 'status_ed', 'omlop_status')->where('emp_id', $empId)->first();

        if ($row) {
            $this->selected = [
                'emp_id' => (string) $row->emp_id,
                'name' => (string) ($row->name ?? ''),
                'nonaktif' => ($row->status_ed ?? '') !== 'E',
            ];
        }
    }

    private function baseQuery()
    {
        $query = DB::table('hrmst_employees')->select('emp_id', 'name', 'status_ed', 'omlop_status')->where('status_ed', 'E');

        if ($this->omlopOnly) {
            $query->where('omlop_status', 'Y');
        }

        return $query;
    }

    public function updatedSearch(): void
    {
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        if (mb_strlen($keyword) < 2) {
            $this->closeAndResetList();
            return;
        }

        // ── Exact match by emp_id ──
        if (ctype_alnum($keyword)) {
            $exact = $this->baseQuery()->where('emp_id', $keyword)->first();

            if ($exact) {
                $this->dispatchSelected(['emp_id' => (string) $exact->emp_id, 'name' => (string) ($exact->name ?? ''), 'nonaktif' => false]);
                return;
            }
        }

        // ── Partial search ──
        $upper = mb_strtoupper($keyword);

        $rows = $this->baseQuery()
            ->where(function ($subQuery) use ($upper) {
                $subQuery->where(DB::raw('upper(name)'), 'like', "%{$upper}%")->orWhere(DB::raw('upper(emp_id)'), 'like', "%{$upper}%");
            })
            ->orderBy('name')
            ->orderBy('emp_id')
            ->limit(50)
            ->get();

        $this->options = $rows
            ->map(
                fn($row) => [
                    'emp_id' => (string) $row->emp_id,
                    'name' => (string) ($row->name ?? ''),
                    'nonaktif' => false,
                    'label' => $row->name ?: '-',
                    'hint' => 'NIK: ' . $row->emp_id . (($row->omlop_status ?? '') === 'Y' ? ' • crew OM LOP' : ''),
                ],
            )
            ->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

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

        $this->dispatchSelected(['emp_id' => $this->options[$index]['emp_id'] ?? '', 'name' => $this->options[$index]['name'] ?? '', 'nonaktif' => false]);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

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
                    <x-text-input type="text" class="block w-full"
                        :value="$selected['name'] . ' (' . $selected['emp_id'] . ')' . ($selected['nonaktif'] ? ' — nonaktif' : '')" disabled />
                </div>
                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- Dropdown list --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-karyawan-{{ $option['emp_id'] }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $option['label'] }}
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
                        Data tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
