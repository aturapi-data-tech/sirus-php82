<x-border-form :title="__('Pemeriksaan Fisik dan Uji Fungsi')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="">

        <x-textarea id="pemeriksaan.FisikujiFungsi.FisikujiFungsi"
            wire:model.live="pemeriksaan.FisikujiFungsi.FisikujiFungsi"
            placeholder="Pemeriksaan Fisik dan Uji Fungsi" :error="$errors->has('pemeriksaan.FisikujiFungsi.FisikujiFungsi')" :disabled="$isFormLocked" rows="3"
            class="w-full" />

        <x-input-error :messages="$errors->get('pemeriksaan.FisikujiFungsi.FisikujiFungsi')" class="mt-1" />

    </div>
</x-border-form>
