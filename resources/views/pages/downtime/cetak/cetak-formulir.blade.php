{{-- Pembungkus cetak formulir manual down time.
     Dipakai untuk satu formulir maupun bundel (banyak formulir dalam 1 PDF).
     $daftar  : array entri registry FormulirDowntime yang dicetak
     $sampul  : true → halaman sampul + daftar isi di depan (mode bundel)

     Kop halaman pertama berasal dari layout cetak standar; blok berikutnya
     (formulir ke-2 dst.) mencetak kopnya sendiri lewat x-downtime.halaman. --}}
@php
    // Formulir pertama menumpang kop layout — varian identitasnya dikirim ke
    // slot patientData supaya blok identitas pasien tercetak di kiri logo.
    $identitasAwal = ($sampul ?? false) ? null : ($daftar[0]['identitas'] ?? null);
@endphp

<x-downtime.dokumen :identitasAwal="$identitasAwal">

    @if ($sampul ?? false)
        <x-downtime.sampul :judul="$judul" :daftar="$daftar" :dicetakOleh="$dicetakOleh ?? null" :tglCetak="$tglCetak ?? null" />
    @endif

    @foreach ($daftar as $formulir)
        @include(\App\Support\Downtime\FormulirDowntime::viewName($formulir), [
            'dtBreak' => ($sampul ?? false) || !$loop->first,
        ])
    @endforeach

</x-downtime.dokumen>
