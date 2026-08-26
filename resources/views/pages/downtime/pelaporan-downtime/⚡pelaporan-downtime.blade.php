<?php

/**
 * Pelaporan Down Time SIMRS — LIST (Akreditasi MRMIK 13.1).
 *
 * Merekam formulir DT-01 "Log Kejadian & Penanganan Down Time SIMRS" ke sistem
 * SESUDAH layanan pulih, supaya laporannya bisa dicari, direkap, dan dievaluasi.
 * Cetakan KOSONG untuk diisi tangan saat SIMRS mati tetap ada di menu Formulir
 * Manual Down Time — modul ini kebalikannya, bukan penggantinya.
 *
 * Satu baris = satu KEJADIAN waktu henti.
 * Rancangan tabel: docs/ddl-pelaporan-downtime.sql.
 *
 * Layar ini TIDAK menulis apa pun ke basis data — ia hanya membaca & mengirim
 * event ke -actions, sesuai kontrak docs/standar-master-module.md §2.
 */

use App\Http\Traits\Sistem\PelaporanDowntime\PelaporanDowntimeTrait;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use PelaporanDowntimeTrait, WithPagination;

    /** 'MM/YYYY'; kosong = semua periode. */
    public string $periode = '';

    public string $searchKeyword = '';

    public int $itemsPerPage = 10;

    /** Tabel laporan sudah dipasang di basis data? */
    public bool $siapDipakai = false;

    public function mount(): void
    {
        $this->siapDipakai = $this->checkTabelDowntime();
        $this->periode = Carbon::now(config('app.timezone'))->format('m/Y');
    }

    public function updatedPeriode(): void
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
        $this->reset(['searchKeyword']);
        $this->periode = Carbon::now(config('app.timezone'))->format('m/Y');
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->dispatch('pelaporan-downtime.openCreate');
    }

    public function openEdit(int $downtimeNo): void
    {
        $this->dispatch('pelaporan-downtime.openEdit', downtimeNo: $downtimeNo);
    }

    public function requestDelete(int $downtimeNo): void
    {
        $this->dispatch('pelaporan-downtime.requestDelete', downtimeNo: $downtimeNo);
    }

    #[On('pelaporan-downtime.saved')]
    public function refreshAfterSaved(): void
    {
        // #[Computed] di-cache satu request; tanpa unset() daftar lama masih
        // terpakai setelah laporan disimpan dan layar tampak tidak berubah.
        unset($this->laporanList, $this->rows, $this->rekap);
        $this->resetPage();
    }

    /** Seluruh laporan yang lolos filter, sudah diringkas. */
    #[Computed]
    public function laporanList(): array
    {
        if (! $this->siapDipakai) {
            return [];
        }

        [$daftar] = $this->findRiwayatDowntime(
            filled($this->periode) ? $this->periode : null,
            $this->searchKeyword
        );

        return $daftar;
    }

    /** Ringkasan periode terpilih — bahan laporan evaluasi ke pimpinan RS. */
    #[Computed]
    public function rekap(): array
    {
        return $this->rekapDowntime($this->laporanList);
    }

    /**
     * Paginasi dirakit sendiri: barisnya berasal dari CLOB yang di-decode di PHP,
     * bukan dari query yang bisa di-LIMIT.
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $semua = $this->laporanList;
        $halaman = Paginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            array_slice($semua, ($halaman - 1) * $this->itemsPerPage, $this->itemsPerPage),
            count($semua),
            $this->itemsPerPage,
            $halaman,
            ['path' => request()->url()],
        );
    }
};
?>

<div>

    <x-page-title
        title="Pelaporan Down Time SIMRS"
        subtitle="Formulir DT-01 (MRMIK 13.1) — satu laporan per kejadian waktu henti" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    <div class="flex flex-col w-full gap-3 sm:flex-row sm:items-end lg:max-w-2xl">
                        <div class="w-full sm:w-auto">
                            <x-input-label for="periode" value="Bulan" />
                            {{-- Gaya sama dengan filter bulan Casemix / Daftar Pasien Bulanan:
                                 diketik mm/yyyy, bukan dropdown — petugas sudah hafal formatnya
                                 dan tak perlu menggulung daftar bulan. Dikosongkan = semua periode. --}}
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input id="periode" type="text" wire:model.live.debounce.500ms="periode"
                                    class="block w-full pl-10 sm:w-40" placeholder="mm/yyyy" maxlength="7" />
                            </div>
                        </div>

                        <div class="w-full">
                            <x-input-label for="searchKeyword" value="Cari No. Log / Modul / Penyebab / Unit" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="cth: listrik padam"
                                class="block w-full mt-1" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate" :disabled="! $siapDipakai">
                            + Catat Kejadian Down Time
                        </x-primary-button>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            @if (! $siapDipakai)
                <div class="px-4 py-3 mt-4 text-sm border rounded-2xl bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                    Tabel <span class="font-mono">RSTXN_DOWNTIMES</span> belum dipasang di basis data.
                    Jalankan <span class="font-mono">docs/ddl-pelaporan-downtime.sql</span> lebih dulu.
                </div>
            @else
                {{-- REKAP PERIODE — buka-tutup, pola Laporan Permintaan Lab.
                     Default TERTUTUP; angkanya sudah terbaca di kepala tombol. --}}
                @php $rekap = $this->rekap; @endphp
                <div class="mt-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900"
                    x-data="{ open: false }">

                    <button type="button" @click="open = !open"
                        class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl hover:bg-surface-soft dark:hover:bg-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-300">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-body dark:text-gray-200">
                                Ringkasan {{ filled($periode) ? $periode : 'semua bulan' }}
                            </div>
                            <div class="text-xs text-muted dark:text-gray-400">
                                <span class="font-medium text-body dark:text-gray-300">{{ $rekap['jumlah'] }}</span> kejadian
                                &middot; total henti
                                <span class="font-medium text-blue-700 dark:text-blue-400">{{ intdiv($rekap['totalMenit'], 60) }} j {{ $rekap['totalMenit'] % 60 }} m</span>
                                &middot; tidak terencana
                                <span class="font-medium text-amber-700 dark:text-amber-400">{{ $rekap['tidakTerencana'] }}</span>
                                &middot; belum pulih
                                <span class="font-medium {{ $rekap['belumPulih'] > 0 ? 'text-red-700 dark:text-red-400' : 'text-body dark:text-gray-300' }}">{{ $rekap['belumPulih'] }}</span>
                            </div>
                        </div>
                        <span class="hidden text-xs sm:inline text-muted dark:text-gray-400"><span x-text="open ? 'Sembunyikan' : 'Lihat detail'"></span></span>
                        <svg class="w-4 h-4 transition-transform duration-200 text-muted-soft shrink-0" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-cloak x-show="open" class="px-4 pb-4 border-t border-hairline dark:border-gray-700"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0">

                        <div class="grid grid-cols-2 gap-3 mt-3 md:grid-cols-4">
                            <div class="p-3 border bg-canvas border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                                <div class="text-xs uppercase text-muted">Kejadian</div>
                                <div class="mt-1 text-2xl font-bold text-ink dark:text-gray-100">{{ $rekap['jumlah'] }}</div>
                                <div class="text-[10px] text-muted dark:text-gray-400">laporan tercatat</div>
                            </div>
                            <div class="p-3 border bg-blue-50 border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-700">
                                <div class="text-xs text-blue-700 uppercase dark:text-blue-300">Total Waktu Henti</div>
                                <div class="mt-1 text-xl font-bold text-blue-800 dark:text-blue-200">
                                    {{ intdiv($rekap['totalMenit'], 60) }} j {{ $rekap['totalMenit'] % 60 }} m
                                </div>
                                {{-- Yang durasinya belum bisa dihitung TIDAK ikut menambah
                                     total, jadi jumlahnya disebut supaya total tak terbaca
                                     sebagai angka lengkap. --}}
                                <div class="text-[10px] text-blue-600 dark:text-blue-400">
                                    {{ $rekap['tanpaDurasi'] > 0 ? $rekap['tanpaDurasi'] . ' belum terhitung' : 'seluruhnya terhitung' }}
                                </div>
                            </div>
                            <div class="p-3 border rounded-xl {{ $rekap['tidakTerencana'] > 0
                                ? 'bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-700'
                                : 'bg-slate-50 border-slate-200 dark:bg-slate-900/20 dark:border-slate-700' }}">
                                <div class="text-xs uppercase {{ $rekap['tidakTerencana'] > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-slate-700 dark:text-slate-300' }}">Tidak Terencana</div>
                                <div class="mt-1 text-2xl font-bold {{ $rekap['tidakTerencana'] > 0 ? 'text-amber-800 dark:text-amber-200' : 'text-slate-800 dark:text-slate-200' }}">
                                    {{ $rekap['tidakTerencana'] }}
                                </div>
                                <div class="text-[10px] {{ $rekap['tidakTerencana'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-600 dark:text-slate-400' }}">
                                    {{ $rekap['jumlah'] > 0 ? round($rekap['tidakTerencana'] / $rekap['jumlah'] * 100) : 0 }}% dari kejadian
                                </div>
                            </div>
                            <div class="p-3 border rounded-xl {{ $rekap['belumPulih'] > 0
                                ? 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-700'
                                : 'bg-slate-50 border-slate-200 dark:bg-slate-900/20 dark:border-slate-700' }}">
                                <div class="text-xs uppercase {{ $rekap['belumPulih'] > 0 ? 'text-red-700 dark:text-red-300' : 'text-slate-700 dark:text-slate-300' }}">Belum Pulih</div>
                                <div class="mt-1 text-2xl font-bold {{ $rekap['belumPulih'] > 0 ? 'text-red-800 dark:text-red-200' : 'text-slate-800 dark:text-slate-200' }}">
                                    {{ $rekap['belumPulih'] }}
                                </div>
                                <div class="text-[10px] {{ $rekap['belumPulih'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-600 dark:text-slate-400' }}">perlu dilengkapi</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- TABLE WRAPPER --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        {{-- Sembilan kolom memaksa tabel menggulung ke samping. Yang
                             saling menjelaskan digabung jadi satu sel bertumpuk:
                             no.log+jenis, mulai+pulih+durasi, lingkup+modul,
                             dampak+unitnya. Enam kolom, muat tanpa gulung samping. --}}
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th>No. Log / Jenis</th>
                                <th>Waktu Henti</th>
                                <th>Lingkup / Modul Terdampak</th>
                                <th>Dampak Pelayanan</th>
                                <th>Paraf</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $laporan)
                                <tr wire:key="pelaporan-downtime-{{ $laporan['downtimeNo'] }}"
                                    class="{{ $laporan['belumPulih'] ? 'bg-amber-50 dark:bg-amber-900/10' : '' }}">

                                    <td class="align-top">
                                        <div class="font-mono text-sm font-semibold text-ink dark:text-gray-200">{{ $laporan['noLog'] }}</div>
                                        <div class="mt-1">
                                            <x-badge :variant="$laporan['jenis'] === 'tidakTerencana' ? 'danger' : 'info'">{{ $laporan['jenisLabel'] }}</x-badge>
                                        </div>
                                    </td>

                                    {{-- WAKTU: mulai di atas, pulih & durasi di bawahnya --}}
                                    <td class="align-top">
                                        <div class="font-mono text-sm text-ink dark:text-gray-200">
                                            {{ filled($laporan['waktuMulai']) ? $laporan['waktuMulai'] : '-' }}
                                        </div>
                                        @if ($laporan['belumPulih'])
                                            <div class="mt-1"><x-badge variant="warning">Belum pulih</x-badge></div>
                                        @else
                                            <div class="font-mono text-xs text-muted dark:text-gray-400">
                                                &rarr; {{ $laporan['lintasHari'] ? $laporan['waktuPulih'] : $laporan['jamPulih'] }}
                                                @if (filled($laporan['durasi']))
                                                    &middot; {{ $laporan['durasi'] }}
                                                @endif
                                            </div>
                                        @endif
                                    </td>

                                    <td class="align-top">
                                        <div class="text-sm text-ink dark:text-gray-200">{{ $laporan['lingkupLabel'] }}</div>
                                        @if (filled($laporan['modulTerdampak']))
                                            <div class="text-xs text-muted dark:text-gray-400">{{ $laporan['modulTerdampak'] }}</div>
                                        @endif
                                        @if (filled($laporan['penyebab']))
                                            <div class="text-xs italic text-muted dark:text-gray-400">{{ \Illuminate\Support\Str::limit($laporan['penyebab'], 70) }}</div>
                                        @endif
                                    </td>

                                    {{-- DAMPAK: berapa unit beralih manual + unit mana saja --}}
                                    <td class="align-top">
                                        @if ($laporan['jumlahUnitManual'] > 0)
                                            <x-badge variant="warning">{{ $laporan['jumlahUnitManual'] }} unit manual</x-badge>
                                            <div class="mt-1 text-xs text-muted dark:text-gray-400">
                                                {{ \Illuminate\Support\Str::limit(implode(', ', $laporan['unitTerdampak']), 60) }}
                                            </div>
                                        @else
                                            <x-badge variant="success">Tidak ada</x-badge>
                                        @endif
                                    </td>

                                    <td class="align-top">
                                        <div class="text-sm text-ink dark:text-gray-200">{{ filled($laporan['paraf']) ? $laporan['paraf'] : '-' }}</div>
                                        @if (filled($laporan['parafTanggal']))
                                            <div class="font-mono text-xs text-muted dark:text-gray-400">{{ $laporan['parafTanggal'] }}</div>
                                        @endif
                                    </td>

                                    <td class="align-top ds-c">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit({{ $laporan['downtimeNo'] }})" />
                                            @can('downtime.pelaporanHapus')
                                                <x-action-delete :action="'requestDelete(' . $laporan['downtimeNo'] . ')'"
                                                    title="Hapus Laporan Down Time"
                                                    message="Yakin hapus laporan {{ $laporan['noLog'] }}? Seluruh isi laporan ikut hilang." />
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada laporan down time pada filter ini.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION --}}
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::downtime.pelaporan-downtime.pelaporan-downtime-actions
                wire:key="pelaporan-downtime-actions" />

        </div>
    </div>
</div>
