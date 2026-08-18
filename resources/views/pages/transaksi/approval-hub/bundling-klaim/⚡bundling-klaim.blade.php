<?php
// resources/views/pages/transaksi/approval-hub/bundling-klaim/bundling-klaim.blade.php
// Bundling berkas BPJS per klaim jadi 1 PDF untuk disetor ke BPJS.
// Sumber data: tabel upload berkas (rstxn_rjuploadbpjses / rstxn_riuploadbpjses),
// bukan approval queue — supaya semua klaim tercakup (manual maupun via AI).

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    #[Session(key: 'bundling-filterMode')]
    public string $filterMode = 'bulan';
    #[Session(key: 'bundling-filterBulan')]
    public string $filterBulan = '';
    #[Session(key: 'bundling-filterTglAwal')]
    public string $filterTglAwal = '';
    #[Session(key: 'bundling-filterTglAkhir')]
    public string $filterTglAkhir = '';
    #[Session(key: 'bundling-filterRefType')]
    public string $filterRefType = '';
    #[Session(key: 'bundling-itemsPerPage')]
    public int $itemsPerPage = 50;

    public string $searchKeyword = '';

    // Bundling state
    public array $selected = [];
    public bool $selectAll = false;
    public array $bundlingResult = [];
    public bool $bundlingProcessing = false;

    public function mount(): void
    {
        if (empty($this->filterBulan)) {
            $this->filterBulan = now()->format('Y-m');
        }
        if (empty($this->filterTglAwal)) {
            $this->filterTglAwal = now()->startOfMonth()->format('Y-m-d');
        }
        if (empty($this->filterTglAkhir)) {
            $this->filterTglAkhir = now()->format('Y-m-d');
        }
    }

    public function updatedFilterMode(): void { $this->resetPage(); }
    public function updatedFilterBulan(): void { $this->resetPage(); $this->selected = []; $this->selectAll = false; }
    public function updatedFilterTglAwal(): void { $this->resetPage(); $this->selected = []; $this->selectAll = false; }
    public function updatedFilterTglAkhir(): void { $this->resetPage(); $this->selected = []; $this->selectAll = false; }
    public function updatedFilterRefType(): void { $this->resetPage(); $this->selected = []; $this->selectAll = false; }
    public function updatedItemsPerPage(): void { $this->resetPage(); }
    public function updatedSearchKeyword(): void { $this->resetPage(); }

    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selected = $this->rows->pluck('key')->toArray();
        } else {
            $this->selected = [];
        }
    }

    public function resetFilters(): void
    {
        $this->filterMode = 'bulan';
        $this->filterBulan = now()->format('Y-m');
        $this->filterTglAwal = now()->startOfMonth()->format('Y-m-d');
        $this->filterTglAkhir = now()->format('Y-m-d');
        $this->filterRefType = '';
        $this->searchKeyword = '';
        $this->selected = [];
        $this->selectAll = false;
        $this->bundlingResult = [];
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $rjQuery = DB::table('rstxn_rjhdrs as h')
            ->join('rstxn_rjuploadbpjses as u', 'h.rj_no', '=', 'u.rj_no')
            ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
            ->whereNotNull('u.uploadbpjs')
            ->select([
                DB::raw("'RJ' as ref_type"),
                'h.rj_no as ref_no',
                'h.reg_no',
                'p.reg_name',
                'h.rj_date',
                DB::raw("(SELECT json_value(h.datadaftarpolirj_json, '$.sep.noSep') FROM dual) as no_sep"),
                DB::raw("COUNT(u.seq_file) as jml_berkas"),
            ])
            ->groupBy('h.rj_no', 'h.reg_no', 'p.reg_name', 'h.rj_date', 'h.datadaftarpolirj_json');

        $riQuery = DB::table('rstxn_rihdrs as h')
            ->join('rstxn_riuploadbpjses as u', 'h.rihdr_no', '=', 'u.rihdr_no')
            ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
            ->whereNotNull('u.uploadbpjs')
            ->select([
                DB::raw("'RI' as ref_type"),
                'h.rihdr_no as ref_no',
                'h.reg_no',
                'p.reg_name',
                DB::raw("h.rihdr_date as rj_date"),
                DB::raw("(SELECT json_value(h.datadaftarri_json, '$.sep.noSep') FROM dual) as no_sep"),
                DB::raw("COUNT(u.seq_file) as jml_berkas"),
            ])
            ->groupBy('h.rihdr_no', 'h.reg_no', 'p.reg_name', 'h.rihdr_date', 'h.datadaftarri_json');

        $this->applyDateFilter($rjQuery, 'h.rj_date');
        $this->applyDateFilter($riQuery, 'h.rihdr_date');

        if ($this->searchKeyword !== '') {
            $kw = '%' . strtoupper(trim($this->searchKeyword)) . '%';
            $rjQuery->where(function ($q) use ($kw) {
                $q->where(DB::raw('UPPER(p.reg_name)'), 'LIKE', $kw)
                  ->orWhere(DB::raw('UPPER(h.reg_no)'), 'LIKE', $kw);
            });
            $riQuery->where(function ($q) use ($kw) {
                $q->where(DB::raw('UPPER(p.reg_name)'), 'LIKE', $kw)
                  ->orWhere(DB::raw('UPPER(h.reg_no)'), 'LIKE', $kw);
            });
        }

        if ($this->filterRefType === 'RI') {
            $combined = $riQuery->orderByDesc('rj_date');
        } elseif ($this->filterRefType !== '') {
            $combined = $rjQuery->orderByDesc('rj_date');
        } else {
            $combined = $rjQuery->unionAll($riQuery)->orderByDesc('rj_date');
        }

        $results = $combined->paginate($this->itemsPerPage);

        $results->getCollection()->transform(function ($row) {
            $row->key = $row->ref_type . '_' . $row->ref_no;
            return $row;
        });

        return $results;
    }

    private function applyDateFilter($query, string $dateCol): void
    {
        if ($this->filterMode === 'bulan' && !empty($this->filterBulan)) {
            $query->whereRaw("to_char($dateCol, 'YYYY-MM') = ?", [$this->filterBulan]);
        } elseif ($this->filterMode === 'tanggal') {
            if (!empty($this->filterTglAwal)) {
                $query->whereRaw("trunc($dateCol) >= to_date(?, 'YYYY-MM-DD')", [$this->filterTglAwal]);
            }
            if (!empty($this->filterTglAkhir)) {
                $query->whereRaw("trunc($dateCol) <= to_date(?, 'YYYY-MM-DD')", [$this->filterTglAkhir]);
            }
        }
    }

    #[Computed]
    public function stats(): array
    {
        $rows = $this->rows;
        $collection = $rows->getCollection();
        return [
            'total' => $rows->total(),
            'displayed' => $collection->count(),
            'selected' => count($this->selected),
            'rj' => $collection->where('ref_type', 'RJ')->count(),
            'ri' => $collection->where('ref_type', 'RI')->count(),
        ];
    }

    public function generateBundling(): void
    {
        $targets = $this->selected;
        if (empty($targets)) {
            $this->dispatch('toast', type: 'error', message: 'Pilih minimal 1 klaim untuk di-bundling.');
            return;
        }

        $this->bundlingProcessing = true;
        $this->bundlingResult = [];

        try {
            $folderBulan = $this->filterMode === 'bulan'
                ? str_replace('-', '_', strrev(str_replace('-', '_', strrev($this->filterBulan))))
                : Carbon::parse($this->filterTglAwal)->format('m_Y');
            [$year, $month] = $this->filterMode === 'bulan'
                ? explode('-', $this->filterBulan)
                : [Carbon::parse($this->filterTglAwal)->format('Y'), Carbon::parse($this->filterTglAwal)->format('m')];
            $folderBulan = $month . '_' . $year;
            $basePath = 'klaim/' . $folderBulan;

            $mountBase = Storage::disk('local')->path('mount/bpjs');
            $uploadBase = Storage::disk('local')->path('upload/bpjs');

            $generated = [];
            $errors = [];
            $skipped = [];

            foreach ($targets as $key) {
                [$refType, $refNo] = explode('_', $key, 2);
                $table = $refType === 'RI' ? 'rstxn_riuploadbpjses' : 'rstxn_rjuploadbpjses';
                $fkCol = $refType === 'RI' ? 'rihdr_no' : 'rj_no';

                $berkas = DB::table($table)
                    ->where($fkCol, $refNo)
                    ->whereNotNull('uploadbpjs')
                    ->orderBy('seq_file')
                    ->pluck('uploadbpjs')
                    ->toArray();

                if (empty($berkas)) {
                    $skipped[] = $key . ': tidak ada berkas';
                    continue;
                }

                $noSep = null;
                if ($refType === 'RI') {
                    $noSep = DB::table('rstxn_rihdrs')
                        ->where('rihdr_no', $refNo)
                        ->value(DB::raw("json_value(datadaftarri_json, '$.sep.noSep')"));
                } else {
                    $noSep = DB::table('rstxn_rjhdrs')
                        ->where('rj_no', $refNo)
                        ->value(DB::raw("json_value(datadaftarpolirj_json, '$.sep.noSep')"));
                }

                if (empty($noSep)) {
                    $skipped[] = $key . ': SEP tidak ditemukan';
                    continue;
                }

                $pdfFiles = [];
                foreach ($berkas as $filename) {
                    if (is_file($mountBase . '/' . $filename)) {
                        $pdfFiles[] = $mountBase . '/' . $filename;
                    } elseif (is_file($uploadBase . '/' . $filename)) {
                        $pdfFiles[] = $uploadBase . '/' . $filename;
                    }
                }

                if (empty($pdfFiles)) {
                    $errors[] = $noSep . ': file fisik tidak ditemukan';
                    continue;
                }

                $subFolder = strtolower($refType);
                $outDir = $basePath . '/' . $subFolder;
                Storage::disk('local')->makeDirectory($outDir);

                $sepSafe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $noSep);
                $outputFile = Storage::disk('local')->path($outDir . '/' . $sepSafe . '.pdf');

                if (count($pdfFiles) === 1) {
                    copy($pdfFiles[0], $outputFile);
                } else {
                    $escaped = array_map('escapeshellarg', $pdfFiles);
                    $cmd = 'pdfunite ' . implode(' ', $escaped) . ' ' . escapeshellarg($outputFile) . ' 2>&1';
                    $output = [];
                    $exitCode = 0;
                    exec($cmd, $output, $exitCode);
                    if ($exitCode !== 0) {
                        $errors[] = $noSep . ': gagal merge (' . implode(' ', $output) . ')';
                        continue;
                    }
                }

                $generated[] = [
                    'sep' => $noSep,
                    'type' => $refType,
                    'berkas' => count($pdfFiles),
                    'path' => $outDir . '/' . $sepSafe . '.pdf',
                ];
            }

            $this->bundlingResult = [
                'folder' => $basePath,
                'generated' => $generated,
                'errors' => $errors,
                'skipped' => $skipped,
                'total' => count($targets),
            ];

            $msg = count($generated) . '/' . count($targets) . ' klaim berhasil di-bundling.';
            if (!empty($errors)) $msg .= ' ' . count($errors) . ' error.';
            if (!empty($skipped)) $msg .= ' ' . count($skipped) . ' dilewati.';
            $this->dispatch('toast', type: empty($errors) ? 'success' : 'warning', message: $msg);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Bundling gagal: ' . $e->getMessage());
        }

        $this->bundlingProcessing = false;
    }

    public function downloadBundlingZip()
    {
        $folder = $this->bundlingResult['folder'] ?? '';
        if (empty($folder)) {
            $this->dispatch('toast', type: 'error', message: 'Generate bundling terlebih dahulu.');
            return null;
        }

        $basePath = Storage::disk('local')->path($folder);
        if (!is_dir($basePath)) {
            $this->dispatch('toast', type: 'error', message: 'Folder bundling tidak ditemukan.');
            return null;
        }

        $zipName = str_replace('/', '_', $folder) . '.zip';
        $zipPath = Storage::disk('local')->path('klaim/' . $zipName);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuat ZIP.');
            return null;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath));
        foreach ($files as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($basePath . '/', '', $file->getRealPath());
                $zip->addFile($file->getRealPath(), $relativePath);
            }
        }
        $zip->close();

        return response()->streamDownload(function () use ($zipPath) {
            readfile($zipPath);
            @unlink($zipPath);
        }, $zipName, ['Content-Type' => 'application/zip']);
    }
};
?>

<div>
    <x-page-title
        title="Bundling Klaim BPJS"
        subtitle="Gabungkan berkas per klaim jadi 1 PDF — struktur folder klaim/MM_YYYY/rj|ugd|ri/noSep.pdf" />

    {{-- TOOLBAR --}}
    <div class="p-4 mb-4 bg-canvas dark:bg-gray-800 rounded-2xl border border-hairline dark:border-gray-700">
        <div class="flex flex-wrap items-end gap-3">

            {{-- Mode filter --}}
            <div>
                <label class="block mb-1 text-xs font-medium text-muted">Filter</label>
                <select wire:model.live="filterMode"
                    class="px-3 py-2 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                    <option value="bulan">Per Bulan</option>
                    <option value="tanggal">Per Tanggal</option>
                </select>
            </div>

            @if ($filterMode === 'bulan')
                <div>
                    <label class="block mb-1 text-xs font-medium text-muted">Bulan</label>
                    <input type="month" wire:model.live="filterBulan"
                        class="px-3 py-2 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-600 dark:text-white" />
                </div>
            @else
                <div>
                    <label class="block mb-1 text-xs font-medium text-muted">Dari</label>
                    <input type="date" wire:model.live="filterTglAwal"
                        class="px-3 py-2 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-600 dark:text-white" />
                </div>
                <div>
                    <label class="block mb-1 text-xs font-medium text-muted">Sampai</label>
                    <input type="date" wire:model.live="filterTglAkhir"
                        class="px-3 py-2 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-600 dark:text-white" />
                </div>
            @endif

            {{-- Jenis Rawat --}}
            <div>
                <label class="block mb-1 text-xs font-medium text-muted">Jenis</label>
                <select wire:model.live="filterRefType"
                    class="px-3 py-2 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                    <option value="">Semua</option>
                    <option value="RJ">RJ</option>
                    <option value="RI">RI</option>
                </select>
            </div>

            {{-- Search --}}
            <div class="flex-1 min-w-[180px]">
                <label class="block mb-1 text-xs font-medium text-muted">Cari</label>
                <input type="text" wire:model.live.debounce.400ms="searchKeyword"
                    placeholder="Nama pasien, No RM..."
                    class="w-full px-3 py-2 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-600 dark:text-white focus:ring-brand focus:border-brand" />
            </div>

            {{-- Per Page --}}
            <div>
                <label class="block mb-1 text-xs font-medium text-muted">Per hal</label>
                <select wire:model.live="itemsPerPage"
                    class="px-3 py-2 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                </select>
            </div>

            <div>
                <button type="button" wire:click="resetFilters"
                    class="px-3 py-2 text-sm font-medium text-muted hover:text-ink dark:hover:text-white transition">
                    Reset
                </button>
            </div>
        </div>
    </div>

    {{-- ACTIONS BAR --}}
    <div class="flex items-center justify-between p-4 mb-4 bg-canvas dark:bg-gray-800 rounded-2xl border border-hairline dark:border-gray-700">
        <div class="flex items-center gap-4 text-sm text-muted">
            <span>Total: <strong class="text-ink dark:text-white">{{ $this->stats['total'] }}</strong></span>
            <span>Dipilih: <strong class="text-indigo-600 dark:text-indigo-400">{{ $this->stats['selected'] }}</strong></span>
            @if (!empty($bundlingResult['generated']))
                <span class="text-emerald-600">Berhasil: <strong>{{ count($bundlingResult['generated']) }}</strong></span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if (!empty($bundlingResult['generated']))
                <button type="button" wire:click="downloadBundlingZip"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Download ZIP
                </button>
            @endif
            <button type="button" wire:click="generateBundling" wire:loading.attr="disabled" wire:target="generateBundling"
                :disabled="@js(empty($this->selected))"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="generateBundling" class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                    Bundling ({{ count($selected) }})
                </span>
                <span wire:loading wire:target="generateBundling" class="flex items-center gap-1.5">
                    <x-loading /> Processing...
                </span>
            </button>
        </div>
    </div>

    {{-- RESULT PANEL --}}
    @if (!empty($bundlingResult))
        <div class="p-4 mb-4 bg-canvas dark:bg-gray-800 rounded-2xl border border-hairline dark:border-gray-700 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-ink dark:text-white uppercase tracking-wider">Hasil Bundling</h3>
                <span class="text-xs text-muted">
                    Output: <code class="bg-surface-soft dark:bg-gray-900 px-1.5 py-0.5 rounded">storage/app/private/{{ $bundlingResult['folder'] ?? '' }}</code>
                </span>
            </div>

            <div class="flex items-center gap-4 text-sm">
                <span class="text-muted">Total: <strong>{{ $bundlingResult['total'] ?? 0 }}</strong></span>
                <span class="text-emerald-600">Berhasil: <strong>{{ count($bundlingResult['generated'] ?? []) }}</strong></span>
                @if (!empty($bundlingResult['errors']))
                    <span class="text-red-600">Error: <strong>{{ count($bundlingResult['errors']) }}</strong></span>
                @endif
                @if (!empty($bundlingResult['skipped']))
                    <span class="text-yellow-600">Dilewati: <strong>{{ count($bundlingResult['skipped']) }}</strong></span>
                @endif
            </div>

            @if (!empty($bundlingResult['errors']) || !empty($bundlingResult['skipped']))
                <div class="p-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                    <ul class="text-xs text-red-700 dark:text-red-400 space-y-0.5">
                        @foreach (array_merge($bundlingResult['errors'] ?? [], $bundlingResult['skipped'] ?? []) as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    {{-- TABLE --}}
    <div class="overflow-hidden bg-canvas dark:bg-gray-800 rounded-2xl border border-hairline dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold uppercase bg-surface-soft dark:bg-gray-900 text-muted">
                    <tr>
                        <th class="px-3 py-3 w-10">
                            <input type="checkbox" wire:model.live="selectAll"
                                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                        </th>
                        <th class="px-3 py-3">Pasien</th>
                        <th class="px-3 py-3">No. SEP</th>
                        <th class="px-3 py-3 text-center">Tipe</th>
                        <th class="px-3 py-3 text-center">Tanggal</th>
                        <th class="px-3 py-3 text-center">Berkas</th>
                        <th class="px-3 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline dark:divide-gray-700">
                    @forelse ($this->rows as $row)
                        @php
                            $bundled = collect($bundlingResult['generated'] ?? [])->firstWhere('sep', $row->no_sep);
                        @endphp
                        <tr class="transition hover:bg-surface-soft dark:hover:bg-gray-800/50"
                            wire:key="bk-{{ $row->key }}">
                            <td class="px-3 py-2.5">
                                <input type="checkbox" value="{{ $row->key }}" wire:model.live="selected"
                                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="font-medium text-ink dark:text-white">{{ $row->reg_name }}</div>
                                <div class="text-xs text-muted">{{ $row->reg_no }}</div>
                            </td>
                            <td class="px-3 py-2.5 font-mono text-xs text-ink dark:text-white">
                                {{ $row->no_sep ?: '-' }}
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $row->ref_type === 'RI' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' }}">
                                    {{ $row->ref_type }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-center text-xs text-muted">
                                {{ $row->rj_date ? Carbon::parse($row->rj_date)->format('d/m/Y') : '-' }}
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                    {{ $row->jml_berkas }} file
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                @if ($bundled)
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        Bundled
                                    </span>
                                @elseif (empty($row->no_sep))
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                        No SEP
                                    </span>
                                @else
                                    <span class="text-xs text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-muted">
                                Tidak ada data berkas untuk filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->rows->hasPages())
            <div class="px-4 py-3 border-t border-hairline dark:border-gray-700">
                {{ $this->rows->links() }}
            </div>
        @endif
    </div>
</div>
