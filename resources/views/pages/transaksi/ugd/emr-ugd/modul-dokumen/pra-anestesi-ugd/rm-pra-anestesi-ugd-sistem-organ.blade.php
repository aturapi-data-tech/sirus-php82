@use('App\Support\Options\PraAnestesiOptions')

                            {{-- ══ SISTEM ORGAN & PENUNJANG ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Fungsi Sistem Organ</h3>
                                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                    @foreach (PraAnestesiOptions::fungsiSistemOrgan() as $organSlug => $organGrup)
                                        <x-border-form :title="$organGrup['label']" :align="__('start')"
                                            :bgcolor="!empty($newForm['fungsiSistemOrgan'][$organSlug . 'Dbn']) ? 'bg-success-tint' : 'bg-error-tint'"
                                            :class="!empty($newForm['fungsiSistemOrgan'][$organSlug . 'Dbn']) ? 'border-success' : 'border-error'">
                                            <div class="space-y-2">
                                                {{-- Status grup: DBN = Dalam Batas Normal --}}
                                                <div class="pb-2 border-b border-hairline-soft dark:border-gray-800">
                                                    <x-toggle wire:model.live="newForm.fungsiSistemOrgan.{{ $organSlug }}Dbn"
                                                        :trueValue="true" :falseValue="false" label="DBN (Dalam Batas Normal)" :disabled="$formReadOnly" />
                                                </div>
                                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                    @foreach ($organGrup['items'] as $organKey => $organLabel)
                                                        <x-toggle wire:model.live="newForm.fungsiSistemOrgan.{{ $organKey }}"
                                                            :trueValue="true" :falseValue="false" :label="$organLabel" :disabled="$formReadOnly" />
                                                    @endforeach
                                                </div>
                                                <div class="flex items-center gap-3 pt-2 border-t border-hairline-soft dark:border-gray-800">
                                                    <div class="shrink-0">
                                                        <x-toggle wire:model.live="newForm.fungsiSistemOrgan.{{ $organSlug }}LainLain"
                                                            :trueValue="true" :falseValue="false" label="Lain-lain" :disabled="$formReadOnly" />
                                                    </div>
                                                    @if (!empty($newForm['fungsiSistemOrgan'][$organSlug . 'LainLain']))
                                                        <x-text-input wire:model.live="newForm.fungsiSistemOrganLainKet.{{ $organSlug }}"
                                                            placeholder="Keterangan lain-lain" class="w-full" />
                                                    @endif
                                                </div>
                                            </div>
                                        </x-border-form>
                                    @endforeach
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label value="Pemeriksaan Laboratorium" class="mb-1" />
                                        <x-textarea wire:model.live="newForm.pemeriksaanLab" :error="$errors->has('newForm.pemeriksaanLab')" rows="2"
                                            placeholder="cth: Hb/Hct/CBC, fungsi ginjal, fungsi hati, serum elektrolit, faal hemostasis" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Pemeriksaan Penunjang" class="mb-1" />
                                        <x-textarea wire:model.live="newForm.pemeriksaanPenunjang" :error="$errors->has('newForm.pemeriksaanPenunjang')" rows="2"
                                            placeholder="cth: X-Ray, EKG, dll" class="w-full" />
                                    </div>
                                </div>
                            </section>
