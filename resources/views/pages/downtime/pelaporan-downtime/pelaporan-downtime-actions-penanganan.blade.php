{{-- Bagian C formulir DT-01. Partial markup murni. --}}

<x-border-form :title="__('C. Identifikasi & Penanganan')" :align="__('start')" :bgcolor="__('bg-canvas')">
    <div class="space-y-4">

        <div>
            <x-input-label value="Hasil Identifikasi Penyebab" class="mb-1" />
            <x-textarea wire:model.live="penanganan.penyebab" rows="3"
                :error="$errors->has('penanganan.penyebab')"
                placeholder="cth: listrik padam, UPS server hanya bertahan 20 menit" class="w-full" />
            <x-input-error :messages="$errors->get('penanganan.penyebab')" class="mt-1" />
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <x-input-label value="Estimasi Pemulihan yang Diinformasikan" class="mb-1" />
                <x-text-input wire:model.live="penanganan.estimasiPemulihan"
                    :error="$errors->has('penanganan.estimasiPemulihan')"
                    placeholder="cth: 2 jam" class="w-full" />
                <x-input-error :messages="$errors->get('penanganan.estimasiPemulihan')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Jam Informasi Disampaikan" class="mb-1" />
                <div class="flex items-center gap-2">
                    <x-text-input wire:model.live="penanganan.jamInformasi"
                        :error="$errors->has('penanganan.jamInformasi')" placeholder="HH:MM" class="w-full" />
                    <x-now-button wire:click="setJamInformasiSekarang"
                        title="Set ke jam sekarang" />
                </div>
                <x-input-error :messages="$errors->get('penanganan.jamInformasi')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label value="Tindakan Penanganan" class="mb-1" />
            <x-textarea wire:model.live="penanganan.tindakan" rows="4"
                :error="$errors->has('penanganan.tindakan')"
                placeholder="Satu tindakan per baris, urut waktu — baris terakhir = hasilnya" class="w-full" />
            <x-input-error :messages="$errors->get('penanganan.tindakan')" class="mt-1" />
        </div>

    </div>
</x-border-form>
