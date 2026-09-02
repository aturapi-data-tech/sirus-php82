<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Carbon\Carbon;

/**
 * Laporan Bulanan Operasi (Kamar Operasi) — area Monitoring Keuangan.
 *
 * Menjawab tiga pertanyaan sekaligus untuk satu bulan: siapa dokter operatornya,
 * siapa dokter anestesinya, dan berapa tarif operasinya sampai pos ON LOOP.
 *
 * SUMBER DATA: rstxn_oks, tabel yang sama dengan layar Daftar Kamar Operasi —
 * bukan tabel biaya di kunjungan induk (rstxn_{rj,ugd,ri}oks). Yang di induk
 * adalah SALINAN pos tarif untuk keperluan penagihan; yang di sini catatan
 * operasinya sendiri, satu baris satu tindakan operasi.
 *
 * TOTAL TARIF = 11 pos KamarOperasiTarif::POS, persis rumus yang dipakai layar
 * Daftar Kamar Operasi — supaya angka di laporan ini tidak pernah berbeda dengan
 * angka yang dilihat petugas OK. ON LOOP (omlop_fee) termasuk di dalamnya dan
 * juga ditampilkan sebagai kolom tersendiri.
 *
 * OPERASI BATAL DIKECUALIKAN: ok_status 'F' berarti DIBATALKAN (bukan final —
 * 'L' yang berarti selesai). Baris batal tarifnya masih tersimpan, jadi kalau
 * ikut dijumlah laporan keuangannya menggelembung.
 */
new class extends Component
{
    public string $filterBulan = '';

    public string $filterOperator = '';

    public string $filterLayanan = '';

    public function mount(): void
    {
        $this->filterBulan = Carbon::now(config('app.timezone'))->format('m/Y');
    }

    public function resetFilters(): void
    {
        $this->reset(['filterOperator', 'filterLayanan']);
        $this->filterBulan = Carbon::now(config('app.timezone'))->format('m/Y');
    }

    /**
     * Awal & akhir bulan terpilih.
     *
     * whereBetween pada kolom tanggal, BUKAN EXTRACT/to_char — fungsi di sisi
     * kolom mematikan pemakaian index ok_date (catatan yang sama ada di layar
     * Daftar Kamar Operasi).
     *
     * @return array{0: Carbon, 1: Carbon}|null null bila teks bulan tak terbaca
     */
    private function rentangBulan(): ?array
    {
        $teks = trim($this->filterBulan);

        if ($teks === '') {
            return null;
        }

        try {
            $awal = Carbon::createFromFormat('m/Y', $teks)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }

        return [$awal, (clone $awal)->endOfMonth()];
    }

    /**
     * Kunjungan induk bisa dari tiga layanan (status_rjri + ref_no).
     *
     * Selain identitas pasien, induknya juga membawa STATUS dan TANGGAL sendiri:
     *   - status  RJ/UGD: A menunggu bayar, L selesai bayar, I transfer, F batal
     *             RI    : I masih dirawat, P pulang, F batal
     *   - tanggal RJ/UGD: rj_date (tanggal kunjungan)
     *             RI    : entry_date — TANGGAL MASUK, bukan tanggal pulang
     *
     * Keduanya beda dari ok_date: satu pasien bisa masuk tanggal 1 dan dioperasi
     * tanggal 3, jadi laporan bulanan yang dipatok ok_date tetap benar sementara
     * kolom tanggal induk menunjukkan sejak kapan pasiennya dirawat.
     */
    private const OK_DENGAN_KUNJUNGAN = <<<'SQL'
        (SELECT k.*,
                NVL(k.status_rjri, 'RI') AS sumber,
                CASE NVL(k.status_rjri, 'RI')
                    WHEN 'RJ'  THEN (SELECT h.reg_no FROM rstxn_rjhdrs  h WHERE h.rj_no    = k.ref_no)
                    WHEN 'UGD' THEN (SELECT h.reg_no FROM rstxn_ugdhdrs h WHERE h.rj_no    = k.ref_no)
                    ELSE            (SELECT h.reg_no FROM rstxn_rihdrs  h WHERE h.rihdr_no = NVL(k.ref_no, k.rihdr_no))
                END AS reg_no,
                CASE NVL(k.status_rjri, 'RI')
                    WHEN 'RJ'  THEN (SELECT h.rj_status FROM rstxn_rjhdrs  h WHERE h.rj_no    = k.ref_no)
                    WHEN 'UGD' THEN (SELECT h.rj_status FROM rstxn_ugdhdrs h WHERE h.rj_no    = k.ref_no)
                    ELSE            (SELECT h.ri_status FROM rstxn_rihdrs  h WHERE h.rihdr_no = NVL(k.ref_no, k.rihdr_no))
                END AS status_induk,
                CASE NVL(k.status_rjri, 'RI')
                    WHEN 'RJ'  THEN (SELECT to_char(h.rj_date,'dd/mm/yyyy')    FROM rstxn_rjhdrs  h WHERE h.rj_no    = k.ref_no)
                    WHEN 'UGD' THEN (SELECT to_char(h.rj_date,'dd/mm/yyyy')    FROM rstxn_ugdhdrs h WHERE h.rj_no    = k.ref_no)
                    ELSE            (SELECT to_char(h.entry_date,'dd/mm/yyyy') FROM rstxn_rihdrs  h WHERE h.rihdr_no = NVL(k.ref_no, k.rihdr_no))
                END AS tgl_induk
           FROM rstxn_oks k)
        SQL;

    /** Rumus total 11 pos — sama persis dengan Daftar Kamar Operasi. */
    private const TOTAL_TARIF = 'NVL(o.oprdoc_fee,0) + NVL(o.anesdoc_fee,0) + NVL(o.changeanesdoc_fee,0)
        + NVL(o.instrument_fee,0) + NVL(o.asistopr_fee,0) + NVL(o.asistanes_fee,0)
        + NVL(o.omlop_fee,0) + NVL(o.ok_fee,0) + NVL(o.rr_fee,0)
        + NVL(o.equipment_fee,0) + NVL(o.rentequipment_fee,0)';

    private function baseQuery()
    {
        $rentang = $this->rentangBulan();

        if ($rentang === null) {
            return null;
        }

        [$awal, $akhir] = $rentang;

        $query = DB::table(DB::raw(self::OK_DENGAN_KUNJUNGAN . ' o'))
            ->whereBetween('o.ok_date', [$awal, $akhir])
            // 'F' = DIBATALKAN. Tarifnya masih tersimpan di baris itu, jadi kalau
            // ikut terjumlah laporan keuangannya menggelembung.
            ->where(function ($cabang) {
                $cabang->whereNull('o.ok_status')->orWhere('o.ok_status', '!=', 'F');
            });

        if ($this->filterOperator !== '') {
            $query->where('o.dr_id', $this->filterOperator);
        }

        if (in_array($this->filterLayanan, ['RJ', 'UGD', 'RI'], true)) {
            $query->where(DB::raw("NVL(o.status_rjri, 'RI')"), $this->filterLayanan);
        }

        return $query;
    }

    /** Dokter operator yang punya operasi di bulan terpilih. */
    #[Computed]
    public function operatorList()
    {
        $rentang = $this->rentangBulan();

        if ($rentang === null) {
            return collect();
        }

        return DB::table('rstxn_oks as o')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 'o.dr_id')
            ->select('o.dr_id', DB::raw('MAX(d.dr_name) as dr_name'))
            ->whereBetween('o.ok_date', $rentang)
            ->where(function ($cabang) {
                $cabang->whereNull('o.ok_status')->orWhere('o.ok_status', '!=', 'F');
            })
            ->groupBy('o.dr_id')
            ->orderBy(DB::raw('MAX(d.dr_name)'))
            ->get();
    }

    /** Rincian per operasi. */
    #[Computed]
    public function rows()
    {
        $query = $this->baseQuery();

        if ($query === null) {
            return collect();
        }

        return $query
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'o.reg_no')
            ->leftJoin('rsmst_doctors as dopr', 'dopr.dr_id', '=', 'o.dr_id')
            ->leftJoin('rsmst_doctors as danes', 'danes.dr_id', '=', 'o.dr_id_ok')
            ->select(
                'o.ok_reg',
                'o.sumber',
                'o.status_induk',
                'o.tgl_induk',
                'o.reg_no',
                'p.reg_name',
                'dopr.dr_name as operator_name',
                'danes.dr_name as anestesi_name',
                DB::raw("to_char(o.ok_date,'dd/mm/yyyy hh24:mi') as ok_date_display"),
                DB::raw('NVL(o.oprdoc_fee,0) as oprdoc_fee'),
                DB::raw('NVL(o.anesdoc_fee,0) as anesdoc_fee'),
                DB::raw('NVL(o.omlop_fee,0) as omlop_fee'),
                // Sisa pos di luar tiga kolom yang ditampilkan sendiri.
                DB::raw('NVL(o.changeanesdoc_fee,0) + NVL(o.instrument_fee,0) + NVL(o.asistopr_fee,0)
                        + NVL(o.asistanes_fee,0) + NVL(o.ok_fee,0) + NVL(o.rr_fee,0)
                        + NVL(o.equipment_fee,0) + NVL(o.rentequipment_fee,0) as lainnya_fee'),
                DB::raw('(' . self::TOTAL_TARIF . ') as total_fee'),
                DB::raw("(
                    SELECT string_agg(a.accdoc_desc)
                    FROM rstxn_okacts t
                    JOIN rsmst_accdocs a ON a.accdoc_id = t.accdoc_id
                    WHERE t.ok_reg = o.ok_reg
                ) AS tindakan_desc"),
            )
            ->orderBy('o.ok_date')
            ->get();
    }

    /** Rekap per dokter operator — pertanyaan "siapa paling banyak dan berapa". */
    #[Computed]
    public function rekapOperator()
    {
        $query = $this->baseQuery();

        if ($query === null) {
            return collect();
        }

        return $query
            ->leftJoin('rsmst_doctors as dopr', 'dopr.dr_id', '=', 'o.dr_id')
            ->select(
                'o.dr_id',
                DB::raw("NVL(MAX(dopr.dr_name), '(tanpa operator)') as dr_name"),
                DB::raw('COUNT(*) as jumlah'),
                DB::raw('SUM(NVL(o.oprdoc_fee,0)) as oprdoc_fee'),
                DB::raw('SUM(' . self::TOTAL_TARIF . ') as total_fee'),
            )
            ->groupBy('o.dr_id')
            ->orderByRaw('SUM(' . self::TOTAL_TARIF . ') DESC')
            ->get();
    }

    /** Kartu ringkasan di kepala laporan. */
    #[Computed]
    public function ringkasan(): array
    {
        $baris = $this->rows;

        return [
            'jumlah' => $baris->count(),
            'oprdoc' => (int) $baris->sum('oprdoc_fee'),
            'anesdoc' => (int) $baris->sum('anesdoc_fee'),
            'omlop' => (int) $baris->sum('omlop_fee'),
            'total' => (int) $baris->sum('total_fee'),
        ];
    }
};
?>

<div>
    <x-page-title
        title="Laporan Bulanan Operasi"
        subtitle="Rekap tindakan kamar operasi per bulan — dokter operator, dokter anestesi & tarif sampai ON LOOP" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            <div class="flex flex-wrap items-center justify-end gap-2 mb-4">
                <a href="{{ route('manajemen.monitoring-keuangan') }}" wire:navigate
                    class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-body bg-canvas border border-gray-300 rounded-lg hover:bg-surface-soft dark:bg-gray-900 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-800 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Kembali
                </a>
            </div>

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan" />
                        <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                            class="mt-1 block w-full sm:w-32" placeholder="mm/yyyy" maxlength="7" />
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter Operator" />
                        <x-select-input wire:model.live="filterOperator" class="mt-1 block w-full sm:w-64">
                            <option value="">— Semua Operator —</option>
                            @foreach ($this->operatorList as $dokter)
                                <option value="{{ $dokter->dr_id }}">{{ $dokter->dr_name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Layanan" />
                        <x-select-input wire:model.live="filterLayanan" class="mt-1 block w-full sm:w-40">
                            <option value="">Semua</option>
                            <option value="RJ">Rawat Jalan</option>
                            <option value="UGD">UGD</option>
                            <option value="RI">Rawat Inap</option>
                        </x-select-input>
                    </div>

                    <div class="pb-0.5">
                        <x-secondary-button wire:click="resetFilters" type="button">Reset</x-secondary-button>
                    </div>
                </div>
            </div>

            {{-- RINGKASAN --}}
            @php $ringkasan = $this->ringkasan; @endphp
            <div class="grid grid-cols-2 gap-3 mt-4 lg:grid-cols-5">
                <div class="p-3 border bg-canvas border-hairline rounded-xl dark:bg-gray-900 dark:border-gray-700">
                    <div class="text-xs uppercase text-muted dark:text-gray-400">Jumlah Operasi</div>
                    <div class="mt-1 text-2xl font-bold text-ink dark:text-gray-100">{{ $ringkasan['jumlah'] }}</div>
                    <div class="text-[10px] text-muted dark:text-gray-400">batal tidak dihitung</div>
                </div>
                <div class="p-3 border bg-emerald-50 border-emerald-200 rounded-xl dark:bg-emerald-900/20 dark:border-emerald-700">
                    <div class="text-xs uppercase text-emerald-700 dark:text-emerald-300">Jasa Operator</div>
                    <div class="mt-1 text-lg font-bold text-emerald-800 dark:text-emerald-200">
                        {{ number_format($ringkasan['oprdoc'], 0, ',', '.') }}
                    </div>
                </div>
                <div class="p-3 border bg-blue-50 border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-700">
                    <div class="text-xs text-blue-700 uppercase dark:text-blue-300">Jasa Anestesi</div>
                    <div class="mt-1 text-lg font-bold text-blue-800 dark:text-blue-200">
                        {{ number_format($ringkasan['anesdoc'], 0, ',', '.') }}
                    </div>
                </div>
                <div class="p-3 border bg-amber-50 border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-700">
                    <div class="text-xs uppercase text-amber-700 dark:text-amber-300">Biaya ON LOOP</div>
                    <div class="mt-1 text-lg font-bold text-amber-800 dark:text-amber-200">
                        {{ number_format($ringkasan['omlop'], 0, ',', '.') }}
                    </div>
                </div>
                <div class="p-3 border bg-surface-soft border-hairline rounded-xl dark:bg-gray-800 dark:border-gray-700">
                    <div class="text-xs uppercase text-muted dark:text-gray-400">Total Tarif</div>
                    <div class="mt-1 text-lg font-bold text-ink dark:text-gray-100">
                        {{ number_format($ringkasan['total'], 0, ',', '.') }}
                    </div>
                    <div class="text-[10px] text-muted dark:text-gray-400">11 pos, termasuk ON LOOP</div>
                </div>
            </div>

            {{-- REKAP PER OPERATOR --}}
            <h3 class="mt-6 mb-2 text-sm font-semibold tracking-wider uppercase text-muted dark:text-gray-400">
                Rekap per Dokter Operator
            </h3>
            <div class="overflow-x-auto rounded-xl">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>Dokter Operator</th>
                            <th class="ds-c">Jumlah Operasi</th>
                            <th class="ds-r">Jasa Operator</th>
                            <th class="ds-r">Total Tarif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rekapOperator as $rekap)
                            <tr wire:key="rekap-{{ $rekap->dr_id ?? 'x' }}">
                                <td class="ds-td-strong">{{ $rekap->dr_name }}</td>
                                <td class="ds-c">{{ $rekap->jumlah }}</td>
                                <td class="ds-r">{{ number_format((int) $rekap->oprdoc_fee, 0, ',', '.') }}</td>
                                <td class="ds-r ds-td-strong">{{ number_format((int) $rekap->total_fee, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-muted dark:text-gray-400">
                                    Belum ada operasi pada bulan ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- RINCIAN PER OPERASI --}}
            <h3 class="mt-6 mb-2 text-sm font-semibold tracking-wider uppercase text-muted dark:text-gray-400">
                Rincian Operasi
            </h3>
            <div class="overflow-x-auto rounded-xl">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>Tgl Operasi</th>
                            <th>Tgl Masuk</th>
                            <th>Pasien</th>
                            <th class="ds-c">Layanan</th>
                            <th class="ds-c">Status Kunjungan</th>
                            <th>Tindakan</th>
                            <th>Dokter Operator</th>
                            <th>Dokter Anestesi</th>
                            <th class="ds-r">Jasa Operator</th>
                            <th class="ds-r">Jasa Anestesi</th>
                            <th class="ds-r">ON LOOP</th>
                            <th class="ds-r">Pos Lain</th>
                            <th class="ds-r">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $row)
                            <tr wire:key="operasi-{{ $row->ok_reg }}">
                                @php
                                    $sumber = strtoupper($row->sumber ?? 'RI');
                                    $statusInduk = strtoupper($row->status_induk ?? '');

                                    // Kode status DIBACA BEDA per jalur: 'I' di RJ/UGD berarti
                                    // dipindah ke UGD, sedangkan 'I' di RI berarti masih dirawat.
                                    // Satu match() untuk keduanya akan salah melabeli.
                                    [$statusLabel, $statusVariant] = $sumber === 'RI'
                                        ? match ($statusInduk) {
                                            'I' => ['Masih Dirawat', 'info'],
                                            'P' => ['Pulang', 'success'],
                                            'F' => ['Batal', 'danger'],
                                            default => ['-', 'gray'],
                                        }
                                        : match ($statusInduk) {
                                            'A' => ['Menunggu Pembayaran', 'warning'],
                                            'L' => ['Lunas', 'success'],
                                            'I' => ['Transfer UGD', 'info'],
                                            'F' => ['Batal', 'danger'],
                                            default => ['-', 'gray'],
                                        };
                                @endphp
                                <td class="ds-td-token">{{ $row->ok_date_display ?? '-' }}</td>
                                <td class="ds-td-token">{{ $row->tgl_induk ?? '-' }}</td>
                                <td>
                                    <div class="font-medium text-ink dark:text-gray-100">{{ $row->reg_name ?? '-' }}</div>
                                    <div class="text-xs text-muted dark:text-gray-400">{{ $row->reg_no ?? '-' }}</div>
                                </td>
                                <td class="ds-c">
                                    <x-badge :variant="match ($sumber) { 'RJ' => 'info', 'UGD' => 'warning', default => 'success' }">
                                        {{ $sumber }}
                                    </x-badge>
                                </td>
                                <td class="ds-c">
                                    <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
                                </td>
                                <td class="max-w-[16rem]">{{ $row->tindakan_desc ?: '-' }}</td>
                                <td>{{ $row->operator_name ?? '-' }}</td>
                                <td>{{ $row->anestesi_name ?? '-' }}</td>
                                <td class="ds-r">{{ number_format((int) $row->oprdoc_fee, 0, ',', '.') }}</td>
                                <td class="ds-r">{{ number_format((int) $row->anesdoc_fee, 0, ',', '.') }}</td>
                                <td class="ds-r">{{ number_format((int) $row->omlop_fee, 0, ',', '.') }}</td>
                                <td class="ds-r">{{ number_format((int) $row->lainnya_fee, 0, ',', '.') }}</td>
                                <td class="ds-r ds-td-strong">{{ number_format((int) $row->total_fee, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="py-6 text-center text-muted dark:text-gray-400">
                                    Belum ada operasi pada bulan ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
