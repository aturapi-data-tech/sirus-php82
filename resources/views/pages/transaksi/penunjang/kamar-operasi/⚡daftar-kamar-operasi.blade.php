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

    /**
     * Mode periode — penamaan & nilainya mengikuti rekap Casemix ('bulanan'|'harian')
     * supaya petugas menemukan kontrol yang sama di dua tempat.
     * Default 'harian': worklist ini dipakai harian oleh petugas OK; bulanan
     * disediakan untuk penelusuran yang lebih luas.
     */
    public string $filterMode = 'harian';
    public string $filterTanggal = '';
    /** mm/yyyy — dipakai saat filterMode 'bulanan'. */
    public string $filterBulan = '';
    public string $filterStatus = '';
    public string $filterLayanan = '';
    public int $itemsPerPage = 10;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->filterBulan = Carbon::now()->format('m/Y');
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

    public function updatedFilterMode(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-kamar-operasi-toolbar');
    }

    public function updatedFilterBulan(): void
    {
        $this->resetPage();
    }

    public function updatedFilterLayanan(): void
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
        $this->reset(['searchKeyword', 'filterStatus', 'filterLayanan', 'filterMode']);
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->filterBulan = Carbon::now()->format('m/Y');
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
     | Dokumen Pelayanan Bedah
     |
     | Form operasi (Laporan Operasi, Pra/Pasca Anestesi, Site Marking, dst.)
     | tinggal di modul dokumen EMR masing-masing unit. Dari sini modalnya dibuka
     | langsung ke tab Pelayanan Bedah supaya petugas OK tak perlu menyusuri tab.
     |
     | Tiap unit punya modul & nama tab sendiri — RI memakai camelCase warisan,
     | RJ/UGD kebab-case. Dipetakan eksplisit, JANGAN ditebak dari string.
     * ------------------------- */
    private const DOKUMEN_BEDAH = [
        'RJ' => ['emr-rj.modul-dokumen.open', 'pelayanan-bedah'],
        'UGD' => ['emr-ugd.modul-dokumen.open', 'pelayanan-bedah'],
        'RI' => ['emr-ri.modul-dokumen.open', 'pelayananBedah'],
    ];

    public function openDokumenBedah(string $sumber, $indukNo): void
    {
        if (!isset(self::DOKUMEN_BEDAH[$sumber]) || empty($indukNo)) {
            $this->dispatch('toast', type: 'error', message: 'Kunjungan induk transaksi ini tidak dikenali.');
            return;
        }

        [$event, $tab] = self::DOKUMEN_BEDAH[$sumber];

        // Nama parameter nomor kunjungan beda per unit (rihdrNo vs rjNo).
        $sumber === 'RI'
            ? $this->dispatch($event, riHdrNo: (string) $indukNo, tab: $tab)
            : $this->dispatch($event, rjNo: (int) $indukNo, tab: $tab);
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
    /**
     * Rentang tanggal untuk query — SATU tempat, dipakai mode harian & bulanan.
     *
     * Sengaja mengembalikan rentang (bukan EXTRACT MONTH/YEAR) supaya query tetap
     * memakai `whereBetween` pada ok_date; EXTRACT mematikan pemakaian index.
     * Input tak terbaca → jatuh ke periode berjalan, bukan ke rentang kosong,
     * supaya daftar tidak mendadak kosong saat petugas salah ketik.
     */
    private function dateRange(): array
    {
        if ($this->filterMode === 'bulanan') {
            try {
                $awal = Carbon::createFromFormat('m/Y', trim($this->filterBulan))->startOfMonth();
            } catch (\Exception $exception) {
                $awal = now()->startOfMonth();
            }

            return [$awal, (clone $awal)->endOfMonth()];
        }

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
     | Kunjungan induk bisa berasal dari tiga layanan (status_rjri + ref_no),
     | jadi identitasnya diresolve lewat inline view: rstxn_oks kecil (±5rb
     | baris), sehingga tiga scalar subquery ber-PK jauh lebih murah daripada
     | UNION tiga tabel kunjungan yang masing-masing ratusan ribu baris.
     |
     | Total tarif = 11 pos yang sama persis dengan pos yang ditransfer ke
     | rstxn_{rj,ugd,ri}oks (lihat KamarOperasiTarif::POS). Dijumlah di SQL supaya
     | tidak perlu tarik semua kolom fee ke PHP hanya untuk menampilkan total.
     * ------------------------- */
    private const OK_DENGAN_KUNJUNGAN = <<<'SQL'
        (SELECT k.*,
                NVL(k.status_rjri, 'RI') AS sumber,
                NVL(k.ref_no, k.rihdr_no) AS induk_no,
                CASE NVL(k.status_rjri, 'RI')
                    WHEN 'RJ'  THEN (SELECT h.reg_no FROM rstxn_rjhdrs  h WHERE h.rj_no    = k.ref_no)
                    WHEN 'UGD' THEN (SELECT h.reg_no FROM rstxn_ugdhdrs h WHERE h.rj_no    = k.ref_no)
                    ELSE            (SELECT h.reg_no FROM rstxn_rihdrs  h WHERE h.rihdr_no = NVL(k.ref_no, k.rihdr_no))
                END AS reg_no,
                CASE NVL(k.status_rjri, 'RI')
                    WHEN 'RJ'  THEN (SELECT h.rj_status FROM rstxn_rjhdrs  h WHERE h.rj_no    = k.ref_no)
                    WHEN 'UGD' THEN (SELECT h.rj_status FROM rstxn_ugdhdrs h WHERE h.rj_no    = k.ref_no)
                    ELSE            (SELECT h.ri_status FROM rstxn_rihdrs  h WHERE h.rihdr_no = NVL(k.ref_no, k.rihdr_no))
                END AS status_induk,
                CASE NVL(k.status_rjri, 'RI')
                    WHEN 'RJ'  THEN (SELECT po.poli_desc  FROM rstxn_rjhdrs  h JOIN rsmst_polis      po ON po.poli_id  = h.poli_id  WHERE h.rj_no    = k.ref_no)
                    WHEN 'UGD' THEN (SELECT e.entry_desc  FROM rstxn_ugdhdrs h JOIN rsmst_entrytypes e  ON e.entry_id  = h.entry_id WHERE h.rj_no    = k.ref_no)
                    ELSE            (SELECT r.room_name   FROM rstxn_rihdrs  h JOIN rsmst_rooms      r  ON r.room_id   = h.room_id  WHERE h.rihdr_no = NVL(k.ref_no, k.rihdr_no))
                END AS unit_name,
                CASE NVL(k.status_rjri, 'RI')
                    WHEN 'RJ'  THEN (SELECT h.klaim_id FROM rstxn_rjhdrs  h WHERE h.rj_no    = k.ref_no)
                    WHEN 'UGD' THEN (SELECT h.klaim_id FROM rstxn_ugdhdrs h WHERE h.rj_no    = k.ref_no)
                    ELSE            (SELECT h.klaim_id FROM rstxn_rihdrs  h WHERE h.rihdr_no = NVL(k.ref_no, k.rihdr_no))
                END AS klaim_id,
                CASE NVL(k.status_rjri, 'RI')
                    WHEN 'RJ'  THEN (SELECT h.vno_sep FROM rstxn_rjhdrs  h WHERE h.rj_no    = k.ref_no)
                    WHEN 'UGD' THEN (SELECT h.vno_sep FROM rstxn_ugdhdrs h WHERE h.rj_no    = k.ref_no)
                    ELSE            (SELECT h.vno_sep FROM rstxn_rihdrs  h WHERE h.rihdr_no = NVL(k.ref_no, k.rihdr_no))
                END AS vno_sep
           FROM rstxn_oks k) o
        SQL;

    #[Computed]
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $query = DB::table(DB::raw(self::OK_DENGAN_KUNJUNGAN))
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'o.reg_no')
            ->leftJoin('rsmst_doctors as dopr', 'dopr.dr_id', '=', 'o.dr_id')
            ->leftJoin('rsmst_doctors as danes', 'danes.dr_id', '=', 'o.dr_id_ok')
            ->leftJoin('rsmst_klaimtypes as kt', 'kt.klaim_id', '=', 'o.klaim_id')
            ->select(
                'o.ok_reg',
                'o.sumber',
                'o.induk_no',
                'o.ok_status',
                DB::raw("to_char(o.ok_date,'dd/mm/yyyy hh24:mi:ss') as ok_date_display"),
                'o.reg_no',
                'o.status_induk',
                'o.unit_name',
                'o.klaim_id',
                'o.vno_sep',
                'kt.klaim_desc',
                'kt.klaim_status',
                'p.reg_name',
                'p.sex',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                'p.address',
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

        if (in_array($this->filterLayanan, ['RJ', 'UGD', 'RI'], true)) {
            $query->where('o.sumber', $this->filterLayanan);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $keyword = mb_strtoupper($search);
            $query->where(function ($subQuery) use ($search, $keyword) {
                if (ctype_digit($search)) {
                    $subQuery->orWhere('o.ok_reg', 'like', "%{$search}%")
                        ->orWhere('o.reg_no', 'like', "%{$search}%")
                        ->orWhere('o.induk_no', 'like', "%{$search}%");
                }
                $subQuery->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$keyword}%")
                    ->orWhere(DB::raw('UPPER(o.vno_sep)'), 'like', "%{$keyword}%");
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

                    {{-- MODE FILTER: Bulanan / Harian (pola rekap Casemix) --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Mode" />
                        <div class="inline-flex mt-1 rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600">
                            <button type="button" wire:click="$set('filterMode', 'bulanan')"
                                class="px-3 py-1.5 text-xs font-medium transition-colors
                                    {{ $filterMode === 'bulanan' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                Bulanan
                            </button>
                            <button type="button" wire:click="$set('filterMode', 'harian')"
                                class="px-3 py-1.5 text-xs font-medium transition-colors border-l border-gray-300 dark:border-gray-600
                                    {{ $filterMode === 'harian' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                Harian
                            </button>
                        </div>
                    </div>

                    {{-- FILTER BULAN / TANGGAL (Tgl Operasi) --}}
                    @if ($filterMode === 'bulanan')
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Bulan (Tgl Operasi)" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                                    class="block w-full pl-10 sm:w-40" placeholder="mm/yyyy" maxlength="7" />
                            </div>
                        </div>
                    @else
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Tanggal (Tgl Operasi)" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input type="text" wire:model.live.debounce.500ms="filterTanggal"
                                    class="block w-full pl-10 sm:w-44" placeholder="dd/mm/yyyy" maxlength="10" />
                            </div>
                        </div>
                    @endif

                    {{-- FILTER LAYANAN --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Layanan" />
                        <x-select-input wire:model.live="filterLayanan" class="w-full mt-1 sm:w-36">
                            <option value="">Semua</option>
                            <option value="RJ">Rawat Jalan</option>
                            <option value="UGD">UGD</option>
                            <option value="RI">Rawat Inap</option>
                        </x-select-input>
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
                        {{-- Layanan yang sedang difilter dibawa ke modal Tambah supaya
                             petugas tidak perlu memilihnya dua kali (pola daftar-laborat). --}}
                        <x-primary-button type="button"
                            wire:click="$dispatch('kamar-operasi-tambah.open', { sumber: '{{ $filterLayanan ?: 'RI' }}' })">
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

                                    // Layanan asal kunjungan.
                                    $sumberRow = strtoupper($row->sumber ?? 'RI');
                                    [$sumberLabel, $sumberClass] = match ($sumberRow) {
                                        'RJ' => ['Rawat Jalan', 'bg-sky-100 text-sky-700 border-sky-200'],
                                        'UGD' => ['Gawat Darurat', 'bg-rose-100 text-rose-700 border-rose-200'],
                                        default => ['Rawat Inap', 'bg-purple-100 text-purple-700 border-purple-200'],
                                    };

                                    // Status kunjungan induk — kolomnya beda per layanan
                                    // (ri_status vs rj_status), sudah diseragamkan di query.
                                    $statusInduk = strtoupper($row->status_induk ?? '');
                                    $statusIndukMuted = 'bg-surface-soft text-muted border-hairline';

                                    if ($sumberRow === 'RI') {
                                        [$indukLabel, $indukClass] = match ($statusInduk) {
                                            '' => ['-', $statusIndukMuted],
                                            'I' => ['Dirawat', 'bg-brand/10 text-brand border-brand/30'],
                                            'P' => ['Pulang', 'bg-amber-100 text-amber-700 border-amber-200'],
                                            'L' => ['Pulang', $statusIndukMuted],
                                            'F' => ['Batal', 'bg-error/10 text-error border-error/30'],
                                            default => [$statusInduk, $statusIndukMuted],
                                        };
                                        $indukAktif = $statusInduk === 'I';
                                        $sebabTerkunci = 'pasien sudah tidak dirawat';
                                    } else {
                                        [$indukLabel, $indukClass] = match ($statusInduk) {
                                            '' => ['-', $statusIndukMuted],
                                            'A' => ['Aktif', 'bg-brand/10 text-brand border-brand/30'],
                                            'L' => ['Sudah Dibayar', 'bg-amber-100 text-amber-700 border-amber-200'],
                                            'I' => ['Dirawat Inap', $statusIndukMuted],
                                            'F' => ['Batal', 'bg-error/10 text-error border-error/30'],
                                            default => [$statusInduk, $statusIndukMuted],
                                        };
                                        $indukAktif = $statusInduk === 'A';
                                        $sebabTerkunci = 'kunjungan sudah ditutup di kasir';
                                    }

                                    // Transfer biaya hanya boleh saat kunjungan induk masih aktif.
                                    // Transaksi yang masih 'A' sementara kunjungannya sudah ditutup =
                                    // biaya TIDAK akan pernah masuk tagihan; ditandai supaya
                                    // petugas/supervisor melihatnya.
                                    $transferTerkunci = $statusCode === 'A' && !$indukAktif;
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
                                    {{-- No Txn (ok_reg) TIDAK ditampilkan — nomor internal modul OK,
                                         tak dipakai berkomunikasi dengan pasien maupun unit lain.
                                         Tetap bisa DICARI lewat kotak pencarian, dan tetap dipakai
                                         sebagai wire:key baris + argumen aksi menu. --}}
                                    <td class="px-6 py-4 space-y-2 align-top">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold border rounded-full {{ $sumberClass }}">
                                                {{ $sumberLabel }}
                                            </span>
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold border rounded-full {{ $indukClass }}">
                                                {{ $indukLabel }}
                                            </span>
                                        </div>
                                        <div class="font-mono text-sm text-body dark:text-gray-300">
                                            {{ $row->ok_date_display ?? '-' }}
                                        </div>
                                        {{-- Nomor kunjungan (No Inap/No Reg) TIDAK ditampilkan —
                                             sama alasannya dengan No Txn. Yang tersisa cuma unit
                                             asalnya, yang memang menerangkan pasien datang dari mana.
                                             Nomornya tetap bisa DICARI dan tetap dipakai aksi menu. --}}
                                        @if (!empty($row->unit_name))
                                            <div class="text-sm text-muted dark:text-gray-400">
                                                {{ $row->unit_name }}
                                            </div>
                                        @endif

                                        {{-- Cara bayar & No. SEP — komponen standar list transaksi,
                                             supaya warna badge & format SEP sama dengan Daftar RJ/UGD/RI. --}}
                                        <x-list.klaim-badge :status="$row->klaim_status" :desc="$row->klaim_desc" :id="$row->klaim_id" />
                                        <x-list.sep-spri :sep="$row->vno_sep" />

                                        @if ($transferTerkunci)
                                            <div class="text-xs font-semibold text-red-700 dark:text-red-300">
                                                Belum ditransfer &mdash; {{ $sebabTerkunci }}
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
                                    <td class="px-6 py-4 align-top">
                                        {{-- Semua aksi baris masuk SATU menu titik-3 (pola Pelayanan RJ):
                                             kolom Aksi tetap ramping walau menunya nanti bertambah. --}}
                                        <div class="flex items-center justify-center">
                                        {{-- Menunya SELALU tampil,
                                             termasuk di baris RJ/UGD — menyembunyikannya bikin petugas
                                             mengira fiturnya rusak. Untuk RJ/UGD isinya dinonaktifkan
                                             berikut alasannya. --}}
                                        @hasanyrole('Admin|Perawat|Dokter|Casemix|Mr|Gizi')
                                            <x-dropdown align="right" width="w-72">
                                                <x-slot name="trigger">
                                                    <x-secondary-button type="button" class="p-2">
                                                        <span class="sr-only">Menu lainnya</span>
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z" />
                                                        </svg>
                                                    </x-secondary-button>
                                                </x-slot>

                                                <x-slot name="content">
                                                    <div class="p-2 space-y-1">

                                                        {{-- Aksi utama: modal kerja transaksi OK --}}
                                                        <x-dropdown-link href="#"
                                                            wire:click.prevent="openDetail('{{ $row->ok_reg }}')"
                                                            class="px-3 py-2 text-sm rounded-lg bg-green-50 hover:bg-green-100 dark:bg-green-900/20 dark:hover:bg-green-900/40">
                                                            <div class="flex items-start gap-2">
                                                                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                                </svg>
                                                                <span>Transaksi Operasi<br>
                                                                    <span class="font-semibold">Tindakan, tarif &amp; transfer biaya</span>
                                                                </span>
                                                            </div>
                                                        </x-dropdown-link>

                                                        <x-dropdown-link href="#"
                                                                wire:click.prevent="openDokumenBedah('{{ $sumberRow }}', '{{ $row->induk_no }}')"
                                                                class="px-3 py-2 text-sm rounded-lg bg-yellow-50 hover:bg-yellow-100 dark:bg-yellow-900/20">
                                                                <div class="flex items-start gap-2">
                                                                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                    </svg>
                                                                    <span>Dokumen Pelayanan Bedah<br>
                                                                        <span class="font-semibold">{{ $row->reg_name }}</span>
                                                                    </span>
                                                                </div>
                                                            </x-dropdown-link>
                                                    </div>
                                                </x-slot>
                                            </x-dropdown>
                                        @endhasanyrole
                                        </div>
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

    {{-- CHILD: modul dokumen tiap unit — dipakai menu titik-3 untuk membuka form
         Pelayanan Bedah tanpa pindah halaman. Komponen yang SAMA dengan yang
         dipakai Daftar RI / Pelayanan RJ / Pelayanan UGD, jadi form & aturan
         penguncian ikut apa adanya. Ketiganya dipasang karena satu worklist bisa
         memuat operasi dari ketiga layanan sekaligus. --}}
    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.modul-dokumen-ri wire:key="modul-dokumen-ri-ok" />
    <livewire:pages::transaksi.rj.emr-rj.modul-dokumen.modul-dokumen-rj wire:key="modul-dokumen-rj-ok" />
    <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.modul-dokumen-ugd wire:key="modul-dokumen-ugd-ok" />
</div>
