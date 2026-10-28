{{-- Partial coding-anamnesa-ri — mewarisi VARIABEL induk, BUKAN import: kelas dipanggil lewat @use. --}}
@use('App\Support\RiwayatPenyakitKeluarga')
{{-- Riwayat Penyakit Keluarga (3e) — hubungan + LOV + keterangan → Tambah --}}
<div>
    <x-satu-sehat.baris-baca label="Riwayat Penyakit Keluarga" :isi="$koding['keluarga']['teks'] ?? ''" />
    <x-satu-sehat.kotak-coding judul="Riwayat Penyakit Keluarga (3e, boleh lebih dari satu)">
        <div class="grid grid-cols-12 gap-2">
            <div class="col-span-4">
                <x-input-label value="Hubungan Keluarga" :required="true" />
                <x-select-input wire:model="formKeluarga.hubungan" :error="$errors->has('formKeluarga.hubungan')" class="w-full mt-1">
                    <option value="">— pilih —</option>
                    @foreach (RiwayatPenyakitKeluarga::HUBUNGAN as $kodeHubungan => $hubungan)
                        <option value="{{ $kodeHubungan }}">{{ $hubungan['label'] }}</option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('formKeluarga.hubungan')" class="mt-1" />
            </div>
            <div class="col-span-8">
                <livewire:lov.snomed.lov-snomed target="codingRiwayatKeluargaSnomed"
                    label="Kode SNOMED Penyakit" valueSet="condition-code"
                    placeholder="Cari penyakit, mis. diabetes, hipertensi..."
                    wire:key="coding-lov-keluarga-{{ $riHdrNo }}-{{ $renderVersions['lov-coding-riwayat-keluarga'] ?? 0 }}" />
                <x-input-error :messages="$errors->get('formKeluarga.snomedCode')" class="mt-1" />
            </div>
        </div>
        <x-text-input wire:model="formKeluarga.keterangan" placeholder="Keterangan (opsional), mis. ibu menderita DM sejak 10 tahun lalu"
            maxlength="200" :error="$errors->has('formKeluarga.keterangan')" class="w-full" />
        <x-primary-button type="button" wire:click="tambahKeluarga" wire:loading.attr="disabled" wire:target="tambahKeluarga"
            class="justify-center w-full">Tambah</x-primary-button>

        <x-modul-dokumen.tabel-daftar :kolom="['Hubungan' => 'w-32', 'Kode SNOMED', 'Petugas' => 'w-44', 'Aksi' => 'text-center w-14']">
            @forelse ($koding['keluarga']['snomedList'] ?? [] as $index => $baris)
                <tbody wire:key="coding-keluarga-baris-{{ $riHdrNo }}-{{ RiwayatPenyakitKeluarga::kunci($baris) }}" class="border-b border-hairline dark:border-gray-700">
                    <tr class="align-top hover:bg-surface-soft dark:hover:bg-gray-800/60">
                        <td class="px-4 py-3 ds-td-strong">{{ RiwayatPenyakitKeluarga::labelHubungan($baris['hubungan'] ?? '') }}</td>
                        <td class="px-4 py-3">
                            <div class="ds-td-strong">{{ RiwayatPenyakitKeluarga::namaBaris($baris) }}
                        @if (!empty($baris['dariMaster']))<x-badge variant="warning" class="ml-1">kunjungan sebelumnya</x-badge>@endif</div>
                            <div class="text-muted dark:text-gray-400">{{ $baris['snomedDisplayEn'] ?? '' }} · <span class="font-mono">{{ $baris['snomedCode'] ?? '' }}</span></div>
                            @if (filled($baris['keterangan'] ?? null))
                                <div class="text-muted dark:text-gray-400">{{ $baris['keterangan'] }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if (filled($baris['petugasCatat'] ?? null))
                                <div class="ds-td-strong">{{ $baris['petugasCatat'] }}</div>
                                <div class="text-muted dark:text-gray-400">{{ $baris['tglCatat'] ?? '' }}</div>
                            @else
                                <span class="text-muted-soft">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <x-hapus-button :action="'hapusKeluarga(' . $index . ')'" title="Hapus Kode"
                                :message="'Hapus ' . RiwayatPenyakitKeluarga::namaBaris($baris) . ' (' . RiwayatPenyakitKeluarga::labelHubungan($baris['hubungan'] ?? '') . ') dari daftar?'" />
                        </td>
                    </tr>
                </tbody>
            @empty
                <x-modul-dokumen.baris-kosong :kolom="4" pesan="Belum ada kode riwayat penyakit keluarga (opsional)" />
            @endforelse
        </x-modul-dokumen.tabel-daftar>
    </x-satu-sehat.kotak-coding>
</div>
