<?php

/**
 * Analisa Perputaran Obat — Fast / Slow / Dead Moving.
 *
 * Data diambil dari LEDGER stok (view yang sama dengan Kartu Stock), bukan dari
 * kolom snapshot IMMST_PRODUCTS.STOCK_* yang tidak punya dimensi waktu. Query per
 * lokasi ada di AnalisaPerputaranObatTrait dengan nama tabel ditulis literal.
 *
 * Klasifikasi berdasar jumlah BULAN yang ada pemakaian dalam periode:
 *   DEAD = tak ada pemakaian sama sekali · FAST = bulan aktif ≥ ambang · SLOW = sisanya.
 * Ambang & panjang periode bisa diubah user dari toolbar.
 */

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use App\Http\Traits\Stock\StockBalanceTrait;
use App\Http\Traits\Keuangan\AnalisaPerputaranObatTrait;

new class extends Component {
    use WithPagination, StockBalanceTrait, AnalisaPerputaranObatTrait;

    public string $slCode = '04';                 // 04 Gudang Medis | 02 Apotek
    public string $kategori = self::KATEGORI_MEDIS;
    public int $bulanPeriode = self::PERIODE_BULAN_DEFAULT;
    public int $ambangFast = self::AMBANG_FAST_DEFAULT;
    public string $filterKlasifikasi = '';        // '' | FAST | SLOW | DEAD
    public string $searchKeyword = '';
    public string $urut = 'kelas';                // kelas | nilai | keluar | nama
    public int $itemsPerPage = 25;

    public function updatedSlCode(): void { $this->resetPage(); }
    public function updatedKategori(): void { $this->sesuaikanLokasi(); $this->resetPage(); }
    public function updatedBulanPeriode(): void { $this->resetPage(); }
    public function updatedAmbangFast(): void { $this->resetPage(); }
    public function updatedFilterKlasifikasi(): void { $this->resetPage(); }
    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedUrut(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void { $this->resetPage(); }

    public function openDetail(string $productId): void
    {
        $this->dispatch(
            'perputaran.openDetail',
            productId: $productId,
            slCode: $this->slCode,
            kategori: $this->kategori,
            bulanPeriode: $this->bulanPeriode,
            ambangFast: $this->ambangFast,
        );
    }

    public function resetFilters(): void
    {
        $this->reset(['filterKlasifikasi', 'searchKeyword']);
        $this->bulanPeriode = self::PERIODE_BULAN_DEFAULT;
        $this->ambangFast = self::AMBANG_FAST_DEFAULT;
        $this->urut = 'kelas';
        $this->resetPage();
    }

    /** Non-medis hanya punya ledger Gudang — jangan biarkan lokasi menggantung. */
    protected function sesuaikanLokasi(): void
    {
        if (!$this->lokasiTerlacak($this->slCode, $this->kategori)) {
            $this->slCode = $this->daftarLokasi($this->kategori)[0] ?? '04';
        }
    }

    /** Nama lokasi yang sedang dilihat — dipakai di judul kolom supaya tak salah baca. */
    #[Computed]
    public function namaLokasiAktif(): string
    {
        return $this->namaLokasi($this->slCode, $this->kategori) ?? $this->slCode;
    }

    #[Computed]
    public function daftarLokasiAktif(): array
    {
        return collect($this->daftarLokasi($this->kategori))
            ->mapWithKeys(fn($kode) => [$kode => $this->namaLokasi($kode, $this->kategori)])
            ->all();
    }

    #[Computed]
    public function rows()
    {
        $query = $this->perputaranQuery(
            $this->slCode,
            $this->kategori,
            $this->bulanPeriode,
            $this->ambangFast,
            $this->filterKlasifikasi,
            trim($this->searchKeyword),
        );

        match ($this->urut) {
            // Fast → Slow → Dead; di dalam kelas: FAST cakupan tertipis dulu,
            // SLOW/DEAD nilai stok terbesar dulu (lihat fmsUrutPerhatian()).
            'kelas' => $this->perputaranUrutPerhatian($query),
            'keluar' => $query->orderByDesc('keluar')->orderBy('product_name'),
            'nama' => $query->orderBy('product_name'),
            default => $query->orderByDesc('nilai_stok')->orderBy('product_name'),
        };

        return $query->paginate($this->itemsPerPage);
    }

    #[Computed]
    public function ringkasan(): array
    {
        return $this->perputaranRingkasan(
            $this->slCode,
            $this->kategori,
            $this->bulanPeriode,
            $this->ambangFast,
            trim($this->searchKeyword),
        );
    }

    #[Computed]
    public function labelPeriode(): string
    {
        return $this->perputaranAwalPeriode($this->bulanPeriode)->format('m/Y')
            . ' — ' . $this->perputaranAkhirPeriode()->format('m/Y');
    }

    #[Computed]
    public function minimalBulanFast(): int
    {
        return $this->perputaranMinimalBulanFast($this->bulanPeriode, $this->ambangFast);
    }
};
?>

<div>
    <x-page-title
        title="Analisa Perputaran Obat (Fast / Slow / Dead)"
        subtitle="Perputaran stok per produk dari ledger gudang & apotek — sorot modal yang mengendap" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 mt-2 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                {{-- Satu baris di layar lebar (xl ke atas); di bawah itu membungkus rapi. --}}
                <div class="flex flex-wrap items-end gap-2 xl:flex-nowrap">

                    <div class="w-full sm:w-48 sm:shrink-0">
                        <x-input-label value="Cari Obat" />
                        <x-text-input wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Kode / nama..." class="block w-full mt-1" />
                    </div>

                    <div class="w-full sm:w-auto sm:shrink-0">
                        <x-input-label value="Kategori" />
                        <x-select-input wire:model.live="kategori" class="w-full mt-1 sm:w-32">
                            <option value="medis">Medis</option>
                            <option value="nonmedis">Non-Medis</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto sm:shrink-0">
                        <x-input-label value="Lokasi" />
                        <x-select-input wire:model.live="slCode" class="w-full mt-1 sm:w-44">
                            @foreach ($this->daftarLokasiAktif as $kode => $nama)
                                <option value="{{ $kode }}">{{ $nama }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto sm:shrink-0">
                        <x-input-label value="Periode" />
                        <x-select-input wire:model.live="bulanPeriode" class="w-full mt-1 sm:w-28">
                            <option value="3">3 bln</option>
                            <option value="6">6 bln</option>
                            <option value="12">12 bln</option>
                            <option value="24">24 bln</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto sm:shrink-0">
                        <x-input-label value="Fast bila" />
                        <x-select-input wire:model.live="ambangFast" class="w-full mt-1 sm:w-36"
                            title="Berapa persen bulan dalam periode yang harus ada pemakaian supaya obat dihitung Fast Moving">
                            <option value="50">≥ 50% bulan</option>
                            <option value="60">≥ 60% bulan</option>
                            <option value="70">≥ 70% bulan</option>
                            <option value="80">≥ 80% bulan</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto sm:shrink-0">
                        <x-input-label value="Klasifikasi" />
                        <x-select-input wire:model.live="filterKlasifikasi" class="w-full mt-1 sm:w-32">
                            <option value="">Semua</option>
                            <option value="FAST">Fast</option>
                            <option value="SLOW">Slow</option>
                            <option value="DEAD">Dead</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto sm:shrink-0">
                        <x-input-label value="Urut" />
                        <x-select-input wire:model.live="urut" class="w-full mt-1 sm:w-40">
                            <option value="kelas">Perlu dicek</option>
                            <option value="nilai">Nilai stok</option>
                            <option value="keluar">Pemakaian</option>
                            <option value="nama">Nama obat</option>
                        </x-select-input>
                    </div>

                    {{-- Baris + Refresh/Reset didorong ke ujung kanan toolbar --}}
                    <div class="flex items-end w-full gap-2 sm:w-auto sm:ml-auto sm:shrink-0">
                        <div class="flex-1 sm:flex-none">
                            <x-input-label value="Baris" />
                            <x-select-input wire:model.live="itemsPerPage" class="w-full mt-1 sm:w-20">
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        <div class="shrink-0 pb-0.5">
                            <x-toolbar-refresh-reset :label="null" />
                        </div>
                    </div>
                </div>

                <div class="mt-2 text-sm text-muted dark:text-gray-400">
                    Periode <strong>{{ $this->labelPeriode }}</strong> ·
                    <strong>FAST</strong> = ada pemakaian di <strong>≥ {{ $ambangFast }}% bulan</strong>
                    (minimal {{ $this->minimalBulanFast }} dari {{ $bulanPeriode }} bulan) ·
                    <strong>SLOW</strong> = ada pemakaian tapi di bawah itu ·
                    <strong>DEAD</strong> = tidak ada pemakaian sama sekali.
                </div>
            </div>

            {{-- RINGKASAN (buka/tutup — pola Ringkasan Piutang; default TUTUP) --}}
            @php $sum = $this->ringkasan; @endphp
            <div class="mt-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900"
                x-data="{ open: false }">

                <button type="button" @click="open = !open"
                    class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl
                           hover:bg-surface-soft dark:hover:bg-gray-800
                           focus:outline-none focus:ring-1 focus:ring-gray-300">

                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-body dark:text-gray-200">
                            Ringkasan Perputaran
                        </div>
                        <div class="text-xs text-muted dark:text-gray-400">
                            {{ number_format($sum['total']['item']) }} item aktif ·
                            Fast {{ number_format($sum['FAST']['item']) }} ·
                            Slow {{ number_format($sum['SLOW']['item']) }} ·
                            <span class="font-semibold text-rose-700 dark:text-rose-300">
                                Dead {{ number_format($sum['DEAD']['item']) }} — Rp {{ number_format($sum['DEAD']['nilai']) }}
                            </span>
                        </div>
                    </div>

                    <span class="hidden text-xs sm:inline text-muted dark:text-gray-400">
                        <span x-text="open ? 'Sembunyikan' : 'Lihat detail'"></span>
                    </span>
                    <svg class="w-4 h-4 transition-transform duration-200 text-muted-soft shrink-0"
                        :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-cloak x-show="open"
                    class="px-4 pb-4 border-t border-hairline dark:border-gray-700"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0">

                    <div class="grid grid-cols-1 gap-3 mt-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="p-3 border rounded-2xl bg-brand-green/5 border-brand-green/25 dark:bg-brand-lime/10 dark:border-brand-lime/25">
                            <div class="text-[11px] font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Fast Moving</div>
                            <div class="mt-1 text-xl font-bold text-brand-green dark:text-brand-lime">{{ number_format($sum['FAST']['item']) }} item</div>
                            <div class="font-mono text-sm text-muted dark:text-gray-400">Rp {{ number_format($sum['FAST']['nilai']) }}</div>
                        </div>
                        <div class="p-3 border rounded-2xl bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-700">
                            <div class="text-[11px] font-semibold tracking-wide uppercase text-amber-700 dark:text-amber-300">Slow Moving</div>
                            <div class="mt-1 text-xl font-bold text-amber-800 dark:text-amber-200">{{ number_format($sum['SLOW']['item']) }} item</div>
                            <div class="font-mono text-sm text-amber-700/80 dark:text-amber-300/80">Rp {{ number_format($sum['SLOW']['nilai']) }}</div>
                        </div>
                        <div class="p-3 border rounded-2xl bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:border-rose-800">
                            <div class="text-[11px] font-semibold tracking-wide uppercase text-rose-700 dark:text-rose-300">Dead Moving</div>
                            <div class="mt-1 text-xl font-bold text-rose-800 dark:text-rose-200">{{ number_format($sum['DEAD']['item']) }} item</div>
                            <div class="font-mono text-sm text-rose-700/80 dark:text-rose-300/80">Rp {{ number_format($sum['DEAD']['nilai']) }}</div>
                        </div>
                        <div class="p-3 border rounded-2xl bg-surface-soft border-hairline dark:border-gray-700 dark:bg-gray-800/40">
                            <div class="text-[11px] font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Total Item Aktif</div>
                            <div class="mt-1 text-xl font-bold text-ink dark:text-gray-100">{{ number_format($sum['total']['item']) }} item</div>
                            <div class="font-mono text-sm text-muted dark:text-gray-400">Nilai stok Rp {{ number_format($sum['total']['nilai']) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABEL --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th>Obat</th>
                                <th class="ds-c">Klasifikasi</th>
                                <th class="text-right">Pemakaian<div class="text-[10px] font-normal normal-case text-muted">{{ $bulanPeriode }} bln · {{ $this->namaLokasiAktif }}</div></th>
                                <th class="text-right">Stok<div class="text-[10px] font-normal normal-case text-muted">{{ $this->namaLokasiAktif }} · tahun berjalan</div></th>
                                <th class="text-right">Nilai Stok</th>
                                <th>Keluar Terakhir</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                @php
                                    $variant = ['FAST' => 'success', 'SLOW' => 'warning', 'DEAD' => 'danger'][$row->klasifikasi] ?? 'gray';
                                    $warnaKelas = [
                                        'FAST' => 'text-brand-green dark:text-brand-lime',
                                        'SLOW' => 'text-amber-700 dark:text-amber-300',
                                        'DEAD' => 'text-rose-700 dark:text-rose-300',
                                    ][$row->klasifikasi] ?? 'text-muted';
                                @endphp
                                <tr wire:key="perputaran-{{ $row->product_id }}">
                                    {{-- Obat: nama + kode + satuan --}}
                                    <td>
                                        <div class="ds-td-strong">{{ $row->product_name }}</div>
                                        <div class="text-xs text-muted">
                                            <span class="font-mono">{{ $row->product_id }}</span>
                                            @if ($row->uom_id)
                                                · {{ $row->uom_id }}
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Klasifikasi + dasar penilaiannya (% bulan aktif) --}}
                                    <td class="ds-c whitespace-nowrap">
                                        <x-badge :variant="$variant">{{ $row->klasifikasi }}</x-badge>
                                        <div class="mt-0.5 text-xs {{ $warnaKelas }}">
                                            {{ (int) $row->persen_aktif }}% bulan aktif
                                        </div>
                                    </td>

                                    {{-- Pemakaian: total + rata² + bulan aktif --}}
                                    <td class="text-right whitespace-nowrap">
                                        <div class="font-mono">{{ number_format($row->keluar) }}</div>
                                        <div class="text-xs text-muted">
                                            rata² {{ number_format($row->rata_bulan, 2) }}/bln ·
                                            {{ (int) $row->bulan_aktif }}/{{ $bulanPeriode }} bln
                                        </div>
                                    </td>

                                    {{-- Stok: saldo + cakupan + penanda batas --}}
                                    <td class="text-right whitespace-nowrap">
                                        <div class="font-mono @if ($row->saldo_akhir < 0) font-semibold text-rose-700 dark:text-rose-300 @endif">
                                            {{ number_format($row->saldo_akhir) }}
                                        </div>
                                        <div class="text-xs">
                                            @if ($row->saldo_akhir < 0)
                                                <span class="font-semibold text-rose-700 dark:text-rose-300">stok minus</span>
                                            @elseif ($row->limit_stok > 0 && $row->saldo_akhir <= $row->limit_stok)
                                                <span class="font-semibold text-rose-700 dark:text-rose-300">≤ limit {{ number_format($row->limit_stok) }}</span>
                                            @elseif ($row->cakupan_bulan !== null)
                                                <span @class([
                                                    'text-muted',
                                                    'font-semibold text-rose-700 dark:text-rose-300' => $row->klasifikasi === 'FAST' && $row->cakupan_bulan < 1,
                                                    'font-semibold text-amber-700 dark:text-amber-300' => $row->klasifikasi !== 'FAST' && $row->cakupan_bulan > 12,
                                                ])>cukup {{ number_format($row->cakupan_bulan, 1) }} bln</span>
                                            @else
                                                <span class="text-muted">tanpa pemakaian</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Nilai stok --}}
                                    <td class="font-mono text-right whitespace-nowrap">
                                        Rp {{ number_format($row->nilai_stok) }}
                                    </td>

                                    {{-- Keluar terakhir + umur diam --}}
                                    <td class="whitespace-nowrap">
                                        @if ($row->keluar_terakhir)
                                            {{ $row->keluar_terakhir }}
                                            <span class="block text-xs text-muted">{{ (int) $row->hari_diam }} hari lalu</span>
                                        @else
                                            <span class="text-muted">belum pernah keluar</span>
                                        @endif
                                    </td>

                                    <td class="ds-c">
                                        <x-secondary-button type="button"
                                            wire:click="openDetail('{{ $row->product_id }}')"
                                            class="px-2 py-1 text-xs whitespace-nowrap"
                                            title="Evaluasi menyeluruh: belanja, usulan order & harga jual">
                                            Evaluasi
                                        </x-secondary-button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-16 text-center text-muted dark:text-gray-400">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-10 h-10 text-hairline" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m4 10V11m4 6V9M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                            <span>Tidak ada data perputaran untuk filter ini.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- Modal evaluasi per item --}}
            <livewire:pages::transaksi.keuangan.analisa-perputaran-obat.analisa-perputaran-obat-actions
                wire:key="analisa-perputaran-obat-actions" />

            {{-- CATATAN --}}
            <details class="p-3 mt-4 text-sm border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800/40">
                <summary class="font-semibold text-blue-800 cursor-pointer dark:text-blue-300">
                    Cara baca angka &amp; batasan yang masih ada
                </summary>
                <div class="mt-2 space-y-2 text-blue-900/80 dark:text-blue-200/80">
                    <ul class="space-y-1" style="list-style: disc; padding-left: 18px">
                        <li><strong>Pemakaian</strong> = total qty keluar di ledger lokasi terpilih sepanjang periode; <strong>Bulan Aktif</strong> = berapa bulan berbeda yang ada transaksi keluar, dan <strong>% Aktif</strong> = bulan aktif dibagi panjang periode.</li>
                        <li>Filter <strong>"Fast bila"</strong> adalah ambang % Aktif: obat masuk FAST kalau % Aktif-nya mencapai angka itu. Jadi obat yang keluar 1x dengan qty besar tetap SLOW — yang dinilai keteraturan pemakaian, bukan besar qty.</li>
                        <li><strong>Stok</strong> memakai saldo ledger tahun berjalan (saldo awal + masuk − keluar), sama dengan Kartu Stock — bukan kolom snapshot di master obat.</li>
                        <li><strong>Nilai stok</strong> = stok × harga pokok (cost_price) master obat. Obat dengan cost_price 0 akan bernilai 0 walau fisiknya ada.</li>
                        <li>Klasifikasi dihitung <strong>per lokasi</strong>. Obat yang mati di gudang bisa saja aktif di apotek — periksa keduanya sebelum menyimpulkan.</li>
                        <li><strong>Cakupan</strong> = stok ÷ rata-rata pemakaian per bulan, yaitu perkiraan berapa bulan lagi stok bertahan. Urutan <strong>"Perlu dicek"</strong> memakai ini: di blok FAST yang cakupannya paling tipis naik ke atas (risiko kehabisan), sedangkan di blok SLOW &amp; DEAD yang nilai stoknya terbesar yang naik (modal mengendap). Tanda <strong>≤ limit</strong> muncul bila stok sudah menyentuh batas minimum di master obat.</li>
                        <li><strong>Stok minus</strong> berarti ledger mencatat keluar lebih besar daripada saldo awal + masuk — biasanya penerimaan belum diposting atau pengeluaran dobel. Item seperti ini naik paling atas di urutan "Perlu dicek" karena angkanya jelas perlu dibetulkan lebih dulu sebelum dipakai mengambil keputusan.</li>
                        <li><strong>Kedaluwarsa belum masuk hitungan.</strong> Stok, cakupan, dan nilai stok memakai
                            seluruh fisik barang tanpa memandang tanggal ED — barang yang tinggal sebulan lagi kedaluwarsa
                            tetap dihitung sebagai stok tersedia. Datanya sebenarnya ada di penerimaan
                            (<span class="font-mono">imtxn_receivedtls.rcv_ed</span> &amp; <span class="font-mono">rcv_bath</span>,
                            terisi 5.817 dari 7.514 baris 12 bulan terakhir), tetapi disimpan sebagai teks bebas sehingga perlu
                            dinormalkan dulu sebelum bisa dipakai menghitung. Untuk sekarang: perlakukan angka ketersediaan di
                            sini sebagai batas atas, bukan stok siap pakai.</li>
                        <li>Belum ada juga: usulan retur ke PBF dan simpanan riwayat klasifikasi antar periode.</li>
                    </ul>
                </div>
            </details>

        </div>
    </div>
</div>
