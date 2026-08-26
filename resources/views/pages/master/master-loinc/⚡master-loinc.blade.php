<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public string $filterClass = '';
    public int $itemsPerPage = 10;

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedFilterClass(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterClass']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->dispatch('master.loinc.openCreate');
    }

    public function openEdit(string $loincCode): void
    {
        $this->dispatch('master.loinc.openEdit', loincCode: $loincCode);
    }

    public function requestDelete(string $loincCode): void
    {
        $this->dispatch('master.loinc.requestDelete', loincCode: $loincCode);
    }

    #[On('master.loinc.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    /** Daftar kelas LOINC untuk filter — hanya bergantung isi tabel, bukan filter lain. */
    #[Computed]
    public function classList(): array
    {
        return DB::table('rsmst_loinc_codes')
            ->select('loinc_class')
            ->whereNotNull('loinc_class')
            ->distinct()
            ->orderBy('loinc_class')
            ->pluck('loinc_class')
            ->all();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('rsmst_loinc_codes')
            ->select('loinc_code', 'display', 'display_id', 'component', 'loinc_class')
            ->orderBy('display_id')
            ->orderBy('display');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(display) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(display_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(component) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(loinc_code) LIKE ?', ["%{$kw}%"]);
            });
        }

        if (trim($this->filterClass) !== '') {
            $q->where('loinc_class', $this->filterClass);
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    <x-page-title
        title="Master LOINC"
        subtitle="Kode LOINC untuk pemeriksaan lab & observasi — dipakai LOV dan pengiriman SATUSEHAT" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    <div class="flex items-center gap-3">
                        <div class="w-full lg:w-96">
                            <x-input-label for="searchKeyword" value="Cari LOINC" class="sr-only" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari kode / nama Inggris / nama Indonesia / komponen..."
                                class="block w-full" />
                        </div>
                        <div class="w-44">
                            <x-input-label for="filterClass" value="Kelas" class="sr-only" />
                            <x-select-input id="filterClass" wire:model.live="filterClass">
                                <option value="">Semua kelas</option>
                                @foreach ($this->classList as $kelas)
                                    <option value="{{ $kelas }}">{{ $kelas }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2">
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
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Kode LOINC
                        </x-primary-button>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            {{-- TABLE WRAPPER --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th>Kode</th>
                                <th>Nama Indonesia</th>
                                <th>Display (LOINC)</th>
                                <th>Komponen</th>
                                <th>Kelas</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="loinc-{{ $row->loinc_code }}">
                                    <td class="ds-td-token">{{ $row->loinc_code }}</td>
                                    <td class="ds-td-strong">{{ $row->display_id ?: '—' }}</td>
                                    <td>{{ $row->display }}</td>
                                    <td>{{ $row->component ?: '—' }}</td>
                                    <td>
                                        @if ($row->loinc_class)
                                            <x-badge variant="info">{{ $row->loinc_class }}</x-badge>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="ds-c">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->loinc_code }}')" />
                                            <x-action-delete :action="'requestDelete(\'' . $row->loinc_code . '\')'"
                                                title="Hapus Kode LOINC"
                                                message="Yakin hapus kode {{ $row->loinc_code }} ({{ $row->display }})?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Data LOINC tidak ditemukan.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION --}}
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::master.master-loinc.master-loinc-actions wire:key="master-loinc-actions" />

        </div>
    </div>
</div>
