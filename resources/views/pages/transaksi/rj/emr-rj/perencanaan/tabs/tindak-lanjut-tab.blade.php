<x-border-form :title="__('Tindak Lanjut')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="space-y-4">

        {{-- Select Tindak Lanjut --}}
        <div>
            <x-select-input id="tindakLanjut" wire:model.live="perencanaan.tindakLanjut.tindakLanjut"
                :disabled="$isFormLocked" :error="$errors->has('perencanaan.tindakLanjut.tindakLanjut')">
                <option value="">Pilih Tindak Lanjut</option>
                @foreach ($perencanaan['tindakLanjut']['tindakLanjutOptions'] ?? [] as $option)
                    <option value="{{ $option['tindakLanjut'] }}">
                        {{ __($option['tindakLanjut']) }}
                    </option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('perencanaan.tindakLanjut.tindakLanjut')" class="mt-1" />
        </div>

        {{-- Kondisi Saat Pulang (bab 25) & Prognosis (bab 22) — dikirim ke Satu Sehat. Buka-tutup seperti
             Status Psikiatri: tertutup, terbuka sendiri bila salah satunya sudah dipilih. --}}
        <x-border-form :title="__('Kondisi Pulang & Prognosis')" :collapsible="true" padding="p-3"
            :open="filled($perencanaan['kondisiPulang']['kondisiPulang'] ?? null) || filled($perencanaan['prognosis']['prognosis'] ?? null)">
        <div class="space-y-3">
        <div>
            <x-input-label value="Kondisi Saat Pulang" />
            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1">
                @foreach (array_keys(\App\Support\Terminologi\KondisiPulang::OPTIONS) as $kondisi)
                    <x-radio-button :label="$kondisi" :value="$kondisi" name="kondisiPulang-rj"
                        wire:model.live="perencanaan.kondisiPulang.kondisiPulang" :disabled="$isFormLocked" />
                @endforeach
            </div>
        </div>

        {{-- Prognosis (Playbook RJ bab 22) — dikirim ke Satu Sehat sebagai ClinicalImpression SNOMED --}}
        <div>
            <x-input-label value="Prognosis" />
            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1">
                @foreach (array_keys(\App\Support\Terminologi\Prognosis::OPTIONS) as $prognosis)
                    <x-radio-button :label="$prognosis" :value="$prognosis" name="prognosis-rj"
                        wire:model.live="perencanaan.prognosis.prognosis" :disabled="$isFormLocked" />
                @endforeach
            </div>
            <x-text-input wire:model.live="perencanaan.prognosis.keteranganPrognosis" :disabled="$isFormLocked"
                placeholder="Keterangan prognosis (opsional), mis. baik bila patuh minum obat" class="w-full mt-2" />
        </div>
        </div>
        </x-border-form>

        {{-- Keterangan --}}
        <div>
            <x-text-input id="keteranganTindakLanjut" placeholder="Keterangan Tindak Lanjut" :error="$errors->has('perencanaan.tindakLanjut.keteranganTindakLanjut')"
                :disabled="$isFormLocked" wire:model.live="perencanaan.tindakLanjut.keteranganTindakLanjut" />
            <x-input-error :messages="$errors->get('perencanaan.tindakLanjut.keteranganTindakLanjut')" class="mt-1" />
        </div>

        {{-- Status PRB toggle dipindah ke modal E-Resep --}}

        {{-- SKDP — tampil hanya jika Tindak Lanjut = Kontrol --}}
        @if (($perencanaan['tindakLanjut']['tindakLanjut'] ?? '') === 'Kontrol')
            <div class="pt-2 border-t border-hairline-soft dark:border-gray-700">
                <livewire:pages::transaksi.rj.emr-rj.skdp.rm-skdp-rj-actions :rjNo="$rjNo"
                    wire:key="rm-skdp-rj-{{ $rjNo }}" />
            </div>
        @endif

        {{-- PRB — tampil hanya jika Tindak Lanjut = PRB --}}
        {{-- @if (($perencanaan['tindakLanjut']['tindakLanjut'] ?? '') === 'PRB')
            <div class="pt-2 border-t border-hairline-soft dark:border-gray-700">
                <livewire:pages::transaksi.rj.emr-rj.prb.rm-prb-rj-actions :rjNo="$rjNo"
                    wire:key="rm-prb-rj-{{ $rjNo }}" />
            </div>
        @endif --}}

    </div>
</x-border-form>
