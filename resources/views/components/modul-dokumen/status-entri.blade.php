@props([
    // Entri sudah final (TTD petugas / terkunci).
    'final' => false,
    // Label saat belum final (Identifikasi Bayi: "Belum TTD").
    'labelDraft' => 'Draft',
])

{{-- Badge status entri modul dokumen (tabel daftar & pratinjau kartu): Terkunci (info) / Draft (warning). --}}
@if ($final)
    <x-badge variant="info" {{ $attributes }}>Terkunci</x-badge>
@else
    <x-badge variant="warning" {{ $attributes }}>{{ $labelDraft }}</x-badge>
@endif
