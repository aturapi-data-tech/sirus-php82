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

    /** Mode tampilan: 'harian' (satu tanggal) | 'shift' (satu shift pada satu tanggal).
     *  DIKIRIM halaman induk Cek Saldo Kas (tab Per Shift/Harian ada DI SANA saja); modal tak punya
     *  tab, pemilih tanggal, maupun pemilih shift sendiri — supaya tidak rancu.
     *  Mode 'bulanan' DIHAPUS 2026-10-24 (permintaan user: berat). Cabang bulanan di
     *  dariTanggal()/sampaiTanggal()/isModeHarian() kini tak pernah terpakai. */
    public string $mode = 'shift';

    /** Shift yang ditampilkan/diunduh/dicetak pada mode 'shift' — dikirim induk. */
    public string $shiftPilihan = '1';

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

    private function setPeriode(string $tahunBulan): void
    {
        $this->periode = $tahunBulan;
        $this->periodeInput = Carbon::parse($tahunBulan . '-01')->format('m/Y');
    }

    private function setTanggalHarian(string $tanggal): void
    {
        $this->tanggalHarian = $tanggal;
        $this->tanggalHarianInput = Carbon::parse($tanggal)->format('d/m/Y');
        $this->setPeriode(substr($tanggal, 0, 7));
    }

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('keuangan.saldo-kas.openHistory')]
    public function openHistory(string $accId, string $tanggal, string $mode = 'shift', string $shift = ''): void
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

        // Tampilan, tanggal & shift MENGIKUTI induk (set tanggalHarian + input + periode).
        $this->mode = $mode === 'harian' ? 'harian' : 'shift';
        $this->setTanggalHarian($tanggal);
        $this->shiftPilihan = $shift !== '' ? $shift : SaldoKas::shiftSekarang();

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
        $tanggalSebelum = Carbon::parse($this->dariTanggal)->subDay()->toDateString();
        return $this->hitungSaldoTanggal($tanggalSebelum);
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

    /** Jenis transaksi = txn_name sebelum '(' pertama, mis. "RJ_ADMIN (123/456)" → "RJ_ADMIN". */
    private function jenisTransaksi(string $txnName): string
    {
        $name = trim($txnName);
        $pos = strpos($name, '(');
        $jenis = $pos !== false ? trim(substr($name, 0, $pos)) : $name;
        return $jenis === '' ? '-' : $jenis;
    }

    /**
     * Nomor referensi = isi kurung TERAKHIR txn_name (no. kas TU, rj_no/reg_no, dst.) —
     * kurung terakhir karena keterangan kas TU (tucashk_desc) bisa memuat kurung sendiri.
     */
    private function referensiTransaksi(string $txnName): string
    {
        return preg_match('/\(([^()]*)\)\s*$/', trim($txnName), $cocok) ? trim($cocok[1]) : '';
    }

    /**
     * Rekap per jenis transaksi (prefix txn_name sebelum '('): jumlah + total nominal.
     * Urut nominal terbesar. Menerima sekumpulan baris (semua / per shift).
     */
    private function rekapJenisDari($rows)
    {
        return collect($rows)
            ->groupBy(fn($baris) => $this->jenisTransaksi((string) $baris->txn_name))
            ->map(fn($grup, $jenis) => (object) [
                'jenis'   => $jenis,
                'count'   => $grup->count(),
                'debit'   => (float) $grup->sum('debit_kita'),
                'kredit'  => (float) $grup->sum('kredit_kita'),
                'nominal' => (float) $grup->sum(fn($baris) => (float) $baris->debit_kita + (float) $baris->kredit_kita),
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
        return collect($this->shiftGroups)
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
        $rows->each(fn ($baris) => $baris->lawan_acc_name = $namaLawan[$baris->lawan_acc_id] ?? null);

        // Hitung running saldo
        $saldo = $this->saldoAwalPeriode;
        return $rows->map(function ($baris) use (&$saldo) {
            $mutasi = (float) $baris->debit_kita - (float) $baris->kredit_kita;
            $saldo += $mutasi;
            $baris->saldo_berjalan = $saldo;
            $baris->mutasi = $mutasi;
            return $baris;
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
            $definisiShift = $this->shiftDefs->first(fn($definisi) => (string) $definisi->shift === (string) $shift);
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
                'range'          => $definisiShift ? substr((string) $definisiShift->shift_start, 0, 5) . '–' . substr((string) $definisiShift->shift_end, 0, 5) : null,
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
        // Hanya shift yang dipilih di induk — shift kosong tetap tampil (saldo awal = akhir).
        return [$this->kelompokSatuShift($this->shiftPilihan)];
    }

    /**
     * Angka baris Saldo Awal & kaki tabel sesuai cakupan tampilan:
     * Per Shift → kelompok shift terpilih; Harian → seluruh hari.
     */
    #[Computed]
    public function ringkasTampil(): object
    {
        if ($this->mode === 'shift' && $this->dariTanggal !== '') {
            $kelompok = $this->shiftGroups[0];
            return (object) [
                'labelAwal'  => 'Saldo Awal Shift ' . $kelompok->shift,
                'saldoAwal'  => $kelompok->saldoAwal,
                'labelAkhir' => 'Saldo Akhir Shift ' . $kelompok->shift,
                'debit'      => $kelompok->subtotalDebit,
                'kredit'     => $kelompok->subtotalKredit,
                'saldoAkhir' => $kelompok->saldoAkhir,
                'adaBaris'   => true,
            ];
        }
        return (object) [
            'labelAwal'  => 'Saldo Awal per ' . ($this->dariTanggal !== '' ? Carbon::parse($this->dariTanggal)->subDay()->format('d/m/Y') : '-'),
            'saldoAwal'  => $this->saldoAwalPeriode,
            'labelAkhir' => 'Saldo Akhir per ' . ($this->sampaiTanggal !== '' ? Carbon::parse($this->sampaiTanggal)->format('d/m/Y') : '-'),
            'debit'      => $this->totalDebit,
            'kredit'     => $this->totalKredit,
            'saldoAkhir' => $this->saldoAkhir,
            'adaBaris'   => $this->rows->count() > 0,
        ];
    }

    /**
     * Dua tombol footer yang sama di kedua tab — cakupannya mengikuti tab aktif:
     *   Per Shift → shift kiriman induk ($shiftPilihan); Harian → seluruh hari.
     * PDF = rekapan (laporan), Excel = data rinci (untuk compare).
     */
    public function unduhData(): mixed
    {
        return $this->mode === 'shift' ? $this->unduhCsv($this->shiftPilihan) : $this->unduhCsv();
    }

    public function cetakRekapan(): mixed
    {
        return $this->mode === 'shift' ? $this->cetakOperanShift($this->shiftPilihan) : $this->cetakPerJenis();
    }

    /**
     * Satu kelompok shift dari susunKelompokShift() — shift tanpa transaksi tetap dibuatkan
     * kelompok kosong (kertas operan & unduhan per shift tetap perlu saldonya).
     */
    private function kelompokSatuShift(string $shift): object
    {
        $kelompok = collect($this->susunKelompokShift())->first(fn($grup) => $grup->shift === $shift);
        if (!$kelompok) {
            // Shift tanpa transaksi tetap perlu kertas operan: saldo awal = akhir = saldo awal hari
            // + seluruh mutasi shift bernomor lebih kecil (urutan sama dengan susunKelompokShift).
            $saldoShift = $this->saldoAwalPeriode + (float) $this->rows
                ->filter(fn($row) => strnatcmp((string) ($row->shift ?: '1'), $shift) < 0)
                ->sum('mutasi');
            $definisiShift = $this->shiftDefs->first(fn($definisi) => (string) $definisi->shift === $shift);
            $kelompok = (object) [
                'shift'          => $shift,
                'range'          => $definisiShift ? substr((string) $definisiShift->shift_start, 0, 5) . '–' . substr((string) $definisiShift->shift_end, 0, 5) : null,
                'items'          => [],
                'subtotalDebit'  => 0.0,
                'subtotalKredit' => 0.0,
                'saldoAwal'      => $saldoShift,
                'saldoAkhir'     => $saldoShift,
            ];
        }

        return $kelompok;
    }

    /**
     * Cetak Laporan Operan — SATU shift SATU akun, untuk serah terima kas antar petugas shift.
     * Saldo awal shift = saldo akhir shift sebelumnya (rumus kumulatif susunKelompokShift, sama
     * dengan "s/d Shift" di induk), jadi angka di kertas operan cocok dengan Cek Saldo Kas.
     */
    public function cetakOperanShift(string $shift): mixed
    {
        if ($this->mode !== 'shift' || $this->dariTanggal === '') {
            return null;
        }

        $kelompok = $this->kelompokSatuShift($shift);

        $dataCetak = [
            'accId'          => $this->accId,
            'accDesc'        => $this->accDesc,
            'tanggalLabel'   => Carbon::parse($this->dariTanggal)->format('d/m/Y'),
            'shift'          => $kelompok->shift,
            'range'          => $kelompok->range,
            'dicetakPada'    => now()->format('d/m/Y H:i'),
            'dicetakOleh'    => auth()->user()->myuser_name ?? auth()->user()->name ?? '',
            'saldoAwal'      => $kelompok->saldoAwal,
            'totalDebit'     => $kelompok->subtotalDebit,
            'totalKredit'    => $kelompok->subtotalKredit,
            'saldoAkhir'     => $kelompok->saldoAkhir,
            'rekapJenis'     => $this->rekapJenisDari($kelompok->items),
            'jumlahTrx'      => count($kelompok->items),
        ];

        $pdf = Pdf::loadView('pages.transaksi.keuangan.saldo-kas.saldo-kas-operan-shift-print', $dataCetak)
            ->setPaper('a4', 'portrait');

        $namaFile = 'operan-kas-' . $this->accId . '-' . $this->akhiranPeriode() . '-shift' . $kelompok->shift . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $namaFile);
    }

    /**
     * Cetak ringkas per jenis transaksi: jumlah trx + total debit/kredit tiap jenis, tanpa rincian baris.
     * Mode per shift: dipecah per shift (subtotal + saldo akhir tiap shift); harian/bulanan: satu kelompok.
     */
    public function cetakPerJenis(): mixed
    {
        if ($this->rows->isEmpty()) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data untuk dicetak.');
            return null;
        }

        if ($this->mode === 'shift') {
            $kelompokJenis = collect($this->susunKelompokShift())
                ->map(fn($kelompok) => (object) [
                    'judul'          => 'SHIFT ' . $kelompok->shift . ($kelompok->range ? ' (' . $kelompok->range . ')' : ''),
                    'subtotalLabel'  => 'Subtotal Shift ' . $kelompok->shift,
                    'rekap'          => $this->rekapJenisDari($kelompok->items),
                    'jumlahTrx'      => count($kelompok->items),
                    'subtotalDebit'  => $kelompok->subtotalDebit,
                    'subtotalKredit' => $kelompok->subtotalKredit,
                    'saldoAwal'      => $kelompok->saldoAwal,
                    'saldoAkhir'     => $kelompok->saldoAkhir,
                ])
                ->all();
        } else {
            $kelompokJenis = [(object) [
                'judul'          => null,
                'subtotalLabel'  => null,
                'rekap'          => $this->rekapJenis,
                'jumlahTrx'      => $this->rows->count(),
                'subtotalDebit'  => $this->totalDebit,
                'subtotalKredit' => $this->totalKredit,
                'saldoAwal'      => $this->saldoAwalPeriode,
                'saldoAkhir'     => $this->saldoAkhir,
            ]];
        }

        $dataCetak = $this->kepalaCetak() + [
            'kelompokJenis' => $kelompokJenis,
            'jumlahTrx'     => $this->rows->count(),
        ];

        // A6 = A4 bagi 4 (ukuran kwitansi); dicetak ke A4 skala 100% jatuh di tengah kertas.
        $pdf = Pdf::loadView('pages.transaksi.keuangan.saldo-kas.saldo-kas-rekap-jenis-print', $dataCetak)
            ->setPaper('a6', 'portrait');

        $namaFile = 'rekap-jenis-kas-' . $this->accId . '-' . $this->akhiranPeriode() . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $namaFile);
    }

    /**
     * Unduh rincian transaksi tampilan aktif sebagai CSV siap Excel (pola Lampiran Gaji Dokter).
     *
     * SENGAJA PIPIH: satu baris = satu jurnal, tanpa baris judul/subtotal shift, supaya bisa
     * dipivot dan dibandingkan dengan unduhan lain waktu (VLOOKUP pada kolom Referensi) untuk
     * melacak transaksi yang berubah/hilang/bertambah. Akun & shift ikut di setiap baris.
     */
    public function unduhCsv(?string $shift = null): mixed
    {
        // $shift terisi (mode per shift) = rincian SATU shift — pasangan kertas Operan, untuk compare data.
        $satuShift = $shift !== null && $this->mode === 'shift' && $this->dariTanggal !== '';

        if (!$satuShift && $this->rows->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada data untuk diunduh.');
            return null;
        }

        // Mode per shift: urutan & saldo berjalan mengikuti kelompok shift di layar; lainnya satu kelompok.
        if ($satuShift) {
            $kelompok = $this->kelompokSatuShift($shift);
            $kelompokList = [$kelompok];
            $saldoAwal  = $kelompok->saldoAwal;
            $saldoAkhir = $kelompok->saldoAkhir;
        } else {
            $kelompokList = $this->mode === 'shift'
                ? $this->susunKelompokShift()
                : [(object) ['shift' => null, 'items' => $this->rows->all(), 'saldoAkhir' => $this->saldoAkhir]];
            $saldoAwal  = $this->saldoAwalPeriode;
            $saldoAkhir = $this->saldoAkhir;
        }
        $saldoAwalTgl = Carbon::parse($this->dariTanggal)->subDay()->format('d/m/Y');
        $sampaiTgl    = Carbon::parse($this->sampaiTanggal)->format('d/m/Y');
        $namaBerkas = 'rincian-kas-' . $this->accId . '-' . $this->akhiranPeriode() . ($satuShift ? '-shift' . $shift : '') . '.csv';

        return response()->streamDownload(function () use ($kelompokList, $saldoAwal, $saldoAkhir, $saldoAwalTgl, $sampaiTgl, $satuShift, $shift) {
            $keluaran = fopen('php://output', 'w');

            // BOM UTF-8 tanpa "sep=;" — alasannya lihat unduhCsv() Lampiran Gaji Dokter.
            fwrite($keluaran, "\xEF\xBB\xBF");
            fputcsv($keluaran, self::KOLOM_CSV, ';');

            // Baris saldo: Debit/Kredit DIKOSONGKAN supaya SUM/pivot kedua kolom itu tetap
            // = total transaksi; angkanya hanya di kolom Saldo Berjalan.
            $tulisSaldo = fn(string $tanggal, string $shift, string $jenis, string $deskripsi, float $saldo) => fputcsv($keluaran, [
                $this->accId, $tanggal, '', $shift, $jenis, '', $deskripsi, '', '', '', '', (int) round($saldo),
            ], ';');

            if ($satuShift) {
                $tulisSaldo($sampaiTgl, $shift, 'SALDO AWAL SHIFT', 'Saldo awal shift ' . $shift, $saldoAwal);
            } else {
                $tulisSaldo($saldoAwalTgl, '', 'SALDO AWAL', 'Saldo awal per ' . $saldoAwalTgl, $saldoAwal);
            }

            foreach ($kelompokList as $kelompok) {
                foreach ($kelompok->items as $baris) {
                    $tglTransaksi = Carbon::parse($baris->txn_date);
                    fputcsv($keluaran, [
                        $this->accId,
                        $tglTransaksi->format('d/m/Y'),
                        $tglTransaksi->format('H:i:s'),
                        (string) ($baris->shift ?: '1'),
                        $this->jenisTransaksi((string) $baris->txn_name),
                        $this->referensiTransaksi((string) $baris->txn_name),
                        $baris->txn_name,
                        $baris->lawan_acc_id,
                        $baris->lawan_acc_name,
                        // Angka MENTAH: nominal berformat ribuan dibaca Excel sebagai teks.
                        (int) round((float) $baris->debit_kita),
                        (int) round((float) $baris->kredit_kita),
                        (int) round((float) $baris->saldo_berjalan),
                    ], ';');
                }
                if ($kelompok->shift !== null) {
                    $tulisSaldo($sampaiTgl, $kelompok->shift, 'SALDO AKHIR SHIFT', 'Saldo akhir shift ' . $kelompok->shift, $kelompok->saldoAkhir);
                }
            }

            // Satu shift: penutupnya sudah baris SALDO AKHIR SHIFT dari kelompok di atas.
            if (!$satuShift) {
                $tulisSaldo($sampaiTgl, '', 'SALDO AKHIR', 'Saldo akhir per ' . $sampaiTgl, $saldoAkhir);
            }

            fclose($keluaran);
        }, $namaBerkas, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Judul kolom CSV — urutannya sejajar dengan nilai di unduhCsv(). */
    public const KOLOM_CSV = [
        'Akun', 'Tanggal', 'Jam', 'Shift', 'Jenis', 'Referensi', 'Deskripsi',
        'Lawan Akun', 'Nama Lawan Akun', 'Debit', 'Kredit', 'Saldo Berjalan',
    ];

    /** Data kepala cetakan (akun, periode, ringkasan saldo) — dipakai kedua cetakan. */
    private function kepalaCetak(): array
    {
        $tglMulai  = Carbon::parse($this->dariTanggal);
        $tglSampai = Carbon::parse($this->sampaiTanggal);

        return [
            'accId'        => $this->accId,
            'accDesc'      => $this->accDesc,
            'mode'         => $this->mode,
            'modeLabel'    => ['harian' => 'Harian', 'shift' => 'Harian (per Shift)', 'bulanan' => 'Bulanan'][$this->mode] ?? 'Harian',
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
        ];
    }

    /** Akhiran nama berkas: Ymd (harian/shift) atau Ym (bulanan). */
    private function akhiranPeriode(): string
    {
        return Carbon::parse($this->dariTanggal)->format($this->isModeHarian() ? 'Ymd' : 'Ym');
    }

    public function closeModal(): void
    {
        $this->reset(['accId', 'accDesc', 'accDkStatus', 'mode', 'shiftPilihan', 'periode', 'periodeInput', 'tanggalHarian', 'tanggalHarianInput']);
        $this->dispatch('close-modal', name: 'saldo-kas-history');
        $this->resetVersion();
    }
};
?>

<div>
    <x-modal name="saldo-kas-history" size="full" height="full" focusable>
        <div class="flex flex-col min-h-full"
             wire:key="{{ $this->renderKey('modal', [$accId, $mode, $periode, $tanggalHarian]) }}">

            <x-modul-dokumen.header :judul="'Riwayat Transaksi — ' . $accDesc" ikon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                Akun <span class="font-mono">{{ $accId }}</span>
            </x-modul-dokumen.header>

            <div class="px-4 py-3 bg-canvas border-b border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    {{-- Baca-saja: tampilan, tanggal & shift dipilih di halaman Cek Saldo Kas --}}
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-muted dark:text-gray-400">Tampilan</span>
                        <x-badge :variant="$mode === 'shift' ? 'success' : 'info'">{{ $mode === 'shift' ? 'Per Shift' : 'Harian' }}</x-badge>
                        <span class="font-mono font-semibold text-ink dark:text-gray-100">{{ $tanggalHarianInput }}</span>
                        @if ($mode === 'shift')
                            <span class="font-semibold text-ink dark:text-gray-100">— Shift {{ $shiftPilihan }}</span>
                        @endif
                        <span class="text-xs text-muted-soft">(ubah di halaman Cek Saldo Kas)</span>
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
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-surface-soft text-muted dark:bg-gray-800 dark:text-gray-300">{{ $mode === 'shift' ? collect($this->rekapJenisPerShift)->sum(fn($grup) => $grup->rekap->count()) : $this->rekapJenis->count() }} jenis</span>
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
                            {{-- Harian: rekap jenis se-hari --}}
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
                                        {{ $this->ringkasTampil->labelAwal }}
                                    </td>
                                    <td class="px-3 py-2.5 font-mono text-base font-bold text-right text-amber-800 dark:text-amber-300">
                                        {{ number_format($this->ringkasTampil->saldoAwal, 0, '.', ',') }}
                                    </td>
                                </tr>
                            @endif

                            @if ($mode === 'shift')
                                {{-- Mode Per Shift: hanya shift terpilih; total & saldo akhirnya di kaki tabel --}}
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
                                    @if (count($group->items) === 0)
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center text-muted dark:text-gray-400">
                                                Tidak ada transaksi pada shift ini — saldo tidak berubah.
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-muted dark:text-gray-400">
                                            Tidak ada transaksi pada tanggal ini.
                                        </td>
                                    </tr>
                                @endforelse
                            @else
                                {{-- Mode Harian: daftar transaksi datar --}}
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
                        @if ($this->ringkasTampil->adaBaris)
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="sticky bottom-0 z-10 px-3 py-3 text-sm font-bold tracking-wide uppercase border-t-2 bg-emerald-100 border-emerald-300 text-emerald-800 dark:bg-emerald-900 dark:border-emerald-700 dark:text-emerald-200">
                                        {{ $this->ringkasTampil->labelAkhir }}
                                    </td>
                                    <td class="sticky bottom-0 z-10 px-3 py-3 font-mono text-base font-bold text-right border-t-2 bg-emerald-100 border-emerald-300 text-blue-700 dark:bg-emerald-900 dark:border-emerald-700 dark:text-blue-300">
                                        {{ number_format($this->ringkasTampil->debit, 0, '.', ',') }}
                                    </td>
                                    <td class="sticky bottom-0 z-10 px-3 py-3 font-mono text-base font-bold text-right border-t-2 bg-emerald-100 border-emerald-300 text-error dark:bg-emerald-900 dark:border-emerald-700 dark:text-rose-300">
                                        {{ number_format($this->ringkasTampil->kredit, 0, '.', ',') }}
                                    </td>
                                    <td class="sticky bottom-0 z-10 px-3 py-3 font-mono text-lg font-bold text-right border-t-2 bg-emerald-100 border-emerald-300 {{ $this->ringkasTampil->saldoAkhir < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-800 dark:text-emerald-200' }} dark:bg-emerald-900 dark:border-emerald-700">
                                        {{ number_format($this->ringkasTampil->saldoAkhir, 0, '.', ',') }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-3 mt-auto bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    {{-- Dua tombol sama di kedua tampilan; cakupan mengikuti induk: Per Shift → shift terpilih, Harian → seluruh hari.
                         Shift kosong tetap boleh diunduh/dicetak (saldo awal = akhir), jadi tak di-disable di tab Per Shift. --}}
                    @php $tanpaData = $mode !== 'shift' && $this->rows->count() === 0; @endphp
                    <x-info-button type="button" class="gap-2" wire:click="unduhData"
                        :disabled="$tanpaData"
                        wire:loading.attr="disabled" wire:target="unduhData"
                        title="{{ $mode === 'shift' ? 'Unduh rincian transaksi shift terpilih + saldo awal/akhir shift' : 'Unduh rincian transaksi sehari + saldo awal/akhir' }} untuk dibandingkan di Excel">
                        <span wire:loading.remove wire:target="unduhData" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Unduh Data Excel
                        </span>
                        <span wire:loading wire:target="unduhData" class="inline-flex items-center gap-2">
                            <x-loading /> Menyiapkan...
                        </span>
                    </x-info-button>
                    <x-cetak-button wire:click="cetakRekapan" :disabled="$tanpaData"
                        label="Cetak Rekapan"
                        title="{{ $mode === 'shift' ? 'Rekapan serah terima kas shift terpilih — saldo awal, masuk, keluar, saldo akhir, kolom TTD' : 'Rekapan sehari — total uang tiap jenis transaksi' }}" />
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
