                            {{-- ══ EVALUASI JALAN NAFAS ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Evaluasi Jalan Nafas</h3>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-6">
                                    <div>
                                        <x-input-label value="Mallampati *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.mallampati" :error="$errors->has('newForm.mallampati')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($mallampatiOptions as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.mallampati')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Alat Bantu Nafas" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.alatBantuNafas" :error="$errors->has('newForm.alatBantuNafas')"
                                            placeholder="cth: nasal kanul / NRM / OPA" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Buka Mulut (cm)" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.bukaMulut" :error="$errors->has('newForm.bukaMulut')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Jarak Mentohyoid (cm)" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.jarakMentohyoid" :error="$errors->has('newForm.jarakMentohyoid')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Jarak Hyothyroid (cm)" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.jarakHyothyroid" :error="$errors->has('newForm.jarakHyothyroid')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Gerak Leher" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.gerakLeher" :error="$errors->has('newForm.gerakLeher')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($gerakLeherOptions as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-4">
                                    <x-toggle wire:model.live="newForm.jalanNafasBebas" :trueValue="true" :falseValue="false"
                                        label="Jalan nafas bebas" :disabled="$formReadOnly" />
                                    <x-toggle wire:model.live="newForm.leherPendek" :trueValue="true" :falseValue="false" label="Leher pendek" :disabled="$formReadOnly" />
                                    <x-toggle wire:model.live="newForm.massa" :trueValue="true" :falseValue="false" label="Massa" :disabled="$formReadOnly" />
                                    <x-toggle wire:model.live="newForm.gigiPalsu" :trueValue="true" :falseValue="false" label="Gigi palsu" :disabled="$formReadOnly" />
                                    <x-toggle wire:model.live="newForm.obesitas" :trueValue="true" :falseValue="false" label="Obesitas" :disabled="$formReadOnly" />
                                    <x-toggle wire:model.live="newForm.sulitVentilasi" :trueValue="true" :falseValue="false" label="Prediksi sulit ventilasi" :disabled="$formReadOnly" />
                                </div>
                            </section>
