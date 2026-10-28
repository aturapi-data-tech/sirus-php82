{{-- Partial coding-anamnesa-ugd. Keluhan Penyerta TIDAK ada: alur IGD tak memuatnya (beda dari RJ 3b). --}}
{{-- Keluhan Utama --}}
<div>
    <x-satu-sehat.baris-baca label="Keluhan Utama" :isi="$koding['keluhanUtama']['teks'] ?? ''" />
    <x-satu-sehat.kotak-coding judul="Keluhan Utama (4a)">
        <livewire:lov.snomed.lov-snomed target="codingKeluhanUtamaSnomed"
            label="Kode SNOMED" valueSet="condition-code"
            placeholder="Ketik keluhan dalam Bahasa Indonesia / Inggris..."
            :initialSnomedCode="($koding['keluhanUtama']['snomedCode'] ?? '') ?: null"
            wire:key="coding-ku-{{ $rjNo }}" />
    </x-satu-sehat.kotak-coding>
</div>

