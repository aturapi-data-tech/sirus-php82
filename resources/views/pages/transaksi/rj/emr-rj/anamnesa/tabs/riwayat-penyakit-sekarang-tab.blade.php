<x-border-form :title="__('Riwayat Penyakit Sekarang')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="">

        <x-textarea id="anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum"
            wire:model.live="anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum"
            placeholder="Deskripsi Anamnesis" :error="$errors->has(
                'anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum',
            )" :disabled="$isFormLocked" :rows="3" class="w-full" />

        <x-input-error :messages="$errors->get('anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum')" class="mt-1" />

    </div>
</x-border-form>
