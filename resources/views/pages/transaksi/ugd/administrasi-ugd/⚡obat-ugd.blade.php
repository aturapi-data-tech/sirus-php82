<?php
// resources/views/pages/transaksi/ugd/administrasi-ugd/obat-ugd.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Stock\StockBalanceTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait, StockBalanceTrait;

    /** Sumber obat resep UGD = Apotek (sama dengan RJ). */
    private const SL_CODE_APOTEK = '02';

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-obat-ugd'];

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $rjObat = [];

    // Saldo stok apotek untuk obat yang sedang dipilih di form entry.
    public ?float $stokTersedia = null;

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
        'catatanKhusus' => '-',
        'etiketStatus' => 0,
    ];

    /* ===============================
     | LISTENER — sync lock saat parent broadcast (post/batal transaksi)
     =============================== */
    #[On('ugd.administrasi-selesai')]
    public function onAdministrasiSelesai(int $rjNo): void
    {
        // Re-check status DB — lock kalau completed, unlock kalau di-batal-kan.
        if ((int) ($this->rjNo ?? 0) === $rjNo) {
            $this->isFormLocked = $this->checkUGDStatus($this->rjNo);
        }
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        if ($this->rjNo) {
            $this->loadData($this->rjNo);
            $this->isFormLocked = $this->checkUGDStatus($this->rjNo);
        }
    }

    /* ===============================
     | LOAD DATA
     =============================== */
    private function loadData(int $rjNo): void
    {
        $this->rjObat = DB::table('rstxn_ugdobats')
            ->join('immst_products', 'immst_products.product_id', 'rstxn_ugdobats.product_id')
            ->select('rstxn_ugdobats.rjobat_dtl', 'rstxn_ugdobats.product_id', 'immst_products.product_name', 'rstxn_ugdobats.qty', 'rstxn_ugdobats.price', 'rstxn_ugdobats.ugd_carapakai', 'rstxn_ugdobats.ugd_kapsul', 'rstxn_ugdobats.ugd_takar', 'rstxn_ugdobats.ugd_ket', 'rstxn_ugdobats.exp_date', 'rstxn_ugdobats.catatan_khusus', 'rstxn_ugdobats.etiket_status')
            ->where('rstxn_ugdobats.rj_no', $rjNo)
            ->orderBy('rstxn_ugdobats.rjobat_dtl')
            ->get()
            ->map(
                fn($r) => [
                    'rjobatDtl' => (int) $r->rjobat_dtl,
                    'productId' => $r->product_id,
                    'productName' => $r->product_name,
                    'qty' => $r->qty,
                    'price' => $r->price,
                    'total' => $r->price * $r->qty,
                    'carapakai' => $r->ugd_carapakai,
                    'kapsul' => $r->ugd_kapsul,
                    'takar' => $r->ugd_takar,
                    'ket' => $r->ugd_ket,
                    'expDate' => $r->exp_date,
                    'expDateDisplay' => $r->exp_date ? Carbon::parse($r->exp_date)->format('d/m/Y') : '-',
                    'catatanKhusus' => $r->catatan_khusus,
                    'etiketStatus' => $r->etiket_status,
                ],
            )
            ->toArray();
    }

    /* ===============================
     | LISTENER
     =============================== */
    #[On('administrasi-obat-ugd.updated')]
    public function onAdministrasiUpdated(): void
    {
        if ($this->rjNo) {
            $this->loadData($this->rjNo);
        }
    }

    /* ===============================
     | LOV SELECTED
     =============================== */
    #[On('lov.selected.obat-ugd')]
    public function onProductSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }
        if (!$payload) {
            $this->formEntryObat['productId'] = $this->formEntryObat['productName'] = $this->formEntryObat['price'] = '';
            $this->stokTersedia = null;
            return;
        }
        $rjDate = DB::table('rstxn_ugdhdrs')->where('rj_no', $this->rjNo)->value('rj_date');
        $this->formEntryObat['productId'] = $payload['product_id'];
        $this->formEntryObat['productName'] = $payload['product_name'];
        $this->formEntryObat['price'] = $payload['sales_price'];
        $this->formEntryObat['expDate'] = ($rjDate ? Carbon::parse($rjDate) : Carbon::now())->addDays(30)->format('d/m/Y');

        // Muat saldo apotek begitu obat dipilih — badge stok langsung tampil sebelum user ketik qty.
        $this->stokTersedia = $this->saldoStok(self::SL_CODE_APOTEK, (string) $payload['product_id']);

        $this->dispatch('focus-input-qty-obat-ugd');
    }

    /**
     * Status pengecekan stok untuk qty yang sedang diketik di form entry.
     *   'idle'   → belum pilih obat (badge tidak ditampilkan)
     *   'cukup'  → qty <= stok
     *   'kurang' → qty > stok
     */
    #[Computed]
    public function stokStatus(): string
    {
        if ($this->stokTersedia === null) {
            return 'idle';
        }
        $qty = (float) ($this->formEntryObat['qty'] ?? 0);
        return $qty > $this->stokTersedia ? 'kurang' : 'cukup';
    }

    /* ===============================
     | INSERT OBAT
     =============================== */
    public function insertObat(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }
        $this->validate(
            [
                'formEntryObat.productId' => 'bail|required|exists:immst_products,product_id',
                'formEntryObat.price' => 'bail|required|numeric|min:0',
                'formEntryObat.qty' => 'bail|required|numeric|min:1',
                'formEntryObat.carapakai' => 'bail|required|numeric|min:1',
                'formEntryObat.kapsul' => 'bail|required|numeric|min:1',
                'formEntryObat.takar' => 'bail|required|string',
                'formEntryObat.expDate' => 'bail|required|date_format:d/m/Y',
                'formEntryObat.catatanKhusus' => 'bail|nullable|string',
                'formEntryObat.etiketStatus' => 'bail|required|integer',
            ],
            [
                'formEntryObat.productId.required' => 'Obat harus dipilih.',
                'formEntryObat.productId.exists' => 'Obat tidak valid.',
                'formEntryObat.price.required' => 'Harga harus diisi.',
                'formEntryObat.qty.required' => 'Jumlah harus diisi.',
                'formEntryObat.qty.min' => 'Jumlah minimal 1.',
                'formEntryObat.carapakai.required' => 'Cara pakai harus diisi.',
                'formEntryObat.kapsul.required' => 'Jumlah per minum harus diisi.',
                'formEntryObat.takar.required' => 'Takaran harus diisi.',
                'formEntryObat.expDate.required' => 'Tanggal kadaluarsa harus diisi.',
                'formEntryObat.expDate.date_format' => 'Tanggal kadaluarsa harus format dd/mm/yyyy.',
            ],
        );

        // Policy stok ditentukan oleh flag 'strict' di trait — block kalau lokasi strict.
        $policy = $this->terapkanKebijakanStok(self::SL_CODE_APOTEK, (string) $this->formEntryObat['productId'], (float) $this->formEntryObat['qty']);
        if (!$policy['boleh']) {
            $stokDisplay = rtrim(rtrim(number_format($policy['tersedia'], 2, ',', '.'), '0'), ',');
            $this->dispatch('toast', type: 'error', message: 'Stok ' . $this->namaLokasi(self::SL_CODE_APOTEK) . ' hanya ' . $stokDisplay . ' — tidak cukup.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockUGDRow($this->rjNo);
                $last = DB::table('rstxn_ugdobats')->select(DB::raw('nvl(max(rjobat_dtl)+1,1) as rjobat_dtl_max'))->first();
                $expDateFormatted = Carbon::createFromFormat('d/m/Y', $this->formEntryObat['expDate'])->startOfDay()->format('Y-m-d H:i:s');
                DB::table('rstxn_ugdobats')->insert([
                    'rjobat_dtl' => $last->rjobat_dtl_max,
                    'rj_no' => $this->rjNo,
                    'product_id' => $this->formEntryObat['productId'],
                    'qty' => $this->formEntryObat['qty'],
                    'price' => $this->formEntryObat['price'],
                    'ugd_carapakai' => $this->formEntryObat['carapakai'],
                    'ugd_kapsul' => $this->formEntryObat['kapsul'],
                    'ugd_takar' => $this->formEntryObat['takar'],
                    'ugd_ket' => $this->formEntryObat['ket'] ?: null,
                    'catatan_khusus' => $this->formEntryObat['catatanKhusus'] ?: '-',
                    'exp_date' => DB::raw("to_date('" . $expDateFormatted . "','yyyy-mm-dd hh24:mi:ss')"),
                    'etiket_status' => $this->formEntryObat['etiketStatus'],
                ]);
                $this->rjObat[] = [
                    'rjobatDtl' => (int) $last->rjobat_dtl_max,
                    'productId' => $this->formEntryObat['productId'],
                    'productName' => $this->formEntryObat['productName'],
                    'qty' => $this->formEntryObat['qty'],
                    'price' => $this->formEntryObat['price'],
                    'total' => $this->formEntryObat['price'] * $this->formEntryObat['qty'],
                    'carapakai' => $this->formEntryObat['carapakai'],
                    'kapsul' => $this->formEntryObat['kapsul'],
                    'takar' => $this->formEntryObat['takar'],
                    'ket' => $this->formEntryObat['ket'],
                    'expDate' => Carbon::createFromFormat('d/m/Y', $this->formEntryObat['expDate'])->format('Y-m-d'),
                    'expDateDisplay' => $this->formEntryObat['expDate'],
                    'catatanKhusus' => $this->formEntryObat['catatanKhusus'],
                    'etiketStatus' => $this->formEntryObat['etiketStatus'],
                ];

                $this->appendAdminLogUGD($this->rjNo, 'Tambah Obat: ' . $this->formEntryObat['productName'] . ' x' . $this->formEntryObat['qty']);
            });
            // Warning saldo (warn-mode dari trait) — insert sudah lolos policy, tinggal beri tahu user.
            if (!$policy['cukup']) {
                $stokDisplay = rtrim(rtrim(number_format($policy['tersedia'], 2, ',', '.'), '0'), ',');
                $this->dispatch('toast', type: 'warning', message: 'Stok ' . $this->namaLokasi(self::SL_CODE_APOTEK) . ' hanya ' . $stokDisplay . ' — perlu dilengkapi dari gudang sebelum diserahkan.');
            }

            $this->resetFormEntry();
            $this->dispatch('focus-lov-obat-ugd');
            $this->dispatch('administrasi-ugd.updated');
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | INLINE EDIT
     =============================== */
    public function startEdit(int $rjobatDtl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $row = collect($this->rjObat)->firstWhere('rjobatDtl', $rjobatDtl);
        if (!$row) {
            return;
        }
        $this->editingDtl = $rjobatDtl;
        $this->editRow = [
            'qty' => $row['qty'],
            'carapakai' => $row['carapakai'],
            'kapsul' => $row['kapsul'],
            'takar' => $row['takar'],
            'ket' => $row['ket'] ?? '',
            'expDate' => $row['expDate'] ? Carbon::parse($row['expDate'])->format('d/m/Y') : '',
            'catatanKhusus' => $row['catatanKhusus'] ?? '-',
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
        if ($this->isFormLocked || !$this->editingDtl) {
            return;
        }
        $this->validateOnly('editRow.qty', ['editRow.qty' => 'required|numeric|min:1'], ['editRow.qty.required' => 'Qty wajib diisi.', 'editRow.qty.min' => 'Qty minimal 1.']);
        $this->validateOnly('editRow.carapakai', ['editRow.carapakai' => 'required|numeric|min:1'], ['editRow.carapakai.required' => 'x/Hari wajib diisi.']);
        $this->validateOnly('editRow.kapsul', ['editRow.kapsul' => 'required|numeric|min:1'], ['editRow.kapsul.required' => 'Per minum wajib diisi.']);
        $this->validateOnly('editRow.takar', ['editRow.takar' => 'required|string'], ['editRow.takar.required' => 'Takar wajib diisi.']);
        $this->validateOnly('editRow.expDate', ['editRow.expDate' => 'required|date_format:d/m/Y'], ['editRow.expDate.required' => 'Exp. Date wajib diisi.', 'editRow.expDate.date_format' => 'Format harus dd/mm/yyyy.']);
        try {
            DB::transaction(function () {
                $this->lockUGDRow($this->rjNo);
                $expDateFormatted = Carbon::createFromFormat('d/m/Y', $this->editRow['expDate'])->startOfDay()->format('Y-m-d H:i:s');
                DB::table('rstxn_ugdobats')
                    ->where('rjobat_dtl', $this->editingDtl)
                    ->update([
                        'qty' => $this->editRow['qty'],
                        'ugd_carapakai' => $this->editRow['carapakai'],
                        'ugd_kapsul' => $this->editRow['kapsul'],
                        'ugd_takar' => $this->editRow['takar'],
                        'ugd_ket' => $this->editRow['ket'] ?: null,
                        'catatan_khusus' => $this->editRow['catatanKhusus'] ?: '-',
                        'exp_date' => DB::raw("to_date('" . $expDateFormatted . "','yyyy-mm-dd hh24:mi:ss')"),
                    ]);
                $this->rjObat = collect($this->rjObat)
                    ->map(
                        fn($item) => $item['rjobatDtl'] !== $this->editingDtl
                            ? $item
                            : array_merge($item, [
                                'qty' => $this->editRow['qty'],
                                'total' => $item['price'] * $this->editRow['qty'],
                                'carapakai' => $this->editRow['carapakai'],
                                'kapsul' => $this->editRow['kapsul'],
                                'takar' => $this->editRow['takar'],
                                'ket' => $this->editRow['ket'],
                                'expDate' => Carbon::createFromFormat('d/m/Y', $this->editRow['expDate'])->format('Y-m-d'),
                                'expDateDisplay' => $this->editRow['expDate'],
                                'catatanKhusus' => $this->editRow['catatanKhusus'],
                            ]),
                    )
                    ->toArray();

                $this->appendAdminLogUGD($this->rjNo, 'Edit Obat #' . $this->editingDtl);
            });
            $this->editingDtl = null;
            $this->editRow = [];
            $this->dispatch('administrasi-ugd.updated');
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil diperbarui.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE
     =============================== */
    public function removeObat(int $rjobatDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }
        try {
            DB::transaction(function () use ($rjobatDtl) {
                $this->lockUGDRow($this->rjNo);
                DB::table('rstxn_ugdobats')->where('rjobat_dtl', $rjobatDtl)->delete();
                $this->rjObat = collect($this->rjObat)->where('rjobatDtl', '!=', $rjobatDtl)->values()->toArray();
                $this->appendAdminLogUGD($this->rjNo, 'Hapus Obat #' . $rjobatDtl);
            });
            if ($this->editingDtl === $rjobatDtl) {
                $this->cancelEdit();
            }
            $this->dispatch('administrasi-ugd.updated');
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK ETIKET
     =============================== */
    public function cetakEtiketItem(int $rjobatDtl): void
    {
        $this->dispatch('cetak-etiket-obat-ugd.open', rjObatNo: $rjobatDtl);
    }

    /* ===============================
     | RESET FORM
     =============================== */
    public function resetFormEntry(): void
    {
        $this->reset(['formEntryObat']);
        $this->formEntryObat['qty'] = 1;
        $this->formEntryObat['carapakai'] = 1;
        $this->formEntryObat['kapsul'] = 1;
        $this->formEntryObat['takar'] = 'Tablet';
        $this->formEntryObat['catatanKhusus'] = '-';
        $this->formEntryObat['etiketStatus'] = 0;
        $this->stokTersedia = null;
        $this->resetValidation();
        $this->incrementVersion('modal-obat-ugd');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-obat-ugd', [$rjNo ?? 'new']) }}" x-data>

    {{-- LOCKED BANNER --}}
    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — data obat terkunci, tidak dapat diubah.
        </div>
    @endif

    {{-- Kiri: form entri · Kanan: daftar data --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-5 items-start">
        {{-- FORM INPUT --}}
        <div class="lg:col-span-2 p-4 border border-hairline rounded-2xl dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40" x-data
            x-on:focus-lov-obat-ugd.window="$nextTick(() => {
                const fokus = () => {
                    const el = $refs.lovObatUgd?.querySelector('input');
                    if (!el || el === document.activeElement) return;
                    if (document.activeElement?.matches('input, select, textarea')) return;
                    el.focus();
                };
                fokus();
                setTimeout(fokus, 150);
            })"
            x-on:focus-input-qty-obat-ugd.window="$nextTick(() => { $refs.inputQty?.focus(); $refs.inputQty?.select(); })">

            @if ($isFormLocked)
                <p class="text-sm italic text-muted-soft dark:text-gray-600">Form input dinonaktifkan.</p>
            @elseif (empty($formEntryObat['productId']))
                {{-- Enter saat kolom cari masih kosong = selesai di tab ini → lompat ke Laboratorium. --}}
                <div x-ref="lovObatUgd"
                    x-on:keydown.enter="if (!$event.target.value?.trim()) $dispatch('administrasi-ugd-goto-tab', { tab: 'Laboratorium', focus: 'focus-panel-laboratorium-ugd' })">
                    <livewire:lov.product.lov-product target="obat-ugd" label="Cari Obat"
                        placeholder="Ketik nama/kode/kandungan obat..."
                        wire:key="lov-obat-ugd-{{ $rjNo }}-{{ $renderVersions['modal-obat-ugd'] ?? 0 }}" />
                </div>
            @else
                {{-- Identitas obat terpilih (read-only, hasil pilih LOV) --}}
                <div class="flex flex-wrap items-center mb-3 gap-x-3 gap-y-1">
                    <span
                        class="px-2 py-0.5 text-xs font-mono rounded-md border border-hairline dark:border-gray-700 bg-surface-soft dark:bg-gray-800 text-muted dark:text-gray-300">{{ $formEntryObat['productId'] }}</span>
                    <span
                        class="text-lg font-bold text-ink dark:text-gray-100">{{ $formEntryObat['productName'] }}</span>

                    {{-- Indikator saldo apotek — live ketika qty berubah --}}
                    @if ($this->stokStatus !== 'idle')
                        @php
                            $stokDisplay = rtrim(rtrim(number_format((float) $stokTersedia, 2, ',', '.'), '0'), ',');
                        @endphp
                        @if ($this->stokStatus === 'cukup')
                            <span class="inline-flex items-center gap-1 ml-auto text-sm font-medium text-green-700 dark:text-green-400"
                                title="Saldo Apotek">
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                Stok Apotek: {{ $stokDisplay }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 ml-auto text-sm font-medium text-red-700 dark:text-red-400"
                                title="Stok Apotek kurang dari qty diminta">
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.74-3l-7.07-12a2 2 0 00-3.48 0l-7.07 12a2 2 0 001.74 3z" />
                                </svg>
                                Stok Apotek: {{ $stokDisplay }} (kurang)
                            </span>
                        @endif
                    @endif
                </div>

                {{-- Semua field entri dalam satu baris (wrap otomatis di layar sempit) --}}
                <div class="flex flex-wrap items-end gap-2">
                    <div class="w-20">
                        <x-input-label value="Qty" class="mb-1" />
                        <x-text-input wire:model.live.debounce.150ms="formEntryObat.qty" placeholder="Qty"
                            class="w-full text-sm" x-ref="inputQty"
                            x-on:keyup.enter="$nextTick(() => $refs.inputHarga?.focus())" />
                        <x-input-error :messages="$errors->get('formEntryObat.qty')" class="mt-1" />
                    </div>
                    <div class="w-28">
                        <x-input-label value="Harga" class="mb-1" />
                        <x-text-input-number wire:model="formEntryObat.price" placeholder="Harga"
                            class="text-sm" x-ref="inputHarga"
                            x-on:keydown.enter.prevent="$el.blur(); $nextTick(() => $refs.inputCarapakai?.focus())" />
                        <x-input-error :messages="$errors->get('formEntryObat.price')" class="mt-1" />
                    </div>
                    <div class="w-16">
                        <x-input-label value="x/Hari" class="mb-1" />
                        <x-text-input wire:model="formEntryObat.carapakai" placeholder="1" class="w-full text-sm"
                            x-ref="inputCarapakai" x-on:keyup.enter="$nextTick(() => $refs.inputKapsul?.focus())" />
                        <x-input-error :messages="$errors->get('formEntryObat.carapakai')" class="mt-1" />
                    </div>
                    <div class="w-20">
                        <x-input-label value="Per Minum" class="mb-1" />
                        <x-text-input wire:model="formEntryObat.kapsul" placeholder="1" class="w-full text-sm"
                            x-ref="inputKapsul" x-on:keyup.enter="$nextTick(() => $refs.inputTakar?.focus())" />
                        <x-input-error :messages="$errors->get('formEntryObat.kapsul')" class="mt-1" />
                    </div>
                    <div class="w-28">
                        <x-input-label value="Takar" class="mb-1" />
                        <x-select-input wire:model="formEntryObat.takar" x-ref="inputTakar"
                            class="block w-full text-sm border-gray-300 rounded-lg shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-brand-green focus:border-brand-green">
                            <option>Tablet</option>
                            <option>Kapsul</option>
                            <option>Sirup</option>
                            <option>Sachet</option>
                            <option>Tetes</option>
                            <option>Salep</option>
                            <option>Injeksi</option>
                            <option>Unit</option>
                            <option>Lainnya</option>
                        </x-select-input>
                    </div>
                    <div class="flex-1 min-w-[7rem]">
                        <x-input-label value="Keterangan" class="mb-1" />
                        <x-text-input wire:model="formEntryObat.ket" placeholder="Ket." class="w-full text-sm"
                            x-ref="inputKet" x-on:keyup.enter="$nextTick(() => $refs.inputExpDate?.focus())" />
                    </div>
                    <div class="w-36">
                        <x-input-label value="Exp. Date" class="mb-1" />
                        <x-text-input wire:model="formEntryObat.expDate" placeholder="dd/mm/yyyy" inputmode="numeric"
                            maxlength="10" class="w-full text-sm" x-ref="inputExpDate"
                            x-on:keyup.enter="$nextTick(() => $refs.inputCatatan?.focus())" />
                        <x-input-error :messages="$errors->get('formEntryObat.expDate')" class="mt-1" />
                    </div>
                    <div class="flex-1 min-w-[8rem]">
                        <x-input-label value="Catatan Khusus" class="mb-1" />
                        <x-text-input wire:model="formEntryObat.catatanKhusus" placeholder="Catatan..."
                            class="w-full text-sm" x-ref="inputCatatan" x-on:keydown.enter.prevent="$el.blur(); $wire.insertObat()" />
                    </div>
                    <div class="w-24">
                        <x-input-label value="Etiket" class="mb-1" />
                        <x-select-input wire:model="formEntryObat.etiketStatus"
                            class="block w-full text-sm border-gray-300 rounded-lg shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-brand-green focus:border-brand-green">
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

        {{-- TABEL DATA --}}
        <div class="lg:col-span-3 overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead
                        class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300 bg-surface-soft dark:bg-gray-800/50">
                        <tr>
                            <th class="px-3 py-3">Kode</th>
                            <th class="px-3 py-3">Nama Obat</th>
                            <th class="px-3 py-3 text-right">Qty</th>
                            <th class="px-3 py-3 text-right">Harga</th>
                            <th class="px-3 py-3 text-right">Total</th>
                            <th class="px-3 py-3">Signa</th>
                            <th class="px-3 py-3 text-center">Etiket</th>
                            @if (!$isFormLocked)
                                <th class="w-24 px-3 py-3 text-center">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                        @forelse ($rjObat as $item)
                            @php $isEditing = $editingDtl === $item['rjobatDtl']; @endphp
                            @if ($isEditing)
                                {{-- BARIS EDIT — tata letaknya dibuat sama dengan form entri di kolom kiri --}}
                                <tr wire:key="obat-row-{{ $item['rjobatDtl'] }}-edit" x-data
                                    class="bg-blue-50 dark:bg-blue-900/20 transition">
                                    <td colspan="{{ $isFormLocked ? 7 : 8 }}" class="px-3 py-3">

                                        {{-- Identitas obat — sejajar dengan baris identitas di form entri --}}
                                        <div class="flex flex-wrap items-center mb-3 gap-x-3 gap-y-1">
                                            <span
                                                class="px-2 py-0.5 text-xs font-mono rounded-md border border-hairline dark:border-gray-700 bg-canvas dark:bg-gray-800 text-muted dark:text-gray-300">{{ $item['productId'] }}</span>
                                            <span
                                                class="text-lg font-bold text-ink dark:text-gray-100">{{ $item['productName'] }}</span>
                                            <span class="ml-auto text-sm text-muted dark:text-gray-400">
                                                Total
                                                <span class="text-base font-bold text-ink dark:text-gray-100">Rp
                                                    {{ number_format($item['price'] * ($editRow['qty'] ?? $item['qty'])) }}</span>
                                            </span>
                                        </div>

                                        {{-- Field — urutan & lebar mengikuti form entri --}}
                                        <div class="flex flex-wrap items-end gap-2">
                                            <div class="w-20">
                                                <x-input-label value="Qty" class="mb-1" />
                                                <x-text-input wire:model="editRow.qty" class="w-full text-sm text-right"
                                                    x-ref="editQty"
                                                    x-init="$el.focus();
                                                    $el.select()"
                                                    x-on:keyup.enter="$nextTick(() => $refs.editCarapakai?.focus())" />
                                            </div>
                                            <div class="w-28">
                                                <x-input-label value="Harga" class="mb-1" />
                                                <x-text-input value="{{ number_format($item['price']) }}" disabled
                                                    class="w-full text-sm text-right" />
                                            </div>
                                            <div class="w-16">
                                                <x-input-label value="x/Hari" class="mb-1" />
                                                <x-text-input wire:model="editRow.carapakai"
                                                    class="w-full text-sm text-center" x-ref="editCarapakai"
                                                    x-on:keyup.enter="$nextTick(() => $refs.editKapsul?.focus())" />
                                            </div>
                                            <div class="w-20">
                                                <x-input-label value="Per Minum" class="mb-1" />
                                                <x-text-input wire:model="editRow.kapsul"
                                                    class="w-full text-sm text-center" x-ref="editKapsul"
                                                    x-on:keyup.enter="$nextTick(() => $refs.editTakar?.focus())" />
                                            </div>
                                            <div class="w-28">
                                                <x-input-label value="Takar" class="mb-1" />
                                                <x-select-input wire:model="editRow.takar" x-ref="editTakar"
                                                    x-on:change="$nextTick(() => $refs.editKet?.focus())"
                                                    class="block w-full text-sm border-gray-300 rounded-lg shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-brand-green focus:border-brand-green">
                                                    <option>Tablet</option>
                                                    <option>Kapsul</option>
                                                    <option>Sirup</option>
                                                    <option>Sachet</option>
                                                    <option>Tetes</option>
                                                    <option>Salep</option>
                                                    <option>Injeksi</option>
                                                    <option>Unit</option>
                                                    <option>Lainnya</option>
                                                </x-select-input>
                                            </div>
                                            <div class="flex-1 min-w-[7rem]">
                                                <x-input-label value="Keterangan" class="mb-1" />
                                                <x-text-input wire:model="editRow.ket" placeholder="Ket."
                                                    class="w-full text-sm" x-ref="editKet"
                                                    x-on:keyup.enter="$nextTick(() => $refs.editExpDate?.focus())" />
                                            </div>
                                            <div class="w-36">
                                                <x-input-label value="Exp. Date" class="mb-1" />
                                                <x-text-input wire:model="editRow.expDate" placeholder="dd/mm/yyyy"
                                                    inputmode="numeric" maxlength="10" class="w-full text-sm"
                                                    x-ref="editExpDate"
                                                    x-on:keyup.enter="$nextTick(() => $refs.editCatatan?.focus())" />
                                            </div>
                                            <div class="flex-1 min-w-[8rem]">
                                                <x-input-label value="Catatan Khusus" class="mb-1" />
                                                <x-text-input wire:model="editRow.catatanKhusus" placeholder="Catatan..."
                                                    class="w-full text-sm" x-ref="editCatatan"
                                                    x-on:keydown.enter.prevent="$el.blur(); $wire.saveEdit()" />
                                            </div>
                                            <div class="w-24">
                                                <x-input-label value="Etiket" class="mb-1" />
                                    <x-icon-button color="blue" type="button"
                                        wire:click="cetakEtiketItem({{ $item['rjobatDtl'] }})" wire:loading.attr="disabled"
                                        wire:target="cetakEtiketItem({{ $item['rjobatDtl'] }})" title="Cetak etiket obat ini">
                                        <span wire:loading.remove wire:target="cetakEtiketItem({{ $item['rjobatDtl'] }})">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                        </span>
                                        <span wire:loading wire:target="cetakEtiketItem({{ $item['rjobatDtl'] }})">
                                            <x-loading class="w-4 h-4" />
                                        </span>
                                    </x-icon-button>
                                            </div>
                                            @if (!$isFormLocked)
                                                <div class="flex items-center gap-2 pb-0.5 shrink-0">
                                                    <x-primary-button type="button" wire:click="saveEdit"
                                                        wire:loading.attr="disabled" wire:target="saveEdit">
                                                        <span wire:loading.remove wire:target="saveEdit">Simpan</span>
                                                        <span wire:loading wire:target="saveEdit"><x-loading /></span>
                                                    </x-primary-button>
                                                    <x-icon-button color="gray" type="button" wire:click="cancelEdit"
                                                        title="Batal — tutup baris edit">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </x-icon-button>
                                                </div>
                                            @endif
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
                                <tr wire:key="obat-row-{{ $item['rjobatDtl'] }}-view"
                                    class="hover:bg-surface-soft dark:hover:bg-gray-800/40 transition">

                                    <td class="px-3 py-1.5 font-mono text-sm text-muted dark:text-gray-400 whitespace-nowrap">
                                        {{ $item['productId'] }}</td>
                                    <td class="px-3 py-1.5 text-ink dark:text-gray-200 whitespace-nowrap">
                                        {{ $item['productName'] }}</td>
                                    <td class="px-3 py-1.5 whitespace-nowrap">
                                        <span
                                            class="block text-right text-body dark:text-gray-300">{{ number_format($item['qty']) }}</span>
                                    </td>
                                    <td class="px-3 py-1.5 text-right text-body dark:text-gray-300 whitespace-nowrap">Rp
                                        {{ number_format($item['price']) }}</td>
                                    <td
                                        class="px-3 py-1.5 font-semibold text-right text-ink dark:text-gray-200 whitespace-nowrap">
                                        Rp
                                        {{ number_format($item['total']) }}
                                    </td>
                                    {{-- Signa = aturan pakai + takaran + ket, exp. date & catatan --}}
                                    <td class="px-3 py-1.5">
                                        <div class="flex flex-col leading-tight">
                                            <span class="text-body dark:text-gray-300">
                                                {{ $item['carapakai'] }}x{{ $item['kapsul'] }} {{ $item['takar'] }}
                                                @if (!empty($item['ket']) && $item['ket'] !== '-')
                                                    <span class="text-muted dark:text-gray-400">&middot; {{ $item['ket'] }}</span>
                                                @endif
                                            </span>
                                            <span class="text-xs text-muted dark:text-gray-400">
                                                Exp. {{ $item['expDateDisplay'] ?? '-' }}
                                                @if (!empty($item['catatanKhusus']) && $item['catatanKhusus'] !== '-')
                                                    &middot; {{ $item['catatanKhusus'] }}
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-1.5 text-center whitespace-nowrap">
                                    <x-icon-button color="blue" type="button"
                                        wire:click="cetakEtiketItem({{ $item['rjobatDtl'] }})" wire:loading.attr="disabled"
                                        wire:target="cetakEtiketItem({{ $item['rjobatDtl'] }})" title="Cetak etiket obat ini">
                                        <span wire:loading.remove wire:target="cetakEtiketItem({{ $item['rjobatDtl'] }})">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                        </span>
                                        <span wire:loading wire:target="cetakEtiketItem({{ $item['rjobatDtl'] }})">
                                            <x-loading class="w-4 h-4" />
                                        </span>
                                    </x-icon-button>
                                    </td>
                                    @if (!$isFormLocked)
                                        <td class="px-3 py-1.5 whitespace-nowrap">
                                            <div class="flex items-center gap-1">
                                                <x-icon-button color="gray" type="button" wire:click="startEdit({{ $item['rjobatDtl'] }})"
                                                    title="Edit baris obat ini">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </x-icon-button>
                                                <x-outline-button type="button" wire:click.prevent="removeObat({{ $item['rjobatDtl'] }})"
                                                    wire:confirm="Hapus obat ini?"
                                                    wire:loading.attr="disabled" wire:target="removeObat({{ $item['rjobatDtl'] }})"
                                                    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                    title="Hapus">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </x-outline-button>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ $isFormLocked ? 7 : 8 }}"
                                    class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                    <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    Belum ada data obat
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if (!empty($rjObat))
                        <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                            <tr>
                                <td colspan="4"
                                    class="px-3 py-3 text-sm font-semibold text-muted dark:text-gray-400">Total</td>
                                <td class="px-3 py-3 text-sm font-bold text-right text-ink dark:text-white">
                                    Rp {{ number_format(collect($rjObat)->sum('total')) }}
                                </td>
                                <td colspan="{{ $isFormLocked ? 2 : 3 }}"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <livewire:pages::components.rekam-medis.ugd.etiket-obat.cetak-etiket-obat wire:key="cetak-etiket-obat-ugd" />

</div>
