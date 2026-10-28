{{-- Partial coding-anamnesa-rj — mewarisi VARIABEL induk, BUKAN import: kelas dipanggil lewat @use. --}}
@use('App\Support\RiwayatPengobatan')
{{-- Riwayat Pengobatan (3f) — obat dari master (kode KFA) + status + aturan pakai → Tambah --}}
<div>
    <x-satu-sehat.baris-baca label="Riwayat Pengobatan" :isi="$koding['pengobatan']['teks'] ?? ''" />
    <x-satu-sehat.kotak-coding judul="Riwayat Pengobatan (3f, obat ber-KFA, boleh lebih dari satu)">
        <div class="grid grid-cols-12 gap-2">
            <div class="col-span-8">
                <livewire:lov.product.lov-product target="codingRiwayatObat" label="Obat (dari Master Obat)"
                    wire:key="coding-lov-obat-{{ $rjNo }}-{{ $renderVersions['lov-coding-riwayat-obat'] ?? 0 }}" />
                <x-input-error :messages="$errors->get('formObat.productId')" class="mt-1" />
                @if (filled($formObat['kfaCode'] ?? null))
                    <div class="mt-1 font-mono text-xs text-brand-green dark:text-brand-lime">KFA {{ $formObat['kfaCode'] }} · {{ $formObat['kfaDisplay'] ?? '' }}</div>
                @endif
                <x-input-error :messages="$errors->get('formObat.kfaCode')" class="mt-1" />
            </div>
            <div class="col-span-4">
                <x-input-label value="Status Konsumsi" :required="true" />
                <x-select-input wire:model="formObat.status" :error="$errors->has('formObat.status')" class="w-full mt-1">
                    @foreach (RiwayatPengobatan::STATUS as $kodeStatus => $labelStatus)
                        <option value="{{ $kodeStatus }}">{{ $labelStatus }}</option>
                    @endforeach
                </x-select-input>
            </div>
        </div>
        <x-text-input wire:model="formObat.aturanPakai" placeholder="Aturan pakai (opsional), mis. 1 tablet 3x sehari sesudah makan"
            maxlength="200" :error="$errors->has('formObat.aturanPakai')" class="w-full" />
        <x-primary-button type="button" wire:click="tambahObat" wire:loading.attr="disabled" wire:target="tambahObat"
            class="justify-center w-full">Tambah</x-primary-button>

        <x-modul-dokumen.tabel-daftar :kolom="['Obat (KFA)', 'Status' => 'w-36', 'Petugas' => 'w-44', 'Aksi' => 'text-center w-14']">
            @forelse ($koding['pengobatan']['obatList'] ?? [] as $index => $baris)
                <tbody wire:key="coding-obat-baris-{{ $rjNo }}-{{ $baris['productId'] ?? $index }}" class="border-b border-hairline dark:border-gray-700">
                    <tr class="align-top hover:bg-surface-soft dark:hover:bg-gray-800/60">
                        <td class="px-4 py-3">
                            <div class="ds-td-strong">{{ $baris['namaObat'] ?? '-' }}</div>
                            <div class="text-muted dark:text-gray-400">KFA <span class="font-mono">{{ $baris['kfaCode'] ?? '' }}</span> · {{ $baris['kfaDisplay'] ?? '' }}</div>
                            @if (filled($baris['aturanPakai'] ?? null))
                                <div class="text-muted dark:text-gray-400">{{ $baris['aturanPakai'] }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ RiwayatPengobatan::labelStatus($baris['status'] ?? '') }}</td>
                        <td class="px-4 py-3">
                            @if (filled($baris['petugasCatat'] ?? null))
                                <div class="ds-td-strong">{{ $baris['petugasCatat'] }}</div>
                                <div class="text-muted dark:text-gray-400">{{ $baris['tglCatat'] ?? '' }}</div>
                            @else
                                <span class="text-muted-soft">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <x-hapus-button :action="'hapusObat(' . $index . ')'" title="Hapus Obat"
                                :message="'Hapus ' . ($baris['namaObat'] ?? 'obat ini') . ' dari daftar riwayat pengobatan?'" />
                        </td>
                    </tr>
                </tbody>
            @empty
                <x-modul-dokumen.baris-kosong :kolom="4" pesan="Belum ada obat riwayat pengobatan (opsional)" />
            @endforelse
        </x-modul-dokumen.tabel-daftar>
    </x-satu-sehat.kotak-coding>
</div>
