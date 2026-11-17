@props([
    // Nama aksi dari App\Support\IkonTombol::PETA (simpan, batal, tambah, ubah, ...).
    'nama',
    // Kelas ukuran; tombol kecil (text-xs) pakai 'w-3.5 h-3.5'. Dipisah dari class supaya tidak bentrok.
    'ukuran' => 'w-4 h-4',
])

{{--
    Ikon baku untuk tombol berteks — peta ada di App\Support\IkonTombol (sumber tunggal).
    Ukuran lewat prop ukuran (default w-4 h-4), BUKAN class — dua kelas lebar akan bentrok.
    Nama tak dikenal: tidak merender apa pun, tapi dicatat ke log supaya salah ketik ketahuan.
    Contoh pemakaian ada di docs/standar-komponen-tombol.md bagian Ikon Tombol.
--}}
@php
    $daftarPath = \App\Support\IkonTombol::paths($nama);
    if ($daftarPath === null) {
        report(new \InvalidArgumentException("Ikon tombol '{$nama}' tidak ada di App\\Support\\IkonTombol::PETA"));
    }
@endphp

@if ($daftarPath !== null)
    <svg {{ $attributes->class([$ukuran, 'shrink-0']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24"
        stroke-width="2" aria-hidden="true">
        @foreach ($daftarPath as $path)
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
        @endforeach
    </svg>
@endif
