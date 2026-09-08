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
        <x-cetak-button wire:click="cetakSuketSakit" label="Cetak Surat Sakit" />
    </div>

</div>
