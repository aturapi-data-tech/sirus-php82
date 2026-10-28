{{-- Partial coding-anamnesa-rj — mewarisi VARIABEL induk, BUKAN import: kelas dipanggil lewat @use. --}}
@use('App\Support\KeluhanPenyerta')
{{-- Keluhan Utama --}}
<div>
    <x-satu-sehat.baris-baca label="Keluhan Utama" :isi="$koding['keluhanUtama']['teks'] ?? ''" />
    <x-satu-sehat.kotak-coding judul="Keluhan Utama (3a)">
        <livewire:lov.snomed.lov-snomed target="codingKeluhanUtamaSnomed"
            label="Kode SNOMED" valueSet="condition-code"
            placeholder="Ketik keluhan dalam Bahasa Indonesia / Inggris..."
            :initialSnomedCode="($koding['keluhanUtama']['snomedCode'] ?? '') ?: null"
            wire:key="coding-ku-{{ $rjNo }}" />
    </x-satu-sehat.kotak-coding>
</div>

{{-- Keluhan Penyerta (daftar kode) --}}
<div>
    <x-satu-sehat.baris-baca label="Keluhan Penyerta" :isi="$koding['keluhanPenyerta']['teks'] ?? ''" />
    <x-satu-sehat.kotak-coding judul="Keluhan Penyerta (3b, boleh lebih dari satu)">
        <livewire:lov.snomed.lov-snomed target="codingKeluhanPenyertaSnomed"
            label="Kode SNOMED" valueSet="condition-code"
            placeholder="Ketik keluhan, pilih — langsung masuk daftar..."
            wire:key="coding-kp-{{ $rjNo }}-{{ $renderVersions['lov-coding-keluhan-penyerta'] ?? 0 }}" />

        <x-modul-dokumen.tabel-daftar :kolom="['Kode SNOMED', 'Petugas' => 'w-44', 'Aksi' => 'text-center w-14']">
            @forelse ($koding['keluhanPenyerta']['snomedList'] ?? [] as $index => $baris)
                <tbody wire:key="coding-kp-baris-{{ $rjNo }}-{{ $baris['snomedCode'] ?? $index }}" class="border-b border-hairline dark:border-gray-700">
                    <tr class="align-top hover:bg-surface-soft dark:hover:bg-gray-800/60">
                        <td class="px-4 py-3">
                            <div class="ds-td-strong">{{ KeluhanPenyerta::namaBaris($baris) }}</div>
                            <div class="text-muted dark:text-gray-400">{{ $baris['snomedDisplayEn'] ?? '' }} · <span class="font-mono">{{ $baris['snomedCode'] ?? '' }}</span></div>
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
                            <x-hapus-button :action="'hapusKeluhanPenyerta(' . $index . ')'" title="Hapus Kode"
                                :message="'Hapus ' . KeluhanPenyerta::namaBaris($baris) . ' dari daftar keluhan penyerta?'" />
                        </td>
                    </tr>
                </tbody>
            @empty
                <x-modul-dokumen.baris-kosong :kolom="3" pesan="Belum ada kode keluhan penyerta (opsional)" />
            @endforelse
        </x-modul-dokumen.tabel-daftar>
    </x-satu-sehat.kotak-coding>
</div>
