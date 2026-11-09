{{--
    Badge topbar cakupan LOINC tindakan radiologi (role diatur di layouts/app-topbar).
    Klik = Master Radiologi tersaring "aktif belum LOINC", urut dari yang paling sering dipakai.
    Tidak tampil bila angkanya gagal dihitung (App\Support\CakupanLoincRadiologi::ringkasan()).
--}}
@php
    $ringkasan = \App\Support\CakupanLoincRadiologi::ringkasan();
@endphp

@if ($ringkasan)
    @php
        $warnaBadge = match (true) {
            $ringkasan['persen'] >= 90 => 'text-green-700 bg-green-100',
            $ringkasan['persen'] >= 60 => 'text-amber-700 bg-amber-100',
            default => 'text-red-700 bg-red-100',
        };
        $keterangan = "Tindakan radiologi ber-LOINC: {$ringkasan['persen']}% ({$ringkasan['berkode']} dari {$ringkasan['total']})\n"
            . "Permintaan " . \App\Support\CakupanLoincRadiologi::HARI_PEMAKAIAN . " hari: {$ringkasan['persenPakai']}% ({$ringkasan['pakaiBerkode']} dari {$ringkasan['pakaiTotal']} permintaan)\n"
            . 'Klik untuk melihat yang belum ber-LOINC';
    @endphp

    <a href="{{ route('master.radiologis', ['loinc' => 'belum']) }}" wire:navigate title="{{ $keterangan }}" aria-label="{{ $keterangan }}"
        class="hidden px-2 py-1 text-xs font-semibold rounded-full sm:inline-block whitespace-nowrap hover:opacity-80 {{ $warnaBadge }}">
        Rad {{ round($ringkasan['persen']) }}%
    </a>
@endif
