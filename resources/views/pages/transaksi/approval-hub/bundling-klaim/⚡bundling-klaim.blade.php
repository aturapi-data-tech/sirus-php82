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

    public string $bundlingType = 'RJ';

    public string $filterMode = 'bulanan';
    public string $filterBulan = '';
    public string $filterTanggal = '';
    public int $itemsPerPage = 50;

    public string $searchKeyword = '';

    // Bundling state
    public array $selected = [];
    public bool $selectAll = false;
    public array $bundlingResult = [];
    public bool $bundlingProcessing = false;

    public function mount(string $bundlingType = 'RJ'): void
    {
        $this->bundlingType = strtoupper($bundlingType);

        $prefix = 'bundling-' . strtolower($this->bundlingType) . '-';
        $this->filterMode = session($prefix . 'filterMode', 'bulanan');
        $this->filterBulan = session($prefix . 'filterBulan', now()->format('m/Y'));
        $this->filterTanggal = session($prefix . 'filterTanggal', now()->format('d/m/Y'));
        $this->itemsPerPage = (int) session($prefix . 'itemsPerPage', 50);
    }

    public function updatedFilterMode(): void
    {
        session()->put('bundling-' . strtolower($this->bundlingType) . '-filterMode', $this->filterMode);
        $this->resetPage();
    }
    public function updatedFilterBulan(): void
    {
        session()->put('bundling-' . strtolower($this->bundlingType) . '-filterBulan', $this->filterBulan);
        $this->resetPage(); $this->selected = []; $this->selectAll = false;
    }
    public function updatedFilterTanggal(): void
    {
        session()->put('bundling-' . strtolower($this->bundlingType) . '-filterTanggal', $this->filterTanggal);
        $this->resetPage(); $this->selected = []; $this->selectAll = false;
    }
    public function updatedItemsPerPage(): void
    {
        session()->put('bundling-' . strtolower($this->bundlingType) . '-itemsPerPage', $this->itemsPerPage);
        $this->resetPage();
    }
    public function updatedSearchKeyword(): void { $this->resetPage(); }

    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selected = $this->rows->pluck('key')->toArray();
        } else {
            $this->selected = [];
        }
    }

    public function toggleSelected(string $key): void
    {
        if (in_array($key, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$key]));
        } else {
            $this->selected[] = $key;
        }
    }

    public function resetFilters(): void
    {
        $this->filterMode = 'bulanan';
        $this->filterBulan = now()->format('m/Y');
        $this->filterTanggal = now()->format('d/m/Y');
        $this->filterRefType = '';
        $this->searchKeyword = '';
        $this->selected = [];
        $this->selectAll = false;
        $this->bundlingResult = [];
        $this->resetPage();
    }

    private function dateRange(): array
    {
        if ($this->filterMode === 'bulanan') {
            if (!preg_match('/^(\d{1,2})\/(\d{4})$/', $this->filterBulan, $m)) {
                return [now()->startOfMonth(), now()->endOfMonth()];
            }
            $start = Carbon::createFromDate((int) $m[2], (int) $m[1], 1)->startOfMonth();
            return [$start, $start->copy()->endOfMonth()];
        }

        if (!preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $this->filterTanggal, $m)) {
            return [now()->startOfDay(), now()->endOfDay()];
        }
        $date = Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1]);
        return [$date->startOfDay(), $date->endOfDay()];
    }

    #[Computed]
    public function rows()
    {
        [$start, $end] = $this->dateRange();
        $startStr = $start->format('Y-m-d');
        $endStr = $end->format('Y-m-d') . ' 23:59:59';

        if ($this->bundlingType === 'RI') {
            $query = DB::table('rstxn_rihdrs as h')
                ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('rstxn_riuploadbpjses as u')
                        ->whereColumn('u.rihdr_no', 'h.rihdr_no')
                        ->whereNotNull('u.uploadbpjs');
                })
                ->whereBetween('h.entry_date', [
                    DB::raw("to_date('$startStr','YYYY-MM-DD')"),
                    DB::raw("to_date('$endStr','YYYY-MM-DD HH24:MI:SS')"),
                ])
                ->select([
                    DB::raw("'RI' as ref_type"),
                    'h.rihdr_no as ref_no',
                    'h.reg_no',
                    'p.reg_name',
                    DB::raw("h.entry_date as rj_date"),
                    'h.vno_sep as no_sep',
                    DB::raw("(SELECT COUNT(*) FROM rstxn_riuploadbpjses u2 WHERE u2.rihdr_no = h.rihdr_no AND u2.uploadbpjs IS NOT NULL) as jml_berkas"),
                ]);
        } else {
            $query = DB::table('rstxn_rjhdrs as h')
                ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('rstxn_rjuploadbpjses as u')
                        ->whereColumn('u.rj_no', 'h.rj_no')
                        ->whereNotNull('u.uploadbpjs');
                })
                ->whereBetween('h.rj_date', [
                    DB::raw("to_date('$startStr','YYYY-MM-DD')"),
                    DB::raw("to_date('$endStr','YYYY-MM-DD HH24:MI:SS')"),
                ])
                ->select([
                    DB::raw("'{$this->bundlingType}' as ref_type"),
                    'h.rj_no as ref_no',
                    'h.reg_no',
                    'p.reg_name',
                    'h.rj_date',
                    'h.vno_sep as no_sep',
                    DB::raw("(SELECT COUNT(*) FROM rstxn_rjuploadbpjses u2 WHERE u2.rj_no = h.rj_no AND u2.uploadbpjs IS NOT NULL) as jml_berkas"),
                ]);
        }

        if ($this->searchKeyword !== '') {
            $kw = '%' . strtoupper(trim($this->searchKeyword)) . '%';
            $query->where(function ($q) use ($kw) {
                $q->where(DB::raw('UPPER(p.reg_name)'), 'LIKE', $kw)
                  ->orWhere(DB::raw('UPPER(h.reg_no)'), 'LIKE', $kw);
            });
        }

        $results = $query->orderByDesc('rj_date')->paginate($this->itemsPerPage);

        $results->getCollection()->transform(function ($row) {
            $row->key = $row->ref_type . '_' . $row->ref_no;
            return $row;
        });

        return $results;
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
            [$start] = $this->dateRange();
            $folderBulan = $start->format('m_Y');
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
                    $noSep = DB::table('rstxn_rihdrs')->where('rihdr_no', $refNo)->value('vno_sep');
                } else {
                    $noSep = DB::table('rstxn_rjhdrs')->where('rj_no', $refNo)->value('vno_sep');
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
                    $cmd = 'env -u LD_LIBRARY_PATH pdfunite ' . implode(' ', $escaped) . ' ' . escapeshellarg($outputFile) . ' 2>&1';
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
        title="Bundling Klaim {{ $bundlingType }}"
        subtitle="Gabungkan berkas per klaim jadi 1 PDF — struktur folder klaim/MM_YYYY/{{ strtolower($bundlingType) }}/noSep.pdf" />

    {{-- TOOLBAR --}}
    <div class="sticky z-30 px-4 py-3 mb-4 bg-canvas border-b border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-wrap items-end gap-3">

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
                    <x-text-input wire:model.live.debounce.400ms="searchKeyword" class="block w-full pl-10"
                        placeholder="Cari Nama Pasien / No RM / No SEP..." />
                </div>
            </div>

            {{-- MODE FILTER: Bulanan / Harian --}}
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

            {{-- FILTER BULAN (mode bulanan) atau TANGGAL (mode harian) --}}
            @if ($filterMode === 'bulanan')
                <div class="w-full sm:w-auto">
                    <x-input-label value="Bulan" />
                    <div class="relative mt-1">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                            class="block w-full pl-10 sm:w-40" placeholder="mm/yyyy" maxlength="7" />
                    </div>
                </div>
            @else
                <div class="w-full sm:w-auto">
                    <x-input-label value="Tanggal" />
                    <div class="relative mt-1">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <x-text-input type="text" wire:model.live.debounce.500ms="filterTanggal"
                            class="block w-full pl-10 sm:w-44" placeholder="dd/mm/yyyy" maxlength="10" />
                    </div>
                </div>
            @endif

            {{-- RIGHT: stats + actions --}}
            <div class="flex flex-wrap items-center gap-3 ml-auto">
                <span class="text-xs text-muted">Total: <strong class="text-ink dark:text-white">{{ $this->stats['total'] }}</strong></span>
                <span class="text-xs text-muted">Dipilih: <strong class="text-indigo-600 dark:text-indigo-400">{{ $this->stats['selected'] }}</strong></span>
                @if (!empty($bundlingResult['generated']))
                    <span class="text-xs text-emerald-600">Berhasil: <strong>{{ count($bundlingResult['generated']) }}</strong></span>
                    <button type="button" wire:click="downloadBundlingZip"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        ZIP
                    </button>
                @endif
                <button type="button" wire:click="generateBundling" wire:loading.attr="disabled" wire:target="generateBundling"
                    :disabled="@js(empty($this->selected))"
                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="generateBundling" class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                        Bundling ({{ count($selected) }})
                    </span>
                    <span wire:loading wire:target="generateBundling" class="flex items-center gap-1">
                        <x-loading /> Processing...
                    </span>
                </button>
                <x-toolbar-refresh-reset :label="null" />
                <div class="w-24">
                    <x-select-input wire:model.live="itemsPerPage">
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                    </x-select-input>
                </div>
            </div>

        </div>
    </div>

    {{-- PANDUAN --}}
    <div x-data="{ buka: false }" class="mb-4 overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
        <button type="button" x-on:click="buka = !buka"
            class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
            <span class="flex items-center min-w-0 gap-2">
                <svg class="w-4 h-4 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="truncate">Panduan: cara pakai Bundling Klaim</span>
            </span>
            <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="buka" x-cloak class="px-4 pb-4 space-y-4 text-sm text-blue-900 dark:text-blue-100">

            {{-- FUNGSI --}}
            <div>
                <div class="font-semibold">Apa itu Bundling Klaim?</div>
                <p class="mt-1">Menggabungkan semua berkas BPJS per klaim (SEP, Grouping, Rekam Medis, dll)
                    menjadi <span class="font-semibold">1 file PDF</span> per nomor SEP &mdash; siap disetor ke BPJS.</p>
            </div>

            {{-- ALUR --}}
            <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                <div class="font-semibold">Langkah-langkah</div>
                <ol class="mt-1 ml-4 space-y-1 list-decimal">
                    <li><span class="font-semibold">Filter data</span> &mdash; pilih mode
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Bulanan</span>
                        (mm/yyyy) atau
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Harian</span>
                        (dd/mm/yyyy). Filter jenis rawat (RJ/RI) jika perlu.</li>
                    <li><span class="font-semibold">Pilih klaim</span> &mdash; toggle ON per item, atau toggle
                        header untuk pilih semua yang tampil di halaman.</li>
                    <li><span class="font-semibold">Klik Bundling</span> &mdash; sistem merge berkas per klaim
                        menggunakan <span class="font-mono text-xs">pdfunite</span>, urut dari slot terendah.</li>
                    <li><span class="font-semibold">Cek hasil</span> &mdash; panel hasil muncul: berapa berhasil,
                        error, atau dilewati (tanpa SEP / file fisik hilang).</li>
                    <li><span class="font-semibold">Download ZIP</span> &mdash; klik tombol ZIP untuk unduh
                        semua hasil bundling sekaligus.</li>
                </ol>
            </div>

            {{-- OUTPUT --}}
            <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                <div class="font-semibold">Struktur output</div>
                <div class="p-2 mt-1 font-mono text-xs bg-blue-100 rounded dark:bg-blue-900/40">
                    storage/app/private/klaim/<br>
                    &nbsp;&nbsp;MM_YYYY/<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;rj/{noSep}.pdf<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;ri/{noSep}.pdf
                </div>
                <p class="mt-1">Satu file PDF per klaim, nama file = nomor SEP.</p>
            </div>

            {{-- CATATAN --}}
            <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                <div class="font-semibold">Catatan</div>
                <ul class="mt-1 ml-4 space-y-0.5 list-disc">
                    <li>Klaim tanpa SEP atau tanpa file fisik otomatis dilewati.</li>
                    <li>Jika hanya ada 1 berkas, file langsung di-copy (tidak perlu merge).</li>
                    <li>Data bisa sampai 6000 item/bulan &mdash; gunakan pagination dan filter untuk proses bertahap.</li>
                </ul>
            </div>

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
                        <th class="px-3 py-3 w-14">
                            <x-toggle wire:model.live="selectAll" :trueValue="true" :falseValue="false" onColor="bg-indigo-600" />
                        </th>
                        <th class="px-3 py-3">Pasien</th>
                        <th class="px-3 py-3">No. SEP</th>
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
                                <x-toggle :current="in_array($row->key, $selected)" :trueValue="true" :falseValue="false"
                                    wireClick="toggleSelected('{{ $row->key }}')" onColor="bg-indigo-600" />
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="font-medium text-ink dark:text-white">{{ $row->reg_name }}</div>
                                <div class="text-xs text-muted">{{ $row->reg_no }}</div>
                            </td>
                            <td class="px-3 py-2.5 font-mono text-xs text-ink dark:text-white">
                                {{ $row->no_sep ?: '-' }}
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
                            <td colspan="6" class="px-4 py-12 text-center text-muted">
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
