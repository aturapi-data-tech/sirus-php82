<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Support\Keuangan\Jurnal;

/**
 * Laporan Neraca per tanggal cutoff — disusun dari GRUP AKUN master (tkacc_gr_accountses:
 * 1 AKTIVA, 2 HUTANG, 3 EKUITAS) dan sub-grup acmst_accgroups, bukan template N1/NWEB yang
 * di data hanya berisi 2–4 baris. Nilai dari App\Support\Keuangan\Jurnal (tabel transaksi langsung).
 *
 * Saldo akun = saldo awal tahun (tktxn_saldoawalakuns, kedua sisi) + arus 1 Januari s/d tanggal,
 * bertanda natural menurut D/K grup: AKTIVA = D − K, HUTANG/EKUITAS = K − D.
 * Laba (Rugi) Tahun Berjalan = Σ (K − D) arus YTD seluruh akun grup PENDAPATAN & BEBAN
 * (padanan view TKVIEW_ACCOUNTS_NERACA2 yang memasukkannya ke akun konfigurasi LRB),
 * ditampilkan sebagai baris tersendiri di Ekuitas.
 */
new class extends Component {
    public string $tanggal = '';

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
    }

    #[Computed]
    public function tahun(): int
    {
        return (int) substr($this->tanggal, 0, 4);
    }

    #[Computed]
    public function awalTahun(): string
    {
        return sprintf('%04d-01-01', $this->tahun);
    }

    /**
     * Laporan lengkap:
     * [
     *   'aktiva'  => ['subgrupList' => [...], 'total' => float],
     *   'hutang'  => [...], 'ekuitas' => [...],
     *   'labaBerjalan' => float, 'totalPasiva' => float, 'selisih' => float,
     * ]
     * subgrup = ['id', 'desc', 'akunList' => [['acc_id','acc_name','aktif','saldoAwal','arusDebit','arusKredit','saldo']], 'total']
     */
    #[Computed]
    public function laporan(): array
    {
        $kosong = ['subgrupList' => [], 'total' => 0.0];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->tanggal)) {
            return ['aktiva' => $kosong, 'hutang' => $kosong, 'ekuitas' => $kosong,
                    'labaBerjalan' => 0.0, 'totalPasiva' => 0.0, 'selisih' => 0.0];
        }

        $grupAkunList  = DB::table('tkacc_gr_accountses')->get()->keyBy('gra_id');
        $subgrupNama   = DB::table('acmst_accgroups')->pluck('acc_name', 'acc_group')->map(fn ($nama) => (string) $nama)->all();

        $akunNeracaRows = DB::table('acmst_accounts')
            ->whereIn('gra_id', ['1', '2', '3'])
            ->orderBy('acc_id')
            ->get();
        $accIdNeraca = $akunNeracaRows->pluck('acc_id')->map(fn ($accId) => (string) $accId)->all();

        $saldoAwal = Jurnal::saldoAwalPerAkun($accIdNeraca, $this->tahun);
        $arus      = Jurnal::arusPerAkun($accIdNeraca, $this->awalTahun, $this->tanggal);

        $sisi = ['1' => 'aktiva', '2' => 'hutang', '3' => 'ekuitas'];
        $neraca = ['aktiva' => $kosong, 'hutang' => $kosong, 'ekuitas' => $kosong];

        foreach ($akunNeracaRows as $akun) {
            $accId    = (string) $akun->acc_id;
            $graId    = (string) $akun->gra_id;
            $dkStatus = (string) ($grupAkunList[$graId]->dk_status ?? 'D');
            $saldoAwalAkun     = $saldoAwal[$accId] ?? ['debit' => 0.0, 'kredit' => 0.0];
            $mutasi   = $arus[$accId]      ?? ['debit' => 0.0, 'kredit' => 0.0];

            $saldoAwalNatural = $dkStatus === 'K' ? $saldoAwalAkun['kredit'] - $saldoAwalAkun['debit'] : $saldoAwalAkun['debit'] - $saldoAwalAkun['kredit'];
            $mutasiNatural    = $dkStatus === 'K' ? $mutasi['kredit'] - $mutasi['debit'] : $mutasi['debit'] - $mutasi['kredit'];
            $saldo            = $saldoAwalNatural + $mutasiNatural;
            $aktif            = (string) ($akun->active_status ?? '1') === '1';

            // Akun nonaktif tanpa saldo dan tanpa mutasi tidak perlu tampil.
            if (!$aktif && abs($saldo) < 0.5 && abs($mutasi['debit']) < 0.5 && abs($mutasi['kredit']) < 0.5) {
                continue;
            }

            $subgrupId = (string) ($akun->acc_group ?? '');
            $kunciSisi = $sisi[$graId];
            if (!isset($neraca[$kunciSisi]['subgrupList'][$subgrupId])) {
                $neraca[$kunciSisi]['subgrupList'][$subgrupId] = [
                    'id'       => $subgrupId,
                    'desc'     => $subgrupNama[$subgrupId] ?? ($subgrupId === '' ? 'TANPA SUB-GRUP' : "GRUP {$subgrupId}"),
                    'akunList' => [],
                    'total'    => 0.0,
                ];
            }
            $neraca[$kunciSisi]['subgrupList'][$subgrupId]['akunList'][] = [
                'acc_id'     => $accId,
                'acc_name'   => (string) ($akun->acc_name ?? ''),
                'aktif'      => $aktif,
                'saldoAwal'  => $saldoAwalNatural,
                'arusDebit'  => $mutasi['debit'],
                'arusKredit' => $mutasi['kredit'],
                'saldo'      => $saldo,
            ];
            $neraca[$kunciSisi]['subgrupList'][$subgrupId]['total'] += $saldo;
            $neraca[$kunciSisi]['total'] += $saldo;
        }
        foreach ($neraca as &$sisiNeraca) {
            $sisiNeraca['subgrupList'] = array_values($sisiNeraca['subgrupList']);
        }
        unset($sisiNeraca);

        $neraca['labaBerjalan'] = $this->hitungLabaBerjalan();
        $neraca['totalPasiva']  = $neraca['hutang']['total'] + $neraca['ekuitas']['total'] + $neraca['labaBerjalan'];
        $neraca['selisih']      = $neraca['aktiva']['total'] - $neraca['totalPasiva'];

        return $neraca;
    }

    /** Σ (K − D) arus YTD semua akun grup PENDAPATAN (4) & BEBAN (5) — padanan NERACA2 / akun LRB. */
    private function hitungLabaBerjalan(): float
    {
        $accIdLabaRugi = DB::table('acmst_accounts')
            ->whereIn('gra_id', ['4', '5'])
            ->pluck('acc_id')
            ->map(fn ($accId) => (string) $accId)
            ->all();

        $laba = 0.0;
        foreach (Jurnal::arusPerAkun($accIdLabaRugi, $this->awalTahun, $this->tanggal) as $arus) {
            $laba += $arus['kredit'] - $arus['debit'];
        }

        return $laba;
    }

    #[Computed]
    public function totalAktiva(): float
    {
        return (float) $this->laporan['aktiva']['total'];
    }

    #[Computed]
    public function totalPasiva(): float
    {
        return (float) $this->laporan['totalPasiva'];
    }

    #[Computed]
    public function selisih(): float
    {
        return (float) $this->laporan['selisih'];
    }

    #[Computed]
    public function isBalanced(): bool
    {
        return abs($this->selisih) < 0.5;
    }
};
?>

<div x-data="{ tampilkanRincian: false }">
    <x-page-title
        title="Laporan Neraca"
        subtitle="Posisi keuangan per tanggal cutoff: Aktiva berbanding Hutang + Ekuitas + Laba Tahun Berjalan. Disusun dari grup akun master, nilai dibaca langsung dari tabel transaksi." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-4 pb-6">
            <div class="p-4 mb-4 border rounded-lg border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700">
                <div class="flex items-start gap-3">
                    <span class="text-xl leading-none">⚠️</span>
                    <div class="flex-1 text-sm text-amber-900 dark:text-amber-100">
                        <p class="font-semibold">Verifikasi manual sebelum dipakai sebagai laporan resmi.</p>
                        <ul class="mt-2 ml-5 space-y-0.5 text-xs list-disc">
                            <li><strong>Saldo awal tahun</strong> diambil dari <span class="font-mono">tktxn_saldoawalakuns</span> tahun {{ $this->tahun }} (kedua sisi D dan K). Bila belum lengkap, neraca tidak akan seimbang.</li>
                            <li><strong>Laba Tahun Berjalan</strong> = seluruh akun grup Pendapatan dan Beban s/d tanggal (termasuk HPP otomatis dari stok), bukan dari template Laba Rugi — bisa sedikit berbeda dari halaman Laba Rugi bila template tidak memuat semua akun.</li>
                            <li>Susunan: grup akun master (Aktiva / Hutang / Ekuitas) dan sub-grup <span class="font-mono">acmst_accgroups</span>; template <span class="font-mono">N1</span>/<span class="font-mono">NWEB</span> tidak dipakai karena isinya hanya beberapa baris. Sumber data: <span class="font-mono">App\Support\Keuangan\Jurnal</span>.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="w-48">
                            <x-input-label for="tanggal" value="Per Tanggal" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                            <x-text-input id="tanggal" type="date" wire:model.live="tanggal" class="block w-full" />
                        </div>
                        <label class="flex items-center gap-2 pb-2 text-sm cursor-pointer select-none text-body dark:text-gray-200">
                            <input type="checkbox" x-model="tampilkanRincian" class="w-4 h-4 rounded border-gray-300 text-brand-green focus:ring-brand-green/40 dark:border-gray-600 dark:bg-gray-900">
                            Tampilkan saldo awal &amp; mutasi
                        </label>
                    </div>

                    <div class="grid grid-cols-3 gap-3 text-right">
                        <div class="px-3 py-2 border rounded-lg bg-surface-soft border-hairline dark:bg-gray-800/40 dark:border-gray-700">
                            <div class="text-[10px] tracking-wider text-muted uppercase">Total Aktiva</div>
                            <div class="font-mono text-sm font-bold text-body dark:text-gray-100">{{ number_format($this->totalAktiva, 0, ',', '.') }}</div>
                        </div>
                        <div class="px-3 py-2 border rounded-lg bg-surface-soft border-hairline dark:bg-gray-800/40 dark:border-gray-700">
                            <div class="text-[10px] tracking-wider text-muted uppercase">Total Pasiva</div>
                            <div class="font-mono text-sm font-bold text-body dark:text-gray-100">{{ number_format($this->totalPasiva, 0, ',', '.') }}</div>
                        </div>
                        <div class="px-3 py-2 border rounded-lg {{ $this->isBalanced ? 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800' : 'bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:border-rose-800' }}">
                            <div class="text-[10px] tracking-wider uppercase {{ $this->isBalanced ? 'text-emerald-700 dark:text-emerald-300' : 'text-error dark:text-rose-300' }}">
                                {{ $this->isBalanced ? 'Seimbang' : 'Selisih' }}
                            </div>
                            <div class="font-mono text-sm font-bold {{ $this->isBalanced ? 'text-emerald-800 dark:text-emerald-200' : 'text-rose-800 dark:text-rose-200' }}">
                                {{ $this->isBalanced ? '✓' : number_format($this->selisih, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl" wire:loading.class="opacity-50">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-3 py-2 font-semibold w-28">KODE</th>
                                <th class="px-3 py-2 font-semibold">URAIAN</th>
                                <th class="px-3 py-2 font-semibold w-40 text-right" x-show="tampilkanRincian">SALDO AWAL</th>
                                <th class="px-3 py-2 font-semibold w-36 text-right" x-show="tampilkanRincian">DEBIT YTD</th>
                                <th class="px-3 py-2 font-semibold w-36 text-right" x-show="tampilkanRincian">KREDIT YTD</th>
                                <th class="px-3 py-2 font-semibold w-44 text-right">SALDO</th>
                            </tr>
                        </thead>
                        <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                            @foreach ([['kunci' => 'aktiva', 'judul' => 'AKTIVA'], ['kunci' => 'hutang', 'judul' => 'HUTANG'], ['kunci' => 'ekuitas', 'judul' => 'EKUITAS']] as $sisi)
                                @php $sisiNeraca = $this->laporan[$sisi['kunci']]; @endphp
                                <tr wire:key="nrc-sisi-{{ $sisi['kunci'] }}" class="bg-surface-soft dark:bg-gray-800">
                                    <td colspan="6" class="px-3 py-2 text-xs font-bold tracking-wider uppercase">{{ $sisi['judul'] }}</td>
                                </tr>
                                @forelse ($sisiNeraca['subgrupList'] as $subgrup)
                                    <tr wire:key="nrc-sub-{{ $sisi['kunci'] }}-{{ $subgrup['id'] }}" class="bg-surface-soft/60 dark:bg-gray-800/60">
                                        <td colspan="6" class="px-3 py-1.5 pl-6 text-xs font-semibold uppercase">{{ $subgrup['desc'] }}</td>
                                    </tr>
                                    @foreach ($subgrup['akunList'] as $akun)
                                        <tr wire:key="nrc-acc-{{ $akun['acc_id'] }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60 {{ $akun['aktif'] ? '' : 'text-muted' }}">
                                            <td class="px-3 py-1.5 font-mono text-xs">{{ $akun['acc_id'] }}</td>
                                            <td class="px-3 py-1.5 pl-9 text-sm">
                                                {{ $akun['acc_name'] ?: '—' }}
                                                @unless ($akun['aktif']) <span class="px-1 ml-1 text-[9px] rounded bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300">nonaktif</span> @endunless
                                            </td>
                                            <td class="px-3 py-1.5 font-mono text-xs text-right text-muted" x-show="tampilkanRincian">{{ number_format($akun['saldoAwal'], 0, ',', '.') }}</td>
                                            <td class="px-3 py-1.5 font-mono text-xs text-right text-muted" x-show="tampilkanRincian">{{ number_format($akun['arusDebit'], 0, ',', '.') }}</td>
                                            <td class="px-3 py-1.5 font-mono text-xs text-right text-muted" x-show="tampilkanRincian">{{ number_format($akun['arusKredit'], 0, ',', '.') }}</td>
                                            <td class="px-3 py-1.5 font-mono text-sm text-right {{ $akun['saldo'] < 0 ? 'text-error dark:text-rose-300' : '' }}">
                                                @if (abs($akun['saldo']) > 0.001) {{ number_format($akun['saldo'], 0, ',', '.') }} @else <span class="text-gray-300">—</span> @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr wire:key="nrc-subtotal-{{ $sisi['kunci'] }}-{{ $subgrup['id'] }}" class="font-semibold bg-surface-soft/40 dark:bg-gray-800/40">
                                        <td></td>
                                        <td class="px-3 py-1.5 pl-6 text-xs uppercase">Subtotal {{ $subgrup['desc'] }}</td>
                                        <td colspan="3" x-show="tampilkanRincian"></td>
                                        <td class="px-3 py-1.5 font-mono text-sm text-right">{{ number_format($subgrup['total'], 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr wire:key="nrc-kosong-{{ $sisi['kunci'] }}">
                                        <td colspan="6" class="px-3 py-2 pl-6 text-xs italic text-muted-soft">(Tidak ada akun)</td>
                                    </tr>
                                @endforelse

                                @if ($sisi['kunci'] === 'ekuitas')
                                    <tr class="font-semibold bg-blue-50/60 dark:bg-blue-900/10">
                                        <td class="px-3 py-1.5 font-mono text-xs text-muted-soft">LRB</td>
                                        <td class="px-3 py-1.5 pl-6 text-sm">Laba (Rugi) Tahun Berjalan s/d {{ \Carbon\Carbon::parse($tanggal)->format('d/m/Y') }}</td>
                                        <td colspan="3" x-show="tampilkanRincian"></td>
                                        <td class="px-3 py-1.5 font-mono text-sm text-right {{ $this->laporan['labaBerjalan'] < 0 ? 'text-error dark:text-rose-300' : 'text-blue-800 dark:text-blue-200' }}">{{ number_format($this->laporan['labaBerjalan'], 0, ',', '.') }}</td>
                                    </tr>
                                @endif

                                <tr class="font-bold bg-blue-50 dark:bg-blue-900/20">
                                    <td></td>
                                    <td class="px-3 py-2 text-sm uppercase">Total {{ $sisi['judul'] }}@if ($sisi['kunci'] === 'ekuitas') <span class="text-[10px] font-normal normal-case text-muted">termasuk laba tahun berjalan</span>@endif</td>
                                    <td colspan="3" x-show="tampilkanRincian"></td>
                                    <td class="px-3 py-2 font-mono text-sm text-right text-blue-800 dark:text-blue-200">
                                        {{ number_format($sisiNeraca['total'] + ($sisi['kunci'] === 'ekuitas' ? $this->laporan['labaBerjalan'] : 0), 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach

                            <tr class="font-bold {{ $this->isBalanced ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-rose-50 dark:bg-rose-900/20' }}">
                                <td></td>
                                <td class="px-3 py-2 text-sm uppercase">Total Pasiva (Hutang + Ekuitas)</td>
                                <td colspan="3" x-show="tampilkanRincian"></td>
                                <td class="px-3 py-2 font-mono text-base text-right">{{ number_format($this->totalPasiva, 0, ',', '.') }}</td>
                            </tr>
                            @unless ($this->isBalanced)
                                <tr class="italic bg-rose-50/60 dark:bg-rose-900/10">
                                    <td></td>
                                    <td class="px-3 py-2 text-xs text-error dark:text-rose-300">Selisih Aktiva − Pasiva (periksa saldo awal tahun dan akun tanpa grup)</td>
                                    <td colspan="3" x-show="tampilkanRincian"></td>
                                    <td class="px-3 py-2 font-mono text-sm text-right text-error dark:text-rose-300">{{ number_format($this->selisih, 0, ',', '.') }}</td>
                                </tr>
                            @endunless
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
