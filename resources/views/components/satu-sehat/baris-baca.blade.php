@props([
    // Label kiri (tanpa titik dua — ditambahkan otomatis).
    'label' => '',
    // Isi teks. Kosong → '-'. Baris baru di isi ikut tampil (whitespace-pre-line).
    'isi' => '',
    // Opsional: keterangan kode yang dikirim ke SATUSEHAT (mis. "LOINC 8867-4 · Heart rate"),
    // tampil kecil di bawah isi. null = tanpa baris kode.
    'kode' => null,
])

{{-- Satu baris BACA gaya Rekam Medis open ("Label : isi") — dipakai layar Coding SATUSEHAT. --}}
<div class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0">
    {{-- Label kosong = baris lanjutan dari label di atasnya (mis. beberapa status) — tanpa titik dua. --}}
    <span class="w-48 text-right shrink-0 text-muted">{{ filled($label) ? $label . ' :' : '' }}</span>
    <span class="min-w-0">
        <span class="block whitespace-pre-line text-body dark:text-gray-300">{{ trim((string) $isi) !== '' ? $isi : '-' }}</span>
        @if (filled($kode))
            <span class="block font-mono text-xs text-brand-green dark:text-brand-lime">{{ $kode }}</span>
        @endif
    </span>
</div>
