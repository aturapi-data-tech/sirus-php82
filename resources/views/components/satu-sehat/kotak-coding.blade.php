@props([
    // Judul kecil kotak, mis. "Keluhan Utama (3a)" — diawali "Coding SATUSEHAT ·" otomatis.
    'judul' => '',
])

{{-- Kotak isian kode di bawah teks yang dikoding — layar Coding SATUSEHAT. --}}
<div class="p-3 mt-2 space-y-2 border border-brand-green/30 rounded-xl bg-brand-green/5 dark:bg-brand-green/10 dark:border-brand-green/50">
    <div class="text-xs font-semibold tracking-wide text-brand-green uppercase dark:text-brand-lime">Coding SATUSEHAT · {{ $judul }}</div>
    {{ $slot }}
</div>
