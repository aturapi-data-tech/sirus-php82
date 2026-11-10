<?php

namespace App\Http\Livewire\Pages\Master\MasterPasien;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\DB;
use App\Support\CakupanIhsPasien;
use App\Support\IhsPasienSatuSehat;
use Illuminate\Support\Facades\Cache;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use WithPagination, MasterPasienTrait;

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    public string $searchKeyword = '';
    public int $itemsPerPage = 10;

    /** 'belum' = pasien berkunjung 90 hari tanpa IHS, urut dari kunjungan terbanyak. */
    #[Url(as: 'ihs')]
    public string $filterIhs = '';

    public function updatedFilterIhs(): void
    {
        $this->resetPage();
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterIhs']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    /* -------------------------
     | Child modal triggers
     * ------------------------- */
    public function openCreate(): void
    {
        $this->dispatch('master.pasien.openCreate');
    }

    public function openEdit(string $regNo): void
    {
        $this->dispatch('master.pasien.openEdit', regNo: $regNo);
    }

    /* -------------------------
     | Request Delete (delegate ke actions)
     * ------------------------- */
    public function requestDelete(string $regNo): void
    {
        $this->dispatch('master.pasien.requestDelete', regNo: $regNo);
    }

    /* -------------------------
     | Refresh after child save
     * ------------------------- */
    #[On('master.pasien.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    /* -------------------------
     | Computed queries
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        $searchKeyword = trim($this->searchKeyword);

        $queryBuilder = DB::table('rsmst_pasiens')
            ->select(['reg_no', 'reg_name', 'sex', 'birth_date', 'address', 'phone', 'blood', 'marital_status', 'nik_bpjs', 'nokartu_bpjs', 'patient_uuid', 'no_jkn', 'reg_date'])
            ->orderBy('reg_name', 'asc');

        if ($this->filterIhs === 'belum') {
            $queryBuilder
                ->reorder()
                ->join(DB::raw('(' . CakupanIhsPasien::sqlPemakaian() . ') pakai'), 'pakai.reg_no_pakai', '=', 'rsmst_pasiens.reg_no')
                ->addSelect(DB::raw('pakai.jumlah_pakai AS jumlah_pakai'))
                ->whereRaw('TRIM(patient_uuid) IS NULL')
                ->orderByRaw('pakai.jumlah_pakai DESC')
                ->orderBy('reg_name', 'asc');
        }

        if ($searchKeyword !== '') {
            $uppercaseKeyword = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($subQuery) use ($uppercaseKeyword, $searchKeyword) {
                $subQuery
                    ->orWhereRaw('UPPER(reg_no) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(nik_bpjs) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(nokartu_bpjs) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(reg_name) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(address) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(phone) LIKE ?', ["%{$uppercaseKeyword}%"]);
            });
        }

        return $queryBuilder;
    }

    #[Computed]
    public function rows()
    {
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }

    /* -------------------------
     | Ambil IHS massal: pasien berkunjung 90 hari tanpa IHS, kunjungan terbanyak dulu.
     | Antrean & daftar laporan di Cache (bukan properti publik: bisa ribuan baris). Tiap putaran
     | dipicu wire:poll dan dibatasi waktunya supaya tidak timeout. Staging dibatasi 5 pasien.
     * ------------------------- */
    private const IHS_DETIK_PER_PUTARAN = 15;
    public const IHS_BATAS_STAGING = 5;
    private const IHS_BATAS_LAPORAN = 300;

    public bool $ihsBerjalan = false;
    public int $ihsTotal = 0;
    public int $ihsSelesai = 0;
    public array $ihsRekap = [];

    public function bolehAmbilIhs(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['Admin', 'Mr']);
    }

    public function ihsDiStaging(): bool
    {
        return str_contains((string) env('SATUSEHAT_BASE_URL'), '-stg');
    }

    private function kunciIhs(string $bagian): string
    {
        return 'ihs-massal.' . auth()->id() . '.' . $bagian;
    }

    public function mulaiAmbilIhs(): void
    {
        if (!$this->bolehAmbilIhs() || $this->ihsBerjalan) {
            return;
        }

        $antrean = DB::table('rsmst_pasiens')
            ->join(DB::raw('(' . CakupanIhsPasien::sqlPemakaian() . ') pakai'), 'pakai.reg_no_pakai', '=', 'rsmst_pasiens.reg_no')
            ->whereRaw('TRIM(patient_uuid) IS NULL')
            ->orderByRaw('pakai.jumlah_pakai DESC')
            ->orderBy('reg_no')
            ->pluck('reg_no')
            ->all();

        if ($this->ihsDiStaging()) {
            $antrean = array_slice($antrean, 0, self::IHS_BATAS_STAGING);
        }

        Cache::put($this->kunciIhs('antrean'), $antrean, now()->addHours(6));
        Cache::put($this->kunciIhs('laporan'), [], now()->addHours(6));
        $this->ihsTotal = count($antrean);
        $this->ihsSelesai = 0;
        $this->ihsRekap = [];
        $this->ihsBerjalan = $antrean !== [];
    }

    public function hentikanAmbilIhs(): void
    {
        $this->ihsBerjalan = false;
        Cache::forget($this->kunciIhs('antrean'));
        unset($this->rows);
    }

    public function putaranAmbilIhs(): void
    {
        if (!$this->ihsBerjalan || !$this->bolehAmbilIhs()) {
            return;
        }

        // Poll berikutnya bisa datang saat putaran ini belum selesai: yang kedua langsung pulang.
        $kunci = Cache::lock($this->kunciIhs('kunci'), self::IHS_DETIK_PER_PUTARAN + 30);
        if (!$kunci->get()) {
            return;
        }

        try {
            $antrean = Cache::get($this->kunciIhs('antrean'), []);
            $laporan = Cache::get($this->kunciIhs('laporan'), []);
            $layanan = new IhsPasienSatuSehat();
            $mulai = microtime(true);

            while ($antrean !== [] && microtime(true) - $mulai < self::IHS_DETIK_PER_PUTARAN) {
                $regNo = array_shift($antrean);
                $hasil = $layanan->pastikanDariMaster((string) $regNo);
                $this->ihsSelesai++;
                $this->ihsRekap[$hasil['status']] = ($this->ihsRekap[$hasil['status']] ?? 0) + 1;

                if (!in_array($hasil['status'], IhsPasienSatuSehat::BERHASIL, true) && count($laporan) < self::IHS_BATAS_LAPORAN) {
                    $laporan[] = ['regNo' => $regNo, 'status' => $hasil['status'], 'pesan' => $hasil['pesan']];
                }
            }

            Cache::put($this->kunciIhs('antrean'), $antrean, now()->addHours(6));
            Cache::put($this->kunciIhs('laporan'), $laporan, now()->addHours(6));
            $this->ihsBerjalan = $antrean !== [];
            unset($this->rows);
        } finally {
            $kunci->release();
        }
    }

    /** Pasien yang tidak berhasil pada proses terakhir, untuk dibereskan petugas. */
    #[Computed]
    public function ihsLaporan(): array
    {
        return Cache::get($this->kunciIhs('laporan'), []);
    }
};
?>

<div>
    <x-page-title
        title="Master Pasien"
        subtitle="Kelola data pasien untuk aplikasi" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter + Action --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Pasien" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari nama/NRM/NIK..." class="block w-full" />
                    </div>



                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('rj.daftar') }}" wire:navigate>
                            <x-outline-button type="button">Pendaftaran Rawat Jalan</x-outline-button>
                        </a>
                        <a href="{{ route('ugd.daftar') }}" wire:navigate>
                            <x-outline-button type="button">Pendaftaran UGD</x-outline-button>
                        </a>
                        <a href="{{ route('ri.daftar') }}" wire:navigate>
                            <x-outline-button type="button">Pendaftaran Rawat Inap</x-outline-button>
                        </a>

                        <div class="w-56">
                            <x-input-label for="filterIhs" value="Filter IHS" class="sr-only" />
                            <x-select-input id="filterIhs" wire:model.live="filterIhs">
                                <option value="">Semua pasien</option>
                                <option value="belum">Berkunjung belum IHS</option>
                            </x-select-input>
                        </div>
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="7">7</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Data Pasien Baru
                        </x-primary-button>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            @php($rows = $this->rows)


            {{-- Ambil IHS massal --}}
            @if ($filterIhs === 'belum' && $this->bolehAmbilIhs())
                <div class="px-4 py-3 mt-3 border rounded-xl bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700"
                    @if ($ihsBerjalan) wire:poll.1s="putaranAmbilIhs" @endif>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="text-sm text-body dark:text-gray-300">
                            <span class="font-semibold text-ink dark:text-gray-100">Ambil IHS massal</span>:
                            cari lewat NIK + nama + tanggal lahir, daftarkan bila NIK belum terdaftar. Bayi dilewati.
                            @if ($this->ihsDiStaging())
                                <span class="font-semibold text-amber-700">Mode staging: dibatasi {{ $this::IHS_BATAS_STAGING }} pasien.</span>
                            @endif
                        </div>
                        @if ($ihsBerjalan)
                            <x-secondary-button type="button" wire:click="hentikanAmbilIhs">Hentikan</x-secondary-button>
                        @else
                            <x-primary-button type="button" wire:click="mulaiAmbilIhs" wire:loading.attr="disabled"
                                wire:target="mulaiAmbilIhs"
                                wire:confirm="Cari dan daftarkan IHS untuk pasien di daftar ini ke SATUSEHAT?">
                                Ambil IHS
                            </x-primary-button>
                        @endif
                    </div>

                    @if ($ihsTotal > 0)
                        <div class="mt-2 text-sm text-body dark:text-gray-300">
                            <span class="font-semibold">{{ number_format($ihsSelesai, 0, ',', '.') }} dari {{ number_format($ihsTotal, 0, ',', '.') }}</span>
                            @if ($ihsBerjalan) <x-loading /> @endif
                            @foreach ($ihsRekap as $status => $jumlah)
                                <span class="ml-2 text-muted">{{ IhsPasienSatuSehat::LABEL[$status] ?? $status }}: {{ number_format($jumlah, 0, ',', '.') }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if (!$ihsBerjalan && $this->ihsLaporan !== [])
                        <details class="mt-2 text-sm">
                            <summary class="cursor-pointer text-brand">Lihat {{ count($this->ihsLaporan) }} pasien yang perlu dibereskan</summary>
                            <ul class="mt-2 space-y-1 overflow-y-auto max-h-48">
                                @foreach ($this->ihsLaporan as $barisLaporan)
                                    <li wire:key="ihs-laporan-{{ $barisLaporan['regNo'] }}">
                                        <span class="font-mono">{{ $barisLaporan['regNo'] }}</span>
                                        <span class="font-semibold">{{ IhsPasienSatuSehat::LABEL[$barisLaporan['status']] ?? $barisLaporan['status'] }}</span>:
                                        <span class="text-muted">{{ $barisLaporan['pesan'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            @endif

            {{-- TABLE WRAPPER: card --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA --}}
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        {{-- TABLE HEAD --}}
                        <thead class="sticky top-0 z-10">
                            <tr class="text-left">
                                <th>No RM</th>
                                <th>Pasien</th>
                                <th>Telepon</th>
                                <th>Alamat</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($rows as $row)
                                <tr wire:key="pasien-row-{{ $row->reg_no }}">
                                    <td class="ds-td-token">
                                        {{ $row->reg_no }}
                                        @if ($filterIhs === 'belum')
                                            <div class="text-xs font-normal" style="color:var(--muted)">
                                                {{ number_format($row->jumlah_pakai ?? 0, 0, ',', '.') }} kunjungan dalam {{ CakupanIhsPasien::HARI_PEMAKAIAN }} hari
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $row->reg_name }}</div>
                                        <div class="text-xs" style="color:var(--muted)">
                                            {{ $row->sex === 'L' ? 'L' : ($row->sex === 'P' ? 'P' : '-') }}
                                            @if ($row->birth_date)
                                                &bull; {{ date('d/m/Y', strtotime($row->birth_date)) }}
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1 text-xs" style="color:var(--muted)">
                                            @if (!empty($row->nik_bpjs))
                                                <span>NIK: <span class="font-mono text-gray-700 dark:text-gray-300">{{ $row->nik_bpjs }}</span></span>
                                            @endif
                                            @if (!empty($row->nokartu_bpjs))
                                                <span>BPJS: <span class="font-mono text-gray-700 dark:text-gray-300">{{ $row->nokartu_bpjs }}</span></span>
                                            @endif
                                            @if (trim($row->patient_uuid ?? '') !== '')
                                                <span>UUID: <span class="font-mono text-gray-700 dark:text-gray-300">{{ $row->patient_uuid }}</span></span>
                                            @else
                                                <span>UUID: <x-badge variant="danger">Belum</x-badge></span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap" style="color:var(--muted)">{{ $row->phone ?? '-' }}</td>
                                    <td class="px-6 py-4 max-w-xs truncate" style="color:var(--muted)">{{ $row->address ?? '-' }}</td>
                                    <td class="ds-c px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->reg_no }}')" />

                                            <x-action-delete :action="'requestDelete(\'' . $row->reg_no . '\')'" title="Hapus Pasien"
                                                message="Yakin hapus pasien {{ $row->reg_name }}?" />
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-10">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Data belum ada.</p>
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
                        {{ $rows->links() }}
                    </div>
                </div>

                {{-- Child actions component --}}
                <livewire:pages::master.master-pasien.master-pasien-actions wire:key="master-pasien-actions" />

            </div>
        </div>
    </div>
    </div>
