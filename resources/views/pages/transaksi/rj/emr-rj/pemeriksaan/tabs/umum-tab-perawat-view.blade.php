<div class="space-y-4">

    {{-- TANDA VITAL --}}
    <x-border-form :title="__('Tanda Vital')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
        <div class="space-y-4">

            {{-- Keadaan Umum --}}
            <div>
                <x-input-label value="Keadaan Umum" />
                <x-text-input wire:model.live="pemeriksaan.tandaVital.keadaanUmum"
                    placeholder="Keadaan Umum" :error="$errors->has('pemeriksaan.tandaVital.keadaanUmum')" :disabled="$isFormLocked" class="w-full mt-1" />
                <x-input-error :messages="$errors->get('pemeriksaan.tandaVital.keadaanUmum')" class="mt-1" />
            </div>

            {{-- Tingkat Kesadaran --}}
            <div>
                <x-input-label value="Tingkat Kesadaran" />
                <x-select-input wire:model.live="pemeriksaan.tandaVital.tingkatKesadaran"
                    :error="$errors->has('pemeriksaan.tandaVital.tingkatKesadaran')" :disabled="$isFormLocked" class="w-full mt-1">
                    <option value="">-- Pilih Tingkat Kesadaran --</option>
                    @foreach ($pemeriksaan['tandaVital']['tingkatKesadaranOptions'] ?? [] as $option)
                        <option value="{{ $option['tingkatKesadaran'] }}">{{ $option['tingkatKesadaran'] }}</option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('pemeriksaan.tandaVital.tingkatKesadaran')" class="mt-1" />
            </div>

            {{-- Grid tanda vital --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <x-input-label value="Sistolik (mmHg)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="pemeriksaan.tandaVital.sistolik" placeholder=""
                        :error="$errors->has('pemeriksaan.tandaVital.sistolik')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('pemeriksaan.tandaVital.sistolik')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Diastolik (mmHg)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="pemeriksaan.tandaVital.distolik" placeholder=""
                        :error="$errors->has('pemeriksaan.tandaVital.distolik')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('pemeriksaan.tandaVital.distolik')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Nadi (x/mnt)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="pemeriksaan.tandaVital.frekuensiNadi"
                        placeholder="" :error="$errors->has('pemeriksaan.tandaVital.frekuensiNadi')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('pemeriksaan.tandaVital.frekuensiNadi')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Nafas (x/mnt)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="pemeriksaan.tandaVital.frekuensiNafas"
                        placeholder="" :error="$errors->has('pemeriksaan.tandaVital.frekuensiNafas')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('pemeriksaan.tandaVital.frekuensiNafas')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Suhu (°C)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="pemeriksaan.tandaVital.suhu" placeholder=""
                        :error="$errors->has('pemeriksaan.tandaVital.suhu')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('pemeriksaan.tandaVital.suhu')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="SPO2 (%)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="pemeriksaan.tandaVital.spo2" placeholder=""
                        :error="$errors->has('pemeriksaan.tandaVital.spo2')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('pemeriksaan.tandaVital.spo2')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="GDA (g/dl)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="pemeriksaan.tandaVital.gda" placeholder=""
                        :error="$errors->has('pemeriksaan.tandaVital.gda')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('pemeriksaan.tandaVital.gda')" class="mt-1" />
                </div>
            </div>

        </div>
    </x-border-form>

    {{-- NUTRISI --}}
    <x-border-form :title="__('Nutrisi')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
        <div class="space-y-4">

            {{-- BB, TB, IMT --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label value="Berat Badan (Kg)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="pemeriksaan.nutrisi.bb" placeholder=""
                        :error="$errors->has('pemeriksaan.nutrisi.bb')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('pemeriksaan.nutrisi.bb')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Tinggi Badan (Cm)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="pemeriksaan.nutrisi.tb" placeholder=""
                        :error="$errors->has('pemeriksaan.nutrisi.tb')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('pemeriksaan.nutrisi.tb')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Index Masa Tubuh (Kg/M²)" class="whitespace-nowrap" />
                    {{-- IMT readonly, dihitung otomatis via hitungIMT() di server saat BB/TB update --}}
                    <div class="flex mt-1">
                        <div
                            class="w-full px-3 py-2 text-base text-ink bg-surface-soft border border-gray-300 rounded-l-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                            {{ $pemeriksaan['nutrisi']['imt'] ?? '-' }}
                        </div>
                        <div
                            class="px-3 py-2 text-sm font-semibold text-center text-muted bg-surface-soft border border-l-0 border-gray-300 rounded-r-lg whitespace-nowrap dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                            Kg/M²
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('pemeriksaan.nutrisi.imt')" class="mt-1" />
                </div>
            </div>

            {{-- Lingkar Kepala & LILA --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label value="Lingkar Kepala (Cm)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="pemeriksaan.nutrisi.lk" placeholder=""
                        :error="$errors->has('pemeriksaan.nutrisi.lk')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('pemeriksaan.nutrisi.lk')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Lingkar Lengan Atas (Cm)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="pemeriksaan.nutrisi.lila" placeholder=""
                        :error="$errors->has('pemeriksaan.nutrisi.lila')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('pemeriksaan.nutrisi.lila')" class="mt-1" />
                </div>
            </div>

        </div>
    </x-border-form>

</div>
