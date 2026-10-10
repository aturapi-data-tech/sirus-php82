<?php
// resources/views/pages/transaksi/ri/kontrol-klaim-ri/kontrol-klaim-ri.blade.php
// Kontrol Klaim RI — disalin dari Daftar Rawat Inap, khusus pasien yang SEDANG DIRAWAT (ri_status 'I').

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Support\OracleLob;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Support\IndikatorTarifKlaim;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait, EmrRITrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['kontrol-klaim-ri-toolbar'];

    public string $searchKeyword = '';
    /** Tab jenis klaim: 'BPJS' (klaim_status BPJS) atau 'UMUM' (selain BPJS: Umum, Kronis, Dokel). */
    public string $tabKlaim = 'BPJS';
    public string $filterDokter = '';
    public string $filterBangsal = '';
    public int $itemsPerPage = 20;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    public function updatedSearchKeyword(): void
    {
        // Tidak incrementVersion — wire:key remount toolbar di tengah ketik bikin
        // search input kehilangan focus, backspace berikutnya memicu browser back.
        $this->resetPage();
    }
    public function pilihTabKlaim(string $tab): void
    {
        $this->tabKlaim = $tab === 'UMUM' ? 'UMUM' : 'BPJS';
        $this->resetPage();
        $this->incrementVersion('kontrol-klaim-ri-toolbar');
    }
    public function updatedFilterDokter(): void
    {
        $this->resetPage();
        $this->incrementVersion('kontrol-klaim-ri-toolbar');
    }
    public function updatedFilterBangsal(): void
    {
        $this->resetPage();
        $this->incrementVersion('kontrol-klaim-ri-toolbar');
    }
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('kontrol-klaim-ri-toolbar');
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterDokter', 'filterBangsal']);
        $this->incrementVersion('kontrol-klaim-ri-toolbar');
        $this->resetPage();
    }

    public function openIdrg(string $riHdrNo): void
    {
        $this->dispatch('daftar-ri.idrg.open', riHdrNo: $riHdrNo);
    }

    #[On('refresh-after-ri.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('kontrol-klaim-ri-toolbar');
        $this->resetPage();
    }

    #[Computed]
    public function baseQuery()
    {
        $query = DB::table('rsview_rihdrs as rv')
            ->leftJoin('rsmst_klaimtypes as kt', 'kt.klaim_id', '=', 'rv.klaim_id')
            ->select([
                'rv.rihdr_no',
                DB::raw("to_char(rv.entry_date,'dd/mm/yyyy hh24:mi') as entry_date_display"),
                DB::raw("to_char(rv.entry_date,'yyyymmddhh24miss') as entry_date_sort"),
                // Hari rawat ke-N (hari masuk = hari ke-1)
                DB::raw('TRUNC(SYSDATE) - TRUNC(rv.entry_date) + 1 as hari_rawat'),
                'rv.reg_no',
                'rv.reg_name',
                'rv.sex',
                DB::raw("to_char(rv.birth_date,'dd/mm/yyyy') as birth_date"),
                'rv.dr_name',
                'rv.klaim_id',
                'kt.klaim_desc',
                'kt.klaim_status',
                'rv.vno_sep',
                'rv.bangsal_name',
                'rv.room_name',
                'rv.datadaftarri_json',
            ])
            ->orderBy('entry_date_sort', 'desc');

        // Hanya pasien yang sedang dirawat, dipisah per tab jenis klaim.
        $query->where(DB::raw("NVL(rv.ri_status,'I')"), 'I');
        $this->saringJenisKlaim($query, $this->tabKlaim);

        if ($this->filterBangsal !== '') {
            $query->where('rv.bangsal_id', $this->filterBangsal);
        }

        if ($this->filterDokter !== '') {
            $ids = DB::table('rsview_rihdrs')
                ->select('rihdr_no', 'datadaftarri_json')
                ->where(DB::raw("NVL(ri_status,'I')"), 'I')
                ->get()
                ->filter(function ($item) {
                    $jsonRaw = OracleLob::read($item->datadaftarri_json ?? null, 'rstxn_rihdrs', 'rihdr_no', $item->rihdr_no, 'datadaftarri_json');
                    $json = json_decode($jsonRaw ?: '{}', true) ?? [];
                    foreach ($json['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [] as $dokterLeveling) {
                        if (($dokterLeveling['drId'] ?? '') === $this->filterDokter) {
                            return true;
                        }
                    }
                    return false;
                })
                ->pluck('rihdr_no')
                ->toArray();

            $query->where(function ($q) use ($ids) {
                $q->where('rv.dr_id', $this->filterDokter)->orWhereIn('rv.rihdr_no', $ids);
            });
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($search, $kw) {
                if (ctype_digit($search)) {
                    $q->orWhere('rv.rihdr_no', 'like', "%{$search}%")->orWhere('rv.reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('UPPER(rv.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(rv.reg_name)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(rv.vno_sep)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(rv.dr_name)'), 'like', "%{$kw}%");
            });
        }

        return $query;
    }

    /** DPJP utama dari levelingDokter (levelDokter 'Utama'); cadangan dokter penerima. */
    private function dpjpUtama(array $dataRi, ?string $drPenerima): string
    {
        foreach ($dataRi['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [] as $dokterLeveling) {
            if (($dokterLeveling['levelDokter'] ?? '') === 'Utama' && !empty($dokterLeveling['drName'])) {
                return $dokterLeveling['drName'];
            }
        }
        return (string) ($drPenerima ?? '-');
    }

    #[Computed]
    public function rows()
    {
        $paginator = $this->baseQuery()->paginate($this->itemsPerPage);

        $paginator->getCollection()->transform(function ($row) {
            $jsonRaw = OracleLob::read($row->datadaftarri_json ?? null, 'rstxn_rihdrs', 'rihdr_no', $row->rihdr_no, 'datadaftarri_json');
            $json = json_decode($jsonRaw ?: '{}', true) ?? [];
            unset($row->datadaftarri_json);

            $row->no_sep = $json['sep']['noSep'] ?? ($row->vno_sep ?? null);
            $row->dpjp_utama = $this->dpjpUtama($json, $row->dr_name);

            // Tarif berjalan RS vs tarif INA-CBG (indikator merah/kuning/hijau) — hanya tab BPJS;
            // pasien umum tak punya tarif klaim, dan hitung biaya RI = belasan query per baris.
            if ($this->tabKlaim === 'BPJS') {
                $row->tarif_rs = IndikatorTarifKlaim::tarifBerjalanRi($this->calculateRICosts((int) $row->rihdr_no));
                $row->tarif_inacbg = IndikatorTarifKlaim::tarifInacbgDariIdrg(is_array($json['idrg'] ?? null) ? $json['idrg'] : []);
            }

            return $row;
        });

        return $paginator;
    }

    /** BPJS = kategori klaim_status 'BPJS'; UMUM = selain itu (termasuk klaim tanpa master). */
    private function saringJenisKlaim($query, string $tab): void
    {
        if ($tab === 'UMUM') {
            $query->where(DB::raw("NVL(kt.klaim_status,'-')"), '!=', 'BPJS');
        } else {
            $query->where('kt.klaim_status', 'BPJS');
        }
    }

    /** Jumlah pasien dirawat per tab — untuk label tab. */
    #[Computed]
    public function jumlahPerKlaim(): array
    {
        $jumlah = DB::table('rsview_rihdrs as rv')
            ->leftJoin('rsmst_klaimtypes as kt', 'kt.klaim_id', '=', 'rv.klaim_id')
            ->where(DB::raw("NVL(rv.ri_status,'I')"), 'I')
            ->selectRaw("SUM(CASE WHEN kt.klaim_status = 'BPJS' THEN 1 ELSE 0 END) as bpjs, SUM(CASE WHEN NVL(kt.klaim_status,'-') != 'BPJS' THEN 1 ELSE 0 END) as umum")
            ->first();

        return ['BPJS' => (int) ($jumlah->bpjs ?? 0), 'UMUM' => (int) ($jumlah->umum ?? 0)];
    }

    #[Computed]
    public function dokterList()
    {
        return DB::table('rsview_rihdrs')->select('dr_id', DB::raw('MAX(dr_name) as dr_name'), DB::raw('COUNT(DISTINCT rihdr_no) as total_pasien'))->where(DB::raw("NVL(ri_status,'I')"), 'I')->groupBy('dr_id')->orderBy('dr_name')->get();
    }

    #[Computed]
    public function bangsalList()
    {
        return DB::table('rsview_rihdrs')->select('bangsal_id', DB::raw('MAX(bangsal_name) as bangsal_name'))->where(DB::raw("NVL(ri_status,'I')"), 'I')->whereNotNull('bangsal_id')->groupBy('bangsal_id')->orderBy('bangsal_name')->get();
    }

};
?>

<div>
    <x-page-title
        title="Kontrol Klaim Rawat Inap"
        subtitle="Pantau klaim pasien yang sedang dirawat inap" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TAB JENIS KLAIM --}}
            <x-tabs variant="chip">
                <x-tab :active="$tabKlaim === 'BPJS'" wire:click="pilihTabKlaim('BPJS')">
                    BPJS <span class="ml-1 text-xs opacity-80">({{ $this->jumlahPerKlaim['BPJS'] }})</span>
                </x-tab>
                <x-tab :active="$tabKlaim === 'UMUM'" wire:click="pilihTabKlaim('UMUM')">
                    Umum <span class="ml-1 text-xs opacity-80">({{ $this->jumlahPerKlaim['UMUM'] }})</span>
                </x-tab>
            </x-tabs>

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3" wire:key="{{ $this->renderKey('kontrol-klaim-ri-toolbar', []) }}">

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
                                placeholder="Cari No RI / No RM / Nama Pasien / No SEP / Dokter..." />
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

                    {{-- FILTER DOKTER --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter" />
                        <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-52">
                            <option value="">Semua Dokter</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">{{ $dokter->dr_name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center gap-2 ml-auto">
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
                    {{-- Tabel ringkas: satu pasien ±2 baris teks supaya banyak pasien muat satu layar. --}}
                    <table class="w-full min-w-[1150px] table-fixed text-sm">

                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="w-[24%] px-4 py-2">Pasien</th>
                                <th class="w-[22%] px-4 py-2">Kamar / DPJP</th>
                                <th class="w-[20%] px-4 py-2">Klaim / SEP</th>
                                <th class="w-[12%] px-4 py-2">Masuk</th>
                                @if ($tabKlaim === 'BPJS')
                                    <th class="w-[16%] px-4 py-2">Tarif RS vs INA-CBG</th>
                                @endif
                                <th class="w-44 px-4 py-2 text-center">Action</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-hairline dark:divide-gray-700">
                            @forelse ($this->rows as $row)
                                <tr class="align-top hover:bg-surface-soft dark:hover:bg-gray-800"
                                    wire:key="kontrol-klaim-ri-row-{{ $row->rihdr_no }}">

                                    <td class="px-4 py-2">
                                        <x-list.identitas-pasien :regNo="$row->reg_no" :nama="$row->reg_name"
                                            :sex="$row->sex" :tglLahir="$row->birth_date" />
                                    </td>

                                    <td class="px-4 py-2 space-y-0.5">
                                        <div class="font-semibold text-ink dark:text-gray-100">{{ $row->room_name ?? '-' }}</div>
                                        <div class="text-xs text-muted dark:text-gray-400">{{ $row->bangsal_name ?? '-' }}</div>
                                        <div class="text-body dark:text-gray-300">{{ $row->dpjp_utama }}</div>
                                    </td>

                                    <td class="px-4 py-2 space-y-1">
                                        <x-list.klaim-badge :status="$row->klaim_status" :desc="$row->klaim_desc" :id="$row->klaim_id" />
                                        <x-list.sep-spri :sep="$row->no_sep" />
                                    </td>

                                    <td class="px-4 py-2 space-y-0.5 tabular-nums">
                                        <div class="text-body dark:text-gray-300">{{ $row->entry_date_display ?? '-' }}</div>
                                        <div class="text-xs text-muted dark:text-gray-400">Hari ke-{{ (int) $row->hari_rawat }}</div>
                                    </td>

                                    @if ($tabKlaim === 'BPJS')
                                        <td class="px-4 py-2">
                                            {{-- Indikator merah/kuning/hijau — App\Support\IndikatorTarifKlaim --}}
                                            <x-klaim.indikator-tarif :tarifRs="$row->tarif_rs" :tarifKlaim="$row->tarif_inacbg" :ringkas="true" />
                                        </td>
                                    @endif

                                    {{-- ACTION — Bridging iDRG / INACBG (hanya pasien BPJS) --}}
                                    <td class="px-4 py-2 text-center">
                                        @if ($row->klaim_status === 'BPJS' || $row->klaim_id === 'JM')
                                            @can('idrg.kirim')
                                                <x-primary-button type="button" wire:click="openIdrg('{{ $row->rihdr_no }}')"
                                                    wire:loading.attr="disabled" wire:target="openIdrg('{{ $row->rihdr_no }}')"
                                                    class="whitespace-nowrap">
                                                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    Bridging iDRG
                                                </x-primary-button>
                                            @else
                                                <span class="text-xs text-muted">Tidak berwenang</span>
                                            @endcan
                                        @else
                                            <span class="text-xs text-muted">Bukan BPJS</span>
                                        @endif
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $tabKlaim === 'BPJS' ? 6 : 5 }}" class="px-6 py-16">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Tidak ada pasien yang sedang dirawat</p>
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

            {{-- iDRG/INACBG Modal (listen ke event daftar-ri.idrg.open) --}}
            <livewire:pages::transaksi.ri.daftar-ri.idrg-ri-actions wire:key="kontrol-klaim-idrg-ri-actions" />


        </div>
    </div>
</div>
