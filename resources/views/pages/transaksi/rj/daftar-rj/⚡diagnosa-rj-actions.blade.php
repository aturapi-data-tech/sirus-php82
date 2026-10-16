<?php
// Komponen Modal "EMR RJ khusus Diagnosa". Dipisah dari daftar-rj supaya orchestrator
// tetap ramping (pola sama satu-sehat-rj-actions). Trigger dari parent:
// dispatch 'daftar-rj.diagnosa.open' dengan rjNo. Reuse komponen diagnosa EMR RJ
// (rm-diagnosa-rj-actions) apa adanya — diagnosis + prosedur; perubahan tercatat di log.

use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component {
    public ?int $rjNo = null;

    #[On('daftar-rj.diagnosa.open')]
    public function open(int $rjNo): void
    {
        $this->rjNo = $rjNo;
        // Load diagnosa di komponen anak + buka modal (tiru model EMR RJ).
        $this->dispatch('open-rm-diagnosa-rj', $rjNo);
        $this->dispatch('open-modal', name: 'diagnosa-rj-daftar');
    }
};
?>

<div>
    <x-modal name="diagnosa-rj-daftar" size="full" height="full" focusable>
        <div class="flex flex-col h-full">
            <x-modul-dokumen.header judul="Diagnosa" ikon="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" jalur="RJ" namaModal="diagnosa-rj-daftar">
            </x-modul-dokumen.header>

            @if (!empty($rjNo))
                {{-- DISPLAY PASIEN --}}
                <div class="px-6 pt-3">
                    <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                        wire:key="diagnosa-rj-daftar-display-pasien-{{ $rjNo }}" />
                </div>
            @endif

            <div class="flex-1 px-6 py-4 overflow-y-auto">
                <livewire:pages::transaksi.rj.emr-rj.diagnosa.rm-diagnosa-rj-actions
                    wire:key="diagnosa-rj-daftar-actions" />
            </div>

            <div class="sticky bottom-0 z-10 flex items-center justify-end gap-2 px-6 py-3 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'diagnosa-rj-daftar' })">Tutup</x-secondary-button>
                <x-primary-button type="button" x-on:click="$dispatch('save-rm-diagnosa-rj')">Simpan Diagnosa</x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
