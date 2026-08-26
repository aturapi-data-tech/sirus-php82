<?php
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  GIZI RAWAT INAP — worklist unit gizi (port ANTRI_RI_GIZI /          ║
// ║  rit003gizi.fmb Oracle Forms)                                        ║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// Route: /ri/gizi → pages::transaksi.ri.gizi-ri.gizi-ri
//
// Tujuan: petugas gizi menentukan program diet harian pasien RI
// tanpa masuk EMR per pasien. Entri disimpan ke node yang sama
// dengan tab EMR Penilaian → Gizi (penilaian.gizi[] di datadaftarri_json) —
// satu sumber data, dua pintu.
//
// Rekap produksi dapur = hitung program diet TERAKHIR tiap pasien dirawat
// (diet berlaku terus sampai diganti entri baru).

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\OracleLob;
use App\Support\Options\GiziOptions;
use App\Support\Terminologi\AlergiSnomed;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public string $filterStatus = 'I'; // I = Dirawat (default), P = Pulang, '' = keduanya
    public string $filterBangsal = '';
    public int $itemsPerPage = 10;

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }
    public function updatedFilterBangsal(): void
    {
        $this->resetPage();
    }
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterBangsal']);
        $this->filterStatus = 'I';
        $this->resetPage();
    }

    public function openEntri(string $riHdrNo): void
    {
        $this->dispatch('gizi-ri-entri.open', riHdrNo: $riHdrNo);
    }

    public function cetakEtiketGizi(string $riHdrNo): void
    {
        $this->dispatch('cetak-etiket-gizi.open', riHdrNo: $riHdrNo);
    }

    // Buka modal yang sama dengan daftar-ri (event & komponen identik)
    public function openModulDokumen(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.modul-dokumen.open', riHdrNo: $riHdrNo);
    }

    public function openAdministrasiPasien(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.administrasi.open', riHdrNo: $riHdrNo);
    }

    // Setelah entri diet tersimpan di modal — listener kosong cukup:
    // request re-render menghitung ulang rows & rekap dari DB.
    #[On('refresh-after-ri.saved')]
    public function refreshList(): void
    {
    }

    private function baseQuery()
    {
        $query = DB::table('rsview_rihdrs as rv')->selectRaw("
                rv.rihdr_no,
                rv.reg_no,
                rv.reg_name,
                rv.sex,
                rv.address,
                rv.dr_name,
                rv.bangsal_id,
                rv.bangsal_name,
                rv.room_name,
                to_char(rv.birth_date,'dd/mm/yyyy') as birth_date,
                to_char(rv.entry_date,'dd/mm/yyyy hh24:mi') as entry_date_display,
                to_char(rv.exit_date,'dd/mm/yyyy hh24:mi') as exit_date_display,
                rv.ri_status,
                rv.datadaftarri_json
            ");

        if ($this->filterStatus !== '') {
            $query->where(DB::raw("NVL(rv.ri_status,'I')"), $this->filterStatus);
        } else {
            // "Semua" = Dirawat + Pulang; exclude batal (F) dll.
            $query->whereIn(DB::raw("NVL(rv.ri_status,'I')"), ['I', 'P']);
        }

        if ($this->filterBangsal !== '') {
            $query->where('rv.bangsal_id', $this->filterBangsal);
        }

        return $query;
    }

    /** Decode JSON EMR satu baris → ringkasan gizi untuk list & rekap. */
    private function ringkasGiziRow(object $row): void
    {
        $jsonRaw = OracleLob::read($row->datadaftarri_json ?? null, 'rstxn_rihdrs', 'rihdr_no', $row->rihdr_no, 'datadaftarri_json');
        $json = json_decode($jsonRaw ?: '{}', true) ?? [];
        $riwayatGizi = $json['penilaian']['gizi'] ?? [];

        $row->diet_terakhir = GiziOptions::entriTerakhirDengan($riwayatGizi, 'programDiet');
        $row->kategori_gizi = GiziOptions::entriTerakhirDengan($riwayatGizi, 'kategoriGizi');
        $row->jumlah_entri = count(GiziOptions::daftarEntri($riwayatGizi));

        // DPJP + level dokter (Utama / Rawat Gabung) — sama seperti Daftar RI
        $dpjpList = [];
        foreach ($json['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [] as $levelingDokter) {
            if (!empty($levelingDokter['drName'])) {
                $level = $levelingDokter['levelDokter'] ?? '';
                $dpjpList[] = [
                    'drName' => $levelingDokter['drName'],
                    'level' => $level === 'RawatGabung' ? 'Rawat Gabung' : $level,
                ];
            }
        }
        $row->dpjp_list = $dpjpList;
        $row->dr_penerima = $row->dr_name ?? '-';

        // Dari Pengkajian Dokter: alergi (Ya/Tidak + keterangan — teks display
        // via AlergiSnomed::untukCetakRi, sama dgn viewer Rekam Medis) &
        // instruksi diet (rencana)
        $row->alergi_dokter = AlergiSnomed::untukCetakRi((array) data_get($json, 'pengkajianDokter.anamnesa', []));
        $row->alergi_ada = $row->alergi_dokter !== '' && $row->alergi_dokter !== AlergiSnomed::TIDAK_ADA['alergi'];
        $row->diet_dokter = trim((string) data_get($json, 'pengkajianDokter.rencana.diet', ''));

        unset($row->datadaftarri_json);
    }

    /** baseQuery + pencarian + urutan — dipakai list (paginate) & unduh CSV (semua baris). */
    private function queryTersaring()
    {
        $query = $this->baseQuery();

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $keyword = mb_strtoupper($search);
            $query->where(function ($subQuery) use ($search, $keyword) {
                if (ctype_digit($search)) {
                    $subQuery->orWhere('rv.reg_no', 'like', "%{$search}%")->orWhere('rv.rihdr_no', 'like', "%{$search}%");
                }
                $subQuery->orWhereRaw('UPPER(rv.reg_name) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(rv.room_name) LIKE ?', ["%{$keyword}%"]);
            });
        }

        return $query->orderByRaw('rv.exit_date DESC NULLS FIRST')->orderByRaw('rv.entry_date DESC');
    }

    #[Computed]
    public function rows()
    {
        $paginator = $this->queryTersaring()->paginate($this->itemsPerPage);
        $paginator->getCollection()->transform(function ($row) {
            $this->ringkasGiziRow($row);
            // Badge status sama dgn daftar-ri: Dirawat=brand, Pulang=success
            $status = $row->ri_status ?: 'I';
            $row->status_text = $status === 'P' ? 'Pulang' : 'Dirawat';
            $row->status_variant = $status === 'P' ? 'success' : 'brand';
            return $row;
        });

        return $paginator;
    }

    /**
     * Rekap produksi dapur: jumlah pasien DIRAWAT per program diet terakhir
     * (mengikuti filter bangsal, mengabaikan pencarian & pagination).
     * Full scan CLOB pasien aktif — populasi kecil (pasien dirawat saja),
     * pola sama dgn filterDokter di daftar-ri.
     */
    #[Computed]
    public function rekapDiet(): array
    {
        $rekap = [];
        $belumAdaDiet = 0;
        $total = 0;

        $rowList = DB::table('rsview_rihdrs as rv')
            ->selectRaw('rv.rihdr_no, rv.datadaftarri_json')
            ->where(DB::raw("NVL(rv.ri_status,'I')"), 'I')
            ->when($this->filterBangsal !== '', fn($q) => $q->where('rv.bangsal_id', $this->filterBangsal))
            ->get();

        foreach ($rowList as $row) {
            $total++;
            $jsonRaw = OracleLob::read($row->datadaftarri_json ?? null, 'rstxn_rihdrs', 'rihdr_no', $row->rihdr_no, 'datadaftarri_json');
            $json = json_decode($jsonRaw ?: '{}', true) ?? [];
            $dietTerakhir = GiziOptions::entriTerakhirDengan($json['penilaian']['gizi'] ?? [], 'programDiet');
            if ($dietTerakhir === []) {
                $belumAdaDiet++;
                continue;
            }
            $namaDiet = (string) $dietTerakhir['nilai'];
            $rekap[$namaDiet] = ($rekap[$namaDiet] ?? 0) + 1;
        }

        // Urut sesuai daftar baku; diet di luar daftar (data lama) menyusul di belakang.
        $urut = [];
        foreach (GiziOptions::PROGRAM_DIET as $namaDiet) {
            if (isset($rekap[$namaDiet])) {
                $urut[$namaDiet] = $rekap[$namaDiet];
                unset($rekap[$namaDiet]);
            }
        }
        ksort($rekap);

        return ['diet' => $urut + $rekap, 'belum' => $belumAdaDiet, 'total' => $total];
    }

    /**
     * Unduh daftar diet pasien sebagai CSV siap-Excel (pola gaji-dokter-lampiran:
     * BOM UTF-8 + pemisah ';', CSV bukan .xlsx karena proyek tidak memuat
     * PhpSpreadsheet). Baris data pipih (aman dipivot); di bawahnya ada blok
     * rekap porsi per program diet, dipisah satu baris kosong.
     *
     * KHUSUS PASIEN DIRAWAT (ri_status='I') — daftar produksi dapur; filter
     * bangsal & pencarian di layar tetap dihormati, filter status TIDAK.
     */
    public function unduhCsv(): mixed
    {
        $rowList = $this->queryTersaring()
            ->where(DB::raw("NVL(rv.ri_status,'I')"), 'I')
            ->get();

        if ($rowList->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada pasien dirawat untuk diunduh.');
            return null;
        }

        foreach ($rowList as $row) {
            $this->ringkasGiziRow($row);
        }

        $tglExport = Carbon::now(config('app.timezone'))->format('d/m/Y H:i');
        $namaBerkas = 'program-diet-pasien-' . Carbon::now(config('app.timezone'))->format('Ymd-Hi') . '.csv';

        // Rekap per jenis diet utk blok bawah CSV — dari baris yang sama dgn
        // isi file (pasien dirawat, filter bangsal & pencarian dihormati).
        // Urutan mengikuti daftar baku; diet lama di luar daftar menyusul.
        $rekapDiet = [];
        $belumAdaDiet = 0;
        foreach ($rowList as $row) {
            $namaDiet = trim((string) ($row->diet_terakhir['nilai'] ?? ''));
            if ($namaDiet === '') {
                $belumAdaDiet++;
                continue;
            }
            $rekapDiet[$namaDiet] = ($rekapDiet[$namaDiet] ?? 0) + 1;
        }
        $rekapUrut = [];
        foreach (GiziOptions::PROGRAM_DIET as $namaDiet) {
            if (isset($rekapDiet[$namaDiet])) {
                $rekapUrut[$namaDiet] = $rekapDiet[$namaDiet];
                unset($rekapDiet[$namaDiet]);
            }
        }
        ksort($rekapDiet);
        $rekapUrut += $rekapDiet;

        return response()->streamDownload(function () use ($rowList, $tglExport, $rekapUrut, $belumAdaDiet) {
            $keluaran = fopen('php://output', 'w');

            // BOM UTF-8 — tanpa ini Excel membaca berkas sebagai ANSI.
            fwrite($keluaran, "\xEF\xBB\xBF");

            // Tgl Export ikut di SETIAP baris (bukan baris judul terpisah) —
            // gabungan beberapa file export tetap bisa dipivot per tanggal.
            // Diet Pengkajian Dokter SEBELUM Program Diet — urutan alur kerja:
            // instruksi dokter dulu, baru gizi menetapkan program bakunya.
            fputcsv($keluaran, ['Tgl Export', 'No RM', 'Nama Pasien', 'JK', 'Tgl Lahir', 'Bangsal', 'Kamar', 'DPJP', 'Tgl Masuk', 'Diet Pengkajian Dokter', 'Program Diet', 'Keterangan Diet', 'Tgl Entri Diet', 'Petugas Gizi', 'Kategori Skrining', 'Alergi'], ';');

            foreach ($rowList as $row) {
                fputcsv($keluaran, [
                    $tglExport,
                    $row->reg_no,
                    $row->reg_name,
                    $row->sex ?? '-',
                    $row->birth_date,
                    $row->bangsal_name,
                    $row->room_name,
                    implode(', ', array_map(fn($dpjp) => trim($dpjp['drName'] . ($dpjp['level'] !== '' ? ' (' . $dpjp['level'] . ')' : '')), $row->dpjp_list)),
                    $row->entry_date_display,
                    $row->diet_dokter,
                    $row->diet_terakhir['nilai'] ?? '',
                    data_get($row->diet_terakhir, 'entri.gizi.programDietKet', ''),
                    $row->diet_terakhir['tglPenilaian'] ?? '',
                    $row->diet_terakhir['petugasPenilai'] ?? '',
                    $row->kategori_gizi['nilai'] ?? '',
                    $row->alergi_dokter,
                ], ';');
            }

            // ── REKAP PORSI PER PROGRAM DIET (blok bawah) ──
            // Baris data di atas tetap pipih (aman dipivot); blok rekap
            // dipisah baris kosong agar mudah dikenali/dibuang di Excel.
            fputcsv($keluaran, [], ';');
            fputcsv($keluaran, ['REKAP PORSI PER PROGRAM DIET'], ';');
            fputcsv($keluaran, ['Program Diet', 'Jumlah Pasien'], ';');
            $totalPasien = 0;
            foreach ($rekapUrut as $namaDiet => $jumlah) {
                fputcsv($keluaran, [$namaDiet, $jumlah], ';');
                $totalPasien += $jumlah;
            }
            if ($belumAdaDiet > 0) {
                fputcsv($keluaran, ['(Belum ada program diet)', $belumAdaDiet], ';');
                $totalPasien += $belumAdaDiet;
            }
            fputcsv($keluaran, ['TOTAL', $totalPasien], ';');

            fclose($keluaran);
        }, $namaBerkas, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    #[Computed]
    public function bangsalList()
    {
        return DB::table('rsview_rihdrs')
            ->select('bangsal_id', DB::raw('MAX(bangsal_name) as bangsal_name'))
            ->where(DB::raw("NVL(ri_status,'I')"), 'I')
            ->whereNotNull('bangsal_id')
            ->groupBy('bangsal_id')
            ->orderBy('bangsal_name')
            ->get();
    }
};
?>

<div>
    <x-page-title
        title="Gizi Rawat Inap"
        subtitle="Program diet harian pasien rawat inap & rekap porsi dapur — unit gizi" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
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
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari No RM / Nama Pasien / Kamar..." />
                        </div>
                    </div>

                    {{-- FILTER BANGSAL --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bangsal" />
                        <x-select-input wire:model.live="filterBangsal" class="w-full mt-1 sm:w-44">
                            <option value="">Semua Bangsal</option>
                            @foreach ($this->bangsalList as $bangsal)
                                <option value="{{ $bangsal->bangsal_id }}">{{ $bangsal->bangsal_name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- FILTER STATUS --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-36">
                            <option value="I">Dirawat</option>
                            <option value="P">Pulang</option>
                            <option value="">Semua</option>
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center gap-2 ml-auto">
                        {{-- Unduh CSV siap-Excel — khusus pasien dirawat --}}
                        <x-outline-button type="button" wire:click="unduhCsv" wire:loading.attr="disabled"
                            class="whitespace-nowrap" title="Unduh Program Diet Pasien — pasien dirawat (CSV siap dibuka Excel)">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Program Diet Pasien
                        </x-outline-button>

                        <x-toolbar-refresh-reset :label="null" />
                        <div class="w-24">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </x-select-input>
                        </div>
                    </div>

                </div>
            </div>

            {{-- REKAP PRODUKSI DAPUR — program diet terakhir pasien dirawat --}}
            @php $rekap = $this->rekapDiet; @endphp
            <div
                class="mt-4 px-4 py-3 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold text-muted dark:text-gray-300 mr-1">
                        Rekap Porsi ({{ $rekap['total'] }} pasien dirawat{{ $filterBangsal !== '' ? ' · bangsal terpilih' : '' }}):
                    </span>
                    @forelse ($rekap['diet'] as $namaDiet => $jumlah)
                        <x-badge variant="brand">{{ $namaDiet }}: {{ $jumlah }}</x-badge>
                    @empty
                        <span class="text-sm text-muted-soft">Belum ada program diet terisi.</span>
                    @endforelse
                    @if ($rekap['belum'] > 0)
                        <x-badge variant="warning">Belum ada diet: {{ $rekap['belum'] }}</x-badge>
                    @endif
                </div>
            </div>

            {{-- TABLE --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-base -mt-3 border-separate border-spacing-y-3">
                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-6 py-3 min-w-[280px]">Pasien</th>
                                <th class="px-6 py-3 min-w-[220px]">Kamar / DPJP</th>
                                <th class="px-6 py-3 min-w-[200px]">Status Layanan</th>
                                <th class="px-6 py-3 min-w-[260px]">Program Diet</th>
                                <th class="px-6 py-3 text-center w-40">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr class="transition bg-canvas dark:bg-gray-900
                                       rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                       hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800"
                                    wire:key="gizi-ri-row-{{ $row->rihdr_no }}">

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-5 space-y-2 align-top">
                                        <div class="flex items-start gap-4">
                                            <x-list.identitas-pasien class="min-w-0"
                                                :regNo="$row->reg_no"
                                                :nama="$row->reg_name"
                                                :sex="$row->sex"
                                                :tglLahir="$row->birth_date"
                                                :alamat="$row->address" />
                                        </div>
                                    </td>

                                    {{-- KAMAR / DPJP — sama seperti sub-kolom kiri daftar-ri --}}
                                    <td class="px-6 py-5 align-top">
                                        <div class="space-y-2">
                                            <div class="font-semibold text-blue-600 dark:text-blue-400">
                                                {{ $row->bangsal_name ?? '-' }}
                                            </div>
                                            <div class="text-base text-ink dark:text-gray-200">
                                                {{ $row->room_name ?? '-' }}
                                            </div>
                                            @if (!empty($row->dpjp_list))
                                                <div class="space-y-0.5">
                                                    <div class="text-xs text-muted-soft">DPJP:</div>
                                                    @foreach ($row->dpjp_list as $dpjp)
                                                        <div class="text-base text-body dark:text-gray-200">
                                                            {{ $dpjp['drName'] }}
                                                            @if (!empty($dpjp['level']))
                                                                <span class="text-xs text-muted">({{ $dpjp['level'] }})</span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <div class="text-xs italic text-muted dark:text-gray-400">
                                                Penerima: {{ $row->dr_penerima }}
                                            </div>
                                        </div>
                                    </td>

                                    {{-- STATUS LAYANAN — pola daftar-ri: tanggal lalu badge status --}}
                                    <td class="px-6 py-5 space-y-2 align-top">
                                        <div class="text-sm text-body dark:text-gray-400 space-y-0.5 whitespace-nowrap">
                                            <div>
                                                <span class="text-muted">Masuk:</span>
                                                {{ $row->entry_date_display ?? '-' }}
                                            </div>
                                            @if (!empty($row->exit_date_display))
                                                <div>
                                                    <span class="text-muted">Pulang:</span>
                                                    {{ $row->exit_date_display }}
                                                </div>
                                            @endif
                                        </div>

                                        <x-badge :variant="$row->status_variant">{{ $row->status_text }}</x-badge>

                                        {{-- Pengkajian Dokter: alergi (merah hanya bila ADA) + instruksi diet --}}
                                        @if ($row->alergi_dokter !== '' || $row->diet_dokter !== '')
                                            <div class="space-y-0.5">
                                                <div class="text-xs text-muted-soft">Pengkajian Dokter:</div>
                                                @if ($row->alergi_dokter !== '')
                                                    <div class="text-sm {{ $row->alergi_ada ? 'text-red-600 dark:text-red-400' : 'text-body dark:text-gray-300' }}">
                                                        <span class="font-semibold">Alergi:</span>
                                                        {{ $row->alergi_dokter }}
                                                    </div>
                                                @endif
                                                @if ($row->diet_dokter !== '')
                                                    <div class="text-sm text-body dark:text-gray-300 whitespace-pre-line">
                                                        <span class="font-semibold">Diet:</span>
                                                        {{ $row->diet_dokter }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </td>

                                    {{-- PROGRAM DIET --}}
                                    <td class="px-6 py-5 align-top">
                                        @if (!empty($row->diet_terakhir))
                                            <div class="font-semibold text-ink dark:text-gray-100">
                                                {{ $row->diet_terakhir['nilai'] }}
                                            </div>
                                            @if (filled(data_get($row->diet_terakhir, 'entri.gizi.programDietKet')))
                                                <div class="text-sm text-muted dark:text-gray-400">
                                                    {{ data_get($row->diet_terakhir, 'entri.gizi.programDietKet') }}
                                                </div>
                                            @endif
                                            <div class="text-xs text-muted-soft">
                                                {{ $row->diet_terakhir['tglPenilaian'] }}
                                                @if ($row->diet_terakhir['petugasPenilai'] !== '')
                                                    · {{ $row->diet_terakhir['petugasPenilai'] }}
                                                @endif
                                            </div>
                                        @else
                                            <x-badge variant="warning">Belum ada program diet</x-badge>
                                        @endif

                                        @if (($row->kategori_gizi['nilai'] ?? '') === 'Berisiko Malnutrisi')
                                            <div class="mt-1.5">
                                                <x-badge variant="warning" class="gap-1"
                                                    title="Skrining gizi terakhir {{ $row->kategori_gizi['tglPenilaian'] }}">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd"
                                                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                    Berisiko Malnutrisi
                                                </x-badge>
                                            </div>
                                        @endif

                                        @if ($row->jumlah_entri > 0)
                                            <div class="mt-0.5 text-xs text-muted-soft">
                                                {{ $row->jumlah_entri }} entri gizi
                                            </div>
                                        @endif
                                    </td>

                                    {{-- AKSI — tombol utama + dropdown titik-3 (pola daftar-ri) --}}
                                    <td class="px-6 py-5 align-top text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <x-dropdown position="left" width="w-[440px]">
                                                <x-slot name="trigger">
                                                    <x-secondary-button type="button" class="p-2">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path
                                                                d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z" />
                                                        </svg>
                                                    </x-secondary-button>
                                                </x-slot>

                                                <x-slot name="content">
                                                    <div class="p-2 space-y-2">
                                                        <div class="grid grid-cols-2 gap-1">

                                                            {{-- Penilaian Gizi — entri program diet harian --}}
                                                            <x-dropdown-link href="#"
                                                                wire:click.prevent="openEntri('{{ $row->rihdr_no }}')"
                                                                class="px-3 py-2 text-sm rounded-lg bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20">
                                                                <div class="flex items-start gap-2">
                                                                    <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                        fill="none" stroke="currentColor"
                                                                        viewBox="0 0 24 24" stroke-width="2">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round"
                                                                            d="M12 4v16m8-8H4" />
                                                                    </svg>
                                                                    <span>Penilaian Gizi<br>
                                                                        <span
                                                                            class="font-semibold">{{ $row->reg_name }}</span>
                                                                    </span>
                                                                </div>
                                                            </x-dropdown-link>

                                                            {{-- Etiket diet — hanya bila sudah ada program diet --}}
                                                            @if (!empty($row->diet_terakhir))
                                                                <x-dropdown-link href="#"
                                                                    wire:click.prevent="cetakEtiketGizi('{{ $row->rihdr_no }}')"
                                                                    class="px-3 py-2 text-sm rounded-lg bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-900/20">
                                                                    <div class="flex items-start gap-2">
                                                                        <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                            fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24" stroke-width="2">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                        </svg>
                                                                        <span>Etiket Diet<br>
                                                                            <span class="font-semibold">
                                                                                {{ $row->diet_terakhir['nilai'] }}
                                                                            </span>
                                                                        </span>
                                                                    </div>
                                                                </x-dropdown-link>
                                                            @endif

                                                            {{-- Modul Dokumen — gate role sama dgn daftar-ri --}}
                                                            @hasanyrole('Admin|Perawat|Dokter|Casemix|Mr|Gizi')
                                                                <x-dropdown-link href="#"
                                                                    wire:click.prevent="openModulDokumen('{{ $row->rihdr_no }}')"
                                                                    class="px-3 py-2 text-sm rounded-lg bg-yellow-50 hover:bg-yellow-100 dark:bg-yellow-900/20">
                                                                    <div class="flex items-start gap-2">
                                                                        <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                            fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24" stroke-width="2">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                        </svg>
                                                                        <span>Modul Dokumen<br>
                                                                            <span class="font-semibold">Formulir &amp;
                                                                                Consent Pasien</span>
                                                                        </span>
                                                                    </div>
                                                                </x-dropdown-link>
                                                            @endhasanyrole

                                                            {{-- Administrasi — gate role sama dgn daftar-ri --}}
                                                            @hasanyrole('Admin|Perawat|Casemix|Tu|Gizi')
                                                                <x-dropdown-link href="#"
                                                                    wire:click.prevent="openAdministrasiPasien('{{ $row->rihdr_no }}')"
                                                                    class="px-3 py-2 text-sm rounded-lg bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/20">
                                                                    <div class="flex items-start gap-2">
                                                                        <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                            fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24" stroke-width="2">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                d="M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z" />
                                                                        </svg>
                                                                        <span>Administrasi<br>
                                                                            <span
                                                                                class="font-semibold">{{ $row->reg_name }}</span>
                                                                        </span>
                                                                    </div>
                                                                </x-dropdown-link>
                                                            @endhasanyrole

                                                        </div>
                                                    </div>
                                                </x-slot>
                                            </x-dropdown>
                                        </div>
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-16">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                            </svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">
                                                Tidak ada pasien rawat inap.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl
                            dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>

            </div>

            {{-- Modal entri diet harian --}}
            <livewire:pages::transaksi.ri.gizi-ri.gizi-ri-entri wire:key="gizi-ri-entri" />

            {{-- Cetak etiket diet (langsung download PDF 6x4 cm) --}}
            <livewire:pages::components.rekam-medis.ri.cetak-etiket-gizi.cetak-etiket-gizi wire:key="cetak-etiket-gizi" />

            {{-- Modul Dokumen & Administrasi — modal sama dgn daftar-ri (sibling, dengar event emr-ri.*) --}}
            <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.modul-dokumen-ri wire:key="modul-dokumen-ri" />
            <livewire:pages::transaksi.ri.administrasi-ri.administrasi-ri wire:key="administrasi-ri-actions" />
            {{-- Log aktivitas — dibuka dari tombol log di dalam modal Administrasi --}}
            <livewire:pages::transaksi.ri.emr-ri.log-aktivitas.log-aktivitas-ri wire:key="log-aktivitas-ri" />

        </div>
    </div>
</div>
