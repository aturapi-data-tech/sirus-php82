<?php

/**
 * Pembayaran Piutang Pasien — Modal Proses Pembayaran (banyak nota sekaligus).
 *
 * Dua konsep pemakaian, satu mesin tulis yang sama:
 *   - mode 'bundel' : klaim BPJS per bulan — semua nota tercentang DILUNASI penuh,
 *                     nominal = total sisa saat proses (tidak bisa diketik).
 *   - mode 'pasien' : pasien umum — nominal bebas, dialokasikan FIFO ke nota
 *                     tercentang (nota terlama dulu); nota terakhir boleh cicilan.
 *
 * Tulis per jalur:
 *   RJ  : rstxn_rjcashins       + rstxn_rjhdrs.txn_status
 *   UGD : rstxn_ugdcashins      + rstxn_ugdhdrs.txn_status
 *   RI  : rstxn_ripaymentpdtls  + rstxn_rihdrs.status_pulang & ri_titip
 *
 * Beda dengan kasir (disengaja, keputusan user):
 *   - Tanggal baris kas = TANGGAL BAYAR SEBENARNYA (kasir memakai tanggal kunjungan).
 *   - RI: ri_titip DIAKUMULASI; kolom ri_bayar sengaja tidak disentuh.
 *
 * Sisa tiap nota selalu dihitung ulang dari database SETELAH baris header dikunci —
 * angka di layar tidak pernah dipakai menulis.
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Traits\Manajemen\Rs\Tu\PiutangPasienTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use PiutangPasienTrait, EmrRJTrait, EmrUGDTrait, EmrRITrait, WithRenderVersioningTrait;

    public array $renderVersions = [];

    public string $mode = 'pasien';          // 'bundel' | 'pasien'
    public string $judulKonteks = '';         // mis. "Klaim BPJS 07/2026" / nama pasien
    public array $items = [];                 // [['jalur' => 'RJ', 'no' => '123'], ...]
    public array $baris = [];                 // ringkasan nota untuk ditampilkan
    public int $totalSisa = 0;
    /** Nota yang riwayatnya sedang dibuka ('JALUR|no') — dimuat saat diklik, bukan semua sekaligus. */
    public ?string $notaDipilih = null;
    /** Pembayaran yang sudah masuk untuk nota dipilih (hanya tampil), terbaru di atas. */
    public array $riwayatBayar = [];

    public ?string $tanggal = null;
    /** Shift baris kas — otomatis dari jam Tanggal Pembayaran, tetap bisa dipilih manual. */
    public ?string $shift = null;
    public ?string $accId = null;
    public ?string $keterangan = null;
    public ?int $bayar = null;
    /** Pratinjau alokasi FIFO untuk dialog konfirmasi — kosong = dialog tertutup. Hanya tampilan. */
    public array $pratinjau = [];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ── Buka modal ── */
    #[On('piutang.openBayar')]
    public function openBayar(array $items, string $mode = 'pasien', string $judulKonteks = ''): void
    {
        $this->resetFormFields();

        $baris = $this->ambilBarisPiutang($items);

        if ($baris->isEmpty()) {
            $this->dispatch('toast', type: 'info', message: 'Tidak ada nota dengan sisa piutang untuk diproses.');
            $this->dispatch('piutang.paid');
            return;
        }

        $this->mode = $mode === 'bundel' ? 'bundel' : 'pasien';
        $this->judulKonteks = $judulKonteks;
        $this->items = $baris->map(fn($row) => ['jalur' => $row->jalur, 'no' => (string) $row->no_transaksi])->all();
        $this->baris = $baris->map(fn($row) => [
            'jalur' => $row->jalur,
            'no' => (string) $row->no_transaksi,
            'nama' => (string) ($row->reg_name ?? ''),
            'regNo' => (string) ($row->reg_no ?? ''),
            'tgl' => (string) ($row->tgl ?? ''),
            'sisa' => (int) $row->sisa,
        ])->all();

        $this->totalSisa = (int) $baris->sum('sisa');
        // Satu nota → riwayat langsung tampil; banyak nota (bundel) → dimuat saat nota diklik.
        if (count($this->items) === 1) {
            $this->pilihNota($this->items[0]['jalur'], $this->items[0]['no']);
        }
        $this->tanggal = Carbon::now()->format('d/m/Y H:i:s');
        $this->shift = $this->shiftPadaJam($this->tanggal);
        $this->bayar = $this->totalSisa;
        $this->keterangan = '';

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'pembayaran-piutang-actions');
        $this->dispatch('focus-piutang-tanggal');
    }

    /* ── Riwayat per nota (klik baris nota) ── */
    public function pilihNota(string $jalur, string $no): void
    {
        $kunci = $jalur . '|' . $no;

        // Hanya nota yang memang sedang diproses di modal ini.
        $ada = collect($this->items)->contains(fn($item) => $item['jalur'] === $jalur && (string) $item['no'] === $no);
        if (!$ada || ($this->notaDipilih === $kunci && count($this->items) > 1)) {
            $this->notaDipilih = null;
            $this->riwayatBayar = [];
            return;
        }

        $this->notaDipilih = $kunci;
        $this->riwayatBayar = $this->ambilRiwayatBayar([['jalur' => $jalur, 'no' => $no]]);
    }

    /* ── LOV akun kas ── */
    #[On('lov.selected.kas-piutang')]
    public function onKasSelected(string $target, ?array $payload): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->dispatch($this->mode === 'bundel' ? 'focus-btn-proses' : 'focus-piutang-nominal');
    }

    public function setTanggalSekarang(): void
    {
        $this->tanggal = Carbon::now()->format('d/m/Y H:i:s');
        $this->updatedTanggal($this->tanggal);
    }

    /** Tanggal diketik ulang → shift menyesuaikan jamnya (jam di luar master = biarkan, pilih manual). */
    public function updatedTanggal(?string $value): void
    {
        $shift = $this->shiftPadaJam((string) $value);
        if ($shift !== null) {
            $this->shift = $shift;
            $this->resetErrorBag('shift');
        }
    }

    /* ── Cek nominal langsung (sync saat blur) — tak perlu menunggu ditolak server ── */
    public function updatedBayar(): void
    {
        $this->resetErrorBag('bayar');
        if ((int) $this->bayar > $this->totalSisa) {
            $this->addError('bayar', 'Melebihi total sisa piutang Rp ' . number_format($this->totalSisa, 0, ',', '.') . '.');
        }
    }

    /**
     * Langkah 1 Proses: validasi + hitung pratinjau alokasi FIFO dari DB (tanpa menulis), lalu
     * tampilkan dialog konfirmasi. Salah ketik di bawah total (300.000 vs 30.000) terlihat di sini
     * — nota mana yang akan LUNAS — sebelum uang tercatat.
     */
    public function siapkanKonfirmasi(): void
    {
        $this->validate(
            [
                'tanggal' => 'required|date_format:d/m/Y H:i:s',
                'shift' => 'required|in:1,2,3',
                'accId' => 'required|string|exists:acmst_accounts,acc_id',
                'bayar' => 'required|integer|min:1',
            ],
            [
                'tanggal.required' => 'Tanggal pembayaran wajib diisi.',
                'tanggal.date_format' => 'Format tanggal harus dd/mm/yyyy hh:mm:ss.',
                'shift.required' => 'Shift belum dipilih.',
                'shift.in' => 'Shift tidak valid.',
                'accId.required' => 'Akun kas wajib dipilih.',
                'accId.exists' => 'Akun kas tidak valid.',
                'bayar.required' => 'Nominal bayar wajib diisi.',
                'bayar.min' => 'Nominal bayar minimal Rp 1.',
            ],
        );

        $barisKini = $this->ambilBarisPiutang($this->items);
        $totalKini = (int) $barisKini->sum('sisa');

        if ($barisKini->isEmpty()) {
            $this->dispatch('toast', type: 'error', message: 'Semua nota terpilih sudah lunas / diproses user lain.');
            return;
        }

        $bayar = $this->mode === 'bundel' ? $totalKini : (int) $this->bayar;
        if ($bayar > $totalKini) {
            $this->addError('bayar', 'Melebihi total sisa piutang Rp ' . number_format($totalKini, 0, ',', '.') . '.');
            return;
        }

        $sisaBayar = $bayar;
        $alokasi = [];
        foreach ($barisKini as $row) {
            if ($sisaBayar <= 0) {
                break;
            }
            $sisaNota = (int) $row->sisa;
            if ($sisaNota <= 0) {
                continue;
            }
            $nominal = min($sisaBayar, $sisaNota);
            $alokasi[] = [
                'jalur' => (string) $row->jalur,
                'no' => (string) $row->no_transaksi,
                'nama' => (string) ($row->reg_name ?? ''),
                'nominal' => $nominal,
                'sisaSesudah' => $sisaNota - $nominal,
            ];
            $sisaBayar -= $nominal;
        }

        $this->pratinjau = [
            'bayar' => $bayar,
            'totalSisa' => $totalKini,
            'accName' => DB::table('acmst_accounts')->where('acc_id', $this->accId)->value('acc_name') ?? $this->accId,
            'alokasi' => $alokasi,
        ];
        $this->dispatch('focus-konfirmasi-piutang');
    }

    public function batalKonfirmasi(): void
    {
        $this->pratinjau = [];
        $this->dispatch('focus-piutang-nominal');
    }

    /* ── Proses pembayaran ── */
    public function prosesBayar(): mixed
    {
        $this->pratinjau = [];

        $this->validate(
            [
                'tanggal' => 'required|date_format:d/m/Y H:i:s',
                'shift' => 'required|in:1,2,3',
                'accId' => 'required|string|exists:acmst_accounts,acc_id',
                'bayar' => 'required|integer|min:1',
            ],
            [
                'tanggal.required' => 'Tanggal pembayaran wajib diisi.',
                'tanggal.date_format' => 'Format tanggal harus dd/mm/yyyy hh:mm:ss.',
                'shift.required' => 'Shift belum dipilih.',
                'shift.in' => 'Shift tidak valid.',
                'accId.required' => 'Akun kas wajib dipilih.',
                'accId.exists' => 'Akun kas tidak valid.',
                'bayar.required' => 'Nominal bayar wajib diisi.',
                'bayar.min' => 'Nominal bayar minimal Rp 1.',
            ],
        );

        if (empty($this->items)) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada nota terpilih.');
            return null;
        }

        if ($this->akunKasBelumTerdaftar()) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas Anda belum terkonfigurasi. Hubungi administrator.');
            return null;
        }

        $empId = auth()->user()->emp_id ?? null;
        if (!$empId) {
            $this->dispatch('toast', type: 'error', message: 'EMP ID belum diisi di profil user. Hubungi administrator.');
            return null;
        }

        $tanggalDb = "to_date('{$this->tanggal}','dd/mm/yyyy hh24:mi:ss')";
        $shift = (string) $this->shift;

        $terbayar = 0;
        $jumlahLunas = 0;
        $jumlahCicilan = 0;
        // Rincian alokasi untuk kwitansi — direkam dari hasil proses (bukan angka layar).
        $rincianKwitansi = [];

        try {
            DB::transaction(function () use ($empId, $tanggalDb, $shift, &$terbayar, &$jumlahLunas, &$jumlahCicilan, &$rincianKwitansi) {
                // 1. Kunci semua baris header terpilih dulu.
                foreach ($this->items as $item) {
                    match ($item['jalur']) {
                        'RJ' => $this->lockRJRow((int) $item['no']),
                        'UGD' => $this->lockUGDRow((int) $item['no']),
                        'RI' => $this->lockRIRow((int) $item['no']),
                        default => null,
                    };
                }

                // 2. Hitung ulang sisa tiap nota dari DB (urut FIFO — terlama dulu).
                $barisKini = $this->ambilBarisPiutang($this->items);

                if ($barisKini->isEmpty()) {
                    throw new \RuntimeException('Semua nota terpilih sudah lunas / diproses user lain.');
                }

                $totalKini = (int) $barisKini->sum('sisa');

                // Mode bundel selalu melunasi seluruh sisa saat ini (klaim BPJS per bulan).
                $bayar = $this->mode === 'bundel' ? $totalKini : (int) $this->bayar;

                if ($bayar > $totalKini) {
                    throw new \RuntimeException(
                        'Nominal bayar (Rp ' . number_format($bayar) . ') melebihi total sisa piutang saat ini (Rp ' . number_format($totalKini) . ').',
                    );
                }

                // 3. Alokasi FIFO ke tiap nota.
                $sisaBayar = $bayar;

                foreach ($barisKini as $row) {
                    if ($sisaBayar <= 0) {
                        break;
                    }

                    $sisaNota = (int) $row->sisa;
                    if ($sisaNota <= 0) {
                        continue;
                    }

                    $nominal = min($sisaBayar, $sisaNota);
                    $lunas = $nominal >= $sisaNota;

                    $catatan = 'Pelunasan piutang: Rp ' . number_format($nominal, 0, ',', '.')
                        . ($lunas ? ' (LUNAS)' : ' (sisa Rp ' . number_format($sisaNota - $nominal, 0, ',', '.') . ')')
                        . (filled($this->keterangan) ? ' — ' . $this->keterangan : '');

                    match ($row->jalur) {
                        'RJ' => $this->simpanBayarRj((int) $row->no_transaksi, $nominal, $empId, $tanggalDb, $shift, $lunas, $catatan),
                        'UGD' => $this->simpanBayarUgd((int) $row->no_transaksi, $nominal, $empId, $tanggalDb, $shift, $lunas, $catatan),
                        'RI' => $this->simpanBayarRi((int) $row->no_transaksi, $nominal, $empId, $tanggalDb, $shift, $lunas, $catatan),
                        default => null,
                    };

                    $rincianKwitansi[] = [
                        'jalur' => (string) $row->jalur,
                        'no' => (string) $row->no_transaksi,
                        'regNo' => (string) ($row->reg_no ?? ''),
                        'nama' => (string) ($row->reg_name ?? ''),
                        'tgl' => (string) ($row->tgl ?? ''),
                        'sisaSebelum' => $sisaNota,
                        'dibayar' => $nominal,
                        'lunas' => $lunas,
                    ];

                    $lunas ? $jumlahLunas++ : $jumlahCicilan++;
                    $terbayar += $nominal;
                    $sisaBayar -= $nominal;
                }
            });

            $pesan = 'Pembayaran Rp ' . number_format($terbayar) . ' berhasil — ' . $jumlahLunas . ' nota lunas';
            if ($jumlahCicilan > 0) {
                $pesan .= ', ' . $jumlahCicilan . ' nota cicilan';
            }

            $this->dispatch('toast', type: 'success', message: $pesan . '.');

            // Kwitansi dirakit SEBELUM closeModal() (yang mereset accId/keterangan/mode).
            $kwitansi = $this->kwitansiPembayaran($rincianKwitansi, $empId, $shift);

            $this->closeModal();
            $this->dispatch('piutang.paid');

            return $kwitansi;
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan pembayaran: ' . $e->getMessage());
        }

        return null;
    }

    /* ===============================
     | KWITANSI PEMBAYARAN PIUTANG
     | Saat proses: dicetak dari hasil alokasi (banyak nota bisa satu kwitansi).
     | Cetak ulang: per baris riwayat (satu baris kas) — satu pembayaran tersebar di
     | rjcashins/ugdcashins/ripaymentpdtls tanpa nomor bersama, jadi tak bisa digabung ulang.
     | $konteks (opsional, untuk cetak ulang): tanggal, accId, keterangan, judulKonteks, cetakUlang.
     =============================== */
    protected function kwitansiPembayaran(array $rincian, string $empId, string $shift, array $konteks = []): mixed
    {
        $tanggal = $konteks['tanggal'] ?? $this->tanggal;
        $accId = $konteks['accId'] ?? $this->accId;
        $keterangan = array_key_exists('keterangan', $konteks) ? $konteks['keterangan'] : $this->keterangan;
        $judulKonteks = $konteks['judulKonteks'] ?? $this->judulKonteks;
        $cetakUlang = (bool) ($konteks['cetakUlang'] ?? false);

        if (empty($rincian)) {
            return null;
        }

        try {
            $daftarRegNo = array_values(array_unique(array_filter(array_column($rincian, 'regNo'))));
            // Satu pasien → kwitansi pasien (identitas lengkap); banyak pasien (bundel) → bukti penerimaan.
            $satuPasien = count($daftarRegNo) === 1;

            $pasien = null;
            if ($satuPasien) {
                $pasien = DB::table('rsmst_pasiens')
                    ->where('reg_no', $daftarRegNo[0])
                    ->first(['reg_no', 'reg_name', 'sex', 'birth_place', 'address', DB::raw("to_char(birth_date,'dd/mm/yyyy') as birth_date")]);
            }

            $umur = null;
            if (!empty($pasien?->birth_date)) {
                try {
                    $selisih = Carbon::createFromFormat('d/m/Y', $pasien->birth_date)->diff(now());
                    $umur = "{$selisih->y} Thn {$selisih->m} Bln {$selisih->d} Hr";
                } catch (\Throwable) {
                    $umur = null;
                }
            }

            $data = [
                'satuPasien' => $satuPasien,
                'judul' => $satuPasien ? 'KWITANSI PEMBAYARAN PIUTANG' : 'BUKTI PENERIMAAN PEMBAYARAN PIUTANG',
                'judulKonteks' => $judulKonteks,
                'cetakUlang' => $cetakUlang,
                'pasien' => $pasien,
                'umur' => $umur,
                'tanggal' => $tanggal,
                'shift' => $shift,
                'accName' => DB::table('acmst_accounts')->where('acc_id', $accId)->value('acc_name') ?? $accId,
                'keterangan' => $keterangan,
                'rincian' => $rincian,
                'total' => (int) array_sum(array_column($rincian, 'dibayar')),
                'kasirName' => DB::table('immst_employers')->where('emp_id', $empId)->value('emp_name') ?? $empId,
                'tglCetak' => Carbon::now(config('app.timezone'))->format('d/m/Y'),
                'jamCetak' => Carbon::now(config('app.timezone'))->format('H:i'),
                'cetakOleh' => auth()->user()->myuser_name ?? '-',
            ];

            $pdf = Pdf::loadView('pages.transaksi.keuangan.pembayaran-piutang.pembayaran-piutang-kwitansi-print', ['data' => $data])
                ->setPaper('A4');

            $namaFile = 'kwitansi-piutang-' . ($satuPasien ? $daftarRegNo[0] : 'bundel') . '-'
                . Carbon::createFromFormat('d/m/Y H:i:s', $tanggal)->format('YmdHis') . ($cetakUlang ? '-ulang' : '') . '.pdf';

            return response()->streamDownload(fn() => print $pdf->output(), $namaFile, ['Content-Type' => 'application/pdf']);
        } catch (\Throwable $e) {
            // Pembayaran SUDAH tersimpan — gagal cetak tak boleh terbaca sebagai gagal bayar.
            $this->dispatch('toast', type: 'warning', message: ($cetakUlang ? 'Kwitansi gagal dicetak: ' : 'Pembayaran tersimpan, tetapi kwitansi gagal dicetak: ') . $e->getMessage());
            return null;
        }
    }

    /* ===============================
     | HAPUS PEMBAYARAN PIUTANG (salah bayar) — model HAPUS BARIS KAS (keputusan user 2026-11-17)
     | Arus kas membaca baris kas langsung (JurnalCabang BAYAR_RJ/UGD/RI, tanpa tutup kas), jadi
     | menghapus baris = saldo kas pada tanggal & shift bayar asli ikut berubah mundur. Karena itu:
     |  - hanya pembayaran dari MENU PIUTANG (RJ/UGD rjc_desc 'PIUT…', RI sesudah tanggal pulang) —
     |    bayar kasir saat pulang dibatalkan lewat Batal Transaksi kasir, angsuran awal di tabnya;
     |  - hanya pembayaran TAHUN BERJALAN (saldo awal tahun TKTXN_SALDOAWALAKUNS tak disentuh);
     |  - hanya Admin / Supervisor Tu; layar memberi peringatan dampak ke arus kas.
     =============================== */
    private const ROLE_HAPUS_BAYAR = ['Admin', 'Supervisor Tu'];

    protected function bolehHapusRiwayat(string $sumber, string $tanggal, string $keterangan, int $setelahPulang): bool
    {
        if (!auth()->user()?->hasAnyRole(self::ROLE_HAPUS_BAYAR)) {
            return false;
        }

        try {
            if (Carbon::createFromFormat('d/m/Y H:i:s', $tanggal)->year !== (int) now()->year) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return match ($sumber) {
            'RJ', 'UGD' => str_starts_with($keterangan, 'PIUT'),
            'RI' => $setelahPulang === 1,
            default => false,
        };
    }

    public function hapusRiwayat(string $sumber, int $id): void
    {
        $peta = [
            'RJ' => ['rstxn_rjcashins', 'rjc_dtl', 'rj_no', 'rjc_date', 'rjc_nominal', 'rjc_desc'],
            'UGD' => ['rstxn_ugdcashins', 'rjc_dtl', 'rj_no', 'rjc_date', 'rjc_nominal', 'rjc_desc'],
            'RI' => ['rstxn_ripaymentpdtls', 'ripay_no', 'rihdr_no', 'ripay_date', 'ripay_bayar', null],
        ];

        if (!isset($peta[$sumber])) {
            $this->dispatch('toast', type: 'error', message: 'Pembayaran ini tidak bisa dihapus dari menu piutang.');
            return;
        }

        [$tabel, $kolomId, $kolomNo, $kolomTgl, $kolomNominal, $kolomDesc] = $peta[$sumber];

        try {
            $hasil = DB::transaction(function () use ($sumber, $id, $tabel, $kolomId, $kolomNo, $kolomTgl, $kolomNominal, $kolomDesc) {
                // Alias c WAJIB: tanpa alias, rihdr_no di subquery terikat ke tabel h (ORA-01427).
                $bayar = DB::table("{$tabel} as c")->where("c.{$kolomId}", $id)->first([
                    DB::raw("c.{$kolomNo} as no_transaksi"),
                    DB::raw("to_char(c.{$kolomTgl},'dd/mm/yyyy hh24:mi:ss') as tgl"),
                    DB::raw("NVL(c.{$kolomNominal},0) as nominal"),
                    DB::raw($kolomDesc ? "c.{$kolomDesc} as keterangan" : 'CAST(NULL AS VARCHAR2(10)) as keterangan'),
                    'c.shift', 'c.acc_id',
                    DB::raw($sumber === 'RI'
                        ? "CASE WHEN c.{$kolomTgl} > (SELECT h.exit_date FROM rstxn_rihdrs h WHERE h.rihdr_no = c.{$kolomNo}) THEN 1 ELSE 0 END as setelah_pulang"
                        : '0 as setelah_pulang'),
                ]);

                if (!$bayar || $this->notaDipilih !== $sumber . '|' . $bayar->no_transaksi) {
                    throw new \RuntimeException('Data pembayaran tidak ditemukan.');
                }

                if (!$this->bolehHapusRiwayat($sumber, (string) $bayar->tgl, (string) ($bayar->keterangan ?? ''), (int) $bayar->setelah_pulang)) {
                    throw new \RuntimeException('Pembayaran ini tidak boleh dihapus (bukan dari menu piutang, beda tahun, atau Anda tidak berwenang).');
                }

                $noTransaksi = (int) $bayar->no_transaksi;
                $nominal = (int) $bayar->nominal;

                match ($sumber) {
                    'RJ' => $this->lockRJRow($noTransaksi),
                    'UGD' => $this->lockUGDRow($noTransaksi),
                    'RI' => $this->lockRIRow($noTransaksi),
                };

                DB::table($tabel)->where($kolomId, $id)->delete();

                // Uang dicabut → nota tak mungkin lagi lunas penuh oleh baris ini: kembalikan ke BON.
                match ($sumber) {
                    'RJ' => DB::table('rstxn_rjhdrs')->where('rj_no', $noTransaksi)->where('txn_status', 'L')->update(['txn_status' => 'H']),
                    'UGD' => DB::table('rstxn_ugdhdrs')->where('rj_no', $noTransaksi)->where('txn_status', 'L')->update(['txn_status' => 'H']),
                    'RI' => DB::table('rstxn_rihdrs')->where('rihdr_no', $noTransaksi)->update([
                        'status_pulang' => 'H',
                        // ri_titip = akumulasi uang diterima (pembayaran piutang menambahnya).
                        'ri_titip' => DB::raw('GREATEST(0, NVL(ri_titip,0) - ' . $nominal . ')'),
                    ]),
                };

                $catatan = 'Hapus pembayaran piutang Rp ' . number_format($nominal, 0, ',', '.')
                    . ' (' . $bayar->tgl . ', shift ' . ($bayar->shift ?? '-') . ', kas ' . ($bayar->acc_id ?? '-') . ') — salah bayar';

                match ($sumber) {
                    'RJ' => $this->appendAdminLogRJ($noTransaksi, $catatan),
                    'UGD' => $this->appendAdminLogUGD($noTransaksi, $catatan),
                    'RI' => $this->appendAdminLogRI($noTransaksi, $catatan),
                };

                return $nominal;
            });
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            return;
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus pembayaran: ' . $e->getMessage());
            return;
        }

        // Segarkan sisa nota, riwayat, & daftar di halaman.
        $baris = $this->ambilBarisPiutang($this->items);
        $this->baris = $baris->map(fn($row) => [
            'jalur' => $row->jalur,
            'no' => (string) $row->no_transaksi,
            'nama' => (string) ($row->reg_name ?? ''),
            'regNo' => (string) ($row->reg_no ?? ''),
            'tgl' => (string) ($row->tgl ?? ''),
            'sisa' => (int) $row->sisa,
        ])->all();
        $this->totalSisa = (int) $baris->sum('sisa');
        if ($this->mode !== 'bundel') {
            $this->bayar = $this->totalSisa;
        }
        [$jalurDipilih, $noDipilih] = explode('|', (string) $this->notaDipilih);
        $this->riwayatBayar = $this->ambilRiwayatBayar([['jalur' => $jalurDipilih, 'no' => $noDipilih]]);

        $this->incrementVersion('modal');
        $this->dispatch('piutang.paid');
        $this->dispatch('toast', type: 'success', message: 'Pembayaran Rp ' . number_format($hasil) . ' dihapus. Saldo kas pada tanggal bayar tersebut ikut berkurang.');
    }

    /**
     * Cetak ulang kwitansi SATU baris riwayat. Baris dibaca ulang dari DB berdasar sumber + id
     * (properti publik riwayatBayar tidak dipakai menulis/mencetak — bisa diubah dari browser).
     */
    public function cetakRiwayat(string $sumber, int $id): mixed
    {
        $peta = [
            'RJ' => ['rstxn_rjcashins', 'rjc_dtl', 'rj_no', 'rjc_date', 'rjc_nominal'],
            'UGD' => ['rstxn_ugdcashins', 'rjc_dtl', 'rj_no', 'rjc_date', 'rjc_nominal'],
            'RI' => ['rstxn_ripaymentpdtls', 'ripay_no', 'rihdr_no', 'ripay_date', 'ripay_bayar'],
            'RI-AWAL' => ['rstxn_ripaymentdtls', 'ripay_no', 'rihdr_no', 'ripay_date', 'ripay_bayar'],
        ];

        if (!isset($peta[$sumber])) {
            return null;
        }

        [$tabel, $kolomId, $kolomNo, $kolomTgl, $kolomNominal] = $peta[$sumber];
        $jalur = $sumber === 'RI-AWAL' ? 'RI' : $sumber;

        $bayar = DB::table($tabel)
            ->where($kolomId, $id)
            ->first([
                DB::raw("{$kolomNo} as no_transaksi"),
                DB::raw("to_char({$kolomTgl},'dd/mm/yyyy hh24:mi:ss') as tgl"),
                DB::raw("NVL({$kolomNominal},0) as nominal"),
                'shift', 'acc_id', 'emp_id',
            ]);

        // Hanya nota yang sedang dibuka di modal ini.
        if (!$bayar || $this->notaDipilih !== $jalur . '|' . $bayar->no_transaksi) {
            $this->dispatch('toast', type: 'error', message: 'Data pembayaran tidak ditemukan.');
            return null;
        }

        $header = match ($jalur) {
            'RJ' => DB::table('rstxn_rjhdrs as h')->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
                ->where('h.rj_no', $bayar->no_transaksi)->first(['h.reg_no', 'p.reg_name', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as tgl")]),
            'UGD' => DB::table('rstxn_ugdhdrs as h')->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
                ->where('h.rj_no', $bayar->no_transaksi)->first(['h.reg_no', 'p.reg_name', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as tgl")]),
            'RI' => DB::table('rstxn_rihdrs as h')->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
                ->where('h.rihdr_no', $bayar->no_transaksi)->first(['h.reg_no', 'p.reg_name', DB::raw("to_char(NVL(h.exit_date,h.entry_date),'dd/mm/yyyy hh24:mi:ss') as tgl")]),
        };

        $rincian = [[
            'jalur' => $jalur,
            'no' => (string) $bayar->no_transaksi,
            'regNo' => (string) ($header->reg_no ?? ''),
            'nama' => (string) ($header->reg_name ?? ''),
            'tgl' => (string) ($header->tgl ?? ''),
            'sisaSebelum' => (int) $bayar->nominal,
            'dibayar' => (int) $bayar->nominal,
            'lunas' => false,
            // Status lunas/angsuran saat itu tak tersimpan per baris kas → kata umum.
            'jenis' => $sumber === 'RI-AWAL' ? 'Angsuran awal (titipan)' : 'Pembayaran',
        ]];

        return $this->kwitansiPembayaran($rincian, (string) ($bayar->emp_id ?? ''), (string) ($bayar->shift ?? ''), [
            'tanggal' => $bayar->tgl,
            'accId' => $bayar->acc_id,
            'keterangan' => null,
            'judulKonteks' => '',
            'cetakUlang' => true,
        ]);
    }

    /**
     * Ambil baris piutang untuk daftar nota, buang yang sudah lunas, urut FIFO
     * (tanggal transaksi terlama dulu). Tanggal dari query berupa teks dd/mm/yyyy
     * hh24:mi:ss, jadi diurutkan di PHP — jumlah barisnya kecil.
     */
    protected function ambilBarisPiutang(array $items): \Illuminate\Support\Collection
    {
        return $this->piutangBanyakTransaksi($items)
            ->filter(fn($row) => (int) $row->sisa > 0)
            ->sortBy(fn($row) => $this->kunciUrutTanggal((string) ($row->tgl ?? '')))
            ->values();
    }

    /**
     * Riwayat pembayaran nota terpilih, urut tanggal (terlama dulu). Sumber = baris kas per jalur:
     *   RJ  rstxn_rjcashins · UGD rstxn_ugdcashins (rjc_desc 'PIUT …' = dari menu piutang)
     *   RI  rstxn_ripaymentpdtls (bayar pulang + piutang — tak ada penanda sumber)
     *       + rstxn_ripaymentdtls (angsuran awal / titipan selama dirawat)
     */
    protected function ambilRiwayatBayar(array $items): array
    {
        $perJalur = [];
        foreach ($items as $item) {
            $perJalur[$item['jalur']][] = (int) $item['no'];
        }

        $kolomUmum = fn(string $alias) => [
            "{$alias}.acc_id",
            'a.acc_name',
            'e.emp_name',
            "{$alias}.emp_id",
            "{$alias}.shift",
        ];

        $riwayat = collect();

        foreach ($perJalur as $jalur => $daftarNo) {
            foreach (array_chunk(array_values(array_unique($daftarNo)), 900) as $potongan) {
                $sumberList = match ($jalur) {
                    'RJ' => [['rstxn_rjcashins', 'rj_no', 'rjc_date', 'rjc_nominal', 'rjc_desc', null, 'rjc_dtl', 'RJ']],
                    'UGD' => [['rstxn_ugdcashins', 'rj_no', 'rjc_date', 'rjc_nominal', 'rjc_desc', null, 'rjc_dtl', 'UGD']],
                    'RI' => [
                        ['rstxn_ripaymentpdtls', 'rihdr_no', 'ripay_date', 'ripay_bayar', null, null, 'ripay_no', 'RI'],
                        ['rstxn_ripaymentdtls', 'rihdr_no', 'ripay_date', 'ripay_bayar', null, 'Angsuran awal', 'ripay_no', 'RI-AWAL'],
                    ],
                    default => [],
                };

                foreach ($sumberList as [$tabel, $kolomNo, $kolomTgl, $kolomNominal, $kolomDesc, $labelTetap, $kolomId, $sumber]) {
                    $rows = DB::table("{$tabel} as c")
                        ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 'c.acc_id')
                        ->leftJoin('immst_employers as e', 'e.emp_id', '=', 'c.emp_id')
                        ->whereIn("c.{$kolomNo}", $potongan)
                        ->select(array_merge($kolomUmum('c'), [
                            DB::raw("c.{$kolomNo} as no_transaksi"),
                            DB::raw("c.{$kolomId} as id_bayar"),
                            DB::raw("to_char(c.{$kolomTgl},'dd/mm/yyyy hh24:mi:ss') as tgl"),
                            DB::raw("NVL(c.{$kolomNominal},0) as nominal"),
                            DB::raw($kolomDesc ? "c.{$kolomDesc} as keterangan" : 'CAST(NULL AS VARCHAR2(10)) as keterangan'),
                            // RI: pembayaran SESUDAH tanggal pulang = dari menu piutang (bayar pulang bertanggal exit_date).
                            DB::raw($sumber === 'RI'
                                ? "CASE WHEN c.{$kolomTgl} > (SELECT h.exit_date FROM rstxn_rihdrs h WHERE h.rihdr_no = c.{$kolomNo}) THEN 1 ELSE 0 END as setelah_pulang"
                                : '0 as setelah_pulang'),
                        ]))
                        ->get();

                    foreach ($rows as $row) {
                        $label = $labelTetap;
                        if ($label === null && $kolomDesc !== null) {
                            $label = str_starts_with((string) $row->keterangan, 'PIUT') ? 'Piutang' : 'Kasir';
                        }

                        $riwayat->push([
                            'jalur' => $jalur,
                            'no' => (string) $row->no_transaksi,
                            'tgl' => (string) $row->tgl,
                            'shift' => (string) ($row->shift ?? ''),
                            'accId' => (string) ($row->acc_id ?? ''),
                            'accName' => (string) ($row->acc_name ?? ''),
                            'kasir' => (string) ($row->emp_name ?: ($row->emp_id ?? '')),
                            'nominal' => (int) $row->nominal,
                            'label' => $label,
                            'sumber' => $sumber,
                            'id' => (int) $row->id_bayar,
                            'bolehHapus' => $this->bolehHapusRiwayat($sumber, (string) $row->tgl, (string) ($row->keterangan ?? ''), (int) $row->setelah_pulang),
                        ]);
                    }
                }
            }
        }

        // Terbaru di atas — sama dengan riwayat di Administrasi RI.
        return $riwayat
            ->sortByDesc(fn($row) => $this->kunciUrutTanggal($row['tgl']))
            ->values()
            ->all();
    }

    /** dd/mm/yyyy hh24:mi:ss → yyyymmddhhmmss (aman untuk sortBy string). */
    protected function kunciUrutTanggal(string $tanggal): string
    {
        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $tanggal)->format('YmdHis');
        } catch (\Throwable) {
            return '99999999999999';
        }
    }

    /* ===============================
     | TULIS PER JALUR
     =============================== */
    protected function simpanBayarRj(int $rjNo, int $nominal, string $empId, string $tanggalDb, string $shift, bool $lunas, string $catatan): void
    {
        $hdr = DB::table('rstxn_rjhdrs')->select('reg_no')->where('rj_no', $rjNo)->first();

        DB::table('rstxn_rjcashins')->insert([
            'acc_id' => $this->accId,
            'rjc_dtl' => DB::raw('rjcdtl_seq.nextval'),
            'rjc_date' => DB::raw($tanggalDb),
            // Format sengaja mengikuti kasir (reg_no / no_transaksi) + penanda PIUT,
            // panjang kolom rjc_desc di Oracle belum diverifikasi — jangan diperpanjang.
            'rjc_desc' => 'PIUT ' . ($hdr->reg_no ?? '') . ' / ' . $rjNo,
            'emp_id' => $empId,
            'rj_no' => $rjNo,
            'shift' => $shift,
            'rjc_nominal' => $nominal,
        ]);

        if ($lunas) {
            DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->update([
                'txn_status' => 'L',
                'pay_date' => DB::raw($tanggalDb),
            ]);

            DB::table('rsmst_pasiens')->where('reg_no', $hdr->reg_no)->update(['lockstatus' => null]);
        }

        $this->appendAdminLogRJ($rjNo, $catatan);
    }

    protected function simpanBayarUgd(int $rjNo, int $nominal, string $empId, string $tanggalDb, string $shift, bool $lunas, string $catatan): void
    {
        $hdr = DB::table('rstxn_ugdhdrs')->select('reg_no')->where('rj_no', $rjNo)->first();

        DB::table('rstxn_ugdcashins')->insert([
            'acc_id' => $this->accId,
            'rjc_dtl' => DB::raw('rjcdtl_seq.nextval'),
            'rjc_date' => DB::raw($tanggalDb),
            'rjc_desc' => 'PIUT ' . ($hdr->reg_no ?? '') . ' / ' . $rjNo,
            'emp_id' => $empId,
            'rj_no' => $rjNo,
            'shift' => $shift,
            'rjc_nominal' => $nominal,
        ]);

        if ($lunas) {
            DB::table('rstxn_ugdhdrs')->where('rj_no', $rjNo)->update([
                'txn_status' => 'L',
                'pay_date' => DB::raw($tanggalDb),
            ]);

            DB::table('rsmst_pasiens')->where('reg_no', $hdr->reg_no)->update(['lockstatus' => null]);
        }

        $this->appendAdminLogUGD($rjNo, $catatan);
    }

    protected function simpanBayarRi(int $riHdrNo, int $nominal, string $empId, string $tanggalDb, string $shift, bool $lunas, string $catatan): void
    {
        DB::table('rstxn_ripaymentpdtls')->insert([
            'ripay_no' => DB::raw('ripayp_seq.nextval'),
            'ripay_date' => DB::raw($tanggalDb),
            'ripay_bayar' => $nominal,
            'rihdr_no' => $riHdrNo,
            'emp_id' => $empId,
            'acc_id' => $this->accId,
            'shift' => $shift,
        ]);

        // ri_titip = akumulasi uang yang sudah diterima (keputusan user).
        $ubah = ['ri_titip' => DB::raw('NVL(ri_titip,0) + ' . $nominal)];

        if ($lunas) {
            $ubah['status_pulang'] = 'L';
            $ubah['payment_date'] = DB::raw($tanggalDb);
        }

        DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->update($ubah);

        $this->appendAdminLogRI($riHdrNo, $catatan);
    }

    /* ===============================
     | GUARD & UTIL
     =============================== */
    protected function akunKasBelumTerdaftar(): bool
    {
        return DB::table('user_kas')->where('user_id', auth()->id())->count() === 0;
    }

    /**
     * Shift sesuai JAM pembayaran (bukan jam sekarang) — pola rstxn_shiftctls.
     * Null bila format belum lengkap / jam di luar master shift: kasir memilih manual
     * (dulu diam-diam jatuh ke shift 1).
     */
    protected function shiftPadaJam(string $tanggal): ?string
    {
        try {
            $jam = Carbon::createFromFormat('d/m/Y H:i:s', $tanggal)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }

        $shift = DB::table('rstxn_shiftctls')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$jam])
            ->value('shift');

        return $shift !== null ? (string) $shift : null;
    }

    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'pembayaran-piutang-actions');
        $this->resetVersion();
    }

    protected function resetFormFields(): void
    {
        $this->reset([
            'mode', 'judulKonteks', 'items', 'baris', 'totalSisa', 'notaDipilih', 'riwayatBayar',
            'tanggal', 'shift', 'accId', 'keterangan', 'bayar', 'pratinjau',
        ]);
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="pembayaran-piutang-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$mode, count($items)]) }}"
            x-data
            x-on:focus-piutang-tanggal.window="$nextTick(() => setTimeout(() => $refs.inputTanggal?.focus(), 150))"
            x-on:focus-piutang-nominal.window="$nextTick(() => setTimeout(() => { $refs.inputBayar?.focus(); $refs.inputBayar?.select(); }, 150))"
            x-on:focus-btn-proses.window="$nextTick(() => setTimeout(() => $refs.btnProses?.focus(), 150))">

            {{-- HEADER --}}
            <x-modul-dokumen.header :judul="$mode === 'bundel' ? 'Pelunasan Klaim (Bundel)' : 'Pembayaran Piutang Pasien'"
                ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                {{ $mode === 'bundel'
                    ? 'Seluruh nota tercentang dilunasi penuh; nominal mengikuti sisa saat diproses.'
                    : 'Nominal dialokasikan FIFO ke nota tercentang — nota terlama dilunasi lebih dulu.' }}
                <x-slot:badge>
                    @if (filled($judulKonteks))
                        <x-badge variant="info">{{ $judulKonteks }}</x-badge>
                    @endif
                    <x-badge variant="gray">{{ count($baris) }} nota</x-badge>
                    <x-badge variant="warning">Sisa Rp {{ number_format($totalSisa) }}</x-badge>
                </x-slot:badge>
            </x-modul-dokumen.header>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                {{-- Kiri: data pembayaran (isian ditumpuk satu kolom) · Kanan: nota yang diproses --}}
                <div class="grid items-start grid-cols-1 gap-4 xl:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">

                    <x-border-form title="Data Pembayaran">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-12 items-start">
                                {{-- Tanggal --}}
                                <div class="sm:col-span-8">
                                    <x-input-label value="Tanggal Pembayaran" :required="true" />
                                    <div class="flex items-center gap-2 mt-1">
                                        <x-text-input type="text" wire:model.live.debounce.500ms="tanggal"
                                            placeholder="dd/mm/yyyy hh:mm:ss" class="w-full text-sm"
                                            x-ref="inputTanggal" :error="$errors->has('tanggal')"
                                            x-on:keydown.enter.prevent="($refs.lovKasWrapper?.querySelector('input:not([disabled])') || $refs.inputBayar || $refs.btnProses)?.focus()" />
                                        <x-now-button wire:click.prevent="setTanggalSekarang" />
                                    </div>
                                    <x-input-error :messages="$errors->get('tanggal')" class="mt-1" />
                                </div>

                                {{-- Shift — otomatis dari jam Tanggal Pembayaran, tetap bisa dipilih manual --}}
                                <div class="sm:col-span-4">
                                    <x-input-label value="Shift" :required="true" />
                                    <x-select-input wire:model="shift" class="w-full mt-1" :error="$errors->has('shift')">
                                        <option value="">-- Shift --</option>
                                        <option value="1">Shift 1</option>
                                        <option value="2">Shift 2</option>
                                        <option value="3">Shift 3</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('shift')" class="mt-1" />
                                </div>

                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-12 items-start">
                                {{-- Akun Kas --}}
                                <div class="sm:col-span-7" x-ref="lovKasWrapper">
                                    <livewire:lov.kas.lov-kas
                                        target="kas-piutang"
                                        tipe=""
                                        label="Akun Kas (penerimaan)"
                                        placeholder="Ketik kode/nama kas..."
                                        :initialAccId="$accId"
                                        :error="$errors->has('accId')"
                                        wire:key="lov-kas-piutang-{{ $renderVersions['modal'] ?? 0 }}" />
                                    <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                                </div>
                                {{-- Nominal — sebaris dengan Akun Kas --}}
                                <div class="sm:col-span-5">
                                    <x-input-label value="Nominal Bayar (Rp)" :required="true" />
                                    <div class="mt-1">
                                        <x-text-input-number wire:model="bayar" class="text-sm"
                                            x-ref="inputBayar" :disabled="$mode === 'bundel'"
                                            :error="$errors->has('bayar')"
                                            x-on:keydown.enter.prevent="$el.blur(); $refs.inputKet?.focus()" />
                                    </div>
                                    <x-input-error :messages="$errors->get('bayar')" class="mt-1" />
                                    <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                        {{ $mode === 'bundel'
                                            ? 'Mode bundel: seluruh nota tercentang dilunasi, nominal mengikuti sisa saat diproses.'
                                            : 'Default = total sisa nota tercentang. Boleh lebih kecil untuk angsuran.' }}
                                    </p>
                                </div>

                                {{-- Keterangan --}}
                                <div class="sm:col-span-12">
                                    <x-input-label value="Untuk Pembayaran (dicetak di kwitansi, opsional)" />
                                    <x-text-input type="text" wire:model="keterangan"
                                        placeholder="Mis. biaya ambulans, selisih biaya di luar tanggungan BPJS, klaim BPJS Juli..."
                                        class="w-full mt-1 text-sm" x-ref="inputKet"
                                        x-on:keydown.enter.prevent="$refs.btnProses?.focus()" />
                                </div>
                            </div>
                        </div>
                    </x-border-form>

                    <div class="space-y-4">
                    {{-- Daftar nota yang akan dibayar --}}
                    <div class="overflow-hidden border border-hairline rounded-2xl bg-canvas dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                                Nota yang diproses ({{ count($baris) }}) — urut FIFO
                            </h3>
                        </div>
                        <div class="overflow-x-auto overflow-y-auto max-h-[40vh]">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                    <tr>
                                        <th class="px-4 py-2 font-semibold">Jalur</th>
                                        <th class="px-4 py-2 font-semibold">No. Transaksi</th>
                                        <th class="px-4 py-2 font-semibold">Pasien</th>
                                        <th class="px-4 py-2 font-semibold">Tanggal</th>
                                        <th class="px-4 py-2 font-semibold text-right">Sisa</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline dark:divide-gray-700">
                                    @foreach ($baris as $item)
                                        @php $notaAktif = $notaDipilih === $item['jalur'] . '|' . $item['no']; @endphp
                                        <tr wire:key="nota-{{ $item['jalur'] }}-{{ $item['no'] }}"
                                            wire:click="pilihNota('{{ $item['jalur'] }}', '{{ $item['no'] }}')"
                                            title="Klik untuk melihat riwayat pembayaran nota ini"
                                            class="transition cursor-pointer {{ $notaAktif ? 'bg-brand-green/10 dark:bg-brand-lime/10' : 'hover:bg-surface-soft dark:hover:bg-gray-800/40' }}">
                                            <td class="px-4 py-2">
                                                @php $variant = ['RJ' => 'info', 'UGD' => 'danger', 'RI' => 'purple'][$item['jalur']] ?? 'gray'; @endphp
                                                <x-badge :variant="$variant">{{ $item['jalur'] }}</x-badge>
                                            </td>
                                            <td class="px-4 py-2 font-mono">{{ $item['no'] }}</td>
                                            <td class="px-4 py-2">
                                                <div class="text-ink dark:text-gray-200">{{ $item['nama'] }}</div>
                                                <div class="font-mono text-xs text-muted">{{ $item['regNo'] }}</div>
                                            </td>
                                            <td class="px-4 py-2 whitespace-nowrap text-muted dark:text-gray-400">{{ $item['tgl'] }}</td>
                                            <td class="px-4 py-2 font-mono text-right text-rose-700 dark:text-rose-300">
                                                Rp {{ number_format($item['sisa']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                                    <tr>
                                        <td colspan="4" class="px-4 py-2 font-semibold text-muted dark:text-gray-400">Total Sisa</td>
                                        <td class="px-4 py-2 font-mono font-bold text-right text-ink dark:text-white">
                                            Rp {{ number_format($totalSisa) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    {{-- Riwayat pembayaran yang sudah masuk untuk nota terpilih — hanya tampil --}}
                    <div class="overflow-hidden border border-hairline rounded-2xl bg-canvas dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                                Riwayat Pembayaran
                                @if ($notaDipilih)
                                    @php $notaRiwayat = collect($baris)->first(fn($item) => $item['jalur'] . '|' . $item['no'] === $notaDipilih); @endphp
                                    — {{ str_replace('|', ' ', $notaDipilih) }}{{ $notaRiwayat ? ' · ' . $notaRiwayat['nama'] : '' }}
                                @endif
                            </h3>
                            @if ($notaDipilih)
                                <x-badge variant="gray">{{ count($riwayatBayar) }} pembayaran</x-badge>
                            @endif
                        </div>
                        <div class="overflow-x-auto overflow-y-auto max-h-[30vh]">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                    <tr>
                                        <th class="px-4 py-2 font-semibold">Nota</th>
                                        <th class="px-4 py-2 font-semibold">Tanggal</th>
                                        <th class="px-4 py-2 font-semibold">Akun Kas</th>
                                        <th class="px-4 py-2 font-semibold">Kasir</th>
                                        <th class="px-4 py-2 font-semibold text-right">Nominal</th>
                                        <th class="w-24 px-4 py-2 font-semibold text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline dark:divide-gray-700">
                                    @forelse ($riwayatBayar as $indeks => $row)
                                        <tr wire:key="riwayat-bayar-{{ $row['sumber'] }}-{{ $row['id'] }}">
                                            <td class="px-4 py-2 whitespace-nowrap">
                                                <span class="font-mono">{{ $row['jalur'] }} {{ $row['no'] }}</span>
                                                @if ($row['label'])
                                                    <div class="text-xs text-muted dark:text-gray-400">{{ $row['label'] }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 whitespace-nowrap">
                                                <div class="text-muted dark:text-gray-400">{{ $row['tgl'] }}</div>
                                                <div class="text-xs text-muted dark:text-gray-400">Shift {{ $row['shift'] ?: '-' }}</div>
                                            </td>
                                            <td class="px-4 py-2">
                                                <div class="text-ink dark:text-gray-200">{{ $row['accName'] ?: '-' }}</div>
                                                <div class="font-mono text-xs text-muted dark:text-gray-400">{{ $row['accId'] ?: '-' }}</div>
                                            </td>
                                            <td class="px-4 py-2 text-muted dark:text-gray-400">{{ $row['kasir'] ?: '-' }}</td>
                                            <td class="px-4 py-2 font-mono text-right text-ink dark:text-gray-200 whitespace-nowrap">
                                                Rp {{ number_format($row['nominal']) }}
                                            </td>
                                            <td class="px-4 py-2">
                                                <div class="flex items-center justify-center gap-1">
                                                    <x-cetak-button wire:click="cetakRiwayat('{{ $row['sumber'] }}', {{ $row['id'] }})"
                                                        title="Cetak ulang kwitansi pembayaran ini" />
                                                    @if ($row['bolehHapus'] ?? false)
                                                        {{-- Peringatan dampak arus kas — model hapus baris (bukan koreksi) --}}
                                                        <x-hapus-button action="hapusRiwayat('{{ $row['sumber'] }}', {{ $row['id'] }})"
                                                            title="Hapus Pembayaran (salah bayar)"
                                                            :message="'Hapus pembayaran Rp ' . number_format($row['nominal']) . ' tanggal ' . $row['tgl'] . ' (shift ' . ($row['shift'] ?: '-') . ', ' . ($row['accName'] ?: $row['accId']) . ')? PERHATIAN: baris kas DIHAPUS — saldo kas pada tanggal & shift tersebut (Cek Saldo Kas, operan shift, buku besar) ikut BERUBAH MUNDUR. Pastikan uangnya memang sudah dikembalikan / tidak diterima. Nota kembali menjadi piutang.'"
                                                            confirmText="Ya, hapus pembayaran" />
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-6 text-sm text-center text-muted-soft dark:text-gray-500">
                                                {{ $notaDipilih ? 'Belum ada pembayaran untuk nota ini' : 'Klik salah satu nota di atas untuk melihat riwayat pembayarannya.' }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if (!empty($riwayatBayar))
                                    <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                                        <tr>
                                            <td colspan="4" class="px-4 py-2 font-semibold text-muted dark:text-gray-400">Total sudah dibayar</td>
                                            <td class="px-4 py-2 font-mono font-bold text-right text-brand-green dark:text-brand-lime whitespace-nowrap">
                                                Rp {{ number_format(collect($riwayatBayar)->sum('nominal')) }}
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        Baris kas dicatat dengan <strong>tanggal pembayaran ini</strong>; nota yang tertutup penuh berubah menjadi Lunas.
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal"><x-ikon nama="batal" /> Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="siapkanKonfirmasi" x-ref="btnProses"
                            :disabled="$mode !== 'bundel' && (int) $bayar > $totalSisa"
                            wire:loading.attr="disabled" wire:target="siapkanKonfirmasi">
                            <span wire:loading.remove wire:target="siapkanKonfirmasi" class="inline-flex items-center gap-2"><x-ikon nama="proses" /> Proses Pembayaran</span>
                            <span wire:loading wire:target="siapkanKonfirmasi"><x-loading /> Memeriksa...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- KONFIRMASI — ringkasan alokasi FIFO sebelum uang tercatat --}}
            @if (!empty($pratinjau))
                <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/50"
                    x-data
                    x-init="$nextTick(() => $refs.btnYaProses?.focus())">
                    <div class="w-full max-w-lg overflow-hidden border shadow-xl bg-canvas rounded-2xl border-hairline dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-5 py-4 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-100">Konfirmasi Pembayaran Piutang</h3>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                Periksa nominal &amp; nota yang akan lunas sebelum diproses.
                            </p>
                        </div>

                        <div class="px-5 py-4 space-y-3">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-sm text-muted dark:text-gray-400">
                                    Bayar untuk {{ count($pratinjau['alokasi']) }} nota
                                </span>
                                <span class="text-2xl font-bold text-brand-green dark:text-brand-lime">
                                    Rp {{ number_format($pratinjau['bayar']) }}
                                </span>
                            </div>

                            <div class="overflow-y-auto border divide-y max-h-64 rounded-xl border-hairline divide-hairline dark:border-gray-700 dark:divide-gray-700">
                                @foreach ($pratinjau['alokasi'] as $row)
                                    <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm" wire:key="pratinjau-{{ $row['jalur'] }}-{{ $row['no'] }}">
                                        <div class="min-w-0">
                                            <div class="font-mono text-ink dark:text-gray-200">{{ $row['jalur'] }} {{ $row['no'] }}</div>
                                            @if ($mode === 'bundel')
                                                <div class="text-xs truncate text-muted dark:text-gray-400">{{ $row['nama'] }}</div>
                                            @endif
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="font-mono text-ink dark:text-gray-200">Rp {{ number_format($row['nominal']) }}</div>
                                            @if ($row['sisaSesudah'] === 0)
                                                <x-badge variant="success">LUNAS</x-badge>
                                            @else
                                                <span class="text-xs text-amber-700 dark:text-amber-300">sisa Rp {{ number_format($row['sisaSesudah']) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="text-sm text-muted dark:text-gray-400">
                                Akun Kas: <strong class="text-ink dark:text-gray-200">{{ $pratinjau['accName'] }}</strong>
                                · Shift <strong class="text-ink dark:text-gray-200">{{ $shift }}</strong>
                                · {{ $tanggal }}
                            </div>

                            @if ($pratinjau['bayar'] >= $pratinjau['totalSisa'])
                                <p class="px-3 py-2 text-xs border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:text-amber-200 dark:bg-amber-900/20 dark:border-amber-700">
                                    Seluruh nota terpilih akan LUNAS dan hilang dari daftar piutang. Pastikan nominalnya benar.
                                </p>
                            @endif
                        </div>

                        <div class="flex justify-end gap-2 px-5 py-4 border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                            <x-secondary-button type="button" wire:click="batalKonfirmasi"><x-ikon nama="batal" /> Batal</x-secondary-button>
                            <x-primary-button type="button" wire:click="prosesBayar" x-ref="btnYaProses"
                                wire:loading.attr="disabled" wire:target="prosesBayar">
                                <span wire:loading.remove wire:target="prosesBayar" class="inline-flex items-center gap-2"><x-ikon nama="proses" /> Ya, proses</span>
                                <span wire:loading wire:target="prosesBayar"><x-loading /> Memproses...</span>
                            </x-primary-button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-modal>
</div>
