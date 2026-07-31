<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['daftar-kamar-operasi-toolbar'];

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    public string $searchKeyword = '';
    public string $filterTanggal = '';
    public string $filterStatus = '';
    public int $itemsPerPage = 10;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
    }

    public function updatedSearchKeyword(): void
    {
        // Tidak incrementVersion — remount toolbar di tengah ketik bikin input
        // kehilangan focus, backspace berikutnya memicu browser back.
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-kamar-operasi-toolbar');
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-kamar-operasi-toolbar');
    }

    /* -------------------------
     | Reset filters
     * ------------------------- */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus']);
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('daftar-kamar-operasi-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Open Detail (actions modal)
     * ------------------------- */
    public function openDetail($okReg): void
    {
        $this->dispatch('kamar-operasi-actions.open', okReg: (string) $okReg);
    }

    /* -------------------------
     | Refresh after save
     * ------------------------- */
    #[On('refresh-after-kamar-operasi.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('daftar-kamar-operasi-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Date range helper
     * ------------------------- */
    private function dateRange(): array
    {
        try {
            $tanggal = Carbon::createFromFormat('d/m/Y', trim($this->filterTanggal))->startOfDay();
        } catch (\Exception $exception) {
            $tanggal = now()->startOfDay();
        }
        return [$tanggal, (clone $tanggal)->endOfDay()];
    }

    /* -------------------------
     | Base Query — RSTXN_OKS
     |
     | Total tarif = 11 pos yang sama persis dengan pos yang ditransfer ke
     | rstxn_rioks (lihat KamarOperasiTrait::POS_TARIF). Dijumlah di SQL supaya
     | tidak perlu tarik semua kolom fee ke PHP hanya untuk menampilkan total.
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $query = DB::table('rstxn_oks as o')
            ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'o.rihdr_no')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
            ->leftJoin('rsmst_doctors as dopr', 'dopr.dr_id', '=', 'o.dr_id')
            ->leftJoin('rsmst_doctors as danes', 'danes.dr_id', '=', 'o.dr_id_ok')
            ->select(
                'o.ok_reg',
                'o.rihdr_no',
                'o.ok_status',
                DB::raw("to_char(o.ok_date,'dd/mm/yyyy hh24:mi:ss') as ok_date_display"),
                'h.reg_no',
                'h.ri_status',
                'p.reg_name',
                'p.sex',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                'p.address',
                'r.room_name',
                'dopr.dr_name as operator_name',
                'danes.dr_name as anestesi_name',
                DB::raw('(NVL(o.oprdoc_fee,0) + NVL(o.anesdoc_fee,0) + NVL(o.changeanesdoc_fee,0)
                        + NVL(o.instrument_fee,0) + NVL(o.asistopr_fee,0) + NVL(o.asistanes_fee,0)
                        + NVL(o.omlop_fee,0) + NVL(o.ok_fee,0) + NVL(o.rr_fee,0)
                        + NVL(o.equipment_fee,0) + NVL(o.rentequipment_fee,0)) as total_fee'),
                DB::raw("(
                    SELECT string_agg(a.accdoc_desc)
                    FROM rstxn_okacts t
                    JOIN rsmst_accdocs a ON a.accdoc_id = t.accdoc_id
                    WHERE t.ok_reg = o.ok_reg
                ) AS tindakan_desc"),
            )
            ->whereBetween('o.ok_date', [$start, $end])
            ->orderBy('o.ok_reg', 'desc');

        if ($this->filterStatus !== '') {
            $query->where('o.ok_status', $this->filterStatus);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $keyword = mb_strtoupper($search);
            $query->where(function ($subQuery) use ($search, $keyword) {
                if (ctype_digit($search)) {
                    $subQuery->orWhere('o.ok_reg', 'like', "%{$search}%")
                        ->orWhere('h.reg_no', 'like', "%{$search}%");
                }
                $subQuery->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$keyword}%");
            });
        }

        return $query;
    }

    /* -------------------------
     | Rows with Pagination
     * ------------------------- */
    #[Computed]
    public function rows()
    {
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }
};
?>

<div>
    <x-page-title
        title="Transaksi Kamar Operasi"
        subtitle="Tindakan operasi, tarif, dan transfer biaya ke rawat inap" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3"
                    wire:key="{{ $this->renderKey('daftar-kamar-operasi-toolbar', []) }}">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari No Txn / No RM / Nama Pasien..." />
                        </div>
                    </div>

                    {{-- FILTER TANGGAL --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tanggal" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live="filterTanggal"
                                class="block w-full pl-10 sm:w-40" placeholder="dd/mm/yyyy" />
                        </div>
                    </div>

                    {{-- FILTER STATUS --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-40">
                            <option value="">Semua</option>
                            <option value="A">Proses Transaksi</option>
                            <option value="L">Transaksi Selesai</option>
                            <option value="F">Dibatalkan</option>
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center gap-2 ml-auto">
                        <x-primary-button type="button" wire:click="$dispatch('kamar-operasi-tambah.open')">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                Tambah Operasi
                            </span>
                        </x-primary-button>

                        {{-- Tombol standar Refresh + Reset (komponen; tanpa label kolom) --}}
                        <x-toolbar-refresh-reset :label="null" />

                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                    </div>

                </div>
            </div>

            {{-- TABLE --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-base -mt-3 border-separate border-spacing-y-3">

                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-6 py-3">No</th>
                                <th class="px-6 py-3">Pasien</th>
                                <th class="px-6 py-3">Transaksi</th>
                                <th class="px-6 py-3">Tindakan / Dokter</th>
                                <th class="px-6 py-3 text-right">Total Tarif</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $index => $row)
                                @php
                                    $statusCode = strtoupper($row->ok_status ?? '');
                                    // Legacy NVL(ok_status,'A'): kosong = masih proses.
                                    $statusCode = $statusCode !== '' ? $statusCode : 'A';

                                    [$statusText, $statusVariant] = match ($statusCode) {
                                        'A' => ['Proses Transaksi', 'warning'],
                                        'L' => ['Transaksi Selesai', 'success'],
                                        'F' => ['Dibatalkan', 'error'],
                                        default => [$statusCode, 'gray'],
                                    };

                                    // Status kunjungan induk (rawat inap).
                                    $statusInduk = strtoupper($row->ri_status ?? '');
                                    $statusIndukMuted = 'bg-surface-soft text-muted border-hairline';
                                    [$indukLabel, $indukClass] = match ($statusInduk) {
                                        ''  => ['-', $statusIndukMuted],
                                        'I' => ['Dirawat', 'bg-brand/10 text-brand border-brand/30'],
                                        'P' => ['Pulang', 'bg-amber-100 text-amber-700 border-amber-200'],
                                        'L' => ['Pulang', $statusIndukMuted],
                                        'F' => ['Batal', 'bg-error/10 text-error border-error/30'],
                                        default => [$statusInduk, $statusIndukMuted],
                                    };

                                    // Transfer biaya ke rawat inap hanya boleh saat pasien masih
                                    // dirawat (ri_status = 'I'). Transaksi yang masih 'A' sementara
                                    // pasiennya sudah pulang = biaya TIDAK akan pernah masuk tagihan;
                                    // ditandai supaya petugas/supervisor melihatnya.
                                    $transferTerkunci = $statusCode === 'A' && $statusInduk !== 'I';
                                @endphp

                                <tr wire:key="daftar-kamar-operasi-{{ $row->ok_reg ?? $index }}"
                                    class="transition rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700 hover:shadow-lg
                                        {{ $transferTerkunci
                                            ? 'bg-red-50 dark:bg-red-900/10 border-l-4 border-red-500 hover:bg-red-100 dark:hover:bg-red-900/20'
                                            : 'bg-canvas dark:bg-gray-900 hover:bg-surface-soft dark:hover:bg-gray-800' }}">

                                    {{-- NO --}}
                                    <td class="px-6 py-4 align-top">
                                        <div class="text-sm font-mono text-muted">
                                            {{ $this->rows->firstItem() + $index }}
                                        </div>
                                    </td>

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-4 space-y-1 align-top">
                                        <x-list.identitas-pasien :regNo="$row->reg_no" :nama="$row->reg_name" :sex="$row->sex" :tglLahir="$row->birth_date" :alamat="$row->address" :collapseUmur="false" />
                                    </td>

                                    {{-- TRANSAKSI --}}
                                    <td class="px-6 py-4 space-y-2 align-top">
                                        <div class="text-base font-medium text-body dark:text-gray-300">
                                            No Txn: {{ $row->ok_reg }}
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold border rounded-full bg-purple-100 text-purple-700 border-purple-200">
                                                Rawat Inap
                                            </span>
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold border rounded-full {{ $indukClass }}">
                                                {{ $indukLabel }}
                                            </span>
                                        </div>
                                        <div class="font-mono text-sm text-body dark:text-gray-300">
                                            {{ $row->ok_date_display ?? '-' }}
                                        </div>
                                        <div class="text-sm text-muted dark:text-gray-400">
                                            No Inap: {{ $row->rihdr_no }}
                                            @if (!empty($row->room_name))
                                                <span class="ml-1">&middot; {{ $row->room_name }}</span>
                                            @endif
                                        </div>
                                        @if ($transferTerkunci)
                                            <div class="text-xs font-semibold text-red-700 dark:text-red-300">
                                                Belum ditransfer &mdash; pasien sudah tidak dirawat
                                            </div>
                                        @endif
                                    </td>

                                    {{-- TINDAKAN / DOKTER --}}
                                    <td class="px-6 py-4 align-top space-y-1">
                                        <div class="max-w-xs text-sm truncate text-muted dark:text-gray-400"
                                            title="{{ $row->tindakan_desc ?? '' }}">
                                            {{ $row->tindakan_desc ?? '-' }}
                                        </div>
                                        <div class="max-w-xs text-sm">
                                            <span class="text-muted">Operator:</span>
                                            <span class="ml-1 font-medium text-ink dark:text-gray-200">{{ $row->operator_name ?? '-' }}</span>
                                        </div>
                                        <div class="max-w-xs text-sm">
                                            <span class="text-muted">Anestesi:</span>
                                            <span class="ml-1 font-medium text-ink dark:text-gray-200">{{ $row->anestesi_name ?? '-' }}</span>
                                        </div>
                                    </td>

                                    {{-- TOTAL TARIF --}}
                                    <td class="px-6 py-4 text-right align-top">
                                        <div class="text-sm font-bold text-ink dark:text-gray-200 whitespace-nowrap">
                                            Rp {{ number_format($row->total_fee ?? 0) }}
                                        </div>
                                    </td>

                                    {{-- STATUS --}}
                                    <td class="px-6 py-4 align-top">
                                        <x-badge :variant="$statusVariant">
                                            {{ $statusText }}
                                        </x-badge>
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-6 py-4 text-center align-top">
                                        <x-primary-button type="button"
                                            wire:click="openDetail('{{ $row->ok_reg }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="openDetail('{{ $row->ok_reg }}')">
                                            <span wire:loading.remove wire:target="openDetail('{{ $row->ok_reg }}')"
                                                class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                                Detail Operasi
                                            </span>
                                            <span wire:loading wire:target="openDetail('{{ $row->ok_reg }}')"
                                                class="flex items-center gap-1.5">
                                                <x-loading /> Memuat...
                                            </span>
                                        </x-primary-button>
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-muted-soft">
                                        <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        <p class="text-sm">Tidak ada transaksi kamar operasi ditemukan</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    </table>
                </div>

                {{-- PAGINATION --}}
                @if ($this->rows->hasPages())
                    <div class="px-6 py-3 border-t border-hairline dark:border-gray-700">
                        {{ $this->rows->links() }}
                    </div>
                @endif

            </div>

        </div>
    </div>

    {{-- CHILD: Modal transaksi kamar operasi --}}
    <livewire:pages::transaksi.penunjang.kamar-operasi.daftar-kamar-operasi-actions wire:key="kamar-operasi-actions-modal" />

    {{-- CHILD: Tambah transaksi operasi baru --}}
    <livewire:pages::transaksi.penunjang.kamar-operasi.daftar-kamar-operasi-tambah-actions wire:key="kamar-operasi-tambah-modal" />
</div>
