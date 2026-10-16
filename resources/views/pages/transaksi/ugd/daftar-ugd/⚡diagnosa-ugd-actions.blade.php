<?php
// Komponen Modal "EMR UGD khusus Diagnosa". Dipisah dari daftar-ugd supaya orchestrator
// tetap ramping (pola sama satu-sehat / diagnosa-rj-actions). Trigger dari parent:
// dispatch 'daftar-ugd.diagnosa.open' dengan rjNo. Reuse komponen diagnosa EMR UGD
// (rm-diagnosa-ugd-actions) apa adanya — diagnosis + prosedur; perubahan tercatat di log.

use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component {
    public ?int $rjNo = null;

    #[On('daftar-ugd.diagnosa.open')]
    public function open(int $rjNo): void
    {
        $this->rjNo = $rjNo;
        $this->dispatch('open-rm-diagnosa-ugd', $rjNo);
        $this->dispatch('open-modal', name: 'diagnosa-ugd-daftar');
    }
};
?>

<div>
    <x-modal name="diagnosa-ugd-daftar" size="full" height="full" focusable>
        <div class="flex flex-col h-full">
            <x-modul-dokumen.header judul="Diagnosa" ikon="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" jalur="UGD" namaModal="diagnosa-ugd-daftar">
            </x-modul-dokumen.header>

            @if (!empty($rjNo))
                {{-- DISPLAY PASIEN --}}
                <div class="px-6 pt-3">
                    <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                        wire:key="diagnosa-ugd-daftar-display-pasien-{{ $rjNo }}" />
                </div>
            @endif

            <div class="flex-1 px-6 py-4 overflow-y-auto">
                <livewire:pages::transaksi.ugd.emr-ugd.diagnosa.rm-diagnosa-ugd-actions
                    wire:key="diagnosa-ugd-daftar-actions" />
            </div>

            <div class="sticky bottom-0 z-10 flex items-center justify-end gap-2 px-6 py-3 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'diagnosa-ugd-daftar' })">Tutup</x-secondary-button>
                <x-primary-button type="button" x-on:click="$dispatch('save-rm-diagnosa-ugd')">Simpan Diagnosa</x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
