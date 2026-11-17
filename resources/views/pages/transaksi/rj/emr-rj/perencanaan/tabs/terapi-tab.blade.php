<div class="space-y-3">

    {{-- Textarea Terapi --}}
    <div>
        <x-textarea id="terapi" placeholder="Terapi" :error="$errors->has('perencanaan.terapi.terapi')" :disabled="$isFormLocked" :rows="7"
            wire:model.live="perencanaan.terapi.terapi" />
        <x-input-error :messages="$errors->get('perencanaan.terapi.terapi')" class="mt-1" />
    </div>

    {{-- Shortcut tombol E-Resep — auto-save SOAP child dulu, tunggu konfirmasi,
         baru buka modal eresep. Logika identik dengan tombol toolbar parent EMR
         (lihat emr-rj.blade.php → "Tombol E-Resep"). --}}
    @hasanyrole('Dokter|Admin|Perawat')
        <div class="flex justify-end">
            <x-primary-button type="button" class="gap-1"
                x-data="{
                    loadingEresep: false,
                    async openEresepWithSave(rjNo) {
                        if (this.loadingEresep) return;
                        this.loadingEresep = true;
                        try {
                            if (!$wire.isFormLocked) {
                                const events = [
                                    'save-rm-anamnesa-rj',
                                    'save-rm-pemeriksaan-rj',
                                    'save-rm-diagnosa-rj',
                                    'save-rm-perencanaan-rj',
                                ];
                                let saved = 0;
                                const onSaved = () => saved++;
                                window.addEventListener('refresh-after-rj.saved', onSaved);
                                try {
                                    events.forEach(e => Livewire.dispatch(e, { silent: true }));
                                    const deadline = Date.now() + 3000;
                                    while (saved < events.length && Date.now() < deadline) {
                                        await new Promise(r => setTimeout(r, 50));
                                    }
                                } finally {
                                    window.removeEventListener('refresh-after-rj.saved', onSaved);
                                }
                            }
                            // Dispatch event sama dengan parent.openEresep() agar modal e-resep terbuka
                            Livewire.dispatch('emr-rj.eresep.open', { rjNo: rjNo });
                            Livewire.dispatch('open-eresep-non-racikan-rj', { rjNo: rjNo });
                            Livewire.dispatch('open-eresep-racikan-rj', { rjNo: rjNo });
                        } finally {
                            this.loadingEresep = false;
                        }
                    }
                }"
                x-bind:disabled="loadingEresep"
                x-on:click.prevent="openEresepWithSave({{ $rjNo }})">
                <span x-show="!loadingEresep" class="flex items-center gap-1">
                    <x-ikon nama="dokumen" />E-Resep
                </span>
                <span x-show="loadingEresep" x-cloak class="flex items-center gap-1">
                    <x-loading /> Menyimpan & memuat...
                </span>
            </x-primary-button>
        </div>
    @endhasanyrole

</div>
