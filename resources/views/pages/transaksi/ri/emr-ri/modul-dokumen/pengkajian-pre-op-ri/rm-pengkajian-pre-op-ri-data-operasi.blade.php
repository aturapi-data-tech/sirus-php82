                            {{-- ══ DATA OPERASI ══ --}}
                            <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Diagnosa Pre Operasi *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.diagnosaPreOp" :error="$errors->has('newForm.diagnosaPreOp')" rows="2"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.diagnosaPreOp')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Rencana Operasi *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.rencanaOperasi" :error="$errors->has('newForm.rencanaOperasi')" rows="2"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.rencanaOperasi')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Dokter Operator *" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.dokterOperator" :error="$errors->has('newForm.dokterOperator')"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.dokterOperator')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Tanggal / Jam Operasi" class="mb-1" />
                                    <div class="flex gap-1">
                                        <x-text-input wire:model.live="newForm.tanggalOperasi" :error="$errors->has('newForm.tanggalOperasi')" placeholder="dd/mm/yyyy HH:mm:ss"
                                            class="w-full" />
                                        <x-now-button wire:click="setNow('tanggalOperasi')" :disabled="$formReadOnly" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Perjanjian dgn Perawat OK (Nama Crew OK)" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.perjanjianPerawatOk" :error="$errors->has('newForm.perjanjianPerawatOk')"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Urgensi Operasi *" class="mb-1" />
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($urgensiOptions as $opt)
                                            <x-radio-button :label="$opt" :value="$opt" name="urgensiPreOp"
                                                wire:model.live="newForm.urgensi" />
                                        @endforeach
                                    </div>
                                    <x-input-error :messages="$errors->get('newForm.urgensi')" class="mt-1" />
                                </div>
                            </section>
