<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Pasien';
    public string $placeholder = 'Ketik no RM/nama pasien%alamat...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state (buat mode selected + edit) */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim reg_no yang sudah tersimpan.
     */
    public ?string $initialRegNo = null;

    /**
     * Mode readonly: jika true, tombol "Ubah" akan hilang saat selected.
     */
    public bool $readonly = false;

    public function mount(): void
    {
        if (!$this->initialRegNo) {
            return;
        }

        $row = DB::table('rsmst_pasiens')
            ->select('reg_no', 'reg_name', DB::raw("to_char(reg_date,'dd/mm/yyyy hh24:mi:ss') as reg_date"), DB::raw("nvl(nokartu_bpjs,'-') as nokartu_bpjs"), DB::raw("nvl(nik_bpjs,'-') as nik_bpjs"), 'sex', DB::raw("to_char(birth_date,'dd/mm/yyyy') as birth_date"), DB::raw('(select trunc( months_between( sysdate, birth_date ) /12 ) from dual) as usia_tahun'), 'bln as usia_bulan', 'hari as usia_hari', 'birth_place', 'blood', 'marital_status', 'rel_id', 'edu_id', 'job_id', 'kk', 'nyonya', 'no_kk', 'address', 'rsmst_desas.des_id', 'rsmst_kecamatans.kec_id', 'rsmst_kabupatens.kab_id', 'rsmst_propinsis.prop_id', 'des_name', 'kec_name', 'kab_name', 'prop_name', 'rt', 'rw', 'phone')
            ->join('rsmst_desas', 'rsmst_desas.des_id', '=', 'rsmst_pasiens.des_id')
            ->join('rsmst_kecamatans', 'rsmst_kecamatans.kec_id', '=', 'rsmst_desas.kec_id')
            ->join('rsmst_kabupatens', 'rsmst_kabupatens.kab_id', '=', 'rsmst_kecamatans.kab_id')
            ->join('rsmst_propinsis', 'rsmst_propinsis.prop_id', '=', 'rsmst_kabupatens.prop_id')
            ->where('reg_no', $this->initialRegNo)
            ->first();

        if ($row) {
            $this->selected = [
                'reg_no' => (string) $row->reg_no,
                'reg_name' => (string) ($row->reg_name ?? ''),
                'reg_date' => (string) ($row->reg_date ?? ''),
                'nokartu_bpjs' => (string) ($row->nokartu_bpjs ?? '-'),
                'nik_bpjs' => (string) ($row->nik_bpjs ?? '-'),
                'sex' => (string) ($row->sex ?? ''),
                'birth_date' => (string) ($row->birth_date ?? ''),
                'usia_tahun' => (string) ($row->usia_tahun ?? '0'),
                'usia_bulan' => (string) ($row->usia_bulan ?? '0'),
                'usia_hari' => (string) ($row->usia_hari ?? '0'),
                'birth_place' => (string) ($row->birth_place ?? ''),
                'blood' => (string) ($row->blood ?? ''),
                'marital_status' => (string) ($row->marital_status ?? ''),
                'address' => (string) ($row->address ?? ''),
                'des_name' => (string) ($row->des_name ?? ''),
                'kec_name' => (string) ($row->kec_name ?? ''),
                'kab_name' => (string) ($row->kab_name ?? ''),
                'prop_name' => (string) ($row->prop_name ?? ''),
                'rt' => (string) ($row->rt ?? ''),
                'rw' => (string) ($row->rw ?? ''),
                'phone' => (string) ($row->phone ?? ''),
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

        // ===== 1) exact match by reg_no =====
        if (ctype_digit($keyword)) {
            $exactRow = DB::table('rsmst_pasiens')->select('reg_no', 'reg_name')->where('reg_no', $keyword)->first();

            if ($exactRow) {
                $this->dispatchSelected([
                    'reg_no' => (string) $exactRow->reg_no,
                    'reg_name' => (string) ($exactRow->reg_name ?? ''),
                ]);
                return;
            }
        }

        // ===== 2) multiple search dengan pemisah % =====
        $query = DB::table('rsmst_pasiens')->select('rsmst_pasiens.reg_no', 'rsmst_pasiens.reg_name', 'rsmst_pasiens.address', 'rsmst_pasiens.sex', DB::raw("to_char(birth_date,'dd/mm/yyyy') as birth_date"), DB::raw('(select trunc( months_between( sysdate, birth_date ) /12 ) from dual) as usia'), 'rsmst_desas.des_name', 'rsmst_kecamatans.kec_name', 'rsmst_kabupatens.kab_name', 'rsmst_propinsis.prop_name')->join('rsmst_desas', 'rsmst_desas.des_id', '=', 'rsmst_pasiens.des_id')->join('rsmst_kecamatans', 'rsmst_kecamatans.kec_id', '=', 'rsmst_desas.kec_id')->join('rsmst_kabupatens', 'rsmst_kabupatens.kab_id', '=', 'rsmst_kecamatans.kab_id')->join('rsmst_propinsis', 'rsmst_propinsis.prop_id', '=', 'rsmst_kabupatens.prop_id');

        // Multiple search by more than one table
        $myMultipleSearch = explode('%', $keyword);

        foreach ($myMultipleSearch as $key => $myMS) {
            $myMS = trim($myMS);
            if (empty($myMS)) {
                continue;
            }

            // key 0 mencari reg_no dan reg_name
            if ($key == 0) {
                $query->where(function ($q) use ($myMS) {
                    $q->where(DB::raw('upper(rsmst_pasiens.reg_no)'), 'like', '%' . strtoupper($myMS) . '%')->orWhere(DB::raw('upper(rsmst_pasiens.reg_name)'), 'like', '%' . strtoupper($myMS) . '%');
                });
            }
            // key 1 mencari alamat
            if ($key == 1) {
                $query->where(function ($q) use ($myMS) {
                    $q->where(DB::raw('upper(rsmst_pasiens.address)'), 'like', '%' . strtoupper($myMS) . '%');
                });
            }
        }

        $rows = $query->orderBy('rsmst_pasiens.reg_name', 'desc')->limit(50)->get();

        $this->options = array_map(function ($row) {
            $regNo = (string) $row->reg_no;
            $regName = (string) ($row->reg_name ?? '');
            $address = (string) ($row->address ?? '');
            $usia = (string) ($row->usia ?? '0');
            $sex = (string) ($row->sex ?? '');
            $birthDate = (string) ($row->birth_date ?? '');

            // Format alamat lengkap
            $alamatLengkap = $address;
            if (!empty($row->des_name)) {
                $alamatLengkap .= ', ' . $row->des_name;
            }
            if (!empty($row->kec_name)) {
                $alamatLengkap .= ', ' . $row->kec_name;
            }
            if (!empty($row->kab_name)) {
                $alamatLengkap .= ', ' . $row->kab_name;
            }
            if (!empty($row->prop_name)) {
                $alamatLengkap .= ', ' . $row->prop_name;
            }
            $alamatLengkap = trim($alamatLengkap, ', ');

            return [
                // payload
                'reg_no' => $regNo,
                'reg_name' => $regName,
                'address' => $address,
                'sex' => $sex,
                'birth_date' => $birthDate,
                'usia' => $usia,
                'des_name' => (string) ($row->des_name ?? ''),
                'kec_name' => (string) ($row->kec_name ?? ''),
                'kab_name' => (string) ($row->kab_name ?? ''),
                'prop_name' => (string) ($row->prop_name ?? ''),

                // UI
                'label' => $regName ?: '-',
                'hint' => "RM: {$regNo}",
                'subhint' => $usia . ' thn, ' . $sex,
                'address' => $alamatLengkap,
            ];
        }, $rows->toArray());

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    public function clearSelected(): void
    {
        // Jika readonly, tidak bisa clear selected
        if ($this->readonly) {
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
            'reg_no' => $this->options[$index]['reg_no'] ?? '',
            'reg_name' => $this->options[$index]['reg_name'] ?? '',
            'address' => $this->options[$index]['address'] ?? '',
            'sex' => $this->options[$index]['sex'] ?? '',
            'birth_date' => $this->options[$index]['birth_date'] ?? '',
            'usia' => $this->options[$index]['usia'] ?? '0',
            'des_name' => $this->options[$index]['des_name'] ?? '',
            'kec_name' => $this->options[$index]['kec_name'] ?? '',
            'kab_name' => $this->options[$index]['kab_name'] ?? '',
            'prop_name' => $this->options[$index]['prop_name'] ?? '',
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
        // Ambil data lengkap pasien yang dipilih
        $row = DB::table('rsmst_pasiens')
            ->select('reg_no', 'reg_name', DB::raw("to_char(reg_date,'dd/mm/yyyy hh24:mi:ss') as reg_date"), DB::raw("nvl(nokartu_bpjs,'-') as nokartu_bpjs"), DB::raw("nvl(nik_bpjs,'-') as nik_bpjs"), 'sex', DB::raw("to_char(birth_date,'dd/mm/yyyy') as birth_date"), DB::raw('(select trunc( months_between( sysdate, birth_date ) /12 ) from dual) as usia_tahun'), 'bln as usia_bulan', 'hari as usia_hari', 'birth_place', 'blood', 'marital_status', 'rel_id', 'edu_id', 'job_id', 'kk', 'nyonya', 'no_kk', 'address', 'rsmst_desas.des_id', 'rsmst_kecamatans.kec_id', 'rsmst_kabupatens.kab_id', 'rsmst_propinsis.prop_id', 'des_name', 'kec_name', 'kab_name', 'prop_name', 'rt', 'rw', 'phone')
            ->join('rsmst_desas', 'rsmst_desas.des_id', '=', 'rsmst_pasiens.des_id')
            ->join('rsmst_kecamatans', 'rsmst_kecamatans.kec_id', '=', 'rsmst_desas.kec_id')
            ->join('rsmst_kabupatens', 'rsmst_kabupatens.kab_id', '=', 'rsmst_kecamatans.kab_id')
            ->join('rsmst_propinsis', 'rsmst_propinsis.prop_id', '=', 'rsmst_kabupatens.prop_id')
            ->where('reg_no', $payload['reg_no'])
            ->first();

        if ($row) {
            $this->selected = [
                'reg_no' => (string) $row->reg_no,
                'reg_name' => (string) ($row->reg_name ?? ''),
                'reg_date' => (string) ($row->reg_date ?? ''),
                'nokartu_bpjs' => (string) ($row->nokartu_bpjs ?? '-'),
                'nik_bpjs' => (string) ($row->nik_bpjs ?? '-'),
                'sex' => (string) ($row->sex ?? ''),
                'birth_date' => (string) ($row->birth_date ?? ''),
                'usia_tahun' => (string) ($row->usia_tahun ?? '0'),
                'usia_bulan' => (string) ($row->usia_bulan ?? '0'),
                'usia_hari' => (string) ($row->usia_hari ?? '0'),
                'birth_place' => (string) ($row->birth_place ?? ''),
                'blood' => (string) ($row->blood ?? ''),
                'marital_status' => (string) ($row->marital_status ?? ''),
                'address' => (string) ($row->address ?? ''),
                'des_name' => (string) ($row->des_name ?? ''),
                'kec_name' => (string) ($row->kec_name ?? ''),
                'kab_name' => (string) ($row->kab_name ?? ''),
                'prop_name' => (string) ($row->prop_name ?? ''),
                'rt' => (string) ($row->rt ?? ''),
                'rw' => (string) ($row->rw ?? ''),
                'phone' => (string) ($row->phone ?? ''),
            ];
        }

        // bersihkan mode search
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;

        // emit ke parent
        $this->dispatch('lov.selected', target: $this->target, payload: $this->selected);
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
            {{-- Mode cari --}}
            @if (!$readonly)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder" wire:model.live.debounce.350ms="search"
                    wire:keydown.escape.prevent="resetLov" wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            {{-- Mode selected --}}
            <div class="flex items-center gap-2">
                <div
                    class="flex-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md dark:bg-gray-800 dark:border-gray-600">
                    <div class="font-medium text-gray-900 dark:text-gray-100">
                        {{ $selected['reg_name'] ?? '' }} ({{ $selected['reg_no'] ?? '' }})
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        @if (!empty($selected['sex']) || !empty($selected['usia_tahun']))
                            {{ $selected['sex'] ?? '' }} / {{ $selected['usia_tahun'] ?? '0' }} thn
                        @endif
                        @if (!empty($selected['birth_date']))
                            , Lahir: {{ $selected['birth_date'] }}
                        @endif
                    </div>
                    @if (!empty($selected['address']))
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                            {{ $selected['address'] }}, {{ $selected['des_name'] ?? '' }}
                        </div>
                    @endif
                </div>

                @if (!$readonly)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- dropdown hanya saat mode cari dan tidak readonly --}}
        @if ($isOpen && $selected === null && !$readonly)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-80 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-pasien-{{ $option['reg_no'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex flex-col">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $option['label'] ?? '-' }}
                                        </span>
                                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                            {{ $option['hint'] ?? '' }}
                                        </span>
                                    </div>

                                    @if (!empty($option['subhint']))
                                        <div class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">
                                            {{ $option['subhint'] }}
                                        </div>
                                    @endif

                                    @if (!empty($option['address']))
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                            📍 {{ $option['address'] }}
                                        </div>
                                    @endif
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 2 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Pasien tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
