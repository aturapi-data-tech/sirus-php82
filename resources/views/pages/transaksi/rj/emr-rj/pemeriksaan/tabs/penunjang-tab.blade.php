<x-border-form :title="__('Pemeriksaan Penunjang')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="">
        <x-textarea id="pemeriksaan.penunjang" wire:model.live="pemeriksaan.penunjang"
            placeholder="Laborat / Foto / EKG / Lain-lain" :error="$errors->has('pemeriksaan.penunjang')" :disabled="$isFormLocked" rows="3" class="w-full" />
        <x-input-error :messages="$errors->get('pemeriksaan.penunjang')" class="mt-1" />
    </div>
</x-border-form>
