<?php
// resources/views/pages/master/master-automatic-stop-order/master-automatic-stop-order.blade.php
//
// LIST master Automatic Stop Order. Dua tampilan dalam satu halaman:
//   golongan → RSMST_STOP_ORDER_GOLONGANS (batas hari per golongan + jumlah obat terpetakan)
//   produk   → RSMST_STOP_ORDER_PRODUCTS  (obat mana masuk golongan mana), bisa disaring per golongan
// Semua tulis DB ada di -actions (golongan) / -produk-actions (pemetaan obat).
// Pola halaman meniru /master/ews.

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int    $itemsPerPage  = 10;
    public string $tampilan      = 'golongan'; // golongan | produk
    public int    $filterGolonganId   = 0;          // 0 = semua golongan (tab produk)

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }
    public function updatedFilterGolonganId(): void   { $this->resetPage(); }

    public function setTampilan(string $tampilan): void
    {
        if (!in_array($tampilan, ['golongan', 'produk'], true)) {
            return;
        }
        $this->tampilan = $tampilan;
        $this->resetPage();
    }

    /** Dari tab golongan: klik jumlah obat → lompat ke tab produk tersaring golongan itu. */
    public function lihatProdukGolongan(int $golonganId): void
    {
        $this->filterGolonganId = $golonganId;
        $this->tampilan = 'produk';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterGolonganId']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    // ── Golongan ──
    public function openCreate(): void                { $this->dispatch('master.automatic-stop-order.openCreate'); }
    public function openEdit(int $golonganId): void        { $this->dispatch('master.automatic-stop-order.openEdit', golonganId: $golonganId); }
    public function requestDelete(int $golonganId): void   { $this->dispatch('master.automatic-stop-order.requestDelete', golonganId: $golonganId); }
    public function toggleActive(int $golonganId): void    { $this->dispatch('master.automatic-stop-order.toggleActive', golonganId: $golonganId); }

    // ── Pemetaan obat ──
    public function openCreateProduk(): void                    { $this->dispatch('master.automatic-stop-order.openCreateProduk', golonganId: $this->filterGolonganId); }
    public function openEditProduk(string $productId): void     { $this->dispatch('master.automatic-stop-order.openEditProduk', productId: $productId); }
    public function requestDeleteProduk(string $productId): void { $this->dispatch('master.automatic-stop-order.requestDeleteProduk', productId: $productId); }
    public function toggleActiveProduk(string $productId): void { $this->dispatch('master.automatic-stop-order.toggleActiveProduk', productId: $productId); }

    #[On('master.automatic-stop-order.saved')]
    public function refreshAfterSaved(): void
    {
        unset($this->golonganOptions);
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        return $this->tampilan === 'produk' ? $this->rowsProduk() : $this->rowsGolongan();
    }

    /** Dropdown filter golongan (tab produk) + dipakai modal. Di-cache per request. */
    #[Computed]
    public function golonganOptions(): array
    {
        return DB::table('rsmst_stop_order_golongans')
            ->select('golongan_id', 'golongan_nama', 'batas_hari', 'active_status')
            ->orderBy('urutan')->orderBy('golongan_id')
            ->get()
            ->map(fn($golongan) => ['golongan_id' => (int) $golongan->golongan_id, 'golongan_nama' => (string) $golongan->golongan_nama, 'batas_hari' => (int) $golongan->batas_hari, 'active_status' => (string) $golongan->active_status])
            ->all();
    }

    private function rowsGolongan()
    {
        $q = DB::table('rsmst_stop_order_golongans as golongan')
            ->select('golongan.golongan_id', 'golongan.golongan_kode', 'golongan.golongan_nama', 'golongan.batas_hari', 'golongan.batas_minimal_hari', 'golongan.keterangan', 'golongan.urutan', 'golongan.active_status')
            ->selectRaw('(select count(*) from rsmst_stop_order_products pemetaan where pemetaan.golongan_id = golongan.golongan_id) as jumlah_obat')
            ->orderBy('golongan.urutan')->orderBy('golongan.golongan_id');

        if (trim($this->searchKeyword) !== '') {
            $keyword = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($subQuery) use ($keyword) {
                $subQuery->whereRaw('UPPER(golongan.golongan_kode) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(golongan.golongan_nama) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(golongan.keterangan) LIKE ?', ["%{$keyword}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }

    private function rowsProduk()
    {
        // immst_products milik Oracle Dev 6i — leftJoin supaya pemetaan ke obat yang
        // sudah dihapus dari master tetap terlihat (nama tampil '-') dan bisa dibersihkan.
        $q = DB::table('rsmst_stop_order_products as pemetaan')
            ->leftJoin('immst_products as produk', 'produk.product_id', '=', 'pemetaan.product_id')
            ->join('rsmst_stop_order_golongans as golongan', 'golongan.golongan_id', '=', 'pemetaan.golongan_id')
            ->select('pemetaan.product_id', 'pemetaan.golongan_id', 'pemetaan.catatan', 'pemetaan.active_status', 'produk.product_name', 'golongan.golongan_nama', 'golongan.batas_hari')
            ->orderBy('golongan.urutan')->orderBy('produk.product_name');

        if ($this->filterGolonganId > 0) {
            $q->where('pemetaan.golongan_id', $this->filterGolonganId);
        }

        if (trim($this->searchKeyword) !== '') {
            $keyword = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($subQuery) use ($keyword) {
                $subQuery->whereRaw('UPPER(pemetaan.product_id) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(produk.product_name) LIKE ?', ["%{$keyword}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    <x-page-title
        title="Master Automatic Stop Order"
        subtitle="Automatic Stop Order — golongan obat, batas hari order, dan pemetaan obat ke golongan" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div>
                            <x-input-label value="Tampilan" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                            <x-tabs variant="pill">
                                <x-tab :active="$tampilan === 'golongan'" wire:click="setTampilan('golongan')">Golongan & Batas Hari</x-tab>
                                <x-tab :active="$tampilan === 'produk'" wire:click="setTampilan('produk')">Pemetaan Obat</x-tab>
                            </x-tabs>
                        </div>
                        @if ($tampilan === 'produk')
                            <div class="w-full sm:w-72">
                                <x-input-label for="filterGolonganId" value="Golongan" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                                <x-select-input id="filterGolonganId" wire:model.live="filterGolonganId" class="w-full">
                                    <option value="0">Semua golongan</option>
                                    @foreach ($this->golonganOptions as $golongan)
                                        <option value="{{ $golongan['golongan_id'] }}">{{ $golongan['golongan_nama'] }} ({{ $golongan['batas_hari'] }} hari)</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                        @endif
                        <div class="w-full sm:w-72">
                            <x-input-label for="searchKeyword" value="Cari" class="sr-only" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="{{ $tampilan === 'produk' ? 'Cari kode / nama obat...' : 'Cari kode / nama golongan...' }}"
                                class="block w-full" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        @if ($tampilan === 'produk')
                            <x-primary-button type="button" wire:click="openCreateProduk">+ Petakan Obat</x-primary-button>
                        @else
                            <x-primary-button type="button" wire:click="openCreate">+ Tambah Golongan</x-primary-button>
                        @endif
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            {{-- PANDUAN — gaya biru-info standar, default TERTUTUP. --}}
            <div x-data="{ buka: false }"
                class="mt-4 overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700 shrink-0">
                <button type="button" x-on:click="buka = !buka"
                    class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                    <span class="flex items-center min-w-0 gap-2">
                        <svg class="w-4 h-4 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="truncate">Panduan: apa itu Automatic Stop Order dan cara mengelola golongan &amp; pemetaan obat</span>
                    </span>
                    <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0"
                        x-bind:class="buka && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="buka" x-cloak class="px-4 pb-4 space-y-4 text-sm text-blue-900 dark:text-blue-100">
                    <div>
                        <div class="font-semibold">Automatic Stop Order</div>
                        <p class="mt-1">
                            Order obat golongan tertentu <span class="font-semibold">berhenti otomatis</span> setelah batas hari,
                            kecuali dokter mengkaji ulang pasien dan menulis order baru. Tujuannya keamanan pasien (obat yang
                            toksik bila menumpuk) dan pengendalian antimikroba (KPRA / KFT).
                        </p>
                    </div>

                    <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                        <div class="font-semibold">Golongan &amp; Batas Hari</div>
                        <ol class="mt-1 ml-4 space-y-1 list-decimal">
                            <li><span class="font-semibold">Kode</span> huruf besar tanpa spasi (mis. <span class="font-mono">KETOROLAK</span>); <span class="font-semibold">Batas stop</span> = hari ke berapa order berhenti; <span class="font-semibold">Batas minimal</span> (opsional) = lama pemberian minimal sebelum boleh dikaji / dihentikan.</li>
                            <li><span class="font-semibold">Keterangan</span> tampil ke dokter / apoteker saat obat menyentuh batas: syarat lanjut, dosis maksimal, dst.</li>
                            <li><span class="font-semibold">Aktif</span> dimatikan &mdash; seluruh obat di golongan itu tidak dipantau Automatic Stop Order tanpa perlu menghapus pemetaannya.</li>
                        </ol>
                    </div>

                    <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                        <div class="font-semibold">Pemetaan Obat</div>
                        <ol class="mt-1 ml-4 space-y-1 list-decimal">
                            <li>Obat dipetakan <span class="font-semibold">per kode obat</span> dari master obat, bukan ditebak dari nama &mdash; apoteker yang memutuskan.</li>
                            <li>Obat yang <span class="font-semibold">belum dipetakan diabaikan</span> Automatic Stop Order. Satu obat hanya masuk satu golongan.</li>
                            <li><span class="font-semibold">Catatan</span> per obat untuk syarat khusus (mis. "IV maksimal 120 mg/hari").</li>
                        </ol>
                    </div>

                    <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                        <div class="font-semibold">Catatan</div>
                        <ol class="mt-1 ml-4 space-y-1 list-decimal">
                            <li>Tulis teks dengan huruf biasa (<span class="font-mono">&lt;= 120 mg</span>) &mdash; simbol matematika dan tanda pisah panjang tidak bisa disimpan Oracle.</li>
                            <li>Kembali ke bawaan golongan: <span class="font-mono">php artisan automatic-stop-order:seed --force</span> (pemetaan obat ikut terhapus).</li>
                        </ol>
                    </div>
                </div>
            </div>

            {{-- TABLE WRAPPER --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    @if ($tampilan === 'produk')
                        <table class="ds-table">
                            <thead class="sticky top-0 z-10">
                                <tr>
                                    <th>Kode Obat</th>
                                    <th>Nama Obat</th>
                                    <th>Golongan</th>
                                    <th class="ds-c">Batas Hari</th>
                                    <th>Catatan</th>
                                    <th class="ds-c">Aktif</th>
                                    <th class="ds-c">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->rows as $row)
                                    <tr wire:key="automatic-stop-order-produk-{{ $row->product_id }}">
                                        <td class="ds-td-token">{{ $row->product_id }}</td>
                                        <td class="ds-td-strong">{{ $row->product_name ?: '-' }}</td>
                                        <td>{{ $row->golongan_nama }}</td>
                                        <td class="ds-c ds-td-token">{{ $row->batas_hari }}</td>
                                        <td class="max-w-md"><div class="text-sm line-clamp-2" title="{{ $row->catatan }}">{{ $row->catatan ?: '-' }}</div></td>
                                        <td class="ds-c">
                                            <x-toggle :current="$row->active_status" trueValue="1" falseValue="0"
                                                wireClick="toggleActiveProduk('{{ $row->product_id }}')" />
                                        </td>
                                        <td class="ds-c">
                                            <div class="flex justify-center gap-2">
                                                <x-action-edit wire:click="openEditProduk('{{ $row->product_id }}')" />
                                                <x-action-delete :action="'requestDeleteProduk(\'' . $row->product_id . '\')'"
                                                    title="Hapus Pemetaan Obat" message="Yakin hapus {{ $row->product_name ?: $row->product_id }} dari golongan {{ $row->golongan_nama }}?" />
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-10">
                                            <div class="flex flex-col items-center justify-center gap-3">
                                                <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada obat yang dipetakan{{ $filterGolonganId > 0 ? ' di golongan ini' : '' }}.</p>
                                                <p class="text-sm text-muted-soft">Klik <b>Petakan Obat</b>, pilih obat dari master obat, lalu tentukan golongannya.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @else
                        <table class="ds-table">
                            <thead class="sticky top-0 z-10">
                                <tr>
                                    <th class="ds-c">Urutan</th>
                                    <th>Kode</th>
                                    <th>Golongan</th>
                                    <th class="ds-c">Batas Minimal</th>
                                    <th class="ds-c">Batas Stop</th>
                                    <th>Keterangan</th>
                                    <th class="ds-c">Obat</th>
                                    <th class="ds-c">Aktif</th>
                                    <th class="ds-c">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->rows as $row)
                                    <tr wire:key="automatic-stop-order-golongan-{{ $row->golongan_id }}">
                                        <td class="ds-c ds-td-token">{{ $row->urutan }}</td>
                                        <td class="ds-td-token">{{ $row->golongan_kode }}</td>
                                        <td class="ds-td-strong">{{ $row->golongan_nama }}</td>
                                        <td class="ds-c">{{ $row->batas_minimal_hari !== null ? $row->batas_minimal_hari . ' hari' : '-' }}</td>
                                        <td class="ds-c"><x-badge variant="info">{{ $row->batas_hari }} hari</x-badge></td>
                                        <td class="max-w-md"><div class="text-sm line-clamp-2" title="{{ $row->keterangan }}">{{ $row->keterangan ?: '-' }}</div></td>
                                        <td class="ds-c">
                                            <button type="button" wire:click="lihatProdukGolongan({{ $row->golongan_id }})"
                                                class="underline decoration-dotted underline-offset-2 text-brand-green dark:text-brand-lime"
                                                title="Lihat obat di golongan ini">{{ $row->jumlah_obat }}</button>
                                        </td>
                                        <td class="ds-c">
                                            <x-toggle :current="$row->active_status" trueValue="1" falseValue="0"
                                                wireClick="toggleActive({{ $row->golongan_id }})" />
                                        </td>
                                        <td class="ds-c">
                                            <div class="flex justify-center gap-2">
                                                <x-action-edit wire:click="openEdit({{ $row->golongan_id }})" />
                                                <x-action-delete :action="'requestDelete(' . $row->golongan_id . ')'"
                                                    title="Hapus Golongan Automatic Stop Order" message="Yakin hapus golongan {{ $row->golongan_nama }}? Hanya bisa bila belum ada obat yang dipetakan." />
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-6 py-10">
                                            <div class="flex flex-col items-center justify-center gap-3">
                                                <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada golongan Automatic Stop Order.</p>
                                                <p class="text-sm text-muted-soft">Isi awal: <code>php artisan automatic-stop-order:seed</code> setelah DDL <code>docs/ddl-automatic-stop-order.sql</code> dijalankan.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>

                {{-- PAGINATION --}}
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::master.master-automatic-stop-order.master-automatic-stop-order-actions wire:key="master-automatic-stop-order-actions" />
            <livewire:pages::master.master-automatic-stop-order.master-automatic-stop-order-produk-actions wire:key="master-automatic-stop-order-produk-actions" />

        </div>
    </div>
</div>
