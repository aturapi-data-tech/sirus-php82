{{-- Partial coding-anamnesa-ri — mewarisi VARIABEL induk, BUKAN import: kelas dipanggil lewat @use. --}}
@use('App\Support\AlergiAlergen')
{{-- Alergi (3c) — "Tidak" = pernyataan 716186003 milik EMR; "Ya" = daftar alergen:
     jenis Obat (KFA master obat) / Makanan / Lingkungan (SNOMED) + keparahan → Tambah --}}
<div>
    <x-satu-sehat.baris-baca label="Alergi"
        :isi="match ($koding['alergi']['adaAlergi'] ?? '') {
            'Ya' => ($koding['alergi']['teks'] ?? '') ?: 'Ada (belum dirinci)',
            'Tidak' => 'Tidak ada alergi',
            default => 'Belum diisi di Pengkajian Dokter — alergi tidak dikirim',
        }" />
    <x-satu-sehat.kotak-coding judul="Alergi (3c, boleh lebih dari satu)">
        @if (($koding['alergi']['adaAlergi'] ?? '') === 'Ya')
            <div>
                <x-input-label value="Jenis Alergi" :required="true" />
                <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1">
                    @foreach (AlergiAlergen::JENIS as $kodeJenis => $labelJenis)
                        <x-radio-button :label="$labelJenis" :value="$kodeJenis" name="coding-alergi-jenis"
                            wire:model.live="formAlergi.jenis" />
                    @endforeach
                </div>
            </div>
            <div class="grid grid-cols-12 gap-2">
                <div class="col-span-8">
                    @if (($formAlergi['jenis'] ?? '') === 'medication')
                        <livewire:lov.product.lov-product target="codingAlergiObat" label="Obat Penyebab Alergi (kode KFA)"
                            wire:key="coding-lov-alergi-obat-{{ $riHdrNo }}-{{ $renderVersions['lov-coding-alergi'] ?? 0 }}" />
                        @if (filled($formAlergi['kode'] ?? null))
                            <div class="mt-1 font-mono text-xs text-brand-green dark:text-brand-lime">KFA {{ $formAlergi['kode'] }} · {{ $formAlergi['display'] ?? '' }}</div>
                        @endif
                    @else
                        <livewire:lov.snomed.lov-snomed target="codingAlergiSnomed"
                            label="Alergen (SNOMED)" valueSet="substance-code"
                            placeholder="Cari alergen, mis. seafood, debu rumah..."
                            wire:key="coding-lov-alergi-snomed-{{ $riHdrNo }}-{{ $renderVersions['lov-coding-alergi'] ?? 0 }}" />
                    @endif
                    <x-input-error :messages="$errors->get('formAlergi.kode')" class="mt-1" />
                </div>
                <div class="col-span-4">
                    <x-input-label value="Tingkat Keparahan" :required="true" />
                    <x-select-input wire:model="formAlergi.keparahan" :error="$errors->has('formAlergi.keparahan')" class="w-full mt-1">
                        @foreach (AlergiAlergen::KEPARAHAN as $kodeKeparahan => $labelKeparahan)
                            <option value="{{ $kodeKeparahan }}">{{ $labelKeparahan }}</option>
                        @endforeach
                    </x-select-input>
                </div>
            </div>
            <x-primary-button type="button" wire:click="tambahAlergi" wire:loading.attr="disabled" wire:target="tambahAlergi"
                class="justify-center w-full">Tambah</x-primary-button>

            <x-modul-dokumen.tabel-daftar :kolom="['Jenis' => 'w-28', 'Alergen', 'Keparahan' => 'w-40', 'Petugas' => 'w-44', 'Aksi' => 'text-center w-14']">
                @forelse ($koding['alergi']['alergenList'] ?? [] as $index => $baris)
                    <tbody wire:key="coding-alergi-baris-{{ $riHdrNo }}-{{ AlergiAlergen::kunci($baris) }}" class="border-b border-hairline dark:border-gray-700">
                        <tr class="align-top hover:bg-surface-soft dark:hover:bg-gray-800/60">
                            <td class="px-4 py-3 ds-td-strong">{{ AlergiAlergen::labelJenis($baris['jenis'] ?? '') }}</td>
                            <td class="px-4 py-3">
                                <div class="ds-td-strong">{{ AlergiAlergen::namaBaris($baris) }}</div>
                                <div class="text-muted dark:text-gray-400">
                                    {{ ($baris['sistem'] ?? '') === AlergiAlergen::SISTEM_KFA ? 'KFA' : 'SNOMED' }}
                                    <span class="font-mono">{{ $baris['kode'] ?? '' }}</span> · {{ $baris['display'] ?? '' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ AlergiAlergen::labelKeparahan($baris['keparahan'] ?? '') }}</td>
                            <td class="px-4 py-3">
                                @if (filled($baris['petugasCatat'] ?? null))
                                    <div class="ds-td-strong">{{ $baris['petugasCatat'] }}</div>
                                    <div class="text-muted dark:text-gray-400">{{ $baris['tglCatat'] ?? '' }}</div>
                                @else
                                    <span class="text-muted-soft">kode lama</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-hapus-button :action="'hapusAlergi(' . $index . ')'" title="Hapus Alergen"
                                    :message="'Hapus ' . AlergiAlergen::namaBaris($baris) . ' dari daftar alergi?'" />
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <x-modul-dokumen.baris-kosong :kolom="5" pesan="Belum ada alergen — wajib diisi karena EMR menyatakan ada alergi" />
                @endforelse
            </x-modul-dokumen.tabel-daftar>
        @elseif (($koding['alergi']['adaAlergi'] ?? '') === 'Tidak')
            <div class="text-sm text-muted dark:text-gray-400">
                Terekam otomatis sebagai <span class="font-semibold text-ink dark:text-gray-100">Tidak ada alergi</span>
                <span class="font-mono text-xs">716186003</span> — dari Pengkajian Dokter.
            </div>
        @else
            <div class="text-sm text-warning-deep dark:text-amber-200">
                Alergi belum diisi di Pengkajian Dokter — tidak dikirim (bukan dianggap "tidak ada alergi").
            </div>
        @endif
    </x-satu-sehat.kotak-coding>
</div>
