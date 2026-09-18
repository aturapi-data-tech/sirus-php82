{{-- pages/transaksi/ugd/emr-ugd/perencanaan/tabs/tindak-lanjut-tab.blade.php --}}
<x-border-form :title="__('Tindak Lanjut')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="space-y-4">

        {{-- Select Tindak Lanjut --}}
        <div>
            <x-select-input wire:model.live="perencanaan.tindakLanjut.tindakLanjut" :disabled="$isFormLocked"
                :error="$errors->has('perencanaan.tindakLanjut.tindakLanjut')">
                <option value="">Pilih Tindak Lanjut</option>
                @foreach ($perencanaan['tindakLanjut']['tindakLanjutOptions'] ?? [] as $option)
                    <option value="{{ $option['tindakLanjut'] }}">
                        {{ __($option['tindakLanjut']) }}
                    </option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('perencanaan.tindakLanjut.tindakLanjut')" class="mt-1" />
        </div>

        {{-- Keterangan --}}
        <div>
            <x-text-input placeholder="Keterangan Tindak Lanjut" :error="$errors->has('perencanaan.tindakLanjut.keteranganTindakLanjut')" :disabled="$isFormLocked"
                wire:model.live="perencanaan.tindakLanjut.keteranganTindakLanjut" />
            <x-input-error :messages="$errors->get('perencanaan.tindakLanjut.keteranganTindakLanjut')" class="mt-1" />
        </div>

        {{-- Rujukan poli RS lain (SISRUTE) — menggantikan panel "Rujukan Antar RS"
             model lama (VClaim biasa): tujuan diketik sendiri, tanpa kriteria, dan
             tidak menerbitkan nomor SATUSEHAT. --}}
        @if (($perencanaan['tindakLanjut']['tindakLanjut'] ?? '') === 'Rujuk')
            <div class="pt-2 border-t border-hairline-soft dark:border-gray-700">
                <livewire:pages::transaksi.ugd.emr-ugd.rujukan-kompetensi.rm-rujukan-kompetensi-ugd-actions :rjNo="$rjNo"
                    wire:key="rm-rujukan-kompetensi-ugd-{{ $rjNo }}" />
            </div>
        @endif

        {{-- Rujukan Berbasis Kompetensi IGD (SATUSEHAT FHIR langsung) — Tindak Lanjut = Rujuk --}}
        @if (($perencanaan['tindakLanjut']['tindakLanjut'] ?? '') === 'Rujuk')
            <div class="pt-2 border-t border-hairline-soft dark:border-gray-700">
                <livewire:pages::transaksi.ugd.emr-ugd.rujukan-kompetensi.rm-rujukan-kompetensi-fhir-ugd-actions
                    :rjNo="$rjNo" wire:key="rm-rujukan-kompetensi-fhir-ugd-{{ $rjNo }}" />
            </div>
        @endif

    </div>
</x-border-form>
