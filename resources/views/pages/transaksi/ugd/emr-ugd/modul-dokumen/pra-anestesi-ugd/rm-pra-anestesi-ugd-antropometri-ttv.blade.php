                            {{-- ══ ANTROPOMETRI & TTV — klasifikasi mengikuti EMR RJ (Tanda Vital / Nutrisi) ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Antropometri & Tanda Vital</h3>

                                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                <x-border-form :title="__('Tanda Vital')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                        <div>
                                            <x-input-label value="Sistolik (mmHg)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.sistolik" :error="$errors->has('newForm.sistolik')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Diastolik (mmHg)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.diastolik" :error="$errors->has('newForm.diastolik')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Nadi (x/mnt)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.nadi" :error="$errors->has('newForm.nadi')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Nafas (x/mnt)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.rr" :error="$errors->has('newForm.rr')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Suhu (°C)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.suhu" :error="$errors->has('newForm.suhu')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="SPO2 (%)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.spo2" :error="$errors->has('newForm.spo2')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="GDA (g/dl)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.gda" :error="$errors->has('newForm.gda')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Skor Nyeri" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.skorNyeri" :error="$errors->has('newForm.skorNyeri')" class="w-full mt-1" />
                                        </div>
                                    </div>
                                </x-border-form>

                                <x-border-form :title="__('Nutrisi')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <div>
                                            <x-input-label value="Berat Badan (Kg)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.bb" :error="$errors->has('newForm.bb')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Tinggi Badan (Cm)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.tb" :error="$errors->has('newForm.tb')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Index Masa Tubuh (Kg/M²)" class="whitespace-nowrap" />
                                            {{-- IMT readonly, dihitung otomatis via updated() saat BB/TB berubah --}}
                                            <div class="flex mt-1">
                                                <div
                                                    class="w-full px-3 py-2 text-base text-ink bg-surface-soft border border-gray-300 rounded-l-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                                                    {{ ($newForm['imt'] ?? '') !== '' ? $newForm['imt'] : '-' }}
                                                </div>
                                                <div
                                                    class="px-3 py-2 text-sm font-semibold text-center text-muted bg-surface-soft border border-l-0 border-gray-300 rounded-r-lg whitespace-nowrap dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                                                    Kg/M²
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </x-border-form>
                                </div>
                            </section>
