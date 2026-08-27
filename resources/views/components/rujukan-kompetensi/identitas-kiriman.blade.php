{{-- resources/views/components/rujukan-kompetensi/identitas-kiriman.blade.php

    Dua nomor yang IKUT DIKIRIM saat rujukan diterbitkan: No. SEP (khusus jalur
    BPJS-SISRUTE) dan UUID Encounter SATUSEHAT (kedua jalur).

    Dulu keduanya hanya terlihat lewat ketiadaannya — muncul di daftar "belum
    bisa mengirim" saat kosong, lalu hilang sama sekali begitu terisi. Akibatnya
    petugas tak punya cara memastikan nomor MANA yang dipakai, dan saat gagal
    tak tahu apakah Encounter-nya memang sudah terbit. Sekarang keduanya selalu
    tampil: terisi = nilainya, kosong = peringatan beserta cara mengisinya.

    Prop:
      :noSep        null = jalur ini tak memakai SEP (FHIR) → barisnya tak tampil
      :encounterId  UUID Encounter SATUSEHAT kunjungan ini
--}}

@props(['noSep' => null, 'encounterId' => ''])

@php
    $noSep = $noSep === null ? null : trim((string) $noSep);
    $encounterId = trim((string) $encounterId);
@endphp

<div class="space-y-1 text-xs">
    @if ($noSep !== null)
        <p class="text-muted-soft">No. SEP kunjungan:
            @if ($noSep === '')
                <span class="font-semibold text-red-700 dark:text-red-300">belum terbit</span>
                <span class="text-muted-soft">— terbitkan dulu lewat menu SEP kunjungan ini.</span>
            @else
                <span class="font-mono text-ink dark:text-gray-200">{{ $noSep }}</span>
            @endif
        </p>
    @endif
    <p class="text-muted-soft">Encounter SATUSEHAT:
        @if ($encounterId === '')
            <span class="font-semibold text-red-700 dark:text-red-300">belum terkirim</span>
            <span class="text-muted-soft">— kirim lewat Daftar kunjungan → menu Satu Sehat → Encounter.</span>
        @else
            {{-- break-all: UUID 36 karakter tanpa spasi; tanpa ini kolom sempit melebar. --}}
            <span class="font-mono break-all text-ink dark:text-gray-200">{{ $encounterId }}</span>
        @endif
    </p>
</div>
