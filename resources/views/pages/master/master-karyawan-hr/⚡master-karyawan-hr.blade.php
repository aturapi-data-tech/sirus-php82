<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

/**
 * Master Karyawan HR — tabel `hrmst_employees` (warisan Oracle Dev 6i).
 *
 * BEDA dengan Master Karyawan (`immst_employers`, NIK login user & coder iDRG).
 * Tabel ini sumber LOV petugas kamar operasi (asisten operator/anestesi,
 * instrument, ON LOOP) dan pra-induksi. Status aktif = `status_ed` ('E' aktif,
 * 'D' non-aktif), petugas OK = `omlop_status` ('Y'/'N').
 */
new class extends Component {
    use WithPagination;

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    public string $searchKeyword = '';
    public string $filterStatus = 'E'; // E|D|semua
    public string $filterPetugasOk = 'semua'; // Y|semua
    public int $itemsPerPage = 10;

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPetugasOk(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterPetugasOk']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    /* -------------------------
     | Child modal triggers
     * ------------------------- */
    public function openCreate(): void
    {
        $this->dispatch('master.karyawan-hr.openCreate');
    }

    public function openEdit(string $empId): void
    {
        $this->dispatch('master.karyawan-hr.openEdit', empId: $empId);
    }

    public function requestDelete(string $empId): void
    {
        $this->dispatch('master.karyawan-hr.requestDelete', empId: $empId);
    }

    /* -------------------------
     | Toggle langsung dari table
     * ------------------------- */
    public function toggleActive(string $empId): void
    {
        $current = (string) DB::table('hrmst_employees')->where('emp_id', $empId)->value('status_ed');
        $newValue = $current === 'E' ? 'D' : 'E';

        DB::table('hrmst_employees')->where('emp_id', $empId)->update(['status_ed' => $newValue]);

        $this->dispatch('toast', type: 'success', message: $newValue === 'E' ? 'Karyawan diaktifkan.' : 'Karyawan dinon-aktifkan.');
    }

    public function togglePetugasOk(string $empId): void
    {
        $current = (string) DB::table('hrmst_employees')->where('emp_id', $empId)->value('omlop_status');
        $newValue = $current === 'Y' ? 'N' : 'Y';

        DB::table('hrmst_employees')->where('emp_id', $empId)->update(['omlop_status' => $newValue]);

        $this->dispatch('toast', type: 'success', message: $newValue === 'Y' ? 'Ditandai sebagai petugas OK.' : 'Tanda petugas OK dilepas.');
    }

    /* -------------------------
     | Refresh after child save
     * ------------------------- */
    #[On('master.karyawan-hr.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    /* -------------------------
     | Computed queries
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        $searchKeyword = trim($this->searchKeyword);

        $queryBuilder = DB::table('hrmst_employees as e')
            ->leftJoin('hrmst_workunits as wu', 'wu.wu_id', '=', 'e.wu_id')
            ->leftJoin('hrmst_workstructures as ws', 'ws.ws_id', '=', 'e.ws_id')
            ->select('e.emp_id', 'e.name', 'e.sex', 'e.status_ed', 'e.omlop_status', 'wu.wu_desc', 'ws.ws_desc')
            ->orderBy('e.name', 'asc');

        if (in_array($this->filterStatus, ['E', 'D'], true)) {
            $queryBuilder->where('e.status_ed', $this->filterStatus);
        }

        if ($this->filterPetugasOk === 'Y') {
            $queryBuilder->where('e.omlop_status', 'Y');
        }

        if ($searchKeyword !== '') {
            $uppercaseKeyword = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($subQuery) use ($uppercaseKeyword) {
                $subQuery
                    ->orWhereRaw('UPPER(e.emp_id) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(e.name) LIKE ?', ["%{$uppercaseKeyword}%"]);
            });
        }

        return $queryBuilder;
    }

    #[Computed]
    public function rows()
    {
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }
};
?>


<div>

    <x-page-title
        title="Master Karyawan HR"
        subtitle="Data karyawan hrmst_employees — sumber LOV crew Kamar Operasi (asisten, instrument, ON LOOP) & pra-induksi" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">

                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col flex-1 min-w-0 gap-2 sm:flex-row">
                        <div class="w-full min-w-0 sm:flex-1">
                            <x-input-label for="searchKeyword" value="Cari Karyawan" class="sr-only" />
                            <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari Emp ID / nama..." class="block w-full" />
                        </div>

                        <div class="w-full sm:w-36 sm:shrink-0">
                            <x-input-label for="filterStatus" value="Status" class="sr-only" />
                            <x-select-input id="filterStatus" wire:model.live="filterStatus">
                                <option value="E">Aktif</option>
                                <option value="D">Non-aktif</option>
                                <option value="semua">Semua status</option>
                            </x-select-input>
                        </div>

                        <div class="w-full sm:w-44 sm:shrink-0">
                            <x-input-label for="filterPetugasOk" value="Petugas OK" class="sr-only" />
                            <x-select-input id="filterPetugasOk" wire:model.live="filterPetugasOk">
                                <option value="semua">Semua karyawan</option>
                                <option value="Y">Petugas OK saja</option>
                            </x-select-input>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 shrink-0">
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        <x-primary-button type="button" wire:click="openCreate" class="whitespace-nowrap">
                            + Tambah Karyawan
                        </x-primary-button>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>


            {{-- TABLE --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-left">
                                <th>Karyawan</th>
                                <th>Unit Kerja / Jabatan</th>
                                <th>Petugas OK</th>
                                <th>Status</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $row)
                                <tr wire:key="karyawan-hr-row-{{ $row->emp_id }}">
                                    {{-- KARYAWAN: nama + emp id · jenis kelamin --}}
                                    <td class="px-6 py-3 space-y-0.5 align-middle">
                                        <div class="font-semibold leading-tight text-ink dark:text-gray-100">
                                            {{ $row->name ?: '-' }}
                                        </div>
                                        <div class="text-sm leading-tight text-muted dark:text-gray-400">
                                            <span class="font-mono">{{ $row->emp_id }}</span>
                                            @if ($row->sex === 'L' || $row->sex === 'P')
                                                · {{ $row->sex === 'L' ? 'Laki-laki' : 'Perempuan' }}
                                            @endif
                                        </div>
                                    </td>

                                    {{-- UNIT KERJA / JABATAN --}}
                                    <td class="px-6 py-3 space-y-0.5 align-middle">
                                        <div class="font-semibold leading-tight text-brand dark:text-emerald-400">
                                            {{ $row->wu_desc ?: '-' }}
                                        </div>
                                        <div class="text-sm leading-tight text-muted dark:text-gray-400">
                                            {{ $row->ws_desc ?: '-' }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        @php $isPetugasOk = (string) $row->omlop_status === 'Y'; @endphp
                                        <x-toggle wire:key="toggle-ok-{{ $row->emp_id }}-{{ $isPetugasOk ? 1 : 0 }}"
                                            :current="$isPetugasOk ? 'Y' : 'N'" trueValue="Y" falseValue="N"
                                            wireClick="togglePetugasOk('{{ $row->emp_id }}')">
                                            {{ $isPetugasOk ? 'Ya' : 'Tidak' }}
                                        </x-toggle>
                                    </td>

                                    <td class="px-6 py-4">
                                        @php $isActive = (string) $row->status_ed === 'E'; @endphp
                                        {{-- wire:key sertakan nilai supaya toggle re-init saat status berubah --}}
                                        <x-toggle wire:key="toggle-active-{{ $row->emp_id }}-{{ $isActive ? 1 : 0 }}"
                                            :current="$isActive ? 'E' : 'D'" trueValue="E" falseValue="D"
                                            wireClick="toggleActive('{{ $row->emp_id }}')">
                                            {{ $isActive ? 'Aktif' : 'Non-aktif' }}
                                        </x-toggle>
                                    </td>

                                    <td class="ds-c px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->emp_id }}')" />

                                            <x-action-delete :action="'requestDelete(\'' . $row->emp_id . '\')'" title="Hapus Karyawan"
                                                message="Yakin hapus karyawan {{ $row->name }} ({{ $row->emp_id }})?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Data belum ada.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>


            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::master.master-karyawan-hr.master-karyawan-hr-actions wire:key="master-karyawan-hr-actions" />
        </div>
    </div>
</div>
