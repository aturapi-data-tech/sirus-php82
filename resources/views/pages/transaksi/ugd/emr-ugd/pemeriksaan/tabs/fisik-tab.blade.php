{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/fisik-tab.blade.php --}}
<x-border-form :title="__('Pemeriksaan Fisik')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="">
        <x-textarea wire:model.live="pemeriksaan.fisik" placeholder="Pemeriksaan Fisik" :error="$errors->has('pemeriksaan.fisik')"
            :disabled="$isFormLocked" rows="3" class="w-full" />
        <x-input-error :messages="$errors->get('pemeriksaan.fisik')" class="mt-1" />
    </div>
</x-border-form>
