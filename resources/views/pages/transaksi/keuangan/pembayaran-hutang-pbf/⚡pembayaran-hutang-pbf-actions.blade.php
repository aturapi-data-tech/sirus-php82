<?php

/**
 * Pembayaran Hutang PBF — Modal Proses Pembayaran.
 *
 * Equivalent dgn Oracle Forms procedure post_transaksi_angsuran (sirus):
 *   1. Validasi: bayar <= total sisa hutang nota terpilih
 *   2. INSERT 1x IMTXN_CASHOUTHDRS (master cashout)
 *   3. Loop nota terpilih (check_boxstatus='1') urut VCOUNT (urutan klik user):
 *        - Kalau bayar >= sisa nota: bayar full → INSERT cashoutdtls + receivepayments,
 *          UPDATE receivehdrs.rcv_status='L', kurangi bayar
 *        - Kalau bayar < sisa nota & bayar > 0: bayar partial (cicilan) → INSERT
 *          cashoutdtls + receivepayments dgn nominal sisa bayar, status tetap 'H'
 *   4. Reset check_boxstatus='0' & vcount=null untuk supplier ini
 *
 * Pola tampilan & langkah mengikuti Pembayaran Piutang Pasien: daftar nota yang diproses
 * (klik = riwayat pembayarannya), shift tampil & otomatis dari jam, nominal > sisa langsung
 * merah, dan dialog konfirmasi alokasi sebelum menulis. Sisa selalu dihitung ulang dari DB.
 *
 * Hapus salah bayar = hapus SATU kas keluar UTUH beserta seluruh nota yang ditutupnya
 * (jurnal "BAYAR PBF" membaca header kas keluar — menghapus sebagian akan membuat kas
 * dan hutang tak cocok). Angsuran awal dibatalkan lewat menu penerimaan, bukan di sini.
 *
 * Sumber dana (pola Forms, 908 baris data lama): KAS → cashout_value; SALDO DP → cashout_value
 * NULL + cashout_value_topup (jurnal "BAYAR PBF TOPUP": uang muka RCV6 → hutang, kas tak
 * berubah). Tidak dicampur dalam satu kas keluar. acc_id NOT NULL → untuk DP diisi akun
 * setoran DP terakhir supplier (jurnal kas mengabaikannya karena cashout_value kosong).
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    /** Hapus salah bayar — sama dengan Pembayaran Piutang. */
    private const ROLE_HAPUS_BAYAR = ['Admin', 'Supervisor Tu'];

    public ?string $suppId   = null;
    public ?string $suppName = null;
    public int     $sisaChecked = 0;
    public int     $jumlah  = 0;
    /** Nota terpilih urut VCOUNT — hanya tampilan. */
    public array   $baris = [];
    /** rcv_no yang riwayatnya sedang dibuka — dimuat saat diklik. */
    public ?string $notaDipilih = null;
    /** Pembayaran yang sudah masuk untuk nota dipilih (hanya tampil), terbaru di atas. */
    public array   $riwayatBayar = [];

    public ?string $tanggal     = null;
    /** Shift cashout — otomatis dari jam Tanggal Pembayaran, tetap bisa dipilih manual. */
    public ?string $shift       = null;
    public ?string $accId       = null; // akun cash-out (acmst_accounts.acc_id)
    public ?string $keterangan  = null;
    public ?int    $bayar       = null;
    /** 'kas' | 'dp' — sumber dana pelunasan. */
    public string  $sumberDana  = 'kas';
    /** Saldo DP supplier saat modal dibuka — tampilan; dihitung ulang saat menulis. */
    public int     $saldoDp     = 0;
    /** Pratinjau alokasi untuk dialog konfirmasi — kosong = dialog tertutup. Hanya tampilan. */
    public array   $pratinjau   = [];

    public array $renderVersions = [];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ── Open modal ── */
    #[On('hutang-pbf.openProsesBayar')]
    public function openProsesBayar(string $suppId, string $suppName, float $sisaChecked = 0, int $jumlah = 0): void
    {
        $this->resetFormFields();
        $this->suppId   = $suppId;
        $this->suppName = $suppName;

        $nota = $this->ambilNotaTerpilih();
        if ($nota->isEmpty()) {
            $this->dispatch('toast', type: 'info', message: 'Tidak ada nota terpilih dengan sisa hutang.');
            $this->dispatch('hutang-pbf.paid');
            return;
        }

        $this->isiBaris($nota);
        if (count($this->baris) === 1) {
            $this->pilihNota($this->baris[0]['rcvNo']);
        }

        $this->tanggal    = Carbon::now()->format('d/m/Y H:i:s');
        $this->shift      = $this->shiftPadaJam($this->tanggal);
        $this->saldoDp    = $this->hitungSaldoDp();
        // Saldo DP ada → dipakai dulu (sampai habis / sampai sisa hutang); sisanya bayar kas berikutnya.
        $this->sumberDana = $this->saldoDp > 0 ? 'dp' : 'kas';
        $this->bayar      = $this->nominalDefault();
        $this->keterangan = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'pembayaran-hutang-pbf-actions');
        $this->dispatch('focus-bayar-tanggal');
    }

    /* ── Riwayat per nota (klik baris nota) ── */
    public function pilihNota(string $rcvNo): void
    {
        $ada = collect($this->baris)->contains(fn ($item) => $item['rcvNo'] === $rcvNo);
        if (!$ada || ($this->notaDipilih === $rcvNo && count($this->baris) > 1)) {
            $this->notaDipilih = null;
            $this->riwayatBayar = [];
            return;
        }

        $this->notaDipilih = $rcvNo;
        $this->riwayatBayar = $this->ambilRiwayatBayar($rcvNo);
    }

    protected function nominalDefault(): int
    {
        return $this->sumberDana === 'dp' ? min($this->saldoDp, $this->sisaChecked) : $this->sisaChecked;
    }

    public function updatedSumberDana(): void
    {
        $this->sumberDana = $this->sumberDana === 'dp' ? 'dp' : 'kas';
        $this->bayar = $this->nominalDefault();
        $this->resetErrorBag(['bayar', 'accId']);
    }

    #[On('lov.selected.kas-hutang-pbf')]
    public function onKasSelected(string $target, ?array $payload): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->dispatch('focus-bayar-nominal');
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
        if ((int) $this->bayar > $this->sisaChecked) {
            $this->addError('bayar', 'Melebihi total sisa hutang Rp ' . number_format($this->sisaChecked, 0, ',', '.') . '.');
        } elseif ($this->sumberDana === 'dp' && (int) $this->bayar > $this->saldoDp) {
            $this->addError('bayar', 'Melebihi saldo DP Rp ' . number_format($this->saldoDp, 0, ',', '.') . '.');
        }
    }

    protected function rules(): array
    {
        return [
            'suppId'  => 'required|string',
            'tanggal' => 'required|date_format:d/m/Y H:i:s',
            'shift'   => 'required|in:1,2,3',
            'accId'   => $this->sumberDana === 'dp' ? 'nullable' : 'required|string|exists:acmst_accounts,acc_id',
            'bayar'   => 'required|integer|min:1',
            'sumberDana' => 'required|in:kas,dp',
        ];
    }

    protected function messages(): array
    {
        return [
            'suppId.required'     => 'Supplier tidak terdeteksi.',
            'tanggal.required'    => 'Tanggal wajib diisi.',
            'tanggal.date_format' => 'Format tanggal harus dd/mm/yyyy hh:mm:ss.',
            'shift.required'      => 'Shift belum dipilih.',
            'shift.in'            => 'Shift tidak valid.',
            'accId.required'      => 'Akun pengeluaran wajib dipilih.',
            'accId.exists'        => 'Akun pengeluaran tidak valid.',
            'bayar.required'      => 'Nominal bayar wajib diisi.',
            'bayar.min'           => 'Nominal bayar minimal Rp 1.',
        ];
    }

    /**
     * Langkah 1 Proses: validasi + hitung pratinjau alokasi (urutan klik) dari DB tanpa menulis,
     * lalu tampilkan dialog konfirmasi — salah ketik nominal terlihat sebelum uang tercatat.
     */
    public function siapkanKonfirmasi(): void
    {
        $this->validate();

        $nota = $this->ambilNotaTerpilih();
        if ($nota->isEmpty()) {
            $this->dispatch('toast', type: 'error', message: 'Semua nota terpilih sudah lunas / diproses user lain.');
            return;
        }

        $totalKini = (int) $nota->sum('sisa');
        $bayar = (int) $this->bayar;
        if ($bayar > $totalKini) {
            $this->addError('bayar', 'Melebihi total sisa hutang Rp ' . number_format($totalKini, 0, ',', '.') . '.');
            return;
        }
        if ($this->sumberDana === 'dp') {
            $this->saldoDp = $this->hitungSaldoDp();
            if ($bayar > $this->saldoDp) {
                $this->addError('bayar', 'Melebihi saldo DP Rp ' . number_format($this->saldoDp, 0, ',', '.') . '.');
                return;
            }
        }

        $sisaBayar = $bayar;
        $alokasi = [];
        foreach ($nota as $row) {
            if ($sisaBayar <= 0) {
                break;
            }
            $nominal = min($sisaBayar, (int) $row->sisa);
            $alokasi[] = [
                'rcvNo'       => (string) $row->rcv_no,
                'urut'        => $row->vcount,
                'nominal'     => $nominal,
                'sisaSesudah' => (int) $row->sisa - $nominal,
            ];
            $sisaBayar -= $nominal;
        }

        $this->pratinjau = [
            'bayar'     => $bayar,
            'totalSisa' => $totalKini,
            'accName'   => $this->sumberDana === 'dp'
                ? 'Saldo DP (sisa Rp ' . number_format($this->saldoDp - $bayar) . ')'
                : (DB::table('acmst_accounts')->where('acc_id', $this->accId)->value('acc_name') ?? $this->accId),
            'alokasi'   => $alokasi,
        ];
    }

    public function batalKonfirmasi(): void
    {
        $this->pratinjau = [];
        $this->dispatch('focus-bayar-nominal');
    }

    /* ── Langkah 2: tulis ── */
    public function prosesBayar(): void
    {
        $this->validate();
        $this->pratinjau = [];

        $bayar = (int) $this->bayar;

        // Resolve emp_id dari USERS (sirus IMTXN_CASHOUTHDRS pakai EMP_ID)
        $empId = auth()->user()->emp_id ?? null;
        if (!$empId) {
            $this->dispatch('toast', type: 'error',
                message: 'Profil pegawai Anda belum di-mapping (emp_id). Hubungi admin.');
            return;
        }

        $shift = (string) $this->shift;
        $tanggalDb = "to_date('{$this->tanggal}','dd/mm/yyyy hh24:mi:ss')";

        $totalDibayarkan = 0;
        $notaLunas = 0;
        $notaCicilan = 0;

        try {
            DB::transaction(function () use ($empId, $shift, $tanggalDb, &$totalDibayarkan, &$notaLunas, &$notaCicilan, $bayar) {
                // Re-fetch nota terpilih dgn lock, urut VCOUNT (urutan klik user)
                $nota = $this->ambilNotaTerpilih(true);

                if ($nota->isEmpty()) {
                    throw new \RuntimeException('Tidak ada nota yang ditandai untuk dibayar (check_boxstatus=1).');
                }

                $totalKini = (int) $nota->sum('sisa');
                if ($bayar > $totalKini) {
                    throw new \RuntimeException('Nominal bayar (Rp ' . number_format($bayar)
                        . ') melebihi total sisa hutang nota terpilih (Rp ' . number_format($totalKini) . ').');
                }

                $pakaiDp = $this->sumberDana === 'dp';
                $accId = $this->accId;
                if ($pakaiDp) {
                    // Kunci supplier: dua petugas tak bisa memakai saldo DP yang sama bersamaan.
                    DB::table('immst_suppliers')->where('supp_id', $this->suppId)->lockForUpdate()->first(['supp_id']);
                    $saldo = $this->hitungSaldoDp();
                    if ($bayar > $saldo) {
                        throw new \RuntimeException('Saldo DP tinggal Rp ' . number_format($saldo) . ' — kurang dari nominal bayar.');
                    }
                    // acc_id NOT NULL: akun setoran DP terakhir supplier (jurnal kas mengabaikannya).
                    $accId = DB::table('imtxn_cashouthdrtopups')->where('supp_id', $this->suppId)
                        ->orderByDesc('cashout_date')->value('acc_id');
                    if (!$accId) {
                        throw new \RuntimeException('Supplier ini belum pernah setor DP.');
                    }
                }

                // Insert master cashout (1x)
                $cashoutNo = (int) DB::selectOne('SELECT cashout_seq.nextval AS val FROM dual')->val;
                // cashout_desc VARCHAR2(100)
                $desc = mb_substr('Angsuran Atas Nama "' . $this->suppName . '"'
                    . ($pakaiDp ? ' (DP)' : '')
                    . (!empty($this->keterangan) ? ' - ' . $this->keterangan : ''), 0, 100);

                DB::table('imtxn_cashouthdrs')->insert([
                    'acc_id'        => $accId,
                    'cashout_no'    => $cashoutNo,
                    'cashout_date'  => DB::raw($tanggalDb),
                    'cashout_desc'  => $desc,
                    'cashout_value' => $pakaiDp ? null : $bayar,
                    'cashout_value_topup' => $pakaiDp ? $bayar : null,
                    'emp_id'        => $empId,
                    'supp_id'       => $this->suppId,
                    'shift'         => $shift,
                ]);

                $sisaBayar = $bayar;

                foreach ($nota as $row) {
                    if ($sisaBayar <= 0) break;

                    $rcvNo = $row->rcv_no;
                    $sisaNota = (int) $row->sisa;

                    if ($sisaBayar >= $sisaNota) {
                        // Bayar full → lunas
                        $nominalBayar = $sisaNota;
                        $rcvStatusBaru = 'L';
                        $payDateRaw = DB::raw($tanggalDb);
                        $notaLunas++;
                    } else {
                        // Bayar partial → cicilan, status tetap H
                        $nominalBayar = $sisaBayar;
                        $rcvStatusBaru = 'H';
                        $payDateRaw = null;
                        $notaCicilan++;
                    }

                    DB::table('imtxn_receivepayments')->insert([
                        'rcvp_no'    => DB::raw('rcvp_seq.nextval'),
                        'rcv_no'     => $rcvNo,
                        'rcvp_date'  => DB::raw($tanggalDb),
                        'rcvp_value' => $nominalBayar,
                        'emp_id'     => $empId,
                    ]);

                    DB::table('imtxn_cashoutdtls')->insert([
                        'cashout_no'  => $cashoutNo,
                        'rcv_no'      => $rcvNo,
                        'cashout_dtl' => DB::raw('codtl_seq.nextval'),
                    ]);

                    $updateData = ['rcv_status' => $rcvStatusBaru];
                    if ($payDateRaw !== null) {
                        $updateData['pay_date'] = $payDateRaw;
                    }

                    DB::table('imtxn_receivehdrs')
                        ->where('rcv_no', $rcvNo)
                        ->update($updateData);

                    $totalDibayarkan += $nominalBayar;
                    $sisaBayar -= $nominalBayar;
                }

                // Reset check_boxstatus & vcount untuk supplier (ekuivalen "menetralkan transaksi")
                DB::table('imtxn_receivehdrs')
                    ->where('supp_id', $this->suppId)
                    ->update([
                        'check_boxstatus' => '0',
                        'vcount'          => null,
                    ]);
            });

            $msg = "Pembayaran Rp " . number_format($totalDibayarkan) . ($this->sumberDana === 'dp' ? ' dari saldo DP' : '') . " berhasil. ";
            $msg .= "{$notaLunas} nota lunas";
            if ($notaCicilan > 0) $msg .= ", {$notaCicilan} nota cicilan";
            $msg .= '.';

            $this->dispatch('toast', type: 'success', message: $msg);
            $this->closeModal();
            $this->dispatch('hutang-pbf.paid');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memproses pembayaran: ' . $e->getMessage());
        }
    }

    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'pembayaran-hutang-pbf-actions');
        $this->resetVersion();
    }

    protected function resetFormFields(): void
    {
        $this->reset(['suppId', 'suppName', 'sisaChecked', 'jumlah', 'baris', 'notaDipilih', 'riwayatBayar',
            'tanggal', 'shift', 'accId', 'keterangan', 'bayar', 'pratinjau', 'sumberDana', 'saldoDp']);
        $this->resetValidation();
    }

    /* ── Data ── */

    /**
     * Nota tercentang supplier ini (status H) urut VCOUNT, beserta total/titipan/sisa yang dihitung
     * ulang dari DB. Rumus total sama dengan daftar: detail diskon kaskade − diskon + PPN + materai.
     * $kunci = true → header dikunci (dipakai di dalam transaksi tulis).
     */
    protected function ambilNotaTerpilih(bool $kunci = false): Collection
    {
        $query = DB::table('imtxn_receivehdrs')
            ->select([
                'rcv_no', 'vcount',
                DB::raw("to_char(rcv_date,'dd/mm/yyyy') as rcv_date_display"),
                DB::raw("to_char(due_date,'dd/mm/yyyy') as due_date_display"),
                'rcv_desc',
                DB::raw('NVL(rcv_diskon,0) as rcv_diskon'),
                DB::raw('NVL(rcv_ppn,0) as rcv_ppn'),
                DB::raw("NVL(rcv_ppn_status,'1') as rcv_ppn_status"),
                DB::raw('NVL(rcv_materai,0) as rcv_materai'),
            ])
            ->where('supp_id', $this->suppId)
            ->where('rcv_status', 'H')
            ->where('check_boxstatus', '1')
            ->orderBy('vcount')
            ->orderBy('rcv_no');

        if ($kunci) {
            $query->lockForUpdate();
        }

        $headers = $query->get();
        if ($headers->isEmpty()) {
            return collect();
        }

        $rcvNos = $headers->pluck('rcv_no')->all();

        $totalDetailMap = DB::table('imtxn_receivedtls')
            ->select('rcv_no', DB::raw("
                NVL(SUM(
                    (NVL(qty,0)*NVL(cost_price,0))
                    - ((NVL(qty,0)*NVL(cost_price,0)) * NVL(dtl_persen,0)/100)
                    - NVL(dtl_diskon,0)
                    - (((NVL(qty,0)*NVL(cost_price,0))
                        - ((NVL(qty,0)*NVL(cost_price,0)) * NVL(dtl_persen,0)/100)
                        - NVL(dtl_diskon,0))
                      * NVL(dtl_persen1,0)/100)
                    - NVL(dtl_diskon1,0)
                ),0) as total_detail"))
            ->whereIn('rcv_no', $rcvNos)
            ->groupBy('rcv_no')
            ->pluck('total_detail', 'rcv_no');

        $titipanMap = DB::table('imtxn_receivepayments')
            ->select('rcv_no', DB::raw('NVL(SUM(rcvp_value),0) as titipan'))
            ->whereIn('rcv_no', $rcvNos)
            ->groupBy('rcv_no')
            ->pluck('titipan', 'rcv_no');

        return $headers->map(function ($hdr) use ($totalDetailMap, $titipanMap) {
            $setelahDiskon = (float) ($totalDetailMap[$hdr->rcv_no] ?? 0) - (float) $hdr->rcv_diskon;
            $ppn = ((string) $hdr->rcv_ppn_status) === '1'
                ? ($setelahDiskon * (float) $hdr->rcv_ppn) / 100
                : 0;
            // Int rupiah sejak awal — selisih sub-1 rupiah kaskade PPN/diskon bikin status nyangkut di 'H'.
            $hdr->total   = (int) round($setelahDiskon + $ppn + (float) $hdr->rcv_materai);
            $hdr->titipan = (int) round((float) ($titipanMap[$hdr->rcv_no] ?? 0));
            $hdr->sisa    = $hdr->total - $hdr->titipan;
            $hdr->vcount  = $hdr->vcount !== null ? (int) $hdr->vcount : null;

            return $hdr;
        })->filter(fn ($hdr) => $hdr->sisa > 0)->values();
    }

    protected function isiBaris(Collection $nota): void
    {
        $this->baris = $nota->map(fn ($row) => [
            'rcvNo'   => (string) $row->rcv_no,
            'urut'    => $row->vcount,
            'tgl'     => (string) ($row->rcv_date_display ?? ''),
            'tempo'   => (string) ($row->due_date_display ?? ''),
            'ket'     => (string) ($row->rcv_desc ?? ''),
            'total'   => (int) $row->total,
            'titipan' => (int) $row->titipan,
            'sisa'    => (int) $row->sisa,
        ])->all();

        $this->sisaChecked = (int) $nota->sum('sisa');
        $this->jumlah = count($this->baris);
    }

    /**
     * Riwayat pembayaran satu nota dari receivepayments (sumber titipan), terbaru di atas.
     * Akun kas & shift diambil dari cashout pasangannya (cashoutdtls rcv_no + cashout_date sama);
     * pembayaran lama tanpa pasangan tetap tampil dengan akun "-".
     */
    protected function ambilRiwayatBayar(string $rcvNo): array
    {
        $bayar = DB::table('imtxn_receivepayments as p')
            ->leftJoin('immst_employers as e', 'p.emp_id', '=', 'e.emp_id')
            ->select([
                'p.rcvp_no', 'p.rcvp_value',
                DB::raw("to_char(p.rcvp_date,'dd/mm/yyyy hh24:mi:ss') as tgl"),
                'e.emp_name',
            ])
            ->where('p.rcv_no', $rcvNo)
            ->orderByDesc('p.rcvp_date')
            ->orderByDesc('p.rcvp_no')
            ->get();

        $cashout = DB::table('imtxn_cashoutdtls as d')
            ->join('imtxn_cashouthdrs as h', 'h.cashout_no', '=', 'd.cashout_no')
            ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 'h.acc_id')
            ->select([
                DB::raw("to_char(h.cashout_date,'dd/mm/yyyy hh24:mi:ss') as tgl"),
                'h.cashout_no', 'h.shift', 'h.acc_id', 'a.acc_name', 'h.cashout_desc',
                DB::raw('NVL(h.cashout_value_topup,0) as nilai_dp'),
            ])
            ->where('d.rcv_no', $rcvNo)
            ->get()
            ->groupBy('tgl');

        // Nota lain yang ikut ditutup kas keluar yang sama — untuk pesan konfirmasi hapus.
        $notaSekas = DB::table('imtxn_cashoutdtls')
            ->whereIn('cashout_no', $cashout->flatten()->pluck('cashout_no')->unique()->all() ?: [-1])
            ->get(['cashout_no', 'rcv_no'])
            ->groupBy('cashout_no')
            ->map(fn ($baris) => $baris->pluck('rcv_no')->map(fn ($no) => (string) $no)->unique()->values()->all());

        return $bayar->map(function ($row) use ($cashout, $notaSekas) {
            $pasangan = $cashout->get($row->tgl)?->first();
            $cashoutNo = (string) ($pasangan->cashout_no ?? '');

            return [
                'id'        => (int) $row->rcvp_no,
                'tgl'       => (string) $row->tgl,
                'nominal'   => (int) round((float) $row->rcvp_value),
                'petugas'   => (string) ($row->emp_name ?? ''),
                'cashoutNo' => (string) ($pasangan->cashout_no ?? ''),
                'shift'     => (string) ($pasangan->shift ?? ''),
                'accId'     => (float) ($pasangan->nilai_dp ?? 0) > 0 ? '' : (string) ($pasangan->acc_id ?? ''),
                'accName'   => (float) ($pasangan->nilai_dp ?? 0) > 0 ? 'Saldo DP' : (string) ($pasangan->acc_name ?? ''),
                'notaSekas' => $cashoutNo !== '' ? ($notaSekas[$cashoutNo] ?? []) : [],
                'angsuranAwal' => str_starts_with((string) ($pasangan->cashout_desc ?? ''), 'Angsuran Awal'),
                'bolehHapus' => $cashoutNo !== ''
                    && $this->bolehHapusPembayaran((string) $row->tgl, (string) ($pasangan->cashout_desc ?? '')),
            ];
        })->all();
    }

    protected function bolehHapusPembayaran(string $tanggal, string $keterangan): bool
    {
        if (!auth()->user()?->hasAnyRole(self::ROLE_HAPUS_BAYAR)) {
            return false;
        }
        if (str_starts_with($keterangan, 'Angsuran Awal')) {
            return false; // dibatalkan lewat menu penerimaan (batal posting)
        }
        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $tanggal)->year === (int) now()->year;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Hapus satu kas keluar (salah bayar) beserta pasangan receivepayments di SETIAP nota yang
     * ditutupnya. receivepayments tak menyimpan cashout_no, jadi pasangan = rcv_no + tanggal-jam
     * yang sama dengan cashout_date; wajib tepat satu per nota dan jumlahnya = cashout_value —
     * kalau tidak (data lama Forms), ditolak daripada menghapus baris yang salah.
     */
    public function hapusPembayaran(int $cashoutNo): void
    {
        try {
            $hasil = DB::transaction(function () use ($cashoutNo) {
                $kas = DB::table('imtxn_cashouthdrs')->where('cashout_no', $cashoutNo)->lockForUpdate()->first([
                    'cashout_no', 'supp_id', 'cashout_desc', 'shift', 'acc_id',
                    DB::raw('NVL(cashout_value,0) + NVL(cashout_value_topup,0) as nilai'),
                    DB::raw('NVL(cashout_value_topup,0) as nilai_dp'),
                    DB::raw("to_char(cashout_date,'dd/mm/yyyy hh24:mi:ss') as tgl"),
                ]);
                if (!$kas || (string) $kas->supp_id !== (string) $this->suppId) {
                    throw new \RuntimeException('Data pembayaran tidak ditemukan.');
                }
                if (!$this->bolehHapusPembayaran((string) $kas->tgl, (string) ($kas->cashout_desc ?? ''))) {
                    throw new \RuntimeException('Pembayaran ini tidak boleh dihapus (angsuran awal, beda tahun, atau Anda tidak berwenang).');
                }

                $notaList = DB::table('imtxn_cashoutdtls')->where('cashout_no', $cashoutNo)->pluck('rcv_no')->map(fn ($no) => (string) $no)->unique()->values()->all();
                if (empty($notaList) || ($this->notaDipilih !== null && !in_array($this->notaDipilih, $notaList, true))) {
                    throw new \RuntimeException('Kas keluar ini tidak terhubung ke nota yang dipilih.');
                }

                DB::table('imtxn_receivehdrs')->whereIn('rcv_no', $notaList)->lockForUpdate()->get(['rcv_no']);

                $pasangan = DB::table('imtxn_receivepayments')
                    ->whereIn('rcv_no', $notaList)
                    ->whereRaw("to_char(rcvp_date,'dd/mm/yyyy hh24:mi:ss') = ?", [$kas->tgl])
                    ->get(['rcvp_no', 'rcv_no', 'rcvp_value']);

                $perNota = $pasangan->groupBy(fn ($row) => (string) $row->rcv_no);
                $tepat = count($notaList) === $perNota->count()
                    && $perNota->every(fn ($baris) => $baris->count() === 1)
                    && (int) round((float) $pasangan->sum('rcvp_value')) === (int) round((float) $kas->nilai);
                if (!$tepat) {
                    throw new \RuntimeException('Pasangan pembayaran per nota tidak bisa dipastikan (data lama) — tidak dihapus. Hubungi admin.');
                }

                DB::table('imtxn_receivepayments')->whereIn('rcvp_no', $pasangan->pluck('rcvp_no')->all())->delete();
                DB::table('imtxn_cashoutdtls')->where('cashout_no', $cashoutNo)->delete();
                DB::table('imtxn_cashouthdrs')->where('cashout_no', $cashoutNo)->delete();
                // Uang dicabut → nota yang tadinya lunas kembali menjadi hutang.
                DB::table('imtxn_receivehdrs')->whereIn('rcv_no', $notaList)->where('rcv_status', 'L')
                    ->update(['rcv_status' => 'H', 'pay_date' => null]);

                return ['nilai' => (int) round((float) $kas->nilai), 'dp' => (int) round((float) $kas->nilai_dp), 'nota' => $notaList, 'kas' => $kas];
            });
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            return;
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus pembayaran: ' . $e->getMessage());
            return;
        }

        Log::info('Hapus pembayaran hutang PBF (salah bayar)', [
            'cashout_no' => $cashoutNo,
            'nilai' => $hasil['nilai'],
            'nota' => $hasil['nota'],
            'tanggal' => $hasil['kas']->tgl,
            'shift' => $hasil['kas']->shift,
            'acc_id' => $hasil['kas']->acc_id,
            'user' => auth()->user()?->myuser_code,
        ]);

        // Segarkan nota, riwayat, dan daftar di halaman (tanpa mengosongkan supplier).
        $nota = $this->ambilNotaTerpilih();
        $this->isiBaris($nota);
        $this->saldoDp = $this->hitungSaldoDp();
        $this->bayar = $this->nominalDefault();
        $this->resetErrorBag('bayar');
        $this->riwayatBayar = $this->notaDipilih ? $this->ambilRiwayatBayar($this->notaDipilih) : [];
        $this->dispatch('hutang-pbf.refresh');
        $this->dispatch('toast', type: 'success', message: 'Pembayaran Rp ' . number_format($hasil['nilai'])
            . ' (' . count($hasil['nota']) . ' nota) dihapus. '
            . ($hasil['dp'] > 0 ? 'Saldo DP supplier kembali Rp ' . number_format($hasil['dp']) . '.' : 'Saldo kas pada tanggal bayar tersebut ikut berubah.'));
    }


    /** Saldo DP supplier = Σ setor (cashouthdrtopups) − Σ terpakai (cashouthdrs.cashout_value_topup). */
    protected function hitungSaldoDp(): int
    {
        if (!$this->suppId) {
            return 0;
        }
        $setor = (float) DB::table('imtxn_cashouthdrtopups')->where('supp_id', $this->suppId)->sum('cashout_value');
        $pakai = (float) DB::table('imtxn_cashouthdrs')->where('supp_id', $this->suppId)->sum('cashout_value_topup');

        return (int) round($setor - $pakai);
    }

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
};
?>

<div>
    <x-modal name="pembayaran-hutang-pbf-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$suppId]) }}"
            x-data
            x-on:focus-bayar-tanggal.window="$nextTick(() => setTimeout(() => $refs.inputTanggal?.focus(), 150))"
            x-on:focus-bayar-nominal.window="$nextTick(() => setTimeout(() => { $refs.inputBayar?.focus(); $refs.inputBayar?.select(); }, 150))"
            x-on:focus-btn-proses.window="$nextTick(() => setTimeout(() => $refs.btnProses?.focus(), 150))">

            {{-- HEADER --}}
            <x-modul-dokumen.header judul="Proses Pembayaran Hutang PBF"
                ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                Nominal dialokasikan ke nota tercentang sesuai urutan klik — nota urutan 1 dilunasi lebih dulu.
                <x-slot:badge>
                    @if (filled($suppName))
                        <x-badge variant="info">{{ $suppName }}</x-badge>
                    @endif
                    <x-badge variant="gray">{{ $jumlah }} nota</x-badge>
                    <x-badge variant="warning">Sisa Rp {{ number_format($sisaChecked) }}</x-badge>
                </x-slot:badge>
            </x-modul-dokumen.header>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                {{-- Kiri: data pembayaran · Kanan: nota yang diproses + riwayat --}}
                <div class="grid items-start grid-cols-1 gap-4 xl:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">

                    <x-border-form title="Data Pembayaran">
                        <div class="space-y-4">
                            <div class="grid items-start grid-cols-1 gap-4 sm:grid-cols-12">
                                {{-- Tanggal --}}
                                <div class="sm:col-span-8">
                                    <x-input-label value="Tanggal Pembayaran" :required="true" />
                                    <div class="flex items-center gap-2 mt-1">
                                        <x-text-input type="text" wire:model.live.debounce.500ms="tanggal"
                                            placeholder="dd/mm/yyyy hh:mm:ss" class="w-full text-sm"
                                            x-ref="inputTanggal" :error="$errors->has('tanggal')"
                                            x-on:keydown.enter.prevent="($refs.lovKasWrapper?.querySelector('input:not([disabled])') || $refs.inputBayar)?.focus()" />
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

                            {{-- Sumber dana — pola Forms: kas ATAU saldo DP, tidak dicampur --}}
                            <div>
                                <x-input-label value="Sumber Dana" :required="true" />
                                <div class="grid grid-cols-2 gap-2 mt-1">
                                    <label class="flex items-center gap-2 px-3 py-2 border cursor-pointer rounded-xl {{ $sumberDana === 'kas' ? 'border-brand-green bg-brand-green/5 dark:border-brand-lime dark:bg-brand-lime/10' : 'border-hairline dark:border-gray-700' }}">
                                        <input type="radio" wire:model.live="sumberDana" value="kas" class="text-brand-green focus:ring-brand-green">
                                        <span class="text-sm text-ink dark:text-gray-100">Kas / Transfer</span>
                                    </label>
                                    <label class="flex items-center gap-2 px-3 py-2 border rounded-xl {{ $saldoDp > 0 ? 'cursor-pointer' : 'opacity-50 cursor-not-allowed' }} {{ $sumberDana === 'dp' ? 'border-brand-green bg-brand-green/5 dark:border-brand-lime dark:bg-brand-lime/10' : 'border-hairline dark:border-gray-700' }}">
                                        <input type="radio" wire:model.live="sumberDana" value="dp" @disabled($saldoDp <= 0) class="text-brand-green focus:ring-brand-green">
                                        <span class="text-sm text-ink dark:text-gray-100">Saldo DP <span class="font-mono text-xs text-muted dark:text-gray-400">Rp {{ number_format($saldoDp, 0, ',', '.') }}</span></span>
                                    </label>
                                </div>
                                @if ($sumberDana === 'dp')
                                    <p class="mt-1 text-xs text-muted dark:text-gray-400">Dibayar dari uang muka yang sudah disetor — kas tidak berkurang lagi.</p>
                                @endif
                            </div>

                            <div class="grid items-start grid-cols-1 gap-4 sm:grid-cols-12">
                                {{-- Akun Kas (hanya sumber Kas) --}}
                                <div class="sm:col-span-7 {{ $sumberDana === 'dp' ? 'hidden' : '' }}" x-ref="lovKasWrapper">
                                    <livewire:lov.kas.lov-kas
                                        target="kas-hutang-pbf"
                                        tipe=""
                                        label="Akun Kas (sumber dana)"
                                        placeholder="Ketik kode/nama kas..."
                                        :initialAccId="$accId"
                                        :error="$errors->has('accId')"
                                        wire:key="lov-kas-hutang-pbf-{{ $suppId ?? 'empty' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                                    <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                                </div>

                                {{-- Nominal — sebaris dengan Akun Kas --}}
                                <div class="sm:col-span-5">
                                    <x-input-label value="Nominal Bayar (Rp)" :required="true" />
                                    <div class="mt-1">
                                        <x-text-input-number wire:model="bayar" class="text-sm"
                                            x-ref="inputBayar" :error="$errors->has('bayar')"
                                            x-on:keydown.enter.prevent="$el.blur(); $refs.inputKet?.focus()" />
                                    </div>
                                    <x-input-error :messages="$errors->get('bayar')" class="mt-1" />
                                    <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                        {{ $sumberDana === 'dp'
                                            ? 'Default = saldo DP atau sisa nota (yang lebih kecil).'
                                            : 'Default = total sisa nota terpilih. Boleh lebih kecil untuk angsuran.' }}
                                    </p>
                                </div>

                                {{-- Keterangan --}}
                                <div class="sm:col-span-12">
                                    <x-input-label value="Keterangan (opsional)" />
                                    <x-text-input type="text" wire:model="keterangan"
                                        placeholder="Keterangan tambahan..." class="w-full mt-1 text-sm"
                                        x-ref="inputKet"
                                        x-on:keydown.enter.prevent="$refs.btnProses?.focus()" />
                                </div>
                            </div>
                        </div>
                    </x-border-form>

                    <div class="space-y-4">
                        {{-- Nota yang akan dibayar --}}
                        <div class="overflow-hidden border border-hairline rounded-2xl bg-canvas dark:bg-gray-900 dark:border-gray-700">
                            <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                                <h3 class="text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                                    Nota yang diproses ({{ count($baris) }}) — urut klik
                                </h3>
                            </div>
                            <div class="overflow-x-auto overflow-y-auto max-h-[40vh]">
                                <table class="w-full text-sm">
                                    <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                        <tr>
                                            <th class="w-12 px-4 py-2 font-semibold text-center">#</th>
                                            <th class="px-4 py-2 font-semibold">No Nota</th>
                                            <th class="px-4 py-2 font-semibold">Tgl Beli</th>
                                            <th class="px-4 py-2 font-semibold">Keterangan</th>
                                            <th class="px-4 py-2 font-semibold text-right">Sisa</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-hairline dark:divide-gray-700">
                                        @foreach ($baris as $item)
                                            @php $notaAktif = $notaDipilih === $item['rcvNo']; @endphp
                                            <tr wire:key="nota-hutang-{{ $item['rcvNo'] }}"
                                                wire:click="pilihNota('{{ $item['rcvNo'] }}')"
                                                title="Klik untuk melihat riwayat pembayaran nota ini"
                                                class="transition cursor-pointer {{ $notaAktif ? 'bg-brand-green/10 dark:bg-brand-lime/10' : 'hover:bg-surface-soft dark:hover:bg-gray-800/40' }}">
                                                <td class="px-4 py-2 text-center">
                                                    <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white rounded-full bg-brand-green dark:bg-brand-lime dark:text-gray-900">{{ $item['urut'] ?? '-' }}</span>
                                                </td>
                                                <td class="px-4 py-2 font-mono">{{ $item['rcvNo'] }}</td>
                                                <td class="px-4 py-2 whitespace-nowrap text-muted dark:text-gray-400">
                                                    {{ $item['tgl'] }}
                                                    @if (filled($item['tempo']))
                                                        <div class="text-xs text-amber-700 dark:text-amber-400">Tempo {{ $item['tempo'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">{{ $item['ket'] ?: '-' }}</td>
                                                <td class="px-4 py-2 font-mono text-right text-rose-700 dark:text-rose-300 whitespace-nowrap">
                                                    Rp {{ number_format($item['sisa']) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                                        <tr>
                                            <td colspan="4" class="px-4 py-2 font-semibold text-muted dark:text-gray-400">Total Sisa</td>
                                            <td class="px-4 py-2 font-mono font-bold text-right text-ink dark:text-white whitespace-nowrap">
                                                Rp {{ number_format($sisaChecked) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {{-- Riwayat pembayaran nota terpilih — hanya tampil --}}
                        <div class="overflow-hidden border border-hairline rounded-2xl bg-canvas dark:bg-gray-900 dark:border-gray-700">
                            <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-hairline dark:border-gray-700">
                                <h3 class="text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                                    Riwayat Pembayaran{{ $notaDipilih ? ' — Nota ' . $notaDipilih : '' }}
                                </h3>
                                @if ($notaDipilih)
                                    <x-badge variant="gray">{{ count($riwayatBayar) }} pembayaran</x-badge>
                                @endif
                            </div>
                            <div class="overflow-x-auto overflow-y-auto max-h-[30vh]">
                                <table class="w-full text-sm">
                                    <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                        <tr>
                                            <th class="px-4 py-2 font-semibold">Tanggal</th>
                                            <th class="px-4 py-2 font-semibold">Akun Kas</th>
                                            <th class="px-4 py-2 font-semibold">Petugas</th>
                                            <th class="px-4 py-2 font-semibold text-right">Nominal</th>
                                            <th class="w-16 px-4 py-2 font-semibold text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-hairline dark:divide-gray-700">
                                        @forelse ($riwayatBayar as $row)
                                            <tr wire:key="riwayat-hutang-{{ $row['id'] }}">
                                                <td class="px-4 py-2 whitespace-nowrap">
                                                    <div class="text-muted dark:text-gray-400">{{ $row['tgl'] }}</div>
                                                    <div class="text-xs text-muted dark:text-gray-400">
                                                        Shift {{ $row['shift'] ?: '-' }}{{ $row['cashoutNo'] ? ' · Kas keluar ' . $row['cashoutNo'] : '' }}
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2">
                                                    <div class="text-ink dark:text-gray-200">{{ $row['accName'] ?: '-' }}</div>
                                                    <div class="font-mono text-xs text-muted dark:text-gray-400">{{ $row['accId'] ?: '-' }}</div>
                                                </td>
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">{{ $row['petugas'] ?: '-' }}</td>
                                                <td class="px-4 py-2 font-mono text-right text-ink dark:text-gray-200 whitespace-nowrap">
                                                    Rp {{ number_format($row['nominal']) }}
                                                </td>
                                                <td class="px-4 py-2 text-center">
                                                    @if ($row['bolehHapus'])
                                                        @php
                                                            $notaLain = array_values(array_diff($row['notaSekas'], [$notaDipilih]));
                                                        @endphp
                                                        <x-hapus-button action="hapusPembayaran({{ (int) $row['cashoutNo'] }})"
                                                            title="Hapus Pembayaran (salah bayar)"
                                                            :message="'Hapus pembayaran No ' . $row['cashoutNo'] . ' tanggal ' . $row['tgl'] . ' (shift ' . ($row['shift'] ?: '-') . ', ' . ($row['accName'] ?: $row['accId']) . ')?' . (count($notaLain) ? ' Pembayaran ini JUGA melunasi nota ' . implode(', ', $notaLain) . ' — semuanya ikut kembali menjadi hutang.' : '') . ($row['accName'] === 'Saldo DP' ? ' Saldo DP supplier kembali sebesar nilainya.' : ' PERHATIAN: saldo kas pada tanggal & shift tersebut ikut BERUBAH MUNDUR. Pastikan uangnya memang tidak jadi dibayarkan ke supplier.')"
                                                            confirmText="Ya, hapus pembayaran" />
                                                    @elseif ($row['angsuranAwal'])
                                                        <span class="text-xs text-muted dark:text-gray-400" title="Batalkan lewat menu penerimaan">awal</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-6 text-sm text-center text-muted-soft dark:text-gray-500">
                                                    {{ $notaDipilih ? 'Belum ada pembayaran untuk nota ini.' : 'Klik salah satu nota di atas untuk melihat riwayat pembayarannya.' }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if (!empty($riwayatBayar))
                                        <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                                            <tr>
                                                <td colspan="3" class="px-4 py-2 font-semibold text-muted dark:text-gray-400">Total sudah dibayar</td>
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
                        Urutan <span class="px-1.5 py-0.5 font-semibold rounded border border-hairline bg-canvas text-body dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Enter</span>:
                        Tanggal → Akun Kas → Nominal → Keterangan → tombol Proses. Nota yang tertutup penuh berubah menjadi Lunas.
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal"><x-ikon nama="batal" /> Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="siapkanKonfirmasi" x-ref="btnProses"
                            :disabled="(int) $bayar > $sisaChecked"
                            wire:loading.attr="disabled" wire:target="siapkanKonfirmasi">
                            <span wire:loading.remove wire:target="siapkanKonfirmasi" class="inline-flex items-center gap-2"><x-ikon nama="proses" /> Proses Pembayaran</span>
                            <span wire:loading wire:target="siapkanKonfirmasi"><x-loading /> Memeriksa...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- KONFIRMASI — ringkasan alokasi sebelum uang tercatat --}}
            @if (!empty($pratinjau))
                <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/50"
                    x-data
                    x-init="$nextTick(() => $refs.btnYaProses?.focus())">
                    <div class="w-full max-w-lg overflow-hidden border shadow-xl bg-canvas rounded-2xl border-hairline dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-5 py-4 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-100">Konfirmasi Pembayaran Hutang</h3>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                Periksa nominal &amp; nota yang akan lunas sebelum diproses.
                            </p>
                        </div>

                        <div class="px-5 py-4 space-y-3">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-sm text-muted dark:text-gray-400">
                                    Bayar ke {{ $suppName }} untuk {{ count($pratinjau['alokasi']) }} nota
                                </span>
                                <span class="text-2xl font-bold text-brand-green dark:text-brand-lime whitespace-nowrap">
                                    Rp {{ number_format($pratinjau['bayar']) }}
                                </span>
                            </div>

                            <div class="overflow-y-auto border divide-y max-h-64 rounded-xl border-hairline divide-hairline dark:border-gray-700 dark:divide-gray-700">
                                @foreach ($pratinjau['alokasi'] as $row)
                                    <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm" wire:key="pratinjau-hutang-{{ $row['rcvNo'] }}">
                                        <div class="font-mono text-ink dark:text-gray-200">
                                            <span class="text-muted dark:text-gray-400">{{ $row['urut'] ?? '-' }}.</span> Nota {{ $row['rcvNo'] }}
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
                                {{ $sumberDana === 'dp' ? 'Sumber' : 'Akun Kas' }}: <strong class="text-ink dark:text-gray-200">{{ $pratinjau['accName'] }}</strong>
                                · Shift <strong class="text-ink dark:text-gray-200">{{ $shift }}</strong>
                                · {{ $tanggal }}
                            </div>

                            @if ($pratinjau['bayar'] >= $pratinjau['totalSisa'])
                                <p class="px-3 py-2 text-xs border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:text-amber-200 dark:bg-amber-900/20 dark:border-amber-700">
                                    Seluruh nota terpilih akan LUNAS dan hilang dari daftar hutang. Pastikan nominalnya benar.
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
