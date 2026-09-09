<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Support\Keuangan\Jurnal;

/**
 * Laporan Laba Rugi — template TKACC_TEMLABARUGINERACA* (HDRS → L2S → L1S → DTLS → TEMACCOUNTES),
 * nilai per akun dari App\Support\Keuangan\Jurnal (jurnal dibaca LANGSUNG dari tabel transaksi,
 * termasuk cabang HPP yang dulu hanya ada di view TKVIEW_ACCOUNTS_LABARUGI).
 *
 * Tanda nilai mengikuti DK_STATUS grup akun (tkacc_gr_accountses) pada baris template, bukan
 * acc_dk_status master yang sering kosong: PENDAPATAN (K) = K − D, BEBAN (D) = D − K.
 * Laba = Σ pendapatan − Σ beban.
 */
new class extends Component {
    /** Format internal: 'YYYY-MM' */
    public string $periode      = '';
    /** Format input user: 'MM/YYYY' */
    public string $periodeInput = '';
    /** temp_id template ber-status L (Laba Rugi): L1 = template form 6i, LWEB = ringkas web. */
    public string $templateId   = 'L1';

    public function mount(): void
    {
        $this->setPeriode(now()->format('Y-m'));
        if (!array_key_exists($this->templateId, $this->daftarTemplate)) {
            $this->templateId = (string) (array_key_first($this->daftarTemplate) ?? '');
        }
    }

    public function updatedPeriodeInput(string $value): void
    {
        $value = trim($value);
        if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{4})$/', $value, $cocok)) {
            $this->periode = '';
            return;
        }
        [, $bulan, $tahun] = $cocok;
        $this->periode = "{$tahun}-{$bulan}";
    }

    private function setPeriode(string $tahunBulan): void
    {
        $this->periode      = $tahunBulan;
        $this->periodeInput = \Carbon\Carbon::parse("{$tahunBulan}-01")->format('m/Y');
    }

    public function prevMonth(): void
    {
        if ($this->periode === '') return;
        $this->setPeriode(\Carbon\Carbon::parse("{$this->periode}-01")->subMonth()->format('Y-m'));
    }

    public function nextMonth(): void
    {
        if ($this->periode === '') return;
        $this->setPeriode(\Carbon\Carbon::parse("{$this->periode}-01")->addMonth()->format('Y-m'));
    }

    #[Computed]
    public function bulanStart(): string
    {
        return $this->periode === '' ? '' : "{$this->periode}-01";
    }

    #[Computed]
    public function bulanEnd(): string
    {
        if ($this->periode === '') return '';
        return \Carbon\Carbon::parse("{$this->periode}-01")->endOfMonth()->toDateString();
    }

    #[Computed]
    public function ytdStart(): string
    {
        if ($this->periode === '') return '';
        return substr($this->periode, 0, 4) . '-01-01';
    }

    /** Template ber-status L: [temp_id => temp_desc]. */
    #[Computed]
    public function daftarTemplate(): array
    {
        return DB::table('tkacc_temlabarugineracahdrs')
            ->where('temp_status', 'L')
            ->orderBy('temp_id')
            ->pluck('temp_desc', 'temp_id')
            ->map(fn ($tempDesc) => (string) $tempDesc)
            ->all();
    }

    /**
     * Hirarki template: L2 → L1 → DTL → akun, tanpa nilai.
     * DK tiap pos = dk_status grup akun (gra_id) baris DTL.
     */
    #[Computed]
    public function struktur(): array
    {
        if ($this->templateId === '') return [];

        $grupAkunList = DB::table('tkacc_gr_accountses')->get()->keyBy('gra_id');

        $kelompokRows = DB::table('tkacc_temlabarugineracahdr_l2s')
            ->where('temp_id', $this->templateId)
            ->orderByRaw("to_number(nvl(temp_seql2,'999')), temp_idl2")
            ->get();

        $kelompokList = [];
        foreach ($kelompokRows as $kelompok) {
            $bagianRows = DB::table('tkacc_temlabarugineracahdr_l1s')
                ->where('temp_idl2', $kelompok->temp_idl2)
                ->orderByRaw("to_number(nvl(temp_seql1,'999')), temp_idl1")
                ->get();

            $bagianList = [];
            foreach ($bagianRows as $bagian) {
                $posRows = DB::table('tkacc_temlabarugineracadtls')
                    ->where('temp_idl1', $bagian->temp_idl1)
                    ->orderByRaw("to_number(nvl(temp_dtl_seq,'999')), temp_dtl")
                    ->get();

                $posList = [];
                foreach ($posRows as $pos) {
                    $accIdList = DB::table('tkacc_temaccountes')
                        ->where('temp_dtl', $pos->temp_dtl)
                        ->orderByRaw("to_number(nvl(temacc_seq,'999')), acc_id")
                        ->pluck('acc_id')
                        ->map(fn ($accId) => (string) $accId)
                        ->all();
                    $grupAkun = $grupAkunList[(string) $pos->gra_id] ?? null;
                    $posList[] = [
                        'id'    => (string) $pos->temp_dtl,
                        'desc'  => (string) $pos->temp_dtl_desc,
                        'graId' => (string) $pos->gra_id,
                        'graDesc' => (string) ($grupAkun->gra_desc ?? ''),
                        'dkStatus' => (string) ($grupAkun->dk_status ?? 'D'),
                        'accIdList' => $accIdList,
                    ];
                }
                $bagianList[] = ['id' => (string) $bagian->temp_idl1, 'desc' => (string) $bagian->temp_descl1, 'posList' => $posList];
            }
            $kelompokList[] = ['id' => (string) $kelompok->temp_idl2, 'desc' => (string) $kelompok->temp_descl2, 'bagianList' => $bagianList];
        }

        return $kelompokList;
    }

    /**
     * Laporan bernilai: struktur + nilai bulan/YTD per akun, subtotal per DTL/L1/L2, laba bersih.
     * Nilai pos = natural (pendapatan positif bila dikredit, beban positif bila didebit);
     * subtotal L1/L2/total = Σ pendapatan − Σ beban (laba), plus rincian keduanya.
     */
    #[Computed]
    public function laporan(): array
    {
        if ($this->periode === '' || $this->templateId === '') return ['kelompokList' => [], 'total' => self::nol()];

        $struktur = $this->struktur;
        $semuaAkun = [];
        foreach ($struktur as $kelompok) foreach ($kelompok['bagianList'] as $bagian) foreach ($bagian['posList'] as $pos) array_push($semuaAkun, ...$pos['accIdList']);
        $semuaAkun = array_values(array_unique($semuaAkun));

        $arusBulan = Jurnal::arusPerAkun($semuaAkun, $this->bulanStart, $this->bulanEnd);
        $arusYtd   = Jurnal::arusPerAkun($semuaAkun, $this->ytdStart,   $this->bulanEnd);
        $namaAkun  = Jurnal::namaAkun($semuaAkun);

        $hitungNilai = fn (array $arus, string $dkStatus) => $dkStatus === 'K'
            ? $arus['kredit'] - $arus['debit']
            : $arus['debit'] - $arus['kredit'];

        $total = self::nol();
        $kelompokList = [];
        foreach ($struktur as $kelompok) {
            $subtotalKelompok = self::nol();
            $bagianList = [];
            foreach ($kelompok['bagianList'] as $bagian) {
                $subtotalBagian = self::nol();
                $posList = [];
                foreach ($bagian['posList'] as $pos) {
                    $bulan = 0.0; $ytd = 0.0; $akunList = [];
                    foreach ($pos['accIdList'] as $akun) {
                        $nilaiBulan = $hitungNilai($arusBulan[$akun] ?? ['debit' => 0, 'kredit' => 0], $pos['dkStatus']);
                        $nilaiYtd = $hitungNilai($arusYtd[$akun]   ?? ['debit' => 0, 'kredit' => 0], $pos['dkStatus']);
                        $bulan += $nilaiBulan; $ytd += $nilaiYtd;
                        $akunList[] = ['acc_id' => $akun, 'acc_name' => (string) ($namaAkun[$akun] ?? ''), 'bulan' => $nilaiBulan, 'ytd' => $nilaiYtd];
                    }
                    $pos['akunList'] = $akunList;
                    $pos['bulan'] = $bulan;
                    $pos['ytd']   = $ytd;
                    $posList[] = $pos;
                    self::tambah($subtotalBagian, $pos['dkStatus'], $bulan, $ytd);
                }
                $bagian['posList'] = $posList;
                $bagian['subtotal'] = $subtotalBagian;
                $bagianList[] = $bagian;
                self::gabung($subtotalKelompok, $subtotalBagian);
            }
            $kelompok['bagianList']  = $bagianList;
            $kelompok['subtotal'] = $subtotalKelompok;
            $kelompokList[] = $kelompok;
            self::gabung($total, $subtotalKelompok);
        }

        return ['kelompokList' => $kelompokList, 'total' => $total];
    }

    private static function nol(): array
    {
        return ['pendapatanBulan' => 0.0, 'pendapatanYtd' => 0.0, 'bebanBulan' => 0.0, 'bebanYtd' => 0.0,
                'labaBulan' => 0.0, 'labaYtd' => 0.0, 'campur' => false];
    }

    private static function tambah(array &$subtotal, string $dkStatus, float $bulan, float $ytd): void
    {
        if ($dkStatus === 'K') { $subtotal['pendapatanBulan'] += $bulan; $subtotal['pendapatanYtd'] += $ytd; }
        else             { $subtotal['bebanBulan']      += $bulan; $subtotal['bebanYtd']      += $ytd; }
        $subtotal['labaBulan'] = $subtotal['pendapatanBulan'] - $subtotal['bebanBulan'];
        $subtotal['labaYtd']   = $subtotal['pendapatanYtd']   - $subtotal['bebanYtd'];
        $subtotal['campur']    = ($subtotal['pendapatanBulan'] != 0 || $subtotal['pendapatanYtd'] != 0)
                         && ($subtotal['bebanBulan'] != 0 || $subtotal['bebanYtd'] != 0);
    }

    private static function gabung(array &$tujuan, array $dari): void
    {
        foreach (['pendapatanBulan', 'pendapatanYtd', 'bebanBulan', 'bebanYtd'] as $kunci) $tujuan[$kunci] += $dari[$kunci];
        $tujuan['labaBulan'] = $tujuan['pendapatanBulan'] - $tujuan['bebanBulan'];
        $tujuan['labaYtd']   = $tujuan['pendapatanYtd']   - $tujuan['bebanYtd'];
        $tujuan['campur']    = ($tujuan['pendapatanBulan'] != 0 || $tujuan['pendapatanYtd'] != 0)
                        && ($tujuan['bebanBulan'] != 0 || $tujuan['bebanYtd'] != 0);
    }

    #[Computed]
    public function labaBersihBulan(): float
    {
        return (float) $this->laporan['total']['labaBulan'];
    }

    #[Computed]
    public function labaBersihYtd(): float
    {
        return (float) $this->laporan['total']['labaYtd'];
    }
};
?>

{{-- tampilkanAkun di sisi browser (Alpine) — baris akun selalu dirender, cukup disembunyikan; tidak memicu hitung ulang server --}}
<div x-data="{ tampilkanAkun: false }">
    <x-page-title
        title="Laporan Laba Rugi"
        subtitle="Pendapatan dan beban per bulan terpilih plus akumulasi tahun berjalan (YTD), tersusun mengikuti template laba rugi. Nilai dibaca langsung dari tabel transaksi." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-4 pb-6">
            <div class="p-4 mb-4 border rounded-lg border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700">
                <div class="flex items-start gap-3">
                    <span class="text-xl leading-none">⚠️</span>
                    <div class="flex-1 text-sm text-amber-900 dark:text-amber-100">
                        <p class="font-semibold">Verifikasi manual sebelum dipakai sebagai laporan resmi.</p>
                        <ul class="mt-2 ml-5 space-y-0.5 text-xs list-disc">
                            <li><strong>HPP otomatis</strong> dihitung dari <span class="font-mono">hpp_product</span> saldo awal stok tahun berjalan × qty (cabang HPP obat RJ/UGD/resep dan transfer gudang). Stock opname yang belum rutin membuat angka ini perlu kehati-hatian.</li>
                            <li>Tanda tiap pos mengikuti grup akun baris template (Pendapatan = kredit, Beban = debit); pos bergrup Aktiva/Hutang di template laba rugi dihitung sebagai beban/pendapatan menurut D/K grupnya.</li>
                            <li>Sumber data: <span class="font-mono">App\Support\Keuangan\Jurnal</span> (tabel transaksi langsung). Susunan: template <span class="font-mono">{{ $templateId }}</span> di <span class="font-mono">tkacc_temlabarugineraca*</span>.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <div>
                            <x-input-label for="periodeInput" value="Periode (mm/yyyy)" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                            <div class="flex items-stretch gap-1">
                                <x-secondary-button type="button" wire:click="prevMonth" class="px-3" title="Bulan sebelumnya">◀</x-secondary-button>
                                <x-text-input id="periodeInput" type="text"
                                    wire:model.live.debounce.500ms="periodeInput"
                                    placeholder="01/2026" maxlength="7"
                                    class="w-28 text-center font-mono" />
                                <x-secondary-button type="button" wire:click="nextMonth" class="px-3" title="Bulan berikutnya">▶</x-secondary-button>
                            </div>
                            <p class="mt-1 text-[11px] text-muted dark:text-gray-400">
                                @if ($periode !== '')
                                    Bulan: {{ \Carbon\Carbon::parse($this->bulanStart)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($this->bulanEnd)->format('d/m/Y') }}
                                    · YTD: {{ \Carbon\Carbon::parse($this->ytdStart)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($this->bulanEnd)->format('d/m/Y') }}
                                @else
                                    <span class="text-error">Format: mm/yyyy</span>
                                @endif
                            </p>
                        </div>

                        <div>
                            <x-input-label for="templateId" value="Template" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                            <select id="templateId" wire:model.live="templateId"
                                class="rounded-lg border-gray-300 bg-gray-50 text-sm text-gray-900 shadow-sm focus:border-brand-green focus:ring-brand-green/40 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:focus:border-brand-lime dark:focus:ring-brand-lime/40">
                                @foreach ($this->daftarTemplate as $tempId => $tempDesc)
                                    <option value="{{ $tempId }}">{{ $tempId }} — {{ $tempDesc }}</option>
                                @endforeach
                            </select>
                        </div>

                        <label class="flex items-center gap-2 pb-2 text-sm cursor-pointer select-none text-body dark:text-gray-200">
                            <input type="checkbox" x-model="tampilkanAkun" class="w-4 h-4 rounded border-gray-300 text-brand-green focus:ring-brand-green/40 dark:border-gray-600 dark:bg-gray-900">
                            Tampilkan akun
                        </label>
                    </div>

                    @if ($periode !== '')
                        <div class="grid grid-cols-2 gap-3 text-right">
                            <div class="px-4 py-2 border rounded-lg bg-surface-soft border-hairline dark:bg-gray-800/40 dark:border-gray-700">
                                <div class="text-[10px] tracking-wider text-muted uppercase">Laba Bersih · Bulan</div>
                                <div class="font-mono text-lg font-bold {{ $this->labaBersihBulan < 0 ? 'text-error dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                                    Rp {{ number_format($this->labaBersihBulan, 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="px-4 py-2 border rounded-lg bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800">
                                <div class="text-[10px] tracking-wider text-emerald-700 uppercase dark:text-emerald-300">Laba Bersih · YTD</div>
                                <div class="font-mono text-lg font-bold {{ $this->labaBersihYtd < 0 ? 'text-error dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-200' }}">
                                    Rp {{ number_format($this->labaBersihYtd, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl" wire:loading.class="opacity-50">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-3 py-2 font-semibold w-28">KODE</th>
                                <th class="px-3 py-2 font-semibold">URAIAN</th>
                                <th class="px-3 py-2 font-semibold w-40 text-right">BULAN INI</th>
                                <th class="px-3 py-2 font-semibold w-40 text-right">YTD</th>
                            </tr>
                        </thead>
                        <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                            @if ($periode === '')
                                <tr><td colspan="4" class="px-4 py-12 text-center text-muted dark:text-gray-400">
                                    Atur periode untuk menampilkan laporan.
                                </td></tr>
                            @elseif (empty($this->laporan['kelompokList']))
                                <tr><td colspan="4" class="px-4 py-12 text-center text-muted dark:text-gray-400">
                                    Template {{ $templateId }} belum punya susunan pos.
                                </td></tr>
                            @else
                                @foreach ($this->laporan['kelompokList'] as $kelompok)
                                    <tr wire:key="lr-l2-{{ $kelompok['id'] }}" class="bg-surface-soft dark:bg-gray-800">
                                        <td colspan="4" class="px-3 py-2 text-xs font-bold tracking-wider uppercase">{{ $kelompok['desc'] }}</td>
                                    </tr>
                                    @foreach ($kelompok['bagianList'] as $bagian)
                                        <tr wire:key="lr-l1-{{ $bagian['id'] }}" class="bg-surface-soft/60 dark:bg-gray-800/60">
                                            <td colspan="4" class="px-3 py-1.5 pl-6 text-xs font-semibold uppercase text-body dark:text-gray-200">{{ $bagian['desc'] }}</td>
                                        </tr>
                                        @foreach ($bagian['posList'] as $pos)
                                            <tr wire:key="lr-dtl-{{ $pos['id'] }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                                <td class="px-3 py-1.5 font-mono text-xs text-muted-soft">{{ $pos['id'] }}</td>
                                                <td class="px-3 py-1.5 pl-9 text-sm">
                                                    {{ $pos['desc'] }}
                                                    <span class="px-1 ml-1 text-[9px] rounded {{ $pos['dkStatus'] === 'K' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}" title="{{ $pos['graDesc'] }}">{{ $pos['dkStatus'] }}</span>
                                                </td>
                                                <td class="px-3 py-1.5 font-mono text-sm text-right">
                                                    @if (abs($pos['bulan']) > 0.001) {{ number_format($pos['bulan'], 0, ',', '.') }} @else <span class="text-gray-300">—</span> @endif
                                                </td>
                                                <td class="px-3 py-1.5 font-mono text-sm text-right">
                                                    @if (abs($pos['ytd']) > 0.001) {{ number_format($pos['ytd'], 0, ',', '.') }} @else <span class="text-gray-300">—</span> @endif
                                                </td>
                                            </tr>
                                                @forelse ($pos['akunList'] as $akun)
                                                    <tr wire:key="lr-acc-{{ $pos['id'] }}-{{ $akun['acc_id'] }}" x-show="tampilkanAkun" class="text-xs text-muted dark:text-gray-400">
                                                        <td class="px-3 py-1 font-mono">{{ $akun['acc_id'] }}</td>
                                                        <td class="px-3 py-1 pl-12">{{ $akun['acc_name'] ?: '—' }}</td>
                                                        <td class="px-3 py-1 font-mono text-right">
                                                            @if (abs($akun['bulan']) > 0.001) {{ number_format($akun['bulan'], 0, ',', '.') }} @else <span class="text-gray-300">—</span> @endif
                                                        </td>
                                                        <td class="px-3 py-1 font-mono text-right">
                                                            @if (abs($akun['ytd']) > 0.001) {{ number_format($akun['ytd'], 0, ',', '.') }} @else <span class="text-gray-300">—</span> @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr wire:key="lr-acc-{{ $pos['id'] }}-kosong" x-show="tampilkanAkun" class="text-xs italic text-muted-soft">
                                                        <td></td><td class="px-3 py-1 pl-12">(belum ada akun dipetakan)</td><td></td><td></td>
                                                    </tr>
                                                @endforelse
                                        @endforeach
                                        <tr wire:key="lr-sub-l1-{{ $bagian['id'] }}" class="font-semibold bg-surface-soft/40 dark:bg-gray-800/40">
                                            <td></td>
                                            <td class="px-3 py-1.5 pl-6 text-xs uppercase">
                                                Subtotal {{ $bagian['desc'] }}
                                                @if ($bagian['subtotal']['campur']) <span class="ml-1 text-[10px] normal-case text-muted-soft">(pendapatan − beban)</span> @endif
                                            </td>
                                            @php $subtotal = $bagian['subtotal']; $nilaiBulan = $subtotal['campur'] ? $subtotal['labaBulan'] : ($subtotal['pendapatanBulan'] ?: $subtotal['bebanBulan']); $nilaiYtd = $subtotal['campur'] ? $subtotal['labaYtd'] : ($subtotal['pendapatanYtd'] ?: $subtotal['bebanYtd']); @endphp
                                            <td class="px-3 py-1.5 font-mono text-sm text-right">{{ number_format($nilaiBulan, 0, ',', '.') }}</td>
                                            <td class="px-3 py-1.5 font-mono text-sm text-right">{{ number_format($nilaiYtd, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                    <tr wire:key="lr-sub-l2-{{ $kelompok['id'] }}" class="font-bold bg-blue-50 dark:bg-blue-900/20">
                                        <td></td>
                                        <td class="px-3 py-2 text-sm uppercase">
                                            {{ $kelompok['desc'] }}
                                            <span class="ml-1 text-[10px] font-normal normal-case text-muted">pendapatan {{ number_format($kelompok['subtotal']['pendapatanYtd'], 0, ',', '.') }} − beban {{ number_format($kelompok['subtotal']['bebanYtd'], 0, ',', '.') }} (YTD)</span>
                                        </td>
                                        <td class="px-3 py-2 font-mono text-sm text-right {{ $kelompok['subtotal']['labaBulan'] < 0 ? 'text-error dark:text-rose-300' : 'text-blue-800 dark:text-blue-200' }}">{{ number_format($kelompok['subtotal']['labaBulan'], 0, ',', '.') }}</td>
                                        <td class="px-3 py-2 font-mono text-sm text-right {{ $kelompok['subtotal']['labaYtd'] < 0 ? 'text-error dark:text-rose-300' : 'text-blue-800 dark:text-blue-200' }}">{{ number_format($kelompok['subtotal']['labaYtd'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach

                                <tr class="font-bold {{ $this->labaBersihBulan < 0 ? 'bg-rose-50 dark:bg-rose-900/20' : 'bg-emerald-50 dark:bg-emerald-900/20' }}">
                                    <td></td>
                                    <td class="px-3 py-2 text-sm uppercase">
                                        Laba (Rugi) Bersih
                                        <span class="ml-1 text-[10px] font-normal normal-case text-muted">pendapatan {{ number_format($this->laporan['total']['pendapatanYtd'], 0, ',', '.') }} − beban {{ number_format($this->laporan['total']['bebanYtd'], 0, ',', '.') }} (YTD)</span>
                                    </td>
                                    <td class="px-3 py-2 font-mono text-base text-right {{ $this->labaBersihBulan < 0 ? 'text-error dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-200' }}">
                                        {{ number_format($this->labaBersihBulan, 0, ',', '.') }}
                                    </td>
                                    <td class="px-3 py-2 font-mono text-base text-right {{ $this->labaBersihYtd < 0 ? 'text-error dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-200' }}">
                                        {{ number_format($this->labaBersihYtd, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
