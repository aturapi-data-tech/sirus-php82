<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['kasir-rj'];

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    // ── Ringkasan Biaya ──
    public int $rjTotal = 0;
    public ?int $rjDiskon = 0;
    public int $dspTotalAll = 0;
    public int $sudahBayar = 0;
    public int $rjSisa = 0;

    // ── Input Kasir ──
    public ?string $accId = null;
    public ?string $accName = null;
    public ?int $bayar = null;
    public int $kembalian = 0;

    // ── Status Transaksi ──
    public ?string $txnStatus = null;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);

        if ($this->rjNo) {
            $this->loadKasirRJ($this->rjNo);
        } else {
            $this->isFormLocked = false;
        }
    }

    /* ===============================
     | EVENT LISTENER
     =============================== */
    #[On('administrasi-kasir-rj.updated')]
    public function onAdministrasiKasirUpdated(): void
    {
        if ($this->rjNo) {
            $this->hitungTotal();
        }
    }

    /* Setelah Transfer ke UGD berhasil (komponen transfer-rj-ke-ugd-actions) → kunci form kasir RJ. */
    #[On('rj-transferred-to-ugd')]
    public function onRjTransferredToUgd(int $rjNo): void
    {
        if ($rjNo !== $this->rjNo) {
            return;
        }
        $this->isFormLocked = true;
        $this->txnStatus = 'I';
        $this->hitungTotal();
        $this->incrementVersion('kasir-rj');
    }

    /* ===============================
     | LOAD KASIR RJ
     =============================== */
    public function loadKasirRJ($rjNo): void
    {
        $this->resetKasir();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $this->findData($rjNo);

        if (empty($this->dataDaftarPoliRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $hdr = DB::table('rstxn_rjhdrs')->select('rj_status', 'txn_status', 'rj_diskon', 'acc_id')->where('rj_no', $rjNo)->first();

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        if ($this->checkRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $this->txnStatus = $hdr->rj_status;
        $this->rjDiskon = (int) ($hdr->rj_diskon ?? 0);

        if ($hdr->acc_id) {
            $this->accId = $hdr->acc_id;
            $this->accName = DB::table('acmst_accounts')->where('acc_id', $hdr->acc_id)->value('acc_name') ?? $hdr->acc_id;
        }

        $this->hitungTotal();
        $this->incrementVersion('kasir-rj');
    }

    private function findData(int $rjNo): void
    {
        $this->dataDaftarPoliRJ = $this->findDataRJ($rjNo) ?? [];
    }

    /* ===============================
     | HITUNG TOTAL
     =============================== */
    public function hitungTotal(): void
    {
        if (!$this->rjNo) {
            return;
        }

        $costs = $this->calculateRJCosts($this->rjNo);
        $this->rjTotal = array_sum($costs);

        $this->recalcSisa();
    }

    private function recalcSisa(): void
    {
        $this->dspTotalAll = max(0, $this->rjTotal - $this->rjDiskon);
        $this->sudahBayar = (int) DB::table('rstxn_rjcashins')->where('rj_no', $this->rjNo)->sum('rjc_nominal');
        $this->rjSisa = max(0, $this->dspTotalAll - $this->sudahBayar);
        $this->hitungKembalian();
    }

    /* ===============================
     | REAKTIF
     =============================== */
    public function updatedRjDiskon(): void
    {
        $this->rjDiskon = max(0, (int) $this->rjDiskon);
        $this->recalcSisa();
    }

    public function updatedBayar(): void
    {
        $this->hitungKembalian();
    }

    private function hitungKembalian(): void
    {
        $bayar = (int) ($this->bayar ?? 0);
        $this->kembalian = $bayar >= $this->rjSisa ? $bayar - $this->rjSisa : 0;
    }

    /* ===============================
     | VALIDASI
     =============================== */
    protected function rules(): array
    {
        return [
            'accId' => ['required', 'string'],
            'bayar' => ['required', 'integer', 'min:0'],
            'rjDiskon' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'accId.required' => 'Akun kas belum dipilih.',
            'bayar.required' => 'Kolom Bayar masih kosong.',
            'bayar.min'            => 'Nominal bayar tidak valid.',
        ];
    }

    /* ===============================
     | LOV KAS
     =============================== */
    #[On('lov.selected.kas-kasir-rj')]
    public function onKasSelected(string $target, ?array $payload): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->accName = $payload['acc_name'] ?? null;
        $this->resetErrorBag('accId');
        // Akun Kas = langkah terakhir sebelum posting (Diskon → Bayar → Akun Kas → Post).
        $this->dispatch('focus-post-transaksi');
    }

    /* ===============================
     | POST TRANSAKSI
     =============================== */
    public function postTransaksi(): void
    {
        // 1. Read-only guard
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        // 2. Cek akun kas user — pakai user_kas (bukan smmst_kases)
        $cekAkunKas = DB::table('user_kas')
            ->where('user_id', auth()->id())
            ->count();

        if ($cekAkunKas === 0) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas anda belum terkonfigurasi. Hubungi administrator.');
            return;
        }

        // 3. Cek status RJ sebelum lock
        if ($this->checkRJStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'info', message: 'Data sudah diproses.');
            return;
        }

        // 4. Validasi form
        $this->validate();

        // 5. Cek lab pending
        if ($this->checkLabPendingRJ($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Hasil Laborat belum selesai, pembayaran tidak bisa diproses.');
            return;
        }

        // 6. Ambil emp_id dari users — tidak perlu query smmst_users lagi
        $empId = auth()->user()->emp_id;

        if (!$empId) {
            $this->dispatch('toast', type: 'error', message: 'EMP ID belum diisi di profil user. Hubungi administrator.');
            return;
        }

        $bayar = (int) $this->bayar;
        $dspTotalAll = $this->rjSisa;
        $newTxnStatus = null;

        try {
            DB::transaction(function () use ($bayar, $dspTotalAll, $empId, &$newTxnStatus) {
                // Lock row
                $this->lockRJRow($this->rjNo);

                // Re-cek setelah lock (cegah double-submit)
                if ($this->checkRJStatus($this->rjNo)) {
                    throw new \RuntimeException('Data sudah diproses oleh user lain.');
                }

                $rjHdr = DB::table('rstxn_rjhdrs')->where('rj_no', $this->rjNo)->first();

                $cashRow = [
                    'acc_id' => $this->accId,
                    'rjc_dtl' => DB::raw('rjcdtl_seq.nextval'),
                    'rjc_date' => $rjHdr->rj_date,
                    'rjc_desc' => $rjHdr->reg_no . ' / ' . $rjHdr->rj_no,
                    'emp_id' => $empId,
                    'rj_no' => $this->rjNo,
                    'shift' => $rjHdr->shift,
                ];

                if ($bayar < $dspTotalAll) {
                    // CICILAN
                    DB::table('rstxn_rjcashins')->insert(array_merge($cashRow, ['rjc_nominal' => $bayar]));
                    DB::table('rstxn_rjhdrs')
                        ->where('rj_no', $this->rjNo)
                        ->update([
                            'txn_status' => 'H',
                            'pay_date' => null,
                            'acc_id' => $this->accId,
                            'rj_diskon' => $this->rjDiskon,
                            'rj_status' => 'L',
                            'emp_id' => $empId,
                        ]);
                    $newTxnStatus = 'H';
                    $this->appendAdminLogRJ($this->rjNo, 'Bayar Cicilan: Rp ' . number_format($bayar, 0, ',', '.') . ' (sisa Rp ' . number_format($dspTotalAll - $bayar, 0, ',', '.') . ')');
                } else {
                    // LUNAS
                    if ($this->rjTotal > 0) {
                        DB::table('rstxn_rjcashins')->insert(array_merge($cashRow, ['rjc_nominal' => $dspTotalAll]));
                    }
                    DB::table('rstxn_rjhdrs')
                        ->where('rj_no', $this->rjNo)
                        ->update([
                            'txn_status' => 'L',
                            'pay_date' => $rjHdr->rj_date,
                            'acc_id' => $this->accId,
                            'rj_diskon' => $this->rjDiskon,
                            'rj_status' => 'L',
                            'emp_id' => $empId,
                        ]);
                    $newTxnStatus = 'L';

                    DB::table('rsmst_pasiens')
                        ->where('reg_no', $rjHdr->reg_no)
                        ->update(['lockstatus' => null]);
                    $this->appendAdminLogRJ($this->rjNo, 'Bayar Lunas: Rp ' . number_format($dspTotalAll, 0, ',', '.'));
                }
            });

            $this->txnStatus = $newTxnStatus;
            $this->hitungTotal();
            $this->isFormLocked = true;
            $this->bayar = null;
            $this->kembalian = 0;
            $this->incrementVersion('kasir-rj');

            $msg = $newTxnStatus === 'L' ? 'Pembayaran lunas berhasil disimpan.' : 'Pembayaran sebagian (cicilan) berhasil disimpan.';

            $this->dispatch('toast', type: 'success', message: $msg);
            $this->dispatch('administrasi-rj.updated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memproses pembayaran: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BATAL TRANSAKSI
     =============================== */
    public function batalTransaksi(): void
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Tu', 'Perawat', 'Manager Umum', 'Supervisor Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses untuk membatalkan transaksi.');
            return;
        }

        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        // CATATAN: lab pending TIDAK memblokir batal (operasi MUNDUR).
        // Membatalkan pembayaran tak menyentuh lab RJ (tetap status_rjri='RJ',
        // ref_no=rj_no, tetap bisa diproses). Guard lab-pending hanya untuk operasi
        // MAJU (postTransaksi / transfer), bukan untuk membatalkan.

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $hdr = DB::table('rstxn_rjhdrs')->select('rj_status', 'txn_status', 'reg_no')->where('rj_no', $this->rjNo)->first();

                if (!$hdr) {
                    throw new \RuntimeException('Data transaksi tidak ditemukan.');
                }

                DB::table('rstxn_rjcashins')->where('rj_no', $this->rjNo)->delete();

                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'txn_status' => 'A',
                        'pay_date' => null,
                        'acc_id' => null,
                        'rj_diskon' => 0,
                        'rj_status' => 'A',
                        'emp_id' => null,
                    ]);

                if ($hdr->reg_no) {
                    DB::table('rsmst_pasiens')
                        ->where('reg_no', $hdr->reg_no)
                        ->update(['lockstatus' => null]);
                }

                $this->appendAdminLogRJ($this->rjNo, 'Batal Transaksi Pembayaran');
            });

            $this->txnStatus = null;
            $this->rjDiskon = 0;
            $this->accId = null;
            $this->accName = null;
            $this->bayar = null;
            $this->kembalian = 0;
            $this->isFormLocked = false;

            $this->hitungTotal();
            $this->incrementVersion('kasir-rj');

            $this->dispatch('toast', type: 'success', message: 'Transaksi berhasil dibatalkan.');
            $this->dispatch('administrasi-rj.updated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membatalkan transaksi: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BATAL TRANSFER UGD
     =============================== */
    /* ===============================
     | BATAL TRANSAKSI RJ → status 'F' (Aktif → Batal)
     | Alur administrasi SENDIRI (standar A→F, seperti RI batalInap). Soft-cancel:
     | record tetap, laporan mengecualikan 'F'. TERPISAH dari Task ID 99 (BPJS) —
     | taskId99 hanya lapor batal ke antrean BPJS, tak menyentuh status transaksi ini.
     =============================== */
    public function batalKunjungan(): void
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Supervisor Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin dan Supervisor TU yang dapat membatalkan.');
            return;
        }

        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $hdr = DB::table('rstxn_rjhdrs')
                    ->select('reg_no', 'rj_status')
                    ->where('rj_no', $this->rjNo)
                    ->first();

                if (!$hdr) {
                    throw new \RuntimeException('Data RJ tidak ditemukan.');
                }

                $status = $hdr->rj_status ?? 'A';
                if ($status === 'F') {
                    throw new \RuntimeException('Transaksi sudah berstatus Batal.');
                }
                if ($status === 'L') {
                    throw new \RuntimeException('Sudah Lunas — batalkan pembayaran (Batal Transaksi) dulu.');
                }
                if ($status === 'I') {
                    throw new \RuntimeException('Sedang transfer — gunakan Batal Transfer UGD.');
                }
                if ($status !== 'A') {
                    throw new \RuntimeException('Status bukan Aktif, tidak bisa dibatalkan.');
                }

                // Guard: belum ada transaksi layanan (batal hanya untuk pendaftaran/kunjungan yang belum jalan).
                $adaTransaksi =
                    DB::table('rstxn_rjactemps')->where('rj_no', $this->rjNo)->exists()
                    || DB::table('rstxn_rjaccdocs')->where('rj_no', $this->rjNo)->exists()
                    || DB::table('rstxn_rjactparams')->where('rj_no', $this->rjNo)->exists()
                    || DB::table('rstxn_rjobats')->where('rj_no', $this->rjNo)->exists()
                    || DB::table('rstxn_rjlabs')->where('rj_no', $this->rjNo)->exists()
                    || DB::table('rstxn_rjrads')->where('rj_no', $this->rjNo)->exists()
                    || DB::table('rstxn_rjothers')->where('rj_no', $this->rjNo)->exists()
                    || DB::table('rstxn_rjcashins')->where('rj_no', $this->rjNo)->exists();

                if ($adaTransaksi) {
                    throw new \RuntimeException('Sudah ada transaksi layanan / pembayaran — batal tidak bisa dilakukan.');
                }

                // Set Batal (soft). Task ID 99 (BPJS) TIDAK disentuh.
                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update(['rj_status' => 'F', 'txn_status' => 'F']);

                // Unlock pasien.
                if ($hdr->reg_no) {
                    DB::table('rsmst_pasiens')
                        ->where('reg_no', $hdr->reg_no)
                        ->update(['lockstatus' => null]);
                }

                $this->appendAdminLogRJ($this->rjNo, 'Batal Transaksi RJ (status F)');
            });

            $this->txnStatus = 'F';
            $this->isFormLocked = true;
            $this->hitungTotal();
            $this->incrementVersion('kasir-rj');
            $this->dispatch('toast', type: 'success', message: 'Transaksi RJ dibatalkan (status Batal).');
            $this->dispatch('administrasi-rj.updated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal batal: ' . $e->getMessage());
        }
    }

    public function batalTransferUGD(): void
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Tu', 'Perawat', 'Manager Umum', 'Supervisor Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses untuk membatalkan transfer.');
            return;
        }

        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        // Cari UGD hasil transfer. Link UTAMA = baris rstxn_ugdtempadmins flag 'RJ'
        // (tempadm_ref = rj_no RJ → rj_no = ugdRjNo). Fallback ke rstxn_ugdbiayaselamadirjs
        // utk data transfer lama yang tak punya baris link ini.
        $ugdRjNo = DB::table('rstxn_ugdtempadmins')
            ->where('tempadm_flag', 'RJ')
            ->where('tempadm_ref', $this->rjNo)
            ->value('rj_no');

        if (!$ugdRjNo) {
            $ugdRjNo = DB::table('rstxn_ugdbiayaselamadirjs')
                ->where('rj_no', $this->rjNo)
                ->value('rj_no_rsugd');
        }

        if (!$ugdRjNo) {
            // Data transfer UGD tak ditemukan (UGD sudah dihapus / anomali dual-system).
            // RECOVERY: kalau RJ masih terkunci status 'I', kembalikan ke 'A' agar tak nyangkut.
            $rjHdr = DB::table('rstxn_rjhdrs')->where('rj_no', $this->rjNo)->first();

            if ($rjHdr && $rjHdr->rj_status === 'I') {
                DB::transaction(function () use ($rjHdr) {
                    $this->lockRJRow($this->rjNo);
                    DB::table('rstxn_rjhdrs')
                        ->where('rj_no', $this->rjNo)
                        ->update(['rj_status' => 'A', 'txn_status' => 'A']);

                    if ($rjHdr->reg_no) {
                        DB::table('rsmst_pasiens')
                            ->where('reg_no', $rjHdr->reg_no)
                            ->update(['lockstatus' => 'RJ']);
                    }

                    $this->appendAdminLogRJ($this->rjNo, 'Batal Transfer — data UGD tak ditemukan; status RJ dikembalikan ke Aktif');
                });

                $this->isFormLocked = false;
                $this->txnStatus = 'A';
                $this->hitungTotal();
                $this->incrementVersion('kasir-rj');
                $this->dispatch('toast', type: 'warning', message: 'Data transfer UGD tidak ditemukan — status RJ dikembalikan ke Aktif.');
                $this->dispatch('administrasi-rj.updated');
                return;
            }

            $this->dispatch('toast', type: 'error', message: 'Tidak ada data transfer untuk RJ ini.');
            return;
        }

        // Cek status UGD masih aktif
        $ugdHdr = DB::table('rstxn_ugdhdrs')->where('rj_no', $ugdRjNo)->first();
        if ($ugdHdr && $ugdHdr->rj_status !== 'A') {
            $this->dispatch('toast', type: 'error', message: 'UGD #' . $ugdRjNo . ' sudah diproses (status: ' . $ugdHdr->rj_status . '). Tidak bisa dibatalkan.');
            return;
        }

        // Cek UGD belum ada transaksi (semua komponen biaya + pembayaran)
        $ugdAdaTransaksi =
            DB::table('rstxn_ugdobats')->where('rj_no', $ugdRjNo)->exists()
            || DB::table('rstxn_ugdlabs')->where('rj_no', $ugdRjNo)->exists()
            || DB::table('rstxn_ugdrads')->where('rj_no', $ugdRjNo)->exists()
            || DB::table('rstxn_ugdactemps')->where('rj_no', $ugdRjNo)->exists()
            || DB::table('rstxn_ugdaccdocs')->where('rj_no', $ugdRjNo)->exists()
            || DB::table('rstxn_ugdactparams')->where('rj_no', $ugdRjNo)->exists()
            || DB::table('rstxn_ugdothers')->where('rj_no', $ugdRjNo)->exists()
            || DB::table('rstxn_ugdcashins')->where('rj_no', $ugdRjNo)->exists();

        if ($ugdAdaTransaksi) {
            $this->dispatch('toast', type: 'error', message: 'UGD #' . $ugdRjNo . ' sudah ada transaksi (obat/lab/tindakan/lain-lain/pembayaran). Tidak bisa dibatalkan.');
            return;
        }

        // CATATAN: lab pending TIDAK memblokir batal transfer (operasi MUNDUR).
        // Undo transfer hanya mengembalikan pasien RJ↔UGD; lab RJ tak tersentuh
        // (tetap status_rjri='RJ', ref_no=rj_no) & tetap bisa diproses di RJ.
        // Guard lab-pending tetap ada di transfer (maju), bukan di sini.

        try {
            DB::transaction(function () use ($ugdRjNo) {
                $this->lockRJRow($this->rjNo);

                $rjHdr = DB::table('rstxn_rjhdrs')->where('rj_no', $this->rjNo)->first();
                if (!$rjHdr || $rjHdr->rj_status !== 'I') {
                    throw new \RuntimeException('Status RJ bukan Transfer UGD, tidak bisa dibatalkan.');
                }

                // Hapus DETAIL transfer (biaya kembali ke RJ; link dibersihkan).
                DB::table('rstxn_ugdbiayaselamadirjs')->where('rj_no', $this->rjNo)->delete();
                DB::table('rstxn_ugdtempadmins')->where('tempadm_flag', 'RJ')->where('tempadm_ref', $this->rjNo)->delete();

                // Header UGD JANGAN di-delete (bisa ada child → ORA-02292) — soft-cancel:
                // tandai Batal (rj_status='F'), KONSISTEN dgn UGD→RI (RI→'F'). Detail sudah dihapus di atas.
                DB::table('rstxn_ugdhdrs')
                    ->where('rj_no', $ugdRjNo)
                    ->update(['rj_status' => 'F', 'txn_status' => 'F']);

                // Kembalikan status RJ → 'A'
                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'rj_status'  => 'A',
                        'txn_status' => 'A',
                    ]);

                // Kembalikan lockstatus pasien → 'RJ'
                DB::table('rsmst_pasiens')
                    ->where('reg_no', $rjHdr->reg_no)
                    ->update(['lockstatus' => 'RJ']);

                $this->appendAdminLogRJ($this->rjNo, 'Batal Transfer ke UGD #' . $ugdRjNo);
            });

            $this->isFormLocked = false;
            $this->txnStatus = 'A';
            $this->hitungTotal();
            $this->incrementVersion('kasir-rj');

            $this->dispatch('toast', type: 'success', message: 'Batal transfer berhasil. RJ kembali aktif.');
            $this->dispatch('administrasi-rj.updated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal batal transfer: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function resetKasir(): void
    {
        $this->reset(['rjNo', 'dataDaftarPoliRJ', 'bayar', 'accId', 'accName', 'txnStatus']);
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->rjTotal = 0;
        $this->rjDiskon = 0;
        $this->dspTotalAll = 0;
        $this->sudahBayar = 0;
        $this->rjSisa = 0;
        $this->kembalian = 0;
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('kasir-rj', [$rjNo ?? 'new']) }}">

    {{-- LOCKED BANNER --}}
    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Transaksi sudah lunas — data terkunci, tidak dapat diubah.
        </div>
    @endif

    {{-- ══ PEMBAYARAN — ringkasan biaya & input pembayaran dalam satu kartu ══ --}}
    {{-- Urutan kerja kasir: Diskon → Bayar → Akun Kas → Post Transaksi --}}
    <div class="p-4 border border-hairline rounded-2xl dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40"
        x-data="{
            fokusKe(ref) {
                const coba = () => {
                    const el = this.$refs[ref];
                    if (!el || el === document.activeElement) return;
                    if (document.activeElement?.matches('input, select, textarea')) return;
                    el.focus();
                    el.select?.();
                };
                this.$nextTick(() => { coba(); setTimeout(coba, 150); });
            }
        }"
        x-on:focus-input-diskon.window="fokusKe('inputDiskon')"
        x-on:focus-input-bayar.window="fokusKe('inputBayar')"
        x-on:focus-post-transaksi.window="fokusKe('btnPost')">

        {{-- ══ PANDUAN KASIR (collapsible, default tertutup) ══ --}}
        @if (!$isFormLocked && ($txnStatus === null || $txnStatus === 'A'))
            <div x-data="{ open: false }"
                class="mb-4 overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                <button type="button" @click="open = !open"
                    class="flex items-center justify-between w-full px-3 py-2 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Panduan Kasir RJ
                    </span>
                    <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="open" x-collapse class="px-3 pb-3 text-xs text-blue-900 dark:text-blue-200">
                    <ul class="space-y-1 ml-4 list-disc">
                        <li><strong>Post Transaksi</strong> — Pilih Akun Kas, isi nominal bayar, lalu klik "Post Transaksi". Bisa cicilan (bayar sebagian) atau lunas (bayar penuh).</li>
                        <li><strong>Transfer ke UGD</strong> — Jika pasien RJ perlu dilanjutkan ke UGD, gunakan tombol "Transfer ke UGD" di daftar <em>Antrian Kasir RJ</em> atau <em>Pelayanan RJ</em> (aktif hanya saat status Antrian). Seluruh biaya RJ akan dipindahkan ke UGD dan status RJ menjadi Transfer UGD.</li>
                    </ul>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[28rem_1fr] lg:gap-6">

            {{-- KIRI: rincian biaya, dibaca atas ke bawah --}}
            <div>
                <dl class="divide-y divide-hairline dark:divide-gray-700">

                    {{-- Subtotal --}}
                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <dt class="text-base text-muted dark:text-gray-400">Subtotal Biaya</dt>
                        <dd class="text-2xl font-bold text-ink dark:text-gray-100">Rp
                            {{ number_format($rjTotal) }}</dd>
                    </div>

                    {{-- Diskon --}}
                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <dt class="text-base font-semibold text-amber-700 dark:text-amber-400">
                            Diskon @if (!$isFormLocked)
                                <span class="text-xs font-normal opacity-70">(dapat diubah)</span>
                            @endif
                        </dt>
                        <dd class="w-48 text-right">
                            @if (!$isFormLocked)
                                <x-text-input-number wire:model="rjDiskon" placeholder="0"
                                    :error="$errors->has('rjDiskon')" x-ref="inputDiskon"
                                    class="text-2xl font-bold"
                                    x-on:keydown.enter.prevent="$el.blur(); $dispatch('focus-input-bayar')" />
                            @else
                                <span class="text-2xl font-bold text-amber-700 dark:text-amber-300">Rp
                                    {{ number_format($rjDiskon) }}</span>
                            @endif
                        </dd>
                    </div>

                    {{-- Total Tagihan --}}
                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <dt class="text-base font-bold text-blue-700 dark:text-blue-300">Total Tagihan</dt>
                        <dd class="text-2xl font-bold text-blue-700 dark:text-blue-300">Rp
                            {{ number_format($dspTotalAll) }}</dd>
                    </div>

                    {{-- Dibayar — hanya bila sudah pernah ada pembayaran (cicilan) --}}
                    @if ($sudahBayar > 0)
                        <div class="flex items-center justify-between gap-4 py-2.5">
                            <dt class="text-base text-muted dark:text-gray-400">Dibayar</dt>
                            <dd class="text-2xl font-bold text-ink dark:text-gray-100">Rp
                                {{ number_format($sudahBayar) }}</dd>
                        </div>
                    @endif

                    {{-- Sisa Tagihan — jumlah yang HARUS dibayar sekarang --}}
                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <dt
                            class="text-base font-bold {{ $rjSisa > 0 ? 'text-error dark:text-rose-400' : 'text-success dark:text-success' }}">
                            Sisa Tagihan
                        </dt>
                        <dd
                            class="text-2xl font-bold {{ $rjSisa > 0 ? 'text-error dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                            Rp {{ number_format($rjSisa) }}
                        </dd>
                    </div>

                    {{-- Bayar — nominal yang diserahkan sekarang --}}
                    @if (!$isFormLocked)
                        <div class="flex items-center justify-between gap-4 py-2.5">
                            <dt class="text-base font-bold text-body dark:text-gray-200">
                                Bayar <span class="text-xs font-normal opacity-70">(Rp)</span>
                            </dt>
                            <dd class="w-48">
                                <x-text-input-number wire:model="bayar" placeholder="0" :error="$errors->has('bayar')"
                                    x-ref="inputBayar" class="text-2xl font-bold"
                                    x-on:keydown.enter.prevent="$el.blur(); $dispatch('focus-lov-kas-kasir-rj')" />
                            </dd>
                        </div>

                        {{-- Hasil dari nominal yang diketik: kurang / pas / kembalian.
                             Satu baris yang berubah label & warna, supaya tidak ada dua angka
                             berbeda (Sisa vs Kembalian) yang saling membingungkan. --}}
                        @php
                            $bayarKini = (int) ($bayar ?? 0);
                            $selisih = $bayarKini - $rjSisa;
                        @endphp
                        @if ($bayarKini > 0)
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                @if ($selisih < 0)
                                    <dt class="text-base font-bold text-error dark:text-rose-400">Kurang Bayar</dt>
                                    <dd class="text-2xl font-bold text-error dark:text-rose-300">Rp
                                        {{ number_format(abs($selisih)) }}</dd>
                                @elseif ($selisih === 0)
                                    <dt class="text-base font-bold text-success dark:text-success">Pas — Lunas</dt>
                                    <dd class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">Rp 0</dd>
                                @else
                                    <dt class="text-base font-bold text-success dark:text-success">Kembalian</dt>
                                    <dd class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">Rp
                                        {{ number_format($kembalian) }}</dd>
                                @endif
                            </div>
                        @endif
                    @endif

                </dl>

                @error('rjDiskon')
                    <x-input-error :messages="$message" class="mt-1" />
                @enderror

                @error('bayar')
                    <x-input-error :messages="$message" class="mt-1" />
                @enderror

            </div>

            {{-- KANAN: input pembayaran / status transaksi --}}
            <div class="lg:pl-6 lg:border-l border-hairline dark:border-gray-700">

            @if ($isFormLocked)
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm italic text-muted-soft dark:text-gray-600">Form input dinonaktifkan.</p>
                        {{-- Batal Transfer & Batal Transaksi — Admin, Tu, Perawat, Manager Umum, Supervisor Tu (samakan dgn hak transfer) --}}
                        @hasanyrole(['Admin', 'Tu', 'Perawat', 'Manager Umum', 'Supervisor Tu'])
                        <div class="flex gap-2">
                            @if ($txnStatus === 'I')
                                <x-confirm-button variant="warning" :action="'batalTransferUGD()'" title="Batal Transfer UGD"
                                    message="Yakin ingin membatalkan transfer ke UGD? Data UGD yang dibuat dari transfer akan dihapus dan RJ kembali aktif. Hanya bisa jika UGD belum ada transaksi (obat/lab/tindakan/lain-lain/pembayaran)."
                                    confirmText="Ya, batalkan transfer" cancelText="Batal">
                                    Batal Transfer UGD
                                </x-confirm-button>
                            @else
                                <x-confirm-button variant="danger" :action="'batalTransaksi()'" title="Batal Transaksi"
                                    message="Yakin ingin membatalkan transaksi? Semua data pembayaran akan dihapus."
                                    confirmText="Ya, batalkan" cancelText="Batal">
                                    Batal Transaksi
                                </x-confirm-button>
                            @endif
                        </div>
                        @endhasanyrole
                    </div>

                    {{-- Keterangan status --}}
                    @if ($txnStatus === 'I')
                        <div class="flex items-start gap-2 px-3 py-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
                            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="font-semibold">Status: Sudah ditransfer ke UGD</p>
                                <p class="mt-1">Biaya RJ telah dipindahkan ke UGD. Jika perlu membatalkan transfer:</p>
                                <ol class="mt-1 ml-4 space-y-0.5 list-decimal">
                                    <li>Pastikan di UGD <strong>belum ada transaksi</strong> apapun (obat, lab, tindakan, lain-lain, pembayaran).</li>
                                    <li>Pastikan <strong>hasil lab RJ sudah selesai</strong> (tidak ada lab pending).</li>
                                    <li>Klik tombol <strong>"Batal Transfer UGD"</strong> di atas, lalu konfirmasi.</li>
                                    <li>Status RJ akan kembali aktif dan bisa diproses ulang (bayar atau transfer ulang).</li>
                                </ol>
                            </div>
                        </div>
                    @elseif ($txnStatus === 'L')
                        <div class="flex items-start gap-2 px-3 py-2 text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg dark:bg-emerald-900/20 dark:border-emerald-700 dark:text-emerald-300">
                            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="font-semibold">Status: Lunas</p>
                                <p class="mt-0.5">Pembayaran sudah selesai. Klik "Batal Transaksi" jika perlu membatalkan pembayaran.</p>
                            </div>
                        </div>
                    @elseif ($txnStatus === 'H')
                        <div class="flex items-start gap-2 px-3 py-2 text-xs text-violet-700 bg-violet-50 border border-violet-200 rounded-lg dark:bg-violet-900/20 dark:border-violet-700 dark:text-violet-300">
                            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="font-semibold">Status: Cicilan (Belum Lunas)</p>
                                <p class="mt-0.5">Masih ada sisa pembayaran. Klik "Batal Transaksi" untuk membatalkan semua pembayaran.</p>
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="space-y-3">

                    {{-- LOV Akun Kas — tipe="rj" agar hanya tampil kas yang aktif untuk RJ --}}
                    <div
                        x-on:focus-lov-kas-kasir-rj.window="$nextTick(() => {
                            const fokus = () => {
                                // Akun sudah terpilih → LOV render input disabled + tombol 'Ubah',
                                // jadi tombol itulah kendali Akun Kas yang bisa difokus.
                                const el = $el.querySelector('input:not([disabled])') || $el.querySelector('button');
                                if (!el || el === document.activeElement) return;
                                if (document.activeElement?.matches('input, select, textarea')) return;
                                el.focus();
                            };
                            fokus();
                            setTimeout(fokus, 150);
                        })">
                        <livewire:lov.kas.lov-kas target="kas-kasir-rj" tipe="rj" label="Akun Kas" :initialAccId="$accId"
                            wire:key="lov-kas-kasir-rj-{{ $rjNo }}-{{ $renderVersions['kasir-rj'] ?? 0 }}" />
                        <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                    </div>

                    {{-- Tombol Post — Admin, Tu. Transfer ke UGD ada di list Antrian Kasir & Pelayanan RJ. --}}
                    @hasanyrole(['Admin', 'Tu'])
                    <div class="flex gap-2">
                        <x-primary-button wire:click="postTransaksi" wire:loading.attr="disabled"
                            wire:target="postTransaksi" x-ref="btnPost">
                            <span wire:loading.remove wire:target="postTransaksi">Post Transaksi</span>
                            <span wire:loading wire:target="postTransaksi"><x-loading /></span>
                        </x-primary-button>
                    </div>
                    @endhasanyrole

                </div>

                {{-- Batal Transaksi (Aktif → Batal 'F') — Admin, Supervisor Tu. Terpisah dari Task ID 99 (BPJS). --}}
                @if ($txnStatus === 'A')
                    @hasanyrole(['Admin', 'Supervisor Tu'])
                        <div class="pt-4 mt-4 border-t border-hairline dark:border-gray-700">
                            <p class="mb-2 text-[11px] text-gray-500 dark:text-gray-400">
                                Batalkan transaksi RJ (status jadi <span class="font-semibold">Batal/F</span>) —
                                hanya bila belum ada transaksi layanan. Task ID 99 (BPJS) terpisah &amp; tak terpengaruh.
                            </p>
                            <x-confirm-button variant="danger" :action="'batalKunjungan()'" title="Batal Transaksi RJ"
                                message="Batalkan transaksi RJ ini? Status akan menjadi BATAL (F). Hanya berhasil jika belum ada transaksi layanan apa pun."
                                confirmText="Ya, batalkan" cancelText="Batal">
                                Batal Transaksi
                            </x-confirm-button>
                        </div>
                    @endhasanyrole
                @endif

                {{-- Badge status pembayaran --}}
                @if ((int) ($bayar ?? 0) >= $rjSisa)
                    <div class="flex items-center gap-1.5 mt-3">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-xs font-semibold text-success dark:text-success">
                            Pembayaran akan diproses sebagai LUNAS{{ (int) $rjSisa === 0 ? ' (BPJS / tidak ada tagihan)' : '' }}
                        </span>
                    </div>
                @elseif ((int) ($bayar ?? 0) < $rjSisa)
                    <div class="flex items-center gap-1.5 mt-3">
                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-xs font-semibold text-amber-700 dark:text-amber-400">
                            Pembayaran akan diproses sebagai CICILAN
                        </span>
                    </div>
                @endif
            @endif

            </div>
        </div>
    </div>

    {{-- TABEL RIWAYAT PEMBAYARAN --}}
    <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">

        <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-300">Riwayat Pembayaran</h3>
            @php $cashins = DB::table('rstxn_rjcashins')->where('rj_no', $rjNo)->orderBy('rjc_date')->get(); @endphp
            <x-badge variant="gray">{{ $cashins->count() }} transaksi</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-xs font-semibold text-muted uppercase dark:text-gray-400 bg-surface-soft dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Akun Kas</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 text-right">Nominal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                    @forelse ($cashins as $cash)
                        <tr wire:key="cashin-rj-{{ $cash->rjc_dtl ?? $loop->index }}" class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 text-muted dark:text-gray-400 whitespace-nowrap">
                                {{ Carbon::parse($cash->rjc_date)->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-muted dark:text-gray-400 whitespace-nowrap">
                                {{ $cash->acc_id }}
                            </td>
                            <td class="px-4 py-3 text-ink dark:text-gray-200">{{ $cash->rjc_desc }}</td>
                            <td
                                class="px-4 py-3 font-semibold text-right text-ink dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($cash->rjc_nominal) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4"
                                class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Belum ada riwayat pembayaran
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($cashins->isNotEmpty())
                    <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="3"
                                class="px-4 py-3 text-sm font-semibold text-muted dark:text-gray-400">
                                Total Dibayar
                            </td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-brand-green dark:text-brand-lime">
                                Rp {{ number_format($cashins->sum('rjc_nominal')) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
