@php
    $psikiatri = $pemeriksaan['pemeriksaanPsikiatri'] ?? [];
    $isiPikir = $psikiatri['prosesBerpikir']['isiPikir'] ?? [];
@endphp

<x-border-form :title="__('Pemeriksaan Psikiatri')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="space-y-4">

        {{-- Kesadaran --}}
        <div>
            <x-input-label value="Kesadaran" />
            <x-text-input wire:model.live="pemeriksaan.pemeriksaanPsikiatri.kesadaran" placeholder="Kesadaran"
                :error="$errors->has('pemeriksaan.pemeriksaanPsikiatri.kesadaran')" :disabled="$isFormLocked" class="w-full mt-1" />
        </div>

        {{-- Kontak --}}
        <div>
            <x-input-label value="Kontak" />
            <div class="grid grid-cols-1 gap-3 mt-1 sm:grid-cols-2">
                <div>
                    <x-input-label value="Mata" class="text-xs" />
                    <x-text-input wire:model.live="pemeriksaan.pemeriksaanPsikiatri.kontak.mata" placeholder="Kontak Mata"
                        :disabled="$isFormLocked" class="w-full mt-1" />
                </div>
                <div>
                    <x-input-label value="Verbal" class="text-xs" />
                    <x-text-input wire:model.live="pemeriksaan.pemeriksaanPsikiatri.kontak.verbal" placeholder="Kontak Verbal"
                        :disabled="$isFormLocked" class="w-full mt-1" />
                </div>
            </div>
        </div>

        {{-- Mood / Afek --}}
        <div>
            <x-input-label value="Mood / Afek" />
            <x-text-input wire:model.live="pemeriksaan.pemeriksaanPsikiatri.moodAfek" placeholder="Mood / Afek"
                :disabled="$isFormLocked" class="w-full mt-1" />
        </div>

        {{-- Proses Berpikir --}}
        <div>
            <x-input-label value="Proses Berpikir" />
            <div class="mt-1 space-y-3">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Bentuk Pikir" class="text-xs" />
                        <div class="flex flex-wrap gap-2 mt-1">
                            @foreach (['Realistik', 'Non Realistik'] as $opt)
                                <x-radio-button :label="$opt" :value="$opt" name="psikiatriBentukPikir"
                                    wire:model.live="pemeriksaan.pemeriksaanPsikiatri.prosesBerpikir.bentukPikir" :disabled="$isFormLocked" />
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <x-input-label value="Arus Pikir" class="text-xs" />
                        <div class="flex flex-wrap gap-2 mt-1">
                            @foreach (['Lancar', 'Tidak Lancar'] as $opt)
                                <x-radio-button :label="$opt" :value="$opt" name="psikiatriArusPikir"
                                    wire:model.live="pemeriksaan.pemeriksaanPsikiatri.prosesBerpikir.arusPikir" :disabled="$isFormLocked" />
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Isi Pikir: Pikiran Tidak Masuk Akal & Waham, keterangan muncul bila Ya --}}
                <p class="text-xs font-semibold text-muted dark:text-gray-400">Isi Pikir</p>
                @foreach (['pikiranTidakMasukAkal' => 'Pikiran Tidak Masuk Akal', 'waham' => 'Waham'] as $field => $label)
                    @php $ketField = 'keterangan' . ucfirst($field); @endphp
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-start">
                        <div>
                            <x-input-label :value="$label" class="text-xs" />
                            <div class="flex flex-wrap gap-2 mt-1">
                                @foreach (['Ya', 'Tidak'] as $opt)
                                    <x-radio-button :label="$opt" :value="$opt" name="psikiatri{{ ucfirst($field) }}"
                                        wire:model.live="pemeriksaan.pemeriksaanPsikiatri.prosesBerpikir.isiPikir.{{ $field }}"
                                        :disabled="$isFormLocked" />
                                @endforeach
                            </div>
                        </div>
                        @if (($isiPikir[$field] ?? '') === 'Ya')
                            <div class="sm:col-span-2">
                                <x-input-label value="Keterangan" class="text-xs" />
                                <x-text-input
                                    wire:model.live="pemeriksaan.pemeriksaanPsikiatri.prosesBerpikir.isiPikir.{{ $ketField }}"
                                    placeholder="Keterangan {{ $label }}" :disabled="$isFormLocked" class="w-full mt-1" />
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Persepsi, Kemauan, Psikomotor --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            @foreach (['persepsi' => 'Persepsi', 'kemauan' => 'Kemauan', 'psikomotor' => 'Psikomotor'] as $field => $label)
                <div>
                    <x-input-label :value="$label" />
                    <x-text-input wire:model.live="pemeriksaan.pemeriksaanPsikiatri.{{ $field }}"
                        placeholder="{{ $label }}" :disabled="$isFormLocked" class="w-full mt-1" />
                </div>
            @endforeach
        </div>

    </div>
</x-border-form>
