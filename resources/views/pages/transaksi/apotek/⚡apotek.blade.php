<?php
// resources/views/pages/transaksi/apotek/apotek.blade.php
// Wrapper 1 halaman untuk Antrian Apotek RJ & UGD dalam tab

use Livewire\Component;

new class extends Component {
    public string $activeTab = 'rj';

    /** Lazy-mount: komponen saldo kas baru dirender saat modal dibuka (query berat ditunda). */
    public bool $showCekSaldo = false;

    public function setTab(string $tab): void
    {
        if (!in_array($tab, ['rj', 'ugd', 'ri'])) {
            return;
        }
        $this->activeTab = $tab;
    }

    public function openCekSaldo(): void
    {
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
                <x-tab :active="$activeTab === 'rj'" wire:click="setTab('rj')">Rawat Jalan</x-tab>
                <x-tab :active="$activeTab === 'ugd'" wire:click="setTab('ugd')">UGD</x-tab>
                <x-tab :active="$activeTab === 'ri'" wire:click="setTab('ri')">Rawat Inap</x-tab>
            </x-tabs>

            <x-outline-button type="button" wire:click="openCekSaldo"
                wire:loading.attr="disabled" wire:target="openCekSaldo" class="shrink-0 px-3 py-1.5 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2-8h8a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6a2 2 0 012-2zm5 5a1 1 0 11-2 0 1 1 0 012 0z" />
                </svg>
                Cek Saldo Kas
            </x-outline-button>
        </div>

        {{-- TAB CONTENT --}}
        <div class="mt-2">
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
                    @if ($showCekSaldo)
                        <livewire:pages::transaksi.keuangan.saldo-kas.saldo-kas :embedded="true" wire:key="cek-saldo-kas-inner" />
                    @endif
                </div>
            </div>
        </x-modal>

    </div>
</div>
