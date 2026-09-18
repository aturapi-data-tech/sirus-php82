<x-border-form :title="__('Rawat Inap')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="space-y-4">

        {{-- No. Referensi --}}
        <div>
            <x-input-label for="noRef" :value="__('No. Referensi')" />
            <x-text-input id="noRef" placeholder="No. Referensi Rawat Inap" class="mt-1" :error="$errors->has('perencanaan.rawatInap.noRef')"
                :disabled="$isFormLocked" wire:model.live="perencanaan.rawatInap.noRef" />
            <x-input-error :messages="$errors->get('perencanaan.rawatInap.noRef')" class="mt-1" />
        </div>

        {{-- Tanggal --}}
        <div>
            <x-input-label for="tanggal" :value="__('Tanggal')" />
            <x-text-input id="tanggal" placeholder="Tanggal [dd/mm/yyyy]" class="mt-1" :error="$errors->has('perencanaan.rawatInap.tanggal')"
                :disabled="$isFormLocked" wire:model.live="perencanaan.rawatInap.tanggal" />
            <x-input-error :messages="$errors->get('perencanaan.rawatInap.tanggal')" class="mt-1" />
        </div>

        {{-- Keterangan --}}
        <div>
            <x-input-label for="keterangan" :value="__('Keterangan')" />
            <x-textarea id="keterangan" placeholder="Keterangan Rawat Inap" class="mt-1" :rows="3"
                :error="$errors->has('perencanaan.rawatInap.keterangan')" :disabled="$isFormLocked"
                wire:model.live="perencanaan.rawatInap.keterangan" />
            <x-input-error :messages="$errors->get('perencanaan.rawatInap.keterangan')" class="mt-1" />
        </div>

    </div>
</x-border-form>
