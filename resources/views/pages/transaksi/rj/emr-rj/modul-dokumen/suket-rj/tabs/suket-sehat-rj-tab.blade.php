{{-- SUKET SEHAT TAB --}}
<div class="pt-0">

    {{-- Keterangan Sehat --}}
    <div class="mb-2">
        <x-input-label for="dataDaftarPoliRJ.suket.suketSehat.suketSehat" :value="__('Keterangan')" :required="__(false)" />

        <x-textarea id="dataDaftarPoliRJ.suket.suketSehat.suketSehat"
            placeholder="Tuliskan keterangan surat sehat pasien..." class="mt-1 ml-2 max-w-2xl" :error="$errors->has('dataDaftarPoliRJ.suket.suketSehat.suketSehat')" :disabled="$isFormLocked"
            wire:model.live="dataDaftarPoliRJ.suket.suketSehat.suketSehat" rows="3" />

        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.suket.suketSehat.suketSehat')" class="mt-1" />
    </div>

    {{-- TOMBOL CETAK --}}
    <div class="flex justify-end mt-3">
        <x-cetak-button wire:click="cetakSuketSehat" label="Cetak Surat Sehat" />
    </div>

</div>
