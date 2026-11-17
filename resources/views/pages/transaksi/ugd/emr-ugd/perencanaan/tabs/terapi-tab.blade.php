{{-- pages/transaksi/ugd/emr-ugd/perencanaan/tabs/terapi-tab.blade.php --}}
<div class="space-y-3">

    {{-- Textarea Terapi --}}
    <div>
        <x-textarea placeholder="Terapi" :error="$errors->has('perencanaan.terapi.terapi')" :disabled="$isFormLocked" :rows="7"
            wire:model.live="perencanaan.terapi.terapi" />
        <x-input-error :messages="$errors->get('perencanaan.terapi.terapi')" class="mt-1" />
    </div>

    {{-- Shortcut tombol E-Resep — auto-save SOAP child dulu, tunggu konfirmasi,
         baru buka modal eresep. Logika identik dengan tombol toolbar parent EMR
         (lihat emr-ugd.blade.php → "Tombol E-Resep"). --}}
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
                                    'save-rm-anamnesa-ugd',
                                    'save-rm-pemeriksaan-ugd',
                                    'save-rm-diagnosa-ugd',
                                    'save-rm-perencanaan-ugd',
                                ];
                                let saved = 0;
                                const onSaved = () => saved++;
                                window.addEventListener('refresh-after-ugd.saved', onSaved);
                                try {
                                    events.forEach(e => Livewire.dispatch(e, { silent: true }));
                                    const deadline = Date.now() + 3000;
                                    while (saved < events.length && Date.now() < deadline) {
                                        await new Promise(r => setTimeout(r, 50));
                                    }
                                } finally {
                                    window.removeEventListener('refresh-after-ugd.saved', onSaved);
                                }
                            }
                            // Dispatch event sama dengan parent.openEresep() agar modal e-resep terbuka
                            Livewire.dispatch('emr-ugd.eresep.open', { rjNo: rjNo });
                            Livewire.dispatch('open-eresep-non-racikan-ugd', { rjNo: rjNo });
                            Livewire.dispatch('open-eresep-racikan-ugd', { rjNo: rjNo });
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
