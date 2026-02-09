<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /* -------------------------
     | Filter & Pagination State
     * ------------------------- */
    public string $searchKeyword = '';
    public int $itemsPerPage = 7;

    /* -------------------------
     | Update Search Keyword
     * Fungsi: Reset halaman saat keyword berubah
     * ------------------------- */
    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    /* -------------------------
     | Update Items Per Page
     * Fungsi: Reset halaman saat jumlah item per halaman berubah
     * ------------------------- */
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /* -------------------------
     | Open Create Modal
     * Fungsi: Trigger modal create di child component
     * ------------------------- */
    public function openCreate(): void
    {
        $this->dispatch('master.obat.openCreate');
    }

    /* -------------------------
     | Open Edit Modal
     * Fungsi: Trigger modal edit di child component
     * ------------------------- */
    public function openEdit(string $productId): void
    {
        $this->dispatch('master.obat.openEdit', productId: $productId);
    }

    /* -------------------------
     | Request Delete
     * Fungsi: Delegate proses delete ke child component (actions)
     * ------------------------- */
    public function requestDelete(string $productId): void
    {
        $this->dispatch('master.obat.requestDelete', productId: $productId);
    }
    
    /* -------------------------
     | Refresh After Saved
     * Fungsi: Refresh grid setelah data disimpan dari child component
     * ------------------------- */
    #[On('master.obat.saved')]
    public function refreshAfterSaved(): void
    {
        // resetPage kadang tidak trigger kalau sudah di page 1 → paksa refresh
        $this->dispatch('$refresh');
    }

    /* -------------------------
     | Base Query
     * Fungsi: Query builder dasar dengan filter search
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        $searchKeyword = trim($this->searchKeyword);

        $queryBuilder = DB::table('immst_products')
            ->select(
                'product_id',
                'product_name',
                'kode',
                'sales_price',
                'cost_price',
                'stock',
                'stockwh',
                'product_status',
                'active_status'
            )
            ->orderBy('product_name', 'asc');

        // Filter berdasarkan keyword pencarian
        if ($searchKeyword !== '') {
            $uppercaseKeyword = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($subQuery) use ($uppercaseKeyword, $searchKeyword) {
                // Jika keyword adalah angka, cari di product_id
                if (ctype_digit($searchKeyword)) {
                    $subQuery->orWhere('product_id', $searchKeyword);
                }

                // Cari di kolom text (case-insensitive)
                $subQuery
                    ->orWhereRaw('UPPER(product_name) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(kode) LIKE ?', ["%{$uppercaseKeyword}%"]);
            });
        }

        return $queryBuilder;
    }

    /* -------------------------
     | Rows (Paginated Data)
     * Fungsi: Data obat dengan pagination
     * ------------------------- */
    #[Computed]
    public function rows()
    {
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }
};
?>


<div>

    {{-- HEADER --}}
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Master Obat
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Kelola data obat & produk untuk aplikasi
            </p>
        </div>
    </header>

    {{-- CONTENT --}}
    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter + Action --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">

                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Obat" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari obat..." class="block w-full" />
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center justify-end gap-2">
                        {{-- Per Page Selector --}}
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="7">7</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        {{-- Tambah Obat Button --}}
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Obat
                        </x-primary-button>
                    </div>
                </div>
            </div>


            {{-- TABLE WRAPPER: card --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA (yang boleh scroll) --}}
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        {{-- TABLE HEAD (sticky) --}}
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">ID</th>
                                <th class="px-4 py-3 font-semibold">KODE</th>
                                <th class="px-4 py-3 font-semibold">NAMA PRODUK</th>
                                <th class="px-4 py-3 font-semibold">HARGA BELI</th>
                                <th class="px-4 py-3 font-semibold">HARGA JUAL</th>
                                <th class="px-4 py-3 font-semibold">STOK</th>
                                <th class="px-4 py-3 font-semibold">STOK WH</th>
                                <th class="px-4 py-3 font-semibold">STATUS</th>
                                <th class="px-4 py-3 font-semibold">AKSI</th>
                            </tr>
                        </thead>

                        {{-- TABLE BODY --}}
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                            <tr wire:key="obat-row-{{ $row->product_id }}"
                                class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                <td class="px-4 py-3">{{ $row->product_id }}</td>
                                <td class="px-4 py-3 font-medium">{{ $row->kode }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $row->product_name }}</td>
                                <td class="px-4 py-3">{{ number_format($row->cost_price ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">{{ number_format($row->sales_price ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">{{ $row->stock ?? 0 }}</td>
                                <td class="px-4 py-3">{{ $row->stockwh ?? 0 }}</td>

                                {{-- Status Badge --}}
                                <td class="px-4 py-3">
                                    <x-badge :variant="(string) $row->active_status === '1' ? 'success' : 'gray'">
                                        {{ (string) $row->active_status === '1' ? 'Aktif' : 'Tidak Aktif' }}
                                    </x-badge>
                                </td>

                                {{-- Action Buttons --}}
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        {{-- Edit Button --}}
                                        <x-outline-button type="button" wire:click="openEdit('{{ $row->product_id }}')">
                                            Edit
                                        </x-outline-button>

                                        {{-- Delete Button with Confirmation --}}
                                        <x-confirm-button variant="danger"
                                            :action="'requestDelete(\'' . $row->product_id . '\')'" title="Hapus Obat"
                                            message="Yakin hapus data obat {{ $row->product_name }}?"
                                            confirmText="Ya, hapus" cancelText="Batal">
                                            Hapus
                                        </x-confirm-button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            {{-- Empty State --}}
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                    Data belum ada.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION STICKY di bawah card --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>


            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::master.master-obat.master-obat-actions />
        </div>
    </div>
</div>