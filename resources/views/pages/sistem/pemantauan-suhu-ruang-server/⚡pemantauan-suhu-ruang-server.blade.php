<?php

/**
 * Pemantauan Suhu Ruang Server — LIST (Akreditasi MRMIK 2.2, Perlindungan Data).
 *
 * SATU BARIS = SATU PENGUKURAN suhu, bukan lembar bulanan. Formulir resminya
 * dirakit saat cetak: kop dari SuhuRuangServerOptions + pengukuran bulan terpilih
 * + garis tanda tangan kosong. Rancangan: docs/ddl-pemantauan-suhu-ruang-server.sql.
 *
 * Layar ini TIDAK menulis apa pun ke basis data — ia hanya membaca & mengirim
 * event ke -actions, sesuai kontrak docs/standar-master-module.md §2.
 */

use App\Http\Traits\Sistem\PemantauanRuangServer\PemantauanSuhuTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use PemantauanSuhuTrait, WithPagination;

    /** 'MM/YYYY'; kosong = semua bulan. */
    public string $periode = '';

    public string $searchKeyword = '';

    public int $itemsPerPage = 15;

    /** Tabel sudah dipasang di basis data? */
    public bool $siapDipakai = false;

    public function mount(): void
    {
        $this->siapDipakai = $this->checkTabelSuhu();
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
        $this->itemsPerPage = 15;
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->dispatch('pemantauan-suhu-ruang-server.openCreate');
    }

    public function openEdit(int $suhuNo): void
    {
        $this->dispatch('pemantauan-suhu-ruang-server.openEdit', suhuNo: $suhuNo);
    }

    public function requestDelete(int $suhuNo): void
    {
        $this->dispatch('pemantauan-suhu-ruang-server.requestDelete', suhuNo: $suhuNo);
    }

    #[On('pemantauan-suhu-ruang-server.saved')]
    public function refreshAfterSaved(): void
    {
        // #[Computed] di-cache satu request; tanpa unset() daftar lama masih
        // terpakai setelah pengukuran disimpan dan layar tampak tidak berubah.
        unset($this->catatanList, $this->rows, $this->rekap);
        $this->resetPage();
    }

    /** Seluruh pengukuran yang lolos filter. */
    #[Computed]
    public function catatanList(): array
    {
        if (! $this->siapDipakai) {
            return [];
        }

        [$daftar] = $this->findRiwayatSuhu(
            filled($this->periode) ? $this->periode : null,
            $this->searchKeyword
        );

        return $daftar;
    }

    /** Ringkasan bulan terpilih — bahan cepat sebelum mencetak. */
    #[Computed]
    public function rekap(): array
    {
        return $this->rekapSuhu($this->catatanList);
    }

    /**
     * Paginasi dirakit sendiri: barisnya berasal dari CLOB yang di-decode di PHP,
     * bukan dari query yang bisa di-LIMIT.
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $semua = $this->catatanList;
        $halaman = Paginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            array_slice($semua, ($halaman - 1) * $this->itemsPerPage, $this->itemsPerPage),
            count($semua),
            $this->itemsPerPage,
            $halaman,
            ['path' => request()->url()],
        );
    }

    /**
     * Cetak formulir bulanan: kop tetap dari Options + seluruh pengukuran bulan
     * terpilih, urut kronologis (kebalikan layar, yang menaruh terbaru di atas).
     */
    public function cetak()
    {
        if (blank($this->periode)) {
            $this->dispatch('toast', type: 'error', message: 'Pilih bulan yang mau dicetak lebih dulu.');

            return null;
        }

        $catatanList = array_reverse($this->catatanList);

        if ($catatanList === []) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada pengukuran pada bulan ini.');

            return null;
        }

        try {
            set_time_limit(300);

            $pdf = Pdf::loadView(
                'pages.components.sistem.pemantauan-suhu-ruang-server.cetak-pemantauan-suhu-ruang-server-print',
                ['data' => [
                    'periode' => $this->periode,
                    'periodeLabel' => Carbon::createFromFormat('m/Y', $this->periode)->translatedFormat('F Y'),
                    'catatanList' => $catatanList,
                    'rekap' => $this->rekapSuhu($catatanList),
                    'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
                ]]
            )->setPaper('A4', 'portrait');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak formulir pemantauan suhu.');

            return response()->streamDownload(
                fn () => print $pdf->output(),
                'pemantauan-suhu-ruang-server-' . str_replace('/', '-', $this->periode) . '.pdf'
            );
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $exception->getMessage());

            return null;
        }
    }
};
?>

<div>

    <x-page-title
        title="Pemantauan Suhu Ruang Server"
        subtitle="Formulir MRMIK 2.2 — catat suhu & status AC tiap pengukuran, cetak rekapnya per bulan" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    <div class="flex flex-col w-full gap-3 sm:flex-row sm:items-end lg:max-w-2xl">
                        <div class="w-full sm:w-auto">
                            <x-input-label for="periode" value="Bulan" />
                            {{-- Gaya sama dengan filter bulan Casemix / Daftar Pasien Bulanan:
                                 diketik mm/yyyy. Dikosongkan = semua bulan. --}}
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
                            <x-input-label for="searchKeyword" value="Cari Status AC / Tindak Lanjut / Paraf" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="cth: AC mati"
                                class="block w-full mt-1" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="30">30</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate" :disabled="! $siapDipakai">
                            + Catat Suhu
                        </x-primary-button>
                        @if ($siapDipakai && $this->catatanList !== [])
                            <x-outline-button type="button" wire:click="cetak" wire:loading.attr="disabled" wire:target="cetak"
                               
                                title="Cetak formulir bulan ini">
                                <span wire:loading.remove wire:target="cetak">Cetak Bulan Ini</span>
                                <span wire:loading wire:target="cetak" class="flex items-center gap-1"><x-loading class="w-4 h-4" /> Mencetak...</span>
                            </x-outline-button>
                        @endif
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            @if (! $siapDipakai)
                <div class="px-4 py-3 mt-4 text-sm border rounded-2xl bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                    Tabel <span class="font-mono">RSTXN_SUHUSERVERS</span> belum dipasang di basis data.
                    Jalankan <span class="font-mono">docs/ddl-pemantauan-suhu-ruang-server.sql</span> lebih dulu.
                </div>
            @else
                {{-- REKAP BULAN — buka-tutup, mengikuti pola Laporan Permintaan Lab
                     (manajemen/rs/penunjang/lab): ringkasannya sudah terbaca di
                     kepala tombol, rinciannya baru dibuka saat diperlukan. Default
                     TERTUTUP supaya tinggi layar tetap jadi milik tabel pengukuran. --}}
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
                                <span class="font-medium text-body dark:text-gray-300">{{ $rekap['jumlah'] }}</span> pengukuran
                                &middot; rentang
                                <span class="font-medium text-blue-700 dark:text-blue-400">{{ $rekap['suhuMin'] === null ? '-' : $rekap['suhuMin'] . '–' . $rekap['suhuMax'] . ' °C' }}</span>
                                &middot; rata-rata
                                <span class="font-medium text-emerald-700 dark:text-emerald-400">{{ $rekap['suhuRata'] === null ? '-' : $rekap['suhuRata'] . ' °C' }}</span>
                                &middot; tidak normal
                                <span class="font-medium {{ $rekap['tidakNormal'] > 0 ? 'text-red-700 dark:text-red-400' : 'text-body dark:text-gray-300' }}">{{ $rekap['tidakNormal'] }}</span>
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
                                <div class="text-xs uppercase text-muted">Pengukuran</div>
                                <div class="mt-1 text-2xl font-bold text-ink dark:text-gray-100">{{ $rekap['jumlah'] }}</div>
                                <div class="text-[10px] text-muted dark:text-gray-400">baris tercatat</div>
                            </div>
                            <div class="p-3 border bg-blue-50 border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-700">
                                <div class="text-xs text-blue-700 uppercase dark:text-blue-300">Rentang Suhu Ruang</div>
                                <div class="mt-1 text-xl font-bold text-blue-800 dark:text-blue-200">
                                    {{ $rekap['suhuMin'] === null ? '-' : $rekap['suhuMin'] . ' – ' . $rekap['suhuMax'] . ' °C' }}
                                </div>
                                <div class="text-[10px] text-blue-600 dark:text-blue-400">terendah &ndash; tertinggi</div>
                            </div>
                            <div class="p-3 border bg-emerald-50 border-emerald-200 rounded-xl dark:bg-emerald-900/20 dark:border-emerald-700">
                                <div class="text-xs uppercase text-emerald-700 dark:text-emerald-300">Rata-rata Ruang</div>
                                <div class="mt-1 text-2xl font-bold text-emerald-800 dark:text-emerald-200">
                                    {{ $rekap['suhuRata'] === null ? '-' : $rekap['suhuRata'] . ' °C' }}
                                </div>
                                <div class="text-[10px] text-emerald-600 dark:text-emerald-400">
                                    standar {{ \App\Support\Options\SuhuRuangServerOptions::SUHU_MIN_DEFAULT }}&ndash;{{ \App\Support\Options\SuhuRuangServerOptions::SUHU_MAX_DEFAULT }} °C
                                    &middot; AC rata-rata {{ ($rekap['suhuAcRata'] ?? null) === null ? '-' : $rekap['suhuAcRata'] . ' °C' }}
                                </div>
                            </div>
                            {{-- Merah hanya kalau memang ada yang di luar rentang — kartu
                                 merah permanen membuat mata berhenti membacanya. --}}
                            <div class="p-3 border rounded-xl {{ $rekap['tidakNormal'] > 0
                                ? 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-700'
                                : 'bg-slate-50 border-slate-200 dark:bg-slate-900/20 dark:border-slate-700' }}">
                                <div class="text-xs uppercase {{ $rekap['tidakNormal'] > 0 ? 'text-red-700 dark:text-red-300' : 'text-slate-700 dark:text-slate-300' }}">Tidak Normal</div>
                                <div class="mt-1 text-2xl font-bold {{ $rekap['tidakNormal'] > 0 ? 'text-red-800 dark:text-red-200' : 'text-slate-800 dark:text-slate-200' }}">
                                    {{ $rekap['tidakNormal'] }}
                                </div>
                                <div class="text-[10px] {{ $rekap['tidakNormal'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-600 dark:text-slate-400' }}">
                                    {{ $rekap['jumlah'] > 0 ? round($rekap['tidakNormal'] / $rekap['jumlah'] * 100) : 0 }}% dari pengukuran
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- TABLE WRAPPER --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th>Waktu Pemantauan</th>
                                <th class="ds-c">Suhu AC (°C)</th>
                                <th class="ds-c">Suhu Ruang (°C)</th>
                                <th>Status AC</th>
                                <th class="ds-c">Kondisi</th>
                                <th>Tindak Lanjut</th>
                                <th>Paraf</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $catatan)
                                <tr wire:key="suhu-{{ $catatan['suhuNo'] }}">
                                    <td class="ds-td-token">{{ filled($catatan['waktu']) ? $catatan['waktu'] : '-' }}</td>
                                    {{-- filled(), bukan ?: — suhu 0 °C nilai yang sah.
                                         Suhu AC kosong pada record lama: angkanya memang
                                         belum pernah diukur, jadi tampil '-' bukan ditebak. --}}
                                    <td class="ds-c">{{ filled($catatan['suhuAc']) ? $catatan['suhuAc'] : '-' }}</td>
                                    <td class="ds-c ds-td-strong">{{ filled($catatan['suhuRuang']) ? $catatan['suhuRuang'] : '-' }}</td>
                                    <td>{{ $catatan['statusAcLabel'] }}</td>
                                    <td class="ds-c">
                                        @if ($catatan['kondisi'] === 'TN')
                                            <x-badge variant="danger">TN</x-badge>
                                        @else
                                            <x-badge variant="success">N</x-badge>
                                        @endif
                                    </td>
                                    <td>{{ filled($catatan['tindakLanjut']) ? $catatan['tindakLanjut'] : '-' }}</td>
                                    <td>
                                        {{ filled($catatan['paraf']) ? $catatan['paraf'] : '-' }}
                                        @if (filled($catatan['parafTanggal']))
                                            <div class="font-mono text-xs text-muted dark:text-gray-400">{{ $catatan['parafTanggal'] }}</div>
                                        @endif
                                    </td>
                                    <td class="ds-c">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit({{ $catatan['suhuNo'] }})" />
                                            @can('sistem.pemantauanRuangServer.hapus')
                                                <x-action-delete :action="'requestDelete(' . $catatan['suhuNo'] . ')'"
                                                    title="Hapus Pengukuran"
                                                    message="Yakin hapus pengukuran {{ $catatan['waktu'] }}?" />
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v10.55A4 4 0 1015 17V3H9z" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada pengukuran pada filter ini.</p>
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

            <livewire:pages::sistem.pemantauan-suhu-ruang-server.pemantauan-suhu-ruang-server-actions
                wire:key="pemantauan-suhu-ruang-server-actions" />

        </div>
    </div>
</div>
