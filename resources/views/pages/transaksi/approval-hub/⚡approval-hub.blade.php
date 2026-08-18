<?php
// resources/views/pages/transaksi/approval-hub/approval-hub.blade.php
// Wrapper halaman Approval Hub — tab per modul (Casemix, SATUSEHAT, dll).

use Livewire\Component;

new class extends Component {
    public string $activeTab = 'casemix';

    private const TABS = ['casemix', 'bundling'];

    public function mount(): void
    {
        $tab = (string) request()->query('tab', '');
        if (in_array($tab, self::TABS, true)) {
            $this->activeTab = $tab;
        }
    }

    public function setTab(string $tab): void
    {
        if (!in_array($tab, self::TABS, true)) {
            return;
        }
        $this->activeTab = $tab;
    }
};
?>

<div class="min-h-screen bg-surface-soft dark:bg-gray-900">
    <div class="px-4 py-4 mx-auto max-w-[1920px]">

        {{-- TAB NAV --}}
        <x-tabs variant="underline">
            <x-tab :active="$activeTab === 'casemix'" color="emerald" wire:click="setTab('casemix')">Casemix / E-Klaim</x-tab>
            <x-tab :active="$activeTab === 'bundling'" color="indigo" wire:click="setTab('bundling')">Bundling Klaim</x-tab>
            {{-- Tab berikutnya tinggal tambah di sini --}}
            {{-- <x-tab :active="$activeTab === 'satusehat'" color="blue" wire:click="setTab('satusehat')">SATUSEHAT</x-tab> --}}
        </x-tabs>

        {{-- TAB CONTENT --}}
        <div class="mt-4">
            @if ($activeTab === 'casemix')
                <livewire:pages::transaksi.approval-hub.casemix-queue.casemix-queue
                    wire:key="casemix-queue-wrapper" />
            @elseif ($activeTab === 'bundling')
                <livewire:pages::transaksi.approval-hub.bundling-klaim.bundling-klaim
                    wire:key="bundling-klaim-wrapper" />
            @endif
        </div>

    </div>

</div>
