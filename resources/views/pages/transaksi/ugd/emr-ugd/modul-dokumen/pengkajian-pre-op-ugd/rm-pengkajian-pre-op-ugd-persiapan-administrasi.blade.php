                            {{-- ══ PERSIAPAN ADMINISTRASI (KE OK) ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Persiapan Administrasi
                                    (sertakan bersama pasien ke OK)</h3>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <x-toggle wire:model.live="newForm.adaRekamMedis" :trueValue="true" :falseValue="false"
                                        label="Rekam Medis" />
                                    <x-toggle wire:model.live="newForm.adaSuratIjin" :trueValue="true" :falseValue="false"
                                        label="Surat Ijin Tindakan Operasi" />
                                    <x-toggle wire:model.live="newForm.adaLab" :trueValue="true" :falseValue="false"
                                        label="Hasil Pemeriksaan Laboratorium" />
                                    <div class="flex items-center gap-3">
                                        <div class="shrink-0">
                                            <x-toggle wire:model.live="newForm.adaRadiologi" :trueValue="true" :falseValue="false"
                                                label="Hasil Pemeriksaan Radiologi" />
                                        </div>
                                        @if ($newForm['adaRadiologi'])
                                            <x-text-input wire:model.live="newForm.radiologiJenis" :error="$errors->has('newForm.radiologiJenis')"
                                                placeholder="Jenis: Thorak Foto / CT-Scan / MRI"
                                                class="w-full" />
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="shrink-0">
                                            <x-toggle wire:model.live="newForm.adaDiagnostik" :trueValue="true" :falseValue="false"
                                                label="Hasil Pemeriksaan Diagnostik" />
                                        </div>
                                        @if ($newForm['adaDiagnostik'])
                                            <x-text-input wire:model.live="newForm.diagnostikJenis" :error="$errors->has('newForm.diagnostikJenis')"
                                                placeholder="Jenis: USG / Colonoscopi / Gastroscopi"
                                                class="w-full" />
                                        @endif
                                    </div>
                                </div>
                            </section>
