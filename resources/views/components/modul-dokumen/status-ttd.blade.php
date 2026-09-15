@props([
    // Nama penanda tangan (petugas). Kosong / "-" = belum TTD.
    'nama' => '',
    // TTD tanpa nama (pasien/keluarga lewat signature pad): true → "Sudah TTD". Diabaikan bila nama terisi.
    'sudah' => false,
    // Waktu TTD (opsional) → "… — waktu"; tampil bila terisi.
    'waktu' => null,
    // tebal = tabel daftar (nama tebal) · biasa = baris rincian · polos = warna ikut sel (pratinjau kartu)
    'gaya' => 'tebal',
])

@php $adaNama = filled($nama) && $nama !== '-'; @endphp

{{-- Sel status TTD modul dokumen: nama petugas / "Sudah TTD" (pasien) / badge merah "Belum TTD". --}}
@if ($adaNama || $sudah)
    @if (!$adaNama)
        <span class="text-success-deep dark:text-green-300">Sudah TTD</span>
    @elseif ($gaya === 'polos')
        {{ $nama }}
    @else
        <span class="{{ $gaya === 'tebal' ? 'font-medium ' : '' }}text-ink dark:text-gray-200">{{ $nama }}</span>
    @endif
    @if (filled($waktu))
        <span class="text-sm text-muted-soft">— {{ $waktu }}</span>
    @endif
@else
    <x-badge variant="danger">Belum TTD</x-badge>
@endif
