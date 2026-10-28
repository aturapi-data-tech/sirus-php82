{{-- Partial coding-anamnesa-rj — mewarisi VARIABEL induk, BUKAN import: kelas dipanggil lewat @use. --}}
@use('App\Support\RiwayatPenyakitPribadi')
{{-- Riwayat Penyakit Sekarang & Dahulu (3d) — pola Rekonsiliasi Obat: LOV + keterangan → Tambah --}}
@foreach ([
    [RiwayatPenyakitPribadi::JENIS_SEKARANG, 'Riwayat Penyakit Sekarang', 'Riwayat Penyakit Sekarang (3d, masih berjalan)', 'codingRiwayatSekarangSnomed', 'lov-coding-riwayat-sekarang'],
    [RiwayatPenyakitPribadi::JENIS_DAHULU, 'Riwayat Penyakit Dahulu', 'Riwayat Penyakit Dahulu (3d, sudah sembuh)', 'codingRiwayatDahuluSnomed', 'lov-coding-riwayat-dahulu'],
] as [$jenis, $label, $judulKoding, $targetLov, $areaLov])
    <div wire:key="coding-riwayat-{{ $jenis }}-{{ $rjNo }}">
        <x-satu-sehat.baris-baca :label="$label" :isi="$koding[$jenis]['teks'] ?? ''"
            :kode="$jenis === RiwayatPenyakitPribadi::JENIS_SEKARANG && filled($koding[$jenis]['teks'] ?? null)
                ? 'teks ini juga dikirim sebagai Riwayat Perjalanan Penyakit (bab 6) · ClinicalImpression SNOMED 312850006' : null" />
        <x-satu-sehat.kotak-coding :judul="$judulKoding">
            <div class="grid grid-cols-12 gap-2">
                <div class="col-span-8">
                    <livewire:lov.snomed.lov-snomed :target="$targetLov"
                        label="Kode SNOMED Penyakit" valueSet="condition-code"
                        placeholder="Cari penyakit, mis. diabetes, hipertensi..."
                        wire:key="coding-lov-{{ $jenis }}-{{ $rjNo }}-{{ $renderVersions[$areaLov] ?? 0 }}" />
                    <x-input-error :messages="$errors->get('formRiwayat.' . $jenis . '.snomedCode')" class="mt-1" />
                </div>
                <div class="col-span-4">
                    @if ($jenis === RiwayatPenyakitPribadi::JENIS_SEKARANG)
                        <x-input-label value="Mulai Diderita" :required="true" />
                        <x-text-input wire:model="formRiwayat.{{ $jenis }}.tglMulai" placeholder="dd/mm/yyyy" maxlength="10"
                            :error="$errors->has('formRiwayat.' . $jenis . '.tglMulai')" class="w-full mt-1" />
                        <x-input-error :messages="$errors->get('formRiwayat.' . $jenis . '.tglMulai')" class="mt-1" />
                    @else
                        <x-input-label value="Usia Saat Menderita (tahun)" :required="true" />
                        <x-text-input wire:model="formRiwayat.{{ $jenis }}.usiaTahun" placeholder="mis. 12" maxlength="3"
                            :error="$errors->has('formRiwayat.' . $jenis . '.usiaTahun')" class="w-full mt-1" />
                        <x-input-error :messages="$errors->get('formRiwayat.' . $jenis . '.usiaTahun')" class="mt-1" />
                    @endif
                </div>
            </div>
            <x-primary-button type="button" wire:click="tambahRiwayat('{{ $jenis }}')" wire:loading.attr="disabled"
                wire:target="tambahRiwayat" class="justify-center w-full">Tambah</x-primary-button>

            <x-modul-dokumen.tabel-daftar :kolom="['Kode SNOMED', 'Keterangan' => 'w-36', 'Petugas' => 'w-44', 'Aksi' => 'text-center w-14']">
                @forelse ($koding[$jenis]['snomedList'] ?? [] as $index => $baris)
                    <tbody wire:key="coding-riwayat-baris-{{ $jenis }}-{{ $rjNo }}-{{ $baris['snomedCode'] ?? $index }}" class="border-b border-hairline dark:border-gray-700">
                        <tr class="align-top hover:bg-surface-soft dark:hover:bg-gray-800/60">
                            <td class="px-4 py-3">
                                <div class="ds-td-strong">{{ RiwayatPenyakitPribadi::namaBaris($baris) }}
                                @if (!empty($baris['dariMaster']))<x-badge variant="warning" class="ml-1">kunjungan sebelumnya</x-badge>@endif</div>
                                <div class="text-muted dark:text-gray-400">{{ $baris['snomedDisplayEn'] ?? '' }} · <span class="font-mono">{{ $baris['snomedCode'] ?? '' }}</span></div>
                            </td>
                            <td class="px-4 py-3">{{ RiwayatPenyakitPribadi::keteranganBaris($baris) }}</td>
                            <td class="px-4 py-3">
                                @if (filled($baris['petugasCatat'] ?? null))
                                    <div class="ds-td-strong">{{ $baris['petugasCatat'] }}</div>
                                    <div class="text-muted dark:text-gray-400">{{ $baris['tglCatat'] ?? '' }}</div>
                                @else
                                    <span class="text-muted-soft">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-hapus-button :action="'hapusRiwayat(\'' . $jenis . '\', ' . $index . ')'" title="Hapus Kode"
                                    :message="'Hapus ' . RiwayatPenyakitPribadi::namaBaris($baris) . ' dari daftar riwayat penyakit?'" />
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <x-modul-dokumen.baris-kosong :kolom="4" pesan="Belum ada kode riwayat penyakit (opsional)" />
                @endforelse
            </x-modul-dokumen.tabel-daftar>
        </x-satu-sehat.kotak-coding>
    </div>
@endforeach
