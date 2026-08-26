                            {{-- ══ DATA DASAR ══ --}}
                            <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Tanggal / Jam *" class="mb-1" />
                                    <div class="flex items-center gap-2">
                                        <x-text-input wire:model.live="newForm.tanggal" placeholder="dd/mm/yyyy HH:mm:ss"
                                            :error="$errors->has('newForm.tanggal')" class="w-full" />
                                        @if (!$formReadOnly)
                                            <x-now-button wire:click="setTanggalSekarang" />
                                        @endif
                                    </div>
                                    <x-input-error :messages="$errors->get('newForm.tanggal')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Kriteria Pasien *" class="mb-1" />
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($kriteriaOptions as $opt)
                                            <x-radio-button :label="$opt" :value="$opt" name="kriteria"
                                                wire:model.live="newForm.kriteria" :disabled="$formReadOnly" />
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Diagnosis Pra Anestesi *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.diagnosisPraAnestesi" :error="$errors->has('newForm.diagnosisPraAnestesi')" rows="2" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.diagnosisPraAnestesi')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Rencana Tindakan *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.rencanaTindakan" :error="$errors->has('newForm.rencanaTindakan')" rows="2" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.rencanaTindakan')" class="mt-1" />
                                </div>
                            </section>
