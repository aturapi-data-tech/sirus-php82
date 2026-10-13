<?php
// resources/views/pages/components/rekam-medis/penunjang/upload-penunjang-display/upload-penunjang-display.blade.php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\OracleLob;

new class extends Component {
    use WithPagination;

    /* =======================
     | Props & Filter
     * ======================= */
    #[Reactive]
    public string $regNo = '';

    public string $searchKeyword = '';
    public string $filterTahun = '';
    public int $itemsPerPage = 3;

    // View PDF
    public string $viewFilePDF = '';

    /* =======================
     | Mount
     * ======================= */
    public function mount(string $regNo = ''): void
    {
        $this->regNo = $regNo;
    }

    public function loadPasien(string $regNo): void
    {
        $this->regNo = $regNo;
        $this->viewFilePDF = '';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterTahun']);
        $this->resetPage();
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }
    public function updatedFilterTahun(): void
    {
        $this->resetPage();
    }
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /* =======================
     | Daftar Tahun Filter — dari tahun upload yang benar-benar ada
     * ======================= */
    #[Computed]
    public function tahunList()
    {
        return collect($this->semuaUpload)->pluck('tahun')->filter()->unique()->sortDesc()->values();
    }

    /* =======================
     | Semua upload pasien — flat dari kunjungan RJ, UGD, dan RI (termasuk yang
     | masih dilayani: upload EMR langsung tersimpan di JSON kunjungan, jadi DPJP
     | sudah bisa melihat sebelum pasien pulang).
     |
     | Struktur array per item (pemeriksaan.uploadHasilPenunjang[] di JSON kunjungan):
     |   file → filename (dmYHis.ext) · desc → keterangan · tglUpload
     |   penanggungJawab: userLog · userLogDate · userLogCode
     * ======================= */
    #[Computed]
    public function semuaUpload(): array
    {
        if (!$this->regNo) {
            return [];
        }

        $sumberList = [
            [
                'jalur' => 'RJ',
                'rows' => DB::table('rstxn_rjhdrs as rj')
                    ->join('rsview_rjkasir as v', 'v.rj_no', '=', 'rj.rj_no')
                    ->where('v.reg_no', $this->regNo)
                    ->whereNotNull('rj.datadaftarpolirj_json')
                    ->select(['rj.rj_no as no_kunjungan', DB::raw("TO_CHAR(rj.rj_date, 'dd/mm/yyyy hh24:mi:ss') as tgl_kunjungan"), 'v.poli_desc as unit', 'v.dr_name', 'rj.datadaftarpolirj_json as json'])
                    ->get(),
                'tabel' => ['rstxn_rjhdrs', 'rj_no', 'datadaftarpolirj_json'],
            ],
            [
                'jalur' => 'UGD',
                'rows' => DB::table('rsview_ugdkasir')
                    ->where('reg_no', $this->regNo)
                    ->whereNotNull('datadaftarugd_json')
                    ->select(['rj_no as no_kunjungan', DB::raw("TO_CHAR(rj_date, 'dd/mm/yyyy hh24:mi:ss') as tgl_kunjungan"), DB::raw("'UGD' as unit"), 'dr_name', 'datadaftarugd_json as json'])
                    ->get(),
                'tabel' => ['rstxn_ugdhdrs', 'rj_no', 'datadaftarugd_json'],
            ],
            [
                'jalur' => 'RI',
                'rows' => DB::table('rsview_rihdrs')
                    ->where('reg_no', $this->regNo)
                    ->whereNotNull('datadaftarri_json')
                    ->select(['rihdr_no as no_kunjungan', DB::raw("TO_CHAR(entry_date, 'dd/mm/yyyy hh24:mi:ss') as tgl_kunjungan"), 'bangsal_name as unit', 'dr_name', 'datadaftarri_json as json'])
                    ->get(),
                'tabel' => ['rstxn_rihdrs', 'rihdr_no', 'datadaftarri_json'],
            ],
        ];

        $uploadList = [];
        foreach ($sumberList as $sumber) {
            [$tabel, $kolomKunci, $kolomJson] = $sumber['tabel'];

            foreach ($sumber['rows'] as $row) {
                try {
                    $isiJson = OracleLob::read($row->json ?? null, $tabel, $kolomKunci, $row->no_kunjungan, $kolomJson);
                    $dataKunjungan = json_decode($isiJson, true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    continue;
                }

                foreach ($dataKunjungan['pemeriksaan']['uploadHasilPenunjang'] ?? [] as $upload) {
                    $tglUpload = $upload['tglUpload'] ?? '';
                    $waktu = DateTime::createFromFormat('d/m/Y H:i:s', $tglUpload) ?: null;

                    $uploadList[] = [
                        'jalur' => $sumber['jalur'],
                        'noKunjungan' => $row->no_kunjungan,
                        'tglKunjungan' => $row->tgl_kunjungan,
                        'unit' => $row->unit ?? '-',
                        'drName' => $row->dr_name ?? '-',
                        'file' => $upload['file'] ?? '',
                        'desc' => $upload['desc'] ?? '-',
                        'tglUpload' => $tglUpload ?: '-',
                        'tahun' => $waktu?->format('Y'),
                        'urut' => $waktu?->format('YmdHis') ?? '0',
                        'userLog' => $upload['penanggungJawab']['userLog'] ?? '-',
                    ];
                }
            }
        }

        usort($uploadList, fn($rowPertama, $rowKedua) => strcmp($rowKedua['urut'], $rowPertama['urut']));

        return $uploadList;
    }

    /* =======================
     | Items setelah filter tahun & kata kunci
     * ======================= */
    #[Computed]
    public function allItems(): array
    {
        $keyword = mb_strtoupper(trim($this->searchKeyword));

        return array_values(
            array_filter($this->semuaUpload, function ($row) use ($keyword) {
                if ($this->filterTahun && $row['tahun'] !== (string) $this->filterTahun) {
                    return false;
                }
                return $keyword === '' || str_contains(mb_strtoupper($row['desc'] . ' ' . $row['userLog']), $keyword);
            }),
        );
    }

    /* =======================
     | Stats
     * ======================= */
    #[Computed]
    public function statsUpload(): array
    {
        $uploadList = collect($this->allItems);

        return [
            'total' => $uploadList->count(),
            'ada_file' => $uploadList->filter(fn($row) => $this->fileExists($row['file']))->count(),
            'total_kunjungan' => $uploadList->map(fn($row) => $row['jalur'] . $row['noKunjungan'])->unique()->count(),
        ];
    }

    /* =======================
     | Rows — manual pagination karena data dari JSON bukan query langsung
     * ======================= */
    #[Computed]
    public function rows()
    {
        $uploadList = $this->allItems;
        $halaman = $this->getPage();
        $perHalaman = $this->itemsPerPage;

        return new LengthAwarePaginator(array_slice($uploadList, ($halaman - 1) * $perHalaman, $perHalaman), count($uploadList), $perHalaman, $halaman, ['path' => request()->url()]);
    }

    /* =======================
     | Resolve URL file upload penunjang
     |
     | Strategi: mount/... (SMB share) → upload/... (lokal sementara sebelum sync)
     | → legacy raw path. Pakai disk 'local' (root: storage/app/private).
     | Kandidat sama dengan openModalViewPenunjang di EMR RJ/UGD/RI.
     * ======================= */
    public function resolveFileUrl(?string $file): ?string
    {
        if (empty($file)) {
            return null;
        }

        $disk = Storage::disk('local');
        $filename = basename($file);
        $kandidatList = array_filter(array_unique(['mount/penunjang/emr/uploadHasilPenunjang/' . $filename, 'upload/penunjang/emr/uploadHasilPenunjang/' . $filename, $file]));

        foreach ($kandidatList as $kandidat) {
            if ($disk->exists($kandidat)) {
                // Whitelist files.show hanya mount/...; route sendiri yang fallback ke upload/.
                // Mengirim path upload/ (file belum ter-sync) = 403.
                $path = preg_replace('#^upload/penunjang/emr/#', 'mount/penunjang/emr/', $kandidat, 1);
                return route('files.show', ['path' => $path]);
            }
        }

        return null;
    }

    public function fileExists(?string $file): bool
    {
        return $this->resolveFileUrl($file) !== null;
    }

    /* =======================
     | Open / Close PDF Viewer — modal + iframe pakai URL (route files.show)
     * ======================= */
    public function openViewPDF(string $file): void
    {
        if (!auth()->user()?->can('penunjang.lihatHasil')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang melihat hasil penunjang.');
            return;
        }

        $url = $this->resolveFileUrl($file);

        if (!$url) {
            $this->dispatch('toast', type: 'error', message: 'File tidak ditemukan di server.');
            return;
        }

        $this->viewFilePDF = $url;
        $this->dispatch('open-modal', name: 'view-upload-penunjang-pdf');
    }

    public function closeViewPDF(): void
    {
        $this->viewFilePDF = '';
        $this->dispatch('close-modal', name: 'view-upload-penunjang-pdf');
    }
};
?>

<div>
    <div
        class="w-full p-4 space-y-3 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

        {{-- ── HEADER + FILTER ─────────────────────────────────────── --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-sm font-semibold tracking-wide uppercase text-body dark:text-gray-300">
                    Riwayat Upload Hasil Penunjang
                </h3>
                @if ($regNo)
                    <span class="px-2 py-0.5 text-xs rounded-full bg-primary/10 text-primary">
                        {{ $this->statsUpload['total'] }} File
                    </span>
                    <span class="px-2 py-0.5 text-xs rounded-full text-muted bg-surface-soft dark:bg-gray-700 dark:text-gray-300">
                        {{ $this->statsUpload['total_kunjungan'] }} Kunjungan
                    </span>
                @endif
            </div>

            @if ($regNo)
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative">
                        <svg class="absolute left-2.5 top-2.5 w-4 h-4 text-muted-soft" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input type="text" wire:model.live.debounce.400ms="searchKeyword"
                            placeholder="Cari keterangan / petugas..."
                            class="pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-lg
                                   focus:ring-2 focus:ring-primary focus:border-primary
                                   dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                    </div>

                    <select wire:model.live="filterTahun"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg
                               focus:ring-2 focus:ring-primary focus:border-primary
                               dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="">Semua Tahun</option>
                        @foreach ($this->tahunList as $tahun)
                            <option value="{{ $tahun }}">{{ $tahun }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="itemsPerPage"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg
                               focus:ring-2 focus:ring-primary focus:border-primary
                               dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="3">3 / hal</option>
                        <option value="5">5 / hal</option>
                        <option value="10">10 / hal</option>
                    </select>

                    @if ($searchKeyword || $filterTahun)
                        <x-secondary-button wire:click="resetFilters" type="button">
                            Reset
                        </x-secondary-button>
                    @endif
                </div>
            @endif
        </div>

        {{-- ── DAFTAR FILE ─────────────────────────────────────────── --}}
        <div class="border divide-y rounded-lg border-hairline divide-hairline dark:border-gray-700 dark:divide-gray-700">
            @forelse ($this->rows as $row)
                @php
                    $fileExists = $this->fileExists($row['file'] ?? null);
                    $isImage = (bool) preg_match('/\.(jpe?g|png)$/i', $row['file'] ?? '');
                    $jalurClass = match ($row['jalur']) {
                        'RI' => 'text-purple-700 bg-purple-100',
                        'UGD' => 'text-red-700 bg-red-100',
                        default => 'text-teal-700 bg-teal-100',
                    };
                    $jalurText = match ($row['jalur']) {
                        'RI' => 'Rawat Inap',
                        'UGD' => 'UGD',
                        default => 'Rawat Jalan',
                    };
                @endphp

                <div wire:key="upload-penunjang-{{ $row['jalur'] }}-{{ $row['noKunjungan'] }}-{{ $row['file'] }}"
                    class="flex items-center gap-3 px-3 py-3 transition-colors bg-canvas hover:bg-surface-soft dark:bg-gray-800 dark:hover:bg-gray-750">

                    {{-- Ikon jenis file --}}
                    <div
                        class="flex items-center justify-center w-10 h-10 rounded-xl shrink-0
                               {{ $isImage ? 'bg-blue-100 dark:bg-blue-400/15' : 'bg-yellow-400/10 dark:bg-yellow-400/15' }}">
                        @if ($isImage)
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        @else
                            <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM8 13h8v1.5H8V13zm0 3h8v1.5H8V16zm0-6h3v1.5H8V10z" />
                            </svg>
                        @endif
                    </div>

                    {{-- Isi --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold truncate text-ink dark:text-gray-100">{{ $row['desc'] }}</span>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $jalurClass }}">{{ $jalurText }}</span>
                            @unless ($fileExists)
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full text-red-700 bg-red-100">
                                    ✗ File tidak ditemukan
                                </span>
                            @endunless
                        </div>
                        <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                            {{ $row['unit'] }} · {{ $row['drName'] }} · kunjungan {{ $row['tglKunjungan'] }}
                        </div>
                        <div class="mt-0.5 text-xs text-muted-soft">
                            Diupload {{ $row['userLog'] }} — <span class="font-mono">{{ $row['tglUpload'] }}</span>
                        </div>
                    </div>

                    {{-- Aksi --}}
                    @can('penunjang.lihatHasil')
                        @if ($fileExists)
                            <x-outline-button type="button" class="shrink-0"
                                wire:click="openViewPDF({{ json_encode($row['file']) }})">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Lihat
                            </x-outline-button>
                        @endif
                    @endcan
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    @if ($regNo)
                        <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="mt-2 text-muted">Belum ada file penunjang yang diupload untuk pasien ini.</p>
                    @else
                        <p class="text-muted">Silakan pilih pasien terlebih dahulu.</p>
                    @endif
                </div>
            @endforelse
        </div>

        @if ($regNo && $this->rows->hasPages())
            <div>
                {{ $this->rows->links() }}
            </div>
        @endif
    </div>

    {{-- ── MODAL LIHAT PDF ─────────────────────────────────────── --}}
    <x-modal name="view-upload-penunjang-pdf" size="full" height="full" focusable>

        <div class="flex flex-col h-[calc(100vh-4rem)]" wire:key="view-upload-penunjang-{{ $viewFilePDF }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700 shrink-0">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);
                           background-size:14px 14px">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-xl
                                   bg-yellow-400/10 dark:bg-yellow-400/15">
                            <svg class="w-5 h-5 text-yellow-500" xmlns="http://www.w3.org/2000/svg"
                                fill="currentColor" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1
                                         1.5L18.5 9H13V3.5zM8 13h8v1.5H8V13zm0 3h8v1.5H8V16zm0-6h3v1.5H8V10z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-ink dark:text-gray-100">
                                Hasil Penunjang
                            </h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                Preview file hasil penunjang pasien (PDF/JPG)
                            </p>
                        </div>
                    </div>

                    <x-secondary-button type="button" wire:click="closeViewPDF" class="!p-2">
                        <span class="sr-only">Tutup</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0
                                   111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414
                                   1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586
                                   10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- BODY — iframe via base64 data URI, tidak perlu route --}}
            <div class="flex-1 min-h-0 bg-surface-soft dark:bg-gray-950">
                @if ($viewFilePDF && (str_starts_with($viewFilePDF, 'data:image/') || preg_match('/\.(jpe?g|png)$/i', $viewFilePDF)))
                    {{-- JPG: tampil utuh di tengah, iframe menaruh gambar di pojok kiri atas ukuran asli --}}
                    <div class="flex items-center justify-center w-full h-full p-4 overflow-auto">
                        <img src="{{ $viewFilePDF }}" alt="Hasil penunjang" class="object-contain max-w-full max-h-full rounded shadow" />
                    </div>
                @elseif ($viewFilePDF)
                    <iframe src="{{ $viewFilePDF }}" class="w-full h-full border-0"></iframe>
                @endif
            </div>

            {{-- FOOTER --}}
            <div
                class="shrink-0 px-6 py-4 bg-canvas border-t border-hairline
                       dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-muted dark:text-gray-400">
                        File dibuka dalam mode preview — tidak dapat diedit.
                    </p>
                    <x-secondary-button type="button" wire:click="closeViewPDF">
                        Tutup
                    </x-secondary-button>
                </div>
            </div>

        </div>

    </x-modal>
</div>
