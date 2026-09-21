<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Rs\Ri\KunjunganRITrait;

new class extends Component {
    use KunjunganRITrait;

    public int $tahunFrom;
    public int $tahunTo;
    public int $kapasitasTT = 0;        // TT total yang dipakai — bawaan = Σ TT bangsal yang dihitung, bisa ditimpa manual
    public int $defaultKapasitasTT = 0; // Σ TT bangsal yang dihitung (lihat $parameterBangsal di trait)

    public function mount(): void
    {
        $now = Carbon::now()->year;
        $this->tahunFrom = $now - 4;
        $this->tahunTo   = $now;
        // TT per bangsal dari master + setelan tersimpan di sesi; TT total = Σ bangsal yang dihitung.
        $this->muatParameterBangsal();
    }

    public function resetKapasitasTT(): void
    {
        $this->kapasitasTT = $this->defaultKapasitasTT;
    }

    private function periodeRange(): array
    {
        $from = min($this->tahunFrom, $this->tahunTo);
        $to   = max($this->tahunFrom, $this->tahunTo);
        $start = Carbon::create($from, 1, 1)->startOfYear();
        $end = Carbon::create($to, 12, 31)->endOfYear();
        return [$start, $end];
    }

    #[Computed]
    public function rows(): array
    {
        [$start, $end] = $this->periodeRange();
        $aggregate = $this->buildKunjunganRIAggregate($start, $end, "to_char(h.exit_date, 'YYYY')");

        $result = [];
        $from = min($this->tahunFrom, $this->tahunTo);
        $to   = max($this->tahunFrom, $this->tahunTo);
        for ($y = $from; $y <= $to; $y++) {
            $key = (string) $y;
            $result[] = $this->fillKunjunganRow($aggregate->get($key), $key, $key);
        }

        // Enrich dengan BOR/BTO/TOI — pembaginya hari yang SUDAH berjalan di tiap tahun
        // (tahun berjalan = s/d hari ini, tahun mendatang = 0), bukan 365/366 penuh.
        return $this->enrichWithBORTOIBTO($result, $this->kapasitasTT, function ($row) {
            $awalTahun = Carbon::create((int) $row['periode_short'], 1, 1);
            return $this->hariPeriodeBerjalan($awalTahun, $awalTahun->copy()->endOfYear());
        });
    }

    #[Computed]
    public function totals(): array
    {
        $base = $this->totalsKunjungan($this->rows);
        $extra = $this->totalBORTOIBTO($this->rows, $this->kapasitasTT);
        return array_merge($base, $extra);
    }

    #[Computed]
    public function pasienUnikGlobal(): int
    {
        [$start, $end] = $this->periodeRange();
        return $this->pasienUnikGlobalRI($start, $end);
    }

    #[Computed]
    public function bangsalBreakdown(): array
    {
        [$start, $end] = $this->periodeRange();
        $rows = $this->bangsalBreakdownRI($start, $end);
        return $this->enrichBangsalIndicators($rows, $this->hariPeriodeBerjalan($start, $end));
    }

    #[Computed]
    public function anomaliData(): array
    {
        [$start, $end] = $this->periodeRange();
        return $this->anomaliDataRI($start, $end);
    }

    /** Panjang kalender penuh rentang tahun terpilih — pembanding hari yang sudah berjalan. */
    public function totalDaysInRange(): int
    {
        $from = min($this->tahunFrom, $this->tahunTo);
        $to   = max($this->tahunFrom, $this->tahunTo);
        $days = 0;
        for ($y = $from; $y <= $to; $y++) {
            $days += Carbon::createFromDate($y, 1, 1)->isLeapYear() ? 366 : 365;
        }
        return $days;
    }

    #[Computed]
    public function chartData(): array
    {
        return $this->chartDataKunjungan($this->rows);
    }
};
?>

<div>
    @php
        $tot = $this->totals;
        $tahunMin = min($tahunFrom, $tahunTo);
        $tahunMax = max($tahunFrom, $tahunTo);
        $chartKey = md5("ri-tahunan-{$tahunFrom}-{$tahunTo}");
    @endphp

    {{-- FILTER + SUMMARY CARDS — collapsible, default closed --}}
    <div class="mt-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900"
        x-data="{ open: false }">

        <button type="button" @click="open = !open"
            class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl
                   hover:bg-surface-soft dark:hover:bg-gray-800
                   focus:outline-none focus:ring-1 focus:ring-gray-300">
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-body dark:text-gray-200">
                    Ringkasan Kunjungan RI {{ $tahunMin }}&ndash;{{ $tahunMax }}
                    <span class="font-normal text-xs text-muted">({{ $tahunMax - $tahunMin + 1 }} tahun)</span>
                </div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Pulang <span class="font-medium text-body dark:text-gray-300">{{ number_format($tot['total']) }}</span>
                    · BPJS <span class="font-medium text-emerald-700 dark:text-emerald-400">{{ number_format($tot['bpjs']) }}</span>
                    · UMUM <span class="font-medium text-amber-700 dark:text-amber-400">{{ number_format($tot['umum']) }}</span>
                    · BOR <span class="font-medium text-purple-700 dark:text-purple-400">{{ $tot['bor'] !== null ? $tot['bor'] . '%' : '—' }}</span>
                    · ALOS <span class="font-medium text-purple-700 dark:text-purple-400">{{ $tot['alos'] }} hr</span>
                    · TOI <span class="font-medium text-purple-700 dark:text-purple-400">{{ $tot['toi'] !== null ? $tot['toi'] . ' hr' : '—' }}</span>
                    · BTO <span class="font-medium text-purple-700 dark:text-purple-400">{{ $tot['bto'] !== null ? $tot['bto'] . 'x' : '—' }}</span>
                </div>
            </div>
            <span class="hidden sm:inline text-xs text-muted dark:text-gray-400">
                <span x-text="open ? 'Sembunyikan' : 'Lihat detail'"></span>
            </span>
            <svg class="w-4 h-4 text-muted-soft transition-transform duration-200 shrink-0"
                :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-cloak x-show="open"
            class="px-4 pb-4 border-t border-hairline dark:border-gray-700"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0">

            {{-- Counts row --}}
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
                <div class="p-3 bg-brand-green/5 border border-brand-green/30 rounded-xl dark:border-brand-lime/30 dark:bg-brand-lime/5">
                    <div class="text-xs text-brand-green uppercase dark:text-brand-lime">Rentang Tahun</div>
                    <div class="mt-1 flex items-center gap-1">
                        <x-text-input type="number" wire:model.live.debounce.500ms="tahunFrom"
                            min="2000" max="2099" maxlength="4"
                            class="block w-full !text-base !font-bold !py-0.5 !px-2" />
                        <span class="text-muted-soft text-sm">&ndash;</span>
                        <x-text-input type="number" wire:model.live.debounce.500ms="tahunTo"
                            min="2000" max="2099" maxlength="4"
                            class="block w-full !text-base !font-bold !py-0.5 !px-2" />
                    </div>
                    <div class="mt-0.5 text-[10px] text-muted dark:text-gray-400 truncate"
                        title="{{ $tahunMin }}–{{ $tahunMax }} ({{ $tahunMax - $tahunMin + 1 }} tahun)">
                        {{ $tahunMin }}&ndash;{{ $tahunMax }} ({{ $tahunMax - $tahunMin + 1 }} thn)
                    </div>
                </div>
                <div class="p-3 bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs text-muted uppercase">Total Pulang</div>
                    <div class="mt-1 text-2xl font-bold text-ink dark:text-gray-100">{{ number_format($tot['total']) }}</div>
                    <div class="text-[10px] text-muted">{{ number_format($this->pasienUnikGlobal) }} pasien unik</div>
                </div>
                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl dark:bg-emerald-900/20 dark:border-emerald-700">
                    <div class="text-xs text-emerald-700 uppercase dark:text-emerald-300">BPJS</div>
                    <div class="mt-1 text-2xl font-bold text-emerald-800 dark:text-emerald-200">{{ number_format($tot['bpjs']) }}</div>
                    <div class="text-[10px] text-emerald-600 dark:text-emerald-400">{{ $tot['total'] > 0 ? round($tot['bpjs'] / $tot['total'] * 100) : 0 }}%</div>
                </div>
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-700">
                    <div class="text-xs text-amber-700 uppercase dark:text-amber-300">UMUM</div>
                    <div class="mt-1 text-2xl font-bold text-amber-800 dark:text-amber-200">{{ number_format($tot['umum']) }}</div>
                    <div class="text-[10px] text-amber-600 dark:text-amber-400">{{ $tot['total'] > 0 ? round($tot['umum'] / $tot['total'] * 100) : 0 }}%</div>
                </div>
                <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-700">
                    <div class="flex items-baseline justify-between">
                        <div class="text-xs text-blue-700 uppercase dark:text-blue-300">Kapasitas TT</div>
                        @if ($kapasitasTT !== $defaultKapasitasTT)
                            <button type="button" wire:click="resetKapasitasTT"
                                class="text-[9px] text-blue-600 hover:underline dark:text-blue-400" title="Kembali ke Σ TT bangsal yang dihitung">
                                reset
                            </button>
                        @endif
                    </div>
                    <x-text-input type="number" wire:model.live.debounce.500ms="kapasitasTT"
                        min="1" max="9999"
                        class="mt-1 block w-full !text-xl !font-bold !py-0.5 !text-blue-800 dark:!text-blue-200" />
                    <div class="mt-0.5 text-[10px] text-blue-600 dark:text-blue-400 truncate"
                        title="Bawaan = jumlah TT bangsal yang dihitung BOR ({{ $defaultKapasitasTT }} bed). Atur per bangsal di kartu Parameter TT per Bangsal.">
                        Σ bangsal dihitung: {{ $defaultKapasitasTT }} bed
                    </div>
                </div>
            </div>

            {{-- Indikator RI (Kemenkes) row --}}
            <div class="mt-3">
                <div class="mb-2 text-[11px] font-semibold tracking-wider text-muted uppercase dark:text-gray-400">
                    Indikator Pelayanan RI (standar Kemenkes)
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {{-- BOR --}}
                    <div class="p-3 bg-purple-50 border border-purple-200 rounded-xl dark:bg-purple-900/20 dark:border-purple-700">
                        <div class="flex items-baseline justify-between">
                            <div class="text-xs text-purple-700 uppercase dark:text-purple-300 font-bold">BOR</div>
                            <div class="text-[10px] text-purple-600 dark:text-purple-400">ideal 60&ndash;85%</div>
                        </div>
                        <div class="mt-1 text-2xl font-bold text-purple-800 dark:text-purple-200">{{ $tot['bor'] ?? '—' }}<span class="text-sm font-medium">%</span></div>
                        <div class="mt-2 pt-2 border-t border-purple-200 dark:border-purple-700/50">
                            <div class="text-[11px] font-semibold text-purple-700 dark:text-purple-300">Bed Occupancy Rate</div>
                            <div class="text-[10px] text-muted dark:text-gray-400 leading-snug mt-0.5">
                                Persentase pemakaian TT dalam periode. <strong>Rendah</strong> = bed banyak kosong (potensi kerugian), <strong>tinggi</strong> = sering penuh (perlu tambah TT).
                            </div>
                        </div>
                    </div>

                    {{-- ALOS --}}
                    <div class="p-3 bg-purple-50 border border-purple-200 rounded-xl dark:bg-purple-900/20 dark:border-purple-700">
                        <div class="flex items-baseline justify-between">
                            <div class="text-xs text-purple-700 uppercase dark:text-purple-300 font-bold">ALOS</div>
                            <div class="text-[10px] text-purple-600 dark:text-purple-400">ideal 6&ndash;9 hari</div>
                        </div>
                        <div class="mt-1 text-2xl font-bold text-purple-800 dark:text-purple-200">{{ $tot['alos'] }}<span class="text-sm font-medium"> hari</span></div>
                        <div class="mt-2 pt-2 border-t border-purple-200 dark:border-purple-700/50">
                            <div class="text-[11px] font-semibold text-purple-700 dark:text-purple-300">Average Length of Stay</div>
                            <div class="text-[10px] text-muted dark:text-gray-400 leading-snug mt-0.5">
                                Rata-rata hari rawat per pasien. <strong>Pendek</strong> bisa berarti pulih cepat atau pulang terlalu dini, <strong>panjang</strong> bisa indikasi mutu pelayanan kurang.
                            </div>
                        </div>
                    </div>

                    {{-- TOI --}}
                    <div class="p-3 bg-purple-50 border border-purple-200 rounded-xl dark:bg-purple-900/20 dark:border-purple-700">
                        <div class="flex items-baseline justify-between">
                            <div class="text-xs text-purple-700 uppercase dark:text-purple-300 font-bold">TOI</div>
                            <div class="text-[10px] text-purple-600 dark:text-purple-400">ideal 1&ndash;3 hari</div>
                        </div>
                        <div class="mt-1 text-2xl font-bold text-purple-800 dark:text-purple-200">
                            {{ $tot['toi'] !== null ? $tot['toi'] : '—' }}<span class="text-sm font-medium"> hari</span>
                        </div>
                        <div class="mt-2 pt-2 border-t border-purple-200 dark:border-purple-700/50">
                            <div class="text-[11px] font-semibold text-purple-700 dark:text-purple-300">Turn Over Interval</div>
                            <div class="text-[10px] text-muted dark:text-gray-400 leading-snug mt-0.5">
                                Interval rata-rata bed kosong antar pasien. <strong>Tinggi</strong> = bed sering tidak dipakai, <strong>rendah</strong> = bed cepat diisi pasien baru (efisien).
                            </div>
                        </div>
                    </div>

                    {{-- BTO --}}
                    <div class="p-3 bg-purple-50 border border-purple-200 rounded-xl dark:bg-purple-900/20 dark:border-purple-700">
                        <div class="flex items-baseline justify-between">
                            <div class="text-xs text-purple-700 uppercase dark:text-purple-300 font-bold">BTO</div>
                            <div class="text-[10px] text-purple-600 dark:text-purple-400">ideal 40&ndash;50/tahun</div>
                        </div>
                        <div class="mt-1 text-2xl font-bold text-purple-800 dark:text-purple-200">{{ $tot['bto'] ?? '—' }}<span class="text-sm font-medium">x</span></div>
                        <div class="mt-2 pt-2 border-t border-purple-200 dark:border-purple-700/50">
                            <div class="text-[11px] font-semibold text-purple-700 dark:text-purple-300">Bed Turn Over</div>
                            <div class="text-[10px] text-muted dark:text-gray-400 leading-snug mt-0.5">
                                Frekuensi pemakaian 1 bed dalam periode. <strong>Tinggi</strong> = bed produktif (banyak pasien), <strong>rendah</strong> = bed jarang dipakai.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- PARAMETER TT PER BANGSAL — TT bisa diubah & bangsal bisa dikeluarkan dari BOR (bed bayi, UGD) --}}
    <x-kunjungan-ri.parameter-tt :rows="$parameterBangsal" :ttDihitung="$this->ttBangsalDihitung()"
        :diubah="$this->parameterBangsalDiubah()" />

    {{-- TABEL PERIODE + RUMUS — komponen hitungan ditampilkan supaya angka janggal bisa dilacak --}}
    <x-kunjungan-ri.tabel-periode :rows="$this->rows" :totals="$tot" :pasienUnikGlobal="$this->pasienUnikGlobal"
        labelPeriode="Tahun" :kapasitasTT="$kapasitasTT" :defaultKapasitasTT="$defaultKapasitasTT" />

    <x-kunjungan-ri.rumus :totals="$tot" :kapasitasTT="$kapasitasTT" :hariKalender="$this->totalDaysInRange()" />

    {{-- TREN CHART --}}
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                Tren Kunjungan
                <span class="ml-2 font-normal text-xs text-muted">(per tahun, {{ $tahunMin }}&ndash;{{ $tahunMax }})</span>
            </h3>
        </div>
        <div class="p-4" wire:ignore wire:key="chart-{{ $chartKey }}">
            <div class="relative h-72" x-data="chartKunjunganRI(@js($this->chartData))">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
    </div>

    {{-- BREAKDOWN PER BANGSAL --}}
    <x-kunjungan-ri.tabel-bangsal :rows="$this->bangsalBreakdown" :hariPeriode="$tot['days_total']"
        keteranganPeriode="{{ $tahunMin }}–{{ $tahunMax }}" />

    {{-- PEMERIKSAAN DATA — hitungan data janggal + uji silang jumlah --}}
    <x-kunjungan-ri.anomali :anomali="$this->anomaliData" :totals="$tot" :bangsal="$this->bangsalBreakdown"
        :kapasitasTT="$kapasitasTT" :ttBangsalDihitung="$this->ttBangsalDihitung()"
        :batasLos="$this->batasLos()" :batasLosBawaan="self::BATAS_LOS_BAWAAN" />
</div>
