<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Support\Keuangan\SaldoKas;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $accId       = '';
    public string $accDesc     = '';
    public string $accDkStatus = 'D';

    /** Mode tampilan: 'harian' (satu tanggal) | 'shift' (satu tanggal, dipotong per shift) | 'bulanan' (satu bulan penuh) */
    public string $mode = 'harian';

    /** Format internal: 'YYYY-MM' (mode bulanan) */
    public string $periode = '';
    /** Format input user: 'MM/YYYY' */
    public string $periodeInput = '';

    /** Tanggal internal mode harian/shift: 'YYYY-MM-DD' */
    public string $tanggalHarian = '';
    /** Input user (ketik manual, tiru daftar RJ): 'DD/MM/YYYY' */
    public string $tanggalHarianInput = '';

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /** Mode berbasis satu tanggal (harian & per-shift), lawan dari bulanan. */
    private function isModeHarian(): bool
    {
        return in_array($this->mode, ['harian', 'shift'], true);
    }

    #[Computed]
    public function dariTanggal(): string
    {
        if ($this->isModeHarian()) {
            return $this->tanggalHarian;
        }
        return $this->periode === '' ? '' : $this->periode . '-01';
    }

    #[Computed]
    public function sampaiTanggal(): string
    {
        if ($this->isModeHarian()) {
            return $this->tanggalHarian;
        }
        if ($this->periode === '') return '';
        return Carbon::parse($this->periode . '-01')->endOfMonth()->toDateString();
    }

    public function setMode(string $mode): void
    {
        if (!in_array($mode, ['harian', 'shift', 'bulanan'], true)) return;
        $this->mode = $mode;
        // Pindah ke mode harian/shift tanpa tanggal → default ke hari terakhir periode terpilih.
        if ($this->isModeHarian() && $this->tanggalHarian === '' && $this->periode !== '') {
            $this->setTanggalHarian(Carbon::parse($this->periode . '-01')->endOfMonth()->toDateString());
        }
    }

    public function prevMonth(): void
    {
        if ($this->periode === '') return;
        $this->setPeriode(Carbon::parse($this->periode . '-01')->subMonth()->format('Y-m'));
    }

    public function nextMonth(): void
    {
        if ($this->periode === '') return;
        $this->setPeriode(Carbon::parse($this->periode . '-01')->addMonth()->format('Y-m'));
    }

    public function prevDay(): void
    {
        if ($this->tanggalHarian === '') return;
        $this->setTanggalHarian(Carbon::parse($this->tanggalHarian)->subDay()->toDateString());
    }

    public function nextDay(): void
    {
        if ($this->tanggalHarian === '') return;
        $this->setTanggalHarian(Carbon::parse($this->tanggalHarian)->addDay()->toDateString());
    }

    /**
     * User mengubah text "MM/YYYY" → parse → set internal YYYY-MM.
     * Kalau format invalid, biarin saja (tabel jadi kosong sampai dikoreksi).
     */
    public function updatedPeriodeInput(string $value): void
    {
        $value = trim($value);
        if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{4})$/', $value, $m)) {
            $this->periode = '';
            return;
        }
        [$_, $bulan, $tahun] = $m;
        $this->periode = "{$tahun}-{$bulan}";
    }

    /**
     * User ketik manual "DD/MM/YYYY" (tiru daftar RJ) → parse ke internal YYYY-MM-DD.
     * Format belum lengkap/invalid → biarkan (tabel pakai tanggal valid terakhir).
     */
    public function updatedTanggalHarianInput(string $value): void
    {
        $value = trim($value);
        if (!preg_match('#^\d{2}/\d{2}/\d{4}$#', $value)) {
            return;
        }
        try {
            $tgl = Carbon::createFromFormat('d/m/Y', $value)->startOfDay();
        } catch (\Throwable) {
            return;
        }
        // tolak overflow (mis. 32/01/2026 → Carbon normalisasi jadi 01/02)
        if ($tgl->format('d/m/Y') !== $value) {
            return;
        }
        $this->tanggalHarian = $tgl->format('Y-m-d');
        $this->setPeriode(substr($this->tanggalHarian, 0, 7));
    }

    private function setPeriode(string $ym): void
    {
        $this->periode = $ym;
        $this->periodeInput = Carbon::parse($ym . '-01')->format('m/Y');
    }

    private function setTanggalHarian(string $ymd): void
    {
        $this->tanggalHarian = $ymd;
        $this->tanggalHarianInput = Carbon::parse($ymd)->format('d/m/Y');
        $this->setPeriode(substr($ymd, 0, 7));
    }

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('keuangan.saldo-kas.openHistory')]
    public function openHistory(string $accId, string $tanggal): void
    {
        $row = DB::table('acmst_accounts')
            ->select('acc_id', 'acc_name', 'acc_dk_status')
            ->where('acc_id', $accId)
            ->first();

        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas tidak ditemukan.');
            return;
        }

        $this->accId       = (string) $row->acc_id;
        $this->accDesc     = (string) ($row->acc_name ?? '');
        $this->accDkStatus = (string) ($row->acc_dk_status ?? 'D');

        // Default: mode harian pada tanggal terpilih di parent (set tanggalHarian + input + periode).
        $this->mode = 'harian';
        $this->setTanggalHarian($tanggal);

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'saldo-kas-history');
    }

    /**
     * Saldo per tanggal (seluruh shift) — rumus 6i, sama dengan induk (App\Support\Keuangan\SaldoKas).
     */
    private function hitungSaldoTanggal(string $tanggal): float
    {
        return SaldoKas::hitung($this->accId, $this->accDkStatus, $tanggal);
    }

    /**
     * Saldo awal periode = saldo per (dari_tanggal - 1 hari).
     */
    #[Computed]
    public function saldoAwalPeriode(): float
    {
        if ($this->dariTanggal === '') return 0;
        $prev = Carbon::parse($this->dariTanggal)->subDay()->toDateString();
        return $this->hitungSaldoTanggal($prev);
    }

    #[Computed]
    public function totalDebit(): float
    {
        return (float) $this->rows->sum('debit_kita');
    }

    #[Computed]
    public function totalKredit(): float
    {
        return (float) $this->rows->sum('kredit_kita');
    }

    #[Computed]
    public function saldoAkhir(): float
    {
        return $this->saldoAwalPeriode + $this->totalDebit - $this->totalKredit;
    }

    /**
     * Rekap per jenis transaksi (prefix txn_name sebelum '('): jumlah + total nominal.
     * Urut nominal terbesar. Menerima sekumpulan baris (semua / per shift).
     */
    private function rekapJenisDari($rows)
    {
        return collect($rows)
            ->groupBy(function ($r) {
                $name = trim((string) $r->txn_name);
                $pos = strpos($name, '(');
                $jenis = $pos !== false ? trim(substr($name, 0, $pos)) : $name;
                return $jenis === '' ? '-' : $jenis;
            })
            ->map(fn($grup, $jenis) => (object) [
                'jenis'   => $jenis,
                'count'   => $grup->count(),
                'nominal' => (float) $grup->sum(fn($r) => (float) $r->debit_kita + (float) $r->kredit_kita),
            ])
            ->sortByDesc('nominal')
            ->values();
    }

    /** Rekap jenis seluruh periode terpilih (mode harian/bulanan). */
    #[Computed]
    public function rekapJenis()
    {
        return $this->rekapJenisDari($this->rows);
    }

    /** Rekap jenis dipecah per shift (mode 'shift'): tiap shift punya rekap jenisnya sendiri. */
    #[Computed]
    public function rekapJenisPerShift(): array
    {
        if ($this->mode !== 'shift') {
            return [];
        }
        return collect($this->susunKelompokShift())
            ->map(fn($kelompok) => (object) [
                'shift' => $kelompok->shift,
                'range' => $kelompok->range,
                'rekap' => $this->rekapJenisDari($kelompok->items),
            ])
            ->all();
    }

    /**
     * Daftar transaksi dalam rentang dgn running saldo.
     */
    #[Computed]
    public function rows()
    {
        if ($this->accId === '' || $this->dariTanggal === '' || $this->sampaiTanggal === '') {
            return collect();
        }

        // Sisi 6i (SaldoKas::sisi): akun D dibaca dari baris txn_acc (debit kita = txn_d),
        // akun K dari baris txn_acc_k (debit kita = txn_k). Lawan = kolom akun satunya.
        $sisi = SaldoKas::sisi($this->accDkStatus);

        // Tanpa JOIN ke acmst_accounts: view berlapis ini sudah sangat besar, join di atasnya
        // memicu ORA-04031 (shared pool). Nama akun lawan diambil terpisah lewat satu query kecil.
        $rows = SaldoKas::query($this->accId, $this->accDkStatus, $this->dariTanggal, $this->sampaiTanggal)
            ->select(
                'txn_date', 'txn_name', 'shift',
                $sisi['lawan'] . ' as lawan_acc_id',
                DB::raw("NVL({$sisi['debit']},0) AS debit_kita"),
                DB::raw("NVL({$sisi['kredit']},0) AS kredit_kita"),
            )
            ->orderBy('txn_date')
            ->get();

        $namaLawan = DB::table('acmst_accounts')
            ->whereIn('acc_id', $rows->pluck('lawan_acc_id')->filter()->unique()->values()->all() ?: ['-'])
            ->pluck('acc_name', 'acc_id');
        $rows->each(fn ($r) => $r->lawan_acc_name = $namaLawan[$r->lawan_acc_id] ?? null);

        // Hitung running saldo
        $saldo = $this->saldoAwalPeriode;
        return $rows->map(function ($r) use (&$saldo) {
            $mutasi = (float) $r->debit_kita - (float) $r->kredit_kita;
            $saldo += $mutasi;
            $r->saldo_berjalan = $saldo;
            $r->mutasi = $mutasi;
            return $r;
        });
    }

    /** Definisi shift (rstxn_shiftctls) — pola konsisten dgn penerimaan/pengeluaran kas TU. */
    #[Computed]
    public function shiftDefs()
    {
        return DB::table('rstxn_shiftctls')
            ->select('shift', 'shift_start', 'shift_end')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->orderBy('shift_start')
            ->get();
    }

    /**
     * Kelompokkan transaksi harian per shift (urut kemunculan/kronologis), dengan subtotal & saldo per shift.
     * Dipakai tampilan mode 'shift' & cetak rekap per shift.
     * Shift diambil dari KOLOM shift jurnal (yang dicatat saat transaksi), bukan dari jam transaksi —
     * sama dengan potongan `yyyymmdd||shift` di form 6i dan filter "s/d Shift" di halaman induk.
     */
    private function susunKelompokShift(): array
    {
        $perShift = [];
        foreach ($this->rows as $row) {
            $perShift[(string) ($row->shift ?: '1')][] = $row;
        }
        // Urut nomor shift, bukan urutan kemunculan: baris berkolom shift 1 yang dicatat malam hari
        // tetap masuk shift 1, sehingga saldo akhir shift N = saldo awal + seluruh baris shift <= N
        // (persis angka "s/d Shift N" di halaman induk / form 6i).
        ksort($perShift, SORT_NATURAL);

        $kelompok = [];
        $saldoAwalShift = $this->saldoAwalPeriode;
        foreach ($perShift as $shift => $rows) {
            $def = $this->shiftDefs->first(fn($d) => (string) $d->shift === (string) $shift);
            $subtotalDebit = 0.0;
            $subtotalKredit = 0.0;
            $saldo = $saldoAwalShift;
            $items = [];
            foreach ($rows as $row) {
                $item = clone $row;                       // running saldo per shift, jangan menimpa mode harian
                $subtotalDebit  += (float) $item->debit_kita;
                $subtotalKredit += (float) $item->kredit_kita;
                $saldo += (float) $item->mutasi;
                $item->saldo_berjalan = $saldo;
                $items[] = $item;
            }
            $saldoAkhirShift = $saldo;

            $kelompok[] = (object) [
                'shift'          => (string) $shift,
                'range'          => $def ? substr((string) $def->shift_start, 0, 5) . '–' . substr((string) $def->shift_end, 0, 5) : null,
                'items'          => $items,
                'subtotalDebit'  => $subtotalDebit,
                'subtotalKredit' => $subtotalKredit,
                'saldoAwal'      => $saldoAwalShift,
                'saldoAkhir'     => $saldoAkhirShift,
            ];
            $saldoAwalShift = $saldoAkhirShift;
        }
        return $kelompok;
    }

    #[Computed]
    public function shiftGroups(): array
    {
        if ($this->mode !== 'shift' || $this->dariTanggal === '') {
            return [];
        }
        return $this->susunKelompokShift();
    }

    public function cetakRekap(): mixed
    {
        if ($this->accId === '' || $this->dariTanggal === '' || $this->sampaiTanggal === '') {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data untuk dicetak.');
            return null;
        }

        // Pra-format tanggal di sini (class zone) supaya blade cetak bebas Carbon.
        $formatItem = function ($row) {
            $tglTransaksi = Carbon::parse($row->txn_date);
            return (object) [
                'tglLabel'      => $tglTransaksi->format('d/m/Y'),
                'jamLabel'      => $tglTransaksi->format('H:i'),
                'deskripsi'     => $row->txn_name,
                'lawanAccId'    => $row->lawan_acc_id,
                'lawanAccName'  => $row->lawan_acc_name,
                'debit'         => (float) $row->debit_kita,
                'kredit'        => (float) $row->kredit_kita,
                'saldoBerjalan' => (float) $row->saldo_berjalan,
            ];
        };

        $transaksiList = $this->rows->map($formatItem)->values();

        // Mode per shift: susun kelompok dgn item ter-format.
        $shiftGroups = [];
        if ($this->mode === 'shift') {
            foreach ($this->susunKelompokShift() as $kelompok) {
                $shiftGroups[] = (object) [
                    'shift'          => $kelompok->shift,
                    'range'          => $kelompok->range,
                    'items'          => collect($kelompok->items)->map($formatItem)->values(),
                    'subtotalDebit'  => $kelompok->subtotalDebit,
                    'subtotalKredit' => $kelompok->subtotalKredit,
                    'saldoAwal'      => $kelompok->saldoAwal,
                    'saldoAkhir'     => $kelompok->saldoAkhir,
                ];
            }
        }

        $tglMulai  = Carbon::parse($this->dariTanggal);
        $tglSampai = Carbon::parse($this->sampaiTanggal);
        $modeLabel = ['harian' => 'Harian', 'shift' => 'Harian (per Shift)', 'bulanan' => 'Bulanan'][$this->mode] ?? 'Harian';

        $dataCetak = [
            'accId'        => $this->accId,
            'accDesc'      => $this->accDesc,
            'mode'         => $this->mode,
            'modeLabel'    => $modeLabel,
            'periodeLabel' => $this->isModeHarian()
                ? $tglMulai->format('d/m/Y')
                : $tglMulai->format('d/m/Y') . ' — ' . $tglSampai->format('d/m/Y'),
            'saldoAwalTgl' => $tglMulai->copy()->subDay()->format('d/m/Y'),
            'sampaiLabel'  => $tglSampai->format('d/m/Y'),
            'dicetakPada'  => now()->format('d/m/Y H:i'),
            'saldoAwal'    => $this->saldoAwalPeriode,
            'totalDebit'   => $this->totalDebit,
            'totalKredit'  => $this->totalKredit,
            'saldoAkhir'   => $this->saldoAkhir,
            'transaksiList' => $transaksiList,
            'shiftGroups'   => $shiftGroups,
        ];

        $pdf = Pdf::loadView('pages.transaksi.keuangan.saldo-kas.saldo-kas-history-print', $dataCetak)
            ->setPaper('a4', 'portrait');

        $akhiranPeriode = $this->isModeHarian()
            ? $tglMulai->format('Ymd')
            : $tglMulai->format('Ym');
        $namaFile = 'rekap-kas-' . $this->accId . '-' . $akhiranPeriode . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $namaFile);
    }

    public function closeModal(): void
    {
        $this->reset(['accId', 'accDesc', 'accDkStatus', 'mode', 'periode', 'periodeInput', 'tanggalHarian', 'tanggalHarianInput']);
        $this->dispatch('close-modal', name: 'saldo-kas-history');
        $this->resetVersion();
    }
};
?>

<div>
    <x-modal name="saldo-kas-history" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal', [$accId, $mode, $periode, $tanggalHarian]) }}">

            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">
                            Riwayat Transaksi — {{ $accDesc }}
                        </h2>
                        <p class="mt-1 text-sm text-muted dark:text-gray-400">
                            Akun <span class="font-mono">{{ $accId }}</span>
                        </p>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="px-4 py-3 bg-canvas border-b border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        {{-- Toggle mode: Harian / Per Shift / Bulanan (Standar UI: x-tabs variant pill) --}}
                        <div>
                            <x-input-label value="Tampilan" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                            <x-tabs variant="pill">
                                <x-tab :active="$mode === 'harian'" color="emerald" wire:click="setMode('harian')">Harian</x-tab>
                                <x-tab :active="$mode === 'shift'" color="emerald" wire:click="setMode('shift')">Per Shift</x-tab>
                                <x-tab :active="$mode === 'bulanan'" color="emerald" wire:click="setMode('bulanan')">Bulanan</x-tab>
                            </x-tabs>
                        </div>

                        @if ($mode === 'bulanan')
                            {{-- Picker bulan --}}
                            <div>
                                <x-input-label for="periodeInput" value="Periode (mm/yyyy)" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                                <div class="flex items-stretch gap-1">
                                    <x-secondary-button type="button" wire:click="prevMonth"
                                        class="px-3" title="Bulan sebelumnya">
                                        ◀
                                    </x-secondary-button>
                                    <x-text-input id="periodeInput" type="text"
                                        wire:model.live.debounce.500ms="periodeInput"
                                        placeholder="01/2026" maxlength="7"
                                        class="w-28 text-center font-mono" />
                                    <x-secondary-button type="button" wire:click="nextMonth"
                                        class="px-3" title="Bulan berikutnya">
                                        ▶
                                    </x-secondary-button>
                                </div>
                            </div>
                        @else
                            {{-- Picker harian --}}
                            <div>
                                <x-input-label for="tanggalHarianInput" value="Tanggal (dd/mm/yyyy)" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                                <div class="flex items-stretch gap-1">
                                    <x-secondary-button type="button" wire:click="prevDay"
                                        class="px-3" title="Hari sebelumnya">
                                        ◀
                                    </x-secondary-button>
                                    <x-text-input id="tanggalHarianInput" type="text"
                                        wire:model.live.debounce.500ms="tanggalHarianInput"
                                        placeholder="dd/mm/yyyy" maxlength="10"
                                        class="w-32 text-center font-mono" />
                                    <x-secondary-button type="button" wire:click="nextDay"
                                        class="px-3" title="Hari berikutnya">
                                        ▶
                                    </x-secondary-button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Rekap per jenis transaksi — kartu collapsible (gaya kartu step casemix) --}}
                <div class="mt-3 border shadow-sm border-hairline rounded-xl bg-canvas dark:bg-gray-900 dark:border-gray-700"
                     x-data="{ openRekap: false }">
                    <button type="button" x-on:click="openRekap = !openRekap"
                        class="flex items-center justify-between w-full gap-3 px-4 py-2.5 text-left">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-brand-green/10 text-brand-green dark:bg-brand-lime/15 dark:text-brand-lime">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-ink dark:text-gray-100">Rekap per Jenis Transaksi</div>
                                <div class="text-xs text-muted dark:text-gray-400">Jumlah &amp; total nominal tiap jenis{{ $mode === 'shift' ? ' — dipisah per shift' : '' }}.</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-surface-soft text-muted dark:bg-gray-800 dark:text-gray-300">{{ $this->rekapJenis->count() }} jenis</span>
                            <svg class="w-4 h-4 text-muted transition-transform" x-bind:class="openRekap ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </button>

                    <div x-show="openRekap" x-transition class="px-4 pt-3 pb-4 border-t border-hairline dark:border-gray-700">
                        @if ($mode === 'shift')
                            {{-- Per shift: tiap shift punya rekap jenisnya sendiri --}}
                            <div class="space-y-3">
                                @forelse ($this->rekapJenisPerShift as $grup)
                                    <div>
                                        <div class="mb-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                            Shift {{ $grup->shift }}@if ($grup->range) <span class="font-normal text-muted">({{ $grup->range }})</span>@endif
                                        </div>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($grup->rekap as $rekap)
                                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 border rounded-lg bg-surface-soft border-hairline dark:bg-gray-800/40 dark:border-gray-700">
                                                    <span class="text-xs font-semibold text-ink dark:text-gray-200">{{ $rekap->jenis }}</span>
                                                    <span class="px-1.5 text-xs font-medium rounded text-muted bg-canvas dark:bg-gray-900 dark:text-gray-400">{{ $rekap->count }} trx</span>
                                                    <span class="text-xs font-mono font-semibold text-brand dark:text-brand-lime">Rp {{ number_format($rekap->nominal, 0, '.', ',') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <span class="text-xs text-muted-soft">Tidak ada transaksi.</span>
                                @endforelse
                            </div>
                        @else
                            {{-- Harian/bulanan: rekap jenis se-periode --}}
                            <div class="flex flex-wrap gap-1.5">
                                @forelse ($this->rekapJenis as $rekap)
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 border rounded-lg bg-surface-soft border-hairline dark:bg-gray-800/40 dark:border-gray-700">
                                        <span class="text-xs font-semibold text-ink dark:text-gray-200">{{ $rekap->jenis }}</span>
                                        <span class="px-1.5 text-xs font-medium rounded text-muted bg-canvas dark:bg-gray-900 dark:text-gray-400">{{ $rekap->count }} trx</span>
                                        <span class="text-xs font-mono font-semibold text-brand dark:text-brand-lime">Rp {{ number_format($rekap->nominal, 0, '.', ',') }}</span>
                                    </div>
                                @empty
                                    <span class="text-xs text-muted-soft">Tidak ada transaksi.</span>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex-1 px-4 py-3 overflow-hidden bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="h-full overflow-y-auto bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-3 py-2 font-semibold w-28">TANGGAL</th>
                                <th class="px-3 py-2 font-semibold">DESKRIPSI</th>
                                <th class="px-3 py-2 font-semibold w-56">LAWAN AKUN</th>
                                <th class="px-3 py-2 font-semibold w-28 text-right">DEBIT</th>
                                <th class="px-3 py-2 font-semibold w-28 text-right">KREDIT</th>
                                <th class="px-3 py-2 font-semibold w-36 text-right">SALDO</th>
                            </tr>
                        </thead>
                        <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                            @if ($this->dariTanggal !== '')
                                <tr class="border-b-2 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800">
                                    <td colspan="5" class="px-3 py-2.5 text-xs font-bold tracking-wide uppercase text-amber-800 dark:text-amber-300">
                                        Saldo Awal per {{ \Carbon\Carbon::parse($this->dariTanggal)->subDay()->format('d/m/Y') }}
                                    </td>
                                    <td class="px-3 py-2.5 font-mono text-base font-bold text-right text-amber-800 dark:text-amber-300">
                                        {{ number_format($this->saldoAwalPeriode, 0, '.', ',') }}
                                    </td>
                                </tr>
                            @endif

                            @if ($mode === 'shift')
                                {{-- Mode Per Shift: transaksi dikelompokkan per shift + subtotal --}}
                                @forelse ($this->shiftGroups as $group)
                                    <tr class="bg-brand-green/10 dark:bg-emerald-900/30">
                                        <td colspan="6" class="px-3 py-1.5 text-xs font-bold tracking-wide uppercase text-emerald-800 dark:text-emerald-200">
                                            Shift {{ $group->shift }}@if ($group->range)<span class="ml-1 font-normal normal-case text-muted">({{ $group->range }})</span>@endif
                                        </td>
                                    </tr>
                                    @foreach ($group->items as $i => $row)
                                        <tr wire:key="shift-{{ $group->shift }}-{{ $i }}-{{ $row->txn_date }}"
                                            class="{{ $i % 2 ? 'bg-surface-soft/40 dark:bg-gray-800/20' : '' }} hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                            <td class="px-3 py-2.5 font-mono align-middle whitespace-nowrap">
                                                <div class="text-sm text-body dark:text-gray-200">{{ \Carbon\Carbon::parse($row->txn_date)->format('d/m/Y') }}</div>
                                                <div class="text-xs font-medium text-muted dark:text-gray-300">{{ \Carbon\Carbon::parse($row->txn_date)->format('H:i') }}</div>
                                            </td>
                                            <td class="px-3 py-2.5 text-sm text-body align-middle dark:text-gray-200">{{ $row->txn_name }}</td>
                                            <td class="px-3 py-2.5 align-middle">
                                                <div class="font-mono text-sm font-semibold text-ink dark:text-gray-100">{{ $row->lawan_acc_id }}</div>
                                                @if (!empty($row->lawan_acc_name))
                                                    <div class="text-xs truncate text-muted dark:text-gray-400">{{ $row->lawan_acc_name }}</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2.5 font-mono text-sm text-right align-middle text-blue-700 dark:text-blue-300">
                                                @if ((float) $row->debit_kita > 0)
                                                    {{ number_format((float) $row->debit_kita, 0, '.', ',') }}
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2.5 font-mono text-sm text-right align-middle text-error dark:text-rose-300">
                                                @if ((float) $row->kredit_kita > 0)
                                                    {{ number_format((float) $row->kredit_kita, 0, '.', ',') }}
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2.5 font-mono text-sm font-semibold text-right align-middle {{ (float) $row->saldo_berjalan < 0 ? 'text-red-600' : '' }}">
                                                {{ number_format((float) $row->saldo_berjalan, 0, '.', ',') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr class="font-semibold bg-surface-soft dark:bg-gray-800/50">
                                        <td colspan="3" class="px-3 py-1.5 text-xs text-right uppercase text-muted">
                                            Subtotal Shift {{ $group->shift }}
                                        </td>
                                        <td class="px-3 py-1.5 font-mono text-sm text-right text-blue-700 dark:text-blue-300">
                                            {{ number_format($group->subtotalDebit, 0, '.', ',') }}
                                        </td>
                                        <td class="px-3 py-1.5 font-mono text-sm text-right text-error dark:text-rose-300">
                                            {{ number_format($group->subtotalKredit, 0, '.', ',') }}
                                        </td>
                                        <td class="px-3 py-1.5 font-mono text-sm text-right {{ $group->saldoAkhir < 0 ? 'text-red-600' : '' }}">
                                            {{ number_format($group->saldoAkhir, 0, '.', ',') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-muted dark:text-gray-400">
                                            Tidak ada transaksi pada tanggal ini.
                                        </td>
                                    </tr>
                                @endforelse
                            @else
                                {{-- Mode Harian / Bulanan: daftar transaksi datar --}}
                                @forelse ($this->rows as $i => $row)
                                    <tr wire:key="hist-{{ $i }}-{{ $row->txn_date }}"
                                        class="{{ $i % 2 ? 'bg-surface-soft/40 dark:bg-gray-800/20' : '' }} hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                        <td class="px-3 py-2.5 font-mono align-middle whitespace-nowrap">
                                            <div class="text-sm text-body dark:text-gray-200">{{ \Carbon\Carbon::parse($row->txn_date)->format('d/m/Y') }}</div>
                                            <div class="text-xs font-medium text-muted dark:text-gray-300">{{ \Carbon\Carbon::parse($row->txn_date)->format('H:i') }}</div>
                                        </td>
                                        <td class="px-3 py-2.5 text-sm text-body align-middle dark:text-gray-200">{{ $row->txn_name }}</td>
                                        <td class="px-3 py-2.5 align-middle">
                                            <div class="font-mono text-sm font-semibold text-ink dark:text-gray-100">{{ $row->lawan_acc_id }}</div>
                                            @if (!empty($row->lawan_acc_name))
                                                <div class="text-xs truncate text-muted dark:text-gray-400">{{ $row->lawan_acc_name }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 font-mono text-sm text-right align-middle text-blue-700 dark:text-blue-300">
                                            @if ((float) $row->debit_kita > 0)
                                                {{ number_format((float) $row->debit_kita, 0, '.', ',') }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 font-mono text-sm text-right align-middle text-error dark:text-rose-300">
                                            @if ((float) $row->kredit_kita > 0)
                                                {{ number_format((float) $row->kredit_kita, 0, '.', ',') }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 font-mono text-sm font-semibold text-right align-middle {{ (float) $row->saldo_berjalan < 0 ? 'text-red-600' : '' }}">
                                            {{ number_format((float) $row->saldo_berjalan, 0, '.', ',') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-muted dark:text-gray-400">
                                            Tidak ada transaksi pada periode ini.
                                        </td>
                                    </tr>
                                @endforelse
                            @endif

                        </tbody>
                        @if ($this->rows->count() > 0)
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="sticky bottom-0 z-10 px-3 py-3 text-sm font-bold tracking-wide uppercase border-t-2 bg-emerald-100 border-emerald-300 text-emerald-800 dark:bg-emerald-900 dark:border-emerald-700 dark:text-emerald-200">
                                        Saldo Akhir per {{ \Carbon\Carbon::parse($this->sampaiTanggal)->format('d/m/Y') }}
                                    </td>
                                    <td class="sticky bottom-0 z-10 px-3 py-3 font-mono text-base font-bold text-right border-t-2 bg-emerald-100 border-emerald-300 text-blue-700 dark:bg-emerald-900 dark:border-emerald-700 dark:text-blue-300">
                                        {{ number_format($this->totalDebit, 0, '.', ',') }}
                                    </td>
                                    <td class="sticky bottom-0 z-10 px-3 py-3 font-mono text-base font-bold text-right border-t-2 bg-emerald-100 border-emerald-300 text-error dark:bg-emerald-900 dark:border-emerald-700 dark:text-rose-300">
                                        {{ number_format($this->totalKredit, 0, '.', ',') }}
                                    </td>
                                    <td class="sticky bottom-0 z-10 px-3 py-3 font-mono text-lg font-bold text-right border-t-2 bg-emerald-100 border-emerald-300 {{ $this->saldoAkhir < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-800 dark:text-emerald-200' }} dark:bg-emerald-900 dark:border-emerald-700">
                                        {{ number_format($this->saldoAkhir, 0, '.', ',') }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-3 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-cetak-button wire:click="cetakRekap" :disabled="$this->rows->count() === 0" label="Cetak Rekap" />
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
