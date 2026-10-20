<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    /** Lokasi stok tujuan return: bangsal → Gudang Medis → Apotek (obat dipakai lagi oleh Apotek). */
    public const GUDANG_SL_CODE = '04';
    public const APOTEK_SL_CODE = '02';

    /** Status header IMTXN_TRFHDRS (sama dengan modul Transfer Stok). */
    public const STATUS_TRANSFER_POSTED = 'L';
    public const STATUS_TRANSFER_BATAL = 'F';

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-rtn-obat-ri', 'modal-kirim-return-ri'];

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataRtnObat = [];

    /** Lokasi asal stok return (ruangan) — dipilih di modal Kirim, default Kode SL bangsal pasien. */
    public ?string $slCodeAsal = null;
    public ?string $slNameAsal = null;

    public array $formEntry = [
        'riobatDate'   => '',
        'productId'    => '',
        'productName'  => '',
        'productPrice' => '',
        'productQty'   => '1',
    ];

    private function nowFormatted(): string
    {
        return Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | LISTENER — sync lock saat parent broadcast (post/batal transaksi)
     =============================== */
    #[On('ri.administrasi-selesai')]
    public function onAdministrasiSelesai(?int $riHdrNo = null): void
    {
        if (!$riHdrNo) return;
        // Re-check status DB — lock kalau completed, unlock kalau di-batal-kan.
        if ((int) ($this->riHdrNo ?? 0) === $riHdrNo) {
            $this->isFormLocked = $this->checkRIStatus($this->riHdrNo);
        }
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->formEntry['riobatDate'] = $this->nowFormatted();

        if ($this->riHdrNo) {
            $this->isFormLocked = $this->checkRIStatus($this->riHdrNo);
            $this->findData($this->riHdrNo);
        }
    }

    private function findData(int $riHdrNo): void
    {
        $rows = DB::table('rstxn_riobatrtns')
            ->join('immst_products', 'immst_products.product_id', '=', 'rstxn_riobatrtns.product_id')
            ->select(
                DB::raw("to_char(riobat_date, 'dd/mm/yyyy hh24:mi:ss') as riobat_date"),
                'rstxn_riobatrtns.product_id',
                'immst_products.product_name',
                'rstxn_riobatrtns.riobat_qty',
                'rstxn_riobatrtns.riobat_price',
                'rstxn_riobatrtns.riobat_no',
                'rstxn_riobatrtns.trf_no_gudang',
                'rstxn_riobatrtns.trf_no_apotek',
            )
            ->where('rstxn_riobatrtns.rihdr_no', $riHdrNo)
            ->orderByDesc('rstxn_riobatrtns.riobat_date')
            ->get();

        $this->dataRtnObat = $rows->map(fn($row) => (array) $row)->toArray();
    }

    /* ===============================
     | REFRESH — event dari sibling / parent
     =============================== */
    #[On('administrasi-ri.updated')]
    public function refresh(): void
    {
        if ($this->riHdrNo) {
            $this->findData($this->riHdrNo);
        }
    }

    /* ===============================
     | LOV SELECTED — PRODUCT
     =============================== */
    #[On('lov.selected.product-rtn-obat-ri')]
    public function onProductSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->formEntry['productId']    = '';
            $this->formEntry['productName']  = '';
            $this->formEntry['productPrice'] = '';
            return;
        }

        $this->formEntry['productId']    = $payload['product_id'];
        $this->formEntry['productName']  = $payload['product_name'];
        // Sementara return pakai harga beli (cost_price), bukan harga jual.
        $this->formEntry['productPrice'] = $payload['cost_price'] ?? 0;

        $this->dispatch('focus-input-rtn-qty');
    }

    /* ===============================
     | INSERT
     =============================== */
    public function insertRtnObat(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntry.productId'    => 'bail|required|exists:immst_products,product_id',
                'formEntry.productPrice' => 'bail|required|numeric|min:0',
                'formEntry.productQty'   => 'bail|required|numeric|min:1',
            ],
            [
                'formEntry.productId.required'    => 'Produk wajib dipilih.',
                'formEntry.productId.exists'      => 'Produk tidak valid.',
                'formEntry.productPrice.required' => 'Harga wajib diisi.',
                'formEntry.productPrice.numeric'  => 'Harga harus berupa angka.',
                'formEntry.productQty.required'   => 'Jumlah wajib diisi.',
                'formEntry.productQty.min'        => 'Jumlah minimal 1.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                // PK legacy: select nvl(max(riobat_no)+1,1) from RSTXN_RIOBATRTNS
                $last = DB::table('rstxn_riobatrtns')
                    ->select(DB::raw("nvl(max(riobat_no)+1,1) as riobat_no_max"))
                    ->first();

                DB::table('rstxn_riobatrtns')->insert([
                    'riobat_no'    => $last->riobat_no_max,
                    'rihdr_no'     => $this->riHdrNo,
                    'product_id'   => $this->formEntry['productId'],
                    'riobat_date'  => DB::raw("TO_DATE('" . $this->formEntry['riobatDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                    'riobat_price' => $this->formEntry['productPrice'],
                    'riobat_qty'   => $this->formEntry['productQty'],
                ]);
                $this->appendAdminLogRI($this->riHdrNo, 'Return Obat: ' . $this->formEntry['productName']);
            });

            $this->resetFormEntry();
            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('focus-lov-rtn-obat-ri');
            $this->dispatch('toast', type: 'success', message: 'Return obat berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE
     =============================== */
    public function removeRtnObat(int $riobatNo): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($riobatNo) {
                $this->lockRIRow($this->riHdrNo);

                // Return yang sudah dikirim ke Apotek tidak boleh hilang — stoknya sudah pindah.
                $sudahDikirim = DB::table('rstxn_riobatrtns')->where('riobat_no', $riobatNo)->whereNotNull('trf_no_apotek')->exists();
                if ($sudahDikirim) {
                    throw new \RuntimeException('Return ini sudah dikirim ke Apotek — tekan Batal Kirim dulu.');
                }

                DB::table('rstxn_riobatrtns')->where('riobat_no', $riobatNo)->delete();
                $this->appendAdminLogRI($this->riHdrNo, 'Hapus Return Obat #' . $riobatNo);
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Return obat berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BUKA MODAL KIRIM — pilih ruangan asal stok, default Kode SL bangsal pasien.
     =============================== */
    public function bukaKirimReturn(): void
    {
        $this->isFormLocked = $this->checkRIStatus($this->riHdrNo);
        if (!$this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi RI belum selesai — return baru bisa dikirim ke Apotek setelah pasien pulang.');
            return;
        }

        $bangsal = DB::table('rsview_rihdrs')
            ->join('rsmst_bangsals', 'rsmst_bangsals.bangsal_id', '=', 'rsview_rihdrs.bangsal_id')
            ->leftJoin('immst_stocklocations', 'immst_stocklocations.sl_code', '=', 'rsmst_bangsals.sl_codefrom')
            ->where('rsview_rihdrs.rihdr_no', $this->riHdrNo)
            ->select('rsmst_bangsals.sl_codefrom', 'immst_stocklocations.sl_name')
            ->first();
        $this->slCodeAsal = trim((string) ($bangsal->sl_codefrom ?? '')) ?: null;
        $this->slNameAsal = $bangsal->sl_name ?? null;

        $this->incrementVersion('modal-kirim-return-ri');
        $this->dispatch('open-modal', name: 'kirim-return-obat-ri');
    }

    #[On('lov.selected.asal-return-ri')]
    public function onPickAsalReturn(string $target, array $payload): void
    {
        $this->slCodeAsal = (string) ($payload['sl_code'] ?? '') ?: null;
        $this->slNameAsal = $payload['sl_name'] ?? null;
    }

    public function tutupKirimReturn(): void
    {
        $this->dispatch('close-modal', name: 'kirim-return-obat-ri');
    }

    /* ===============================
     | KIRIM RETURN KE APOTEK — HANYA setelah transaksi RI selesai, supaya tidak ada return
     | baru yang menyusul. Dua transfer langsung berstatus selesai (L):
     |   1. ruangan asal (dipilih di modal) → Gudang Medis (04)   2. Gudang Medis (04) → Apotek (02)
     | Ledger tkview_iostock* membaca transfer berstatus L sebagai mutasi keluar/masuk.
     =============================== */
    public function kirimReturnKeApotek(): void
    {
        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                // Dibaca ulang SESUDAH baris RI dikunci — kasir bisa membatalkan transaksi di sela klik.
                $this->isFormLocked = $this->checkRIStatus($this->riHdrNo);
                if (!$this->isFormLocked) {
                    throw new \RuntimeException('Transaksi RI belum selesai — return baru bisa dikirim ke Apotek setelah pasien pulang.');
                }

                $slCodeAsal = (string) $this->slCodeAsal;
                if ($slCodeAsal === '' || in_array($slCodeAsal, [self::GUDANG_SL_CODE, self::APOTEK_SL_CODE], true)) {
                    throw new \RuntimeException('Pilih ruangan asal stok return dulu (bukan Gudang/Apotek).');
                }
                if (!DB::table('immst_stocklocations')->where('sl_code', $slCodeAsal)->exists()) {
                    throw new \RuntimeException("Lokasi stok {$slCodeAsal} tidak terdaftar di Master Lokasi Stok.");
                }

                $rowsReturn = DB::table('rstxn_riobatrtns')
                    ->where('rihdr_no', $this->riHdrNo)
                    ->whereNull('trf_no_apotek')
                    ->get();
                if ($rowsReturn->isEmpty()) {
                    throw new \RuntimeException('Tidak ada return yang perlu dikirim.');
                }

                // Satu baris detail per produk — modul Transfer Stok juga menolak produk ganda.
                $qtyPerProduk = $rowsReturn->groupBy('product_id')->map(fn($rowsProduk) => $rowsProduk->sum('riobat_qty'));
                $empId = auth()->user()->emp_id ?? 'SYSTEM';

                $trfNo = [];
                foreach (['gudang' => [$slCodeAsal, self::GUDANG_SL_CODE], 'apotek' => [self::GUDANG_SL_CODE, self::APOTEK_SL_CODE]] as $tujuan => [$slCodeFrom, $slCodeTo]) {
                    $trfNo[$tujuan] = (int) DB::table('imtxn_trfhdrs')->max('trf_no') + 1;
                    DB::table('imtxn_trfhdrs')->insert([
                        'trf_no' => $trfNo[$tujuan],
                        'trf_date' => DB::raw('SYSDATE'),
                        'sl_codefrom' => $slCodeFrom,
                        'sl_codeto' => $slCodeTo,
                        'trf_status' => self::STATUS_TRANSFER_POSTED,
                        'emp_id' => $empId,
                    ]);

                    $trfDtl = (int) DB::table('imtxn_trfdtls')->max('trf_dtl');
                    foreach ($qtyPerProduk as $productId => $qty) {
                        DB::table('imtxn_trfdtls')->insert([
                            'trf_no' => $trfNo[$tujuan],
                            'trf_dtl' => ++$trfDtl,
                            'product_id' => $productId,
                            'qty' => $qty,
                        ]);
                    }
                }

                DB::table('rstxn_riobatrtns')
                    ->whereIn('riobat_no', $rowsReturn->pluck('riobat_no')->all())
                    ->update(['trf_no_gudang' => $trfNo['gudang'], 'trf_no_apotek' => $trfNo['apotek']]);

                $this->appendAdminLogRI($this->riHdrNo, "Kirim Return Obat ke Apotek: Transfer #{$trfNo['gudang']} ({$slCodeAsal} {$this->slNameAsal} → Gudang) & #{$trfNo['apotek']} (Gudang → Apotek)");
            });

            $this->findData($this->riHdrNo);
            $this->tutupKirimReturn();
            $this->dispatch('toast', type: 'success', message: 'Return obat dikirim ke Apotek.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim return: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BATAL KIRIM — pembalik kirimReturnKeApotek: transfernya dibatalkan (F) supaya
     | ledger tidak menghitungnya lagi, lalu tanda kirim di baris return dikosongkan.
     =============================== */
    public function batalKirimReturn(): void
    {
        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $rowsReturn = DB::table('rstxn_riobatrtns')
                    ->where('rihdr_no', $this->riHdrNo)
                    ->whereNotNull('trf_no_apotek')
                    ->get();
                if ($rowsReturn->isEmpty()) {
                    throw new \RuntimeException('Tidak ada return yang sudah dikirim.');
                }

                $daftarTrfNo = $rowsReturn->pluck('trf_no_gudang')->merge($rowsReturn->pluck('trf_no_apotek'))->unique()->values()->all();

                DB::table('imtxn_trfhdrs')->whereIn('trf_no', $daftarTrfNo)->update(['trf_status' => self::STATUS_TRANSFER_BATAL]);
                DB::table('rstxn_riobatrtns')
                    ->whereIn('riobat_no', $rowsReturn->pluck('riobat_no')->all())
                    ->update(['trf_no_gudang' => null, 'trf_no_apotek' => null]);

                $this->appendAdminLogRI($this->riHdrNo, 'Batal Kirim Return Obat ke Apotek: Transfer #' . implode(', #', $daftarTrfNo) . ' dibatalkan');
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('toast', type: 'success', message: 'Kirim return dibatalkan — transfer stok dibatalkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membatalkan kirim: ' . $e->getMessage());
        }
    }

    public function refreshRiobatDate(): void
    {
        $this->formEntry['riobatDate'] = $this->nowFormatted();
    }

    public function resetFormEntry(): void
    {
        $this->reset(['formEntry']);
        $this->formEntry['riobatDate'] = $this->nowFormatted();
        $this->formEntry['productQty'] = '1';
        $this->resetValidation();
        $this->incrementVersion('modal-rtn-obat-ri');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-rtn-obat-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — transaksi terkunci.
        </div>
    @endif

    {{-- Kiri: form entri · Kanan: daftar data --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 items-start">
        @if (!$isFormLocked)
            <div class="p-4 border border-hairline rounded-2xl dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40"
                x-data
                x-on:focus-input-rtn-qty.window="$nextTick(() => $refs.inputRtnQty?.focus())"
                x-on:focus-lov-rtn-obat-ri.window="$nextTick(() => {
                const fokus = () => {
                    const el = $refs.lovRtnObat?.querySelector('input');
                    if (!el || el === document.activeElement) return;
                    if (document.activeElement?.matches('input, select, textarea')) return;
                    el.focus();
                };
                fokus();
                setTimeout(fokus, 150);
            })">

                @if (empty($formEntry['productId']))
                    {{-- Enter saat kolom cari masih kosong = selesai di tab ini → lompat ke tab berikutnya. --}}
                <div x-ref="lovRtnObat"
                    x-on:keydown.enter="if (!$event.target.value?.trim()) $dispatch('administrasi-ri-goto-tab', { tab: 'RiObatPinjam', focus: 'focus-lov-obat-pinjam-ri' })">
                        <livewire:lov.product.lov-product target="product-rtn-obat-ri" label="Produk / Obat"
                            placeholder="Ketik kode/nama produk..."
                            wire:key="lov-product-rtn-{{ $riHdrNo }}-{{ $renderVersions['modal-rtn-obat-ri'] ?? 0 }}" />
                    </div>
                @else
                    <div class="grid grid-cols-12 gap-3 items-end">
                        {{-- Tanggal --}}
                        <div class="col-span-2">
                            <x-input-label value="Tanggal" class="mb-1" />
                            <div class="flex gap-1">
                                <x-text-input wire:model="formEntry.riobatDate" placeholder="dd/mm/yyyy hh:mm:ss"
                                    class="flex-1 text-sm font-mono min-w-0" />
                                <button type="button" wire:click="refreshRiobatDate" title="Waktu sekarang"
                                    class="shrink-0 px-2 text-muted-soft hover:text-blue-500 dark:hover:text-blue-400 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        {{-- Kode --}}
                        <div class="col-span-1">
                            <x-input-label value="Kode" class="mb-1" />
                            <x-text-input wire:model="formEntry.productId" disabled class="w-full text-sm" />
                        </div>
                        {{-- Produk --}}
                        <div class="col-span-3">
                            <x-input-label value="Produk" class="mb-1" />
                            <x-text-input wire:model="formEntry.productName" disabled class="w-full text-sm" />
                        </div>
                        {{-- Harga --}}
                        <div class="col-span-2">
                            <x-input-label value="Harga" class="mb-1" />
                            <x-text-input-number wire:model="formEntry.productPrice"
                                x-on:keydown.enter.prevent="$nextTick(() => $refs.inputRtnQty?.focus())" />
                            @error('formEntry.productPrice') <x-input-error :messages="$message" class="mt-1" /> @enderror
                        </div>
                        {{-- Qty --}}
                        <div class="col-span-2">
                            <x-input-label value="Qty" class="mb-1" />
                            <x-text-input-number wire:model="formEntry.productQty"
                                placeholder="Qty"
                                x-ref="inputRtnQty"
                                x-on:keydown.enter.prevent="$el.blur(); $wire.insertRtnObat()" />
                            @error('formEntry.productQty') <x-input-error :messages="$message" class="mt-1" /> @enderror
                        </div>
                        {{-- Buttons --}}
                        <div class="col-span-2 flex gap-2 items-end">
                            <x-icon-button color="gray" type="button" wire:click.prevent="resetFormEntry"
                                title="Batal — kosongkan form entri">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </x-icon-button>
                        </div>
                        {{-- Petunjuk cara simpan — tombol Simpan/Tambah ditiadakan --}}
                        <p class="mt-3 text-xs text-muted dark:text-gray-400">
                            Tekan <span class="px-1.5 py-0.5 font-semibold rounded border border-hairline bg-canvas text-body dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Enter</span>
                            di kolom terakhir untuk menyimpan.
                        </p>
                    </div>
                @endif
            </div>
        @endif

        <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
                <h3 class="text-sm font-semibold text-body dark:text-gray-300">Return Obat RI</h3>
                <div class="flex items-center gap-2">
                    <x-badge variant="gray">{{ count($dataRtnObat) }} item</x-badge>
                    @php
                        $jumlahBelumDikirim = collect($dataRtnObat)->whereNull('trf_no_apotek')->count();
                        $jumlahSudahDikirim = count($dataRtnObat) - $jumlahBelumDikirim;
                    @endphp
                    @if ($jumlahSudahDikirim > 0)
                        <x-confirm-button variant="warning-soft" action="batalKirimReturn()" title="Batal Kirim Return"
                            message="Transfer stok return pasien ini dibatalkan, stok kembali seperti sebelum dikirim. Lanjutkan?"
                            confirmText="Ya, batalkan">
                            Batal Kirim
                        </x-confirm-button>
                    @endif
                    @if ($jumlahBelumDikirim > 0)
                        <x-primary-button type="button" wire:click="bukaKirimReturn" :disabled="!$isFormLocked">
                            Kirim Return ke Apotek
                        </x-primary-button>
                        @if (!$isFormLocked)
                            <span class="text-xs text-muted dark:text-gray-400">aktif setelah transaksi RI selesai</span>
                        @endif
                    @endif
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300 bg-surface-soft dark:bg-gray-800/50">
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Produk</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Harga</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                            <th class="px-4 py-3">Transfer</th>
                            @if (!$isFormLocked) <th class="w-20 px-4 py-3 text-center">Hapus</th> @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                        @forelse ($dataRtnObat as $item)
                            <tr wire:key="rtn-obat-ri-{{ $item['riobat_no'] ?? $loop->index }}" class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                                <td class="px-4 py-1.5 font-mono text-sm text-muted whitespace-nowrap">{{ $item['riobat_date'] ?? '-' }}</td>
                                <td class="px-4 py-1.5 text-ink dark:text-gray-200 whitespace-nowrap">{{ $item['product_name'] ?? $item['product_id'] }}</td>
                                <td class="px-4 py-1.5 text-right text-body dark:text-gray-300">{{ $item['riobat_qty'] ?? 0 }}</td>
                                <td class="px-4 py-1.5 text-right text-body dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['riobat_price'] ?? 0) }}</td>
                                <td class="px-4 py-1.5 font-semibold text-right text-red-600 dark:text-red-400 whitespace-nowrap">
                                    -Rp {{ number_format(($item['riobat_qty'] ?? 0) * ($item['riobat_price'] ?? 0)) }}
                                </td>
                                <td class="px-4 py-1.5 whitespace-nowrap">
                                    @if (!empty($item['trf_no_apotek']))
                                        <x-badge variant="success">Terkirim</x-badge>
                                        <span class="ml-1 font-mono text-xs text-muted">#{{ $item['trf_no_gudang'] }} → #{{ $item['trf_no_apotek'] }}</span>
                                    @else
                                        <x-badge variant="warning">Belum dikirim</x-badge>
                                    @endif
                                </td>
                                @if (!$isFormLocked)
                                    <td class="px-4 py-1.5 text-center">
                                        @if (empty($item['trf_no_apotek']))
                                            <x-hapus-button wire:click.prevent="removeRtnObat({{ $item['riobat_no'] }})" confirm="Hapus return obat ini?" />
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isFormLocked ? 6 : 7 }}" class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                    Belum ada return obat
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if (!empty($dataRtnObat))
                        <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-sm font-semibold text-muted dark:text-gray-400">Total Return</td>
                                <td class="px-4 py-3 text-sm font-bold text-right text-red-600 dark:text-red-400">
                                    -Rp {{ number_format(collect($dataRtnObat)->sum(fn($i) => ($i['riobat_qty'] ?? 0) * ($i['riobat_price'] ?? 0))) }}
                                </td>
                                <td></td>
                                @if (!$isFormLocked) <td></td> @endif
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
    {{-- MODAL KIRIM — pilih ruangan asal stok, lalu dua transfer: ruangan → Gudang → Apotek --}}
    <x-modal name="kirim-return-obat-ri" size="2xl" focusable>
        <x-modul-dokumen.header judul="Kirim Return ke Apotek" ikon="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4">
            Stok return dipindah lewat dua transfer: ruangan asal → Gudang Medis (04) → Apotek (02).
        </x-modul-dokumen.header>
        <div class="p-6 space-y-5" wire:key="{{ $this->renderKey('modal-kirim-return-ri', [$riHdrNo ?? 'new']) }}">
            <livewire:lov.stocklocation.lov-stocklocation target="asal-return-ri" label="Ruangan asal stok"
                placeholder="Ketik kode / nama ruangan..." :initialSlCode="$slCodeAsal"
                :excludeSlCode="[self::GUDANG_SL_CODE, self::APOTEK_SL_CODE]" jenisLokasi="medis"
                wire:key="lov-asal-return-{{ $this->renderKey('modal-kirim-return-ri', [$riHdrNo ?? 'new']) }}" />

            @php $rowsBelumDikirim = collect($dataRtnObat)->whereNull('trf_no_apotek'); @endphp
            <div class="overflow-hidden border border-hairline rounded-xl dark:border-gray-700">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs font-semibold text-gray-600 uppercase bg-surface-soft dark:bg-gray-800/50 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-2">Produk</th>
                            <th class="px-4 py-2 text-right">Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                        @foreach ($rowsBelumDikirim->groupBy('product_id') as $rowsProduk)
                            <tr>
                                <td class="px-4 py-1.5 text-ink dark:text-gray-200">{{ $rowsProduk->first()['product_name'] ?? $rowsProduk->first()['product_id'] }}</td>
                                <td class="px-4 py-1.5 text-right text-body dark:text-gray-300">{{ $rowsProduk->sum('riobat_qty') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-hairline-soft dark:border-gray-800">
                <x-secondary-button type="button" wire:click="tutupKirimReturn">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click.prevent="kirimReturnKeApotek" wire:loading.attr="disabled"
                    wire:target="kirimReturnKeApotek" :disabled="!$slCodeAsal">
                    <span wire:loading.remove wire:target="kirimReturnKeApotek">Kirim ke Apotek</span>
                    <span wire:loading wire:target="kirimReturnKeApotek"><x-loading class="w-4 h-4" /></span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
