{{-- pages/transaksi/ugd/emr-ugd/modul-dokumen/suket/tab/suket-sehat-tab.blade.php --}}
<div class="pt-0">

    {{-- Keterangan Sehat --}}
    <div class="mb-2">
        <x-input-label value="Keterangan" :required="false" />
        <x-textarea placeholder="Tuliskan keterangan surat sehat pasien..." class="mt-1 ml-2 max-w-2xl" :error="$errors->has('dataDaftarUGD.suket.suketSehat.suketSehat')"
            :disabled="$isFormLocked" wire:model.live="dataDaftarUGD.suket.suketSehat.suketSehat" rows="3" />
        <x-input-error :messages="$errors->get('dataDaftarUGD.suket.suketSehat.suketSehat')" class="mt-1" />
    </div>

    {{-- TOMBOL CETAK --}}
    <div class="flex justify-end mt-3">
        <x-cetak-button wire:click="cetakSuketSehat" label="Cetak Surat Sehat" />
    </div>

</div>
