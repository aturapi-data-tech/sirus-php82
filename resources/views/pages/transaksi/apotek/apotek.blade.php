<?php
// resources/views/pages/transaksi/apotek/apotek.blade.php
// Wrapper 1 halaman untuk Antrian Apotek RJ & UGD dalam tab

use Livewire\Component;

new class extends Component {
    public string $activeTab = 'rj';

    public function setTab(string $tab): void
    {
        if (!in_array($tab, ['rj', 'ugd'])) {
            return;
        }
        $this->activeTab = $tab;
    }
};
?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="px-4 py-4 mx-auto max-w-[1920px]">

        {{-- HEADER --}}
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">Antrian Apotek</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Telaah resep &amp; pelayanan kefarmasian — Rawat Jalan &amp; UGD
                </p>
            </div>
        </div>

        {{-- TAB NAV --}}
        <div class="flex border-b border-gray-200 dark:border-gray-700">
            <button type="button" wire:click="setTab('rj')"
                class="px-4 py-2 -mb-px text-sm font-medium transition border-b-2 {{ $activeTab === 'rj' ? 'text-emerald-700 border-emerald-600 dark:text-emerald-300 dark:border-emerald-400' : 'text-gray-500 border-transparent hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                Rawat Jalan
            </button>
            <button type="button" wire:click="setTab('ugd')"
                class="px-4 py-2 -mb-px text-sm font-medium transition border-b-2 {{ $activeTab === 'ugd' ? 'text-rose-700 border-rose-600 dark:text-rose-300 dark:border-rose-400' : 'text-gray-500 border-transparent hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                UGD
            </button>
        </div>

        {{-- TAB CONTENT --}}
        <div class="mt-4">
            @if ($activeTab === 'rj')
                <livewire:pages::transaksi.rj.antrian-apotek-rj.antrian-apotek-rj
                    wire:key="antrian-apotek-rj-wrapper" />
            @elseif ($activeTab === 'ugd')
                <livewire:pages::transaksi.ugd.antrian-apotek-ugd.antrian-apotek-ugd
                    wire:key="antrian-apotek-ugd-wrapper" />
            @endif
        </div>

    </div>
</div>
