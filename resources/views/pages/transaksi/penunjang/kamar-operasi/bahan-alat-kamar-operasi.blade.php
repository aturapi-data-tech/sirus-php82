<?php
// resources/views/pages/transaksi/penunjang/kamar-operasi/bahan-alat-kamar-operasi.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\Txn\Penunjang\KamarOperasiTrait;
use App\Support\KamarOperasiTarif;

/**
 * Tab Bahan dan Alat (`rstxn_okobats`).
 *
 * Total (qty x harga) membentuk pos `equipment_fee` — ditagihkan ke pasien.
 * Tiap tambah/hapus memanggil KamarOperasiTarif::hitungUlang().
 */
new class extends Component {
    use KamarOperasiTrait;

    public string $okReg = '';
    public bool $isFormLocked = true;
    public int $riHdrNo = 0;

    public array $rows = [];

    /* ── Form tambah ── */
    public ?string $formProductId = null;
    public string $formProductName = '';
    public string $formQty = '1';
    public ?int $formHarga = null;

    public function mount(string $okReg = ''): void
    {
        $this->okReg = $okReg;
        $this->findData();
    }

    #[On('kamar-operasi.updated')]
    public function findData(): void
    {
        if ($this->okReg === '') {
            $this->rows = [];
            return;
        }

        $this->isFormLocked = $this->statusOk($this->okReg) !== 'A';
        $this->riHdrNo = $this->riHdrNoOk($this->okReg);

        $this->rows = DB::table('rstxn_okobats as t')
            ->leftJoin('immst_products as p', 'p.product_id', '=', 't.product_id')
            ->select('t.okobat_id', 't.product_id', 'p.product_name', 't.okobat_qty', 't.okobat_price')
            ->where('t.ok_reg', $this->okReg)
            ->orderBy('t.okobat_id')
            ->get()
            ->map(fn($bahanAlat) => (array) $bahanAlat)
            ->toArray();
    }

    private function resetForm(): void
    {
        $this->reset(['formProductId', 'formProductName', 'formHarga']);
        $this->formQty = '1';
    }

    private function bolehUbah(): bool
    {
        if (!$this->isAllowedRoleOk()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses.');
            return false;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi sudah selesai/dibatalkan — data tidak bisa diubah.');
            $this->findData();
            return false;
        }

        return true;
    }

    #[On('lov.selected.kamar-operasi-produk')]
    public function pilihProduk($target = null, $payload = null): void
    {
        $this->formProductId = $payload['product_id'] ?? null;
        $this->formProductName = $payload['product_name'] ?? '';
        $this->formHarga = isset($payload['sales_price']) ? (int) $payload['sales_price'] : null;

        $this->dispatch('kamar-operasi-fokus', ke: 'ok-qty-bahan');
    }

    public function tambah(): void
    {
        if (!$this->bolehUbah()) {
            return;
        }

        if (empty($this->formProductId)) {
            $this->dispatch('toast', type: 'error', message: 'Pilih bahan/alat terlebih dahulu.');
            return;
        }

        $validator = Validator::make(
            ['qty' => str_replace(['.', ',', ' '], '', trim($this->formQty)), 'harga' => $this->formHarga],
            ['qty' => 'bail|required|integer|min:1|max:99999', 'harga' => 'bail|required|integer|min:0|max:999999999'],
            ['qty.required' => 'Qty wajib diisi.', 'qty.integer' => 'Qty harus angka bulat.', 'qty.min' => 'Qty minimal 1.', 'harga.required' => 'Harga wajib diisi.', 'harga.integer' => 'Harga harus angka bulat.'],
        );

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first());
            return;
        }

        $riHdrNo = $this->riHdrNo;
        $productId = $this->formProductId;
        $productName = $this->formProductName;
        $qty = (int) str_replace(['.', ',', ' '], '', trim($this->formQty));
        $harga = (int) $this->formHarga;
        $totalBaru = 0;

        $berhasil = $this->jalankanDenganRetryOk(function () use ($riHdrNo, $productId, $productName, $qty, $harga, &$totalBaru) {
            $row = $this->kunciBarisOk($this->okReg);

            $nomor = (int) DB::scalar('SELECT NVL(MAX(okobat_id),0) + 1 FROM rstxn_okobats');

            DB::table('rstxn_okobats')->insert(['okobat_id' => $nomor, 'product_id' => $productId, 'okobat_qty' => $qty, 'okobat_price' => $harga, 'ok_reg' => $this->okReg]);

            [, $totalBaru] = KamarOperasiTarif::hitungUlang($this->okReg, $row);

            $this->catatLogOk($riHdrNo, "Tambah bahan/alat OK No.{$this->okReg} — {$productId} {$productName} {$qty} x Rp " . number_format($harga) . '. Total Rp ' . number_format($totalBaru));
        }, 'Gagal menambah bahan/alat');

        if (!$berhasil) {
            return;
        }

        $this->resetForm();
        $this->findData();
        $this->dispatch('kamar-operasi.updated');
        $this->dispatch('toast', type: 'success', message: 'Bahan/alat ditambahkan — total Rp ' . number_format($totalBaru) . '.');
        $this->dispatch('kamar-operasi-fokus', ke: 'ok-lov-bahan');
    }

    public function hapus(int $okobatId): void
    {
        if (!$this->bolehUbah()) {
            return;
        }

        $riHdrNo = $this->riHdrNo;
        $totalBaru = 0;

        $berhasil = $this->jalankanDenganRetryOk(function () use ($riHdrNo, $okobatId, &$totalBaru) {
            $row = $this->kunciBarisOk($this->okReg);

            $baris = DB::table('rstxn_okobats')->where('okobat_id', $okobatId)->where('ok_reg', $this->okReg)->first();

            if (!$baris) {
                throw new \RuntimeException('Baris bahan/alat tidak ditemukan.');
            }

            DB::table('rstxn_okobats')->where('okobat_id', $okobatId)->where('ok_reg', $this->okReg)->delete();

            [, $totalBaru] = KamarOperasiTarif::hitungUlang($this->okReg, $row);

            $this->catatLogOk($riHdrNo, "Hapus bahan/alat OK No.{$this->okReg} — {$baris->product_id} " . (int) $baris->okobat_qty . ' x Rp ' . number_format((int) $baris->okobat_price) . '. Total Rp ' . number_format($totalBaru));
        }, 'Gagal menghapus bahan/alat');

        if (!$berhasil) {
            return;
        }

        $this->findData();
        $this->dispatch('kamar-operasi.updated');
        $this->dispatch('toast', type: 'success', message: 'Bahan/alat dihapus — total Rp ' . number_format($totalBaru) . '.');
    }
};
?>

<div>
    <p class="mb-3 text-xs text-muted dark:text-gray-400">
        Total di sini membentuk pos <span class="font-semibold">Bahan &amp; Alat</span> — ditagihkan ke pasien.
    </p>

    @unless ($isFormLocked)
        <div class="grid grid-cols-1 gap-3 p-3 mb-4 border rounded-xl border-hairline dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40 lg:grid-cols-12">
            {{-- Enter saat kolom cari masih kosong = selesai di tab ini. --}}
            <div class="lg:col-span-5" id="ok-lov-bahan"
                x-on:keydown.enter="if (!$event.target.value?.trim()) $dispatch('kamar-operasi-lanjut-tab', { ke: 'Omlop' })">
                <livewire:lov.product.lov-product target="kamar-operasi-produk" label="Bahan / Alat"
                    :initialProductId="$formProductId" wire:key="lov-produk-{{ $okReg }}-{{ $formProductId }}" />
            </div>
            <div class="lg:col-span-2">
                <x-input-label value="Qty" />
                <x-text-input-number id="ok-qty-bahan" wire:model="formQty" placeholder="1"
                    x-on:keydown.enter.prevent="$el.blur(); $refs.hargaBahanAlat?.focus()" />
            </div>
            <div class="lg:col-span-3">
                <x-input-label value="Harga" />
                {{-- Enter: ada isi -> simpan; kosong -> pindah tab berikutnya. --}}
                <x-text-input-number wire:model="formHarga" placeholder="0" x-ref="hargaBahanAlat"
                    x-on:keydown.enter.prevent="
                        const kosong = $el.value.replace(/\D/g, '') === '';
                        $el.blur();
                        kosong ? $dispatch('kamar-operasi-lanjut-tab', { ke: 'Omlop' }) : $wire.tambah()
                    " />
            </div>
            <div class="flex items-end lg:col-span-2">
                <x-primary-button type="button" wire:click="tambah" wire:loading.attr="disabled"
                    wire:target="tambah" class="justify-center w-full text-xs">
                    <span wire:loading.remove wire:target="tambah">Tambah</span>
                    <span wire:loading wire:target="tambah" class="flex items-center gap-1">
                        <x-loading /> ...
                    </span>
                </x-primary-button>
            </div>
        </div>
    @endunless

    <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300 bg-surface-soft dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Nama Bahan / Alat</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Harga</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        @unless ($isFormLocked)
                            <th class="px-4 py-3 text-center">Aksi</th>
                        @endunless
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                    @forelse ($rows as $row)
                        <tr wire:key="bahan-alat-{{ $row['okobat_id'] }}" class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                            <td class="px-4 py-1.5 font-mono text-muted">{{ $row['product_id'] ?? '-' }}</td>
                            <td class="px-4 py-1.5 text-ink dark:text-gray-200">{{ $row['product_name'] ?? '-' }}</td>
                            <td class="px-4 py-1.5 text-right text-ink dark:text-gray-200 tabular-nums">{{ number_format($row['okobat_qty'] ?? 0) }}</td>
                            <td class="px-4 py-1.5 text-right text-ink dark:text-gray-200 tabular-nums">Rp {{ number_format($row['okobat_price'] ?? 0) }}</td>
                            <td class="px-4 py-1.5 font-semibold text-right text-ink dark:text-gray-200 tabular-nums">
                                Rp {{ number_format((int) ($row['okobat_qty'] ?? 0) * (int) ($row['okobat_price'] ?? 0)) }}
                            </td>
                            @unless ($isFormLocked)
                                <td class="px-4 py-1.5 text-center">
                                    <x-confirm-button variant="danger" action="hapus({{ $row['okobat_id'] }})"
                                        title="Hapus Bahan/Alat" message="Hapus baris bahan/alat ini? Pos Bahan &amp; Alat akan dihitung ulang."
                                        confirmText="Ya, hapus" cancelText="Batal" class="!px-2 !py-1 text-xs">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </x-confirm-button>
                                </td>
                            @endunless
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-muted-soft">Belum ada bahan dan alat</td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($rows))
                    <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-sm font-semibold text-muted dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-ink dark:text-white">
                                Rp {{ number_format(collect($rows)->sum(fn($bahanAlat) => (int) ($bahanAlat['okobat_qty'] ?? 0) * (int) ($bahanAlat['okobat_price'] ?? 0))) }}
                            </td>
                            @unless ($isFormLocked)
                                <td></td>
                            @endunless
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
