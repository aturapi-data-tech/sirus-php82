<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['ri-resep-modal-administrasi'];

    /* ── State umum ── */
    public bool $isLoaded = false;
    public string $activeTab = 'obat';   // obat | kasir
    public ?int $slsNo = null;
    public ?int $rihdrNo = null;

    public ?string $riStatus = null;
    public ?string $slsDateDisplay = null;
    public ?string $status = null;       // A | L

    public ?int $jasaKaryawan = 3000;    // alias acte_price (label baru)

    /** @var array<int,array<string,mixed>> */
    public array $items = [];

    /* ── Inline edit obat ── */
    public ?int $editingDtl = null;
    public array $editRow = [];

    public array $formEntryObat = [
        'productId' => '',
        'productName' => '',
        'price' => '',
        'qty' => 1,
        'carapakai' => 1,
        'kapsul' => 1,
        'takar' => 'Tablet',
        'ket' => '',
        'expDate' => '',
        'etiketStatus' => 0,
    ];

    /* ── State kasir ── */
    public ?int $bayar = null;
    public int $kembalian = 0;
    public int $kekurangan = 0;
    public ?string $accId = null;
    public ?string $accName = null;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    #[On('ri-resep-administrasi.open')]
    public function open(int $slsNo, ?string $tab = null): void
    {
        $this->resetForm();
        $this->slsNo = $slsNo;
        $this->loadData();

        if (!$this->isLoaded) {
            return;
        }

        // Default tab: obat (kalau belum kasir), kasir (kalau sudah)
        $this->activeTab = $tab ?? ($this->status === 'L' ? 'kasir' : 'obat');

        $this->incrementVersion('ri-resep-modal-administrasi');
        $this->dispatch('open-modal', name: 'ri-resep-administrasi');
    }

    public function setActiveTab(string $tab): void
    {
        if (in_array($tab, ['obat', 'kasir'])) {
            $this->activeTab = $tab;
        }
    }

    /* ===============================
     | LOAD DATA
     =============================== */
    private function loadData(): void
    {
        $hdr = DB::table('imtxn_slshdrs as s')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 's.reg_no')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 's.dr_id')
            ->join('rstxn_rihdrs as r', 'r.rihdr_no', '=', 's.rihdr_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'r.klaim_id')
            ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 's.acc_id')
            ->select(
                's.sls_no', 's.status', 's.rihdr_no', 's.reg_no', 's.acte_price',
                's.acc_id', 'a.acc_name', 's.sls_bayar', 's.sls_bon',
                DB::raw("to_char(s.sls_date,'dd/mm/yyyy hh24:mi:ss') as sls_date_display"),
                'r.ri_status',
            )
            ->where('s.sls_no', $this->slsNo)
            ->first();

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data resep tidak ditemukan.');
            $this->isLoaded = false;
            return;
        }

        $this->rihdrNo = (int) $hdr->rihdr_no;
        $this->riStatus = $hdr->ri_status;
        $this->slsDateDisplay = $hdr->sls_date_display;
        $this->status = $hdr->status ?: 'A';
        $this->jasaKaryawan = (int) ($hdr->acte_price ?? 3000);
        $this->accId = $hdr->acc_id;
        $this->accName = $hdr->acc_name ?: $hdr->acc_id;
        $this->bayar = $this->status === 'L' ? (int) ($hdr->sls_bayar ?? 0) : null;

        $this->loadItems();
        $this->recalcKasir();
        $this->isLoaded = true;
    }

    private function loadItems(): void
    {
        $this->items = DB::table('imtxn_slsdtls as dtl')
            ->leftJoin('immst_products as p', 'p.product_id', '=', 'dtl.product_id')
            ->select(
                'dtl.sls_dtl', 'dtl.product_id',
                DB::raw("nvl(p.product_name,dtl.product_id) as product_name"),
                'dtl.qty', 'dtl.sales_price',
                'dtl.resep_carapakai', 'dtl.resep_kapsul', 'dtl.resep_takar', 'dtl.resep_ket',
                'dtl.etiket_status',
                DB::raw("to_char(dtl.exp_date,'yyyy-mm-dd') as exp_date"),
            )
            ->where('dtl.sls_no', $this->slsNo)
            ->orderBy('dtl.sls_dtl')
            ->get()
            ->map(fn($r) => [
                'slsDtl' => (int) $r->sls_dtl,
                'productId' => $r->product_id,
                'productName' => $r->product_name,
                'qty' => (int) ($r->qty ?? 0),
                'price' => (int) ($r->sales_price ?? 0),
                'total' => (int) ($r->sales_price ?? 0) * (int) ($r->qty ?? 0),
                'carapakai' => $r->resep_carapakai,
                'kapsul' => $r->resep_kapsul,
                'takar' => $r->resep_takar ?: 'Tablet',
                'ket' => $r->resep_ket,
                'etiketStatus' => (int) ($r->etiket_status ?? 0),
                'expDate' => $r->exp_date,                                                                  // yyyy-mm-dd (untuk input type="date" saat edit)
                'expDateDisplay' => $r->exp_date ? Carbon::parse($r->exp_date)->format('d/m/Y') : '-',     // dd/mm/yyyy (untuk tampilan tabel)
            ])
            ->toArray();
    }

    /* ===============================
     | GUARDS (computed)
     =============================== */
    #[Computed]
    public function isObatLocked(): bool
    {
        return $this->status === 'L' || strtoupper($this->riStatus ?? '') === 'P';
    }

    #[Computed]
    public function isKasirPosted(): bool
    {
        return $this->status === 'L';
    }

    #[Computed]
    public function canEditJasa(): bool
    {
        return !$this->isKasirPosted && auth()->user()->hasAnyRole(['Admin', 'Tu']);
    }

    /* ===============================
     | LOV PRODUCT (obat)
     =============================== */
    #[On('lov.selected.ri-resep-obat')]
    public function onProductSelected(?array $payload): void
    {
        if ($this->isObatLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }
        if (!$payload) {
            $this->resetFormEntry();
            return;
        }

        $this->formEntryObat['productId'] = $payload['product_id'] ?? '';
        $this->formEntryObat['productName'] = $payload['product_name'] ?? '';
        $this->formEntryObat['price'] = $payload['sales_price'] ?? 0;
        $this->formEntryObat['expDate'] = Carbon::now()->addMonths(12)->format('d/m/Y');

        $this->dispatch('focus-input-qty-obat-ri');
    }

    /* ===============================
     | INSERT OBAT
     =============================== */
    public function insertObat(): void
    {
        if ($this->isObatLocked) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi terkunci, tidak dapat ditambah.');
            return;
        }

        $this->validate(
            [
                'formEntryObat.productId' => 'required|exists:immst_products,product_id',
                'formEntryObat.price' => 'required|numeric|min:0',
                'formEntryObat.qty' => 'required|numeric|min:1',
                'formEntryObat.carapakai' => 'required|numeric|min:1',
                'formEntryObat.kapsul' => 'required|numeric|min:1',
                'formEntryObat.takar' => 'required|string',
                'formEntryObat.expDate' => 'required|date_format:d/m/Y',
            ],
            [
                'formEntryObat.productId.required' => 'Obat wajib dipilih.',
                'formEntryObat.qty.min' => 'Qty minimal 1.',
                'formEntryObat.price.min' => 'Harga tidak valid.',
                'formEntryObat.expDate.required' => 'Exp Date wajib diisi.',
                'formEntryObat.expDate.date_format' => 'Exp Date harus format dd/mm/yyyy.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockSlshdrAndGuard();

                $maxDtl = (int) DB::table('imtxn_slsdtls')->select(DB::raw('nvl(max(sls_dtl)+1,1) as m'))->value('m');
                $expFormatted = Carbon::createFromFormat('d/m/Y', $this->formEntryObat['expDate'])->startOfDay()->format('Y-m-d H:i:s');

                DB::table('imtxn_slsdtls')->insert([
                    'sls_dtl' => $maxDtl,
                    'sls_no' => $this->slsNo,
                    'product_id' => $this->formEntryObat['productId'],
                    'qty' => $this->formEntryObat['qty'],
                    'sales_price' => $this->formEntryObat['price'],
                    'exp_date' => DB::raw("to_date('{$expFormatted}','yyyy-mm-dd hh24:mi:ss')"),
                    'resep_carapakai' => $this->formEntryObat['carapakai'],
                    'resep_kapsul' => $this->formEntryObat['kapsul'],
                    'resep_takar' => $this->formEntryObat['takar'],
                    'resep_ket' => $this->formEntryObat['ket'] ?: null,
                    'etiket_status' => $this->formEntryObat['etiketStatus'],
                ]);
            });

            $this->loadItems();
            $this->recalcKasir();
            $this->resetFormEntry();
            $this->dispatch('ri-resep-focus-lov-obat');
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil ditambahkan.');
            $this->dispatch('ri-resep-refresh-after-antrian.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | INLINE EDIT OBAT
     =============================== */
    public function startEdit(int $slsDtl): void
    {
        if ($this->isObatLocked) {
            return;
        }
        $row = collect($this->items)->firstWhere('slsDtl', $slsDtl);
        if (!$row) {
            return;
        }
        $this->editingDtl = $slsDtl;
        $this->editRow = [
            'qty' => $row['qty'],
            'carapakai' => $row['carapakai'],
            'kapsul' => $row['kapsul'],
            'takar' => $row['takar'],
            'ket' => $row['ket'] ?? '',
            'expDate' => !empty($row['expDate']) ? Carbon::parse($row['expDate'])->format('d/m/Y') : '',
            'etiketStatus' => $row['etiketStatus'],
        ];
    }

    public function cancelEdit(): void
    {
        $this->editingDtl = null;
        $this->editRow = [];
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        if ($this->isObatLocked || !$this->editingDtl) {
            return;
        }

        $this->validate(
            [
                'editRow.qty' => 'required|numeric|min:1',
                'editRow.carapakai' => 'required|numeric|min:1',
                'editRow.kapsul' => 'required|numeric|min:1',
                'editRow.takar' => 'required|string',
                'editRow.expDate' => 'required|date_format:d/m/Y',
            ],
            [
                'editRow.qty.required' => 'Qty wajib diisi.',
                'editRow.qty.min' => 'Qty minimal 1.',
                'editRow.expDate.required' => 'Exp Date wajib diisi.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockSlshdrAndGuard();

                $expFormatted = Carbon::createFromFormat('d/m/Y', $this->editRow['expDate'])->startOfDay()->format('Y-m-d H:i:s');

                DB::table('imtxn_slsdtls')
                    ->where('sls_dtl', $this->editingDtl)
                    ->where('sls_no', $this->slsNo)
                    ->update([
                        'qty' => $this->editRow['qty'],
                        'resep_carapakai' => $this->editRow['carapakai'],
                        'resep_kapsul' => $this->editRow['kapsul'],
                        'resep_takar' => $this->editRow['takar'],
                        'resep_ket' => $this->editRow['ket'] ?: null,
                        'exp_date' => DB::raw("to_date('{$expFormatted}','yyyy-mm-dd hh24:mi:ss')"),
                        'etiket_status' => $this->editRow['etiketStatus'],
                    ]);
            });

            $this->loadItems();
            $this->recalcKasir();
            $this->editingDtl = null;
            $this->editRow = [];
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil diperbarui.');
            $this->dispatch('ri-resep-refresh-after-antrian.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeObat(int $slsDtl): void
    {
        if ($this->isObatLocked) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi terkunci, tidak dapat dihapus.');
            return;
        }

        try {
            DB::transaction(function () use ($slsDtl) {
                $this->lockSlshdrAndGuard();
                DB::table('imtxn_slsdtls')
                    ->where('sls_dtl', $slsDtl)
                    ->where('sls_no', $this->slsNo)
                    ->delete();
            });

            $this->loadItems();
            $this->recalcKasir();
            if ($this->editingDtl === $slsDtl) {
                $this->cancelEdit();
            }
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil dihapus.');
            $this->dispatch('ri-resep-refresh-after-antrian.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | JASA KARYAWAN (acte_price)
     =============================== */
    public function updatedJasaKaryawan(): void
    {
        // Mirip pola kasir-rj.updatedRjDiskon() — hanya update state lokal,
        // tidak persist ke DB di sini. Persist acte_price terjadi saat postTransaksi().
        $this->jasaKaryawan = max(0, (int) $this->jasaKaryawan);
        $this->recalcKasir();
    }

    /* ===============================
     | KASIR
     =============================== */
    #[On('lov.selected.ri-resep-kas-administrasi')]
    public function onKasSelected(?array $payload = null): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->accName = $payload['acc_name'] ?? null;
        $this->resetErrorBag('accId');
        // Akun Kas = langkah terakhir sebelum posting (Jasa Karyawan → Bayar → Akun Kas → Post).
        $this->dispatch('focus-post-transaksi-ri');
    }

    public function updatedBayar(): void
    {
        $this->recalcKasir();
    }

    private function recalcKasir(): void
    {
        $bayar = (int) ($this->bayar ?? 0);
        $totalAll = $this->totalAll;
        $this->kembalian = $bayar >= $totalAll ? $bayar - $totalAll : 0;
        $this->kekurangan = $bayar < $totalAll ? $totalAll - $bayar : 0;
    }

    public function postTransaksi(): void
    {
        if (!auth()->user()->hasAnyRole(['Apoteker', 'Admin', 'Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses untuk memproses kasir.');
            return;
        }
        if ($this->isKasirPosted) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi sudah diproses.');
            return;
        }

        $cekKas = DB::table('user_kas')->where('user_id', auth()->id())->count();
        if ($cekKas === 0) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas anda belum terkonfigurasi. Hubungi administrator.');
            return;
        }
        if (strtoupper($this->riStatus ?? '') === 'P') {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi tidak dapat diproses.');
            return;
        }

        $empId = auth()->user()->emp_id;
        if (!$empId) {
            $this->dispatch('toast', type: 'error', message: 'EMP ID belum diisi di profil user. Hubungi administrator.');
            return;
        }

        $this->validate(
            [
                'accId' => 'required|string',
                'bayar' => 'required|integer|min:0',
            ],
            [
                'accId.required' => 'Akun kas belum dipilih.',
                'bayar.required' => 'Nominal bayar belum diisi.',
            ],
        );

        $bayar = (int) $this->bayar;
        $totalAll = $this->totalAll;
        $isBon = $bayar < $totalAll;
        $sisa = $isBon ? $totalAll - $bayar : 0;

        try {
            DB::transaction(function () use ($bayar, $totalAll, $isBon, $sisa, $empId) {
                DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->lockForUpdate()->first();
                $current = DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->first();
                if (!$current) {
                    throw new \RuntimeException('Data resep tidak ditemukan.');
                }
                if (strtoupper($current->status ?? 'A') === 'L') {
                    throw new \RuntimeException('Data sudah diproses oleh user lain.');
                }

                $shift =
                    DB::table('rstxn_shiftctls')
                        ->select('shift')
                        ->whereRaw("to_char(sysdate,'HH24:MI:SS') between shift_start and shift_end")
                        ->value('shift') ?? ($current->shift ?? 1);

                DB::table('imtxn_slshdrs')
                    ->where('sls_no', $this->slsNo)
                    ->update([
                        'status' => 'L',
                        'sls_total' => $totalAll,
                        'sls_bayar' => $bayar,
                        'sls_bon' => $isBon ? $bayar : null,
                        'bayar' => $bayar,        // legacy: nominal cash yang dibayar
                        'sisa' => $sisa,          // legacy: sisa kurang bayar (= bon)
                        'acc_id' => $this->accId,
                        'acte_price' => $this->jasaKaryawan,
                        'shift' => $shift,
                        'emp_id' => $empId,       // kasir yang post (sebelumnya placeholder '1' dari eresep)
                        'waktu_selesai_pelayanan' => DB::raw('sysdate'),
                    ]);

                if ($isBon) {
                    $maxBonNo = (int) DB::table('rstxn_ribonobats')->select(DB::raw('nvl(max(ribon_no)+1,1) as m'))->value('m');
                    DB::table('rstxn_ribonobats')->insert([
                        'ribon_no' => $maxBonNo,
                        'ribon_desc' => 'BR TGL: ' . ($current->sls_date ? Carbon::parse($current->sls_date)->format('d/m/Y') : '-') . ' NO BR: ' . $this->slsNo,
                        'ribon_date' => $current->sls_date,
                        'ribon_price' => $totalAll - $bayar,
                        'rihdr_no' => $this->rihdrNo,
                        'sls_no' => $this->slsNo,
                    ]);
                }
            });

            $this->status = 'L';
            // Invalidate computed cache (Livewire 3 caches per-request)
            unset($this->isKasirPosted, $this->isObatLocked, $this->canEditJasa);
            $this->incrementVersion('ri-resep-modal-administrasi');

            $msg = $isBon
                ? 'Transaksi tersimpan. Sisa Rp ' . number_format($totalAll - $bayar) . ' masuk Bon Inap.'
                : 'Transaksi LUNAS tersimpan.';

            $this->dispatch('toast', type: 'success', message: $msg);
            $this->dispatch('ri-resep-refresh-after-antrian.saved');
            $this->dispatch('cetak-kwitansi-ri-obat.open', slsNo: $this->slsNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function batalTransaksi(): void
    {
        if (!auth()->user()->hasAnyRole(['Apoteker', 'Admin', 'Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses untuk membatalkan transaksi.');
            return;
        }
        if ($this->status !== 'L') {
            $this->dispatch('toast', type: 'error', message: 'Transaksi belum diproses, tidak perlu dibatalkan.');
            return;
        }
        if (strtoupper($this->riStatus ?? '') === 'P') {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi tidak dapat dibatalkan.');
            return;
        }

        try {
            DB::transaction(function () {
                DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->lockForUpdate()->first();
                $current = DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->first();
                if (!$current || strtoupper($current->status ?? 'A') !== 'L') {
                    throw new \RuntimeException('Transaksi sudah dalam status belum diproses.');
                }

                DB::table('imtxn_slshdrs')
                    ->where('sls_no', $this->slsNo)
                    ->update([
                        'status' => 'A',
                        'sls_bayar' => null,
                        'sls_bon' => null,
                        'bayar' => null,
                        'sisa' => null,
                        'acc_id' => null,
                        'waktu_selesai_pelayanan' => null,
                        // emp_id sengaja TIDAK direset (audit trail siapa terakhir post)
                    ]);

                DB::table('rstxn_ribonobats')->where('sls_no', $this->slsNo)->delete();
            });

            $this->status = 'A';
            $this->bayar = null;
            $this->accId = null;
            $this->accName = null;
            // Invalidate computed cache
            unset($this->isKasirPosted, $this->isObatLocked, $this->canEditJasa);
            $this->recalcKasir();
            $this->incrementVersion('ri-resep-modal-administrasi');

            $this->dispatch('toast', type: 'success', message: 'Transaksi berhasil dibatalkan.');
            $this->dispatch('ri-resep-refresh-after-antrian.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function cetakKwitansi(): void
    {
        $this->dispatch('cetak-kwitansi-ri-obat.open', slsNo: $this->slsNo);
    }

    public function cetakEtiketItem(int $slsDtl): void
    {
        $this->dispatch('cetak-etiket-obat-ri.open', slsDtl: $slsDtl);
    }

    /* ===============================
     | CLOSE & HELPERS
     =============================== */
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'ri-resep-administrasi');
        $this->resetForm();
    }

    private function lockSlshdrAndGuard(): void
    {
        DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->lockForUpdate()->first();
        $current = DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->first();
        if (!$current) {
            throw new \RuntimeException('Data SLS tidak ditemukan.');
        }
        if (strtoupper($current->status ?? 'A') === 'L') {
            throw new \RuntimeException('Transaksi sudah diproses kasir, tidak dapat diubah.');
        }
    }

    public function resetFormEntry(): void
    {
        $this->reset(['formEntryObat']);
        $this->formEntryObat['qty'] = 1;
        $this->formEntryObat['carapakai'] = 1;
        $this->formEntryObat['kapsul'] = 1;
        $this->formEntryObat['takar'] = 'Tablet';
        $this->formEntryObat['etiketStatus'] = 0;
        $this->resetValidation();
        $this->incrementVersion('ri-resep-modal-administrasi');
    }

    private function resetForm(): void
    {
        $this->reset([
            'slsNo', 'rihdrNo', 'isLoaded', 'activeTab',
            'riStatus', 'slsDateDisplay', 'status', 'items',
            'editingDtl', 'editRow',
            'bayar', 'kembalian', 'kekurangan', 'accId', 'accName',
        ]);
        $this->jasaKaryawan = 3000;
        $this->activeTab = 'obat';
        $this->resetFormEntry();
        $this->resetVersion();
    }

    #[Computed]
    public function subtotal(): int
    {
        return (int) collect($this->items)->sum('total');
    }

    #[Computed]
    public function totalAll(): int
    {
        return $this->subtotal + (int) ($this->jasaKaryawan ?? 0);
    }

};
?>

<div>
    <x-modal name="ri-resep-administrasi" size="full" height="full" focusable>
        <div wire:key="{{ $this->renderKey('ri-resep-modal-administrasi', [$slsNo ?? 'new']) }}"
            x-data
            x-on:focus-input-qty-obat-ri.window="$nextTick(() => $refs.inputQtyRi?.focus())"
            x-on:ri-resep-focus-lov-obat.window="$nextTick(() => $refs.lovObatRi?.querySelector('input')?.focus())">

            {{-- ═══════════ HEADER — pola Administrasi RJ ═══════════ --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative space-y-3" x-data="{ expanded: false }">

                    {{-- ROW 1: Display Pasien | Total (clickable toggle) | Close --}}
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <livewire:pages::transaksi.ri-resep.display-pasien-ri-resep.display-pasien-ri-resep
                                :slsNo="$slsNo" wire:key="ri-resep-display-pasien-{{ $slsNo ?? 'new' }}" />
                        </div>

                        @if ($isLoaded)
                            {{-- Total — kartu yang bisa diklik untuk membuka rincian di ROW 2 --}}
                            <button type="button" x-on:click="expanded = !expanded"
                                :title="expanded ? 'Sembunyikan rincian biaya' : 'Tampilkan rincian biaya'"
                                class="group self-end flex-shrink-0 px-8 pt-3 pb-2 min-w-[220px] text-right transition border rounded-2xl cursor-pointer bg-brand-green/10 dark:bg-brand-lime/10 border-brand-green/20 dark:border-brand-lime/20 hover:bg-brand-green/20 hover:border-brand-green/40 hover:shadow-md dark:hover:bg-brand-lime/20 dark:hover:border-brand-lime/40 focus:outline-none focus:ring-2 focus:ring-brand-green/40 dark:focus:ring-brand-lime/40">
                                <p
                                    class="mb-1 text-xs font-medium tracking-wide uppercase text-brand-green dark:text-brand-lime whitespace-nowrap">
                                    Total Tagihan
                                </p>
                                <p class="text-2xl font-bold text-ink dark:text-white tabular-nums whitespace-nowrap">
                                    Rp {{ number_format($this->totalAll) }}
                                </p>
                                <div
                                    class="flex items-center justify-end gap-1 pt-1.5 mt-1.5 text-xs font-semibold border-t border-brand-green/20 dark:border-brand-lime/20 text-muted dark:text-gray-300 whitespace-nowrap">
                                    <span>Lihat Rincian</span>
                                    <svg class="w-3.5 h-3.5 transition-transform" :class="expanded ? 'rotate-180' : ''"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </button>
                        @endif

                        {{-- Close --}}
                        <x-icon-button color="gray" type="button" wire:click="closeModal" class="flex-shrink-0">
                            <span class="sr-only">Close</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </x-icon-button>
                    </div>

                    {{-- ROW 2: Rincian biaya — collapsible, default tertutup --}}
                    @if ($isLoaded)
                        <div x-show="expanded" x-collapse
                            class="p-2 border border-hairline rounded-2xl dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40">
                            <div class="flex items-center gap-2">
                                @if ($this->isKasirPosted)
                                    <x-badge variant="danger" class="text-xs whitespace-nowrap shrink-0">Read Only</x-badge>
                                @endif
                                <div class="grid flex-1 grid-cols-2 sm:grid-cols-4 gap-1.5 min-w-0">
                                    @foreach ([['label' => 'Subtotal Obat', 'value' => $this->subtotal], ['label' => 'Jasa Karyawan', 'value' => (int) ($jasaKaryawan ?? 0)], ['label' => 'Total Tagihan', 'value' => $this->totalAll], ['label' => 'Dibayar', 'value' => (int) ($bayar ?? 0)]] as $item)
                                        <div
                                            class="px-2.5 py-1.5 bg-canvas border border-hairline rounded-xl dark:bg-gray-900 dark:border-gray-700">
                                            <p class="text-xs text-muted dark:text-gray-400 mb-0.5 truncate">
                                                {{ $item['label'] }}</p>
                                            <p class="text-xs font-semibold text-ink dark:text-gray-200 tabular-nums">
                                                Rp {{ number_format($item['value']) }}
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @if (!$isLoaded)
                <div class="px-6 py-12 text-center text-muted-soft">Memuat data...</div>
            @else

                {{-- TAB STRIP --}}
                <div class="flex border-b border-hairline dark:border-gray-700 px-6">
                    <button type="button" wire:click="setActiveTab('obat')"
                        class="px-4 py-2 -mb-px text-sm font-medium border-b-2 transition
                            {{ $activeTab === 'obat'
                                ? 'text-blue-700 border-blue-600 dark:text-blue-300 dark:border-blue-400'
                                : 'text-muted border-transparent hover:text-body' }}">
                        Obat
                    </button>
                    <button type="button" wire:click="setActiveTab('kasir')"
                        class="px-4 py-2 -mb-px text-sm font-medium border-b-2 transition
                            {{ $activeTab === 'kasir'
                                ? 'text-violet-700 border-violet-600 dark:text-violet-300 dark:border-violet-400'
                                : 'text-muted border-transparent hover:text-body' }}">
                        Kasir
                    </button>
                </div>

                {{-- ═════════════════ TAB OBAT ═════════════════ --}}
                @if ($activeTab === 'obat')
                    <div class="px-6 py-4 max-h-[calc(100vh-380px)] overflow-y-auto space-y-4">

                        @if ($this->isObatLocked)
                            <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                @if ($status === 'L')
                                    Transaksi sudah diproses kasir — daftar obat terkunci.
                                @else
                                    Pasien sudah pulang — daftar obat terkunci.
                                @endif
                            </div>
                        @endif

                        {{-- Kiri: form entri · Kanan: daftar obat --}}
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-5 items-start">
                            {{-- FORM INPUT --}}
                            <div class="lg:col-span-2 p-4 border border-hairline rounded-2xl dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40">
                                @if ($this->isObatLocked)
                                    <p class="text-sm italic text-muted-soft dark:text-gray-600">Form input dinonaktifkan.</p>
                                @elseif (empty($formEntryObat['productId']))
                                    <div x-ref="lovObatRi">
                                        <livewire:lov.product.lov-product target="ri-resep-obat" label="Cari Obat"
                                            placeholder="Ketik nama/kode obat..."
                                            wire:key="ri-resep-lov-obat-{{ $slsNo }}-{{ $renderVersions['ri-resep-modal-administrasi'] ?? 0 }}" />
                                    </div>
                                @else
                                    {{-- Identitas obat terpilih (read-only, hasil pilih LOV) --}}
                                    <div class="flex flex-wrap items-center mb-3 gap-x-3 gap-y-1">
                                        <span
                                            class="px-2 py-0.5 text-xs font-mono rounded-md border border-hairline dark:border-gray-700 bg-canvas dark:bg-gray-800 text-muted dark:text-gray-300">{{ $formEntryObat['productId'] }}</span>
                                        <span
                                            class="text-lg font-bold text-ink dark:text-gray-100">{{ $formEntryObat['productName'] }}</span>
                                    </div>

                                    {{-- Semua field entri dalam satu baris (wrap otomatis di layar sempit) --}}
                                    <div class="flex flex-wrap items-end gap-2">
                                        <div class="w-20">
                                            <x-input-label value="Qty" class="mb-1" />
                                            <x-text-input wire:model="formEntryObat.qty" class="w-full text-sm text-right"
                                                x-ref="inputQtyRi"
                                                x-on:keyup.enter="$nextTick(() => $refs.inputHargaRi?.focus())" />
                                            <x-input-error :messages="$errors->get('formEntryObat.qty')" class="mt-1" />
                                        </div>
                                        <div class="w-28">
                                            <x-input-label value="Harga" class="mb-1" />
                                            <x-text-input-number wire:model="formEntryObat.price" class="text-sm"
                                                x-ref="inputHargaRi"
                                                x-on:keydown.enter.prevent="$el.blur(); $nextTick(() => $refs.inputCarapakaiRi?.focus())" />
                                            <x-input-error :messages="$errors->get('formEntryObat.price')" class="mt-1" />
                                        </div>
                                        <div class="w-16">
                                            <x-input-label value="x/Hari" class="mb-1" />
                                            <x-text-input wire:model="formEntryObat.carapakai"
                                                class="w-full text-sm text-center" x-ref="inputCarapakaiRi"
                                                x-on:keyup.enter="$nextTick(() => $refs.inputKapsulRi?.focus())" />
                                        </div>
                                        <div class="w-20">
                                            <x-input-label value="Per Minum" class="mb-1" />
                                            <x-text-input wire:model="formEntryObat.kapsul" class="w-full text-sm text-center"
                                                x-ref="inputKapsulRi"
                                                x-on:keyup.enter="$nextTick(() => $refs.inputTakarRi?.focus())" />
                                        </div>
                                        <div class="w-28">
                                            <x-input-label value="Takar" class="mb-1" />
                                            <x-select-input wire:model="formEntryObat.takar" x-ref="inputTakarRi"
                                                x-on:keyup.enter="$nextTick(() => $refs.inputKetRi?.focus())"
                                                class="block w-full text-sm border-gray-300 rounded-lg shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                <option>Tablet</option><option>Kapsul</option><option>Sirup</option>
                                                <option>Sachet</option><option>Tetes</option><option>Salep</option>
                                                <option>Injeksi</option><option>Unit</option><option>Lainnya</option>
                                            </x-select-input>
                                        </div>
                                        <div class="flex-1 min-w-[7rem]">
                                            <x-input-label value="Keterangan" class="mb-1" />
                                            <x-text-input wire:model="formEntryObat.ket" placeholder="Ket."
                                                class="w-full text-sm" x-ref="inputKetRi"
                                                x-on:keyup.enter="$nextTick(() => $refs.inputExpDateRi?.focus())" />
                                        </div>
                                        <div class="w-36">
                                            <x-input-label value="Exp. Date" class="mb-1" />
                                            <x-text-input wire:model="formEntryObat.expDate" placeholder="dd/mm/yyyy"
                                                inputmode="numeric" maxlength="10" class="w-full text-sm"
                                                x-ref="inputExpDateRi"
                                                x-on:keyup.enter="$nextTick(() => $refs.inputEtiketRi?.focus())" />
                                            <x-input-error :messages="$errors->get('formEntryObat.expDate')" class="mt-1" />
                                        </div>
                                        <div class="w-24">
                                            <x-input-label value="Etiket" class="mb-1" />
                                            <x-select-input wire:model="formEntryObat.etiketStatus" x-ref="inputEtiketRi"
                                                x-on:keydown.enter.prevent="$el.blur(); $wire.insertObat()"
                                                class="block w-full text-sm border-gray-300 rounded-lg shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                <option value="0">Belum</option>
                                                <option value="1">Sudah</option>
                                            </x-select-input>
                                        </div>
                                        <div class="flex items-center gap-2 pb-0.5 shrink-0">
                                            <x-icon-button color="gray" type="button" wire:click.prevent="resetFormEntry"
                                                title="Batal — kosongkan form entri">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </x-icon-button>
                                        </div>
                                    </div>

                                    {{-- Petunjuk cara simpan — tombol Tambah sudah ditiadakan --}}
                                    <p class="mt-3 text-xs text-muted dark:text-gray-400">
                                        Tekan <span class="px-1.5 py-0.5 font-semibold rounded border border-hairline bg-canvas text-body dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Enter</span>
                                        di kolom terakhir untuk menyimpan.
                                    </p>
                                @endif
                            </div>

                            {{-- TABEL OBAT --}}
                            <div class="lg:col-span-3 overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm text-left">
                                        <thead
                                            class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300 bg-surface-soft dark:bg-gray-800/50">
                                            <tr>
                                                <th class="px-3 py-3">Kode</th>
                                                <th class="px-3 py-3">Nama Obat</th>
                                                <th class="px-3 py-3 text-right w-20">Qty</th>
                                                <th class="px-3 py-3 text-right w-24">Harga</th>
                                                <th class="px-3 py-3 text-right w-28">Total</th>
                                                <th class="px-3 py-3">Signa</th>
                                                <th class="px-3 py-3 text-center w-24">Etiket</th>
                                                <th class="px-3 py-3 text-center w-28">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                                            @forelse ($items as $item)
                                                @php $isEditing = $editingDtl === $item['slsDtl']; @endphp

                                                @if ($isEditing)
                                                    {{-- BARIS EDIT — tata letaknya dibuat sama dengan form entri --}}
                                                    <tr wire:key="ri-resep-obat-{{ $item['slsDtl'] }}-edit"
                                                        class="bg-blue-50 dark:bg-blue-900/20 transition">
                                                        <td colspan="{{ $this->isObatLocked ? 7 : 8 }}" class="px-3 py-3">

                                                            {{-- Identitas obat — sejajar dengan form entri --}}
                                                            <div class="flex flex-wrap items-center mb-3 gap-x-3 gap-y-1">
                                                                <span
                                                                    class="px-2 py-0.5 text-xs font-mono rounded-md border border-hairline dark:border-gray-700 bg-canvas dark:bg-gray-800 text-muted dark:text-gray-300">{{ $item['productId'] }}</span>
                                                                <span
                                                                    class="text-lg font-bold text-ink dark:text-gray-100">{{ $item['productName'] }}</span>
                                                                <span class="ml-auto text-sm text-muted dark:text-gray-400">
                                                                    Total
                                                                    <span class="text-base font-bold text-ink dark:text-gray-100">Rp
                                                                        {{ number_format($item['price'] * (int) ($editRow['qty'] ?? 0)) }}</span>
                                                                </span>
                                                            </div>

                                                            {{-- Field — urutan & lebar mengikuti form entri --}}
                                                            <div class="flex flex-wrap items-end gap-2">
                                                                <div class="w-20">
                                                                    <x-input-label value="Qty" class="mb-1" />
                                                                    <x-text-input wire:model="editRow.qty"
                                                                        class="w-full text-sm text-right" x-ref="editQtyRi"
                                                                        x-init="$el.focus();
                                                                        $el.select()" />
                                                                </div>
                                                                <div class="w-28">
                                                                    <x-input-label value="Harga" class="mb-1" />
                                                                    <x-text-input value="{{ number_format($item['price']) }}"
                                                                        disabled class="w-full text-sm text-right" />
                                                                </div>
                                                                <div class="w-16">
                                                                    <x-input-label value="x/Hari" class="mb-1" />
                                                                    <x-text-input wire:model="editRow.carapakai"
                                                                        class="w-full text-sm text-center" placeholder="x" />
                                                                </div>
                                                                <div class="w-20">
                                                                    <x-input-label value="Per Minum" class="mb-1" />
                                                                    <x-text-input wire:model="editRow.kapsul"
                                                                        class="w-full text-sm text-center" placeholder="dd" />
                                                                </div>
                                                                <div class="w-28">
                                                                    <x-input-label value="Takar" class="mb-1" />
                                                                    <x-select-input wire:model="editRow.takar"
                                                                        class="block w-full text-sm border-gray-300 rounded-lg shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                                        <option>Tablet</option><option>Kapsul</option><option>Sirup</option>
                                                                        <option>Sachet</option><option>Tetes</option><option>Salep</option>
                                                                        <option>Injeksi</option><option>Unit</option><option>Lainnya</option>
                                                                    </x-select-input>
                                                                </div>
                                                                <div class="flex-1 min-w-[7rem]">
                                                                    <x-input-label value="Keterangan" class="mb-1" />
                                                                    <x-text-input wire:model="editRow.ket" placeholder="Ket."
                                                                        class="w-full text-sm" />
                                                                </div>
                                                                <div class="w-36">
                                                                    <x-input-label value="Exp. Date" class="mb-1" />
                                                                    <x-text-input wire:model="editRow.expDate"
                                                                        placeholder="dd/mm/yyyy" inputmode="numeric"
                                                                        maxlength="10" class="w-full text-sm" />
                                                                </div>
                                                                <div class="flex items-center gap-2 pb-0.5 shrink-0">
                                                                    <x-primary-button type="button" wire:click="saveEdit"
                                                                        wire:loading.attr="disabled" wire:target="saveEdit">
                                                                        <span wire:loading.remove
                                                                            wire:target="saveEdit">Simpan</span>
                                                                        <span wire:loading wire:target="saveEdit"><x-loading /></span>
                                                                    </x-primary-button>
                                                                    <x-icon-button color="gray" type="button"
                                                                        wire:click="cancelEdit" title="Batal — tutup baris edit">
                                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                                stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                        </svg>
                                                                    </x-icon-button>
                                                                </div>
                                                            </div>

                                                            @error('editRow.qty')
                                                                <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                                                            @enderror
                                                            @error('editRow.expDate')
                                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                                            @enderror
                                                        </td>
                                                    </tr>
                                                @else
                                                    <tr wire:key="ri-resep-obat-{{ $item['slsDtl'] ?? $loop->index }}"
                                                        class="hover:bg-surface-soft dark:hover:bg-gray-800/40 transition">
                                                        <td class="px-3 py-1.5 font-mono text-sm text-muted">
                                                            {{ $item['productId'] }}</td>
                                                        <td class="px-3 py-1.5 uppercase">{{ $item['productName'] }}</td>
                                                        <td class="px-3 py-1.5 font-mono text-right">{{ $item['qty'] }}</td>
                                                        <td class="px-3 py-1.5 font-mono text-sm text-right">
                                                            {{ number_format($item['price']) }}</td>
                                                        <td class="px-3 py-1.5 font-mono text-sm font-semibold text-right">
                                                            {{ number_format($item['total']) }}</td>

                                                        {{-- Signa = aturan pakai + takaran + ket, exp. date --}}
                                                        <td class="px-3 py-1.5">
                                                            <div class="flex flex-col leading-tight">
                                                                <span class="text-body dark:text-gray-300">
                                                                    S{{ $item['carapakai'] ?? '-' }}dd{{ $item['kapsul'] ?? '-' }}
                                                                    {{ $item['takar'] ?? '' }}
                                                                    @if (!empty($item['ket']) && $item['ket'] !== '-')
                                                                        <span class="text-muted dark:text-gray-400">&middot; {{ $item['ket'] }}</span>
                                                                    @endif
                                                                </span>
                                                                <span class="text-xs text-muted dark:text-gray-400">
                                                                    Exp. {{ $item['expDateDisplay'] ?? '-' }}
                                                                </span>
                                                            </div>
                                                        </td>

                                                        {{-- Etiket --}}
                                                        <td class="px-3 py-1.5 text-center whitespace-nowrap">
                                                            <x-icon-button color="blue" type="button"
                                                                wire:click="cetakEtiketItem({{ $item['slsDtl'] }})"
                                                                wire:loading.attr="disabled"
                                                                wire:target="cetakEtiketItem({{ $item['slsDtl'] }})"
                                                                title="Cetak etiket obat ini">
                                                                <span wire:loading.remove
                                                                    wire:target="cetakEtiketItem({{ $item['slsDtl'] }})">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                        viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            stroke-width="2"
                                                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                    </svg>
                                                                </span>
                                                                <span wire:loading
                                                                    wire:target="cetakEtiketItem({{ $item['slsDtl'] }})">
                                                                    <x-loading class="w-4 h-4" />
                                                                </span>
                                                            </x-icon-button>
                                                        </td>

                                                        {{-- Aksi --}}
                                                        <td class="px-3 py-1.5 whitespace-nowrap">
                                                            @if (!$this->isObatLocked)
                                                                <div class="flex items-center justify-center gap-1">
                                                                    <x-icon-button color="gray" type="button"
                                                                        wire:click="startEdit({{ $item['slsDtl'] }})"
                                                                        title="Edit baris obat ini">
                                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                                stroke-width="2"
                                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                        </svg>
                                                                    </x-icon-button>
                                                                    <x-outline-button type="button" wire:click.prevent="removeObat({{ $item['slsDtl'] }})"
                                                                        wire:confirm="Hapus obat ini?"
                                                                        wire:loading.attr="disabled" wire:target="removeObat({{ $item['slsDtl'] }})"
                                                                        class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                                        title="Hapus">
                                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                        </svg>
                                                                    </x-outline-button>
                                                                </div>
                                                            @else
                                                                <span class="text-sm text-muted-soft">—</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endif
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="px-3 py-8 text-center text-muted-soft">Belum ada obat.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        @if (count($items) > 0)
                                            <tfoot class="bg-surface-soft dark:bg-gray-800/50 border-t border-hairline dark:border-gray-700">
                                                <tr>
                                                    <td colspan="6" class="px-3 py-2 text-right text-xs font-semibold">Subtotal</td>
                                                    <td class="px-3 py-2 text-right font-mono font-bold">Rp {{ number_format($this->subtotal) }}</td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                @endif

                {{-- ═════════════════ TAB KASIR (samakan dengan kasir-rj) ═════════════════ --}}
                @if ($activeTab === 'kasir')
                    @php
                        $sudahBayar = $this->isKasirPosted ? (int) ($bayar ?? 0) : 0;
                        $totalAll = $this->totalAll;
                        $sisaTagihan = max(0, $totalAll - $sudahBayar);
                    @endphp

                    <div class="px-6 py-4 max-h-[calc(100vh-380px)] overflow-y-auto space-y-4"
                        wire:key="{{ $this->renderKey('ri-resep-modal-administrasi', [$slsNo ?? 'new']) }}-kasir-tab">

                        {{-- LOCKED BANNER --}}
                        @if ($this->isKasirPosted)
                            <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                Transaksi sudah diproses — data terkunci, tidak dapat diubah.
                            </div>
                        @endif

                        {{-- ══ PEMBAYARAN — ringkasan biaya & input pembayaran dalam satu kartu ══ --}}
                        {{-- Urutan kerja kasir: Jasa Karyawan → Bayar → Akun Kas → Post Transaksi --}}
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
                            x-on:focus-input-jasa-ri.window="fokusKe('inputJasaRi')"
                            x-on:focus-input-bayar-ri.window="fokusKe('inputBayarRi')"
                            x-on:focus-post-transaksi-ri.window="fokusKe('btnPostRi')">

                            {{-- ══ PANDUAN KASIR (collapsible, default tertutup) ══ --}}
                            @if (!$this->isKasirPosted && strtoupper($riStatus ?? '') !== 'P')
                                <div x-data="{ open: false }"
                                    class="mb-4 overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                                    <button type="button" @click="open = !open"
                                        class="flex items-center justify-between w-full px-3 py-2 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                                        <span class="flex items-center gap-2">
                                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Panduan Kasir RI
                                        </span>
                                        <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>

                                    <div x-show="open" x-collapse class="px-3 pb-3 text-xs text-blue-900 dark:text-blue-200">
                                        <ul class="space-y-1 ml-4 list-disc">
                                            <li>Pilih Akun Kas, isi nominal bayar, lalu klik "Post Transaksi".</li>
                                            <li>Bayar penuh = LUNAS. Bayar sebagian = BON, sisanya masuk Bon Inap
                                                (ditagih saat pulang).</li>
                                        </ul>
                                    </div>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-5 items-start">

                                {{-- KIRI: rincian biaya, dibaca atas ke bawah --}}
                                <div class="lg:col-span-2">
                                    <dl class="divide-y divide-hairline dark:divide-gray-700">

                                        {{-- Subtotal Obat --}}
                                        <div class="flex items-center justify-between gap-4 py-2.5">
                                            <dt class="text-base text-muted dark:text-gray-400">Subtotal Biaya</dt>
                                            <dd class="text-2xl font-bold text-ink dark:text-gray-100">Rp
                                                {{ number_format($this->subtotal) }}</dd>
                                        </div>

                                        {{-- Jasa Karyawan (bisa diubah) --}}
                                        <div class="flex items-center justify-between gap-4 py-2.5">
                                            <dt class="text-base font-semibold text-amber-700 dark:text-amber-400">
                                                Jasa Karyawan @if ($this->canEditJasa)
                                                    <span class="text-xs font-normal opacity-70">(dapat diubah)</span>
                                                @endif
                                            </dt>
                                            <dd class="w-48 text-right">
                                                @if ($this->canEditJasa)
                                                    <x-text-input-number wire:model="jasaKaryawan" placeholder="0"
                                                        x-ref="inputJasaRi" class="text-2xl font-bold"
                                                        x-on:keydown.enter.prevent="$el.blur(); $dispatch('focus-input-bayar-ri')" />
                                                @else
                                                    <span class="text-2xl font-bold text-amber-700 dark:text-amber-300">Rp
                                                        {{ number_format($jasaKaryawan) }}</span>
                                                @endif
                                            </dd>
                                        </div>

                                        {{-- Total Tagihan --}}
                                        <div class="flex items-center justify-between gap-4 py-2.5">
                                            <dt class="text-base font-bold text-blue-700 dark:text-blue-300">Total Tagihan
                                            </dt>
                                            <dd class="text-2xl font-bold text-blue-700 dark:text-blue-300">Rp
                                                {{ number_format($totalAll) }}</dd>
                                        </div>

                                        {{-- Dibayar — hanya bila sudah ada pembayaran --}}
                                        @if ($sudahBayar > 0)
                                            <div class="flex items-center justify-between gap-4 py-2.5">
                                                <dt class="text-base text-muted dark:text-gray-400">Dibayar</dt>
                                                <dd class="text-2xl font-bold text-ink dark:text-gray-100">Rp
                                                    {{ number_format($sudahBayar) }}</dd>
                                            </div>
                                        @endif

                                        {{-- Sisa Tagihan / Bon Inap --}}
                                        <div class="flex items-center justify-between gap-4 py-2.5">
                                            <dt
                                                class="text-base font-bold {{ $sisaTagihan > 0 ? 'text-error dark:text-rose-400' : 'text-success dark:text-success' }}">
                                                Sisa Tagihan
                                            </dt>
                                            <dd
                                                class="text-2xl font-bold {{ $sisaTagihan > 0 ? 'text-error dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                                                Rp {{ number_format($sisaTagihan) }}
                                            </dd>
                                        </div>

                                        {{-- Bayar — nominal yang diserahkan sekarang --}}
                                        @if (!$this->isKasirPosted && strtoupper($riStatus ?? '') !== 'P')
                                            <div class="flex items-center justify-between gap-4 py-2.5">
                                                <dt class="text-base font-bold text-body dark:text-gray-200">
                                                    Bayar <span class="text-xs font-normal opacity-70">(Rp)</span>
                                                </dt>
                                                <dd class="w-48">
                                                    <x-text-input-number wire:model="bayar" placeholder="0"
                                                        :error="$errors->has('bayar')" x-ref="inputBayarRi"
                                                        class="text-2xl font-bold"
                                                        x-on:keydown.enter.prevent="$el.blur(); $dispatch('ri-resep-focus-lov-kas-administrasi')" />
                                                </dd>
                                            </div>

                                            {{-- Hasil dari nominal yang diketik: kurang (masuk Bon Inap) / pas / kembalian --}}
                                            @php
                                                $bayarKini = (int) ($bayar ?? 0);
                                                $selisih = $bayarKini - $sisaTagihan;
                                            @endphp
                                            @if ($bayarKini > 0)
                                                <div class="flex items-center justify-between gap-4 py-2.5">
                                                    @if ($selisih < 0)
                                                        <dt class="text-base font-bold text-error dark:text-rose-400">Kurang
                                                            Bayar</dt>
                                                        <dd class="text-2xl font-bold text-error dark:text-rose-300">Rp
                                                            {{ number_format(abs($selisih)) }}</dd>
                                                    @elseif ($selisih === 0)
                                                        <dt class="text-base font-bold text-success dark:text-success">Pas
                                                            — Lunas</dt>
                                                        <dd class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                                                            Rp 0</dd>
                                                    @else
                                                        <dt class="text-base font-bold text-success dark:text-success">
                                                            Kembalian</dt>
                                                        <dd class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                                                            Rp {{ number_format($kembalian) }}</dd>
                                                    @endif
                                                </div>
                                            @endif
                                        @endif

                                    </dl>

                                    @error('bayar')
                                        <x-input-error :messages="$message" class="mt-1" />
                                    @enderror
                                </div>

                                {{-- KANAN: input pembayaran / status transaksi --}}
                                <div class="lg:col-span-3 lg:pl-6 lg:border-l border-hairline dark:border-gray-700">
                            @if ($this->isKasirPosted)
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm italic text-muted-soft dark:text-gray-600">Form input dinonaktifkan.</p>
                                        @hasanyrole('Apoteker|Admin|Tu')
                                            @if (strtoupper($riStatus ?? '') !== 'P')
                                                <x-confirm-button variant="danger" :action="'batalTransaksi()'"
                                                    title="Batal Transaksi"
                                                    message="Yakin ingin membatalkan transaksi ini? Bon Inap (jika ada) akan dihapus."
                                                    confirmText="Ya, batalkan" cancelText="Batal">
                                                    Batal Transaksi
                                                </x-confirm-button>
                                            @endif
                                        @endhasanyrole
                                    </div>

                                    @if ($sisaTagihan > 0)
                                        <div class="flex items-start gap-2 px-3 py-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
                                            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <div>
                                                <p class="font-semibold">Status: Bon (sisa masuk Bon Inap)</p>
                                                <p class="mt-0.5">Sisa Rp {{ number_format($sisaTagihan) }} ditagih saat pasien pulang.</p>
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex items-start gap-2 px-3 py-2 text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg dark:bg-emerald-900/20 dark:border-emerald-700 dark:text-emerald-300">
                                            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <div>
                                                <p class="font-semibold">Status: Lunas</p>
                                                <p class="mt-0.5">Pembayaran sudah selesai. Klik "Batal Transaksi" jika perlu membatalkan.</p>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="text-xs text-muted dark:text-gray-400 space-y-0.5">
                                        <div>Cara Bayar: <span class="font-mono">{{ $accName ?? $accId ?? '-' }}</span></div>
                                    </div>
                                </div>
                            @else
                                @if (strtoupper($riStatus ?? '') === 'P')
                                    <div class="px-3 py-2 mb-3 text-xs text-error bg-rose-50 border border-rose-200 rounded-lg dark:bg-rose-900/20 dark:border-rose-700 dark:text-rose-300">
                                        Pasien sudah pulang. Transaksi tidak dapat diproses.
                                    </div>
                                @endif

                                <div class="space-y-3"
                                    x-on:ri-resep-focus-lov-kas-administrasi.window="$nextTick(() => {
                                        const fokus = () => {
                                            const el = $el.querySelector('input:not([disabled])') || $el.querySelector('button');
                                            if (!el || el === document.activeElement) return;
                                            if (document.activeElement?.matches('input, select, textarea')) return;
                                            el.focus();
                                        };
                                        fokus();
                                        setTimeout(fokus, 150);
                                    })">
                                    <div>
                                        <livewire:lov.kas.lov-kas
                                            target="ri-resep-kas-administrasi"
                                            tipe="ri"
                                            label="Akun Kas"
                                            :initialAccId="$accId"
                                            wire:key="ri-resep-lov-kas-administrasi-{{ $slsNo }}-{{ $renderVersions['ri-resep-modal-administrasi'] ?? 0 }}" />
                                        <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                                    </div>

                                    <div class="flex gap-2">
                                        @hasanyrole('Apoteker|Admin|Tu')
                                            @if (strtoupper($riStatus ?? '') !== 'P')
                                                <x-primary-button wire:click="postTransaksi" wire:loading.attr="disabled" wire:target="postTransaksi" x-ref="btnPostRi">
                                                    <span wire:loading.remove wire:target="postTransaksi">Post Transaksi</span>
                                                    <span wire:loading wire:target="postTransaksi"><x-loading /></span>
                                                </x-primary-button>
                                            @endif
                                        @endhasanyrole
                                    </div>
                                </div>

                                @if ((int) ($bayar ?? 0) >= $sisaTagihan && $sisaTagihan > 0)
                                    <div class="flex items-center gap-1.5 mt-3">
                                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="text-xs font-semibold text-success dark:text-success">
                                            Pembayaran akan diproses sebagai LUNAS
                                        </span>
                                    </div>
                                @elseif ((int) ($bayar ?? 0) > 0 && (int) ($bayar ?? 0) < $sisaTagihan)
                                    <div class="flex items-center gap-1.5 mt-3">
                                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="text-xs font-semibold text-amber-700 dark:text-amber-400">
                                            Pembayaran akan diproses sebagai BON
                                        </span>
                                    </div>
                                @endif
                            @endif

                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- FOOTER --}}
                <div class="flex items-center justify-between gap-2 px-6 py-4 border-t border-hairline bg-surface-soft dark:border-gray-700 dark:bg-gray-900">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>

                    <div class="flex gap-2">
                        @if ($activeTab === 'kasir' && $this->isKasirPosted)
                            <x-info-button wire:click="cetakKwitansi" wire:loading.attr="disabled" wire:target="cetakKwitansi">
                                <span wire:loading.remove wire:target="cetakKwitansi" class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    Cetak Kwitansi
                                </span>
                                <span wire:loading wire:target="cetakKwitansi" class="flex items-center gap-1">
                                    <x-loading /> Menyiapkan...
                                </span>
                            </x-info-button>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    </x-modal>
</div>
