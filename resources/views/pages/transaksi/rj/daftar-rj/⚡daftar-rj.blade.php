<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    public string $searchKeyword = '';
    public string $filterTanggal = '';
    public string $filterStatus = 'A';
    public string $filterPoli = '';
    public string $filterDokter = '';
    public int $itemsPerPage = 10;

    public function mount(): void
    {
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedFilterTanggal(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPoli(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDokter(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /* -------------------------
     | Reset filters
     * ------------------------- */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterPoli', 'filterDokter']);
        $this->filterStatus = '1';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->resetPage();
    }

    /* -------------------------
     | Child modal triggers
     * ------------------------- */
    public function openCreate(): void
    {
        $this->dispatch('daftar-rj.openCreate');
    }

    public function openEdit(string $rjNo): void
    {
        $this->dispatch('daftar-rj.openEdit', rjNo: $rjNo);
    }

    /* -------------------------
    | Request Delete (delegate ke actions)
    * ------------------------- */
    public function requestDelete(string $rjNo): void
    {
        $this->dispatch('daftar-rj.requestDelete', rjNo: $rjNo);
    }

    /* -------------------------
     | Refresh after child save
     * ------------------------- */
    #[On('daftar-rj.saved')]
    public function refreshAfterSaved(): void
    {
        $this->dispatch('$refresh');
    }

    /* -------------------------
     | Computed queries
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $labSub = DB::table('lbtxn_checkuphdrs')->select('ref_no', DB::raw('COUNT(*) as lab_status'))->where('status_rjri', 'RJ')->where('checkup_status', '!=', 'B')->groupBy('ref_no');

        $radSub = DB::table('rstxn_rjrads')->select('rj_no', DB::raw('COUNT(*) as rad_status'))->groupBy('rj_no');

        $query = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoinSub($labSub, 'lab', fn($j) => $j->on('lab.ref_no', '=', 'h.rj_no'))
            ->leftJoinSub($radSub, 'rad', fn($j) => $j->on('rad.rj_no', '=', 'h.rj_no'))
            ->select(['h.rj_no', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date_display"), 'h.reg_no', 'p.reg_name', 'p.sex', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'h.no_antrian', 'h.poli_id', 'po.poli_desc', 'h.dr_id', 'd.dr_name', 'h.klaim_id', 'h.shift', 'h.rj_status', 'h.vno_sep', DB::raw('COALESCE(lab.lab_status, 0) as lab_status'), DB::raw('COALESCE(rad.rad_status, 0) as rad_status')])
            ->whereBetween('h.rj_date', [$start, $end])
            ->orderBy('h.rj_date', 'desc')
            ->orderBy('h.no_antrian', 'asc');

        if ($this->filterStatus !== '') {
            $query->where('h.rj_status', $this->filterStatus);
        }
        if ($this->filterPoli !== '') {
            $query->where('h.poli_id', $this->filterPoli);
        }
        if ($this->filterDokter !== '') {
            $query->where('h.dr_id', $this->filterDokter);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($search, $kw) {
                if (ctype_digit($search)) {
                    $q->orWhere('h.rj_no', 'like', "%{$search}%")->orWhere('h.reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('UPPER(h.rj_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$kw}%");
            });
        }

        return $query;
    }

    private function dateRange(): array
    {
        $d = Carbon::createFromFormat('d/m/Y', $this->filterTanggal)->startOfDay();
        return [$d, (clone $d)->endOfDay()];
    }

    #[Computed]
    public function rows()
    {
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }

    /* -------------------------
     | Master data for filters
     * ------------------------- */
    #[Computed]
    public function poliList()
    {
        return DB::table('rsmst_polis')->select('poli_id', 'poli_desc', 'spesialis_status')->orderBy('poli_desc')->get();
    }

    #[Computed]
    public function dokterList()
    {
        return cache()->remember(
            "dokterList:{$this->filterTanggal}:{$this->filterStatus}:{$this->searchKeyword}",
            60, // 60 detik
            function () {
                $filterDate = $this->filterTanggal;

                $query = DB::table('rstxn_rjhdrs')
                    ->select('rstxn_rjhdrs.dr_id', DB::raw('MAX(rsmst_doctors.dr_name) as dr_name'), 'rstxn_rjhdrs.poli_id', DB::raw('MAX(rsmst_polis.poli_desc) as poli_desc'), DB::raw('COUNT(DISTINCT rstxn_rjhdrs.rj_no) as total_pasien'))
                    ->join('rsmst_doctors', 'rsmst_doctors.dr_id', '=', 'rstxn_rjhdrs.dr_id')
                    ->join('rsmst_polis', 'rsmst_polis.poli_id', '=', 'rstxn_rjhdrs.poli_id') // ✅ FILTER TANGGAL (WAJIB)
                    ->where(DB::raw("to_char(rstxn_rjhdrs.rj_date, 'dd/mm/yyyy')"), '=', $filterDate);

                // ✅ FILTER POLI (JIKA ADA)
                // if (!empty($this->filterPoli)) {
                //     $query->where('rstxn_rjhdrs.poli_id', $this->filterPoli);
                // }

                // ✅ FILTER DOKTER (JIKA ADA) - UNTUK DETAIL DOKTER
                // if (!empty($this->filterDokter)) {
                //     $query->where('rstxn_rjhdrs.dr_id', $this->filterDokter);
                // }

                // ✅ FILTER STATUS (JIKA ADA)
                if (!empty($this->filterStatus)) {
                    $query->where('rstxn_rjhdrs.rj_status', $this->filterStatus);
                }

                // ✅ FILTER SEARCH (JIKA ADA)
                if (!empty($this->searchKeyword) && strlen($this->searchKeyword) >= 2) {
                    $keyword = strtoupper($this->searchKeyword);
                    $query->where(function ($q) use ($keyword) {
                        $q->where(DB::raw('UPPER(rsmst_doctors.dr_name)'), 'LIKE', "%{$keyword}%")->orWhere(DB::raw('UPPER(rsmst_polis.poli_desc)'), 'LIKE', "%{$keyword}%");
                    });
                }

                return $query->groupBy('rstxn_rjhdrs.dr_id', 'rstxn_rjhdrs.poli_id')->orderBy('poli_desc')->orderBy('dr_name')->get();
            },
        );
    }

    #[Computed]
    public function klaimList()
    {
        return DB::table('rsmst_klaims')->select('klaim_id', 'klaim_name')->where('active_status', '1')->orderBy('klaim_name')->get();
    }

    /* -------------------------
     | Helper methods for UI
     * ------------------------- */
    public function getStatusBadge(string $status): string
    {
        return match ($status) {
            '0' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300', // Batal
            '1' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200', // Daftar
            '2' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200', // Antrian
            '3' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200', // Dilayani
            '4' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200', // Selesai
            '5' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200', // Tidak Hadir
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    public function getStatusText(string $status): string
    {
        return match ($status) {
            '0' => 'Batal',
            '1' => 'DAFTAR',
            '2' => 'ANTRIAN',
            '3' => 'DILAYANI',
            '4' => 'SELESAI',
            '5' => 'TIDAK HADIR',
            default => 'Unknown',
        };
    }

    public function getShiftBadge(string $shift): string
    {
        return match ($shift) {
            'PAGI' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
            'SIANG' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
            'SORE' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
            'MALAM' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        };
    }
};
?>

<div>

    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Daftar Rawat Jalan
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Kelola pendaftaran pasien rawat jalan
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter + Action --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">

                <div class="flex flex-wrap items-end gap-3">

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
                                placeholder="Cari No RJ / No RM / Nama Pasien..." />
                        </div>
                    </div>

                    {{-- FILTER TANGGAL --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tanggal" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live="filterTanggal"
                                class="block w-full pl-10 sm:w-40" placeholder="dd/mm/yyyy" x-mask="99/99/9999" />
                        </div>
                    </div>

                    {{-- FILTER STATUS --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-36">
                            <option value="">Semua</option>
                            <option value="A">Antrian</option>
                            <option value="L">Selesai</option>
                            <option value="F">Batal</option>
                            <option value="I">Rujuk</option>
                        </x-select-input>
                    </div>

                    {{-- FILTER POLI --}}
                    {{-- <div class="w-full sm:w-auto">
                        <x-input-label value="Poliklinik" />
                        <x-select-input wire:model.live="filterPoli" class="w-full mt-1 sm:w-48">
                            <option value="">Semua Poli</option>
                            @foreach ($this->poliList as $poli)
                                <option value="{{ $poli->poli_id }}">
                                    {{ $poli->poli_desc }}
                                    @if ($poli->spesialis_status == '1')
                                        (Spesialis)
                                    @endif
                                </option>
                            @endforeach
                        </x-select-input>
                    </div> --}}

                    {{-- FILTER DOKTER --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter" />
                        <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-48">
                            <option value="">Semua Dokter</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">{{ $dokter->dr_name }}</option>
                            @endforeach
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
                            <x-input-label value="Per halaman" class="sr-only" />
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        <x-primary-button type="button" wire:click="openCreate" class="whitespace-nowrap">
                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            Daftar Baru
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE WRAPPER: card --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA --}}
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        {{-- TABLE HEAD --}}
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">No. RJ</th>
                                <th class="px-4 py-3 font-semibold">Waktu</th>
                                <th class="px-4 py-3 font-semibold">No. RM / Nama</th>
                                <th class="px-4 py-3 font-semibold">Poli / Dokter</th>
                                <th class="px-4 py-3 font-semibold">Antrian</th>
                                <th class="px-4 py-3 font-semibold">Shift</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold">SEP</th>
                                <th class="px-4 py-3 font-semibold">AKSI</th>
                            </tr>
                        </thead>

                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                                <tr wire:key="rj-row-{{ $row->rj_no }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-mono font-medium">{{ $row->rj_no }}</td>
                                    <td class="px-4 py-3 text-xs whitespace-nowrap">{{ $row->rj_date_display }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $row->reg_name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row->reg_no }}
                                            @if ($row->sex || $row->birth_date)
                                                | {{ $row->sex ?? '' }} / {{ $row->birth_date ?? '' }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $row->poli_desc }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row->dr_name }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-mono font-bold text-center">
                                        #{{ str_pad($row->no_antrian, 3, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="px-2 py-1 text-xs font-medium rounded-full {{ $this->getShiftBadge($row->shift) }}">
                                            {{ $row->shift }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1">
                                            <span
                                                class="px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap {{ $this->getStatusBadge($row->rj_status) }}">
                                                {{ $this->getStatusText($row->rj_status) }}
                                            </span>
                                            <div class="flex gap-1">
                                                @if ($row->lab_status > 0)
                                                    <span
                                                        class="px-1.5 py-0.5 text-[10px] font-medium bg-purple-100 text-purple-800 rounded-full dark:bg-purple-900 dark:text-purple-200">
                                                        Lab: {{ $row->lab_status }}
                                                    </span>
                                                @endif
                                                @if ($row->rad_status > 0)
                                                    <span
                                                        class="px-1.5 py-0.5 text-[10px] font-medium bg-indigo-100 text-indigo-800 rounded-full dark:bg-indigo-900 dark:text-indigo-200">
                                                        Rad: {{ $row->rad_status }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs">
                                        {{ $row->vno_sep ?: '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-outline-button type="button"
                                                wire:click="openEdit('{{ $row->rj_no }}')"
                                                class="whitespace-nowrap">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Edit
                                            </x-outline-button>

                                            <x-confirm-button variant="danger" :action="'requestDelete(\'' . $row->rj_no . '\')'"
                                                title="Hapus Pendaftaran"
                                                message="Yakin ingin menghapus pendaftaran RJ No {{ $row->rj_no }}? Data yang sudah memiliki pemeriksaan lab/rad/farmasi tidak dapat dihapus."
                                                confirmText="Ya, hapus" cancelText="Batal">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9"
                                        class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        <svg class="w-16 h-16 mx-auto mb-3 text-gray-300 dark:text-gray-600"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        <p class="mb-1 text-lg font-medium">Belum ada data Rawat Jalan</p>
                                        <p class="text-sm text-gray-400 dark:text-gray-500">
                                            {{ empty($filterTanggal) ? 'Silakan pilih tanggal' : 'Belum ada pendaftaran untuk tanggal ' . $filterTanggal }}
                                        </p>
                                        <x-primary-button type="button" wire:click="openCreate" class="mt-4">
                                            + Daftar Pasien Baru
                                        </x-primary-button>
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

            {{-- Child actions component (modal CRUD) --}}
            {{-- <livewire:pages.transaksi.rj.daftar-rj.daftar-rj-actions> --}}
        </div>
    </div>
</div>
