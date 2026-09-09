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
use App\Support\NoSep;
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

    /**
     * Status pendaftaran klaim ke BPJS: '' semua | 'belum' | 'terdaftar'.
     * Penandanya ada di node JSON (apotekOnline.noSjp), bukan kolom — jadi
     * saringannya memakai REGEXP_LIKE, lihat baseQuery().
     */
    public string $filterStatus = '';

    /**
     * Jenis klaim. Beda dari Pelayanan RJ yang menyaring BPJS vs UMUM: layar ini
     * memang HANYA berisi pasien BPJS, jadi yang berguna adalah memilah antar
     * jenis BPJS-nya sendiri (JKN Mandiri / PBI / Kronis / JKN Mobile).
     */
    public string $filterKlaim = '';

    public string $filterDokter = '';

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

    /**
     * Daftar dokter untuk dropdown. HANYA bergantung pada rentang tanggal —
     * filter lain (status, klaim, pencarian) sengaja tidak dipakai supaya isi
     * dropdown stabil: petugas bisa berpindah status/klaim tanpa kehilangan
     * dokter yang sudah dipilih, walau hasil query utama jadi kosong.
     * Pola sama dengan dokterList() di Pelayanan RJ.
     */
    public function dokterList()
    {
        [$mulai, $selesai] = $this->dateRange();

        return DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'h.klaim_id', '=', 'k.klaim_id')
            ->whereBetween('h.rj_date', [$mulai, $selesai])
            ->whereRaw('h.vno_sep IS NOT NULL AND LENGTH(TRIM(h.vno_sep)) > 0')
            ->where(function ($subQuery) {
                $subQuery->where('k.klaim_status', 'BPJS')->orWhere('h.klaim_id', 'JM');
            })
            ->select('h.dr_id', DB::raw('MAX(d.dr_name) as dr_name'))
            ->groupBy('h.dr_id')
            ->orderBy('dr_name')
            ->get();
    }

    /** Jenis klaim yang benar-benar muncul pada periode ini. Stabil seperti dokterList(). */
    public function klaimList()
    {
        [$mulai, $selesai] = $this->dateRange();

        return DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'h.klaim_id', '=', 'k.klaim_id')
            ->whereBetween('h.rj_date', [$mulai, $selesai])
            ->whereRaw('h.vno_sep IS NOT NULL AND LENGTH(TRIM(h.vno_sep)) > 0')
            ->where(function ($subQuery) {
                $subQuery->where('k.klaim_status', 'BPJS')->orWhere('h.klaim_id', 'JM');
            })
            ->select('h.klaim_id', DB::raw('MAX(k.klaim_desc) as klaim_desc'))
            ->groupBy('h.klaim_id')
            ->orderBy('klaim_desc')
            ->get();
    }

    /**
     * Dipanggil tombol Reset pada x-toolbar-refresh-reset (nama method sudah
     * dipatok komponennya). Mode harian/bulanan & tanggal SENGAJA ikut kembali
     * ke keadaan awal supaya "reset" berarti sama seperti di Pelayanan RJ.
     */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterKlaim', 'filterDokter']);
        $this->mode = 'harian';
        $this->filterTanggal = now()->format('d/m/Y');
        $this->filterBulan = now()->format('m/Y');
        $this->resetPage();
    }

    /**
     * Tahapan layanan pasien, disalin dari Antrian Apotek RJ supaya istilah &
     * warnanya sama di kedua layar. Urutannya menurun: yang paling akhir tercapai
     * yang ditampilkan. Batal terdeteksi dari taskId99 ATAU rj_status='F'
     * (mutasi langsung model lama yang tak menulis taskId).
     *
     * @return array{teks: string, variant: string}
     */
    private function statusLayanan(array $data, ?string $rjStatus): array
    {
        $tasks = $data['taskIdPelayanan'] ?? [];

        if (!empty($tasks['taskId99']) || $rjStatus === 'F') {
            return ['teks' => 'Batal', 'variant' => 'danger'];
        }
        if (!empty($tasks['taskId7'])) {
            return ['teks' => 'Pasien Menerima Resep', 'variant' => 'success'];
        }
        if (!empty($tasks['taskId6'])) {
            return ['teks' => 'Menunggu Resep', 'variant' => 'warning'];
        }
        if (!empty($tasks['taskId5'])) {
            return ['teks' => 'Keluar Poli', 'variant' => 'brand'];
        }
        if (!empty($tasks['taskId4'])) {
            return ['teks' => 'Masuk Poli', 'variant' => 'warning'];
        }
        if (!empty($tasks['taskId3'])) {
            return ['teks' => 'Pendaftaran', 'variant' => 'alternative'];
        }

        return ['teks' => 'Belum Dilayani', 'variant' => 'gray'];
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
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterKlaim(): void { $this->resetPage(); }
    public function updatedFilterDokter(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void { $this->resetPage(); }

    #[On('apotek-online-rj.refresh')]
    public function refresh(): void { unset($this->rows); }

    /** Rentang tanggal aktif menurut mode. */
    private function dateRange(): array
    {
        if ($this->mode === 'bulanan') {
            try {
                $tanggal = Carbon::createFromFormat('m/Y', trim($this->filterBulan))->startOfMonth();
            } catch (\Throwable) {
                $tanggal = now()->startOfMonth();
            }
            return [$tanggal, (clone $tanggal)->endOfMonth()];
        }

        try {
            $tanggal = Carbon::createFromFormat('d/m/Y', trim($this->filterTanggal))->startOfDay();
        } catch (\Throwable) {
            $tanggal = now()->startOfDay();
        }
        return [$tanggal, (clone $tanggal)->endOfDay()];
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
            ->where(function ($subQuery) {
                $subQuery->where('k.klaim_status', 'BPJS')->orWhere('h.klaim_id', 'JM');
            })
            ->select([
                'h.rj_no',
                DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date_display"),
                'h.reg_no', 'p.reg_name', 'p.sex', 'p.address',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                'h.dr_id', 'd.dr_name', 'po.poli_desc',
                // Cara bayar diambil dari MODEL KLAIM seperti Pelayanan RJ: label dari
                // klaim_desc asli, warna dari kategori klaim_status.
                'h.klaim_id', 'k.klaim_status', 'k.klaim_desc',
                'h.shift', 'h.rj_status',
                'h.vno_sep', 'h.status_kronis', 'h.status_iter',
                'h.datadaftarpolirj_json',
            ])
            ->orderByDesc('h.rj_date');

        if ($this->filterDokter !== '') {
            $query->where('h.dr_id', $this->filterDokter);
        }

        if ($this->filterKlaim !== '') {
            $query->where('h.klaim_id', $this->filterKlaim);
        }

        // Terdaftar = node apotekOnline sudah menyimpan noSjp BERISI. Oracle tak
        // mendukung JSON_VALUE di sini (ORA-00904), jadi pakai REGEXP_LIKE pada CLOB;
        // polanya menuntut minimal satu karakter di antara tanda kutip supaya draft
        // (noSjp kosong) tidak ikut terhitung sudah terkirim.
        $polaSudahDaftar = '"noSjp"[[:space:]]*:[[:space:]]*"[^"]+"';
        if ($this->filterStatus === 'terdaftar') {
            $query->whereRaw("REGEXP_LIKE(h.datadaftarpolirj_json, '{$polaSudahDaftar}')");
        }
        if ($this->filterStatus === 'belum') {
            // Kurung WAJIB: tanpa itu OR mengangkat dirinya ke atas rantai AND dan
            // seluruh filter lain (tanggal, dokter, BPJS) ikut lumpuh untuk baris
            // yang JSON-nya NULL. REGEXP_LIKE atas NULL bernilai NULL, jadi cabang
            // IS NULL memang diperlukan — bukan sekadar jaga-jaga.
            $query->whereRaw("(NOT REGEXP_LIKE(h.datadaftarpolirj_json, '{$polaSudahDaftar}') OR h.datadaftarpolirj_json IS NULL)");
        }

        if (trim($this->searchKeyword) !== '') {
            $keyword = '%' . mb_strtoupper(trim($this->searchKeyword)) . '%';
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->whereRaw('UPPER(p.reg_name) LIKE ?', [$keyword])
                  ->orWhereRaw('UPPER(h.reg_no) LIKE ?', [$keyword])
                  ->orWhereRaw('UPPER(h.vno_sep) LIKE ?', [$keyword])
                  ->orWhereRaw('TO_CHAR(h.rj_no) LIKE ?', [$keyword]);
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
            $paginator->getCollection()->map(function ($kunjungan) {
            $data = $this->decodeJson($kunjungan->datadaftarpolirj_json);
            $ringkas = $this->ringkasKronis($kunjungan->rj_no);
            $status = $this->statusLayanan($data, $kunjungan->rj_status);
            $apotek = $data['apotekOnline'] ?? [];

            return (object) [
                'rjNo' => $kunjungan->rj_no,
                'rjDate' => $kunjungan->rj_date_display,
                'regNo' => $kunjungan->reg_no,
                'regName' => $kunjungan->reg_name,
                'sex' => $kunjungan->sex,
                'birthDate' => $kunjungan->birth_date,
                'alamat' => $kunjungan->address,
                'drName' => $kunjungan->dr_name,
                'poliDesc' => $kunjungan->poli_desc,
                'klaimId' => $kunjungan->klaim_id,
                'klaimStatus' => $kunjungan->klaim_status,
                'klaimDesc' => $kunjungan->klaim_desc,
                'shift' => $kunjungan->shift,
                'statusTeks' => $status['teks'],
                'statusVariant' => $status['variant'],
                'adaEresep' => isset($data['eresep']) || isset($data['eresepRacikan']),
                'adaRacikan' => isset($data['eresepRacikan']),
                // vno_sep kolom TEKS BEBAS — nomor SEP harus diekstrak, bukan dibaca
                // utuh. Lihat App\Support\NoSep untuk sebaran isinya.
                'noSep' => NoSep::ekstrak($kunjungan->vno_sep),
                'noSepAsli' => (string) $kunjungan->vno_sep,
                'sepCatatan' => NoSep::catatan($kunjungan->vno_sep),
                'sepSah' => NoSep::sah($kunjungan->vno_sep),
                'statusKronis' => $kunjungan->status_kronis,
                'statusIter' => $kunjungan->status_iter,
                'jmlKronis' => $ringkas['kronis'],
                'jmlBerDpho' => $ringkas['berDpho'],
                'jmlTanpaDpho' => $ringkas['tanpaDpho'],
                'sudahDaftar' => !empty($apotek['noSjp']),
                'noSjpApotek' => $apotek['noSjp'] ?? '',
            ];
        })
        );

        return $paginator;
    }

    /**
     * Hitung obat KRONIS (rstxn_rjobats status_kronis='Y') & berapa yang sudah
     * ber-kode DPHO — itulah yang benar-benar bisa diklaim Apotek Online. Bukan
     * seluruh e-resep: obat dalam paket INA-CBG (qty_bpjs) tak diklaim di sini.
     * Satu query per baris, tapi hanya untuk halaman aktif (~15 baris).
     */
    private function ringkasKronis(string $rjNo): array
    {
        $obat = DB::table('rstxn_rjobats as o')
            ->leftJoin('immst_products as p', 'o.product_id', '=', 'p.product_id')
            ->where('o.rj_no', $rjNo)
            ->where('o.status_kronis', 'Y')
            ->where('o.qty_kronis', '>', 0)
            ->select('p.kode_dpho')
            ->get();

        $berDpho = $obat->filter(fn($barisObat) => trim((string) ($barisObat->kode_dpho ?? '')) !== '')->count();

        return [
            'kronis' => $obat->count(),
            'berDpho' => $berDpho,
            'tanpaDpho' => $obat->count() - $berDpho,
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

                {{-- MODE Harian / Bulanan — khas layar ini, tidak ada di Pelayanan RJ.
                   | Ditaruh paling kiri karena menentukan arti kolom tanggal di sebelahnya. --}}
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

                {{-- SEARCH — flex-1, isi sisa ruang setelah filter lain --}}
                <div class="w-full sm:flex-1 sm:min-w-[12rem]">
                    <x-input-label value="Pencarian" class="sr-only" />
                    <div class="relative mt-1">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                            placeholder="Cari No RM / Nama Pasien / No. SEP..." />
                    </div>
                </div>

                {{-- FILTER TANGGAL / BULAN — satu slot, isinya ikut mode --}}
                <div class="w-full sm:w-auto">
                    <x-input-label :value="$mode === 'harian' ? 'Tanggal' : 'Bulan'" />
                    <div class="relative mt-1">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        @if ($mode === 'harian')
                            <x-text-input type="text" wire:model.live.debounce.500ms="filterTanggal"
                                class="block w-full pl-10 sm:w-40" placeholder="dd/mm/yyyy" />
                        @else
                            <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                                class="block w-full pl-10 sm:w-40" placeholder="mm/yyyy" />
                        @endif
                    </div>
                </div>

                {{-- FILTER STATUS — pendaftaran klaim ke BPJS --}}
                <div class="w-full sm:w-auto">
                    <x-input-label value="Status" />
                    <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-44">
                        <option value="">Semua</option>
                        <option value="belum">Belum didaftarkan</option>
                        <option value="terdaftar">Terdaftar</option>
                    </x-select-input>
                </div>

                {{-- FILTER KLAIM — layar ini BPJS semua, jadi yang dipilah jenis BPJS-nya --}}
                <div class="w-full sm:w-auto">
                    <x-input-label value="Klaim" />
                    <x-select-input wire:model.live="filterKlaim" class="w-full mt-1 sm:w-44">
                        <option value="">Semua</option>
                        @foreach ($this->klaimList() as $klaim)
                            <option value="{{ $klaim->klaim_id }}">{{ $klaim->klaim_desc ?: $klaim->klaim_id }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                {{-- FILTER DOKTER --}}
                <div class="w-full sm:w-auto">
                    <x-input-label value="Dokter" />
                    <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-56">
                        <option value="">Semua Dokter</option>
                        @foreach ($this->dokterList() as $dokter)
                            <option value="{{ $dokter->dr_id }}">{{ $dokter->dr_name }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                {{-- RIGHT ACTIONS --}}
                <div class="flex items-center gap-2 ml-auto">
                    <x-toolbar-refresh-reset :label="null" />

                    <div class="w-20">
                        <x-select-input wire:model.live="itemsPerPage" class="text-sm" title="Per halaman">
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
                        <tr
                            class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                            <th class="px-6 py-3 w-[24%]">Pasien</th>
                            <th class="px-6 py-3 w-[20%]">Poli</th>
                            <th class="px-6 py-3 w-[16%]">Status Layanan</th>
                            <th class="px-6 py-3 w-[18%]">Tindak Lanjut</th>
                            <th class="px-6 py-3 w-[22%] text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $row)
                            <tr class="transition bg-canvas dark:bg-gray-900 rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700 hover:shadow-lg"
                                wire:key="apotek-online-{{ $row->rjNo }}">
                                {{-- Kolom disusun mengikuti Pelayanan RJ: identitas + alamat,
                                   | cara bayar dari model klaim, dan aksi lewat titik-tiga.
                                   | TANPA toggle Alpine per baris (x-data/x-show/x-collapse):
                                   | list ini ikut di-morph tiap refresh, dan pola itu sudah
                                   | pernah membuat Pelayanan RJ hang — konten dibuat tampil terus. --}}
                                {{-- Kolom & lebarnya disamakan dengan Pelayanan RJ. Pemetaan isinya:
                                   | Poli           → poli, dokter, cara bayar
                                   | Status Layanan → SEP + penanda KRONIS/ITER + kesiapan obat
                                   | Tindak Lanjut  → sudah didaftarkan ke BPJS atau belum
                                   | TANPA toggle Alpine per baris (x-data/x-show/x-collapse): list ini
                                   | ikut di-morph tiap refresh, dan pola itu sudah pernah membuat
                                   | Pelayanan RJ hang — konten dibuat tampil terus. --}}
                                <td class="px-6 py-6 space-y-3 align-middle rounded-l-2xl">
                                    <x-list.identitas-pasien :regNo="$row->regNo" :nama="$row->regName"
                                        :sex="$row->sex" :tglLahir="$row->birthDate" :alamat="$row->alamat" />
                                </td>

                                {{-- Ukuran & warna poli/dokter mengikuti kolom Poli Pelayanan RJ:
                                   | poli tebal berwarna brand, dokter text-sm muted, jarak space-y-0.5. --}}
                                <td class="px-6 py-6 space-y-0.5 align-middle">
                                    {{-- Tanggal & jam kunjungan RJ berdiri paling atas di kolom ini. --}}
                                    <div class="text-xs text-body dark:text-gray-400 leading-tight">
                                        {{ $row->rjDate }}
                                    </div>
                                    <div class="font-semibold text-brand dark:text-emerald-400 leading-tight">
                                        {{ $row->poliDesc ?? '-' }}
                                    </div>
                                    <div class="text-sm text-muted dark:text-gray-400 leading-tight">
                                        dr. {{ $row->drName ?? '-' }}
                                    </div>
                                    <div class="mt-0.5">
                                        <x-list.klaim-badge :status="$row->klaimStatus" :desc="$row->klaimDesc" :id="$row->klaimId" />
                                    </div>
                                </td>

                                {{-- STATUS LAYANAN — susunannya disamakan dengan Antrian Apotek RJ:
                                   | shift, badge tahapan layanan (dari taskIdPelayanan), lalu penanda
                                   | E-Resep/Racikan. Baris SEP & kesiapan obat khas layar ini menyusul
                                   | di bawahnya. --}}
                                <td class="px-6 py-6 space-y-2 align-middle">
                                    <div class="text-sm text-muted dark:text-gray-400 whitespace-nowrap">
                                        Shift {{ $row->shift ?? '-' }} | {{ $row->rjDate }}
                                    </div>

                                    <x-badge :variant="$row->statusVariant">{{ $row->statusTeks }}</x-badge>

                                    <div class="flex gap-1.5">
                                        @if ($row->adaEresep)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                                E-Resep
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                                Tanpa Resep
                                            </span>
                                        @endif

                                        @if ($row->adaRacikan)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                                Racikan
                                            </span>
                                        @endif
                                    </div>

                                    @if ($row->sepSah)
                                        <x-list.sep-spri :sep="$row->noSep" />
                                        @if (filled($row->sepCatatan))
                                            {{-- Catatan yang menempel pada kolom SEP (mis. ITER) ditampilkan
                                               | terpisah supaya jelas ia BUKAN bagian dari nomor yang dikirim. --}}
                                            <div class="text-xs text-muted-soft">catatan: {{ $row->sepCatatan }}</div>
                                        @endif
                                    @else
                                        <div><x-badge variant="danger">SEP tidak terbaca</x-badge></div>
                                        <div class="text-xs text-muted-soft">isi kolom: {{ $row->noSepAsli ?: '(kosong)' }}</div>
                                    @endif

                                    @if ($row->statusKronis === 'Y')
                                        <div><x-badge variant="warning">KRONIS</x-badge></div>
                                    @endif
                                    @if ($row->statusIter === 'Y')
                                        <div><x-badge variant="purple">ITER</x-badge></div>
                                    @endif

                                </td>

                                {{-- TINDAK LANJUT — kesiapan obat lebih dulu (itu yang menentukan
                                   | ada/tidaknya tindak lanjut), baru status pendaftaran klaimnya. --}}
                                <td class="px-6 py-6 space-y-1 align-middle">
                                    @if ($row->jmlKronis > 0)
                                        <div class="text-xs text-body dark:text-gray-200">{{ $row->jmlKronis }} obat kronis</div>
                                        <div class="text-xs">
                                            <span class="text-success">{{ $row->jmlBerDpho }} siap (ber-DPHO)</span>
                                            @if ($row->jmlTanpaDpho > 0)
                                                · <span class="text-warning-deep dark:text-amber-300">{{ $row->jmlTanpaDpho }} belum dipetakan</span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="text-xs text-muted-soft">tidak ada obat kronis</div>
                                    @endif

                                    @if ($row->sudahDaftar)
                                        <x-badge variant="success">Terdaftar</x-badge>
                                        <div class="font-mono text-xs text-muted-soft">{{ $row->noSjpApotek }}</div>
                                    @else
                                        <x-badge variant="gray">Belum didaftarkan</x-badge>
                                    @endif
                                </td>

                                <td class="px-6 py-6 text-center align-middle rounded-r-2xl">
                                    <x-dropdown position="left" width="w-[320px]">
                                        <x-slot name="trigger">
                                            <x-secondary-button type="button" class="p-2">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z" />
                                                </svg>
                                            </x-secondary-button>
                                        </x-slot>

                                        <x-slot name="content">
                                            <div class="p-2 space-y-2">
                                                {{-- Kepala menu: menjawab "menu ini untuk pasien yang mana". --}}
                                                <x-list.identitas-aksi :regNo="$row->regNo" :nama="$row->regName"
                                                    :sex="$row->sex" jalur="Rawat Jalan" />

                                                @if ($row->sudahDaftar)
                                                    <x-outline-button type="button" class="w-full"
                                                        wire:click="$dispatch('apotek-online-rj.daftarkan', { rjNo: '{{ $row->rjNo }}' })">
                                                        Lihat Klaim
                                                    </x-outline-button>
                                                @elseif ($row->sepSah)
                                                    <x-primary-button type="button" class="w-full"
                                                        wire:click="$dispatch('apotek-online-rj.daftarkan', { rjNo: '{{ $row->rjNo }}' })">
                                                        Daftarkan &amp; Kirim
                                                    </x-primary-button>
                                                @else
                                                    {{-- Tanpa nomor SEP tak ada yang bisa dikirim: REFASALSJP wajib,
                                                       | dan BPJS menuntut tepat 19 karakter. Ditahan di sini supaya
                                                       | petugas tak menempuh seluruh rantai lalu ditolak di ujung. --}}
                                                    <div class="px-3 py-2 text-xs rounded-lg bg-error/10 text-error-deep dark:text-red-300">
                                                        Tidak bisa didaftarkan — kolom SEP tidak memuat nomor SEP.
                                                        Betulkan lebih dulu di Pendaftaran.
                                                    </div>
                                                @endif
                                            </div>
                                        </x-slot>
                                    </x-dropdown>
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

    {{-- Modal Daftarkan & Kirim (Langkah 2) --}}
    <livewire:pages::transaksi.rj.apotek-online-rj.apotek-online-rj-actions wire:key="apotek-online-rj-actions" />
</div>
