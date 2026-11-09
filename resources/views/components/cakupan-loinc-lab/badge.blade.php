{{--
    Badge topbar cakupan LOINC item lab (role diatur di layouts/app-topbar).
    Klik = Master Lab tersaring "belum LOINC", urut dari yang paling sering dipakai.
    Tidak tampil bila angkanya gagal dihitung (App\Support\CakupanLoincLab::ringkasan()).
--}}
@php
    $ringkasan = \App\Support\CakupanLoincLab::ringkasan();
@endphp

@if ($ringkasan)
    @php
        $warnaBadge = match (true) {
            $ringkasan['persen'] >= 90 => 'text-green-700 bg-green-100',
            $ringkasan['persen'] >= 60 => 'text-amber-700 bg-amber-100',
            default => 'text-red-700 bg-red-100',
        };
        $keterangan = "Item lab ber-LOINC: {$ringkasan['persen']}% ({$ringkasan['berkode']} dari {$ringkasan['total']})\n"
            . "Hasil lab " . \App\Support\CakupanLoincLab::HARI_PEMAKAIAN . " hari: {$ringkasan['persenPakai']}% ({$ringkasan['pakaiBerkode']} dari {$ringkasan['pakaiTotal']} hasil)\n"
            . 'Klik untuk melihat yang belum ber-LOINC';
    @endphp

    <a href="{{ route('master.laborat', ['loinc' => 'belum']) }}" wire:navigate title="{{ $keterangan }}" aria-label="{{ $keterangan }}"
        class="hidden px-2 py-1 text-xs font-semibold rounded-full sm:inline-block whitespace-nowrap hover:opacity-80 {{ $warnaBadge }}">
        Lab {{ round($ringkasan['persen']) }}%
    </a>
@endif
