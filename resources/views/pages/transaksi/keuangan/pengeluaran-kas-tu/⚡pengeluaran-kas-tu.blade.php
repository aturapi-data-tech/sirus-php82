<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int $itemsPerPage = 10;
    public string $filterBulan = '';

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void { $this->resetPage(); }
    public function updatedFilterBulan(): void { $this->resetPage(); }

    /** Tombol Reset toolbar (x-toolbar-refresh-reset) — kembali ke bulan berjalan, pencarian kosong. */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->resetPage();
    }

    /* ── Pemicu ke modal entri (child actions) ── */
    public function openCreate(): void
    {
        $this->dispatch('pengeluaran-kas.openCreate');
    }

    public function openEdit(string $tucashkNo): void
    {
        $this->dispatch('pengeluaran-kas.openEdit', tucashkNo: $tucashkNo);
    }

    public function requestDelete(string $tucashkNo): void
    {
        $this->dispatch('pengeluaran-kas.requestDelete', tucashkNo: $tucashkNo);
    }

    #[On('pengeluaran-kas.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    /* ===============================
     | QUERY
     =============================== */
    protected function filteredQuery()
    {
        $query = DB::table('rstxn_tucashks as a')
            ->leftJoin('acmst_accounts as b', 'a.acc_id', '=', 'b.acc_id')
            ->leftJoin('acmst_accounts as c', 'a.acc_id_kas', '=', 'c.acc_id')
            ->leftJoin('immst_employers as d', 'a.emp_id', '=', 'd.emp_id');

        if ($this->searchKeyword !== '') {
            $upper = strtoupper($this->searchKeyword);
            $query->where(function ($q) use ($upper) {
                $q->whereRaw('UPPER(a.tucashk_desc) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(b.acc_name) LIKE ?', ["%{$upper}%"])
                    ->orWhere('a.tucashk_no', 'like', "%{$this->searchKeyword}%");
            });
        }

        if ($this->filterBulan !== '') {
            $query->whereRaw("TO_CHAR(a.tucashk_date,'MM/YYYY') = ?", [$this->filterBulan]);
        }

        return $query;
    }

    #[Computed]
    public function rows()
    {
        return $this->filteredQuery()
            ->select([
                'a.tucashk_no',
                DB::raw("to_char(a.tucashk_date,'dd/mm/yyyy hh24:mi:ss') as tucashk_date_display"),
                'a.shift',
                'a.tucashk_desc',
                'a.tucashk_nominal',
                'a.acc_id',
                'b.acc_name',
                'a.acc_id_kas',
                'c.acc_name as acc_name_kas',
                'a.emp_id',
                'd.emp_name',
                'a.tucashk_status',
            ])
            ->orderByDesc('a.tucashk_date')
            ->orderByDesc('a.shift')
            ->paginate($this->itemsPerPage);
    }

    #[Computed]
    public function totalBulan(): int
    {
        return (int) $this->filteredQuery()->sum('a.tucashk_nominal');
    }
};
?>

<div>
    <x-page-title
        title="Pengeluaran Kas TU"
        subtitle="Pencatatan pengeluaran kas (Cash-Out) di luar transaksi pelayanan RS" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR — pola Antrian Kasir RJ: label di atas isian, ikon, Refresh/Reset baku --}}
            <div class="sticky z-30 px-4 py-3 border-b bg-surface-soft border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari No / Keterangan / Akun..." class="block w-full pl-10" />
                        </div>
                    </div>

                    {{-- BULAN --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live.debounce.300ms="filterBulan"
                                placeholder="mm/yyyy" class="block w-full pl-10 sm:w-36" />
                        </div>
                    </div>

                    {{-- PER HALAMAN --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Per Hal." />
                        <x-select-input wire:model.live="itemsPerPage" class="w-full mt-1 sm:w-28">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </x-select-input>
                    </div>

                    <x-toolbar-refresh-reset />

                    {{-- Total periode — angka besar warna brand --}}
                    <div class="flex items-center gap-2 px-4 py-2 ml-auto border rounded-xl whitespace-nowrap bg-brand-green/5 border-brand-green/25 dark:bg-brand-lime/10 dark:border-brand-lime/25">
                        <span class="text-[11px] font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                            Total Periode {{ $filterBulan ?: 'semua' }}
                        </span>
                        <svg class="w-6 h-6 shrink-0 text-brand-green dark:text-brand-lime" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 10h18M7 15h2m4 0h4M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" />
                        </svg>
                        <span class="text-2xl font-bold leading-none text-brand-green dark:text-brand-lime">
                            Rp {{ number_format($this->totalBulan) }}
                        </span>
                    </div>

                    @can('keuangan.pengeluaran-kas-tu.tambah')
                    <x-primary-button type="button" wire:click="openCreate">
                        <x-ikon nama="tambah" />
                        Tambah Pengeluaran Kas
                    </x-primary-button>
                    @endcan
                </div>

                {{-- Timestamp refresh --}}
                <div class="mt-1 text-xs text-muted">
                    Data Terakhir: {{ now()->format('d/m/Y H:i:s') }}
                </div>
            </div>

            {{-- TABEL --}}
            <div class="flex flex-col flex-1 min-h-0 mt-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full -mt-2 text-base border-separate border-spacing-y-2">
                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr class="text-sm font-semibold tracking-wide text-left uppercase text-muted dark:text-gray-300">
                                {{-- Lebar tetap untuk kolom ringkas; Keterangan mengambil sisa ruang --}}
                                <th class="px-6 py-3 w-56">Transaksi</th>
                                <th class="px-4 py-3">Keterangan / Akun</th>
                                <th class="px-4 py-3 w-64">Kas / Petugas</th>
                                <th class="px-4 py-3 text-right w-44">Nominal</th>
                                <th class="px-4 py-3 text-center w-52">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($this->rows as $row)
                                <tr wire:key="co-row-{{ $row->tucashk_no }}"
                                    class="transition shadow-sm bg-canvas dark:bg-gray-900 hover:shadow-md hover:bg-surface-soft dark:hover:bg-gray-800 rounded-2xl ring-1 ring-hairline dark:ring-gray-700">

                                    {{-- TRANSAKSI: nomor + tanggal | shift --}}
                                    <td class="px-6 py-4 space-y-1 align-top whitespace-nowrap">
                                        <div class="font-mono text-sm font-bold text-blue-600 dark:text-blue-400">
                                            CO #{{ $row->tucashk_no }}
                                        </div>
                                        <div class="text-xs text-muted dark:text-gray-400">
                                            {{ $row->tucashk_date_display ?? '-' }} | Shift {{ $row->shift ?? '-' }}
                                        </div>
                                    </td>

                                    {{-- KETERANGAN / AKUN PENGELUARAN --}}
                                    <td class="px-4 py-4 space-y-1 align-top">
                                        <div class="text-sm font-semibold text-ink dark:text-gray-100">
                                            {{ $row->tucashk_desc ?? '-' }}
                                        </div>
                                        <div class="text-sm text-body dark:text-gray-300">
                                            {{ $row->acc_name ?? '-' }}
                                            <span class="font-mono text-xs text-muted-soft">{{ $row->acc_id }}</span>
                                        </div>
                                    </td>

                                    {{-- KAS / PETUGAS --}}
                                    <td class="px-4 py-4 space-y-1 align-top">
                                        <div class="text-xs text-muted dark:text-gray-400">
                                            Kas: <span class="font-medium text-body dark:text-gray-300">{{ $row->acc_name_kas ?? '-' }}</span>
                                            <span class="font-mono text-muted-soft">({{ $row->acc_id_kas }})</span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-1.5 text-xs text-muted dark:text-gray-400">
                                            Petugas:
                                            <span class="font-medium text-success dark:text-success">{{ $row->emp_name ?? $row->emp_id ?? '-' }}</span>
                                            <x-badge variant="success">Posted</x-badge>
                                        </div>
                                    </td>

                                    {{-- NOMINAL — kolom sendiri, rata kanan --}}
                                    <td class="px-4 py-4 text-right align-top">
                                        <div class="font-mono text-base font-bold whitespace-nowrap text-red-600 dark:text-red-400">
                                            Rp {{ number_format($row->tucashk_nominal ?? 0) }}
                                        </div>
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-4 py-4 align-top">
                                        {{-- Edit kiri, Hapus kanan, dipisah garis — jarak lebar supaya tidak salah klik --}}
                                        <div class="flex items-center justify-between gap-4 mx-auto w-44">
                                            @can('kasTu.koreksi')
                                                <x-action-edit wire:click="openEdit('{{ $row->tucashk_no }}')" />
                                            @endcan
                                            @if (auth()->user()?->can('kasTu.koreksi') && auth()->user()?->can('kasTu.hapus'))
                                                <span class="w-px h-8 bg-hairline dark:bg-gray-700" aria-hidden="true"></span>
                                            @endif
                                            @can('kasTu.hapus')
                                                <x-hapus-button :action="'requestDelete(\'' . $row->tucashk_no . '\')'" title="Hapus Transaksi" message="Yakin ingin menghapus transaksi #{{ $row->tucashk_no }}?" />
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-16 text-center text-muted dark:text-gray-400">
                                        Tidak ada data pengeluaran kas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="sticky bottom-0 z-10 px-4 py-3 border-t bg-canvas border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- Modal entri / koreksi (child actions) --}}
            <livewire:pages::transaksi.keuangan.pengeluaran-kas-tu.pengeluaran-kas-tu-actions
                wire:key="pengeluaran-kas-tu-actions" />
        </div>
    </div>
</div>
