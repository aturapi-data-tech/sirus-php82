{{-- pages/transaksi/rj/emr-rj/penilaian/tabs/nyeri-tab.blade.php --}}
<div class="space-y-4">

    @if (!$isFormLocked)
        <x-border-form :title="__('Tambah Penilaian Nyeri')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
            <div class="space-y-4">

                <div @class([
                    'grid gap-4',
                    'grid-cols-2' => $formEntryNyeri['nyeri']['nyeri'] === 'Ya',
                    'grid-cols-1' => $formEntryNyeri['nyeri']['nyeri'] !== 'Ya',
                ])>
                    <div>
                        <x-input-label value="Status Nyeri" :required="true" />
                        <x-select-input wire:model.live="formEntryNyeri.nyeri.nyeri" class="w-full mt-1"
                            x-ref="nyStatus" x-on:keydown.enter.prevent="$refs.nyTgl?.focus()">
                            <option value="Tidak">Tidak</option>
                            <option value="Ya">Ya</option>
                        </x-select-input>
                        <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.nyeri')" class="mt-1" />
                    </div>

                    @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya')
                        <div>
                            <x-input-label value="Tanggal Penilaian" :required="true" />
                            <div class="flex gap-2 mt-1">
                                <x-text-input wire:model="formEntryNyeri.tglPenilaian" placeholder="dd/mm/yyyy hh:ii:ss"
                                    :error="$errors->has('formEntryNyeri.tglPenilaian')" class="w-full"
                                    x-ref="nyTgl" x-on:keydown.enter.prevent="$refs.nyMetode?.focus()" />
                                <x-now-button wire:click="setTglPenilaianNyeri" />
                            </div>
                            <x-input-error :messages="$errors->get('formEntryNyeri.tglPenilaian')" class="mt-1" />
                        </div>
                    @endif
                </div>

                @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya')
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <x-input-label value="Metode Penilaian" :required="true" />
                            <x-select-input wire:model.live="formEntryNyeri.nyeri.nyeriMetode.nyeriMetode"
                                class="w-full mt-1"
                                x-ref="nyMetode" x-on:keydown.enter.prevent="($refs.nyMetodeScore || $refs.nyPencetus)?.focus()">
                                <option value="">-- Pilih Metode --</option>
                                @foreach ($this->daftarSkala as $kode => $skala)
                                    <option value="{{ $kode }}">{{ $kode }} — {{ $skala['sasaran'] }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.nyeriMetode.nyeriMetode')" class="mt-1" />
                            @if (!empty($skalaDisarankan))
                                <p class="mt-1 text-xs text-muted">
                                    Umur pasien {{ $umurPasienTahun }} th — disarankan:
                                    <span class="font-semibold text-brand dark:text-brand-lime">{{ implode(' / ', $skalaDisarankan) }}</span>
                                </p>
                            @endif
                        </div>

                        <x-nyeri.panduan-skala :daftarSkala="$this->daftarSkala" />

                        @if ($this->skalaTerpilih)
                            <x-nyeri.identitas-skala :skala="$this->skalaTerpilih" :kode="$formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode']"
                                :skor="$formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore']" :tafsir="$this->interpretasiBerjalan" />
                        @endif
                    </div>
                @endif

                {{-- ===== INSTRUMEN SKALA (bentuk mengikuti tipe skala) ===== --}}
                @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya' && $this->skalaTerpilih)
                    <x-nyeri.instrumen :skala="$this->skalaTerpilih" :kode="$formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode']"
                        :dataNyeri="$formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri']" x-ref="nyMetodeScore"
                        x-on:keydown.enter.prevent="$refs.nyPencetus?.focus()" />
                @endif

                {{-- ===== DETAIL NYERI ===== --}}
                @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya')
                    <x-border-form :title="__('Detail Nyeri')" :align="__('start')" :bgcolor="__('bg-canvas')">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <x-input-label value="Pencetus" />
                                    <x-text-input wire:model="formEntryNyeri.nyeri.pencetus" class="w-full mt-1"
                                        x-ref="nyPencetus" x-on:keydown.enter.prevent="$refs.nyDurasi?.focus()" />
                                </div>
                                <div>
                                    <x-input-label value="Durasi" />
                                    <x-text-input wire:model="formEntryNyeri.nyeri.durasi"
                                        placeholder="Contoh: 30 menit" class="w-full mt-1"
                                        x-ref="nyDurasi" x-on:keydown.enter.prevent="$refs.nyLokasi?.focus()" />
                                </div>
                                <div>
                                    <x-input-label value="Lokasi" />
                                    <x-text-input wire:model="formEntryNyeri.nyeri.lokasi" class="w-full mt-1"
                                        x-ref="nyLokasi" x-on:keydown.enter.prevent="$refs.nyWaktu?.focus()" />
                                </div>
                                <div>
                                    <x-input-label value="Waktu Nyeri" />
                                    <x-text-input wire:model="formEntryNyeri.nyeri.waktuNyeri"
                                        placeholder="Contoh: Malam hari" class="w-full mt-1"
                                        x-ref="nyWaktu" x-on:keydown.enter.prevent="$refs.nyKesadaran?.focus()" />
                                </div>
                                <div>
                                    <x-input-label value="Tingkat Kesadaran" />
                                    <x-select-input wire:model="formEntryNyeri.nyeri.tingkatKesadaran"
                                        class="w-full mt-1"
                                        x-ref="nyKesadaran" x-on:keydown.enter.prevent="$refs.nyAktivitas?.focus()">
                                        <option value="">-- Pilih --</option>
                                        <option value="Composmentis">Composmentis</option>
                                        <option value="Apatis">Apatis</option>
                                        <option value="Somnolen">Somnolen</option>
                                        <option value="Stupor">Stupor</option>
                                        <option value="Koma">Koma</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Tingkat Aktivitas" />
                                    <x-select-input wire:model="formEntryNyeri.nyeri.tingkatAktivitas"
                                        class="w-full mt-1"
                                        x-ref="nyAktivitas" x-on:keydown.enter.prevent="$refs.nyFarmakologi?.focus()">
                                        <option value="">-- Pilih --</option>
                                        <option value="Mandiri">Mandiri</option>
                                        <option value="Dibantu Sebagian">Dibantu Sebagian</option>
                                        <option value="Dibantu Penuh">Dibantu Penuh</option>
                                    </x-select-input>
                                </div>
                            </div>
                        </div>
                    </x-border-form>
                @endif

                @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya')
                    {{-- ===== INTERVENSI & CATATAN ===== --}}
                    <x-border-form :title="__('Intervensi & Catatan')" :align="__('start')" :bgcolor="__('bg-canvas')">
                        <div class="space-y-4">
                            <div>
                                <x-input-label value="Intervensi Farmakologi" />
                                <x-textarea wire:model="formEntryNyeri.nyeri.ketIntervensiFarmakologi"
                                    class="w-full mt-1" rows="2" placeholder="Nama obat, dosis, rute..."
                                    x-ref="nyFarmakologi" />
                            </div>
                            <div>
                                <x-input-label value="Intervensi Non-Farmakologi" />
                                <x-textarea wire:model="formEntryNyeri.nyeri.ketIntervensiNonFarmakologi"
                                    class="w-full mt-1" rows="2"
                                    placeholder="Kompres, relaksasi, distraksi..." />
                            </div>
                            <div>
                                <x-input-label value="Catatan Tambahan" />
                                <x-textarea wire:model="formEntryNyeri.nyeri.catatanTambahan" class="w-full mt-1"
                                    rows="2" />
                            </div>
                        </div>
                    </x-border-form>
                @endif

                <div class="flex justify-end pt-2">
                    <x-primary-button wire:click="addAssessmentNyeri" wire:loading.attr="disabled"
                        wire:target="addAssessmentNyeri">
                        <span wire:loading.remove wire:target="addAssessmentNyeri">Simpan Penilaian Nyeri</span>
                        <span wire:loading wire:target="addAssessmentNyeri">Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>
        </x-border-form>
    @endif

    {{-- ===== TABEL RIWAYAT ===== --}}
    @if (collect($dataDaftarPoliRJ['penilaian']['nyeri'] ?? [])->filter(fn($r) => filled(data_get($r, 'tglPenilaian')))->isNotEmpty())
        <x-border-form :title="__('Riwayat Penilaian Nyeri')" :align="__('start')" :bgcolor="__('bg-canvas')">
            <div class="overflow-x-auto rounded-lg border border-hairline dark:border-gray-700">
                <table class="w-full text-sm text-left text-muted dark:text-gray-300">
                    <thead class="bg-surface-soft dark:bg-gray-700 text-muted dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2 font-medium">Tgl Penilaian</th>
                            <th class="px-3 py-2 font-medium">Petugas</th>
                            <th class="px-3 py-2 font-medium">Nyeri</th>
                            <th class="px-3 py-2 font-medium">Metode</th>
                            <th class="px-3 py-2 font-medium">Skor</th>
                            <th class="px-3 py-2 font-medium">Keterangan</th>
                            @if (!$isFormLocked)
                                <th class="px-3 py-2 font-medium"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                        @foreach (array_reverse(array_filter($dataDaftarPoliRJ['penilaian']['nyeri'] ?? [], fn($r) => filled(data_get($r, 'tglPenilaian'))), true) as $i => $row)
                            @php
                                $tafsir = $this->interpretasiEntri($row);
                                $ket = $tafsir['label'];
                                $skala = $this->daftarSkala[$row['nyeri']['nyeriMetode']['nyeriMetode'] ?? ''] ?? null;
                                $rowBg = match ($tafsir['tingkat']) {
                                    'sangatBerat', 'berat'
                                        => 'bg-red-50 hover:bg-red-100 dark:bg-red-900/10 dark:hover:bg-red-900/20',
                                    'sedang'
                                        => 'bg-orange-50 hover:bg-orange-100 dark:bg-orange-900/10 dark:hover:bg-orange-900/20',
                                    'ringan'
                                        => 'bg-yellow-50 hover:bg-yellow-100 dark:bg-yellow-900/10 dark:hover:bg-yellow-900/20',
                                    'tidak'
                                        => 'bg-green-50 hover:bg-green-100 dark:bg-green-900/10 dark:hover:bg-green-900/20',
                                    default => 'hover:bg-surface-soft dark:hover:bg-gray-800',
                                };
                            @endphp
                            <tr class="{{ $rowBg }}">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['tglPenilaian'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['petugasPenilai'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-sm font-medium
                                        {{ ($row['nyeri']['nyeri'] ?? '') === 'Ya' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $row['nyeri']['nyeri'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    {{ $row['nyeri']['nyeriMetode']['nyeriMetode'] ?? '-' }}
                                    @if ($skala)
                                        <div class="text-[11px] text-muted-soft">{{ $skala['sasaran'] }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 font-bold">
                                    {{ $row['nyeri']['nyeriMetode']['nyeriMetodeScore'] ?? '-' }}@if ($skala)<span class="font-normal text-muted-soft">/{{ $skala['max'] }}</span>@endif
                                </td>
                                <td class="px-3 py-2">
                                    @if ($ket !== '-')
                                        <span class="px-2 py-0.5 rounded-full text-sm font-medium {{ $tafsir['badge'] }}">
                                            {{ $ket }}
                                        </span>
                                        @if ($tafsir['tataLaksana'])
                                            <div class="text-[11px] text-muted-soft mt-0.5">{{ $tafsir['tataLaksana'] }}</div>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                @if (!$isFormLocked)
                                    <td class="px-3 py-2">
                                        <x-outline-button type="button"
                                            wire:click="removeAssessmentNyeri({{ $i }})"
                                            wire:confirm="Hapus data nyeri ini?"
                                            wire:loading.attr="disabled"
                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                            title="Hapus">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </x-outline-button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-border-form>
    @else
        <p class="text-sm text-center text-muted-soft py-6">Belum ada data penilaian nyeri.</p>
    @endif

</div>
