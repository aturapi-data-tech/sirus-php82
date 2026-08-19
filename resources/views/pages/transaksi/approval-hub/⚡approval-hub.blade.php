<?php
// resources/views/pages/transaksi/approval-hub/approval-hub.blade.php
// Wrapper halaman Approval Hub — tab per modul (Casemix RJ/UGD/RI, SATUSEHAT, dll).

use Livewire\Component;
use Livewire\Attributes\Session;

new class extends Component {
    #[Session(key: 'approval-hub-activeTab')]
    public string $activeTab = 'casemix-rj';

    private const TABS = ['casemix-rj', 'casemix-ugd', 'casemix-ri', 'satusehat', 'bundling'];

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
            <x-tab :active="$activeTab === 'casemix-rj'" color="emerald" wire:click="setTab('casemix-rj')">Casemix RJ</x-tab>
            <x-tab :active="$activeTab === 'casemix-ugd'" color="amber" wire:click="setTab('casemix-ugd')">Casemix UGD</x-tab>
            <x-tab :active="$activeTab === 'casemix-ri'" color="violet" wire:click="setTab('casemix-ri')">Casemix RI</x-tab>
            <x-tab :active="$activeTab === 'bundling'" color="indigo" wire:click="setTab('bundling')">Bundling Klaim</x-tab>
            <x-tab :active="$activeTab === 'satusehat'" color="teal" wire:click="setTab('satusehat')">SATUSEHAT RJ</x-tab>
        </x-tabs>

        {{-- TAB CONTENT --}}
        <div class="mt-4">
            @if ($activeTab === 'casemix-rj')
                <livewire:pages::transaksi.approval-hub.casemix-queue.casemix-queue
                    queue-type="RJ"
                    wire:key="casemix-queue-rj" />
            @elseif ($activeTab === 'casemix-ugd')
                <livewire:pages::transaksi.approval-hub.casemix-queue.casemix-queue
                    queue-type="UGD"
                    wire:key="casemix-queue-ugd" />
            @elseif ($activeTab === 'casemix-ri')
                <livewire:pages::transaksi.approval-hub.casemix-queue.casemix-queue
                    queue-type="RI"
                    wire:key="casemix-queue-ri" />
            @elseif ($activeTab === 'satusehat')
                <livewire:pages::transaksi.approval-hub.satusehat-queue.satusehat-queue
                    wire:key="satusehat-queue-wrapper" />
            @elseif ($activeTab === 'bundling')
                <livewire:pages::transaksi.approval-hub.bundling-klaim.bundling-klaim
                    wire:key="bundling-klaim-wrapper" />
            @endif
        </div>

    </div>

</div>
