                            {{-- ══ ANAMNESE & RIWAYAT ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <div>
                                    <x-input-label value="Anamnese" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.anamnese" :error="$errors->has('newForm.anamnese')" rows="2" class="w-full" />
                                </div>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div class="flex items-center gap-3">
                                        <div class="shrink-0">
                                            <x-toggle wire:model.live="newForm.riwayatAnestesi" :trueValue="true" :falseValue="false" label="Ada riwayat anestesi" :disabled="$formReadOnly" />
                                        </div>
                                        @if ($newForm['riwayatAnestesi'])
                                            <x-text-input wire:model.live="newForm.riwayatAnestesiKet" :error="$errors->has('newForm.riwayatAnestesiKet')" placeholder="Keterangan riwayat anestesi" class="w-full" />
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="shrink-0">
                                            <x-toggle wire:model.live="newForm.riwayatAlergi" :trueValue="true" :falseValue="false" label="Ada riwayat alergi" :disabled="$formReadOnly" />
                                        </div>
                                        @if ($newForm['riwayatAlergi'])
                                            <x-text-input wire:model.live="newForm.riwayatAlergiKet" :error="$errors->has('newForm.riwayatAlergiKet')" placeholder="Keterangan alergi" class="w-full" />
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Obat yang Sedang Dikonsumsi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.obatDikonsumsi" :error="$errors->has('newForm.obatDikonsumsi')" class="w-full" />
                                </div>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div class="flex items-center gap-3">
                                        <div class="shrink-0">
                                            <x-toggle wire:model.live="newForm.merokok" :trueValue="true" :falseValue="false" label="Merokok" :disabled="$formReadOnly" />
                                        </div>
                                        @if ($newForm['merokok'])
                                            <x-text-input wire:model.live="newForm.merokokKet" :error="$errors->has('newForm.merokokKet')" placeholder="Keterangan (jumlah/lama merokok)" class="w-full" />
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="shrink-0">
                                            <x-toggle wire:model.live="newForm.alkohol" :trueValue="true" :falseValue="false" label="Alkohol" :disabled="$formReadOnly" />
                                        </div>
                                        @if ($newForm['alkohol'])
                                            <x-text-input wire:model.live="newForm.alkoholKet" :error="$errors->has('newForm.alkoholKet')" placeholder="Keterangan (jumlah/frekuensi)" class="w-full" />
                                        @endif
                                    </div>
                                </div>
                            </section>
