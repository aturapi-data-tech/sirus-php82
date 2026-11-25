<?php
// resources/views/pages/transaksi/apotek/apotek.blade.php
// Wrapper 1 halaman untuk Antrian Apotek RJ & UGD dalam tab

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
            'rj' => 'rj.antrian-apotek',
            'ugd' => 'ugd.antrian-apotek',
            'ri' => 'ri-resep.antrian',
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
    <div class="px-4 pt-2 pb-4 mx-auto max-w-[1920px]">

        {{-- HEADER --}}
        {{-- <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-xl font-bold text-ink dark:text-white">Apotek</h1>
                <p class="text-xs text-muted dark:text-gray-400">
                    Telaah resep, pelayanan kefarmasian, &amp; kasir resep
                </p>
            </div>
        </div> --}}
        {{-- TAB NAV + AKSI --}}
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            {{-- Varian chip. Prop color sengaja dilepas: varian chip & card selalu
                 memakai warna brand untuk state aktif (lihat komponen tab), jadi
                 emerald/rose/blue tak lagi berpengaruh. --}}
            <x-tabs variant="chip">
                @if ($this->bolehTab('rj'))
                    <x-tab :active="$activeTab === 'rj'" wire:click="setTab('rj')">Rawat Jalan</x-tab>
                @endif
                @if ($this->bolehTab('ugd'))
                    <x-tab :active="$activeTab === 'ugd'" wire:click="setTab('ugd')">UGD</x-tab>
                @endif
                @if ($this->bolehTab('ri'))
                    <x-tab :active="$activeTab === 'ri'" wire:click="setTab('ri')">Rawat Inap</x-tab>
                @endif
            </x-tabs>

            @if ($this->bolehCekSaldo())
                <x-outline-button type="button" wire:click="openCekSaldo"
                    wire:loading.attr="disabled" wire:target="openCekSaldo" class="shrink-0 px-3 py-1.5 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2-8h8a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6a2 2 0 012-2zm5 5a1 1 0 11-2 0 1 1 0 012 0z" />
                    </svg>
                    Cek Saldo Kas
                </x-outline-button>
            @endif
        </div>

        {{-- TAB CONTENT --}}
        <div class="mt-2">
            @if ($activeTab === '')
                <div class="p-6 text-sm text-center border border-dashed border-gray-300 rounded-xl text-muted bg-canvas dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
                    Anda belum memiliki hak akses ke tab mana pun di halaman ini.
                </div>
            @endif
            @if ($activeTab === 'rj')
                <livewire:pages::transaksi.rj.antrian-apotek-rj.antrian-apotek-rj
                    wire:key="antrian-apotek-rj-wrapper" />
            @elseif ($activeTab === 'ugd')
                <livewire:pages::transaksi.ugd.antrian-apotek-ugd.antrian-apotek-ugd
                    wire:key="antrian-apotek-ugd-wrapper" />
            @elseif ($activeTab === 'ri')
                <livewire:pages::transaksi.ri-resep.antrian-ri-resep.antrian-ri-resep
                    wire:key="ri-resep-antrian-wrapper" />
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
