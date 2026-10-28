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

        {{-- Kondisi Saat Keluar (Playbook IGD bab 25) — dikirim ke Satu Sehat. Buka-tutup seperti
             Status Psikiatri: tertutup, terbuka sendiri bila sudah dipilih. --}}
        <x-border-form :title="__('Kondisi Saat Keluar')" :collapsible="true" padding="p-3"
            :open="filled($perencanaan['kondisiPulang']['kondisiPulang'] ?? null)">
            <div class="flex flex-wrap gap-x-4 gap-y-1">
                @foreach (array_keys(\App\Support\Terminologi\KondisiPulang::OPTIONS) as $kondisi)
                    <x-radio-button :label="$kondisi" :value="$kondisi" name="kondisiPulang-ugd"
                        wire:model.live="perencanaan.kondisiPulang.kondisiPulang" :disabled="$isFormLocked" />
                @endforeach
            </div>
        </x-border-form>

        {{-- Rujukan Antar RS — tampil hanya jika Tindak Lanjut = Rujuk --}}
        @if (($perencanaan['tindakLanjut']['tindakLanjut'] ?? '') === 'Rujuk')
            <div class="pt-2 border-t border-hairline-soft dark:border-gray-700">
                <livewire:pages::transaksi.ugd.emr-ugd.rujukan-antar-rs.rm-rujukan-ugd-actions :rjNo="$rjNo"
                    wire:key="rm-rujukan-ugd-{{ $rjNo }}" />
            </div>
        @endif

    </div>
</x-border-form>
