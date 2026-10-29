{{-- pages/transaksi/ugd/emr-ugd/perencanaan/tabs/discharge-planning-tab.blade.php --}}
{{-- Port form Discharge Planning RI (multi-entri, kode SNOMED per entri) — sumber CarePlan Rencana
     Pemulangan SATUSEHAT UGD (Playbook IGD bab 22). Metode tambah/hapus ada di rm-perencanaan-ugd-actions. --}}
<x-border-form title="Discharge Planning" align="start" bgcolor="bg-surface-soft">
    <div class="mt-3 space-y-6">
        <div>
            <x-input-label value="Pelayanan Berkelanjutan" />
            <div class="mt-2 flex gap-4">
                @foreach (['Ada', 'Tidak Ada'] as $opt)
                    <x-radio-button :label="$opt" :value="$opt" name="pelayananBerkelanjutan-ugd"
                        wire:model.live="perencanaan.dischargePlanning.pelayananBerkelanjutan.pelayananBerkelanjutan"
                        :disabled="$isFormLocked" />
                @endforeach
            </div>
            @if (($perencanaan['dischargePlanning']['pelayananBerkelanjutan']['pelayananBerkelanjutan'] ?? '') === 'Ada')
                {{-- Entri terstruktur: tiap baris membawa kode SNOMED-nya sendiri (untuk CarePlan). --}}
                <div class="mt-3 space-y-2 rounded-lg border border-hairline p-3 dark:border-gray-700">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <x-input-label value="Jenis Pelayanan *" />
                            <x-select-input wire:model="formPelayanan.jenisPelayanan" class="mt-1 w-full"
                                :error="$errors->has('formPelayanan.jenisPelayanan')" :disabled="$isFormLocked">
                                <option value="">-- pilih --</option>
                                @foreach (\App\Support\Options\DischargePlanningOptions::PELAYANAN as $opt)
                                    <option value="{{ $opt['label'] }}">{{ $opt['label'] }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('formPelayanan.jenisPelayanan')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Tanggal Rencana" />
                            <div class="mt-1 flex gap-2">
                                <x-text-input wire:model="formPelayanan.tglRencana" class="flex-1"
                                    :error="$errors->has('formPelayanan.tglRencana')" placeholder="dd/mm/yyyy"
                                    :disabled="$isFormLocked" />
                                @if (!$isFormLocked)
                                    <x-now-button wire:click="setTglRencana" />
                                @endif
                            </div>
                            <x-input-error :messages="$errors->get('formPelayanan.tglRencana')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Tempat / Fasyankes" />
                            <x-text-input wire:model="formPelayanan.tempatFasyankes" class="mt-1 w-full"
                                placeholder="mis. Puskesmas Ngunut" :disabled="$isFormLocked" />
                        </div>
                        <div>
                            <x-input-label value="Keterangan" />
                            <x-text-input wire:model="formPelayanan.ketJenis" class="mt-1 w-full"
                                :error="$errors->has('formPelayanan.ketJenis')" placeholder="wajib diisi bila Lainnya"
                                :disabled="$isFormLocked" />
                            <x-input-error :messages="$errors->get('formPelayanan.ketJenis')" class="mt-1" />
                        </div>
                    </div>
                    <x-ghost-button type="button" wire:click="tambahPelayanan" wire:loading.attr="disabled"
                        :disabled="$isFormLocked" class="text-xs">+ Tambah Pelayanan</x-ghost-button>
                </div>

                @php
                    $rowsPelayanan = $perencanaan['dischargePlanning']['pelayananBerkelanjutan']['pelayananBerkelanjutanData'] ?? [];
                @endphp
                @if (count($rowsPelayanan) > 0)
                    <div class="mt-2 overflow-x-auto rounded-lg border border-hairline dark:border-gray-700">
                        <table class="ds-table">
                            <thead>
                                <tr class="text-left">
                                    <th class="min-w-[10rem]">Jenis Pelayanan</th>
                                    <th class="min-w-[11rem]">Tempat / Tgl Rencana</th>
                                    <th class="min-w-[8rem]">Keterangan</th>
                                    <th class="ds-c">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rowsPelayanan as $i => $row)
                                    <tr>
                                        <td>
                                            <div class="ds-td-strong">{{ $row['jenisPelayanan'] ?? '-' }}</div>
                                            @if (!empty($row['snomedCode']))
                                                <div class="ds-td-meta">{{ $row['snomedCode'] }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div>{{ ($row['tempatFasyankes'] ?? '') ?: '-' }}</div>
                                            @if (!empty($row['tglRencana']))
                                                <div class="ds-td-meta">{{ $row['tglRencana'] }}</div>
                                            @endif
                                        </td>
                                        <td>{{ ($row['ketJenis'] ?? '') ?: '-' }}</td>
                                        <td class="ds-c">
                                            @if (!$isFormLocked)
                                                <x-hapus-button :action="'hapusPelayanan(' . $i . ')'" title="Hapus Pelayanan" :message="'Yakin hapus ' . ($row['jenisPelayanan'] ?? 'entri ini') . '?'" />
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
        <div>
            <x-input-label value="Penggunaan Alat Bantu" />
            <div class="mt-2 flex gap-4">
                @foreach (['Ada', 'Tidak Ada'] as $opt)
                    <x-radio-button :label="$opt" :value="$opt" name="penggunaanAlatBantu-ugd"
                        wire:model.live="perencanaan.dischargePlanning.penggunaanAlatBantu.penggunaanAlatBantu"
                        :disabled="$isFormLocked" />
                @endforeach
            </div>
            @if (($perencanaan['dischargePlanning']['penggunaanAlatBantu']['penggunaanAlatBantu'] ?? '') === 'Ada')
                {{-- Entri terstruktur: tiap baris membawa kode SNOMED-nya sendiri (untuk CarePlan). --}}
                <div class="mt-3 space-y-2 rounded-lg border border-hairline p-3 dark:border-gray-700">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <x-input-label value="Jenis Alat Bantu *" />
                            <x-select-input wire:model="formAlat.jenisAlat" class="mt-1 w-full"
                                :error="$errors->has('formAlat.jenisAlat')" :disabled="$isFormLocked">
                                <option value="">-- pilih --</option>
                                @foreach (\App\Support\Options\DischargePlanningOptions::ALAT_BANTU as $opt)
                                    <option value="{{ $opt['label'] }}">{{ $opt['label'] }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('formAlat.jenisAlat')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Sumber Alat" />
                            <x-select-input wire:model="formAlat.sumberAlat" class="mt-1 w-full"
                                :disabled="$isFormLocked">
                                <option value="">-- pilih --</option>
                                @foreach (\App\Support\Options\DischargePlanningOptions::SUMBER_ALAT as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label value="Durasi Penggunaan" />
                            <x-text-input wire:model="formAlat.durasi" class="mt-1 w-full"
                                placeholder="mis. 2 minggu / seterusnya" :disabled="$isFormLocked" />
                        </div>
                        <div>
                            <x-input-label value="Keterangan" />
                            <x-text-input wire:model="formAlat.ketAlat" class="mt-1 w-full"
                                :error="$errors->has('formAlat.ketAlat')" placeholder="wajib diisi bila Lainnya"
                                :disabled="$isFormLocked" />
                            <x-input-error :messages="$errors->get('formAlat.ketAlat')" class="mt-1" />
                        </div>
                    </div>
                    <x-ghost-button type="button" wire:click="tambahAlat" wire:loading.attr="disabled"
                        :disabled="$isFormLocked" class="text-xs">+ Tambah Alat Bantu</x-ghost-button>
                </div>

                @php
                    $rowsAlat = $perencanaan['dischargePlanning']['penggunaanAlatBantu']['penggunaanAlatBantuData'] ?? [];
                @endphp
                @if (count($rowsAlat) > 0)
                    <div class="mt-2 overflow-x-auto rounded-lg border border-hairline dark:border-gray-700">
                        <table class="ds-table">
                            <thead>
                                <tr class="text-left">
                                    <th class="min-w-[10rem]">Jenis Alat Bantu</th>
                                    <th class="min-w-[8rem]">Sumber Alat</th>
                                    <th class="min-w-[7rem]">Durasi</th>
                                    <th class="min-w-[8rem]">Keterangan</th>
                                    <th class="ds-c">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rowsAlat as $i => $row)
                                    <tr>
                                        <td>
                                            <div class="ds-td-strong">{{ $row['jenisAlat'] ?? '-' }}</div>
                                            @if (!empty($row['snomedCode']))
                                                <div class="ds-td-meta">{{ $row['snomedCode'] }}</div>
                                            @endif
                                        </td>
                                        <td>{{ ($row['sumberAlat'] ?? '') ?: '-' }}</td>
                                        <td>{{ ($row['durasi'] ?? '') ?: '-' }}</td>
                                        <td>{{ ($row['ketAlat'] ?? '') ?: '-' }}</td>
                                        <td class="ds-c">
                                            @if (!$isFormLocked)
                                                <x-hapus-button :action="'hapusAlat(' . $i . ')'" title="Hapus Alat Bantu" :message="'Yakin hapus ' . ($row['jenisAlat'] ?? 'entri ini') . '?'" />
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-border-form>
