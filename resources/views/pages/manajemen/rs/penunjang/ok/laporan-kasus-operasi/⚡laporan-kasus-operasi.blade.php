<?php
// Laporan Kasus Operasi (OK) — padanan BAGIAN BAWAH report legacy ok002
// "Laporan OK Bulanan": rekap jumlah kasus per Dokter Operator per jenis
// tindakan operasi. Keterangan diambil dari rsmst_accdocs.accdoc_desc
// (nama tindakan sudah memuat sufiks (BP)/(UM)); 1 kasus = 1 baris
// tindakan rstxn_okacts. Transaksi batal (ok_status='F') tidak dihitung.
// Filter & model tabel mengikuti pola Casemix (daftar-rj-bulanan).

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    /** Mode periode ala Casemix. */
    public string $filterMode = 'bulanan'; // 'bulanan' | 'tahunan'

    /** Bulan laporan, format mm/yyyy (mode bulanan). */
    public string $filterBulan = '';

    /** Tahun laporan, format yyyy (mode tahunan). */
    public string $filterTahun = '';

    /** Filter layanan asal transaksi OK (hanya basis Tanggal OK). */
    public string $sumber = 'SEMUA';

    /**
     * Basis periode: 'OK' = tanggal transaksi OK; 'PULANG' = pasien RI yang
     * PULANG pada periode terpilih (exit_date, ri_status='P') — persis report
     * legacy ok002 "Laporan OK Bulanan (Pulang Inap)".
     */
    public string $basis = 'OK';

    public function mount(): void
    {
        $this->filterBulan = Carbon::now(config('app.timezone'))->format('m/Y');
        $this->filterTahun = Carbon::now(config('app.timezone'))->format('Y');
    }

    public function updatedFilterMode(): void
    {
        unset($this->rekap);
    }

    public function updatedFilterBulan(): void
    {
        unset($this->rekap);
    }

    public function updatedFilterTahun(): void
    {
        unset($this->rekap);
    }

    public function updatedSumber(): void
    {
        unset($this->rekap);
    }

    public function gantiBasis(string $basis): void
    {
        if (in_array($basis, ['OK', 'PULANG'], true)) {
            $this->basis = $basis;
            unset($this->rekap);
        }
    }

    /** Batas periode [awal, akhirEksklusif] dari filter aktif; null bila input belum valid. */
    private function batasPeriode(): ?array
    {
        if ($this->filterMode === 'tahunan') {
            if (!preg_match('/^\d{4}$/', $this->filterTahun)) {
                return null;
            }
            $awal = Carbon::createFromFormat('Y-m-d', $this->filterTahun . '-01-01')->startOfDay();
            return [$awal, $awal->copy()->addYear()];
        }

        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{4}$/', $this->filterBulan)) {
            return null;
        }
        [$bulan, $tahun] = explode('/', $this->filterBulan);
        $awal = Carbon::createFromFormat('Y-m-d', $tahun . '-' . $bulan . '-01')->startOfDay();
        return [$awal, $awal->copy()->addMonth()];
    }

    /** Label periode untuk judul rekap. */
    #[Computed]
    public function labelPeriode(): string
    {
        $batas = $this->batasPeriode();
        if (!$batas) {
            return '(periode belum valid)';
        }
        return $this->filterMode === 'tahunan'
            ? 'Tahun ' . $batas[0]->format('Y')
            : $batas[0]->locale('id')->translatedFormat('F Y');
    }

    /**
     * Rekap per dokter operator: [dr_name => ['rincian' => [{keterangan, jml}], 'subtotal' => n]].
     * Baris lama tanpa status_rjri di-backfill 'RI' (NVL) — sama dengan modul Kamar Operasi.
     */
    #[Computed]
    public function rekap(): array
    {
        $batas = $this->batasPeriode();
        if (!$batas) {
            return [];
        }
        [$awal, $akhir] = $batas;

        $query = DB::table('rstxn_oks as o')
            ->join('rstxn_okacts as t', 't.ok_reg', '=', 'o.ok_reg')
            ->leftJoin('rsmst_accdocs as a', 'a.accdoc_id', '=', 't.accdoc_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'o.dr_id')
            ->whereRaw("COALESCE(o.ok_status, 'A') <> 'F'");

        if ($this->basis === 'PULANG') {
            // Ala legacy: populasi = pasien RI yang pulang pada periode ini; seluruh
            // transaksi OK kunjungan itu ikut dihitung tanpa melihat tgl OK.
            $query
                ->whereRaw("NVL(o.status_rjri, 'RI') = 'RI'")
                ->join('rstxn_rihdrs as h', function ($join) {
                    $join->on(DB::raw('h.rihdr_no'), '=', DB::raw('NVL(o.rihdr_no, o.ref_no)'));
                })
                ->whereRaw("NVL(h.ri_status, 'I') = 'P'")
                ->whereRaw("h.exit_date >= to_date(?, 'yyyy-mm-dd') and h.exit_date < to_date(?, 'yyyy-mm-dd')", [$awal->format('Y-m-d'), $akhir->format('Y-m-d')]);
        } else {
            $query->whereRaw("o.ok_date >= to_date(?, 'yyyy-mm-dd') and o.ok_date < to_date(?, 'yyyy-mm-dd')", [$awal->format('Y-m-d'), $akhir->format('Y-m-d')]);

            if ($this->sumber !== 'SEMUA') {
                $query->whereRaw("NVL(o.status_rjri, 'RI') = ?", [$this->sumber]);
            }
        }

        $barisRekap = $query
            ->groupBy('d.dr_name', 'a.accdoc_desc')
            ->orderBy('d.dr_name')
            ->orderBy('a.accdoc_desc')
            ->select('d.dr_name', 'a.accdoc_desc', DB::raw('count(*) as jml_kasus'))
            ->get();

        return $barisRekap
            ->groupBy(fn($baris) => $baris->dr_name ?: '(Tanpa Dokter Operator)')
            ->map(fn($rincianDokter) => [
                'rincian' => $rincianDokter->map(fn($baris) => ['keterangan' => $baris->accdoc_desc ?: '(Tanpa keterangan)', 'jml' => (int) $baris->jml_kasus])->values()->all(),
                'subtotal' => $rincianDokter->sum(fn($baris) => (int) $baris->jml_kasus),
            ])
            ->all();
    }

    #[Computed]
    public function totalKasus(): int
    {
        return collect($this->rekap)->sum('subtotal');
    }
};
?>

<div>
    <x-page-title
        title="Laporan Kasus Operasi (OK)"
        subtitle="Rekap jumlah kasus per Dokter Operator per jenis tindakan operasi — padanan rekap bawah Laporan OK Bulanan legacy. Transaksi batal tidak dihitung." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR — pola Casemix (daftar-rj-bulanan) --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- MODE FILTER: Bulanan / Tahunan --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Mode" />
                        <div class="inline-flex mt-1 rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600">
                            <button type="button" wire:click="$set('filterMode', 'bulanan')"
                                class="px-3 py-1.5 text-xs font-medium transition-colors
                                    {{ $filterMode === 'bulanan' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                Bulanan
                            </button>
                            <button type="button" wire:click="$set('filterMode', 'tahunan')"
                                class="px-3 py-1.5 text-xs font-medium transition-colors border-l border-gray-300 dark:border-gray-600
                                    {{ $filterMode === 'tahunan' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                Tahunan
                            </button>
                        </div>
                    </div>

                    {{-- FILTER BULAN (mode bulanan) atau TAHUN (mode tahunan) --}}
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
                            <x-input-label value="Tahun" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input type="text" wire:model.live.debounce.500ms="filterTahun"
                                    class="block w-full pl-10 sm:w-32" placeholder="yyyy" maxlength="4" />
                            </div>
                        </div>
                    @endif

                    {{-- BASIS: Tanggal OK / Pulang Inap (legacy) --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Basis" />
                        <div class="inline-flex mt-1 rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600">
                            <button type="button" wire:click="gantiBasis('OK')"
                                class="px-3 py-1.5 text-xs font-medium transition-colors
                                    {{ $basis === 'OK' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                Tanggal OK
                            </button>
                            <button type="button" wire:click="gantiBasis('PULANG')"
                                class="px-3 py-1.5 text-xs font-medium transition-colors border-l border-gray-300 dark:border-gray-600
                                    {{ $basis === 'PULANG' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                Pulang Inap (legacy)
                            </button>
                        </div>
                    </div>

                    {{-- FILTER LAYANAN (hanya basis Tanggal OK) --}}
                    @if ($basis === 'OK')
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Layanan" />
                            <x-select-input wire:model.live="sumber" class="w-full mt-1 sm:w-36">
                                <option value="SEMUA">Semua</option>
                                <option value="RJ">Rawat Jalan</option>
                                <option value="UGD">UGD</option>
                                <option value="RI">Rawat Inap</option>
                            </x-select-input>
                        </div>
                    @else
                        <div class="w-full pb-1.5 sm:w-auto">
                            <span class="text-xs italic text-muted-soft dark:text-gray-500">Khusus Rawat Inap — pasien pulang pada periode terpilih, seluruh transaksi OK kunjungannya dihitung.</span>
                        </div>
                    @endif

                    <div class="flex items-center gap-1.5 pb-1.5 ml-auto text-sm text-muted dark:text-gray-400" wire:loading wire:target="filterMode, filterBulan, filterTahun, sumber, gantiBasis">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </div>
                </div>
            </div>

            {{-- TABLE WRAPPER — pola Casemix (kartu lebar penuh) --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-6 py-3 w-80 border-r border-hairline dark:border-gray-700">Dokter Operator</th>
                                <th class="px-6 py-3">Keterangan</th>
                                <th class="px-6 py-3 text-center w-36">Jml Kasus</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline dark:divide-gray-700">
                            @forelse ($this->rekap as $dokter => $rekapDokter)
                                @foreach ($rekapDokter['rincian'] as $indexRincian => $rincian)
                                    <tr class="transition hover:bg-surface-soft dark:hover:bg-gray-800/50"
                                        wire:key="rekap-ok-{{ $filterMode }}-{{ $basis }}-{{ $loop->parent->index }}-{{ $indexRincian }}">
                                        @if ($indexRincian === 0)
                                            <td rowspan="{{ count($rekapDokter['rincian']) + 1 }}" class="px-6 py-4 font-semibold align-top text-ink border-r border-hairline dark:text-gray-100 dark:border-gray-700">{{ $dokter }}</td>
                                        @endif
                                        <td class="px-6 py-4 text-body dark:text-gray-300">{{ $rincian['keterangan'] }}</td>
                                        <td class="px-6 py-4 text-center text-body dark:text-gray-300">{{ number_format($rincian['jml'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-surface-soft/60 dark:bg-gray-800/40" wire:key="rekap-ok-sub-{{ $filterMode }}-{{ $basis }}-{{ $loop->index }}">
                                    <td class="px-6 py-3 text-right font-semibold text-ink dark:text-gray-200">Subtotal</td>
                                    <td class="px-6 py-3 text-center font-semibold text-ink dark:text-gray-200">{{ number_format($rekapDokter['subtotal'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-24">
                                        <div class="flex flex-col items-center gap-3 text-muted dark:text-gray-400">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-5l-2 3h-2l-2-3H4" />
                                            </svg>
                                            <span class="text-sm font-medium">Belum ada data</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (count($this->rekap) > 0)
                            <tfoot>
                                <tr class="bg-surface-card dark:bg-gray-800">
                                    <td colspan="2" class="px-6 py-3 text-right font-bold text-ink dark:text-gray-100">Total Kasus</td>
                                    <td class="px-6 py-3 text-center font-bold text-ink dark:text-gray-100">{{ number_format($this->totalKasus, 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                {{-- FOOTER BAR — ala bar paginasi Casemix: periode + total + catatan --}}
                <div class="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-2 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    <span class="text-sm font-semibold text-ink dark:text-gray-200">
                        Rekap Kasus per Dokter Operator — {{ $this->labelPeriode }}
                    </span>
                    <span class="text-xs text-muted-soft dark:text-gray-500"
                        title="1 kasus = 1 baris tindakan operasi; transaksi OK yang dibatalkan tidak dihitung (report legacy ok002 ikut menghitung batal).">
                        1 kasus = 1 baris tindakan &middot; transaksi batal tidak dihitung
                    </span>
                    <x-badge variant="info">Total {{ number_format($this->totalKasus, 0, ',', '.') }} kasus</x-badge>
                </div>
            </div>

        </div>
    </div>
</div>
