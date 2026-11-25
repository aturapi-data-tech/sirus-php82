<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Authorize;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Stock\StockBalanceTrait;

new class extends Component {
    use WithRenderVersioningTrait, StockBalanceTrait;

    /** Kategori ledger stok untuk cek batal posting (StockBalanceTrait). */
    private const KATEGORI_STOK = 'medis';

    public array $renderVersions = [];
    #[Locked]
    public string $formMode = 'create';

    // ── Header ──
    #[Locked] // kunci record — tak boleh diubah dari klien (celah ID)
    public ?int $rcvNo = null;
    public ?string $rcvDate = null;
    public ?string $suppId = null;
    public ?string $suppName = null;
    public ?string $rcvDesc = null;
    public ?int $spNo = null;
    public ?string $rcvStatus = null;

    // ── Detail entry (input barang) ──
    public ?string $entryProductId = null;
    public ?string $entryProductName = null;
    public ?int $entryQty = 1;
    public ?int $entryCostPrice = 0;
    public ?string $entryDiscount1 = null; // "10%" atau "50000"
    public ?string $entryDiscount2 = null;
    public ?string $entryRcvBath = null;
    public ?string $entryRcvEd = null;

    // ── Keranjang (in-memory array) ──
    public array $details = [];
    private int $detailCounter = 0;
    #[Locked] // diisi server (cekHargaBeli); kalau tak dikunci, klien bisa ubah harga pokok produk lain
    public array $pendingPriceUpdate = [];

    // ── Summary ──
    public int $totalBarang = 0;
    public int $totalQty = 0;
    public ?int $rcvDiskon = 0;
    public int $totalSetelahDiskon = 0;
    public ?float $rcvPpn = 0;
    public string $rcvPpnStatus = '1';
    public int $ppnNominal = 0;
    public ?int $rcvMaterai = 0;
    public int $grandTotal = 0;
    public ?int $bayar = 0;
    public int $sisa = 0;

    // ── Cara Bayar (akun kas keluar) ──
    public ?string $accId = null;
    public ?string $accName = null;
    /** Shift kas keluar angsuran awal — otomatis dari jam Tanggal Penerimaan, tetap bisa dipilih manual. */
    public ?string $shift = null;
    /** Ringkasan posting untuk dialog konfirmasi — kosong = dialog tertutup. Hanya tampilan. */
    public array $pratinjau = [];

    public function mount(): void
    {
        $this->registerAreas(['modal', 'entry', 'bayar']);
    }

    /* ══════════════════════════════
     | OPEN CREATE
     ══════════════════════════════ */
    #[On('penerimaan-medis.openCreate')]
    #[Authorize('gudang.penerimaan-medis.tambah')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->rcvDate = Carbon::now()->format('d/m/Y H:i:s');
        $this->details = [];
        $this->detailCounter = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'penerimaan-medis-actions');
        $this->dispatch('focus-rcv-date');
    }

    /* ══════════════════════════════
     | OPEN EDIT
     ══════════════════════════════ */
    #[On('penerimaan-medis.openEdit')]
    public function openEdit(string $rcvNo): void
    {
        $this->resetFormFields();
        $this->formMode = 'edit';

        $hdr = DB::table('imtxn_receivehdrs')->where('rcv_no', $rcvNo)->first();
        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }

        $this->rcvNo = (int) $hdr->rcv_no;
        $this->rcvDate = $hdr->rcv_date ? Carbon::parse($hdr->rcv_date)->format('d/m/Y H:i:s') : null;
        $this->suppId = $hdr->supp_id;
        $this->rcvDesc = $hdr->rcv_desc;
        $this->spNo = $hdr->sp_no ? (int) $hdr->sp_no : null;
        $this->rcvDiskon = (int) ($hdr->rcv_diskon ?? 0);
        $this->rcvPpn = (float) ($hdr->rcv_ppn ?? 0);
        $this->rcvPpnStatus = (string) ($hdr->rcv_ppn_status ?? '1');
        $this->rcvMaterai = (int) ($hdr->rcv_materai ?? 0);
        $this->bayar = (int) ($hdr->rcv_bayar ?? 0);
        $this->accId = $hdr->acc_id ?? null;
        $this->rcvStatus = (string) ($hdr->rcv_status ?? '');

        $this->loadDetailsFromDb();
        $this->hitungSemua();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'penerimaan-medis-actions');
        $this->dispatch('focus-rcv-desc');
    }

    /* ══════════════════════════════
     | LOV LISTENERS
     ══════════════════════════════ */
    #[On('lov.selected.supplier-rcv')]
    public function onSupplierSelected(string $target, ?array $payload): void
    {
        $this->suppId = $payload['supp_id'] ?? null;
        $this->suppName = $payload['supp_name'] ?? null;
        $this->dispatch('focus-rcv-desc');
    }

    #[On('lov.selected.product-rcv')]
    public function onProductSelected(string $target, ?array $payload): void
    {
        $this->entryProductId = $payload['product_id'] ?? null;
        $this->entryProductName = $payload['product_name'] ?? null;
        $this->entryCostPrice = (int) ($payload['cost_price'] ?? 0);
        $this->entryQty = 1;
        $this->dispatch('focus-entry-qty');
    }

    #[On('lov.selected.kas-rcv')]
    public function onKasSelected(string $target, ?array $payload): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->accName = $payload['acc_name'] ?? null;
        $this->resetErrorBag('accId');
        // Akun Kas = langkah terakhir (Diskon → PPN → Materai → Bayar → Akun Kas → Simpan).
        $this->dispatch('focus-btn-simpan-rcv');
    }

    /* ══════════════════════════════
     | BARANG KE KERANJANG (IN-MEMORY)
     ══════════════════════════════ */
    protected function entryRules(): array
    {
        return [
            'entryProductId' => 'required|string',
            'entryQty' => 'required|integer|min:1',
            'entryCostPrice' => 'required|integer|min:0',
            'entryDiscount1' => 'nullable|string',
            'entryDiscount2' => 'nullable|string',
            'entryRcvBath' => 'required|string|max:25',
            'entryRcvEd' => 'required|date_format:d/m/Y',
        ];
    }

    protected function entryMessages(): array
    {
        return [
            'entryProductId.required' => 'Barang wajib dipilih.',
            'entryQty.required' => 'Qty wajib diisi.',
            'entryQty.min' => 'Qty minimal 1.',
            'entryCostPrice.required' => 'Harga wajib diisi.',
            'entryCostPrice.min' => 'Harga tidak boleh negatif.',
            'entryRcvBath.required' => 'Batch wajib diisi.',
            'entryRcvBath.max' => 'Batch maksimal 25 karakter.',
            'entryRcvEd.required' => 'Exp. Date wajib diisi.',
            'entryRcvEd.date_format' => 'Format Exp. Date harus dd/mm/yyyy.',
        ];
    }

    public function tambahBarang(): void
    {
        $this->validate($this->entryRules(), $this->entryMessages());

        // Cek duplikat product_id di keranjang
        $existingIdx = null;
        foreach ($this->details as $idx => $dtl) {
            if ($dtl['product_id'] === $this->entryProductId) {
                $existingIdx = $idx;
                break;
            }
        }

        // Parse diskon: "10%" → persen, "50000" → rupiah
        $qty = $this->entryQty ?? 0;
        $costPrice = $this->entryCostPrice ?? 0;
        $total = $qty * $costPrice;

        [$dtlPersen, $dtlDiskon] = $this->parseDiscount($this->entryDiscount1, $total);
        $afterDisc1 = $total - ($total * $dtlPersen) / 100 - $dtlDiskon;
        [$dtlPersen1, $dtlDiskon1] = $this->parseDiscount($this->entryDiscount2, $afterDisc1);

        $item = [
            'product_id' => $this->entryProductId,
            'product_name' => $this->entryProductName,
            'qty' => $qty,
            'cost_price' => $costPrice,
            'dtl_persen' => $dtlPersen,
            'dtl_diskon' => $dtlDiskon,
            'dtl_persen1' => $dtlPersen1,
            'dtl_diskon1' => $dtlDiskon1,
            'dsp_discount' => $this->entryDiscount1,
            'dsp_discount1' => $this->entryDiscount2,
            'rcv_bath' => $this->entryRcvBath,
            'rcv_ed' => $this->entryRcvEd,
        ];

        // Hitung vtotal
        $item['vtotal'] = $this->hitungVtotal($item);

        if ($existingIdx !== null) {
            // Update existing
            $item['_key'] = $this->details[$existingIdx]['_key'];
            $item['rcv_dtl'] = $this->details[$existingIdx]['rcv_dtl'] ?? null;
            $this->details[$existingIdx] = $item;
        } else {
            // New item
            $this->detailCounter++;
            $item['_key'] = $this->detailCounter;
            $item['rcv_dtl'] = null; // belum ada di DB
            $this->details[] = $item;
        }

        $this->resetEntry();
        $this->incrementVersion('entry');
        $this->hitungSemua();
        $this->dispatch('toast', type: 'success', message: 'Barang ditambahkan ke keranjang.');
        $this->dispatch('focus-entry-product');
    }

    /* ══════════════════════════════
     | CEK HARGA BELI vs MASTER
     ══════════════════════════════ */
    public function cekHargaBeliEntry(): void
    {
        // Bisa menulis harga master (auto update) — ikut hak entri sesuai mode.
        Gate::authorize($this->formMode === 'create' ? 'gudang.penerimaan-medis.tambah' : 'gudang.penerimaan-medis.ubah');

        if (!$this->entryProductId || !$this->entryCostPrice) {
            return;
        }

        $productName = $this->entryProductName ?? $this->entryProductId;
        $this->cekHargaBeli($this->entryProductId, $productName, (int) $this->entryCostPrice);
    }

    private function cekHargaBeli(string $productId, string $productName, int $newPrice): void
    {
        $masterPrice = (int) (DB::table('immst_products')->where('product_id', $productId)->value('cost_price') ?? 0);

        if ($newPrice === $masterPrice) {
            return;
        }

        $fmtOld = number_format($masterPrice);
        $fmtNew = number_format($newPrice);

        if ($newPrice > $masterPrice) {
            $this->dispatch('toast', type: 'warning', message: "Harga {$productName} NAIK, dari Rp {$fmtOld} menjadi Rp {$fmtNew}");
        } else {
            $this->dispatch('toast', type: 'info', message: "Harga {$productName} TURUN, dari Rp {$fmtOld} menjadi Rp {$fmtNew}");
        }

        // Cek setting auto update
        $autoUpdate = DB::table('rsmst_identitases')->value('rcvupdate_cost_price') ?? '0';

        if ($autoUpdate === '1') {
            // Auto update harga di master
            DB::table('immst_products')
                ->where('product_id', $productId)
                ->update(['cost_price' => $newPrice]);
            $this->dispatch('toast', type: 'success', message: "Harga master {$productName} otomatis diupdate.");
        } else {
            // Simpan pending untuk konfirmasi manual
            $this->pendingPriceUpdate = [
                'product_id' => $productId,
                'product_name' => $productName,
                'old_price' => $masterPrice,
                'new_price' => $newPrice,
            ];
        }
    }

    public function confirmUpdateHarga(): void
    {
        Gate::authorize($this->formMode === 'create' ? 'gudang.penerimaan-medis.tambah' : 'gudang.penerimaan-medis.ubah');

        if (empty($this->pendingPriceUpdate)) {
            return;
        }

        DB::table('immst_products')
            ->where('product_id', $this->pendingPriceUpdate['product_id'])
            ->update(['cost_price' => $this->pendingPriceUpdate['new_price']]);

        $this->dispatch('toast', type: 'success', message: "Harga master {$this->pendingPriceUpdate['product_name']} berhasil diupdate.");
        $this->pendingPriceUpdate = [];
    }

    public function skipUpdateHarga(): void
    {
        $this->pendingPriceUpdate = [];
    }

    public function hapusBarang(int $key): void
    {
        $this->details = array_values(array_filter($this->details, fn($d) => $d['_key'] !== $key));
        $this->hitungSemua();
    }

    /* ══════════════════════════════
     | LOAD DETAILS FROM DB (EDIT)
     ══════════════════════════════ */
    private function loadDetailsFromDb(): void
    {
        $rows = DB::table('imtxn_receivedtls as a')
            ->leftJoin('immst_products as b', 'a.product_id', '=', 'b.product_id')
            ->where('a.rcv_no', $this->rcvNo)
            ->select(['a.rcv_dtl', 'a.product_id', 'b.product_name', 'a.qty', 'a.cost_price', 'a.dtl_persen', 'a.dtl_diskon', 'a.dtl_persen1', 'a.dtl_diskon1', 'a.rcv_bath', 'a.rcv_ed'])
            ->orderBy('a.rcv_dtl')
            ->get();

        $this->details = [];
        $this->detailCounter = 0;

        foreach ($rows as $row) {
            $this->detailCounter++;
            $sub = ($row->qty ?? 0) * ($row->cost_price ?? 0);
            $disc1 = ($sub * ($row->dtl_persen ?? 0)) / 100 + ($row->dtl_diskon ?? 0);
            $afterDisc1 = $sub - $disc1;
            $disc2 = ($afterDisc1 * ($row->dtl_persen1 ?? 0)) / 100 + ($row->dtl_diskon1 ?? 0);

            // Reconstruct display discount
            $dspDiscount = ($row->dtl_persen ?? 0) > 0 ? "{$row->dtl_persen}%" : (($row->dtl_diskon ?? 0) > 0 ? (string) (int) $row->dtl_diskon : null);
            $dspDiscount1 = ($row->dtl_persen1 ?? 0) > 0 ? "{$row->dtl_persen1}%" : (($row->dtl_diskon1 ?? 0) > 0 ? (string) (int) $row->dtl_diskon1 : null);

            $this->details[] = [
                '_key' => $this->detailCounter,
                'rcv_dtl' => $row->rcv_dtl,
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'qty' => $row->qty,
                'cost_price' => $row->cost_price,
                'dtl_persen' => $row->dtl_persen,
                'dtl_diskon' => $row->dtl_diskon,
                'dtl_persen1' => $row->dtl_persen1,
                'dtl_diskon1' => $row->dtl_diskon1,
                'dsp_discount' => $dspDiscount,
                'dsp_discount1' => $dspDiscount1,
                'rcv_bath' => $row->rcv_bath,
                'rcv_ed' => $row->rcv_ed,
                'vtotal' => $afterDisc1 - $disc2,
            ];
        }
    }

    /* ══════════════════════════════
     | PARSE DISCOUNT ("10%" → persen, "50000" → rupiah)
     ══════════════════════════════ */
    private function parseDiscount(?string $input, float $maxTotal): array
    {
        if (!$input || trim($input) === '') {
            return [0, 0]; // [persen, rupiah]
        }

        $clean = str_replace(',', '', trim($input));

        if (str_ends_with($clean, '%')) {
            $persen = min(100, (float) str_replace('%', '', $clean));
            return [$persen, 0];
        }

        $rupiah = (float) $clean;
        if ($rupiah > $maxTotal) {
            $this->dispatch('toast', type: 'error', message: 'Diskon melebihi total.');
            return [0, 0];
        }

        return [0, $rupiah];
    }

    private function hitungVtotal(array $item): float
    {
        $total = ($item['qty'] ?? 0) * ($item['cost_price'] ?? 0);
        $persen1 = ($total * ($item['dtl_persen'] ?? 0)) / 100;
        $afterDisc1 = $total - $persen1 - ($item['dtl_diskon'] ?? 0);
        $persen2 = ($afterDisc1 * ($item['dtl_persen1'] ?? 0)) / 100;
        return $afterDisc1 - $persen2 - ($item['dtl_diskon1'] ?? 0);
    }

    /* ══════════════════════════════
     | HITUNG SEMUA (sesuai Oracle hitung_semua)
     ══════════════════════════════ */
    public function hitungSemua(): void
    {
        // total = SUM vtotal dari detail
        $this->totalBarang = (int) array_sum(array_column($this->details, 'vtotal'));
        $this->totalQty = (int) array_sum(array_column($this->details, 'qty'));

        // diskon header
        $diskon = $this->rcvDiskon ?? 0;

        // total setelah diskon
        $this->totalSetelahDiskon = $this->totalBarang - $diskon;

        // ppn — di Oracle dihitung di kedua kondisi (status '1' dan '0')
        $this->ppnNominal = (int) round(($this->totalSetelahDiskon * ($this->rcvPpn ?? 0)) / 100);

        // materai
        $materai = $this->rcvMaterai ?? 0;

        // grand total = setelah diskon + ppn + materai
        $this->grandTotal = $this->totalSetelahDiskon + $this->ppnNominal + $materai;

        // sisa = bayar - grand total
        $this->sisa = ($this->bayar ?? 0) - $this->grandTotal;
    }

    /**
     * Toggle "Kena PPN" — status dan nilai persen harus SELALU sejalan.
     * Sebelumnya rcv_ppn_status hanya diisi diam-diam dari RSMST_IDENTITASES tanpa
     * kontrol di layar, sehingga bisa tersimpan status '0' padahal PPN diketik > 0
     * (faktur 10833 th. 2020). Modul lain menafsirkan kombinasi itu berbeda:
     * penerimaan menghitung PPN apa adanya, pembayaran hutang PBF menganggap bebas PPN.
     */
    public function updatedRcvPpnStatus(): void
    {
        if ($this->rcvPpnStatus === '0') {
            $this->rcvPpn = 0;
            $this->hitungSemua();
            return;
        }

        // Dinyalakan: pakai persen dari identitas RS bila field masih kosong/0.
        if (($this->rcvPpn ?? 0) <= 0) {
            $this->rcvPpn = (float) (DB::table('rsmst_identitases')->value('ppn_value') ?? 0);
        }

        $this->hitungSemua();
    }

    public function updatedRcvDiskon(): void
    {
        $this->hitungSemua();
        $this->cekBayarMelebihi();
    }
    public function updatedRcvPpn(): void
    {
        $this->hitungSemua();
        $this->cekBayarMelebihi();
    }
    public function updatedRcvMaterai(): void
    {
        $this->hitungSemua();
        $this->cekBayarMelebihi();
    }
    public function updatedBayar(): void
    {
        $this->hitungSemua();
        $this->cekBayarMelebihi();
    }

    /** Bayar ke supplier tidak mengenal kembalian: nominal > grand total ditandai merah. */
    protected function cekBayarMelebihi(): bool
    {
        $this->resetErrorBag('bayar');
        if ((int) ($this->bayar ?? 0) > (int) $this->grandTotal) {
            $this->addError('bayar', 'Melebihi grand total Rp ' . number_format($this->grandTotal, 0, ',', '.') . ' — bayar ke supplier tidak ada kembalian.');
            return true;
        }

        return false;
    }

    /* ══════════════════════════════
     | SIMPAN (INSERT HEADER + DETAILS)
     ══════════════════════════════ */
    /** Semua pemeriksaan sebelum posting. Mengembalikan emp_id, atau null bila ditolak. */
    protected function periksaPosting(): ?string
    {
        // validate() DULUAN, sebelum guard toast lain — kalau ditaruh setelahnya,
        // early-return bikin field bernilai salah tidak pernah ditandai merah.
        // Angka pembayaran header ikut menentukan grand total & hutang ke supplier,
        // jadi divalidasi sebelum menulis — bukan hanya di-cast float diam-diam.
        // PPN dibatasi 0-100: salah ketik (mis. 1100) langsung menggelembungkan tagihan.
        $this->validate(
            [
                'rcvPpn' => 'nullable|numeric|min:0|max:100',
                'rcvDiskon' => 'nullable|numeric|min:0',
                'rcvMaterai' => 'nullable|numeric|min:0',
                'bayar' => 'nullable|numeric|min:0',
                'shift' => 'required|in:1,2,3',
            ],
            [
                'rcvPpn.numeric' => 'PPN harus berupa angka.',
                'rcvPpn.min' => 'PPN tidak boleh negatif.',
                'rcvPpn.max' => 'PPN maksimal 100%.',
                'rcvDiskon.numeric' => 'Diskon faktur harus berupa angka.',
                'rcvDiskon.min' => 'Diskon faktur tidak boleh negatif.',
                'rcvMaterai.numeric' => 'Materai harus berupa angka.',
                'rcvMaterai.min' => 'Materai tidak boleh negatif.',
                'bayar.numeric' => 'Nominal bayar harus berupa angka.',
                'bayar.min' => 'Nominal bayar tidak boleh negatif.',
                'shift.required' => 'Shift belum dipilih.',
                'shift.in' => 'Shift tidak valid.',
            ],
        );

        // Guard edit mode: status selain 'A' (Daftar Tunggu) sudah final — tidak boleh disimpan ulang.
        if ($this->formMode === 'edit' && $this->rcvNo) {
            $currentStatus = (string) (DB::table('imtxn_receivehdrs')->where('rcv_no', $this->rcvNo)->value('rcv_status') ?? '');
            if ($currentStatus !== 'A') {
                $this->dispatch('toast', type: 'error', message: "Status '{$currentStatus}' — transaksi sudah final, tidak bisa diubah. Batalkan dulu untuk mengembalikan ke Daftar Tunggu.");
                return null;
            }
        }

        if (!$this->suppId) {
            $this->dispatch('toast', type: 'error', message: 'Supplier wajib dipilih.');
            return null;
        }

        if (count($this->details) === 0) {
            $this->dispatch('toast', type: 'error', message: 'Keranjang masih kosong, tambahkan barang.');
            return null;
        }

        if (!$this->accId) {
            $this->addError('accId', 'Cara bayar wajib dipilih.');
            $this->dispatch('toast', type: 'error', message: 'Cara bayar wajib dipilih.');
            return null;
        }

        $empId = auth()->user()->emp_id ?? null;
        if (!$empId) {
            $this->dispatch('toast', type: 'error', message: 'EMP ID belum diisi di profil user.');
            return null;
        }

        $this->hitungSemua();
        if ($this->cekBayarMelebihi()) {
            $this->dispatch('toast', type: 'error', message: 'Nominal bayar melebihi grand total.');
            return null;
        }

        return (string) $empId;
    }

    /** Langkah 1 posting: periksa, lalu tampilkan dialog konfirmasi — belum menulis apa pun. */
    public function siapkanPosting(): void
    {
        if ($this->periksaPosting() === null) {
            return;
        }

        $bayar = (int) ($this->bayar ?? 0);
        $grandTotal = (int) $this->grandTotal;
        $this->pratinjau = [
            'grandTotal' => $grandTotal,
            'bayar' => $bayar,
            'status' => $bayar >= $grandTotal && $grandTotal > 0 ? 'L' : 'H',
            'sisaHutang' => max(0, $grandTotal - $bayar),
            'jumlahItem' => count($this->details),
            'accName' => (string) (DB::table('acmst_accounts')->where('acc_id', $this->accId)->value('acc_name') ?? $this->accId),
        ];
    }

    public function batalKonfirmasiPosting(): void
    {
        $this->pratinjau = [];
    }

    /** Langkah 2 posting: tulis. Pemeriksaan diulang — dialog hanya tampilan. */
    public function simpan(): void
    {
        Gate::authorize($this->formMode === 'create' ? 'gudang.penerimaan-medis.tambah' : 'gudang.penerimaan-medis.ubah');

        $this->pratinjau = [];
        $empId = $this->periksaPosting();
        if ($empId === null) {
            return;
        }

        // Refresh summary supaya rcvStatus akurat
        $this->hitungSemua();
        $bayar = (int) ($this->bayar ?? 0);
        $grandTotal = (int) $this->grandTotal;
        $rcvStatus = $bayar >= $grandTotal && $grandTotal > 0 ? 'L' : 'H';
        $payDate = $rcvStatus === 'L' ? $this->rcvDate : null;
        $cashoutValue = min($bayar, $grandTotal);

        // Supplier name untuk keterangan cashout
        $suppName = DB::table('immst_suppliers')->where('supp_id', $this->suppId)->value('supp_name') ?? ($this->suppName ?? '');

        // Shift ikut Tanggal Penerimaan (= cashout_date), bukan jam tombol ditekan —
        // Saldo Kas per shift membaca kolom ini.
        $shift = (string) $this->shift;

        try {
            DB::transaction(function () use ($empId, $shift, $bayar, $grandTotal, $rcvStatus, $payDate, $cashoutValue, $suppName) {
                $payDateRaw = $payDate ? DB::raw("to_date('{$payDate}','dd/mm/yyyy hh24:mi:ss')") : null;

                if ($this->formMode === 'create') {
                    // Generate rcv_no
                    $rcvNo = (int) DB::selectOne('SELECT NVL(MAX(rcv_no),0)+1 AS val FROM imtxn_receivehdrs')->val;
                    $this->rcvNo = $rcvNo;

                    DB::table('imtxn_receivehdrs')->insert([
                        'rcv_no' => $rcvNo,
                        'rcv_date' => DB::raw("to_date('{$this->rcvDate}','dd/mm/yyyy hh24:mi:ss')"),
                        'supp_id' => $this->suppId,
                        'emp_id' => $empId,
                        'shift' => $shift,
                        'rcv_desc' => $this->rcvDesc,
                        'sp_no' => $this->spNo,
                        'rcv_diskon' => $this->rcvDiskon ?? 0,
                        'rcv_ppn' => $this->rcvPpn ?? 0,
                        'rcv_ppn_status' => $this->rcvPpnStatus,
                        'rcv_materai' => $this->rcvMaterai ?? 0,
                        'rcv_bayar' => $bayar,
                        'rcv_status' => $rcvStatus,
                        'pay_date' => $payDateRaw,
                        'acc_id' => $this->accId,
                    ]);

                    // Insert all details
                    foreach ($this->details as $dtl) {
                        DB::table('imtxn_receivedtls')->insert([
                            'rcv_no' => $rcvNo,
                            'rcv_dtl' => DB::raw('rcvdtl_seq.nextval'),
                            'product_id' => $dtl['product_id'],
                            'qty' => $dtl['qty'] ?? 0,
                            'cost_price' => $dtl['cost_price'] ?? 0,
                            'dtl_persen' => $dtl['dtl_persen'] ?? 0,
                            'dtl_diskon' => $dtl['dtl_diskon'] ?? 0,
                            'dtl_persen1' => $dtl['dtl_persen1'] ?? 0,
                            'dtl_diskon1' => $dtl['dtl_diskon1'] ?? 0,
                            'rcv_bath' => $dtl['rcv_bath'],
                            'rcv_ed' => $dtl['rcv_ed'],
                        ]);
                    }
                } else {
                    // Update header
                    DB::table('imtxn_receivehdrs')
                        ->where('rcv_no', $this->rcvNo)
                        ->update([
                            'rcv_date' => DB::raw("to_date('{$this->rcvDate}','dd/mm/yyyy hh24:mi:ss')"),
                            'supp_id' => $this->suppId,
                            'emp_id' => $empId,
                            'shift' => $shift,
                            'rcv_desc' => $this->rcvDesc,
                            'sp_no' => $this->spNo,
                            'rcv_diskon' => $this->rcvDiskon ?? 0,
                            'rcv_ppn' => $this->rcvPpn ?? 0,
                            'rcv_ppn_status' => $this->rcvPpnStatus,
                            'rcv_materai' => $this->rcvMaterai ?? 0,
                            'rcv_bayar' => $bayar,
                            'rcv_status' => $rcvStatus,
                            'pay_date' => $payDateRaw,
                            'acc_id' => $this->accId,
                        ]);

                    // Delete old details & re-insert
                    DB::table('imtxn_receivedtls')->where('rcv_no', $this->rcvNo)->delete();

                    foreach ($this->details as $dtl) {
                        DB::table('imtxn_receivedtls')->insert([
                            'rcv_no' => $this->rcvNo,
                            'rcv_dtl' => DB::raw('rcvdtl_seq.nextval'),
                            'product_id' => $dtl['product_id'],
                            'qty' => $dtl['qty'] ?? 0,
                            'cost_price' => $dtl['cost_price'] ?? 0,
                            'dtl_persen' => $dtl['dtl_persen'] ?? 0,
                            'dtl_diskon' => $dtl['dtl_diskon'] ?? 0,
                            'dtl_persen1' => $dtl['dtl_persen1'] ?? 0,
                            'dtl_diskon1' => $dtl['dtl_diskon1'] ?? 0,
                            'rcv_bath' => $dtl['rcv_bath'],
                            'rcv_ed' => $dtl['rcv_ed'],
                        ]);
                    }

                    // Hapus pembayaran lama (edit mode) supaya tidak double insert
                    DB::table('imtxn_receivepayments')->where('rcv_no', $this->rcvNo)->delete();
                    $oldCashoutNos = DB::table('imtxn_cashoutdtls')->where('rcv_no', $this->rcvNo)->pluck('cashout_no')->all();
                    DB::table('imtxn_cashoutdtls')->where('rcv_no', $this->rcvNo)->delete();
                    if (!empty($oldCashoutNos)) {
                        DB::table('imtxn_cashouthdrs')->whereIn('cashout_no', $oldCashoutNos)->delete();
                    }
                }

                // Insert pembayaran (cashout + receive payment) kalau bayar > 0
                if ($bayar > 0) {
                    $cashoutNo = (int) DB::selectOne('SELECT cashout_seq.nextval AS val FROM dual')->val;
                    $desc = 'Angsuran Awal, Atas Nama :"' . $suppName . '" Nota No "' . $this->rcvNo . '".';

                    DB::table('imtxn_cashouthdrs')->insert([
                        'shift' => $shift,
                        'acc_id' => $this->accId,
                        'cashout_no' => $cashoutNo,
                        'cashout_date' => DB::raw("to_date('{$this->rcvDate}','dd/mm/yyyy hh24:mi:ss')"),
                        'cashout_desc' => $desc,
                        'cashout_value' => $cashoutValue,
                        'emp_id' => $empId,
                        'supp_id' => $this->suppId,
                    ]);

                    DB::table('imtxn_cashoutdtls')->insert([
                        'cashout_no' => $cashoutNo,
                        'rcv_no' => $this->rcvNo,
                        'cashout_dtl' => DB::raw('codtl_seq.nextval'),
                    ]);

                    DB::table('imtxn_receivepayments')->insert([
                        'rcvp_no' => DB::raw('rcvp_seq.nextval'),
                        'rcv_no' => $this->rcvNo,
                        'rcvp_date' => DB::raw("to_date('{$this->rcvDate}','dd/mm/yyyy hh24:mi:ss')"),
                        'rcvp_value' => $cashoutValue,
                        'emp_id' => $empId,
                    ]);
                }
            });

            $msg = $rcvStatus === 'L'
                ? 'Obat dari PBF — lunas, disimpan & diposting.'
                : 'Obat dari PBF — status Hutang, disimpan & diposting.';
            $this->dispatch('toast', type: 'success', message: $msg);
            $this->closeBayar();
            $this->closeModal();
            $this->dispatch('penerimaan-medis.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ══════════════════════════════
     | DELETE
     ══════════════════════════════ */
    #[On('penerimaan-medis.requestDelete')]
    public function deleteFromGrid(string $rcvNo): void
    {
        if (!auth()->user()->can('gudang.penerimaan-medis.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses untuk menghapus.');
            return;
        }

        // Nota terposting (H/L) sudah masuk stok & kas — hapus fisik akan merusak keduanya.
        $statusHapus = (string) (DB::table('imtxn_receivehdrs')->where('rcv_no', $rcvNo)->value('rcv_status') ?? '');
        if (!in_array($statusHapus, ['A', 'F'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Hanya nota Daftar Tunggu / Batal yang boleh dihapus. Batalkan posting dulu.');
            return;
        }

        try {
            DB::transaction(function () use ($rcvNo) {
                DB::table('imtxn_receivedtls')->where('rcv_no', $rcvNo)->delete();
                DB::table('imtxn_receivehdrs')->where('rcv_no', $rcvNo)->delete();
            });

            $this->dispatch('toast', type: 'success', message: 'Data penerimaan berhasil dihapus.');
            $this->dispatch('penerimaan-medis.saved');
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ══════════════════════════════
     | BATAL TRANSAKSI
     | Port dari Oracle Forms:
     |   - status 'A'      → set 'F'
     |   - status 'H'/'L'  → rollback cashout+payment + set status kembali 'A',
     |                       kecuali jumlah cashoutdtls untuk rcv_no > 1 (terkunci nota lain).
     ══════════════════════════════ */
    public function batalCurrentRcv(): void
    {
        if (!$this->rcvNo) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi belum disimpan.');
            return;
        }
        $this->batalFromGrid((string) $this->rcvNo);
        $this->closeModal();
    }

    #[On('penerimaan-medis.requestBatal')]
    public function batalFromGrid(string $rcvNo): void
    {
        if (!auth()->user()->can('transaksi.batalPenerimaan')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses untuk membatalkan.');
            return;
        }

        try {
            $hdr = DB::table('imtxn_receivehdrs')->where('rcv_no', $rcvNo)->first();
            if (!$hdr) {
                $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
                return;
            }

            $status = (string) ($hdr->rcv_status ?? '');

            if ($status === 'F') {
                $this->dispatch('toast', type: 'warning', message: 'Transaksi sudah dibatalkan sebelumnya.');
                return;
            }

            if ($status === 'A') {
                DB::transaction(function () use ($rcvNo) {
                    DB::table('imtxn_receivehdrs')->where('rcv_no', $rcvNo)->update(['rcv_status' => 'F']);
                });
                $this->dispatch('toast', type: 'success', message: 'Transaksi berhasil dibatalkan.');
                $this->dispatch('penerimaan-medis.saved');
                return;
            }

            if (in_array($status, ['H', 'L'], true)) {
                $penghalang = $this->penghalangBatalPosting($rcvNo, $hdr);
                if ($penghalang !== null) {
                    $this->dispatch('toast', type: 'error', message: $penghalang);
                    return;
                }

                $cashoutNos = DB::transaction(function () use ($rcvNo) {
                    // Kunci & periksa ulang — bisa saja user lain membayar di antara cek dan tulis.
                    $kini = (string) (DB::table('imtxn_receivehdrs')->where('rcv_no', $rcvNo)->lockForUpdate()->value('rcv_status') ?? '');
                    if (!in_array($kini, ['H', 'L'], true)) {
                        throw new \RuntimeException('Status nota sudah berubah. Muat ulang daftar.');
                    }

                    $cashoutNos = DB::table('imtxn_cashoutdtls')->where('rcv_no', $rcvNo)->pluck('cashout_no')->unique()->values()->all();
                    // Penjaga terakhir: kas keluar yang juga menutup nota lain TIDAK boleh ikut terhapus.
                    if (!empty($cashoutNos) && DB::table('imtxn_cashoutdtls')->whereIn('cashout_no', $cashoutNos)->where('rcv_no', '<>', $rcvNo)->exists()) {
                        throw new \RuntimeException('Pembayaran nota ini satu kas keluar dengan nota lain — hapus dulu di menu Pembayaran Hutang PBF.');
                    }

                    DB::table('imtxn_receivepayments')->where('rcv_no', $rcvNo)->delete();
                    DB::table('imtxn_cashoutdtls')->where('rcv_no', $rcvNo)->delete();
                    if (!empty($cashoutNos)) {
                        DB::table('imtxn_cashouthdrs')->whereIn('cashout_no', $cashoutNos)->delete();
                    }

                    DB::table('imtxn_receivehdrs')->where('rcv_no', $rcvNo)->update([
                        'rcv_status' => 'A',
                        'rcv_bayar' => 0,
                        'pay_date' => null,
                    ]);

                    return $cashoutNos;
                });

                Log::info('Batal posting penerimaan medis', [
                    'rcv_no' => $rcvNo,
                    'status_lama' => $status,
                    'cashout_dihapus' => $cashoutNos,
                    'user' => auth()->user()?->myuser_code,
                ]);

                $this->dispatch('toast', type: 'success', message: 'Posting dibatalkan — stok ditarik dari gudang' . (empty($cashoutNos) ? '' : ', angsuran awal dihapus dari kas') . '. Status kembali ke Daftar Tunggu.');
                $this->dispatch('penerimaan-medis.saved');
                return;
            }

            $this->dispatch('toast', type: 'warning', message: "Status '{$status}' tidak dikenal, pembatalan dilewati.");
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membatalkan: ' . $e->getMessage());
        }
    }

    /**
     * Alasan batal posting DITOLAK, atau null bila boleh.
     *  1. Hanya nota tahun berjalan — saldo awal stok tahun ini sudah memuat nota tahun lalu.
     *  2. Pembayaran: yang boleh ikut terhapus hanya SATU kas keluar milik nota ini sendiri
     *     (angsuran awal). Pelunasan lain / kas keluar bersama nota lain → hapus dulu di menu
     *     pembayaran hutang (header kas keluar yang menutup nota lain tak boleh hilang).
     *  3. Stok gudang harus cukup untuk ditarik kembali: ditolak bila tanpa nota ini stok minus
     *     DAN ada barang keluar sejak tanggal nota (ledger yang sudah minus dari dulu tak menghalangi).
     */
    protected function penghalangBatalPosting(string $rcvNo, object $hdr): ?string
    {
        if ($hdr->rcv_date && Carbon::parse($hdr->rcv_date)->year !== (int) now()->year) {
            return 'Nota tahun lalu tidak bisa dibatalkan — saldo awal stok tahun ini sudah memuatnya.';
        }

        $cashoutNos = DB::table('imtxn_cashoutdtls')->where('rcv_no', $rcvNo)->pluck('cashout_no')->unique()->values();
        $jumlahBayar = (int) DB::table('imtxn_receivepayments')->where('rcv_no', $rcvNo)->count();
        if ($cashoutNos->count() > 1 || $jumlahBayar > 1) {
            return 'Nota ini sudah punya ' . max($cashoutNos->count(), $jumlahBayar) . ' pembayaran (angsuran awal + pelunasan). Hapus pelunasannya dulu di menu Pembayaran Hutang PBF.';
        }
        if ($cashoutNos->isNotEmpty()) {
            $notaLain = DB::table('imtxn_cashoutdtls')->whereIn('cashout_no', $cashoutNos->all())->where('rcv_no', '<>', $rcvNo)->pluck('rcv_no')->unique()->take(5)->implode(', ');
            if ($notaLain !== '') {
                return "Pembayaran nota ini satu kas keluar dengan nota lain ({$notaLain}). Hapus dulu pembayaran itu di menu Pembayaran Hutang PBF.";
            }
        }

        $qtyPerProduk = DB::table('imtxn_receivedtls')->where('rcv_no', $rcvNo)
            ->select('product_id', DB::raw('SUM(NVL(qty,0)) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // Ditolak hanya bila tanpa nota ini stok jadi minus DAN ada barang keluar sejak nota diposting.
        // Ledger yang memang sudah minus sebelum nota ini (data lama) tidak menghalangi — batal hanya
        // mengembalikan ke keadaan sebelum posting.
        $kurang = [];
        foreach ($qtyPerProduk as $productId => $qty) {
            $saldo = $this->saldoStok('04', (string) $productId, null, self::KATEGORI_STOK);
            if ($saldo - (float) $qty >= 0) {
                continue;
            }
            $keluarSejakPosting = (float) DB::table('tkview_iostockwhs')
                ->where('product_id', (string) $productId)
                ->where('txn_date', '>=', $hdr->rcv_date)
                ->sum(DB::raw('NVL(qty_k,0)'));
            if ($keluarSejakPosting > 0) {
                $kurang[] = (string) $productId;
            }
        }
        if (!empty($kurang)) {
            $nama = DB::table('immst_products')->whereIn('product_id', array_slice($kurang, 0, 5))->pluck('product_name')->implode(', ');
            return 'Stok gudang tidak cukup untuk ditarik — ' . count($kurang) . ' obat sudah terpakai/ditransfer (' . $nama . (count($kurang) > 5 ? ', …' : '') . '). Batal posting akan membuat stok minus.';
        }

        return null;
    }

    /** Shift dari jam tanggal (rstxn_shiftctls); null bila jam di luar master. */
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

    /* ══════════════════════════════
     | MODAL PEMBAYARAN
     ══════════════════════════════ */
    public function openBayar(): void
    {
        if (empty($this->details)) {
            $this->dispatch('toast', type: 'error', message: 'Keranjang masih kosong.');
            return;
        }
        // Reset input pembayaran — biar saat buka modal bayar selalu mulai dari 0
        // (mis. kasus edit transaksi yang punya rcv_bayar lama tidak auto-fill ke field).
        $this->reset(['rcvDiskon', 'rcvPpn', 'rcvMaterai', 'bayar', 'accId', 'accName']);
        $this->resetErrorBag('accId');

        // Auto-fill PPN dari RSMST_IDENTITASES (port Oracle Forms):
        //   AUTO_PPN_STATUS = '0' → PPN 0%, selain itu → ambil PPN_VALUE master.
        $idn = DB::table('rsmst_identitases')->select('auto_ppn_status', 'ppn_value')->first();
        if ($idn) {
            $this->rcvPpnStatus = (string) ($idn->auto_ppn_status ?? '1');
            $this->rcvPpn = $this->rcvPpnStatus === '0' ? 0 : (float) ($idn->ppn_value ?? 0);
        }

        $this->hitungSemua();
        $this->pratinjau = [];
        $this->resetErrorBag(['bayar', 'shift']);
        $this->shift = $this->shiftPadaJam((string) $this->rcvDate) ?? $this->shift;
        $this->incrementVersion('bayar');
        $this->dispatch('open-modal', name: 'penerimaan-medis-bayar');
        $this->dispatch('focus-input-diskon-rcv');
    }

    public function closeBayar(): void
    {
        // Reset semua input pembayaran ke default 0 / null — biar tampilan bersih saat reopen.
        $this->reset(['rcvDiskon', 'rcvPpn', 'rcvMaterai', 'bayar', 'accId', 'accName', 'shift', 'pratinjau']);
        $this->resetErrorBag(['accId', 'bayar', 'shift']);
        $this->hitungSemua();
        // Bump versi area 'bayar' supaya LOV kas remount (state bersih) tanpa
        // ganggu LOV lain di main modal.
        $this->incrementVersion('bayar');
        $this->dispatch('close-modal', name: 'penerimaan-medis-bayar');
    }

    /* ══════════════════════════════
     | CLOSE & RESET
     ══════════════════════════════ */
    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'penerimaan-medis-actions');
        // JANGAN resetVersion — open berikutnya tinggal incrementVersion dapat angka baru.
        // Kalau di-reset ke 0, open kedua balik ke v1 → wire:key LOV sama → state persist.
        $this->incrementVersion('modal');
        $this->incrementVersion('entry');
    }

    protected function resetFormFields(): void
    {
        $this->reset(['rcvNo', 'rcvDate', 'suppId', 'suppName', 'rcvDesc', 'spNo', 'rcvStatus', 'entryProductId', 'entryProductName', 'entryQty', 'entryCostPrice', 'entryDiscount1', 'entryDiscount2', 'entryRcvBath', 'entryRcvEd', 'details', 'totalBarang', 'totalQty', 'rcvDiskon', 'totalSetelahDiskon', 'rcvPpn', 'rcvPpnStatus', 'ppnNominal', 'rcvMaterai', 'grandTotal', 'bayar', 'sisa', 'pendingPriceUpdate', 'accId', 'accName', 'shift', 'pratinjau']);
        $this->detailCounter = 0;
        $this->resetValidation();
    }

    private function resetEntry(): void
    {
        $this->reset(['entryProductId', 'entryProductName', 'entryQty', 'entryCostPrice', 'entryDiscount1', 'entryDiscount2', 'entryRcvBath', 'entryRcvEd']);
    }
};
?>

<div>
    <x-modal name="penerimaan-medis-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $rcvNo]) }}">

            {{-- HEADER --}}
            @php
                [$modalTitle, $modalSubtitle, $modeBadgeLabel, $modeBadgeVariant] = match (true) {
                    $formMode === 'create' => ['Tambah Obat dari PBF', 'Catat penerimaan obat baru dari PBF / Supplier.', 'Tambah Baru', 'success'],
                    in_array($rcvStatus, ['H', 'L'], true) => ["Lihat Obat dari PBF #{$rcvNo}", 'Transaksi sudah diposting — data hanya bisa dilihat. Gunakan "Batalkan Transaksi" kalau perlu revisi.', 'Lihat (Final)', 'alternative'],
                    $rcvStatus === 'F' => ["Lihat Obat dari PBF #{$rcvNo}", 'Transaksi sudah dibatalkan — hanya untuk riwayat.', 'Batal', 'danger'],
                    default => ["Edit Obat dari PBF #{$rcvNo}", 'Ubah data penerimaan obat (Daftar Tunggu).', 'Edit', 'warning'],
                };
            @endphp
            <x-modul-dokumen.header :judul="$modalTitle" ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                {{ $modalSubtitle }}
                <x-slot:badge>
                    <x-badge :variant="$modeBadgeVariant">{{ $modeBadgeLabel }}</x-badge>
                </x-slot:badge>
            </x-modul-dokumen.header>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 space-y-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20" x-data
                x-on:focus-rcv-date.window="$nextTick(() => setTimeout(() => $refs.inputRcvDate?.focus(), 150))"
                x-on:focus-rcv-supplier.window="$nextTick(() => setTimeout(() => $refs.lovSupplierWrapper?.querySelector('input:not([disabled])')?.focus(), 150))"
                x-on:focus-rcv-desc.window="$nextTick(() => setTimeout(() => $refs.inputRcvDesc?.focus(), 150))"
                x-on:focus-entry-product.window="$nextTick(() => setTimeout(() => $refs.entryProductWrapper?.querySelector('input:not([disabled])')?.focus(), 150))"
                x-on:focus-entry-qty.window="$nextTick(() => setTimeout(() => $refs.inputEntryQty?.focus(), 150))"
                x-on:focus-entry-cost.window="$nextTick(() => setTimeout(() => $refs.inputEntryCost?.focus(), 150))"
                x-on:focus-btn-save-rcv.window="$nextTick(() => setTimeout(() => $refs.btnSaveRcv?.focus(), 150))">

                <div class="grid grid-cols-7 gap-4">
                    @php $isReadOnly = in_array($rcvStatus, ['H', 'L', 'F'], true); @endphp

                    {{-- ═══ SECTION 1: DATA PENERIMAAN ═══ --}}
                    <x-border-form title="Data Penerimaan" class="col-span-2">
                        <div class="grid grid-cols-1 gap-4 ">
                            <div>
                                <x-input-label value="Tanggal" :required="true" />
                                <x-text-input type="text" wire:model="rcvDate" placeholder="dd/mm/yyyy hh:mm:ss"
                                    class="w-full mt-1" x-ref="inputRcvDate" @disabled($isReadOnly)
                                    x-on:keydown.enter.prevent="$refs.lovSupplierWrapper?.querySelector('input:not([disabled])')?.focus()" />
                            </div>
                            <div x-ref="lovSupplierWrapper">
                                <livewire:lov.supplier.lov-supplier target="supplier-rcv" label="Supplier"
                                    jenisSupplier="medis" :initialSuppId="$suppId" :readonly="$isReadOnly"
                                    wire:key="lov-supp-{{ $rcvNo ?? 'new' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                            </div>
                            <div>
                                <x-input-label value="Keterangan" />
                                <x-text-input type="text" wire:model="rcvDesc" placeholder="Keterangan penerimaan"
                                    class="w-full mt-1" x-ref="inputRcvDesc" @disabled($isReadOnly)
                                    x-on:keydown.enter.prevent="$refs.entryProductWrapper?.querySelector('input:not([disabled])')?.focus()" />
                            </div>
                        </div>
                    </x-border-form>

                    {{-- ═══ SECTION 2: TAMBAH BARANG + KERANJANG — tampil kalau supplier sudah dipilih ═══ --}}
                    @if (empty($suppId))
                        <div class="flex flex-col items-center justify-center gap-2 py-12 text-center border border-dashed col-span-5 rounded-2xl border-gray-300 bg-surface-soft/60 dark:bg-gray-800/30 dark:border-gray-700">
                            <svg class="w-10 h-10 text-muted-soft" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <p class="text-sm text-muted dark:text-gray-400">
                                Lengkapi <span class="font-semibold text-brand dark:text-brand-lime">Data
                                    Penerimaan</span> (Supplier) di atas untuk mulai menambahkan barang.
                            </p>
                        </div>
                    @else
                    <x-border-form :title="($isReadOnly ? 'Keranjang (' : 'Tambah Barang / Keranjang (') . count($details) . ' item)'" padding="p-0"
                        class="overflow-hidden col-span-5"
                        wire:key="entry-section-{{ $renderVersions['entry'] ?? 0 }}">
                        {{-- Form entry (sembunyikan saat status non-editable) --}}
                        @if (!$isReadOnly)
                        <div class="p-4 ">
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-[2.5fr_0.7fr_repeat(5,1fr)] items-start">
                                {{-- Barang --}}
                                <div class="col-span-2 sm:col-span-1" x-ref="entryProductWrapper">
                                    <div>
                                        <livewire:lov.product.lov-product target="product-rcv" label="Barang"
                                            :initialProductId="$entryProductId"
                                            wire:key="lov-prod-{{ $rcvNo ?? 'new' }}-{{ $renderVersions['entry'] ?? 0 }}" />
                                    </div>
                                    <div class="mt-1 min-h-[1.25rem]">
                                        <x-input-error :messages="$errors->get('entryProductId')" />
                                    </div>
                                </div>
                                {{-- Qty --}}
                                <div>
                                    <div>
                                        <x-input-label value="Qty" />
                                        <x-text-input-number wire:model="entryQty" x-ref="inputEntryQty"
                                            :error="$errors->has('entryQty')"
                                            x-on:keydown.enter.prevent="$el.blur(); $nextTick(() => $refs.inputEntryCost?.focus())" />
                                    </div>
                                    <div class="mt-1 min-h-[1.25rem]">
                                        <x-input-error :messages="$errors->get('entryQty')" />
                                    </div>
                                </div>
                                {{-- Harga --}}
                                <div>
                                    <div>
                                        <x-input-label value="Harga" />
                                        <x-text-input-number wire:model="entryCostPrice" x-ref="inputEntryCost"
                                            :error="$errors->has('entryCostPrice')"
                                            x-on:keydown.enter.prevent="$el.blur(); $nextTick(() => { $wire.cekHargaBeliEntry(); $refs.inputEntryDisc1?.focus() })" />
                                    </div>
                                    <div class="mt-1 min-h-[1.25rem]">
                                        <x-input-error :messages="$errors->get('entryCostPrice')" />
                                    </div>
                                </div>
                                {{-- Disc 1 --}}
                                <div>
                                    <div>
                                        <x-input-label value="Disc 1" />
                                        <x-text-input type="text" wire:model="entryDiscount1" class="w-full mt-1"
                                            placeholder="10% / 5000" :error="$errors->has('entryDiscount1')" x-ref="inputEntryDisc1"
                                            x-on:keydown.enter.prevent="$refs.inputEntryDisc2?.focus()" />
                                    </div>
                                    <div class="mt-1 min-h-[1.25rem]">
                                        <x-input-error :messages="$errors->get('entryDiscount1')" />
                                    </div>
                                </div>
                                {{-- Disc 2 --}}
                                <div>
                                    <div>
                                        <x-input-label value="Disc 2" />
                                        <x-text-input type="text" wire:model="entryDiscount2" class="w-full mt-1"
                                            placeholder="5% / 1000" :error="$errors->has('entryDiscount2')" x-ref="inputEntryDisc2"
                                            x-on:keydown.enter.prevent="$refs.inputEntryBatch?.focus()" />
                                    </div>
                                    <div class="mt-1 min-h-[1.25rem]">
                                        <x-input-error :messages="$errors->get('entryDiscount2')" />
                                    </div>
                                </div>
                                {{-- Batch --}}
                                <div>
                                    <div>
                                        <x-input-label value="Batch" />
                                        <x-text-input type="text" wire:model="entryRcvBath" class="w-full mt-1"
                                            placeholder="Batch" :error="$errors->has('entryRcvBath')" x-ref="inputEntryBatch"
                                            x-on:keydown.enter.prevent="$refs.inputEntryEd?.focus()" />
                                    </div>
                                    <div class="mt-1 min-h-[1.25rem]">
                                        <x-input-error :messages="$errors->get('entryRcvBath')" />
                                    </div>
                                </div>
                                {{-- ED --}}
                                <div>
                                    <div>
                                        <x-input-label value="ED" />
                                        <x-text-input type="text" wire:model="entryRcvEd" class="w-full mt-1"
                                            placeholder="dd/mm/yyyy" :error="$errors->has('entryRcvEd')" x-ref="inputEntryEd"
                                            x-on:keydown.enter.prevent="$wire.tambahBarang()" />
                                    </div>
                                    <div class="mt-1 min-h-[1.25rem]">
                                        <x-input-error :messages="$errors->get('entryRcvEd')" />
                                    </div>
                                </div>
                            </div>

                            {{-- Petunjuk cara simpan — tombol Tambah ditiadakan --}}
                            <p class="mt-3 text-xs text-muted dark:text-gray-400">
                                Tekan <span class="px-1.5 py-0.5 font-semibold rounded border border-hairline bg-canvas text-body dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Enter</span>
                                di kolom <strong>ED</strong> untuk menambahkan ke keranjang.
                            </p>
                        </div>

                        {{-- ═══ KONFIRMASI UPDATE HARGA (amber = warning) ═══ --}}
                        @if (!empty($pendingPriceUpdate))
                            <div
                                class="mx-4 mb-4 flex flex-wrap items-center justify-between gap-3 px-4 py-3 border border-amber-300 rounded-xl bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700">
                                <div class="flex-1 min-w-0 text-sm text-amber-800 dark:text-amber-200">
                                    Harga <strong>{{ $pendingPriceUpdate['product_name'] }}</strong> berubah
                                    (Rp {{ number_format($pendingPriceUpdate['old_price']) }} &rarr; Rp
                                    {{ number_format($pendingPriceUpdate['new_price']) }}).
                                    Update harga di master barang?
                                </div>
                                <div class="flex gap-2 shrink-0">
                                    <x-primary-button type="button" wire:click="confirmUpdateHarga"
                                        class="!py-1 !px-3 text-sm">
                                        <x-ikon nama="simpan" />
                                        Ya, Update
                                    </x-primary-button>
                                    <x-secondary-button type="button" wire:click="skipUpdateHarga"
                                        class="!py-1 !px-3 text-sm">
                                        <x-ikon nama="batal" />
                                        Tidak
                                    </x-secondary-button>
                                </div>
                            </div>
                        @endif
                        @endif {{-- /!$isReadOnly: tutup form entry --}}

                        {{-- ═══ TABEL KERANJANG — lanjutan section yang sama ═══ --}}
                        <div class="overflow-x-auto border-t border-hairline dark:border-gray-700">
                            <table class="min-w-full text-sm">
                                <thead
                                    class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                    <tr class="text-left">
                                        <th class="px-3 py-2 font-semibold">#</th>
                                        <th class="px-3 py-2 font-semibold">Barang</th>
                                        <th class="px-3 py-2 font-semibold text-right">Qty</th>
                                        <th class="px-3 py-2 font-semibold text-right">Harga</th>
                                        <th class="px-3 py-2 font-semibold text-right">Diskon</th>
                                        <th class="px-3 py-2 font-semibold text-right">Total</th>
                                        <th class="px-3 py-2 font-semibold">Batch</th>
                                        <th class="px-3 py-2 font-semibold">ED</th>
                                        @if (!$isReadOnly)
                                            <th class="px-3 py-2 font-semibold"></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline dark:divide-gray-700">
                                    @forelse($details as $i => $dtl)
                                        <tr wire:key="dtl-{{ $dtl['_key'] }}"
                                            class="hover:bg-surface-soft dark:hover:bg-gray-800/50">
                                            <td class="px-3 py-1.5 text-muted-soft">{{ $i + 1 }}</td>
                                            <td class="px-3 py-1.5">
                                                <div class="font-medium text-ink dark:text-gray-100">
                                                    {{ $dtl['product_name'] ?? '-' }}</div>
                                                <div class="text-xs text-muted-soft">{{ $dtl['product_id'] }}</div>
                                            </td>
                                            <td class="px-3 py-1.5 font-mono text-right">
                                                {{ number_format($dtl['qty'] ?? 0) }}</td>
                                            <td class="px-3 py-1.5 font-mono text-right">
                                                {{ number_format($dtl['cost_price'] ?? 0) }}</td>
                                            <td class="px-3 py-1.5 text-right">
                                                <div>{{ $dtl['dsp_discount'] ?? '-' }}</div>
                                                @if (!empty($dtl['dsp_discount1']))
                                                    <div class="text-xs text-muted-soft">{{ $dtl['dsp_discount1'] }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td
                                                class="px-3 py-1.5 font-mono font-semibold text-right text-brand dark:text-brand-lime">
                                                Rp {{ number_format($dtl['vtotal'] ?? 0) }}</td>
                                            <td class="px-3 py-1.5">{{ $dtl['rcv_bath'] ?? '-' }}</td>
                                            <td class="px-3 py-1.5">{{ $dtl['rcv_ed'] ?? '-' }}</td>
                                            @if (!$isReadOnly)
                                                <td class="px-3 py-1.5">
                                                    <x-hapus-button wire:click.prevent="hapusBarang({{ $dtl['_key'] }})" confirm="Hapus {{ $dtl['product_name'] ?? 'barang ini' }} dari keranjang?" title="Hapus dari keranjang" />
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ $isReadOnly ? 8 : 9 }}"
                                                class="px-4 py-8 text-sm text-center text-muted-soft">
                                                Keranjang kosong{{ $isReadOnly ? '.' : '. Tambahkan barang di atas.' }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-border-form>
                    @endif
                </div>

                {{-- ═══ RINCIAN PEMBAYARAN — hanya tampil kalau transaksi sudah dibayar (H/L) ═══ --}}
                @if (in_array($rcvStatus, ['H', 'L'], true))
                    <x-border-form :title="'Rincian Pembayaran — ' . ($rcvStatus === 'L' ? 'Lunas' : 'Hutang')">

                        {{-- Status + cara bayar --}}
                        <div class="flex flex-wrap items-center gap-2 mb-3">
                            <x-badge :variant="$rcvStatus === 'L' ? 'success' : 'warning'">
                                {{ $rcvStatus === 'L' ? 'LUNAS' : 'HUTANG' }}
                            </x-badge>
                            @if ($accName || $accId)
                                <span class="text-sm text-muted dark:text-gray-400">
                                    via <strong class="text-body dark:text-gray-200">{{ $accName ?: $accId }}</strong>
                                </span>
                            @endif
                        </div>

                        {{-- Rincian — dibaca dari atas ke bawah, pola Kasir RJ --}}
                        <dl class="w-full max-w-md divide-y divide-hairline dark:divide-gray-700">

                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-base text-muted dark:text-gray-400">Total Barang</dt>
                                <dd class="text-2xl font-bold text-ink dark:text-gray-100">Rp
                                    {{ number_format($totalBarang) }}</dd>
                            </div>

                            <div class="flex items-center justify-between gap-4 py-2.5 px-3 -mx-3 rounded-lg bg-amber-50/70 dark:bg-amber-900/10">
                                <dt class="text-base font-semibold text-amber-700 dark:text-amber-400">Diskon</dt>
                                <dd class="text-2xl font-bold text-amber-700 dark:text-amber-300">&minus; Rp
                                    {{ number_format($rcvDiskon ?? 0) }}</dd>
                            </div>

                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-base text-muted dark:text-gray-400">Setelah Diskon</dt>
                                <dd class="text-2xl font-bold text-ink dark:text-gray-100">Rp
                                    {{ number_format($totalSetelahDiskon) }}</dd>
                            </div>

                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-base text-muted dark:text-gray-400">PPN ({{ $rcvPpn ?? 0 }}%)</dt>
                                <dd class="text-2xl font-bold text-ink dark:text-gray-100">Rp
                                    {{ number_format($ppnNominal) }}</dd>
                            </div>

                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-base text-muted dark:text-gray-400">Materai</dt>
                                <dd class="text-2xl font-bold text-ink dark:text-gray-100">Rp
                                    {{ number_format($rcvMaterai ?? 0) }}</dd>
                            </div>

                            <div class="flex items-center justify-between gap-4 py-2.5 px-3 -mx-3 rounded-lg bg-blue-50 dark:bg-blue-900/15">
                                <dt class="text-base font-bold text-blue-700 dark:text-blue-300">Grand Total</dt>
                                <dd class="text-2xl font-bold text-blue-700 dark:text-blue-300">Rp
                                    {{ number_format($grandTotal) }}</dd>
                            </div>

                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-base text-muted dark:text-gray-400">Dibayar</dt>
                                <dd class="text-2xl font-bold text-ink dark:text-gray-100">Rp
                                    {{ number_format($bayar ?? 0) }}</dd>
                            </div>

                            {{-- Baris hasil: kurang / pas / kembalian --}}
                            @php $selisih = (int) ($bayar ?? 0) - (int) $grandTotal; @endphp
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                @if ($selisih < 0)
                                    <dt class="text-base font-bold text-error dark:text-rose-400">Sisa / Kurang</dt>
                                    <dd class="text-2xl font-bold text-error dark:text-rose-300">Rp
                                        {{ number_format(abs($selisih)) }}</dd>
                                @elseif ($selisih === 0)
                                    <dt class="text-base font-bold text-success dark:text-success">Pas — Lunas</dt>
                                    <dd class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">Rp 0</dd>
                                @else
                                    <dt class="text-base font-bold text-amber-700 dark:text-amber-400">Lebih Bayar (kas keluar dibatasi grand total)</dt>
                                    <dd class="text-2xl font-bold text-amber-700 dark:text-amber-300">Rp
                                        {{ number_format($selisih) }}</dd>
                                @endif
                            </div>

                        </dl>
                    </x-border-form>
                @endif
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-3 mt-auto bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    {{-- KIRI: Batalkan Transaksi --}}
                    <div class="flex items-center gap-3">
                        @can('transaksi.batalPenerimaan')
                            @if ($formMode === 'edit' && $rcvNo && $rcvStatus !== 'F')
                                @php
                                    $batalMsg = $rcvStatus === 'A'
                                        ? 'Transaksi (Daftar Tunggu) akan di-set ke status Batal (F). Lanjut?'
                                        : 'Posting dibatalkan: stok nota ini ditarik dari gudang, angsuran awal (bila ada) dihapus dari kas, status kembali ke Daftar Tunggu. Ditolak bila stok sudah terpakai, nota tahun lalu, atau sudah ada pelunasan dari menu Pembayaran Hutang PBF. Lanjut?';
                                @endphp
                                <x-confirm-button variant="danger" action="batalCurrentRcv()"
                                    title="Batalkan Transaksi" :message="$batalMsg"
                                    confirmText="Ya, batalkan" cancelText="Tidak">
                                    <x-ikon nama="batalkan" />
                                    Batalkan Transaksi
                                </x-confirm-button>
                            @endif
                        @endcan
                        <div class="text-xs text-muted dark:text-gray-400">
                            <strong>{{ count($details) }}</strong> item &middot; Grand Total:
                            <strong class="font-mono text-brand dark:text-brand-lime">Rp
                                {{ number_format($grandTotal) }}</strong>
                        </div>
                    </div>

                    {{-- KANAN: Tutup + Bayar --}}
                    <div class="flex flex-wrap justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal"><x-ikon nama="batal" /> Tutup</x-secondary-button>
                        @can($formMode === 'create' ? 'gudang.penerimaan-medis.tambah' : 'gudang.penerimaan-medis.ubah')
                        <x-primary-button type="button" wire:click="openBayar"
                            :disabled="count($details) === 0 || ($formMode === 'edit' && $rcvStatus !== 'A')">
                            <x-ikon nama="proses" class="mr-1" />
                            Proses Pembayaran
                        </x-primary-button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </x-modal>

    {{-- ═══════════════════════════════════════════════════════════════
         MODAL PEMBAYARAN — input diskon/PPN/materai/bayar + summary
         ═══════════════════════════════════════════════════════════════ --}}
    <x-modal name="penerimaan-medis-bayar" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('bayar', [$rcvNo]) }}"
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
            x-on:focus-lov-kas-rcv.window="$nextTick(() => {
                const fokus = () => {
                    const el = $refs.lovKasRcv?.querySelector('input:not([disabled])') || $refs.lovKasRcv?.querySelector('button');
                    if (!el || el === document.activeElement) return;
                    if (document.activeElement?.matches('input, select, textarea')) return;
                    el.focus();
                };
                fokus();
                setTimeout(fokus, 150);
            })"
            x-on:focus-btn-simpan-rcv.window="fokusKe('btnSaveRcv')"
            x-on:focus-input-diskon-rcv.window="fokusKe('inputDiskonRcv')">
            {{-- Header --}}
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand/10 dark:bg-brand-lime/15">
                            <svg class="w-5 h-5 text-brand dark:text-brand-lime" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 10h18M5 10V6a2 2 0 012-2h10a2 2 0 012 2v4M5 10v10a2 2 0 002 2h10a2 2 0 002-2V10" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Pembayaran</h2>
                            <p class="text-xs text-muted dark:text-gray-400">
                                Isi diskon, PPN, materai, dan nilai bayar sebelum posting.
                            </p>
                        </div>
                    </div>
                    <x-secondary-button type="button" wire:click="closeBayar" class="!p-2">
                        <span class="sr-only">Close</span>
                        <x-ikon nama="batal" />
                    </x-secondary-button>
                </div>
            </div>

            {{-- Body — pola Kasir RJ: satu daftar vertikal, input menyatu di barisnya.
                 Urutan kerja: Diskon → PPN → Materai → Bayar → Akun Kas → Simpan. --}}
            <div class="flex-1 px-6 py-5 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                {{-- PANDUAN (biru-info, collapsible, default tertutup) --}}
                <div x-data="{ open: false }"
                    class="w-full mx-auto mb-4 max-w-6xl overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                    <button type="button" @click="open = !open"
                        class="flex items-center justify-between w-full px-3 py-2 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Cara Pakai — Posting &amp; Batal Penerimaan
                        </span>
                        <svg class="w-4 h-4 text-blue-600 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="open" x-collapse class="px-3 pb-3 text-xs text-blue-900 dark:text-blue-200">
                        <ul class="ml-4 space-y-1 list-disc">
                            <li>Urutan: Diskon → PPN → Materai → Bayar → Akun Kas → Shift → <strong>Simpan &amp; Posting</strong> → periksa dialog konfirmasi.</li>
                            <li>Bayar sekarang (angsuran awal) <strong>tidak boleh melebihi grand total</strong> — bayar ke supplier tidak ada kembalian. Bayar 0 / sebagian = HUTANG, dilunasi di menu Pembayaran Hutang PBF (bisa memakai Saldo DP).</li>
                            <li>Shift kas keluar mengikuti jam Tanggal Penerimaan, bukan jam tombol ditekan.</li>
                            <li>Setelah diposting, stok masuk gudang dan nota tidak bisa diubah. Untuk revisi: <strong>Batalkan Transaksi</strong> (Admin/TU) → nota kembali ke Daftar Tunggu.</li>
                            <li>Batal posting <strong>ditolak</strong> bila: nota tahun lalu; stok obat sudah terpakai/ditransfer; sudah ada pelunasan dari menu Pembayaran Hutang PBF; atau pembayarannya satu transaksi dengan nota lain. Hapus dulu pelunasan itu di menu Pembayaran Hutang PBF.</li>
                        </ul>
                    </div>
                </div>

                <div class="grid w-full grid-cols-1 gap-6 mx-auto max-w-6xl lg:grid-cols-2 items-start">

                    {{-- ══ KIRI: perhitungan tagihan ══ --}}
                    <div class="p-4 border border-hairline rounded-2xl dark:border-gray-700 bg-canvas dark:bg-gray-900">
                        <h3 class="flex items-center gap-2 mb-2 text-sm font-semibold tracking-wide uppercase text-blue-700 dark:text-blue-300">
                            <span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                            Perhitungan Tagihan
                        </h3>
                        <dl class="divide-y divide-hairline dark:divide-gray-700">

                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-base text-muted dark:text-gray-400">Total Barang</dt>
                                <dd class="text-2xl font-bold text-ink dark:text-gray-100">Rp
                                    {{ number_format($totalBarang) }}</dd>
                            </div>

                            <div class="flex items-center justify-between gap-4 py-2.5 px-3 -mx-3 rounded-lg bg-amber-50/70 dark:bg-amber-900/10">
                                <dt class="text-base font-semibold text-amber-700 dark:text-amber-400">
                                    Diskon <span class="text-xs font-normal opacity-70">(Rp)</span>
                                </dt>
                                <dd class="w-48">
                                    <x-text-input-number wire:model="rcvDiskon" placeholder="0" x-ref="inputDiskonRcv"
                                        class="text-2xl font-bold"
                                        x-on:keydown.enter.prevent="$el.blur(); fokusKe('inputPpnRcv')" />
                                </dd>
                            </div>

                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-base text-muted dark:text-gray-400">Setelah Diskon</dt>
                                <dd class="text-2xl font-bold text-ink dark:text-gray-100">Rp
                                    {{ number_format($totalSetelahDiskon) }}</dd>
                            </div>

                            {{-- PPN persen: input biasa, BUKAN x-text-input-number — komponen itu
                                 mengirim integer bersih, sedangkan $rcvPpn float (mis. 11,5%). --}}
                            {{-- Saklar status PPN (rcv_ppn_status). Dimatikan → persen dinolkan,
                                 dinyalakan → ambil PPN_VALUE dari identitas RS. --}}
                            <div class="flex items-center justify-between gap-4 py-2.5 px-3 -mx-3">
                                <dt class="text-base text-muted dark:text-gray-400">Faktur kena PPN</dt>
                                <dd>
                                    <x-toggle wire:model="rcvPpnStatus" trueValue="1" falseValue="0"
                                        :label="$rcvPpnStatus === '1' ? 'Ya' : 'Bebas PPN'" />
                                </dd>
                            </div>

                            <div class="flex items-center justify-between gap-4 py-2.5 px-3 -mx-3 rounded-lg bg-amber-50/70 dark:bg-amber-900/10">
                                <dt class="text-base font-semibold text-amber-700 dark:text-amber-400">
                                    PPN <span class="text-xs font-normal opacity-70">(%)</span>
                                </dt>
                                <dd class="w-48">
                                    {{-- $rcvPpn bertipe ?float: kalau huruf sempat terkirim, Livewire melempar
                                         TypeError (500) SEBELUM validasi server jalan. Karena itu karakter
                                         non-angka disaring di input, dan rentang 0-100 tetap dijaga di simpan(). --}}
                                    <x-text-input type="text" inputmode="decimal" wire:model.blur="rcvPpn"
                                        placeholder="0" x-ref="inputPpnRcv" class="w-full text-2xl font-bold text-right"
                                        :error="$errors->has('rcvPpn')" :disabled="$rcvPpnStatus === '0'"
                                        x-on:input="$el.value = $el.value.replace(/[^0-9.,]/g, '').replace(',', '.')"
                                        x-on:keydown.enter.prevent="$el.blur(); fokusKe('inputMateraiRcv')" />
                                    <x-input-error :messages="$errors->get('rcvPpn')" class="mt-1 text-right" />
                                </dd>
                            </div>

                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-base text-muted dark:text-gray-400">Nilai PPN</dt>
                                <dd class="text-2xl font-bold text-ink dark:text-gray-100">Rp
                                    {{ number_format($ppnNominal) }}</dd>
                            </div>

                            <div class="flex items-center justify-between gap-4 py-2.5 px-3 -mx-3 rounded-lg bg-amber-50/70 dark:bg-amber-900/10">
                                <dt class="text-base font-semibold text-amber-700 dark:text-amber-400">
                                    Materai <span class="text-xs font-normal opacity-70">(Rp)</span>
                                </dt>
                                <dd class="w-48">
                                    <x-text-input-number wire:model="rcvMaterai" placeholder="0"
                                        x-ref="inputMateraiRcv" class="text-2xl font-bold"
                                        x-on:keydown.enter.prevent="$el.blur(); fokusKe('inputBayarRcv')" />
                                </dd>
                            </div>

                            <div class="flex items-center justify-between gap-4 py-2.5 px-3 -mx-3 rounded-lg bg-blue-50 dark:bg-blue-900/15">
                                <dt class="text-base font-bold text-blue-700 dark:text-blue-300">Grand Total</dt>
                                <dd class="text-2xl font-bold text-blue-700 dark:text-blue-300">Rp
                                    {{ number_format($grandTotal) }}</dd>
                            </div>

                        </dl>
                    </div>

                    {{-- ══ KANAN: pembayaran ══ --}}
                    <div class="p-4 border border-hairline rounded-2xl dark:border-gray-700 bg-canvas dark:bg-gray-900">
                        <h3 class="flex items-center gap-2 mb-2 text-sm font-semibold tracking-wide uppercase text-emerald-700 dark:text-emerald-300">
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                            Pembayaran
                        </h3>
                        <dl class="divide-y divide-hairline dark:divide-gray-700">

                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-base font-bold text-blue-700 dark:text-blue-300">Grand Total</dt>
                                <dd class="text-2xl font-bold text-blue-700 dark:text-blue-300">Rp
                                    {{ number_format($grandTotal) }}</dd>
                            </div>

                            <div class="flex items-center justify-between gap-4 py-2.5 px-3 -mx-3 rounded-lg bg-amber-50/70 dark:bg-amber-900/10">
                                <dt class="text-base font-bold text-amber-700 dark:text-amber-400">
                                    Bayar <span class="text-xs font-normal opacity-70">(Rp)</span>
                                </dt>
                                <dd class="w-48">
                                    <x-text-input-number wire:model="bayar" placeholder="0" x-ref="inputBayarRcv"
                                        class="text-2xl font-bold" :error="$errors->has('bayar')"
                                        x-on:keydown.enter.prevent="$el.blur(); $dispatch('focus-lov-kas-rcv')" />
                                    <x-input-error :messages="$errors->get('bayar')" class="mt-1 text-right" />
                                </dd>
                            </div>

                            {{-- Hasil: kurang / pas / lebih --}}
                            @if ((int) ($bayar ?? 0) > 0)
                                <div
                                    class="flex items-center justify-between gap-4 py-2.5 px-3 -mx-3 rounded-lg {{ $sisa < 0 ? 'bg-rose-50 dark:bg-rose-900/15' : 'bg-emerald-50 dark:bg-emerald-900/15' }}">
                                    @if ($sisa < 0)
                                        <dt class="text-base font-bold text-error dark:text-rose-400">Sisa / Kurang</dt>
                                        <dd class="text-2xl font-bold text-error dark:text-rose-300">Rp
                                            {{ number_format(abs($sisa)) }}</dd>
                                    @elseif ($sisa === 0)
                                        <dt class="text-base font-bold text-success dark:text-success">Pas — Lunas</dt>
                                        <dd class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">Rp 0</dd>
                                    @else
                                        <dt class="text-base font-bold text-error dark:text-rose-400">Melebihi Grand Total</dt>
                                        <dd class="text-2xl font-bold text-error dark:text-rose-300">Rp
                                            {{ number_format($sisa) }}</dd>
                                    @endif
                                </div>
                            @endif
                        </dl>

                        {{-- Cara Bayar — langkah TERAKHIR sebelum posting --}}
                        <div class="pt-4 mt-4 border-t border-hairline dark:border-gray-700" x-ref="lovKasRcv">
                            <livewire:lov.kas.lov-kas target="kas-rcv" tipe="" label="Cara Bayar (Akun Kas)"
                                :initialAccId="$accId"
                                wire:key="lov-kas-rcv-{{ $rcvNo ?? 'new' }}-{{ $renderVersions['bayar'] ?? 0 }}" />
                            <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                        </div>

                        {{-- Shift kas keluar — otomatis dari jam Tanggal Penerimaan, tetap bisa dipilih manual --}}
                        <div class="mt-4">
                            <x-input-label value="Shift" :required="true" />
                            <x-select-input wire:model="shift" class="w-full mt-1" :error="$errors->has('shift')">
                                <option value="">-- Shift --</option>
                                <option value="1">Shift 1</option>
                                <option value="2">Shift 2</option>
                                <option value="3">Shift 3</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('shift')" class="mt-1" />
                            <p class="mt-1 text-xs text-muted dark:text-gray-400">Diisi otomatis dari jam Tanggal Penerimaan.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer modal bayar --}}
            <div class="sticky bottom-0 z-10 flex justify-end gap-2 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-800/50 dark:border-gray-700">
                <x-secondary-button type="button" wire:click="closeBayar">
                    <x-ikon nama="batal" />
                    Batal
                </x-secondary-button>
                <x-primary-button type="button" wire:click="siapkanPosting" wire:loading.attr="disabled"
                    wire:target="siapkanPosting" x-ref="btnSaveRcv"
                    :disabled="(int) ($bayar ?? 0) > $grandTotal">
                    <span wire:loading.remove wire:target="siapkanPosting" class="inline-flex items-center gap-2"><x-ikon nama="simpan" /> Simpan &amp; Posting</span>
                    <span wire:loading wire:target="siapkanPosting"><x-loading /> Memeriksa...</span>
                </x-primary-button>
            </div>

            {{-- KONFIRMASI POSTING — ringkasan sebelum stok & kas tercatat --}}
            @if (!empty($pratinjau))
                <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/50"
                    x-data x-init="$nextTick(() => $refs.btnYaPosting?.focus())">
                    <div class="w-full max-w-lg overflow-hidden border shadow-xl bg-canvas rounded-2xl border-hairline dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-5 py-4 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-100">Konfirmasi Posting</h3>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                Setelah diposting, stok masuk gudang dan nota tidak bisa diubah lagi.
                            </p>
                        </div>

                        <dl class="px-5 py-4 space-y-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted dark:text-gray-400">Supplier</dt>
                                <dd class="font-medium text-right text-ink dark:text-gray-100">{{ $suppName }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted dark:text-gray-400">Jumlah item</dt>
                                <dd class="text-ink dark:text-gray-100">{{ $pratinjau['jumlahItem'] }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted dark:text-gray-400">Grand total</dt>
                                <dd class="font-mono font-semibold text-ink dark:text-gray-100">Rp {{ number_format($pratinjau['grandTotal']) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted dark:text-gray-400">Dibayar sekarang</dt>
                                <dd class="font-mono font-semibold text-brand-green dark:text-brand-lime">Rp {{ number_format($pratinjau['bayar']) }}</dd>
                            </div>
                            @if ($pratinjau['bayar'] > 0)
                                <div class="flex justify-between gap-3">
                                    <dt class="text-muted dark:text-gray-400">Akun kas · shift</dt>
                                    <dd class="text-right text-ink dark:text-gray-100">{{ $pratinjau['accName'] }} · Shift {{ $shift }}</dd>
                                </div>
                            @endif
                            <div class="flex items-center justify-between gap-3 pt-2 border-t border-hairline dark:border-gray-700">
                                <dt class="text-muted dark:text-gray-400">Status</dt>
                                <dd>
                                    @if ($pratinjau['status'] === 'L')
                                        <x-badge variant="success">LUNAS</x-badge>
                                    @else
                                        <x-badge variant="warning">HUTANG Rp {{ number_format($pratinjau['sisaHutang']) }}</x-badge>
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        <div class="flex justify-end gap-2 px-5 py-4 border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                            <x-secondary-button type="button" wire:click="batalKonfirmasiPosting"><x-ikon nama="batal" /> Batal</x-secondary-button>
                            <x-primary-button type="button" wire:click="simpan" x-ref="btnYaPosting"
                                wire:loading.attr="disabled" wire:target="simpan">
                                <span wire:loading.remove wire:target="simpan" class="inline-flex items-center gap-2"><x-ikon nama="simpan" /> Ya, posting</span>
                                <span wire:loading wire:target="simpan"><x-loading /> Menyimpan...</span>
                            </x-primary-button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-modal>
</div>
