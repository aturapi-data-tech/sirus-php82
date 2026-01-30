<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

new class extends Component {
    use WithPagination;

    // UI State
    public string $q = '';
    public int $perPage = 10;

    public bool $modal = false;
    public string $mode = 'create'; // create|edit

    // Form fields (camelCase)
    public ?string $poliId = null;
    public string $poliDesc = '';
    public ?string $kdPoliBpjs = null;
    public ?string $poliUuid = null;
    public string $spesialisStatus = '0';

    /* -------------------------
     | Reactive UI handlers
     * ------------------------- */

    public function updatedQ(): void
    {
        $this->resetPage();
        $this->resetValidation();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    /* -------------------------
     | Modal actions
     * ------------------------- */

    public function openCreate(): void
    {
        $this->resetForm();
        $this->mode = 'create';
        $this->modal = true;
    }

    public function openEdit(string $id): void
    {
        $row = DB::table('rsmst_polis')->where('poli_id', $id)->first();
        if (!$row) {
            return;
        }

        $this->poliId = (string) $row->poli_id;
        $this->poliDesc = (string) ($row->poli_desc ?? '');
        $this->kdPoliBpjs = $row->kd_poli_bpjs;
        $this->poliUuid = $row->poli_uuid;
        $this->spesialisStatus = (string) ($row->spesialis_status ?? '0');

        $this->mode = 'edit';
        $this->modal = true;
        $this->resetValidation();
    }

    public function closeModal(): void
    {
        $this->modal = false;
        $this->resetValidation();
    }

    public function resetForm(): void
    {
        $this->reset(['poliId', 'poliDesc', 'kdPoliBpjs', 'poliUuid', 'spesialisStatus']);
        $this->spesialisStatus = '0';
    }

    /* -------------------------
     | Validation
     * ------------------------- */

    protected function rules(): array
    {
        $rules = [
            'poliId' => ['required', 'numeric'],
            'poliDesc' => ['required', 'string', 'max:255'],
            'kdPoliBpjs' => ['nullable', 'string', 'max:50'],
            'poliUuid' => ['nullable', 'string', 'max:100'],
            'spesialisStatus' => ['required', Rule::in(['0', '1'])],
        ];

        if ($this->mode === 'create') {
            $rules['poliId'][] = Rule::unique('rsmst_polis', 'poli_id');
        } else {
            $rules['poliId'][] = Rule::unique('rsmst_polis', 'poli_id')->ignore($this->poliId, 'poli_id');
        }

        return $rules;
    }

    /* -------------------------
     | Writes
     * ------------------------- */

    public function save(): void
    {
        $data = $this->validate();

        $payload = [
            'poli_desc' => $data['poliDesc'],
            'kd_poli_bpjs' => $data['kdPoliBpjs'],
            'poli_uuid' => $data['poliUuid'],
            'spesialis_status' => $data['spesialisStatus'],
        ];

        if ($this->mode === 'create') {
            DB::table('rsmst_polis')->insert([
                'poli_id' => $data['poliId'],
                ...$payload,
            ]);
        } else {
            DB::table('rsmst_polis')->where('poli_id', $data['poliId'])->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data poli berhasil disimpan.');

        $this->closeModal();
        // optional:
        // $this->resetPage();
    }

    public function delete(string $id): void
    {
        $used = DB::table('rstxn_rjhdrs')->where('poli_id', $id)->exists();
        if ($used) {
            $this->dispatch('toast', type: 'error', message: 'Data poli sudah dipakai pada transaksi Rawat Jalan.');
            return;
        }

        DB::table('rsmst_polis')->where('poli_id', $id)->delete();
        $this->dispatch('toast', type: 'error', message: 'Data poli berhasil dihapus.');

        $this->resetPage();
    }

    /* -------------------------
     | ✅ Computed (Best practice v4)
     * ------------------------- */

    #[Computed]
    public function baseQuery()
    {
        $s = trim($this->q);

        $q = DB::table('rsmst_polis')->select('poli_id', 'poli_desc', 'kd_poli_bpjs', 'poli_uuid', 'spesialis_status')->orderBy('poli_desc', 'asc');

        if ($s !== '') {
            $upper = mb_strtoupper($s);

            $q->where(function ($qq) use ($upper, $s) {
                if (ctype_digit($s)) {
                    $qq->orWhere('poli_id', $s);
                }

                $qq->orWhereRaw('UPPER(poli_desc) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(kd_poli_bpjs) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(poli_uuid) LIKE ?', ["%{$upper}%"]);
            });
        }

        return $q;
    }

    #[Computed]
    public function rows()
    {
        return $this->baseQuery()->paginate($this->perPage);
    }
};
?>


<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Master Poli
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Kelola data poli & ruangan untuk aplikasi
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- Toolbar --}}
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="w-full lg:max-w-md">
                    <input wire:model.live.debounce.300ms="q" type="text" placeholder="Cari poli..."
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-lime" />
                </div>

                <div class="flex items-center justify-end gap-2">
                    <select wire:model.live="perPage"
                        class="px-3 py-2 text-sm border border-gray-200 rounded-xl dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="20">20</option>
                        <option value="100">100</option>
                    </select>

                    <x-primary-button type="button" wire:click="openCreate">
                        + Tambah Poli
                    </x-primary-button>
                </div>
            </div>

            {{-- Table --}}
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr class="text-left text-gray-600 dark:text-gray-300">
                            <th class="px-4 py-3 font-semibold">ID</th>
                            <th class="px-4 py-3 font-semibold">POLI</th>
                            <th class="px-4 py-3 font-semibold">BPJS</th>
                            <th class="px-4 py-3 font-semibold">UUID</th>
                            <th class="px-4 py-3 font-semibold">STATUS</th>
                            <th class="px-4 py-3 font-semibold">AKSI</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($this->rows as $row)
                            <tr wire:key="poli-{{ $row->poli_id }}"
                                class="hover:bg-gray-50/60 dark:hover:bg-gray-900/30">
                                <td class="px-4 py-3">{{ $row->poli_id }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $row->poli_desc }}</td>
                                <td class="px-4 py-3">{{ $row->kd_poli_bpjs }}</td>
                                <td class="px-4 py-3">{{ $row->poli_uuid }}</td>
                                <td class="px-4 py-3">
                                    {{ (string) $row->spesialis_status === '1' ? 'Spesialis' : 'Non Spesialis' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2">
                                        <x-outline-button type="button" wire:click="openEdit('{{ $row->poli_id }}')">
                                            Edit
                                        </x-outline-button>

                                        <x-danger-button type="button" wire:click="delete('{{ $row->poli_id }}')"
                                            onclick="return confirm('Yakin hapus data ini?')">
                                            Hapus
                                        </x-danger-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                    Data belum ada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->rows->links() }}
            </div>

            {{-- Modal --}}
            @if ($modal)
                <div class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4">
                    <div
                        class="w-full max-w-lg bg-white border border-gray-200 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        <div
                            class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="font-semibold text-gray-900 dark:text-gray-100">
                                {{ $mode === 'edit' ? 'Ubah Poli' : 'Tambah Poli' }}
                            </div>

                            <button wire:click="closeModal"
                                class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-200">
                                ✕
                            </button>
                        </div>

                        <div class="p-5 space-y-3">
                            <div>
                                <label class="text-xs text-gray-500">POLI ID</label>
                                <input wire:model.defer="poliId" @disabled($mode === 'edit')
                                    class="w-full border border-gray-200 rounded-xl dark:border-gray-700 dark:bg-gray-800 disabled:opacity-60" />
                                @error('poliId')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="text-xs text-gray-500">POLI DESC</label>
                                <input wire:model.defer="poliDesc"
                                    class="w-full border border-gray-200 rounded-xl dark:border-gray-700 dark:bg-gray-800" />
                                @error('poliDesc')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="text-xs text-gray-500">KD POLI BPJS</label>
                                    <input wire:model.defer="kdPoliBpjs"
                                        class="w-full border border-gray-200 rounded-xl dark:border-gray-700 dark:bg-gray-800" />
                                    @error('kdPoliBpjs')
                                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div>
                                    <label class="text-xs text-gray-500">SPESIALIS STATUS</label>
                                    <select wire:model.defer="spesialisStatus"
                                        class="w-full border border-gray-200 rounded-xl dark:border-gray-700 dark:bg-gray-800">
                                        <option value="0">Non Spesialis</option>
                                        <option value="1">Spesialis</option>
                                    </select>
                                    @error('spesialisStatus')
                                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label class="text-xs text-gray-500">POLI UUID</label>
                                <input wire:model.defer="poliUuid"
                                    class="w-full border border-gray-200 rounded-xl dark:border-gray-700 dark:bg-gray-800" />
                                @error('poliUuid')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-200 dark:border-gray-700">
                            <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                            <x-primary-button type="button" wire:click="save">Simpan</x-primary-button>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
