<?php

/**
 * Evaluasi Item Obat — modal detail dari Analisa Perputaran Obat.
 *
 * Menyatukan tiga sudut pandang yang selama ini terpisah layar:
 *   1. POSISI   — stok, cakupan, batas minimum, klasifikasi perputaran
 *   2. BELANJA  — riwayat pembelian per faktur: harga bruto, dua lapis diskon,
 *                 PPN faktur, harga netto per unit, dan trennya naik/turun
 *   3. USULAN   — berapa yang wajar diorder per bulan & harga jual yang menjaga
 *                 margin saat harga beli bergerak
 *
 * Semua usulan di sini adalah HITUNGAN BANTU, bukan keputusan otomatis: tidak ada
 * satupun angka master yang ditulis balik ke database dari layar ini.
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use App\Http\Traits\Stock\StockBalanceTrait;
use App\Http\Traits\Keuangan\AnalisaPerputaranObatTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use StockBalanceTrait, AnalisaPerputaranObatTrait, WithRenderVersioningTrait;

    public array $renderVersions = [];

    public ?string $productId = null;
    public string $slCode = '04';
    public string $kategori = self::KATEGORI_MEDIS;
    public int $bulanPeriode = self::PERIODE_BULAN_DEFAULT;
    public int $ambangFast = self::AMBANG_FAST_DEFAULT;

    /** Berapa bulan stok yang ingin dipegang saat mengorder ulang. */
    public int $targetCakupanBulan = 2;

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('perputaran.openDetail')]
    public function openDetail(string $productId, string $slCode, string $kategori, int $bulanPeriode, int $ambangFast): void
    {
        $this->productId = $productId;
        $this->slCode = $slCode;
        $this->kategori = $kategori;
        $this->bulanPeriode = $bulanPeriode;
        $this->ambangFast = $ambangFast;
        $this->targetCakupanBulan = 2;

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'analisa-perputaran-obat-actions');
    }

    public function closeModal(): void
    {
        $this->reset(['productId']);
        $this->dispatch('close-modal', name: 'analisa-perputaran-obat-actions');
        $this->resetVersion();
    }

    /* ── Data mentah ── */

    #[Computed]
    public function master(): ?object
    {
        if (!$this->productId) {
            return null;
        }

        if ($this->kategori === self::KATEGORI_NONMEDIS) {
            return $this->perputaranMasterNonMedis($this->productId);
        }

        return $this->perputaranMasterMedis($this->productId);
    }

    /** Baris perputaran produk ini — dihitung ulang dengan filter yang sama seperti list. */
    #[Computed]
    public function posisi(): ?object
    {
        if (!$this->productId) {
            return null;
        }

        return $this->perputaranQuery($this->slCode, $this->kategori, $this->bulanPeriode, $this->ambangFast)
            ->where('product_id', $this->productId)
            ->first();
    }

    #[Computed]
    public function pembelianList()
    {
        if (!$this->productId) {
            return collect();
        }

        if ($this->kategori === self::KATEGORI_NONMEDIS) {
            return $this->perputaranPembelianNonMedis($this->productId, $this->bulanPeriode);
        }

        return $this->perputaranPembelianMedis($this->productId, $this->bulanPeriode);
    }

    /** Mutasi bulanan di GUDANG — masuk = pembelian/retur, keluar = distribusi ke unit. */
    #[Computed]
    public function bulananGudangList()
    {
        if (!$this->productId) {
            return collect();
        }

        if ($this->kategori === self::KATEGORI_NONMEDIS) {
            return $this->perputaranBulananGudangNonMedis($this->productId, $this->bulanPeriode);
        }

        return $this->perputaranBulananGudangMedis($this->productId, $this->bulanPeriode);
    }

    /** Mutasi bulanan di APOTEK — keluar = pemakaian pasien. Non-medis tidak punya apotek. */
    #[Computed]
    public function bulananApotekList()
    {
        if (!$this->productId || $this->kategori === self::KATEGORI_NONMEDIS) {
            return collect();
        }

        return $this->perputaranBulananApotekMedis($this->productId, $this->bulanPeriode);
    }

    /**
     * Gabungan per bulan: pembelian & keluar gudang berdampingan dengan pemakaian
     * apotek, supaya rantai barang terbaca dalam satu tabel — beli → keluar gudang
     * → masuk apotek → dipakai pasien.
     */
    #[Computed]
    public function pergerakanBulananList(): array
    {
        $gudangList = $this->bulananGudangList->keyBy('bulan_kode');
        $apotekList = $this->bulananApotekList->keyBy('bulan_kode');
        $pembelianPerBulan = $this->belanjaList
            ->groupBy(fn($faktur) => substr($faktur->tgl_display, 6, 4) . substr($faktur->tgl_display, 3, 2));

        $bulanKodeList = $gudangList->keys()
            ->merge($apotekList->keys())
            ->merge($pembelianPerBulan->keys())
            ->unique()
            ->sort()
            ->values();

        $pergerakan = [];

        foreach ($bulanKodeList as $bulanKode) {
            $gudang = $gudangList->get($bulanKode);
            $apotek = $apotekList->get($bulanKode);
            $pembelian = $pembelianPerBulan->get($bulanKode);

            $pergerakan[] = [
                'bulanDisplay' => $gudang->bulan_display
                    ?? $apotek->bulan_display
                    ?? substr($bulanKode, 4, 2) . '/' . substr($bulanKode, 0, 4),
                'qtyBeli' => $pembelian ? (float) $pembelian->sum('qty') : 0.0,
                'nilaiBeli' => $pembelian ? (float) $pembelian->sum('netto_total') : 0.0,
                'gudangMasuk' => (float) ($gudang->masuk ?? 0),
                'gudangKeluar' => (float) ($gudang->keluar ?? 0),
                'apotekMasuk' => (float) ($apotek->masuk ?? 0),
                'apotekKeluar' => (float) ($apotek->keluar ?? 0),
            ];
        }

        return $pergerakan;
    }

    /* ── Hitungan belanja ── */

    /**
     * Harga netto per unit tiap faktur, DUA versi:
     *   netto_unit     → setelah dua lapis diskon baris, SEBELUM PPN
     *   netto_unit_ppn → ditambah PPN faktur
     *
     * Dua-duanya perlu karena master obat menyimpan cost_price pada basis SEBELUM
     * PPN. Membandingkan master dengan angka ber-PPN membuat setiap item terlihat
     * "beda 11%" padahal cuma beda basis. Perbandingan ke master memakai versi
     * sebelum PPN; pengecekan rugi/tidak memakai versi ber-PPN (kondisi terburuk).
     *
     * Diskon di level faktur (rcv_diskon) sengaja TIDAK dialokasikan ke unit —
     * nilainya melekat ke seluruh faktur, membaginya rata akan menyesatkan.
     */
    #[Computed]
    public function belanjaList()
    {
        return $this->pembelianList
            ->filter(fn($faktur) => (float) $faktur->qty > 0)
            ->map(function ($faktur) {
                $nettoBaris = (float) $faktur->netto_baris;
                $nettoPlusPpn = $nettoBaris * (1 + ((float) $faktur->ppn_persen / 100));

                return (object) [
                    'tgl_display' => $faktur->tgl_display,
                    'faktur' => $faktur->faktur,
                    'supp_name' => $faktur->supp_name,
                    'qty' => (float) $faktur->qty,
                    'harga_bruto' => (float) $faktur->harga_bruto,
                    'diskon_persen' => (float) $faktur->diskon_persen,
                    'diskon_persen2' => (float) $faktur->diskon_persen2,
                    'diskon_rupiah' => (float) $faktur->diskon_rupiah + (float) $faktur->diskon_rupiah2,
                    'ppn_persen' => (float) $faktur->ppn_persen,
                    'ppn_status' => (string) $faktur->ppn_status,
                    'diskon_faktur' => (float) $faktur->diskon_faktur,
                    'materai_faktur' => (float) $faktur->materai_faktur,
                    // kolomnya di-alias 'harga_bruto' di query, BUKAN cost_price
                    'diskon_per_unit' => ((float) $faktur->qty * (float) $faktur->harga_bruto > 0)
                        ? (((float) $faktur->qty * (float) $faktur->harga_bruto) - (float) $faktur->netto_baris) / (float) $faktur->qty
                        : 0.0,
                    'netto_unit' => $nettoBaris / (float) $faktur->qty,
                    'netto_unit_ppn' => $nettoPlusPpn / (float) $faktur->qty,
                    'netto_total' => $nettoBaris,
                    'netto_total_ppn' => $nettoPlusPpn,
                ];
            })
            ->values();
    }

    #[Computed]
    public function ringkasanBelanja(): array
    {
        $belanja = $this->belanjaList;

        if ($belanja->isEmpty()) {
            return [
                'jumlahFaktur' => 0, 'totalQty' => 0.0, 'totalNilai' => 0.0, 'totalNilaiPpn' => 0.0,
                'brutoTerakhir' => 0.0, 'potonganPersen' => null,
                'hppTerakhir' => 0.0, 'hppTerakhirPpn' => 0.0, 'hppRata' => 0.0,
                'hppTerendah' => 0.0, 'hppTertinggi' => 0.0,
                'hppSebelumnya' => 0.0, 'trenPersen' => null, 'tglTerakhir' => null,
                'adaDiskon' => false,
            ];
        }

        $totalQty = (float) $belanja->sum('qty');
        $totalNilai = (float) $belanja->sum('netto_total');
        $totalNilaiPpn = (float) $belanja->sum('netto_total_ppn');
        $terakhir = $belanja->first();                 // sudah urut tanggal DESC
        $sebelumnya = $belanja->skip(1)->first();

        $hppTerakhir = (float) $terakhir->netto_unit;
        $hppSebelumnya = $sebelumnya ? (float) $sebelumnya->netto_unit : 0.0;

        return [
            'jumlahFaktur' => $belanja->count(),
            'totalQty' => $totalQty,
            'totalNilai' => $totalNilai,
            'totalNilaiPpn' => $totalNilaiPpn,
            'brutoTerakhir' => (float) $terakhir->harga_bruto,
            'potonganPersen' => (float) $terakhir->harga_bruto > 0
                ? ((float) $terakhir->harga_bruto - $hppTerakhir) / (float) $terakhir->harga_bruto * 100
                : null,
            'hppTerakhir' => $hppTerakhir,
            'hppTerakhirPpn' => (float) $terakhir->netto_unit_ppn,
            'hppRata' => $totalQty > 0 ? $totalNilai / $totalQty : 0.0,
            'hppTerendah' => (float) $belanja->min('netto_unit'),
            'hppTertinggi' => (float) $belanja->max('netto_unit'),
            'hppSebelumnya' => $hppSebelumnya,
            'trenPersen' => $hppSebelumnya > 0 ? (($hppTerakhir - $hppSebelumnya) / $hppSebelumnya) * 100 : null,
            'tglTerakhir' => $terakhir->tgl_display,
            'adaDiskon' => $belanja->contains(fn($faktur) => $faktur->diskon_persen > 0 || $faktur->diskon_persen2 > 0 || $faktur->diskon_rupiah > 0),
        ];
    }

    /* ── Usulan ── */

    #[Computed]
    public function usulan(): array
    {
        $master = $this->master;
        $posisi = $this->posisi;
        $belanja = $this->ringkasanBelanja;

        $isiBox = (float) ($master->qty_box ?? 0);
        $rataPakaiBulan = (float) ($posisi->rata_bulan ?? 0);
        $stok = (float) ($posisi->saldo_akhir ?? 0);

        // Kebutuhan sampai target cakupan, dikurangi stok yang masih ada.
        $kebutuhan = ($rataPakaiBulan * $this->targetCakupanBulan) - $stok;
        $orderSekarang = max(0.0, $kebutuhan);

        $hargaBeliMaster = (float) ($master->cost_price ?? 0);
        $hargaJualMaster = (float) ($master->sales_price ?? 0);
        $hppTerakhir = (float) $belanja['hppTerakhir'];        // basis sama dgn master: sebelum PPN
        $hppTerakhirPpn = (float) $belanja['hppTerakhirPpn'];  // kondisi terburuk: PPN ditanggung RS

        // Markup yang selama ini dipakai di master → dipertahankan terhadap HPP terbaru.
        $markupMaster = $hargaBeliMaster > 0 ? ($hargaJualMaster - $hargaBeliMaster) / $hargaBeliMaster : null;

        return [
            'orderPerBulan' => $this->bulatkanKeBox($rataPakaiBulan, $isiBox),
            'orderSekarang' => $this->bulatkanKeBox($orderSekarang, $isiBox),
            'kebutuhanKasar' => $orderSekarang,
            'isiBox' => $isiBox,
            'stok' => $stok,
            'rataPakaiBulan' => $rataPakaiBulan,
            'markupMaster' => $markupMaster,
            'hargaJualUsulan' => ($markupMaster !== null && $hppTerakhir > 0)
                ? round($hppTerakhir * (1 + $markupMaster) / 100) * 100
                : null,
            'marginJualSekarang' => ($hargaJualMaster > 0 && $hppTerakhir > 0)
                ? (($hargaJualMaster - $hppTerakhir) / $hargaJualMaster) * 100
                : null,
            'hargaJualUsulanPpn' => ($markupMaster !== null && $hppTerakhirPpn > 0)
                ? round($hppTerakhirPpn * (1 + $markupMaster) / 100) * 100
                : null,
            'jualDiBawahBeli' => $hargaJualMaster > 0 && $hppTerakhirPpn > 0 && $hargaJualMaster < $hppTerakhirPpn,
            'masterBedaDenganFaktur' => $hargaBeliMaster > 0 && $hppTerakhir > 0
                ? (($hppTerakhir - $hargaBeliMaster) / $hargaBeliMaster) * 100
                : null,
        ];
    }

    /** Pembulatan ke atas mengikuti isi box — order tidak pernah pecahan koli. */
    protected function bulatkanKeBox(float $qty, float $isiBox): float
    {
        if ($qty <= 0) {
            return 0.0;
        }

        if ($isiBox <= 1) {
            return ceil($qty);
        }

        return ceil($qty / $isiBox) * $isiBox;
    }

    /**
     * Stok di SEMUA lokasi berledger untuk kategori ini — gudang & apotek berdiri
     * sendiri, jadi "stok saat ini" tanpa keterangan lokasi mudah salah dibaca.
     * Memakai StockBalanceTrait, sumber yang sama dengan pengecekan stok e-resep.
     *
     * @return array<int, array{kode: string, nama: string, saldo: float, aktif: bool}>
     */
    #[Computed]
    public function stokPerLokasiList(): array
    {
        if (!$this->productId) {
            return [];
        }

        $stokList = [];

        foreach ($this->daftarLokasi($this->kategori) as $kodeLokasi) {
            $stokList[] = [
                'kode' => $kodeLokasi,
                'nama' => $this->namaLokasi($kodeLokasi, $this->kategori) ?? $kodeLokasi,
                'saldo' => $this->saldoStok($kodeLokasi, $this->productId, null, $this->kategori),
                'aktif' => $kodeLokasi === $this->slCode,
            ];
        }

        return $stokList;
    }

    /**
     * Ke mana saja obat ini diserahkan dalam periode — apotek maupun ruangan.
     * Ruangan tidak punya ledger stok, jadi jejaknya berhenti di serah-terima
     * transfer; pemakaian di dalam ruangan tidak terekam di database.
     */
    #[Computed]
    public function distribusiList()
    {
        if (!$this->productId) {
            return collect();
        }

        if ($this->kategori === self::KATEGORI_NONMEDIS) {
            return $this->perputaranDistribusiRuanganNonMedis($this->productId, $this->bulanPeriode);
        }

        return $this->perputaranDistribusiRuanganMedis($this->productId, $this->bulanPeriode);
    }

    /** Skala batang — dipakai bersama gudang & apotek supaya tingginya sebanding. */
    #[Computed]
    public function pakaiTertinggi(): float
    {
        $tertinggi = collect($this->pergerakanBulananList)
            ->flatMap(fn($bulan) => [$bulan['gudangKeluar'], $bulan['apotekKeluar']])
            ->max();

        return (float) max(1, $tertinggi ?? 1);
    }
};
?>

<div>
    <x-modal name="analisa-perputaran-obat-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$productId]) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah" class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah" class="hidden w-6 h-6 dark:block" />
                            </div>

                            <div>
                                <h2 class="text-2xl font-semibold text-ink dark:text-gray-100">
                                    {{ $this->master?->product_name ?? 'Evaluasi Item' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Evaluasi menyeluruh: posisi stok, riwayat belanja, dan usulan order &amp; harga jual.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 mt-3">
                            <x-badge variant="gray">{{ $productId }}</x-badge>
                            @if ($this->posisi)
                                @php
                                    $variantKelas = ['FAST' => 'success', 'SLOW' => 'warning', 'DEAD' => 'danger'][$this->posisi->klasifikasi] ?? 'gray';
                                @endphp
                                <x-badge :variant="$variantKelas">{{ $this->posisi->klasifikasi }}</x-badge>
                            @endif
                            <x-badge variant="info">{{ $this->namaLokasi($slCode, $kategori) ?? $slCode }}</x-badge>
                            <x-badge variant="gray">{{ $bulanPeriode }} bulan terakhir</x-badge>
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                @php
                    $master = $this->master;
                    $posisi = $this->posisi;
                    $belanja = $this->ringkasanBelanja;
                    $usulan = $this->usulan;
                @endphp

                @if (!$master)
                    <div class="p-6 text-center text-muted">Data produk tidak ditemukan.</div>
                @else
                    <div class="space-y-4">

                        {{-- 1. POSISI & MASTER --}}
                        <x-border-form title="Posisi Stok & Data Master">
                            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                                <div>
                                    <div class="text-[11px] font-semibold tracking-wide uppercase text-muted">
                                        Stok {{ $this->namaLokasi($slCode, $kategori) ?? $slCode }}
                                    </div>
                                    <div class="font-mono text-xl font-bold {{ ($posisi->saldo_akhir ?? 0) < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-ink dark:text-gray-100' }}">
                                        {{ number_format($posisi->saldo_akhir ?? 0) }}
                                        <span class="text-xs font-normal text-muted">{{ $master->uom_desc }}</span>
                                    </div>
                                    <div class="text-xs text-muted">
                                        batas minimum {{ number_format($kategori === 'medis' && $slCode === '02' ? $master->limit_stock : $master->limit_stockwh) }}
                                    </div>

                                    {{-- Lokasi lain ditampilkan supaya angka di atas tak dikira stok total RS --}}
                                    <div class="pt-2 mt-2 space-y-0.5 border-t border-hairline dark:border-gray-700">
                                        @foreach ($this->stokPerLokasiList as $lokasi)
                                            <div class="flex justify-between gap-3 text-xs {{ $lokasi['aktif'] ? 'font-semibold text-ink dark:text-gray-200' : 'text-muted' }}">
                                                <span>{{ $lokasi['nama'] }}{{ $lokasi['aktif'] ? ' (dilihat)' : '' }}</span>
                                                <span class="font-mono {{ $lokasi['saldo'] < 0 ? 'text-rose-700 dark:text-rose-300' : '' }}">
                                                    {{ number_format($lokasi['saldo']) }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[11px] font-semibold tracking-wide uppercase text-muted">Pemakaian {{ $bulanPeriode }} bln</div>
                                    <div class="font-mono text-xl font-bold text-ink dark:text-gray-100">{{ number_format($posisi->keluar ?? 0) }}</div>
                                    <div class="text-xs text-muted">rata² {{ number_format($posisi->rata_bulan ?? 0, 2) }}/bln · aktif {{ (int) ($posisi->bulan_aktif ?? 0) }}/{{ $bulanPeriode }} bln</div>
                                </div>
                                <div>
                                    <div class="text-[11px] font-semibold tracking-wide uppercase text-muted">Cakupan stok</div>
                                    <div class="font-mono text-xl font-bold text-ink dark:text-gray-100">
                                        {{ $posisi?->cakupan_bulan !== null ? number_format($posisi->cakupan_bulan, 1) . ' bln' : '—' }}
                                    </div>
                                    <div class="text-xs text-muted">nilai stok Rp {{ number_format($posisi->nilai_stok ?? 0) }}</div>
                                </div>
                                <div>
                                    <div class="text-[11px] font-semibold tracking-wide uppercase text-muted">Master</div>
                                    <div class="text-sm text-ink dark:text-gray-100">
                                        beli <span class="font-mono">Rp {{ number_format($master->cost_price) }}</span>
                                    </div>
                                    <div class="text-sm text-ink dark:text-gray-100">
                                        jual <span class="font-mono">{{ $master->sales_price > 0 ? 'Rp ' . number_format($master->sales_price) : '—' }}</span>
                                    </div>
                                    <div class="text-xs text-muted">isi box {{ number_format($master->qty_box) }} · {{ $master->supp_name }}</div>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 2. USULAN --}}
                        <x-border-form title="Usulan Order & Harga Jual">
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                                {{-- Order --}}
                                <div class="p-3 border rounded-2xl border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                                    <div class="flex items-center justify-between gap-3">
                                        <h4 class="text-sm font-semibold text-ink dark:text-gray-100">Order</h4>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-muted">target cakupan</span>
                                            <x-select-input wire:model.live="targetCakupanBulan" class="text-sm w-28">
                                                <option value="1">1 bulan</option>
                                                <option value="2">2 bulan</option>
                                                <option value="3">3 bulan</option>
                                            </x-select-input>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 mt-3">
                                        <div>
                                            <div class="text-[11px] font-semibold tracking-wide uppercase text-muted">Order rutin / bulan</div>
                                            <div class="font-mono text-xl font-bold text-brand-green dark:text-brand-lime">
                                                {{ number_format($usulan['orderPerBulan']) }}
                                            </div>
                                            <div class="text-xs text-muted">dari rata² {{ number_format($usulan['rataPakaiBulan'], 2) }}/bln</div>
                                        </div>
                                        <div>
                                            <div class="text-[11px] font-semibold tracking-wide uppercase text-muted">Order sekarang</div>
                                            <div class="font-mono text-xl font-bold {{ $usulan['orderSekarang'] > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-muted' }}">
                                                {{ number_format($usulan['orderSekarang']) }}
                                            </div>
                                            <div class="text-xs text-muted">
                                                @if ($usulan['orderSekarang'] > 0)
                                                    stok {{ number_format($usulan['stok']) }} belum cukup {{ $targetCakupanBulan }} bulan
                                                @else
                                                    stok masih cukup {{ $targetCakupanBulan }} bulan
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    @if ($usulan['isiBox'] > 1)
                                        <p class="mt-2 text-xs text-muted">
                                            Angka dibulatkan ke atas kelipatan isi box ({{ number_format($usulan['isiBox']) }}).
                                        </p>
                                    @endif
                                </div>

                                {{-- Harga: beli vs jual sekarang vs usulan --}}
                                <div class="p-3 border rounded-2xl border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100">Harga</h4>

                                    <div class="grid grid-cols-3 gap-3 mt-3">
                                        {{-- Beli --}}
                                        <div>
                                            <div class="text-[11px] font-semibold tracking-wide uppercase text-muted">Harga beli terakhir</div>
                                            <div class="font-mono text-xl font-bold text-ink dark:text-gray-100">
                                                Rp {{ number_format($belanja['hppTerakhir']) }}
                                            </div>
                                            <div class="text-xs text-muted">
                                                kotor Rp {{ number_format($belanja['brutoTerakhir']) }}
                                                @if ($belanja['potonganPersen'] !== null && $belanja['potonganPersen'] > 0)
                                                    · potongan {{ number_format($belanja['potonganPersen'], 2) }}%
                                                @endif
                                            </div>
                                            <div class="text-xs text-muted">
                                                rata² Rp {{ number_format($belanja['hppRata']) }} ·
                                                incl PPN Rp {{ number_format($belanja['hppTerakhirPpn']) }}
                                            </div>
                                        </div>

                                        {{-- Jual sekarang --}}
                                        <div class="pl-3 border-l border-hairline dark:border-gray-700">
                                            <div class="text-[11px] font-semibold tracking-wide uppercase text-muted">Jual sekarang</div>
                                            <div class="font-mono text-xl font-bold text-ink dark:text-gray-100">
                                                {{ $master->sales_price > 0 ? 'Rp ' . number_format($master->sales_price) : '—' }}
                                            </div>
                                            <div class="text-xs text-muted">
                                                @if ($usulan['marginJualSekarang'] !== null)
                                                    margin {{ number_format($usulan['marginJualSekarang'], 1) }}%
                                                    · untung Rp {{ number_format($master->sales_price - $belanja['hppTerakhir']) }}/unit
                                                @elseif ($master->sales_price <= 0)
                                                    non-medis tak menyimpan harga jual
                                                @else
                                                    belum ada pembelian sebagai pembanding
                                                @endif
                                            </div>
                                            <div class="text-xs text-muted">
                                                beli di master Rp {{ number_format($master->cost_price) }}
                                            </div>
                                        </div>

                                        {{-- Usulan --}}
                                        <div class="pl-3 border-l border-hairline dark:border-gray-700">
                                            <div class="text-[11px] font-semibold tracking-wide uppercase text-muted">Usulan jual</div>
                                            <div class="font-mono text-xl font-bold text-brand-green dark:text-brand-lime">
                                                {{ $usulan['hargaJualUsulan'] !== null ? 'Rp ' . number_format($usulan['hargaJualUsulan']) : '—' }}
                                            </div>
                                            <div class="text-xs text-muted">
                                                @if ($usulan['markupMaster'] !== null)
                                                    markup master {{ number_format($usulan['markupMaster'] * 100, 1) }}%
                                                @else
                                                    markup tak terhitung
                                                @endif
                                            </div>
                                            <div class="text-xs text-muted">
                                                @if ($usulan['hargaJualUsulanPpn'] !== null)
                                                    Rp {{ number_format($usulan['hargaJualUsulanPpn']) }} bila PPN ditanggung RS
                                                @endif
                                            </div>
                                            @if ($usulan['hargaJualUsulan'] !== null && $master->sales_price > 0)
                                                @php $selisih = $usulan['hargaJualUsulan'] - $master->sales_price; @endphp
                                                <div class="text-xs font-semibold {{ $selisih > 0 ? 'text-rose-700 dark:text-rose-300' : ($selisih < 0 ? 'text-brand-green dark:text-brand-lime' : 'text-muted') }}">
                                                    @if ($selisih == 0)
                                                        harga sekarang sudah pas
                                                    @else
                                                        {{ $selisih > 0 ? 'perlu naik' : 'bisa turun' }} Rp {{ number_format(abs($selisih)) }}
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($usulan['jualDiBawahBeli'])
                                        <div class="px-3 py-2 mt-3 text-xs font-semibold border rounded-lg text-rose-800 bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:border-rose-800 dark:text-rose-300">
                                            Harga jual master LEBIH RENDAH dari harga beli terakhir termasuk PPN
                                            (Rp {{ number_format($belanja['hppTerakhirPpn']) }}) — tiap penjualan menambah rugi.
                                        </div>
                                    @endif

                                    @if ($usulan['masterBedaDenganFaktur'] !== null && abs($usulan['masterBedaDenganFaktur']) >= 1)
                                        <div class="px-3 py-2 mt-3 text-xs border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
                                            Harga beli di master beda {{ number_format($usulan['masterBedaDenganFaktur'], 1) }}%
                                            dari faktur terakhir (master Rp {{ number_format($master->cost_price) }} ·
                                            faktur Rp {{ number_format($belanja['hppTerakhir']) }}).
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </x-border-form>

                        {{-- Penjelasan cara hitung — panel biru standar, default tertutup --}}
                        <details class="p-3 text-sm border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800/40">
                            <summary class="font-semibold text-blue-800 cursor-pointer dark:text-blue-300">
                                Cara angka usulan di atas dihitung
                            </summary>
                            <div class="mt-2 space-y-3 text-blue-900/80 dark:text-blue-200/80">

                                <div>
                                    <div class="font-semibold">Order</div>
                                    <ul class="mt-1 space-y-1" style="list-style: disc; padding-left: 18px">
                                        <li>
                                            <strong>Rata-rata pemakaian</strong> = total keluar di {{ $this->namaLokasi($slCode, $kategori) ?? $slCode }}
                                            ÷ panjang periode
                                            = {{ number_format($posisi->keluar ?? 0) }} ÷ {{ $bulanPeriode }} bulan
                                            = <strong>{{ number_format($usulan['rataPakaiBulan'], 2) }}/bulan</strong>.
                                        </li>
                                        <li>
                                            <strong>Order rutin/bulan</strong> = rata-rata pemakaian, dibulatkan <em>ke atas</em>
                                            @if ($usulan['isiBox'] > 1)
                                                ke kelipatan isi box ({{ number_format($usulan['isiBox']) }})
                                            @else
                                                ke satuan penuh
                                            @endif
                                            → <strong>{{ number_format($usulan['orderPerBulan']) }}</strong>.
                                        </li>
                                        <li>
                                            <strong>Order sekarang</strong> = (rata-rata × target cakupan) − stok saat ini
                                            = ({{ number_format($usulan['rataPakaiBulan'], 2) }} × {{ $targetCakupanBulan }}) − {{ number_format($usulan['stok']) }}
                                            = {{ number_format($usulan['kebutuhanKasar'], 2) }}
                                            @if ($usulan['isiBox'] > 1)
                                                → dibulatkan ke atas kelipatan {{ number_format($usulan['isiBox']) }}
                                            @endif
                                            → <strong>{{ number_format($usulan['orderSekarang']) }}</strong>.
                                            Bernilai 0 bila stok sudah menutup target cakupan.
                                        </li>
                                    </ul>
                                </div>

                                <div>
                                    <div class="font-semibold">Harga jual</div>
                                    <ul class="mt-1 space-y-1" style="list-style: disc; padding-left: 18px">
                                        <li>
                                            <strong>Harga beli terakhir</strong> = nilai baris faktur terakhir setelah dua lapis
                                            diskon, dibagi qty, <em>sebelum PPN</em>
                                            = <strong>Rp {{ number_format($belanja['hppTerakhir']) }}</strong>
                                            (Rp {{ number_format($belanja['hppTerakhirPpn']) }} bila PPN ikut dihitung).
                                        </li>
                                        <li>
                                            <strong>Markup master</strong> = (harga jual master − harga beli master) ÷ harga beli master
                                            = (Rp {{ number_format($master->sales_price) }} − Rp {{ number_format($master->cost_price) }})
                                            ÷ Rp {{ number_format($master->cost_price) }}
                                            = <strong>{{ $usulan['markupMaster'] !== null ? number_format($usulan['markupMaster'] * 100, 1) . '%' : '—' }}</strong>.
                                            Markup inilah yang dipertahankan, jadi kebijakan harga RS tidak berubah — yang berubah
                                            hanya harga belinya.
                                        </li>
                                        <li>
                                            <strong>Usulan harga jual</strong> = harga beli terakhir × (1 + markup master),
                                            dibulatkan ke ratusan
                                            = Rp {{ number_format($belanja['hppTerakhir']) }} × {{ $usulan['markupMaster'] !== null ? number_format(1 + $usulan['markupMaster'], 3) : '—' }}
                                            → <strong>{{ $usulan['hargaJualUsulan'] !== null ? 'Rp ' . number_format($usulan['hargaJualUsulan']) : '—' }}</strong>.
                                        </li>
                                        <li>
                                            <strong>Margin sekarang</strong> = (harga jual master − harga beli terakhir) ÷ harga jual master
                                            = <strong>{{ $usulan['marginJualSekarang'] !== null ? number_format($usulan['marginJualSekarang'], 1) . '%' : '—' }}</strong>.
                                            Margin memakai basis sebelum PPN; kalau PPN masukan dianggap beban RS, pakai angka
                                            "bila PPN ditanggung RS".
                                        </li>
                                    </ul>
                                </div>

                                <p class="text-xs">
                                    Semua ini hitungan bantu berbasis data historis — belum memperhitungkan lead time supplier,
                                    kedaluwarsa, rencana kegiatan (mis. operasi terjadwal), maupun kesepakatan harga khusus.
                                    Angka master tidak pernah diubah dari layar ini.
                                </p>
                            </div>
                        </details>

                        {{-- 3. BELANJA --}}
                        <x-border-form title="Riwayat Pembelian ({{ $belanja['jumlahFaktur'] }} faktur)">
                            @if ($belanja['jumlahFaktur'] === 0)
                                <p class="text-sm text-muted">Tidak ada pembelian dalam {{ $bulanPeriode }} bulan terakhir.</p>
                            @else
                                <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                                    <div>
                                        <div class="text-[11px] font-semibold tracking-wide uppercase text-muted">Harga beli terakhir</div>
                                        <div class="font-mono text-lg font-bold text-ink dark:text-gray-100">Rp {{ number_format($belanja['hppTerakhir']) }}</div>
                                        <div class="text-xs text-muted">
                                            {{ $belanja['tglTerakhir'] }} · Rp {{ number_format($belanja['hppTerakhirPpn']) }} incl PPN
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] font-semibold tracking-wide uppercase text-muted">Tren vs sebelumnya</div>
                                        @if ($belanja['trenPersen'] === null)
                                            <div class="text-lg font-bold text-muted">—</div>
                                        @else
                                            <div class="font-mono text-lg font-bold {{ $belanja['trenPersen'] > 0 ? 'text-rose-700 dark:text-rose-300' : ($belanja['trenPersen'] < 0 ? 'text-brand-green dark:text-brand-lime' : 'text-muted') }}">
                                                {{ $belanja['trenPersen'] > 0 ? '+' : '' }}{{ number_format($belanja['trenPersen'], 1) }}%
                                            </div>
                                            <div class="text-xs text-muted">sebelumnya Rp {{ number_format($belanja['hppSebelumnya']) }}</div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="text-[11px] font-semibold tracking-wide uppercase text-muted">Rata² tertimbang</div>
                                        <div class="font-mono text-lg font-bold text-ink dark:text-gray-100">Rp {{ number_format($belanja['hppRata']) }}</div>
                                        <div class="text-xs text-muted">Rp {{ number_format($belanja['hppTerendah']) }} – {{ number_format($belanja['hppTertinggi']) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] font-semibold tracking-wide uppercase text-muted">Qty dibeli</div>
                                        <div class="font-mono text-lg font-bold text-ink dark:text-gray-100">{{ number_format($belanja['totalQty']) }}</div>
                                        <div class="text-xs text-muted">{{ $belanja['jumlahFaktur'] }} kali order</div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] font-semibold tracking-wide uppercase text-muted">Nilai belanja</div>
                                        <div class="font-mono text-lg font-bold text-ink dark:text-gray-100">Rp {{ number_format($belanja['totalNilai']) }}</div>
                                        <div class="text-xs text-muted">{{ $belanja['adaDiskon'] ? 'ada diskon supplier' : 'tanpa diskon' }}</div>
                                    </div>
                                </div>

                                <div class="mt-3 overflow-x-auto border rounded-xl border-hairline dark:border-gray-700 max-h-72 overflow-y-auto">
                                    <table class="w-full text-sm">
                                        <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                            <tr>
                                                <th class="px-3 py-2 font-semibold">Tanggal</th>
                                                <th class="px-3 py-2 font-semibold">Supplier / Faktur</th>
                                                <th class="px-3 py-2 font-semibold text-right">Qty</th>
                                                <th class="px-3 py-2 font-semibold text-right">
                                                    Harga Bruto
                                                    <div class="text-[10px] font-normal normal-case text-muted">satuan · total baris</div>
                                                </th>
                                                <th class="px-3 py-2 font-semibold text-right">
                                                    Netto / unit
                                                    <div class="text-[10px] font-normal normal-case text-muted">satuan · total baris</div>
                                                </th>
                                                <th class="px-3 py-2 font-semibold text-right">Diskon</th>
                                                <th class="px-3 py-2 font-semibold text-right">PPN</th>
                                                <th class="px-3 py-2 font-semibold text-right">Incl PPN</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-hairline dark:divide-gray-700">
                                            @foreach ($this->belanjaList as $faktur)
                                                <tr>
                                                    <td class="px-3 py-2 whitespace-nowrap">{{ $faktur->tgl_display }}</td>
                                                    <td class="px-3 py-2">
                                                        <div class="text-ink dark:text-gray-200">{{ $faktur->supp_name }}</div>
                                                        <div class="font-mono text-xs text-muted">{{ $faktur->faktur }}</div>
                                                        @if ($faktur->diskon_faktur > 0 || $faktur->materai_faktur > 0)
                                                            <div class="text-[10px] text-amber-700 dark:text-amber-300">
                                                                @if ($faktur->diskon_faktur > 0)
                                                                    diskon faktur Rp {{ number_format($faktur->diskon_faktur) }}
                                                                @endif
                                                                @if ($faktur->materai_faktur > 0)
                                                                    · materai Rp {{ number_format($faktur->materai_faktur) }}
                                                                @endif
                                                                (tidak masuk harga/unit)
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 font-mono text-right">{{ number_format($faktur->qty) }}</td>
                                                    <td class="px-3 py-2 font-mono text-right">
                                                        {{ number_format($faktur->harga_bruto) }}
                                                        <span class="block text-[10px] font-normal text-muted">{{ number_format($faktur->qty * $faktur->harga_bruto) }}</span>
                                                    </td>
                                                    <td class="px-3 py-2 font-mono font-semibold text-right">
                                                        {{ number_format($faktur->netto_unit) }}
                                                        <span class="block text-[10px] font-normal text-muted">{{ number_format($faktur->netto_total) }}</span>
                                                    </td>
                                                    <td class="px-3 py-2 font-mono text-right">
                                                        @if ($faktur->diskon_persen > 0 || $faktur->diskon_persen2 > 0)
                                                            {{ rtrim(rtrim(number_format($faktur->diskon_persen, 2), '0'), '.') }}%@if ($faktur->diskon_persen2 > 0) + {{ rtrim(rtrim(number_format($faktur->diskon_persen2, 2), '0'), '.') }}%@endif
                                                        @elseif ($faktur->diskon_rupiah > 0)
                                                            Rp {{ number_format($faktur->diskon_rupiah) }}
                                                            <span class="block text-[10px] font-normal text-muted">se-baris · ≈ Rp {{ number_format($faktur->diskon_per_unit, 2) }}/unit</span>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 font-mono text-right">
                                                        @if ($faktur->ppn_status !== '1')
                                                            <span class="text-muted">bebas PPN</span>
                                                        @else
                                                            {{ rtrim(rtrim(number_format($faktur->ppn_persen, 2), '0'), '.') }}%
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 font-mono text-right text-muted">{{ number_format($faktur->netto_unit_ppn) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <details class="p-3 mt-3 text-sm border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800/40">
                                    <summary class="font-semibold text-blue-800 cursor-pointer dark:text-blue-300">
                                        Asal angka diskon, PPN, dan materai
                                    </summary>
                                    <div class="mt-2 space-y-2 text-blue-900/80 dark:text-blue-200/80">
                                        <div>
                                            <div class="font-semibold">Diskon — melekat di BARIS faktur (imtxn_receivedtls)</div>
                                            <ul class="mt-1 space-y-1" style="list-style: disc; padding-left: 18px">
                                                <li>Ada dua lapis, tiap lapis punya versi persen dan rupiah:
                                                    <span class="font-mono">dtl_persen</span> / <span class="font-mono">dtl_diskon</span>,
                                                    lalu <span class="font-mono">dtl_persen1</span> / <span class="font-mono">dtl_diskon1</span>.
                                                    Lapis kedua dihitung <em>setelah</em> lapis pertama, bukan dijumlahkan.
                                                </li>
                                                <li>Versi rupiah adalah potongan untuk <strong>seluruh baris</strong>, bukan per unit —
                                                    "Rp 50" pada baris qty 50 berarti ≈ Rp 1/unit. Itulah sebabnya netto/unit turun
                                                    dari 142.484 menjadi 142.483.
                                                </li>
                                                <li>Rumusnya sama persis dengan yang dipakai Pembayaran Hutang PBF, supaya nilai
                                                    tagihan dan nilai pembelian tidak pernah beda antar layar.
                                                </li>
                                            </ul>
                                        </div>

                                        <div>
                                            <div class="font-semibold">PPN — melekat di FAKTUR (imtxn_receivehdrs)</div>
                                            <ul class="mt-1 space-y-1" style="list-style: disc; padding-left: 18px">
                                                <li><span class="font-mono">rcv_ppn</span> = persen PPN, dipakai hanya bila
                                                    <span class="font-mono">rcv_ppn_status = '1'</span>; kalau tidak, baris ditandai
                                                    <strong>bebas PPN</strong>. Di data 12 bulan terakhir, 1.408 dari 3.339 faktur memang tanpa PPN.
                                                </li>
                                                <li>PPN dikenakan pada nilai baris setelah diskon, itulah kolom <strong>Incl PPN</strong>.</li>
                                            </ul>
                                        </div>

                                        <div>
                                            <div class="font-semibold">Diskon faktur &amp; materai — TIDAK dibagi ke unit</div>
                                            <ul class="mt-1 space-y-1" style="list-style: disc; padding-left: 18px">
                                                <li><span class="font-mono">rcv_diskon</span> (potongan untuk seluruh nota) dan
                                                    <span class="font-mono">rcv_materai</span> melekat ke faktur, bukan ke barang.
                                                    Membaginya rata ke tiap item akan menggeser harga satuan barang yang tidak ada
                                                    hubungannya dengan potongan itu, jadi sengaja dibiarkan di luar perhitungan —
                                                    tapi tetap ditampilkan sebagai catatan kecil pada barisnya bila nilainya ada.
                                                </li>
                                                <li>Di data 12 bulan terakhir keduanya nyaris tak terpakai: diskon faktur 0 dari
                                                    3.339 faktur, materai hanya 2 faktur. Kalau nanti mulai dipakai rutin, alokasinya
                                                    perlu dibahas dulu sebelum diubah.
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </details>
                            @endif
                        </x-border-form>

                        {{-- 4. DISTRIBUSI KE APOTEK & RUANGAN --}}
                        <x-border-form title="Distribusi ke Apotek & Ruangan">
                            @php
                                $distribusi = $this->distribusiList;
                                $totalDistribusi = (float) $distribusi->sum('qty');
                            @endphp

                            @if ($distribusi->isEmpty())
                                <p class="text-sm text-muted">
                                    Tidak ada transfer keluar yang terposting untuk item ini dalam {{ $bulanPeriode }} bulan terakhir.
                                </p>
                            @else
                                <div class="overflow-x-auto border rounded-xl border-hairline dark:border-gray-700 max-h-72 overflow-y-auto">
                                    <table class="w-full text-sm">
                                        <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                            <tr>
                                                <th class="px-3 py-2 font-semibold">Dari</th>
                                                <th class="px-3 py-2 font-semibold">Tujuan</th>
                                                <th class="px-3 py-2 font-semibold text-right">Qty</th>
                                                <th class="px-3 py-2 font-semibold text-right">Porsi</th>
                                                <th class="px-3 py-2 font-semibold text-right">Transfer</th>
                                                <th class="px-3 py-2 font-semibold">Terakhir</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-hairline dark:divide-gray-700">
                                            @foreach ($distribusi as $tujuan)
                                                @php $porsi = $totalDistribusi > 0 ? (float) $tujuan->qty / $totalDistribusi * 100 : 0; @endphp
                                                <tr>
                                                    <td class="px-3 py-2 text-muted whitespace-nowrap">{{ $tujuan->asal_nama }}</td>
                                                    <td class="px-3 py-2 font-medium text-ink dark:text-gray-200 whitespace-nowrap">
                                                        {{ $tujuan->tujuan_nama }}
                                                    </td>
                                                    <td class="px-3 py-2 font-mono text-right">{{ number_format($tujuan->qty) }}</td>
                                                    <td class="px-3 py-2 text-right">
                                                        <div class="flex items-center justify-end gap-2">
                                                            <div class="hidden w-24 h-2 overflow-hidden rounded sm:block bg-surface-soft dark:bg-gray-800">
                                                                <div class="h-full rounded bg-sky-500/70" style="width: {{ max(0, min(100, $porsi)) }}%"></div>
                                                            </div>
                                                            <span class="font-mono text-xs text-muted">{{ number_format($porsi, 1) }}%</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-2 font-mono text-right text-muted">{{ number_format($tujuan->jumlah_transfer) }}×</td>
                                                    <td class="px-3 py-2 whitespace-nowrap text-muted">{{ $tujuan->transfer_terakhir }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                                            <tr class="font-semibold">
                                                <td class="px-3 py-2" colspan="2">Total diserahkan</td>
                                                <td class="px-3 py-2 font-mono text-right">{{ number_format($totalDistribusi) }}</td>
                                                <td class="px-3 py-2"></td>
                                                <td class="px-3 py-2"></td>
                                                <td class="px-3 py-2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <p class="mt-2 text-xs text-muted">
                                    Sumbernya transfer stok berstatus <strong>terposting</strong> (draft &amp; batal tidak dihitung).
                                    Ruangan seperti OK, UGD, VK, ICU, atau laborat <strong>tidak punya kartu stok sendiri</strong> di
                                    database ini — jejak obat berhenti di serah-terima transfer, pemakaian di dalam ruangan tidak
                                    terekam. Yang menuju <strong>Apotek</strong> masih bisa ditelusuri lebih jauh lewat kolom
                                    Apotek pada tabel pergerakan bulanan di bawah.
                                </p>
                            @endif
                        </x-border-form>

                        {{-- 5. PERGERAKAN BULANAN: GUDANG vs APOTEK --}}
                        <x-border-form title="Pergerakan per Bulan — Pembelian, Gudang & Apotek">
                            @php $pergerakan = $this->pergerakanBulananList; @endphp

                            @if (empty($pergerakan))
                                <p class="text-sm text-muted">Tidak ada pembelian maupun mutasi pada periode ini.</p>
                            @else
                                <div class="overflow-x-auto border rounded-xl border-hairline dark:border-gray-700">
                                    <table class="w-full text-sm">
                                        <thead class="text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                            <tr>
                                                <th class="px-3 py-2 font-semibold" rowspan="2">Bulan</th>
                                                <th class="px-3 py-2 font-semibold text-center border-l border-hairline dark:border-gray-700" colspan="2">Pembelian</th>
                                                <th class="px-3 py-2 font-semibold text-center border-l border-hairline dark:border-gray-700" colspan="2">Gudang</th>
                                                @if ($kategori !== 'nonmedis')
                                                    <th class="px-3 py-2 font-semibold text-center border-l border-hairline dark:border-gray-700" colspan="2">Apotek</th>
                                                @endif
                                            </tr>
                                            <tr class="text-xs">
                                                <th class="px-3 pb-2 font-medium text-right border-l border-hairline dark:border-gray-700">Qty</th>
                                                <th class="px-3 pb-2 font-medium text-right">Nilai</th>
                                                <th class="px-3 pb-2 font-medium text-right border-l border-hairline dark:border-gray-700">Masuk</th>
                                                <th class="px-3 pb-2 font-medium text-right">Keluar</th>
                                                @if ($kategori !== 'nonmedis')
                                                    <th class="px-3 pb-2 font-medium text-right border-l border-hairline dark:border-gray-700">Masuk</th>
                                                    <th class="px-3 pb-2 font-medium text-right">Dipakai</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-hairline dark:divide-gray-700">
                                            @foreach ($pergerakan as $bulan)
                                                <tr>
                                                    <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $bulan['bulanDisplay'] }}</td>
                                                    <td class="px-3 py-2 font-mono text-right border-l border-hairline dark:border-gray-700">
                                                        {{ $bulan['qtyBeli'] > 0 ? number_format($bulan['qtyBeli']) : '—' }}
                                                    </td>
                                                    <td class="px-3 py-2 font-mono text-right text-muted">
                                                        {{ $bulan['nilaiBeli'] > 0 ? number_format($bulan['nilaiBeli']) : '—' }}
                                                    </td>
                                                    <td class="px-3 py-2 font-mono text-right border-l border-hairline dark:border-gray-700">
                                                        {{ $bulan['gudangMasuk'] > 0 ? number_format($bulan['gudangMasuk']) : '—' }}
                                                    </td>
                                                    <td class="px-3 py-2 font-mono text-right">
                                                        <div class="flex items-center justify-end gap-2">
                                                            <div class="hidden w-20 h-2 overflow-hidden rounded sm:block bg-surface-soft dark:bg-gray-800">
                                                                <div class="h-full rounded bg-brand-green/70 dark:bg-brand-lime/60"
                                                                    style="width: {{ max(0, min(100, $bulan['gudangKeluar'] / $this->pakaiTertinggi * 100)) }}%"></div>
                                                            </div>
                                                            <span>{{ $bulan['gudangKeluar'] > 0 ? number_format($bulan['gudangKeluar']) : '—' }}</span>
                                                        </div>
                                                    </td>
                                                    @if ($kategori !== 'nonmedis')
                                                        <td class="px-3 py-2 font-mono text-right border-l border-hairline dark:border-gray-700">
                                                            {{ $bulan['apotekMasuk'] > 0 ? number_format($bulan['apotekMasuk']) : '—' }}
                                                        </td>
                                                        <td class="px-3 py-2 font-mono text-right">
                                                            <div class="flex items-center justify-end gap-2">
                                                                <div class="hidden w-20 h-2 overflow-hidden rounded sm:block bg-surface-soft dark:bg-gray-800">
                                                                    <div class="h-full rounded bg-sky-500/70"
                                                                        style="width: {{ max(0, min(100, $bulan['apotekKeluar'] / $this->pakaiTertinggi * 100)) }}%"></div>
                                                                </div>
                                                                <span>{{ $bulan['apotekKeluar'] > 0 ? number_format($bulan['apotekKeluar']) : '—' }}</span>
                                                            </div>
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                                            <tr class="font-semibold">
                                                <td class="px-3 py-2">Total</td>
                                                <td class="px-3 py-2 font-mono text-right border-l border-hairline dark:border-gray-700">{{ number_format(collect($pergerakan)->sum('qtyBeli')) }}</td>
                                                <td class="px-3 py-2 font-mono text-right">{{ number_format(collect($pergerakan)->sum('nilaiBeli')) }}</td>
                                                <td class="px-3 py-2 font-mono text-right border-l border-hairline dark:border-gray-700">{{ number_format(collect($pergerakan)->sum('gudangMasuk')) }}</td>
                                                <td class="px-3 py-2 font-mono text-right">{{ number_format(collect($pergerakan)->sum('gudangKeluar')) }}</td>
                                                @if ($kategori !== 'nonmedis')
                                                    <td class="px-3 py-2 font-mono text-right border-l border-hairline dark:border-gray-700">{{ number_format(collect($pergerakan)->sum('apotekMasuk')) }}</td>
                                                    <td class="px-3 py-2 font-mono text-right">{{ number_format(collect($pergerakan)->sum('apotekKeluar')) }}</td>
                                                @endif
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <p class="mt-2 text-xs text-muted">
                                    Alur barang terbaca dari kiri ke kanan: dibeli dari supplier → masuk gudang →
                                    keluar gudang (distribusi ke apotek/unit) → masuk apotek → dipakai pasien.
                                    Selisih antar kolom wajar terjadi karena beda waktu posting, retur, atau
                                    pengeluaran gudang ke unit selain apotek.
                                </p>
                            @endif
                        </x-border-form>

                        {{-- PANDUAN BACA — panel biru standar, default tertutup --}}
                        <details class="p-3 text-sm border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800/40">
                            <summary class="font-semibold text-blue-800 cursor-pointer dark:text-blue-300">
                                Cara membaca evaluasi ini — urutan periksa &amp; tindakannya
                            </summary>
                            <div class="mt-2 space-y-3 text-blue-900/80 dark:text-blue-200/80">

                                <div>
                                    <div class="font-semibold">Urutan membacanya</div>
                                    <ol class="mt-1 space-y-1" style="list-style: decimal; padding-left: 20px">
                                        <li><strong>Posisi stok dulu.</strong> Pastikan Anda melihat lokasi yang dimaksud —
                                            gudang dan apotek berdiri sendiri. Stok minus berarti data belum benar; betulkan
                                            dulu sebelum memakai angka lain di halaman ini.</li>
                                        <li><strong>Pola pemakaian.</strong> Lihat "bulan aktif" dan tabel pergerakan bulanan.
                                            Pemakaian rutin tiap bulan boleh dijadikan dasar order; pemakaian yang menumpuk di
                                            satu-dua bulan biasanya kegiatan tertentu, jangan dirata-ratakan mentah.</li>
                                        <li><strong>Harga belinya bergerak ke mana.</strong> Baca tren dan rentang terendah–tertinggi.
                                            Kalau harga naik, cek dulu apakah diskon supplier hilang atau memang harga dasar naik.</li>
                                        <li><strong>Baru putuskan</strong> order dan harga jual, memakai kotak Usulan sebagai titik awal.</li>
                                    </ol>
                                </div>

                                <div>
                                    <div class="font-semibold">Membaca kombinasi angka</div>
                                    <ul class="mt-1 space-y-1" style="list-style: disc; padding-left: 18px">
                                        <li><strong>FAST + cakupan &lt; 1 bulan</strong> → paling mendesak: dipakai rutin tapi stok
                                            hampir habis. Pakai angka "Order sekarang".</li>
                                        <li><strong>FAST + cakupan besar</strong> → aman; jangan tambah order walau pemakaiannya tinggi.</li>
                                        <li><strong>SLOW/DEAD + nilai stok besar</strong> → modal mengendap. Hentikan pembelian,
                                            pertimbangkan retur ke supplier atau pemindahan ke unit yang masih memakai.</li>
                                        <li><strong>DEAD tapi masih dibeli</strong> (ada faktur di riwayat) → pembelian jalan terus
                                            padahal barang tidak keluar; ini yang paling sering jadi temuan.</li>
                                        <li><strong>Distribusi didominasi satu ruangan</strong> → pemakaian sebenarnya ada di ruangan
                                            itu; ingat jejaknya berhenti di serah-terima, bukan pemakaian pasien.</li>
                                    </ul>
                                </div>

                                <div>
                                    <div class="font-semibold">Yang perlu diwaspadai sebelum mengambil keputusan</div>
                                    <ul class="mt-1 space-y-1" style="list-style: disc; padding-left: 18px">
                                        <li>Rata-rata pemakaian memakai seluruh periode. Obat baru atau obat yang baru berhenti
                                            dipakai akan terlihat menyesatkan — periksa tabel bulanannya.</li>
                                        <li>Usulan harga jual hanya mempertahankan markup yang sudah ada di master. Kalau markup
                                            master-nya sendiri belum benar, usulannya ikut tidak benar.</li>
                                        <li><strong>Ketersediaan di sini belum mencakup kedaluwarsa.</strong> Stok dan cakupan
                                            menghitung seluruh fisik barang, termasuk yang sebentar lagi ED. Jadi "cukup 3 bulan"
                                            bisa jadi tidak benar bila sebagian stoknya kedaluwarsa lebih dulu — periksa fisik
                                            atau kartu batch sebelum memutuskan menunda order.</li>
                                        <li>Belum diperhitungkan juga: lead time supplier, rencana kegiatan (mis. operasi
                                            terjadwal), dan kesepakatan harga khusus.</li>
                                        <li>Layar ini tidak pernah mengubah data — semua keputusan tetap dijalankan lewat menu
                                            pembelian dan master obat.</li>
                                    </ul>
                                </div>
                            </div>
                        </details>
                    </div>
                @endif
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        Semua usulan di layar ini hitungan bantu — tidak ada angka master yang diubah dari sini.
                    </div>
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
