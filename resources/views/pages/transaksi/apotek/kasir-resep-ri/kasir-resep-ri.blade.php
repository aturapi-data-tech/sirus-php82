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
    protected array $renderAreas = ['kasir-resep-ri-toolbar'];

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    public string $searchKeyword = '';
    public string $filterTanggal = '';
    public string $filterStatus = 'A';
    public string $filterDokter = '';
    public int $itemsPerPage = 10;
    public string $autoRefresh = 'Tidak';

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
    }

    /* -------------------------
     | Updated hooks
     * ------------------------- */
    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->incrementVersion('kasir-resep-ri-toolbar');
    }

    public function updatedFilterTanggal(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDokter(): void
    {
        $this->resetPage();
        $this->incrementVersion('kasir-resep-ri-toolbar');
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('kasir-resep-ri-toolbar');
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterDokter']);
        $this->filterStatus = 'A';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('kasir-resep-ri-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Dispatch ke child actions
     * ------------------------- */
    public function openKasir(int $slsNo): void
    {
        $this->dispatch('kasir-resep-ri.open', slsNo: $slsNo);
    }

    /* -------------------------
     | Refresh setelah child save/batal
     * ------------------------- */
    #[On('refresh-after-kasir-ri.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('kasir-resep-ri-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Date range helper
     * ------------------------- */
    private function dateRange(): array
    {
        try {
            $d = Carbon::createFromFormat('d/m/Y', trim($this->filterTanggal))->startOfDay();
        } catch (\Exception $e) {
            $d = now()->startOfDay();
        }
        return [$d, (clone $d)->endOfDay()];
    }

    /* -------------------------
     | Computed: main query
     * ------------------------- */
    #[Computed]
    public function rows()
    {
        [$start, $end] = $this->dateRange();

        $query = DB::table('imtxn_slshdrs as s')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 's.reg_no')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 's.dr_id')
            ->join('rstxn_rihdrs as r', 'r.rihdr_no', '=', 's.rihdr_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'r.klaim_id')
            ->select([
                's.sls_no',
                DB::raw("to_char(s.sls_date,'dd/mm/yyyy hh24:mi:ss') as sls_date_display"),
                's.status',
                's.rihdr_no',
                's.reg_no',
                'p.reg_name',
                'p.sex',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                's.dr_id',
                'd.dr_name',
                's.shift',
                's.acte_price',
                's.sls_total',
                's.sls_bayar',
                's.sls_bon',
                's.acc_id',
                DB::raw("to_char(s.waktu_masuk_pelayanan,'dd/mm/yyyy hh24:mi') as waktu_masuk"),
                DB::raw("to_char(s.waktu_selesai_pelayanan,'dd/mm/yyyy hh24:mi') as waktu_selesai"),
                'r.ri_status',
                'r.klaim_id',
                'k.klaim_desc',
                DB::raw('(select count(*) from imtxn_slsdtls where sls_no = s.sls_no) as item_count'),
                DB::raw('(select nvl(sum(sales_price * qty),0) from imtxn_slsdtls where sls_no = s.sls_no) as subtotal'),
            ])
            ->whereNotNull('s.rihdr_no')
            ->whereBetween('s.sls_date', [$start, $end])
            ->where(DB::raw("nvl(s.status,'A')"), $this->filterStatus);

        if ($this->filterDokter !== '') {
            $query->where('s.dr_id', $this->filterDokter);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($kw, $search) {
                if (ctype_digit($search)) {
                    $q->orWhere('s.sls_no', 'like', "%{$search}%")->orWhere('s.reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('upper(p.reg_name)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('upper(s.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('upper(d.dr_name)'), 'like', "%{$kw}%");
            });
        }

        $paginator = $query->orderByDesc('s.sls_date')->paginate($this->itemsPerPage);

        $paginator->getCollection()->transform(function ($row) {
            $row->subtotal = (int) ($row->subtotal ?? 0);
            $row->acte_price = (int) ($row->acte_price ?? 0);
            $row->total_all = $row->subtotal + $row->acte_price;
            $row->sls_bayar = (int) ($row->sls_bayar ?? 0);
            $row->sls_bon = (int) ($row->sls_bon ?? 0);

            // Status SLS badge
            $row->status_text = match (strtoupper($row->status ?? 'A')) {
                'L' => 'Lunas/Bon',
                'A' => 'Belum Diproses',
                default => $row->status ?? '-',
            };
            $row->status_variant = match (strtoupper($row->status ?? 'A')) {
                'L' => 'success',
                'A' => 'warning',
                default => 'gray',
            };

            // RI status
            $row->ri_status_text = match (strtoupper($row->ri_status ?? '')) {
                'A' => 'Dirawat',
                'P' => 'Sudah Pulang',
                'B' => 'Batal',
                default => $row->ri_status ?? '-',
            };
            $row->ri_status_variant = match (strtoupper($row->ri_status ?? '')) {
                'A' => 'brand',
                'P' => 'gray',
                'B' => 'danger',
                default => 'alternative',
            };

            // Klaim
            $row->klaim_label = match ($row->klaim_id) {
                'UM' => 'UMUM',
                'JM' => 'BPJS',
                'KR' => 'Kronis',
                default => $row->klaim_desc ?? 'Asuransi Lain',
            };
            $row->klaim_variant = match ($row->klaim_id) {
                'UM' => 'success',
                'JM' => 'brand',
                'KR' => 'warning',
                default => 'alternative',
            };

            // Umur
            if (!empty($row->birth_date)) {
                try {
                    $tglLahir = Carbon::createFromFormat('d/m/Y', $row->birth_date);
                    $diff = $tglLahir->diff(now());
                    $row->umur_format = "{$diff->y} Thn {$diff->m} Bln {$diff->d} Hr";
                } catch (\Exception $e) {
                    $row->umur_format = '-';
                }
            } else {
                $row->umur_format = '-';
            }

            return $row;
        });

        return $paginator;
    }

    /* -------------------------
     | Computed: dokter filter list
     * ------------------------- */
    #[Computed]
    public function dokterList()
    {
        [$start, $end] = $this->dateRange();

        return DB::table('imtxn_slshdrs as s')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 's.dr_id')
            ->select('s.dr_id', DB::raw('max(d.dr_name) as dr_name'), DB::raw('count(distinct s.sls_no) as total_resep'))
            ->whereNotNull('s.rihdr_no')
            ->whereBetween('s.sls_date', [$start, $end])
            ->groupBy('s.dr_id')
            ->orderBy('dr_name')
            ->get();
    }
};
?>

<div>
    <div class="w-full bg-white dark:bg-gray-800 rounded-2xl">
        <div class="px-4 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3"
                    wire:key="{{ $this->renderKey('kasir-resep-ri-toolbar', []) }}">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari No SLS / No RM / Nama Pasien / Dokter..." />
                        </div>
                    </div>

                    {{-- TANGGAL --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tanggal Resep" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live="filterTanggal"
                                class="block w-full pl-10 sm:w-40" placeholder="dd/mm/yyyy" />
                        </div>
                    </div>

                    {{-- STATUS --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-44">
                            <option value="A">Belum Diproses</option>
                            <option value="L">Sudah Diproses</option>
                        </x-select-input>
                    </div>

                    {{-- DOKTER --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter" />
                        <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-48">
                            <option value="">Semua Dokter</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">
                                    {{ $dokter->dr_name }} ({{ $dokter->total_resep }})
                                </option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- AUTO REFRESH --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Auto Refresh" />
                        <x-select-input wire:model.live="autoRefresh" class="w-full mt-1 sm:w-28">
                            <option value="Ya">Ya (30s)</option>
                            <option value="Tidak">Tidak</option>
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center gap-2 ml-auto">
                        <x-secondary-button type="button" wire:click="resetFilters" class="whitespace-nowrap">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset
                        </x-secondary-button>
                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </x-select-input>
                        </div>
                    </div>

                </div>

                <div class="mt-1 text-xs text-gray-500">
                    Data Terakhir: {{ now()->format('d/m/Y H:i:s') }}
                </div>
            </div>

            {{-- AUTO REFRESH WRAPPER --}}
            @if ($autoRefresh === 'Ya')
                <div wire:poll.30s class="mt-4">
                @else
                    <div class="mt-4">
            @endif

            {{-- TABLE --}}
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-360px)] rounded-t-2xl">
                    <table class="min-w-full text-base border-separate border-spacing-y-2">

                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr
                                class="text-xs font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th class="px-4 py-3">No SLS &amp; Pasien</th>
                                <th class="px-4 py-3">Ruangan / Dokter</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Tagihan</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr
                                    class="transition bg-white dark:bg-gray-900 hover:shadow-md hover:bg-blue-50 dark:hover:bg-gray-800 rounded-xl">

                                    {{-- NO SLS & PASIEN --}}
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="flex flex-col items-center justify-center w-16 h-16 rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                                <span class="text-xl font-bold leading-none">
                                                    {{ $row->sls_no }}
                                                </span>
                                                <span class="text-[9px] font-medium mt-0.5 text-center leading-tight">
                                                    No SLS
                                                </span>
                                            </div>
                                            <div class="space-y-0.5 min-w-0">
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $row->reg_no }}
                                                </div>
                                                <div
                                                    class="text-sm font-semibold text-gray-900 dark:text-white truncate max-w-[200px]">
                                                    {{ $row->reg_name }}
                                                </div>
                                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                                    {{ $row->sex === 'L' ? 'Laki-Laki' : ($row->sex === 'P' ? 'Perempuan' : '-') }}
                                                    &bull; {{ $row->umur_format }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-500">
                                                    No RI: {{ $row->rihdr_no }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- RUANGAN / DOKTER --}}
                                    <td class="px-4 py-4 space-y-1 align-top">
                                        <div class="text-sm text-gray-700 dark:text-gray-300">
                                            {{ $row->dr_name ?? '-' }}
                                        </div>
                                        <x-badge :variant="$row->klaim_variant">
                                            {{ $row->klaim_label }}
                                        </x-badge>
                                        <x-badge :variant="$row->ri_status_variant">
                                            {{ $row->ri_status_text }}
                                        </x-badge>
                                        <div class="text-xs text-gray-500 dark:text-gray-500">
                                            {{ $row->sls_date_display }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-500">
                                            Shift {{ $row->shift ?? '-' }}
                                            &bull; {{ $row->item_count }} obat
                                        </div>
                                    </td>

                                    {{-- STATUS --}}
                                    <td class="px-4 py-4 space-y-2 align-top">
                                        <x-badge :variant="$row->status_variant">
                                            {{ $row->status_text }}
                                        </x-badge>

                                        @if ($row->status === 'L')
                                            <div class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                                                <div>Bayar:
                                                    <span
                                                        class="font-mono font-semibold text-emerald-700 dark:text-emerald-300">
                                                        Rp {{ number_format($row->sls_bayar) }}
                                                    </span>
                                                </div>
                                                @if ($row->sls_bon > 0)
                                                    <div>Bon Inap:
                                                        <span
                                                            class="font-mono font-semibold text-amber-700 dark:text-amber-300">
                                                            Rp
                                                            {{ number_format($row->total_all - $row->sls_bayar) }}
                                                        </span>
                                                    </div>
                                                @endif
                                                @if ($row->acc_id)
                                                    <div class="font-mono">{{ $row->acc_id }}</div>
                                                @endif
                                                @if ($row->waktu_selesai)
                                                    <div>Selesai: {{ $row->waktu_selesai }}</div>
                                                @endif
                                            </div>
                                        @else
                                            <div class="text-xs text-gray-500 dark:text-gray-500">
                                                Masuk: {{ $row->waktu_masuk ?? '-' }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- TAGIHAN --}}
                                    <td class="px-4 py-4 align-top text-right">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Subtotal</div>
                                        <div
                                            class="font-mono text-sm text-gray-700 dark:text-gray-300">
                                            Rp {{ number_format($row->subtotal) }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Embalase</div>
                                        <div class="font-mono text-sm text-gray-700 dark:text-gray-300">
                                            Rp {{ number_format($row->acte_price) }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total</div>
                                        <div class="font-mono text-base font-bold text-gray-900 dark:text-white">
                                            Rp {{ number_format($row->total_all) }}
                                        </div>
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex flex-col gap-2">
                                            @if ($row->status === 'L')
                                                <x-success-button
                                                    wire:click="openKasir({{ $row->sls_no }})"
                                                    class="text-xs whitespace-nowrap justify-center">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    Lihat / Cetak
                                                </x-success-button>
                                            @else
                                                @hasanyrole('Apoteker|Admin|Tu')
                                                    <x-primary-button
                                                        wire:click="openKasir({{ $row->sls_no }})"
                                                        class="text-xs whitespace-nowrap justify-center">
                                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M3 10h18M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" />
                                                        </svg>
                                                        Kasir
                                                    </x-primary-button>
                                                @else
                                                    <x-secondary-button disabled
                                                        class="text-xs whitespace-nowrap justify-center cursor-not-allowed opacity-60">
                                                        Akses ditolak
                                                    </x-secondary-button>
                                                @endhasanyrole
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"
                                        class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                            <span>Tidak ada resep rawat inap pada filter ini</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

        </div>{{-- end auto-refresh wrapper --}}

        {{-- Child action component --}}
        <livewire:pages::transaksi.apotek.kasir-resep-ri.kasir-resep-ri-actions
            wire:key="kasir-resep-ri-actions" />

    </div>
</div>
