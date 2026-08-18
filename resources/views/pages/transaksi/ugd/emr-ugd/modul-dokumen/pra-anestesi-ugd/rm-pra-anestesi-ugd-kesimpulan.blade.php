                            {{-- ══ KESIMPULAN ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Kesimpulan Evaluasi Pra Anestesi</h3>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label value="Jenis Anestesi *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.jenisAnestesi" :error="$errors->has('newForm.jenisAnestesi')" placeholder="cth: GA / Spinal / Sedasi" class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.jenisAnestesi')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="PS ASA *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.psAsa" :error="$errors->has('newForm.psAsa')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($asaOptions as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.psAsa')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Induksi Pra Anestesi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.induksiPraAnestesi" :error="$errors->has('newForm.induksiPraAnestesi')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Penyulit" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.penyulit" :error="$errors->has('newForm.penyulit')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Komplikasi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.komplikasi" :error="$errors->has('newForm.komplikasi')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Obat Analgesik Pasca Operasi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.obatAnalgesikPascaOp" :error="$errors->has('newForm.obatAnalgesikPascaOp')" class="w-full" />
                                    </div>
                                </div>
                            </section>
