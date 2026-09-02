<?php

use App\Support\KamarOperasiTarif;
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

    /**
     * Tab aktif: 'operator' | 'anestesi' | 'rincian'.
     *
     * Dipegang di server, bukan Alpine x-show, supaya HANYA tabel tab aktif yang
     * dirender dan computed tab lain tak ikut dijalankan — tiga tabel sekaligus
     * berarti tiga query berat untuk satu layar yang cuma menampilkan satu.
     */
    public string $tab = 'operator';

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['operator', 'anestesi', 'rincian'], true) ? $tab : 'operator';
    }

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

    /**
     * Pemilah "jumlah operasi" menurut keadaan pasiennya sekarang.
     *
     * Kode status induk TIDAK SERAGAM antar jalur, jadi dipilah per jalur:
     *   RI     : 'I' masih dirawat, 'P' pulang
     *   RJ/UGD : pasien pulang hari itu juga; yang menandai kelar adalah 'L'
     *            (selesai pembayaran), bukan 'P' — kode 'P' tak dipakai di sana.
     *
     * Ketiga kolom ini SELALU berjumlah sama dengan COUNT(*), supaya angkanya
     * bisa diadu dan tak ada baris yang menghilang tanpa jejak. "Lainnya"
     * menampung yang belum kelar (RJ/UGD 'A'), pindah jalur ('I' di RJ/UGD),
     * induk batal ('F'), dan status kosong.
     */
    private const MASIH_DIRAWAT = "CASE WHEN NVL(o.status_rjri,'RI') = 'RI' AND o.status_induk = 'I' THEN 1 ELSE 0 END";

    private const SUDAH_PULANG = "CASE WHEN (NVL(o.status_rjri,'RI') = 'RI'  AND o.status_induk = 'P')
              OR (NVL(o.status_rjri,'RI') <> 'RI' AND o.status_induk = 'L') THEN 1 ELSE 0 END";

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
                'p.sex',
                'p.address',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                'dopr.dr_name as operator_name',
                'danes.dr_name as anestesi_name',
                DB::raw("to_char(o.ok_date,'dd/mm/yyyy hh24:mi') as ok_date_display"),
                DB::raw('(' . self::TOTAL_TARIF . ') as total_fee'),
                DB::raw("(
                    SELECT string_agg(a.accdoc_desc)
                    FROM rstxn_okacts t
                    JOIN rsmst_accdocs a ON a.accdoc_id = t.accdoc_id
                    WHERE t.ok_reg = o.ok_reg
                ) AS tindakan_desc"),
                // 11 pos dirinci satu per satu, daftarnya diambil dari
                // KamarOperasiTarif::POS supaya tak ada pos yang terlewat kalau
                // kelak master tarifnya bertambah — dan tak perlu ditulis ulang
                // di header tabel. Spread WAJIB argumen terakhir.
                ...array_map(
                    fn (string $kolom) => DB::raw("NVL(o.{$kolom},0) as {$kolom}"),
                    array_keys(KamarOperasiTarif::POS)
                ),
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
                DB::raw('SUM(' . self::MASIH_DIRAWAT . ') as masih_dirawat'),
                DB::raw('SUM(' . self::SUDAH_PULANG . ') as sudah_pulang'),
                DB::raw('COUNT(*) - SUM(' . self::MASIH_DIRAWAT . ') - SUM(' . self::SUDAH_PULANG . ') as lainnya'),
                DB::raw('SUM(NVL(o.oprdoc_fee,0)) as oprdoc_fee'),
                DB::raw('SUM(' . self::TOTAL_TARIF . ') as total_fee'),
            )
            ->groupBy('o.dr_id')
            ->orderByRaw('SUM(' . self::TOTAL_TARIF . ') DESC')
            ->get();
    }

    /**
     * Rekap per dokter ANESTESI — pasangan rekapOperator().
     *
     * Dikelompokkan pada dr_id_ok (kolom dokter anestesi di rstxn_oks), bukan
     * dr_id. Angka yang benar-benar milik dokter anestesi adalah anesdoc_fee;
     * "Total Tarif" ikut ditampilkan supaya sebanding dengan tabel operator,
     * TAPI itu nilai penuh operasinya, bukan penghasilan si anestesi — operasi
     * yang sama juga terhitung di baris operatornya. Jangan menjumlahkan kedua
     * tabel: hasilnya dobel.
     */
    #[Computed]
    public function rekapAnestesi()
    {
        $query = $this->baseQuery();

        if ($query === null) {
            return collect();
        }

        return $query
            ->leftJoin('rsmst_doctors as danes2', 'danes2.dr_id', '=', 'o.dr_id_ok')
            ->select(
                'o.dr_id_ok',
                DB::raw("NVL(MAX(danes2.dr_name), '(tanpa dokter anestesi)') as dr_name"),
                DB::raw('COUNT(*) as jumlah'),
                DB::raw('SUM(' . self::MASIH_DIRAWAT . ') as masih_dirawat'),
                DB::raw('SUM(' . self::SUDAH_PULANG . ') as sudah_pulang'),
                DB::raw('COUNT(*) - SUM(' . self::MASIH_DIRAWAT . ') - SUM(' . self::SUDAH_PULANG . ') as lainnya'),
                DB::raw('SUM(NVL(o.anesdoc_fee,0)) as anesdoc_fee'),
                DB::raw('SUM(NVL(o.changeanesdoc_fee,0)) as changeanesdoc_fee'),
                DB::raw('SUM(' . self::TOTAL_TARIF . ') as total_fee'),
            )
            ->groupBy('o.dr_id_ok')
            ->orderByRaw('SUM(NVL(o.anesdoc_fee,0)) DESC')
            ->get();
    }

    /**
     * Ekspor CSV — rincian per operasi, satu baris satu operasi.
     *
     * Mengikuti tab yang sedang dibuka SECARA SENGAJA TIDAK: yang diekspor selalu
     * rinciannya, bukan rekap yang sedang tampil. Rekap per dokter bisa dibuat
     * ulang dari rincian lewat pivot, sebaliknya tidak bisa.
     *
     * Bentuk berkas mengikuti pola ekspor repo (lihat Gizi RI): BOM UTF-8 supaya
     * Excel tak membacanya sebagai ANSI, pemisah titik-koma mengikuti Excel
     * ber-locale Indonesia, dan angka ditulis MENTAH tanpa pemisah ribuan supaya
     * tetap terbaca sebagai angka, bukan teks.
     */
    public function exportCsv()
    {
        $baris = $this->rows;
        $rentang = $this->rentangBulan();
        $labelBulan = $rentang === null ? 'semua' : $rentang[0]->format('m-Y');
        $namaBerkas = 'laporan-operasi-' . $labelBulan . '.csv';

        // Tanggal ekspor ikut di SETIAP baris, bukan jadi baris judul terpisah —
        // gabungan beberapa berkas ekspor tetap bisa dipivot per tanggal.
        $tglExport = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $posLabel = KamarOperasiTarif::LABEL;

        return response()->streamDownload(function () use ($baris, $tglExport, $posLabel) {
            $keluaran = fopen('php://output', 'w');

            // BOM UTF-8 — tanpa ini Excel membaca berkas sebagai ANSI.
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, [
                'Tgl Export', 'Tgl Operasi', 'Tgl Masuk', 'No RM', 'Nama Pasien', 'JK',
                'Layanan', 'Status Kunjungan', 'Tindakan', 'Dokter Operator', 'Dokter Anestesi',
                ...array_values($posLabel), 'Total Tarif',
            ], ';');

            foreach ($baris as $row) {
                $sumber = strtoupper($row->sumber ?? 'RI');
                $status = strtoupper($row->status_induk ?? '');

                // Label status dibaca PER JALUR — 'I' di RJ/UGD berarti transfer,
                // 'I' di RI berarti masih dirawat.
                $statusLabel = $sumber === 'RI'
                    ? match ($status) { 'I' => 'Masih Dirawat', 'P' => 'Pulang', 'F' => 'Batal', default => '' }
                    : match ($status) { 'A' => 'Menunggu Pembayaran', 'L' => 'Lunas', 'I' => 'Transfer UGD', 'F' => 'Batal', default => '' };

                fputcsv($keluaran, [
                    $tglExport,
                    $row->ok_date_display,
                    $row->tgl_induk,
                    $row->reg_no,
                    $row->reg_name,
                    $row->sex ?? '',
                    $sumber,
                    $statusLabel,
                    $row->tindakan_desc,
                    $row->operator_name,
                    $row->anestesi_name,
                    // Angka MENTAH tanpa pemisah ribuan — begitu diberi titik,
                    // Excel membacanya sebagai teks dan tak bisa dijumlah.
                    ...array_map(fn (string $kolom) => (int) $row->{$kolom}, array_keys($posLabel)),
                    (int) $row->total_fee,
                ], ';');
            }

            fclose($keluaran);
        }, $namaBerkas, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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

    {{-- Kerangka meniru Slip Gaji Dokter: tinggi layar dikunci lalu dibagi
         flex-kolom, jadi yang menggulung isinya — toolbar & tab tetap di tempat.
         Latar surface-soft dengan kartu tabel canvas. --}}
    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-0 pb-6">


            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 pt-1 pb-2 bg-surface-soft border-b border-hairline top-16 dark:bg-gray-900 dark:border-gray-700">
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

                    {{-- Tombol didorong ke kanan dengan ml-auto dan Kembali ikut di
                         baris ini — sama seperti Slip Gaji Dokter. Sebelumnya Kembali
                         berdiri sendiri di baris atas dan memakan satu baris penuh
                         hanya untuk satu tombol. --}}
                    <div class="flex flex-wrap items-end gap-2 ml-auto">
                        {{-- Ekspor CSV selalu berisi RINCIAN per operasi, apa pun tab
                             yang sedang dibuka: rekap per dokter bisa dibuat ulang dari
                             rincian lewat pivot, sebaliknya tidak bisa. Nonaktif saat
                             bulannya kosong supaya tak mengunduh berkas berisi judul saja. --}}
                        <x-info-button type="button" class="gap-2" wire:click="exportCsv"
                            wire:loading.attr="disabled" wire:target="exportCsv"
                            :disabled="$this->ringkasan['jumlah'] === 0"
                            title="Unduh rincian operasi bulan ini sebagai CSV — satu baris satu operasi, 11 pos tarif dirinci">
                            <span wire:loading.remove wire:target="exportCsv" class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Export CSV
                            </span>
                            <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-2">
                                <x-loading /> Menyiapkan...
                            </span>
                        </x-info-button>

                        {{-- Tombol baku toolbar list: Refresh (muat ulang tanpa mengubah
                             filter) + Reset (kembalikan filter ke awal). --}}
                        <x-toolbar-refresh-reset :label="null" />

                        <a href="{{ route('manajemen.monitoring-keuangan') }}" wire:navigate
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-body bg-canvas border border-gray-300 rounded-lg hover:bg-surface-soft dark:bg-gray-900 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-800 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Kembali
                        </a>
                    </div>
                </div>

                <p class="mt-2 text-sm text-muted dark:text-gray-400">
                    Laporan dipatok <span class="font-semibold">tanggal operasi</span>; kolom Tgl Masuk menunjukkan
                    sejak kapan pasiennya dirawat. Operasi berstatus batal tidak ikut dihitung.
                </p>
            </div>

            {{-- RINGKASAN — panel LIPAT bergaya Slip Gaji Dokter, default TERTUTUP.
                 Angka pentingnya tetap terbaca di bilah judul walau tertutup, jadi
                 lima kartu tak perlu memakan tinggi layar terus-menerus. Sengaja
                 BUKAN gaya biru-info: biru dicadangkan untuk panel panduan. --}}
            @php $ringkasan = $this->ringkasan; @endphp
            <div x-data="{ buka: false }"
                class="mt-4 overflow-hidden border rounded-2xl border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                <button type="button" x-on:click="buka = !buka"
                    class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-semibold transition-colors text-ink hover:bg-surface-soft dark:text-gray-100 dark:hover:bg-gray-800">
                    <span class="flex items-center min-w-0 gap-2">
                        <svg class="w-4 h-4 shrink-0 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 17V7m4 10V11m4 6V9M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <span class="shrink-0">Ringkasan bulan ini</span>
                        <span class="text-sm font-normal truncate text-muted dark:text-gray-400">
                            ({{ $ringkasan['jumlah'] }} operasi &middot; total
                            {{ number_format($ringkasan['total'], 0, ',', '.') }})
                        </span>
                    </span>
                    <svg class="w-4 h-4 ml-2 transition-transform shrink-0 text-muted" x-bind:class="buka && 'rotate-180'"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                {{-- Lima angka dalam SATU baris: kotak kartu dilepas, tinggal label
                     kecil di atas nilai, dipisah garis tipis. --}}
                <div x-show="buka" x-cloak
                    class="grid grid-cols-5 px-4 pb-4 divide-x divide-hairline dark:divide-gray-700">

                    <div class="px-2 first:pl-0 last:pr-0">
                        <div class="text-sm text-muted dark:text-gray-400">Operasi</div>
                        <div class="font-semibold t-num text-ink dark:text-gray-100">{{ $ringkasan['jumlah'] }}</div>
                        <div class="text-sm text-muted dark:text-gray-400 whitespace-nowrap">batal tidak dihitung</div>
                    </div>

                    <div class="px-2">
                        <div class="text-sm text-muted dark:text-gray-400">Jasa Operator</div>
                        <div class="font-semibold t-num text-emerald-700 dark:text-emerald-300">
                            {{ number_format($ringkasan['oprdoc'], 0, ',', '.') }}
                        </div>
                    </div>

                    <div class="px-2">
                        <div class="text-sm text-muted dark:text-gray-400">Jasa Anestesi</div>
                        <div class="font-semibold t-num text-blue-700 dark:text-blue-300">
                            {{ number_format($ringkasan['anesdoc'], 0, ',', '.') }}
                        </div>
                    </div>

                    <div class="px-2">
                        <div class="text-sm text-muted dark:text-gray-400">Biaya ON LOOP</div>
                        <div class="font-semibold t-num text-amber-700 dark:text-amber-300">
                            {{ number_format($ringkasan['omlop'], 0, ',', '.') }}
                        </div>
                    </div>

                    <div class="px-2 last:pr-0">
                        <div class="text-sm text-muted dark:text-gray-400">Total Tarif</div>
                        <div class="font-semibold t-num text-ink dark:text-gray-100">
                            {{ number_format($ringkasan['total'], 0, ',', '.') }}
                        </div>
                        <div class="text-sm text-muted dark:text-gray-400 whitespace-nowrap">11 pos, termasuk ON LOOP</div>
                    </div>

                </div>
            </div>

            {{-- TAB --}}
            {{-- Tab dipegang server (bukan Alpine x-show) supaya hanya tabel tab aktif
                 yang dirender — tiga tabel sekaligus berarti tiga query berat untuk satu
                 layar yang cuma menampilkan satu. --}}
            <div class="mt-4">
                <x-tabs variant="underline">
                    <x-tab :active="$tab === 'operator'" color="emerald" wire:click="setTab('operator')">
                        Rekap Dokter Operator
                    </x-tab>
                    <x-tab :active="$tab === 'anestesi'" color="blue" wire:click="setTab('anestesi')">
                        Rekap Dokter Anestesi
                    </x-tab>
                    <x-tab :active="$tab === 'rincian'" color="brand" wire:click="setTab('rincian')">
                        Rincian Operasi
                    </x-tab>
                </x-tabs>
            </div>

            {{-- Kartu tabel bergaya Slip Gaji Dokter: canvas ber-ring, sudut 2xl,
                 kepala kolom sticky, isi yang menggulung. --}}
            <div class="flex flex-col flex-1 min-h-0 mt-3 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                @if ($tab === 'operator')
            {{-- REKAP PER OPERATOR --}}
            <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>Dokter Operator</th>
                            <th class="ds-c">Jumlah Operasi</th>
                            <th class="ds-c">Sudah Pulang</th>
                            <th class="ds-c">Masih Dirawat</th>
                            <th class="ds-c">Lainnya</th>
                            <th class="ds-r">Jasa Operator</th>
                            <th class="ds-r">Total Tarif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rekapOperator as $rekap)
                            <tr wire:key="rekap-{{ $rekap->dr_id ?? 'x' }}">
                                <td class="ds-td-strong">{{ $rekap->dr_name }}</td>
                                <td class="ds-c ds-td-strong">{{ $rekap->jumlah }}</td>
                                <td class="ds-c">{{ (int) $rekap->sudah_pulang ?: '-' }}</td>
                                <td class="ds-c">{{ (int) $rekap->masih_dirawat ?: '-' }}</td>
                                <td class="ds-c">{{ (int) $rekap->lainnya ?: '-' }}</td>
                                <td class="ds-r">{{ number_format((int) $rekap->oprdoc_fee, 0, ',', '.') }}</td>
                                <td class="ds-r ds-td-strong">{{ number_format((int) $rekap->total_fee, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-muted dark:text-gray-400">
                                    Belum ada operasi pada bulan ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="mt-1 text-xs text-muted dark:text-gray-400">
                <span class="font-medium">Sudah Pulang</span> = rawat inap berstatus Pulang, plus RJ/UGD yang
                pembayarannya selesai. <span class="font-medium">Masih Dirawat</span> = rawat inap yang belum pulang.
                <span class="font-medium">Lainnya</span> = RJ/UGD belum selesai bayar, pindah jalur, atau kunjungan
                induknya batal. Ketiganya selalu berjumlah sama dengan Jumlah Operasi.
            </p>
                @elseif ($tab === 'anestesi')
            {{-- REKAP PER ANESTESI --}}
            <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>Dokter Anestesi</th>
                            <th class="ds-c">Jumlah Operasi</th>
                            <th class="ds-c">Sudah Pulang</th>
                            <th class="ds-c">Masih Dirawat</th>
                            <th class="ds-c">Lainnya</th>
                            <th class="ds-r">Jasa Anestesi</th>
                            <th class="ds-r">Jasa Pengganti</th>
                            <th class="ds-r">Total Tarif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rekapAnestesi as $rekap)
                            <tr wire:key="rekap-anes-{{ $rekap->dr_id_ok ?? 'x' }}">
                                <td class="ds-td-strong">{{ $rekap->dr_name }}</td>
                                <td class="ds-c ds-td-strong">{{ $rekap->jumlah }}</td>
                                <td class="ds-c">{{ (int) $rekap->sudah_pulang ?: '-' }}</td>
                                <td class="ds-c">{{ (int) $rekap->masih_dirawat ?: '-' }}</td>
                                <td class="ds-c">{{ (int) $rekap->lainnya ?: '-' }}</td>
                                <td class="ds-r ds-td-strong">{{ number_format((int) $rekap->anesdoc_fee, 0, ',', '.') }}</td>
                                <td class="ds-r">{{ number_format((int) $rekap->changeanesdoc_fee, 0, ',', '.') }}</td>
                                <td class="ds-r">{{ number_format((int) $rekap->total_fee, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-6 text-center text-muted dark:text-gray-400">
                                    Belum ada operasi pada bulan ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{-- "Total Tarif" di tabel ini adalah nilai penuh operasinya, bukan
                 penghasilan dokter anestesi — operasi yang sama juga terhitung di
                 tabel operator. Menjumlahkan kedua tabel akan dobel. --}}
            <p class="mt-1 text-xs text-muted dark:text-gray-400">
                Kolom Total Tarif adalah nilai penuh operasi, bukan penghasilan dokter anestesi &mdash;
                operasi yang sama juga muncul di rekap operator. Jangan menjumlahkan kedua tabel.
            </p>
                @else
            {{-- RINCIAN PER OPERASI --}}
            <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                <table class="ds-table">
                    <thead>
                        <tr>
                            {{-- Kolom yang berpasangan disatukan jadi satu sel bertingkat:
                                 tanggal (operasi + masuk), kunjungan (jalur + status),
                                 dan dokter (operator + anestesi). Kolom angka tetap
                                 terpisah supaya mudah dibandingkan antar baris. --}}
                            <th>Tanggal</th>
                            <th>Pasien</th>
                            <th>Tindakan &amp; Dokter</th>
                            <th>Rincian Tarif</th>
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
                                <td class="whitespace-nowrap">
                                    <div class="ds-td-token">{{ $row->ok_date_display ?? '-' }}</div>
                                    <div class="text-xs text-muted dark:text-gray-400">
                                        masuk {{ $row->tgl_induk ?? '-' }}
                                    </div>
                                </td>
                                {{-- Identitas pasien memakai komponen baku list transaksi
                                     (acuannya Pelayanan RJ), jadi urutan No RM -> Nama/gender
                                     -> tgl lahir (umur) -> alamat seragam di seluruh repo.
                                     collapseUmur SENGAJA false: laporan ini tak punya toggle
                                     Alpine `expanded`, dan komponen itu menyembunyikan baris
                                     umur di balik x-show bila dinyalakan. --}}
                                <td class="min-w-[18rem]">
                                    <x-list.identitas-pasien :regNo="$row->reg_no" :nama="$row->reg_name"
                                        :sex="$row->sex" :tglLahir="$row->birth_date" :alamat="$row->address">
                                        <div class="flex flex-wrap items-center gap-1 mt-1">
                                            <x-badge :variant="match ($sumber) { 'RJ' => 'info', 'UGD' => 'warning', default => 'success' }">
                                                {{ $sumber }}
                                            </x-badge>
                                            <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
                                        </div>
                                    </x-list.identitas-pasien>
                                </td>
                                <td class="max-w-[20rem]">
                                    {{-- Nama dokter seukuran nama tindakan: keduanya sama-sama
                                         isi pokok kolom ini, bukan keterangan tambahan. Yang
                                         diredupkan cukup kata penunjuknya. --}}
                                    <div class="text-ink dark:text-gray-100">{{ $row->tindakan_desc ?: '-' }}</div>
                                    <div class="mt-1 text-ink dark:text-gray-100">
                                        <span class="text-muted dark:text-gray-400">operator:</span>
                                        {{ $row->operator_name ?? '-' }}
                                    </div>
                                    <div class="text-ink dark:text-gray-100">
                                        <span class="text-muted dark:text-gray-400">anestesi:</span>
                                        {{ $row->anestesi_name ?? '-' }}
                                    </div>
                                </td>
                                {{-- Seluruh pos tarif jadi SATU sel. Hanya pos yang terisi
                                     yang ditulis: menampilkan 11 baris dengan sebagian besar
                                     nol membuat selnya panjang tanpa menambah keterangan. --}}
                                <td class="max-w-[22rem] text-xs">
                                    @php
                                        $posTerisi = [];
                                        foreach (KamarOperasiTarif::LABEL as $kolomPos => $labelPos) {
                                            $nilaiPos = (int) $row->{$kolomPos};
                                            if ($nilaiPos !== 0) {
                                                $posTerisi[$labelPos] = $nilaiPos;
                                            }
                                        }
                                    @endphp
                                    @forelse ($posTerisi as $labelPos => $nilaiPos)
                                        <div class="flex justify-between gap-3">
                                            <span class="text-muted dark:text-gray-400">{{ $labelPos }}</span>
                                            <span class="font-mono text-ink dark:text-gray-200">{{ number_format($nilaiPos, 0, ',', '.') }}</span>
                                        </div>
                                    @empty
                                        <span class="text-muted dark:text-gray-400">-</span>
                                    @endforelse
                                </td>
                                <td class="ds-r ds-td-strong whitespace-nowrap">{{ number_format((int) $row->total_fee, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-muted dark:text-gray-400">
                                    Belum ada operasi pada bulan ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
                @endif
            </div>

        </div>
    </div>
</div>
