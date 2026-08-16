<?php
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  APOTEK ONLINE RJ — worklist klaim obat PRB / kronis / kemoterapi     ║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// Route: /apotek-online/rj
//
// Daftar kerja petugas apotek untuk klaim Apotek Online BPJS. Menampilkan resep
// pasien BPJS yang SEP-nya SUDAH ter-create (rstxn_rjhdrs.vno_sep) — dari situ
// resep apotek dibuat. Petugas tinggal "Daftarkan" (POST sjpresep) lalu kirim
// obat (racikan/non-racikan) memakai kode DPHO yang sudah dipetakan di Master Obat.
//
// Mode HARIAN (satu tanggal) & BULANAN (satu bulan), mirip rekap Casemix — karena
// klaim apotek disetorkan per periode.
//
// LANGKAH 1 (berkas ini): worklist + deteksi kelayakan + status. Sumber MURNI DB
// lokal, jadi bisa dipakai & diuji sekarang meski CID BPJS belum aktif.
// LANGKAH 2 (menyusul): modal "Daftarkan & Kirim" yang merakit payload dari e-resep
// + kode_dpho lalu memanggil ApotekTrait.

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\EresepJson;

new class extends Component {
    use WithPagination;

    #[Session(key: 'apotek-online-rj-mode')]
    public string $mode = 'harian'; // harian | bulanan

    #[Session(key: 'apotek-online-rj-tanggal')]
    public string $filterTanggal = ''; // dd/mm/yyyy (mode harian)

    #[Session(key: 'apotek-online-rj-bulan')]
    public string $filterBulan = ''; // format m/Y (mm/yyyy) — mode bulanan, samakan Casemix

    public string $searchKeyword = '';
    public int $itemsPerPage = 15;

    public function mount(): void
    {
        if ($this->filterTanggal === '') {
            $this->filterTanggal = now()->format('d/m/Y');
        }
        if ($this->filterBulan === '') {
            $this->filterBulan = now()->format('m/Y');
        }
    }

    public function setMode(string $mode): void
    {
        if (in_array($mode, ['harian', 'bulanan'], true)) {
            $this->mode = $mode;
            $this->resetPage();
        }
    }

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedFilterTanggal(): void { $this->resetPage(); }
    public function updatedFilterBulan(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void { $this->resetPage(); }

    #[On('apotek-online-rj.refresh')]
    public function refresh(): void { unset($this->rows); }

    /** Rentang tanggal aktif menurut mode. */
    private function dateRange(): array
    {
        if ($this->mode === 'bulanan') {
            try {
                $d = Carbon::createFromFormat('m/Y', trim($this->filterBulan))->startOfMonth();
            } catch (\Throwable) {
                $d = now()->startOfMonth();
            }
            return [$d, (clone $d)->endOfMonth()];
        }

        try {
            $d = Carbon::createFromFormat('d/m/Y', trim($this->filterTanggal))->startOfDay();
        } catch (\Throwable) {
            $d = now()->startOfDay();
        }
        return [$d, (clone $d)->endOfDay()];
    }

    /**
     * Kelayakan Apotek Online: pasien BPJS yang SEP-nya SUDAH ada. Tanpa SEP,
     * REFASALSJP kosong dan resep apotek tak bisa dibuat — jadi itu syarat mutlak,
     * dipakai sebagai filter query, bukan sekadar penanda.
     */
    /**
     * Query dasar (tanpa parsing JSON) — dipakai bersama oleh penghitung total dan
     * pengambil halaman. Parsing e-resep + hitung DPHO MAHAL (satu query per baris),
     * jadi HANYA dikerjakan untuk baris yang benar-benar tampil di halaman ini —
     * bukan seluruh bulan.
     */
    private function baseQuery()
    {
        [$mulai, $selesai] = $this->dateRange();

        $query = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
            ->leftJoin('rsmst_doctors as d', 'h.dr_id', '=', 'd.dr_id')
            ->leftJoin('rsmst_polis as po', 'h.poli_id', '=', 'po.poli_id')
            ->leftJoin('rsmst_klaimtypes as k', 'h.klaim_id', '=', 'k.klaim_id')
            ->whereBetween('h.rj_date', [$mulai, $selesai])
            ->whereRaw("h.vno_sep IS NOT NULL AND LENGTH(TRIM(h.vno_sep)) > 0")
            // BPJS: klaim_status='BPJS' atau klaim JKN Mobile
            ->where(function ($q) {
                $q->where('k.klaim_status', 'BPJS')->orWhere('h.klaim_id', 'JM');
            })
            ->select([
                'h.rj_no',
                DB::raw("to_char(h.rj_date,'dd/mm/yyyy') as rj_date_display"),
                'h.reg_no', 'p.reg_name', 'p.sex',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                'h.dr_id', 'd.dr_name', 'po.poli_desc',
                'h.vno_sep', 'h.status_kronis', 'h.status_iter',
                'h.datadaftarpolirj_json',
            ])
            ->orderByDesc('h.rj_date');

        if (trim($this->searchKeyword) !== '') {
            $kw = '%' . mb_strtoupper(trim($this->searchKeyword)) . '%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('UPPER(p.reg_name) LIKE ?', [$kw])
                  ->orWhereRaw('UPPER(h.reg_no) LIKE ?', [$kw])
                  ->orWhereRaw('UPPER(h.vno_sep) LIKE ?', [$kw])
                  ->orWhereRaw('TO_CHAR(h.rj_no) LIKE ?', [$kw]);
            });
        }

        return $query;
    }

    #[Computed]
    public function rows()
    {
        // Paginate DB-level — JSON decode & hitung DPHO hanya untuk page aktif
        // (~15 baris), bukan seluruh record bulan itu.
        $paginator = $this->baseQuery()->paginate($this->itemsPerPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(function ($r) {
            $data = $this->decodeJson($r->datadaftarpolirj_json);
            $ringkas = $this->ringkasResep($data);
            $apotek = $data['apotekOnline'] ?? [];

            return (object) [
                'rjNo' => $r->rj_no,
                'rjDate' => $r->rj_date_display,
                'regNo' => $r->reg_no,
                'regName' => $r->reg_name,
                'sex' => $r->sex,
                'birthDate' => $r->birth_date,
                'drName' => $r->dr_name,
                'poliDesc' => $r->poli_desc,
                'noSep' => $r->vno_sep,
                'statusKronis' => $r->status_kronis,
                'statusIter' => $r->status_iter,
                'jmlNonRacikan' => $ringkas['nonRacikan'],
                'jmlRacikan' => $ringkas['racikan'],
                'jmlBerDpho' => $ringkas['berDpho'],
                'jmlTanpaDpho' => $ringkas['tanpaDpho'],
                'sudahDaftar' => !empty($apotek['noSjp']),
                'noSjpApotek' => $apotek['noSjp'] ?? '',
            ];
        })
        );

        return $paginator;
    }

    /** Ringkas resep: hitung obat & berapa yang SUDAH ber-kode DPHO (siap kirim). */
    private function ringkasResep(array $data): array
    {
        $productIds = [];
        $nonRacikan = 0;
        $racikan = 0;

        foreach (EresepJson::lembar($data) as $lembar) {
            foreach ($lembar['nonRacikan'] as $obat) {
                $nonRacikan++;
                $pid = trim((string) ($obat['productId'] ?? ''));
                if ($pid !== '') { $productIds[$pid] = true; }
            }
            foreach ($lembar['racikan'] as $grup) {
                $racikan++;
                foreach ($grup as $bahan) {
                    $pid = trim((string) ($bahan['productId'] ?? ''));
                    if ($pid !== '') { $productIds[$pid] = true; }
                }
            }
        }

        $berDpho = 0;
        if ($productIds !== []) {
            $berDpho = DB::table('immst_products')
                ->whereIn('product_id', array_keys($productIds))
                ->whereRaw("kode_dpho IS NOT NULL AND LENGTH(TRIM(kode_dpho)) > 0")
                ->count();
        }

        return [
            'nonRacikan' => $nonRacikan,
            'racikan' => $racikan,
            'berDpho' => $berDpho,
            'tanpaDpho' => count($productIds) - $berDpho,
        ];
    }

    private function decodeJson($raw): array
    {
        if (is_object($raw) && method_exists($raw, 'load')) {
            $raw->load();
            $raw = (string) $raw;
        }
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : [];
    }

    // Rekap "sudah/belum daftar" sengaja TIDAK dihitung global: penandanya ada di
    // dalam JSON per baris, dan memindai 1.600+ CLOB tiap render terlalu mahal.
    // Status ditampilkan per baris pada halaman yang tampil; total cukup dari count().
};
?>

<div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
    <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

        <x-page-title title="Apotek Online RJ"
            subtitle="Klaim obat PRB / kronis / kemoterapi ke BPJS — resep pasien rawat jalan yang SEP-nya sudah dibuat" />

        {{-- TOOLBAR --}}
        <div class="sticky z-30 px-4 py-3 mt-2 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
            <div class="flex flex-wrap items-end gap-3">

                {{-- Mode Harian / Bulanan --}}
                <div class="inline-flex overflow-hidden border rounded-lg border-hairline dark:border-gray-600">
                    <button type="button" wire:click="setMode('bulanan')"
                        class="px-3 py-1.5 text-sm font-medium transition-colors {{ $mode === 'bulanan' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                        Bulanan
                    </button>
                    <button type="button" wire:click="setMode('harian')"
                        class="px-3 py-1.5 text-sm font-medium transition-colors border-l border-gray-300 dark:border-gray-600 {{ $mode === 'harian' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                        Harian
                    </button>
                </div>

                @php
                    $ikonKalender = 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z';
                @endphp
                @if ($mode === 'harian')
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tanggal" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $ikonKalender }}" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live.debounce.500ms="filterTanggal"
                                class="block w-full pl-10 sm:w-44" placeholder="dd/mm/yyyy" maxlength="10" />
                        </div>
                    </div>
                @else
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $ikonKalender }}" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                                class="block w-full pl-10 sm:w-40" placeholder="mm/yyyy" maxlength="7" />
                        </div>
                    </div>
                @endif

                <div class="w-full sm:w-auto sm:flex-1">
                    <x-input-label value="Pencarian" />
                    <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="w-full mt-1"
                        placeholder="Nama pasien / No. RM / No. SEP..." />
                </div>

            </div>
        </div>

        {{-- REKAP --}}
        @php $total = $this->rows->total(); @endphp
        <div class="mt-4 px-4 py-3 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center gap-2">
                <span class="mr-1 text-sm font-semibold text-muted dark:text-gray-300">Resep BPJS ber-SEP pada periode:</span>
                <x-badge variant="info">{{ $total }} resep</x-badge>
                <span class="text-xs text-muted-soft">Status daftar ditampilkan per baris.</span>
            </div>
        </div>

        {{-- TABEL --}}
        <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
            <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                <table class="min-w-full text-base -mt-3 border-separate border-spacing-y-3">
                    <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                        <tr class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                            <th class="px-6 py-3 min-w-[240px]">Pasien</th>
                            <th class="px-6 py-3 min-w-[200px]">SEP / Poli</th>
                            <th class="px-6 py-3 min-w-[180px]">Obat</th>
                            <th class="px-6 py-3 min-w-[150px]">Status</th>
                            <th class="w-32 px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $row)
                            <tr class="transition bg-canvas dark:bg-gray-900 rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700 hover:shadow-lg"
                                wire:key="apotek-online-{{ $row->rjNo }}">
                                <td class="px-6 py-3 rounded-l-2xl">
                                    <x-list.identitas-pasien :nama="$row->regName" :reg-no="$row->regNo"
                                        :sex="$row->sex" :tgl-lahir="$row->birthDate" />
                                    <div class="mt-1 text-xs text-muted-soft">{{ $row->rjDate }} · dr. {{ $row->drName ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-3">
                                    <x-list.sep-spri :sep="$row->noSep" />
                                    <div class="mt-1 text-xs text-muted dark:text-gray-400">{{ $row->poliDesc ?? '-' }}</div>
                                    @if ($row->statusKronis === 'Y')
                                        <span class="inline-block mt-1 text-xs"><x-badge variant="warning">KRONIS</x-badge></span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm">
                                    <div class="text-body dark:text-gray-200">
                                        {{ $row->jmlNonRacikan }} non-racikan · {{ $row->jmlRacikan }} racikan
                                    </div>
                                    <div class="mt-0.5 text-xs">
                                        <span class="text-success">{{ $row->jmlBerDpho }} siap (ber-DPHO)</span>
                                        @if ($row->jmlTanpaDpho > 0)
                                            · <span class="text-warning-deep dark:text-amber-300">{{ $row->jmlTanpaDpho }} belum dipetakan</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-3">
                                    @if ($row->sudahDaftar)
                                        <x-badge variant="success">Terdaftar</x-badge>
                                        <div class="mt-1 font-mono text-xs text-muted-soft">{{ $row->noSjpApotek }}</div>
                                    @else
                                        <x-badge variant="gray">Belum</x-badge>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-center rounded-r-2xl">
                                    {{-- Langkah 2: modal Daftarkan & Kirim. Untuk sekarang tombol
                                         menandai tempatnya; dinonaktifkan agar tak memberi harapan
                                         palsu selama alurnya belum tersambung. --}}
                                    <x-outline-button type="button" disabled title="Menyusul — menunggu modal Daftarkan & koneksi BPJS aktif">
                                        Daftarkan
                                    </x-outline-button>
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
                                        <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada data</p>
                                        <p class="text-xs text-muted-soft">Tidak ada resep BPJS ber-SEP pada periode ini.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- PAGINASI — links() bawaan Laravel: nomor halaman 1 2 3 … --}}
            <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                {{ $this->rows->links() }}
            </div>
        </div>

    </div>
</div>
