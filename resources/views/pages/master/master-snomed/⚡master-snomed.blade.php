<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public string $filterValueSet = '';
    public int $itemsPerPage = 10;

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedFilterValueSet(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterValueSet']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->dispatch('master.snomed.openCreate');
    }

    public function openEdit(string $snomedCode): void
    {
        $this->dispatch('master.snomed.openEdit', snomedCode: $snomedCode);
    }

    public function requestDelete(string $snomedCode): void
    {
        $this->dispatch('master.snomed.requestDelete', snomedCode: $snomedCode);
    }

    #[On('master.snomed.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    /** Value set yang ada di tabel — hanya bergantung isi tabel, bukan filter lain. */
    #[Computed]
    public function valueSetList(): array
    {
        return DB::table('rsmst_snomed_codes')
            ->select('value_set')
            ->whereNotNull('value_set')
            ->distinct()
            ->orderBy('value_set')
            ->pluck('value_set')
            ->all();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('rsmst_snomed_codes')
            ->select('snomed_code', 'display_en', 'display_id', 'value_set')
            ->orderBy('value_set')
            ->orderBy('display_id')
            ->orderBy('display_en');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(display_en) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(display_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(snomed_code) LIKE ?', ["%{$kw}%"]);
            });
        }

        if (trim($this->filterValueSet) !== '') {
            $q->where('value_set', $this->filterValueSet);
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    <x-page-title
        title="Master SNOMED CT"
        subtitle="Kode SNOMED CT untuk keluhan, prosedur & alergi — dipakai LOV dan pengiriman SATUSEHAT" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    <div class="flex items-center gap-3">
                        <div class="w-full lg:w-96">
                            <x-input-label for="searchKeyword" value="Cari SNOMED" class="sr-only" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari kode / istilah Inggris / istilah Indonesia..."
                                class="block w-full" />
                        </div>
                        <div class="w-48">
                            <x-input-label for="filterValueSet" value="Value set" class="sr-only" />
                            <x-select-input id="filterValueSet" wire:model.live="filterValueSet">
                                <option value="">Semua value set</option>
                                @foreach ($this->valueSetList as $vs)
                                    <option value="{{ $vs }}">{{ $vs }}</option>
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
                            + Tambah Kode SNOMED
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
                                <th>Istilah Indonesia</th>
                                <th>Display (SNOMED)</th>
                                <th>Value Set</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="snomed-{{ $row->snomed_code }}">
                                    <td class="ds-td-token">{{ $row->snomed_code }}</td>
                                    <td class="ds-td-strong">{{ $row->display_id ?: '—' }}</td>
                                    <td>{{ $row->display_en }}</td>
                                    <td>
                                        <x-badge variant="info">{{ $row->value_set ?: '—' }}</x-badge>
                                    </td>
                                    <td class="ds-c">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->snomed_code }}')" />
                                            <x-action-delete :action="'requestDelete(\'' . $row->snomed_code . '\')'"
                                                title="Hapus Kode SNOMED"
                                                message="Yakin hapus kode {{ $row->snomed_code }} ({{ $row->display_en }})?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Data SNOMED tidak ditemukan.</p>
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

            <livewire:pages::master.master-snomed.master-snomed-actions wire:key="master-snomed-actions" />

        </div>
    </div>
</div>
