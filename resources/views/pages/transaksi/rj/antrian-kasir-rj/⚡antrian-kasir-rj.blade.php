<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\OracleLob;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['antrian-kasir-toolbar'];

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    #[Session(key: 'antrian-kasir-rj-searchKeyword')]
    public string $searchKeyword = '';
    #[Session(key: 'antrian-kasir-rj-filterTanggal')]
    public string $filterTanggal = '';
    #[Session(key: 'antrian-kasir-rj-filterStatus')]
    public string $filterStatus = 'A';
    #[Session(key: 'antrian-kasir-rj-filterKlaim')]
    public string $filterKlaim = ''; // '' | 'BPJS' | 'UMUM' — pakai klaim_status di rsmst_klaimtypes (JM dianggap BPJS)
    #[Session(key: 'antrian-kasir-rj-filterDokter')]
    public string $filterDokter = '';
    #[Session(key: 'antrian-kasir-rj-itemsPerPage')]
    public int $itemsPerPage = 10;
    public string $autoRefresh = 'Ya';

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterTanggal = $this->filterTanggal ?: Carbon::now()->format('d/m/Y');
    }

    /* -------------------------
     | Updated hooks — reset page & bump toolbar
     * ------------------------- */
    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-kasir-toolbar');
    }

    public function updatedFilterTanggal(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDokter(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-kasir-toolbar');
    }

    public function updatedFilterKlaim(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-kasir-toolbar');
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-kasir-toolbar');
    }

    /* -------------------------
     | Reset filters
     * ------------------------- */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterKlaim', 'filterDokter']);
        $this->filterStatus = 'A';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('antrian-kasir-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Dispatch ke child actions
     * ------------------------- */

    public function openAdministrasiPasien(string $rjNo): void
    {
        $this->dispatch('emr-rj.administrasi.open', rjNo: $rjNo);
    }

    /* -------------------------
     | Refresh setelah child save
     * ------------------------- */
    #[On('refresh-after-kasir.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('antrian-kasir-toolbar');
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

        // Kolom di sini = yang DITAMPILKAN daftar + yang dipakai filter/urut, tidak lebih.
        // Query ini mencakup seluruh pasien di tanggal itu: CLOB JSON utuh dan hitungan
        // lab/rad TIDAK diambil di sini (CLOB di query besar memicu ORA-01555 saat
        // kunjungan lain disimpan — kasus Pelayanan RJ 2026-10-26). Urutan antrean butuh
        // dua penanda dari JSON → dihitung di server dgn DBMS_LOB.INSTR, CLOB-nya tak ikut
        // terkirim. taskId5 dibuat '' saat pendaftaran, jadi "terisi" = ada kunci bernilai
        // string dan bukan string kosong.
        $urutAdministrasi = DB::raw("CASE WHEN DBMS_LOB.INSTR(h.datadaftarpolirj_json, '\"AdministrasiRj\"') > 0 THEN 0 ELSE 1 END as urut_administrasi");
        $urutKeluarPoli = DB::raw("CASE WHEN DBMS_LOB.INSTR(h.datadaftarpolirj_json, '\"taskId5\":\"') > 0 AND DBMS_LOB.INSTR(h.datadaftarpolirj_json, '\"taskId5\":\"\"') = 0 THEN 0 ELSE 1 END as urut_keluar_poli");

        $query = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select(['h.rj_no', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date_display"), 'h.reg_no', 'p.reg_name', 'p.sex', 'p.address', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'h.no_antrian', 'po.poli_desc', 'd.dr_name', 'h.klaim_id', 'h.shift', 'h.rj_status', 'h.vno_sep', 'k.klaim_desc', 'k.klaim_status', 'h.status_kronis', $urutAdministrasi, $urutKeluarPoli])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where(DB::raw("NVL(h.rj_status,'A')"), $this->filterStatus)
            ->where('h.klaim_id', '!=', 'KR');

        // Filter Klaim BPJS / UMUM
        // BPJS = klaim_status='BPJS' (di rsmst_klaimtypes) ATAU klaim_id='JM' (JKN Mobile)
        // UMUM = bukan keduanya
        if ($this->filterKlaim === 'BPJS') {
            $query->where(function ($q) {
                $q->where('k.klaim_status', 'BPJS')->orWhere('h.klaim_id', 'JM');
            });
        } elseif ($this->filterKlaim === 'UMUM') {
            $query->where(function ($q) {
                $q->where(function ($w) {
                    $w->where('k.klaim_status', '!=', 'BPJS')->orWhereNull('k.klaim_status');
                })->where('h.klaim_id', '!=', 'JM');
            });
        }

        if ($this->filterDokter !== '') {
            $query->where('h.dr_id', $this->filterDokter);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($kw, $search) {
                if (ctype_digit($search)) {
                    $q->orWhere('h.rj_no', 'like', "%{$search}%")->orWhere('h.reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(d.dr_name)'), 'like', "%{$kw}%");
            });
        }

        // Ambil semua dulu untuk sorting custom (antrian kasir)
        $all = $query->get();

        // Sort: pasien yang sudah Administrasi paling atas (siap dibayar),
        // lalu yang sudah keluar poli (taskId5), tie-breaker no_antrian poli asc.
        $sorted = $all
            ->sortBy(fn($row) => [(int) $row->urut_administrasi, (int) $row->urut_keluar_poli, (int) ($row->no_antrian ?? 0)])
            ->values();

        // Manual paginate
        $page = $this->getPage();
        $perPage = $this->itemsPerPage;
        $total = $sorted->count();
        $items = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator($items, $total, $perPage, $page, ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]);

        // CLOB JSON + hitungan lab/rad hanya untuk baris halaman ini. Gagal sekaligus →
        // ambil ulang per baris; satu baris gagal tak menjatuhkan daftar.
        $rjNoHalaman = $items->pluck('rj_no')->all();

        $jsonPerRjNo = [];
        try {
            $jsonPerRjNo = DB::table('rstxn_rjhdrs')
                ->whereIn('rj_no', $rjNoHalaman)
                ->pluck('datadaftarpolirj_json', 'rj_no')
                ->all();
        } catch (\Throwable $e) {
        }

        $labPerRjNo = DB::table('lbtxn_checkuphdrs')
            ->whereIn('ref_no', $rjNoHalaman)
            ->where('status_rjri', 'RJ')
            ->where('checkup_status', '!=', 'B')
            ->groupBy('ref_no')
            ->selectRaw('ref_no, COUNT(*) as jumlah')
            ->pluck('jumlah', 'ref_no')
            ->all();

        $radPerRjNo = DB::table('rstxn_rjrads')
            ->whereIn('rj_no', $rjNoHalaman)
            ->groupBy('rj_no')
            ->selectRaw('rj_no, COUNT(*) as jumlah')
            ->pluck('jumlah', 'rj_no')
            ->all();

        // Transform rows
        $paginator->getCollection()->transform(function ($row) use ($jsonPerRjNo, $labPerRjNo, $radPerRjNo) {
            $row->lab_status = (int) ($labPerRjNo[$row->rj_no] ?? 0);
            $row->rad_status = (int) ($radPerRjNo[$row->rj_no] ?? 0);

            $jsonRaw = array_key_exists($row->rj_no, $jsonPerRjNo)
                ? OracleLob::read($jsonPerRjNo[$row->rj_no], 'rstxn_rjhdrs', 'rj_no', $row->rj_no, 'datadaftarpolirj_json')
                : rescue(fn() => OracleLob::toString(DB::table('rstxn_rjhdrs')->where('rj_no', $row->rj_no)->value('datadaftarpolirj_json')), '', false);
            $json = json_decode($jsonRaw ?: '{}', true) ?? [];

            // E-Resep
            $row->has_eresep_racikan = isset($json['eresepRacikan']) ? 1 : 0;
            $row->eresep_count = (isset($json['eresep']) ? 1 : 0) + $row->has_eresep_racikan;

            // Task IDs
            $row->task_id5 = $json['taskIdPelayanan']['taskId5'] ?? null;
            $row->task_id6 = $json['taskIdPelayanan']['taskId6'] ?? null;
            $row->task_id7 = $json['taskIdPelayanan']['taskId7'] ?? null;

            // Status resep
            $row->status_resep = $json['statusResep']['status'] ?? null;
            $row->status_resep_label = match ($row->status_resep) {
                'DITUNGGU' => 'Ditunggu',
                'DITINGGAL' => 'Ditinggal',
                default => '-',
            };
            $row->status_resep_color = match ($row->status_resep) {
                'DITUNGGU' => 'success',
                'DITINGGAL' => 'warning',
                default => 'alternative',
            };

            // Administrasi
            $row->admin_user = isset($json['AdministrasiRj']) ? $json['AdministrasiRj']['userLog'] ?? '✔' : '-';

            // Status badge — unified berdasarkan urutan Task ID flow
            // BATAL HANYA DARI rj_status='F'. taskId99 = laporan batal antrean BPJS,
            // tidak menyentuh status transaksi.
            $tasks = $json['taskIdPelayanan'] ?? [];
            if ($row->rj_status === 'F') {
                $row->status_text = 'Batal';
                $row->status_variant = 'danger';
            } elseif (!empty($tasks['taskId7'])) {
                $row->status_text = 'Pasien Menerima Resep';
                $row->status_variant = 'success';
            } elseif (!empty($tasks['taskId6'])) {
                $row->status_text = 'Menunggu Resep';
                $row->status_variant = 'warning';
            } elseif (!empty($tasks['taskId5'])) {
                $row->status_text = 'Keluar Poli';
                $row->status_variant = 'brand';
            } elseif (!empty($tasks['taskId4'])) {
                $row->status_text = 'Masuk Poli';
                $row->status_variant = 'warning';
            } elseif (!empty($tasks['taskId3'])) {
                $row->status_text = 'Pendaftaran';
                $row->status_variant = 'alternative';
            } else {
                $row->status_text = 'Belum Dilayani';
                $row->status_variant = 'gray';
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

        return DB::table('rstxn_rjhdrs')
            ->join('rsmst_doctors', 'rsmst_doctors.dr_id', '=', 'rstxn_rjhdrs.dr_id')
            ->select('rstxn_rjhdrs.dr_id', DB::raw('MAX(rsmst_doctors.dr_name) as dr_name'), DB::raw('COUNT(DISTINCT rstxn_rjhdrs.rj_no) as total_pasien'))
            ->whereBetween('rstxn_rjhdrs.rj_date', [$start, $end])
            ->where('rstxn_rjhdrs.klaim_id', '!=', 'KR')
            ->groupBy('rstxn_rjhdrs.dr_id')
            ->orderBy('dr_name')
            ->get();
    }
};
?>

<div>
    <x-page-title
        title="Kasir Rawat Jalan"
        subtitle="Kelola administrasi & pembayaran pasien rawat jalan" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3"
                    wire:key="{{ $this->renderKey('antrian-kasir-toolbar', []) }}">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari No RJ / No RM / Nama Pasien / Dokter..." />
                        </div>
                    </div>

                    {{-- TANGGAL --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tanggal" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor"
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
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-36">
                            <option value="A">Antrian</option>
                            <option value="L">Selesai</option>
                            <option value="F">Batal</option>
                            <option value="I">Transfer UGD</option>
                        </x-select-input>
                    </div>

                    {{-- FILTER KLAIM — BPJS / UMUM --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Klaim" />
                        <x-select-input wire:model.live="filterKlaim" class="w-full mt-1 sm:w-32">
                            <option value="">Semua</option>
                            <option value="BPJS">BPJS</option>
                            <option value="UMUM">UMUM</option>
                        </x-select-input>
                    </div>

                    {{-- DOKTER --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter" />
                        <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-48">
                            <option value="">Semua Dokter</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">
                                    {{ $dokter->dr_name }} ({{ $dokter->total_pasien }})
                                </option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- AUTO REFRESH --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Auto Refresh" />
                        <x-select-input wire:model.live="autoRefresh" class="w-full mt-1 sm:w-28">
                            <option value="Ya">Ya (20s)</option>
                            <option value="Tidak">Tidak</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Per Hal." />
                        <x-select-input wire:model.live="itemsPerPage" class="w-full mt-1 sm:w-28">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </x-select-input>
                    </div>

                    {{-- AKSI — Refresh + Reset standar di kanan sendiri --}}
                    <x-toolbar-refresh-reset class="ml-auto" />

                </div>

                {{-- Timestamp refresh --}}
                <div class="mt-1 text-xs text-muted">
                    Data Terakhir: {{ now()->format('d/m/Y H:i:s') }}
                </div>
            </div>

            {{-- AUTO REFRESH WRAPPER --}}
            @if ($autoRefresh === 'Ya')
                <div wire:poll.30s class="mt-4 flex flex-col flex-1 min-h-0">
                @else
                    <div class="mt-4 flex flex-col flex-1 min-h-0">
            @endif

            {{-- TABLE --}}
            <div class="flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-base -mt-2 border-separate border-spacing-y-2">

                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-4 py-3">Pasien</th>
                                <th class="px-4 py-3">Poli / Dokter</th>
                                <th class="px-4 py-3">Status Layanan</th>
                                <th class="px-4 py-3">Waktu Kasir</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="antrian-kasir-rj-{{ $row->rj_no ?? $loop->index }}"
                                    class="transition bg-canvas dark:bg-gray-900 hover:shadow-md hover:bg-surface-soft dark:hover:bg-gray-800 rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700">

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-6 space-y-3 align-top">
                                        <x-list.identitas-pasien :regNo="$row->reg_no" :nama="$row->reg_name"
                                            :sex="$row->sex" :tglLahir="$row->birth_date"
                                            :alamat="$row->address" :collapseUmur="false" />
                                    </td>

                                    {{-- POLI / DOKTER --}}
                                    <td class="px-4 py-4 space-y-1 align-top">
                                        <div class="text-sm font-semibold text-success dark:text-success">
                                            {{ $row->poli_desc ?? '-' }}
                                        </div>
                                        <div class="text-sm text-body dark:text-gray-300">
                                            {{ $row->dr_name ?? '-' }}
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-list.klaim-badge :status="$row->klaim_status" :desc="$row->klaim_desc" :id="$row->klaim_id" />
                                            <x-list.sep-spri :sep="$row->vno_sep" />
                                            @if (($row->status_kronis ?? 'N') === 'Y')
                                                <span
                                                    class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200"
                                                    title="Kunjungan ini punya obat dengan split kronis (BPJS InaCBG + Kronis luar paket)">
                                                    KRONIS
                                                </span>
                                            @endif
                                            <div>
                                                @if ($row->lab_status)
                                                    <x-badge variant="alternative">Laborat</x-badge>
                                                @endif
                                                @if ($row->rad_status)
                                                    <x-badge variant="brand">Radiologi</x-badge>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- STATUS LAYANAN --}}
                                    <td class="px-4 py-4 space-y-2 align-top">
                                        <div class="text-xs text-muted dark:text-gray-400">
                                            {{ $row->rj_date_display }} | Shift {{ $row->shift ?? '-' }}
                                        </div>

                                        <x-badge :variant="$row->status_variant">
                                            {{ $row->status_text }}
                                        </x-badge>

                                        {{-- E-Resep indicator --}}
                                        <div class="flex gap-1.5">
                                            @if ($row->eresep_count)
                                                <span
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd"
                                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                    E-Resep
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                                    Tanpa Resep
                                                </span>
                                            @endif

                                            @if ($row->has_eresep_racikan)
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                                    Racikan
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Status resep --}}
                                        @if ($row->status_resep)
                                            <x-badge :variant="$row->status_resep_color">
                                                {{ $row->status_resep_label }}
                                            </x-badge>
                                        @endif

                                    </td>

                                    {{-- WAKTU APOTEK --}}
                                    <td class="px-4 py-4 space-y-2 align-top">
                                        <div class="text-xs space-y-1">
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id5 ? 'bg-blue-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-muted dark:text-gray-400">
                                                    Keluar Poli:
                                                    <span class="font-medium">{{ $row->task_id5 ?? '—' }}</span>
                                                </span>
                                            </div>
                                            @php
                                                $rjLabel = match ($row->rj_status) {
                                                    'A' => 'Belum Bayar',
                                                    'L' => 'Selesai Pembayaran',
                                                    'I' => 'Transfer UGD',
                                                    'F' => 'Batal',
                                                    default => null,
                                                };
                                                $rjTextColor = match ($row->rj_status) {
                                                    'A' => 'text-amber-600 dark:text-amber-400',
                                                    'L' => 'text-success dark:text-success',
                                                    'I' => 'text-blue-600 dark:text-blue-400',
                                                    'F' => 'text-red-600 dark:text-red-400',
                                                    default => 'text-muted-soft',
                                                };
                                            @endphp
                                            @if ($rjLabel)
                                                <div class="text-xs text-muted dark:text-gray-500">
                                                    Kasir:
                                                    <span
                                                        class="font-medium {{ $rjTextColor }}">{{ $rjLabel }}</span>
                                                </div>
                                            @endif
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id6 ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-muted dark:text-gray-400">
                                                    Masuk Apotek:
                                                    <span class="font-medium">{{ $row->task_id6 ?? '—' }}</span>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id7 ? 'bg-violet-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-muted dark:text-gray-400">
                                                    Keluar Apotek:
                                                    <span class="font-medium">{{ $row->task_id7 ?? '—' }}</span>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="text-xs text-muted dark:text-gray-500">
                                            Administrasi:
                                            <span
                                                class="font-medium {{ $row->admin_user !== '-' ? 'text-success dark:text-success' : 'text-muted-soft' }}">
                                                {{ $row->admin_user }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-4 py-4 align-top">
                                        @if ($row->status_text === 'Batal')
                                            {{-- Batal: actions tidak diakses, konfirmasi ke Pendaftaran --}}
                                            <div
                                                class="flex flex-col items-center gap-2 p-3 text-center border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/10 dark:border-red-800">
                                                <div class="text-red-500 dark:text-red-400">
                                                    <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="1.5"
                                                            d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                                                    </svg>
                                                </div>
                                                <span class="text-xs font-semibold text-red-600 dark:text-red-400">
                                                    Pasien Batal
                                                </span>
                                                <span class="text-xs text-muted dark:text-gray-400">
                                                    Konfirmasi ke<br>Pendaftaran
                                                </span>
                                            </div>
                                        @else
                                            {{-- Aksi kasir — menu titik-3 baku (sama dengan Pelayanan), melayang agar baris terbawah tak terpotong --}}
                                            <x-dropdown width="w-[440px]" :melayang="true">
                                                <x-slot name="trigger">
                                                    <x-secondary-button type="button" class="p-2.5">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z" />
                                                        </svg>
                                                    </x-secondary-button>
                                                </x-slot>

                                                <x-slot name="content">
                                                    <div class="p-2 space-y-2">
                                                        <x-list.identitas-aksi :regNo="$row->reg_no" :nama="$row->reg_name" :sex="$row->sex"
                                                            jalur="Rawat Jalan" />

                                                        <div class="grid grid-cols-2 gap-1 items-stretch">
                                                        @hasanyrole('Admin|Tu|Manager Umum|Supervisor Tu')
                                                        <x-dropdown-link href="#" wire:click.prevent="openAdministrasiPasien('{{ $row->rj_no }}')"
                                                            class="px-3 py-2 text-sm rounded-lg h-full bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/20 dark:hover:bg-purple-900/40">
                                                            <div class="flex items-start gap-2">
                                                                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z" />
                                                                </svg>
                                                                <span class="min-w-0">
                                                                    <span class="block font-semibold">Administrasi</span>
                                                                    <span class="block text-xs font-normal text-muted dark:text-gray-400">Tagihan &amp; pembayaran</span>
                                                                </span>
                                                            </div>
                                                        </x-dropdown-link>
                                                        @endhasanyrole

                                                        {{-- Transfer — aktif HANYA saat status Antrian (rj_status='A') --}}
                                                        @hasanyrole('Admin|Tu|Perawat|Manager Umum|Supervisor Tu')
                                                        @if ($row->rj_status === 'A')
                                                        <x-dropdown-link href="#" wire:click.prevent="$dispatch('open-transfer-rj-ke-ugd', { rjNo: {{ $row->rj_no }} })"
                                                            class="px-3 py-2 text-sm rounded-lg h-full bg-teal-50 hover:bg-teal-100 dark:bg-teal-900/20 dark:hover:bg-teal-900/40">
                                                            <div class="flex items-start gap-2">
                                                                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                                                </svg>
                                                                <span class="min-w-0">
                                                                    <span class="block font-semibold">Transfer ke UGD</span>
                                                                    <span class="block text-xs font-normal text-muted dark:text-gray-400">Pindahkan biaya ke UGD</span>
                                                                </span>
                                                            </div>
                                                        </x-dropdown-link>
                                                        @else
                                                        <div class="px-3 py-2 text-sm rounded-lg h-full bg-gray-50 text-muted-soft opacity-60 cursor-not-allowed dark:bg-gray-800/40" title="Hanya saat status Antrian">
                                                            <div class="flex items-start gap-2">
                                                                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                                                </svg>
                                                                <span class="min-w-0">
                                                                    <span class="block font-semibold">Transfer ke UGD</span>
                                                                    <span class="block text-xs font-normal text-muted dark:text-gray-400">Hanya saat status Antrian</span>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @endhasanyrole

                                                        {{-- Ubah Data Kunjungan (tanggal/shift/klaim) — HANYA saat status Antrian --}}
                                                        @can('rj.ubahDataKunjungan')
                                                        @if ($row->rj_status === 'A')
                                                        <x-dropdown-link href="#" wire:click.prevent="$dispatch('open-ubah-data-kunjungan-rj', { rjNo: {{ $row->rj_no }} })"
                                                            class="px-3 py-2 text-sm rounded-lg h-full bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/20 dark:hover:bg-amber-900/40">
                                                            <div class="flex items-start gap-2">
                                                                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                                <span class="min-w-0">
                                                                    <span class="block font-semibold">Ubah Data Kunjungan</span>
                                                                    <span class="block text-xs font-normal text-muted dark:text-gray-400">Tanggal, shift &amp; klaim</span>
                                                                </span>
                                                            </div>
                                                        </x-dropdown-link>
                                                        @else
                                                        <div class="px-3 py-2 text-sm rounded-lg h-full bg-gray-50 text-muted-soft opacity-60 cursor-not-allowed dark:bg-gray-800/40" title="Hanya saat status Antrian">
                                                            <div class="flex items-start gap-2">
                                                                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                                <span class="min-w-0">
                                                                    <span class="block font-semibold">Ubah Data Kunjungan</span>
                                                                    <span class="block text-xs font-normal text-muted dark:text-gray-400">Hanya saat status Antrian</span>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @endcan
                                                        </div>
                                                    </div>
                                                </x-slot>
                                            </x-dropdown>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"
                                        class="px-6 py-16 text-center text-muted dark:text-gray-400">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                            <span>Tidak ada data antrian kasir</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

        </div>{{-- end auto-refresh wrapper --}}

        {{-- Child action components --}}

        <livewire:pages::transaksi.rj.emr-rj.log-aktivitas.log-aktivitas-rj wire:key="log-aktivitas-rj" />
        <livewire:pages::transaksi.rj.administrasi-rj.administrasi-rj wire:key="administrasi-rj-actions" />
        <livewire:pages::transaksi.rj.administrasi-rj.transfer-rj-ke-ugd-actions wire:key="transfer-rj-ke-ugd-actions" />
        <livewire:pages::transaksi.rj.antrian-kasir-rj.ubah-data-kunjungan-rj-actions wire:key="ubah-data-kunjungan-rj-actions" />

    </div>
</div>
</div>
