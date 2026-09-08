{{-- pages/transaksi/ugd/emr-ugd/modul-dokumen/suket/tab/suket-istirahat-tab.blade.php --}}
<div class="pt-0">

    {{-- Mulai Istirahat + Jumlah Hari — 1 baris (grid 4 kolom) --}}
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-2">
    <div class="sm:col-span-2">
        <x-input-label value="Mulai Istirahat" :required="false" />
        <x-select-input class="mt-1 ml-2" :disabled="$isFormLocked" :error="$errors->has('dataDaftarUGD.suket.suketIstirahat.mulaiIstirahat')"
            wire:model.live="dataDaftarUGD.suket.suketIstirahat.mulaiIstirahat">
            @foreach ($dataDaftarUGD['suket']['suketIstirahat']['mulaiIstirahatOptions'] ?? [] as $option)
                @php
                    // Kompat: struktur baru ['value','label'] + fallback struktur lama ['mulaiIstirahat']
                    $optValue = $option['value'] ?? $option['mulaiIstirahat'] ?? '';
                    $optLabel = $option['label'] ?? $option['mulaiIstirahat'] ?? '';
                @endphp
                <option value="{{ $optValue }}">{{ $optLabel }}</option>
            @endforeach
        </x-select-input>
        <x-input-error :messages="$errors->get('dataDaftarUGD.suket.suketIstirahat.mulaiIstirahat')" class="mt-1" />
    </div>

    {{-- Jumlah Hari Istirahat --}}
    <div class="sm:col-span-1">
        <x-input-label value="Jumlah Hari Istirahat" :required="false" />
        <x-text-input-mou placeholder="0" class="mt-1 ml-2" :error="$errors->has('dataDaftarUGD.suket.suketIstirahat.suketIstirahatHari')" :disabled="$isFormLocked" :mou_label="__('Hari')"
            wire:model.live="dataDaftarUGD.suket.suketIstirahat.suketIstirahatHari" />
        <x-input-error :messages="$errors->get('dataDaftarUGD.suket.suketIstirahat.suketIstirahatHari')" class="mt-1" />
    </div>
    </div>{{-- /grid 2 kolom --}}

    {{-- Keterangan Istirahat --}}
    <div class="mb-2">
        <x-input-label value="Keterangan" :required="false" />
        <x-textarea placeholder="Tuliskan keterangan surat istirahat pasien..." class="mt-1 ml-2 max-w-2xl" :error="$errors->has('dataDaftarUGD.suket.suketIstirahat.suketIstirahat')"
            :disabled="$isFormLocked" wire:model.live="dataDaftarUGD.suket.suketIstirahat.suketIstirahat" rows="3" />
        <x-input-error :messages="$errors->get('dataDaftarUGD.suket.suketIstirahat.suketIstirahat')" class="mt-1" />
    </div>

    {{-- TOMBOL CETAK --}}
    <div class="flex justify-end mt-3">
        <x-secondary-button wire:click="cetakSuketSakit" wire:loading.attr="disabled" wire:target="cetakSuketSakit">
            <span wire:loading.remove wire:target="cetakSuketSakit" class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5z" />
                </svg>
                Cetak Surat Sakit
            </span>
            <span wire:loading wire:target="cetakSuketSakit" class="flex items-center gap-1">
                <x-loading /> Mencetak...
            </span>
        </x-secondary-button>
    </div>

</div>
