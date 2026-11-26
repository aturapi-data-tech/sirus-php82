<?php
// resources/views/pages/transaksi/kasir/kasir.blade.php
// Wrapper 1 halaman untuk Antrian Kasir RJ & UGD dalam tab
// + Cash In / Cash Out / Piutang Pasien = komponen Keuangan apa adanya
// + Hutang PBF / Hutang Non-Medis / DP PBF / DP Non-Medis = komponen Keuangan apa adanya

use Livewire\Component;
use Livewire\Attributes\Locked;
use App\Http\Traits\Concerns\WithTabHakAksesTrait;
use App\Support\AksesHalaman;

new class extends Component {
    use WithTabHakAksesTrait;

    /** Diganti hanya lewat setTab() — tab yang tak boleh tak bisa dipilih dari klien. */
    #[Locked]
    public string $activeTab = 'rj';

    /** Lazy-mount: komponen saldo kas baru dirender saat modal dibuka (query berat ditunda). */
    #[Locked] // menyalakannya merender saldo kas — hanya lewat openCekSaldo() yang dijaga
    public bool $showCekSaldo = false;

    /** Tab → halaman asal yang komponennya ditanam; tab tampil hanya bila boleh Lihat halaman itu. */
    protected function petaTabHalaman(): array
    {
        return [
            'rj' => 'rj.antrian-kasir',
            'ugd' => 'ugd.antrian-kasir',
            'ri' => 'kasir.antrian-ri',
            'daftar-ri' => 'kasir.daftar-ri',
            'cash-in' => 'keuangan.penerimaan-kas-tu',
            'cash-out' => 'keuangan.pengeluaran-kas-tu',
            'piutang-pasien' => 'keuangan.pembayaran-piutang-pasien',
            'list-piutang' => 'manajemen.rs.tu.piutang-pasien',
            'hutang-pbf' => 'keuangan.pembayaran-hutang-pbf',
            'hutang-non-medis' => 'keuangan.pembayaran-hutang-non-medis',
            'dp-pbf' => 'keuangan.topup-supplier-pbf',
            'dp-non-medis' => 'keuangan.topup-supplier-non-medis',
        ];
    }

    public function mount(): void
    {
        $this->pastikanTabBoleh();
    }

    public function setTab(string $tab): void
    {
        if (!$this->bolehTab($tab)) {
            return;
        }
        $this->activeTab = $tab;
    }

    /** Cek Saldo Kas = isi halaman Saldo Kas → ikut hak Lihat-nya (Role Control: Keuangan › Saldo Kas). */
    public function bolehCekSaldo(): bool
    {
        return AksesHalaman::boleh(auth()->user(), 'keuangan.saldo-kas');
    }

    public function openCekSaldo(): void
    {
        if (!$this->bolehCekSaldo()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki hak melihat Saldo Kas.');
            return;
        }
        $this->showCekSaldo = true;
        $this->dispatch('open-modal', name: 'cek-saldo-kas');
    }
};
?>

<div class="min-h-screen bg-surface-soft dark:bg-gray-900">
    <div class="px-4 py-4 mx-auto max-w-[1920px]">

        {{-- HEADER --}}
        {{-- <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-xl font-bold text-ink dark:text-white">Kasir</h1>
                <p class="text-xs text-muted dark:text-gray-400">
                    Telaah resep, pelayanan kefarmasian, &amp; kasir resep
                </p>
            </div>
        </div> --}}
        {{-- TAB NAV + AKSI --}}
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            {{-- flex-wrap: 12 tab turun ke baris kedua, bukan menggeser halaman ke kanan --}}
            <x-tabs variant="underline" class="flex-wrap flex-1 min-w-0">
                @if ($this->bolehTab('rj'))
                    <x-tab :active="$activeTab === 'rj'" color="emerald" wire:click="setTab('rj')">Rawat Jalan</x-tab>
                @endif
                @if ($this->bolehTab('ugd'))
                    <x-tab :active="$activeTab === 'ugd'" color="rose" wire:click="setTab('ugd')">UGD</x-tab>
                @endif
                @if ($this->bolehTab('ri'))
                    <x-tab :active="$activeTab === 'ri'" color="blue" wire:click="setTab('ri')">Resep Inap</x-tab>
                @endif
                @if ($this->bolehTab('daftar-ri'))
                    <x-tab :active="$activeTab === 'daftar-ri'" color="purple" wire:click="setTab('daftar-ri')">Pasien Rawat Inap</x-tab>
                @endif
                @if ($this->bolehTab('cash-in'))
                    <x-tab :active="$activeTab === 'cash-in'" color="brand" wire:click="setTab('cash-in')">Cash In</x-tab>
                @endif
                @if ($this->bolehTab('cash-out'))
                    <x-tab :active="$activeTab === 'cash-out'" color="violet" wire:click="setTab('cash-out')">Cash Out</x-tab>
                @endif
                @if ($this->bolehTab('piutang-pasien'))
                    <x-tab :active="$activeTab === 'piutang-pasien'" color="blue" wire:click="setTab('piutang-pasien')">Pembayaran Piutang Pasien</x-tab>
                @endif
                @if ($this->bolehTab('list-piutang'))
                    <x-tab :active="$activeTab === 'list-piutang'" color="purple" wire:click="setTab('list-piutang')">List Piutang Pasien</x-tab>
                @endif
                @if ($this->bolehTab('hutang-pbf'))
                    <x-tab :active="$activeTab === 'hutang-pbf'" color="rose" wire:click="setTab('hutang-pbf')">Bayar Hutang PBF</x-tab>
                @endif
                @if ($this->bolehTab('hutang-non-medis'))
                    <x-tab :active="$activeTab === 'hutang-non-medis'" color="rose" wire:click="setTab('hutang-non-medis')">Bayar Hutang Non-Medis</x-tab>
                @endif
                @if ($this->bolehTab('dp-pbf'))
                    <x-tab :active="$activeTab === 'dp-pbf'" color="emerald" wire:click="setTab('dp-pbf')">Bayar DP PBF</x-tab>
                @endif
                @if ($this->bolehTab('dp-non-medis'))
                    <x-tab :active="$activeTab === 'dp-non-medis'" color="emerald" wire:click="setTab('dp-non-medis')">Bayar DP Non-Medis</x-tab>
                @endif
            </x-tabs>

            @if ($this->bolehCekSaldo())
                <x-outline-button type="button" wire:click="openCekSaldo"
                    wire:loading.attr="disabled" wire:target="openCekSaldo" class="shrink-0 px-3 py-1.5 text-sm">
                    <x-ikon nama="cari" />
                    Cek Saldo Kas
                </x-outline-button>
            @endif
        </div>

        {{-- TAB CONTENT --}}
        <div class="mt-4">
            @if ($activeTab === '')
                <div class="p-6 text-sm text-center border border-dashed border-gray-300 rounded-xl text-muted bg-canvas dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
                    Anda belum memiliki hak akses ke tab mana pun di halaman ini.
                </div>
            @endif
            @if ($activeTab === 'rj')
                <livewire:pages::transaksi.rj.antrian-kasir-rj.antrian-kasir-rj
                    wire:key="antrian-kasir-rj-wrapper" />
            @elseif ($activeTab === 'ugd')
                <livewire:pages::transaksi.ugd.antrian-kasir-ugd.antrian-kasir-ugd
                    wire:key="antrian-kasir-ugd-wrapper" />
            @elseif ($activeTab === 'ri')
                <livewire:pages::transaksi.kasir.antrian-kasir-ri.antrian-kasir-ri
                    wire:key="antrian-kasir-ri-wrapper" />
            @elseif ($activeTab === 'daftar-ri')
                <livewire:pages::transaksi.kasir.daftar-kasir-ri.daftar-kasir-ri
                    wire:key="daftar-kasir-ri-wrapper" />
            @elseif ($activeTab === 'cash-in')
                <livewire:pages::transaksi.keuangan.penerimaan-kas-tu.penerimaan-kas-tu
                    wire:key="penerimaan-kas-tu-wrapper" />
            @elseif ($activeTab === 'cash-out')
                <livewire:pages::transaksi.keuangan.pengeluaran-kas-tu.pengeluaran-kas-tu
                    wire:key="pengeluaran-kas-tu-wrapper" />
            @elseif ($activeTab === 'piutang-pasien')
                <livewire:pages::transaksi.keuangan.pembayaran-piutang.pembayaran-piutang-pasien
                    wire:key="pembayaran-piutang-pasien-wrapper" />
            @elseif ($activeTab === 'list-piutang')
                <livewire:pages::manajemen.rs.tu.piutang-pasien.piutang-pasien
                    wire:key="list-piutang-pasien-wrapper" />
            @elseif ($activeTab === 'hutang-pbf')
                <livewire:pages::transaksi.keuangan.pembayaran-hutang-pbf.pembayaran-hutang-pbf
                    wire:key="hutang-pbf-wrapper" />
            @elseif ($activeTab === 'hutang-non-medis')
                <livewire:pages::transaksi.keuangan.pembayaran-hutang-non-medis.pembayaran-hutang-non-medis
                    wire:key="hutang-non-medis-wrapper" />
            @elseif ($activeTab === 'dp-pbf')
                <livewire:pages::transaksi.keuangan.topup-supplier-pbf.topup-supplier-pbf
                    wire:key="dp-pbf-wrapper" />
            @elseif ($activeTab === 'dp-non-medis')
                <livewire:pages::transaksi.keuangan.topup-supplier-non-medis.topup-supplier-non-medis
                    wire:key="dp-non-medis-wrapper" />
            @endif
        </div>

        {{-- MODAL CEK SALDO KAS — struktur mengikuti master-poli-actions (header + body) --}}
        <x-modal name="cek-saldo-kas" size="full" height="full" focusable>
            <div class="flex flex-col h-full">

                {{-- HEADER --}}
                <x-modul-dokumen.header judul="Cek Saldo Kas" ikon="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2-8h8a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6a2 2 0 012-2zm5 5a1 1 0 11-2 0 1 1 0 012 0z" namaModal="cek-saldo-kas">
                    Saldo kas/bank per tanggal — hanya akun yang menjadi hak akses Anda.
                </x-modul-dokumen.header>

                {{-- BODY: komponen saldo-kas, lazy via $showCekSaldo --}}
                <div class="flex-1 min-h-0">
                    @if ($showCekSaldo && $this->bolehCekSaldo())
                        <livewire:pages::transaksi.keuangan.saldo-kas.saldo-kas :embedded="true" wire:key="cek-saldo-kas-inner" />
                    @endif
                </div>
            </div>
        </x-modal>

    </div>
</div>
