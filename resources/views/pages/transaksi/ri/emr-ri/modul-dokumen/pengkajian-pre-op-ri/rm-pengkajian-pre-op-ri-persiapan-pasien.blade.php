                            {{-- ══ PERSIAPAN PASIEN ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Persiapan Pasien</h3>
                                {{-- Pre Medikasi / Cairan / Obat — daftar array ala rekonsiliasi obat --}}
                                <x-border-form :title="__('Pre Medikasi / Cairan / Obat')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                                    <div class="space-y-3">
                                        @if (!$formReadOnly)
                                            <div class="grid grid-cols-12 gap-2">
                                                <div class="col-span-12 sm:col-span-3">
                                                    <x-input-label value="Jenis" :required="true" class="truncate whitespace-nowrap" />
                                                    <x-select-input wire:model="persiapanJenis" :error="$errors->has('persiapanJenis')"
                                                        class="w-full px-2 mt-1">
                                                        <option value="">—</option>
                                                        @foreach (['Pre Medikasi', 'Cairan', 'Obat'] as $persiapanJenisOpsi)
                                                            <option value="{{ $persiapanJenisOpsi }}">{{ $persiapanJenisOpsi }}</option>
                                                        @endforeach
                                                    </x-select-input>
                                                    <x-input-error :messages="$errors->get('persiapanJenis')" class="mt-1" />
                                                </div>
                                                <div class="col-span-12 sm:col-span-5">
                                                    <x-input-label value="Nama / Keterangan" :required="true" class="truncate whitespace-nowrap" />
                                                    <x-text-input wire:model="persiapanNama" wire:keydown.enter.prevent="addPersiapan"
                                                        placeholder="Midazolam 2 mg / RL 500 ml" :error="$errors->has('persiapanNama')"
                                                        class="w-full px-2 mt-1" />
                                                    <x-input-error :messages="$errors->get('persiapanNama')" class="mt-1" />
                                                </div>
                                                <div class="col-span-12 sm:col-span-4">
                                                    <x-input-label value="Tgl/Jam Pemberian" :required="true" class="truncate whitespace-nowrap" />
                                                    <div class="flex gap-1 mt-1">
                                                        <x-text-input wire:model="persiapanTglJam" placeholder="dd/mm/yyyy HH:mm:ss"
                                                            :error="$errors->has('persiapanTglJam')" class="w-full px-2" />
                                                        <x-now-button wire:click="setPersiapanTglJamNow" />
                                                    </div>
                                                    <x-input-error :messages="$errors->get('persiapanTglJam')" class="mt-1" />
                                                </div>
                                            </div>

                                            <x-primary-button type="button" wire:click="addPersiapan" wire:loading.attr="disabled"
                                                wire:target="addPersiapan" class="justify-center gap-1.5 w-full">
                                                <span wire:loading.remove wire:target="addPersiapan" class="flex items-center gap-1.5">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    Tambah
                                                </span>
                                                <span wire:loading wire:target="addPersiapan" class="flex items-center gap-1.5">
                                                    <x-loading class="w-4 h-4" /> Menambahkan...
                                                </span>
                                            </x-primary-button>
                                        @endif

                                        <div class="overflow-x-auto bg-canvas border rounded-2xl border-hairline dark:border-gray-700">
                                            <table class="ds-table">
                                                <thead>
                                                    <tr>
                                                        <th class="ds-c w-10">No</th>
                                                        <th>Jenis</th>
                                                        <th>Nama / Keterangan</th>
                                                        <th>Tgl/Jam Pemberian</th>
                                                        <th class="ds-c w-14">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($newForm['persiapanObatCairan'] ?? [] as $persiapanIndex => $persiapanItem)
                                                        <tr wire:key="persiapan-pre-op-ri-{{ $riHdrNo ?? 'new' }}-{{ $persiapanIndex }}">
                                                            <td class="ds-c ds-td-meta">{{ $persiapanIndex + 1 }}</td>
                                                            <td class="ds-td-strong">{{ $persiapanItem['jenis'] ?? '-' }}</td>
                                                            <td>{{ $persiapanItem['nama'] ?? '-' }}</td>
                                                            <td>{{ ($persiapanItem['tglJam'] ?? '') ?: '-' }}</td>
                                                            <td class="ds-c">
                                                                @if (!$formReadOnly)
                                                                    <x-confirm-button variant="danger-soft" :action="'removePersiapan(' . $persiapanIndex . ')'"
                                                                        title="Hapus Baris" :message="'Yakin hapus ' . ($persiapanItem['nama'] ?? 'baris ini') . ' dari daftar?'"
                                                                        confirmText="Ya, hapus" cancelText="Batal" class="px-2 py-1">
                                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                        </svg>
                                                                    </x-confirm-button>
                                                                @else
                                                                    <span class="text-muted-soft">—</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="5" class="ds-c italic text-muted-soft">
                                                                Belum ada pre medikasi / cairan / obat.
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </x-border-form>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <x-input-label value="Tgl/Jam Mulai Puasa" class="mb-1" />
                                        <div class="flex gap-1">
                                            <x-text-input wire:model.live="newForm.puasaMulaiJam" :error="$errors->has('newForm.puasaMulaiJam')" placeholder="dd/mm/yyyy HH:mm:ss"
                                                class="w-full" />
                                            <x-now-button wire:click="setNow('puasaMulaiJam')" :disabled="$formReadOnly" />
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <x-toggle wire:model.live="newForm.sudahDicukur" :trueValue="true" :falseValue="false"
                                        label="Sudah dicukur / dibersihkan daerah operasi" />
                                    <x-toggle wire:model.live="newForm.persiapanDarah" :trueValue="true" :falseValue="false"
                                        label="Persiapan darah" />
                                    <x-toggle wire:model.live="newForm.gigiPalsuDilepas" :trueValue="true" :falseValue="false"
                                        label="Gigi palsu / kontak lensa / perhiasan dilepas" />
                                    <x-toggle wire:model.live="newForm.pengosonganKandungKemih" :trueValue="true"
                                        :falseValue="false" label="Pengosongan kandung kemih" />
                                    <x-toggle wire:model.live="newForm.clysma" :trueValue="true" :falseValue="false"
                                        label="Clysma / glyserin" />
                                    <x-toggle wire:model.live="newForm.riwayatPenyakit" :trueValue="true" :falseValue="false"
                                        label="Ada riwayat penyakit" />
                                </div>
                                @if ($newForm['riwayatPenyakit'])
                                    <div>
                                        <x-input-label value="Keterangan Riwayat Penyakit" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.riwayatPenyakitKet" :error="$errors->has('newForm.riwayatPenyakitKet')"
                                            class="w-full" />
                                    </div>
                                @endif
                                <div>
                                    <x-input-label value="Lain-lain" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.lainLain" :error="$errors->has('newForm.lainLain')" rows="2"
                                        class="w-full" />
                                </div>
                            </section>
