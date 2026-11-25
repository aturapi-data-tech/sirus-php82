<?php
// resources/views/pages/transaksi/casemix/casemix.blade.php
// Wrapper 1 halaman untuk Daftar Pasien Bulanan RJ / UGD / RI dalam tab — view Casemix

use Livewire\Component;
use Livewire\Attributes\Locked;
use App\Http\Traits\Concerns\WithTabHakAksesTrait;

new class extends Component {
    use WithTabHakAksesTrait;

    /** Diganti hanya lewat setTab() — tab yang tak boleh tak bisa dipilih dari klien. */
    #[Locked]
    public string $activeTab = 'rj';

    /** Tab → halaman asal yang komponennya ditanam; tab tampil hanya bila boleh Lihat halaman itu. */
    protected function petaTabHalaman(): array
    {
        return [
            'rj' => 'rj.daftar-bulanan',
            'ugd' => 'ugd.daftar-bulanan',
            'ri' => 'ri.daftar-bulanan',
            'rekap-rj' => 'casemix', // Rekap iDRG tak punya halaman asal lain → hak Lihat Casemix sendiri
            'rekap' => 'casemix',
        ];
    }

    public function mount(): void
    {
        // Deep-link dari AppMenu: /transaksi/casemix?tab=rekap-rj
        $tab = (string) request()->query('tab', '');
        if (array_key_exists($tab, $this->petaTabHalaman())) {
            $this->activeTab = $tab;
        }
        $this->pastikanTabBoleh();
    }

    public function setTab(string $tab): void
    {
        if (!$this->bolehTab($tab)) {
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
            @if ($this->bolehTab('rj'))
                <x-tab :active="$activeTab === 'rj'" color="emerald" wire:click="setTab('rj')">Rawat Jalan</x-tab>
            @endif
            @if ($this->bolehTab('ugd'))
                <x-tab :active="$activeTab === 'ugd'" color="rose" wire:click="setTab('ugd')">UGD</x-tab>
            @endif
            @if ($this->bolehTab('ri'))
                <x-tab :active="$activeTab === 'ri'" color="blue" wire:click="setTab('ri')">Rawat Inap</x-tab>
            @endif
            @if ($this->bolehTab('rekap-rj'))
                <x-tab :active="$activeTab === 'rekap-rj'" color="violet" wire:click="setTab('rekap-rj')">Rekap iDRG Rawat Jalan</x-tab>
            @endif
            @if ($this->bolehTab('rekap'))
                <x-tab :active="$activeTab === 'rekap'" color="violet" wire:click="setTab('rekap')">Rekap iDRG Rawat Inap</x-tab>
            @endif
        </x-tabs>

        {{-- TAB CONTENT --}}
        <div class="mt-4">
            @if ($activeTab === '')
                <div class="p-6 text-sm text-center border border-dashed border-gray-300 rounded-xl text-muted bg-canvas dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
                    Anda belum memiliki hak akses ke tab mana pun di halaman ini.
                </div>
            @endif
            @if ($activeTab === 'rj')
                <livewire:pages::transaksi.rj.daftar-rj-bulanan.daftar-rj-bulanan
                    wire:key="daftar-rj-bulanan-wrapper" />
            @elseif ($activeTab === 'ugd')
                <livewire:pages::transaksi.ugd.daftar-ugd-bulanan.daftar-ugd-bulanan
                    wire:key="daftar-ugd-bulanan-wrapper" />
            @elseif ($activeTab === 'ri')
                <livewire:pages::transaksi.ri.daftar-ri-bulanan.daftar-ri-bulanan
                    wire:key="daftar-ri-bulanan-wrapper" />
            @elseif ($activeTab === 'rekap-rj')
                <livewire:pages::transaksi.casemix.rekap-idrg-rj.rekap-idrg-rj wire:key="rekap-idrg-rj-wrapper" />
            @elseif ($activeTab === 'rekap')
                <livewire:pages::transaksi.casemix.rekap-idrg-ri.rekap-idrg-ri wire:key="rekap-idrg-ri-wrapper" />
            @endif
        </div>

    </div>
</div>
