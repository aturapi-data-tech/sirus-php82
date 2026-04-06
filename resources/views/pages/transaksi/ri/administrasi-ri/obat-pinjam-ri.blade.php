<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-obat-pinjam-ri'];

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataDaftarRI = [];

    public array $formEntry = [
        'productId'    => '',
        'productName'  => '',
        'productPrice' => '',
        'productQty'   => '1',
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);

        if ($this->riHdrNo) {
            $this->findData($this->riHdrNo);
        } else {
            $this->dataDaftarRI['RiObatPinjam'] = [];
        }
    }

    private function findData(int $riHdrNo): void
    {
        $rows = DB::table('rstxn_riobats')
            ->join('immst_products', 'immst_products.product_id', '=', 'rstxn_riobats.product_id')
            ->select(
                DB::raw("to_char(riobat_date, 'dd/mm/yyyy hh24:mi:ss') as riobat_date"),
                'rstxn_riobats.product_id',
                'immst_products.product_name',
                'rstxn_riobats.riobat_qty',
                'rstxn_riobats.riobat_price',
                'rstxn_riobats.riobat_no',
            )
            ->where('rstxn_riobats.rihdr_no', $riHdrNo)
            ->orderBy('riobat_date')
            ->get();

        $this->dataDaftarRI['RiObatPinjam'] = $rows->map(fn($r) => (array) $r)->toArray();
    }

    /* ===============================
     | LOV SELECTED — PRODUCT
     =============================== */
    #[On('lov.selected.product-obat-pinjam-ri')]
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
        $this->formEntry['productPrice'] = $payload['sales_price'] ?? 0;

        $this->dispatch('focus-input-obat-qty');
    }

    /* ===============================
     | INSERT
     =============================== */
    public function insertObatPinjam(): void
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

                $last = DB::table('rstxn_riobats')
                    ->select(DB::raw("nvl(max(riobat_no)+1,1) as riobat_no_max"))
                    ->first();

                DB::table('rstxn_riobats')->insert([
                    'riobat_no'    => $last->riobat_no_max,
                    'rihdr_no'     => $this->riHdrNo,
                    'product_id'   => $this->formEntry['productId'],
                    'riobat_date'  => DB::raw("sysdate"),
                    'riobat_price' => $this->formEntry['productPrice'],
                    'riobat_qty'   => $this->formEntry['productQty'],
                ]);
                $this->appendAdminLog($this->riHdrNo, 'Tambah Obat Pinjam: ' . $this->formEntry['productName']);
            });

            $this->resetFormEntry();
            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Obat pinjam berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE
     =============================== */
    public function removeObatPinjam(int $riobatNo): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($riobatNo) {
                $this->lockRIRow($this->riHdrNo);
                DB::table('rstxn_riobats')->where('riobat_no', $riobatNo)->delete();
                $this->appendAdminLog($this->riHdrNo, 'Hapus Obat Pinjam #' . $riobatNo);
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Obat pinjam berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function resetFormEntry(): void
    {
        $this->reset(['formEntry']);
        $this->formEntry['productQty'] = '1';
        $this->resetValidation();
        $this->incrementVersion('modal-obat-pinjam-ri');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-obat-pinjam-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — transaksi terkunci.
        </div>
    @endif

    @if (!$isFormLocked)
        <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40"
            x-data
            x-on:focus-input-obat-qty.window="$nextTick(() => $refs.inputObatQty?.focus())">

            @if (empty($formEntry['productId']))
                <div class="w-80">
                    <livewire:lov.product.lov-product target="product-obat-pinjam-ri" label="Produk / Obat"
                        placeholder="Ketik kode/nama produk..."
                        wire:key="lov-product-{{ $riHdrNo }}-{{ $renderVersions['modal-obat-pinjam-ri'] ?? 0 }}" />
                </div>
            @else
                <div class="flex items-end gap-3">
                    <div class="w-28">
                        <x-input-label value="Kode" class="mb-1" />
                        <x-text-input wire:model="formEntry.productId" disabled class="w-full text-sm" />
                    </div>
                    <div class="flex-1">
                        <x-input-label value="Produk" class="mb-1" />
                        <x-text-input wire:model="formEntry.productName" disabled class="w-full text-sm" />
                    </div>
                    <div class="w-32">
                        <x-input-label value="Harga" class="mb-1" />
                        <x-text-input wire:model="formEntry.productPrice" class="w-full text-sm"
                            x-on:keyup.enter="$refs.inputObatQty?.focus()" />
                        @error('formEntry.productPrice') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    <div class="w-24">
                        <x-input-label value="Qty" class="mb-1" />
                        <x-text-input wire:model="formEntry.productQty" placeholder="Qty" class="w-full text-sm"
                            x-ref="inputObatQty"
                            x-on:keyup.enter="$wire.insertObatPinjam()" />
                        @error('formEntry.productQty') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    <div class="flex gap-2 pb-0.5">
                        <button type="button" wire:click.prevent="insertObatPinjam" wire:loading.attr="disabled"
                            wire:target="insertObatPinjam"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-brand-green hover:bg-brand-green/90 disabled:opacity-60 dark:bg-brand-lime dark:text-gray-900 transition shadow-sm">
                            <span wire:loading.remove wire:target="insertObatPinjam">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </span>
                            <span wire:loading wire:target="insertObatPinjam"><x-loading class="w-4 h-4" /></span>
                            Tambah
                        </button>
                        <button type="button" wire:click.prevent="resetFormEntry"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Batal
                        </button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Obat Pinjam</h3>
            <x-badge variant="gray">{{ count($dataDaftarRI['RiObatPinjam'] ?? []) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Harga</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        @if (!$isFormLocked) <th class="w-20 px-4 py-3 text-center">Hapus</th> @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($dataDaftarRI['RiObatPinjam'] ?? [] as $item)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $item['riobat_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">{{ $item['product_name'] ?? $item['product_id'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $item['riobat_qty'] ?? 0 }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['riobat_price'] ?? 0) }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format(($item['riobat_qty'] ?? 0) * ($item['riobat_price'] ?? 0)) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                        wire:click.prevent="removeObatPinjam({{ $item['riobat_no'] }})"
                                        wire:confirm="Hapus obat pinjam ini?" wire:loading.attr="disabled"
                                        wire:target="removeObatPinjam({{ $item['riobat_no'] }})"
                                        class="inline-flex items-center justify-center w-8 h-8 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isFormLocked ? 5 : 6 }}"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Belum ada obat pinjam
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataDaftarRI['RiObatPinjam']))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($dataDaftarRI['RiObatPinjam'])->sum(fn($i) => ($i['riobat_qty'] ?? 0) * ($i['riobat_price'] ?? 0))) }}
                            </td>
                            @if (!$isFormLocked) <td></td> @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
