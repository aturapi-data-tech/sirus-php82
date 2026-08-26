                            {{-- ══ PENANDAAN LOKASI OPERASI (SITE MARKING, SKP 4) ══ --}}
                            <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Penandaan Lokasi Operasi (Site Marking)</h3>
                                <x-input-label value="Penandaan Lokasi *" class="mb-1" />
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($perluOptions as $opt)
                                        <x-radio-button :label="$opt" :value="$opt" name="perluPenandaan"
                                            wire:model.live="newForm.perluPenandaan" />
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('newForm.perluPenandaan')" class="mt-1" />

                                @if (($newForm['perluPenandaan'] ?? '') === 'Tidak diperlukan')
                                    <div class="mt-2">
                                        <x-input-label value="Alasan Tidak Diperlukan *" class="mb-1" />
                                        <x-textarea wire:model.live="newForm.alasanTidakPerlu" :error="$errors->has('newForm.alasanTidakPerlu')" rows="2"
                                            placeholder="cth: organ tunggal / garis tengah / kasus tidak melibatkan lateralitas"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.alasanTidakPerlu')" class="mt-1" />
                                    </div>
                                @endif
                            </section>

                            @if (($newForm['perluPenandaan'] ?? '') === 'Ya')
                                {{-- ══ DETAIL LOKASI PENANDAAN ══ --}}
                                <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <x-input-label value="Region Anatomi *" class="mb-1" />
                                            <x-select-input wire:model.live="newForm.regionAnatomi" :error="$errors->has('newForm.regionAnatomi')"
                                                class="w-full">
                                                <option value="">— pilih —</option>
                                                @foreach ($regionOptions as $opt)
                                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                                @endforeach
                                            </x-select-input>
                                            <x-input-error :messages="$errors->get('newForm.regionAnatomi')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Sisi / Lateralitas *" class="mb-1" />
                                            <x-select-input wire:model.live="newForm.sisi" :error="$errors->has('newForm.sisi')"
                                                class="w-full">
                                                <option value="">— pilih —</option>
                                                @foreach ($sisiOptions as $opt)
                                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                                @endforeach
                                            </x-select-input>
                                            <x-input-error :messages="$errors->get('newForm.sisi')" class="mt-1" />
                                        </div>
                                    </div>
                                    <div>
                                        <x-input-label value="Detail Lokasi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.detailLokasi" :error="$errors->has('newForm.detailLokasi')"
                                            placeholder="cth: digiti III pedis (D)" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Metode Penandaan" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.metodePenandaan" :error="$errors->has('newForm.metodePenandaan')"
                                            class="w-full" />
                                    </div>
                                    <x-toggle wire:model.live="newForm.pasienDilibatkan" :trueValue="true"
                                        :falseValue="false" label="Pasien dilibatkan saat penandaan" />
                                </section>

                                {{-- ══ DIAGRAM PENANDAAN (klik tubuh) ══ --}}
                                <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700" x-data="{}">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h3 class="text-base font-semibold text-ink dark:text-gray-200">Diagram Penandaan Lokasi</h3>
                                        @if (!$formReadOnly)
                                            <div class="flex gap-2">
                                                <x-secondary-button type="button" wire:click="undoMark" class="text-sm py-1 px-2">Hapus tanda terakhir</x-secondary-button>
                                                <x-outline-button type="button" wire:click="clearMarks" wire:confirm="Bersihkan semua tanda?" class="!px-2 !py-1 text-sm">Bersihkan</x-outline-button>
                                            </div>
                                        @endif
                                    </div>
                                    <p class="text-sm text-muted-soft dark:text-gray-500">
                                        Klik pada panel (tubuh / kepala / tangan / kaki) untuk menandai lokasi operasi. Tanda bernomor urut per panel & tersimpan untuk dicetak.
                                    </p>

                                    <x-site-marking-diagram :marks="$newForm['marks'] ?? []" :editable="!$formReadOnly"
                                        wire-add-mark="addMark" />

                                    @if (count($newForm['marks'] ?? []) > 0)
                                        <p class="text-sm text-center text-muted dark:text-gray-400">{{ count($newForm['marks']) }} tanda ditempatkan.</p>
                                    @endif
                                </section>
                            @endif
