{{--
    Badge topbar cakupan Location poli (role diatur di layouts/app-topbar).
    Klik = Master Poli tersaring "belum Location", urut dari yang paling sering dipakai.
    Tidak tampil bila angkanya gagal dihitung (App\Support\CakupanLocationPoli::ringkasan()).
--}}
@php
    $ringkasan = \App\Support\CakupanLocationPoli::ringkasan();
@endphp

@if ($ringkasan)
    @php
        $angkaBadge = $ringkasan['pakaiTotal'] > 0 ? $ringkasan['persenPakai'] : $ringkasan['persen'];
        $warnaBadge = match (true) {
            $angkaBadge >= 90 => 'text-green-700 bg-green-100',
            $angkaBadge >= 60 => 'text-amber-700 bg-amber-100',
            default => 'text-red-700 bg-red-100',
        };
        $keterangan = "Poli ber-Location: {$ringkasan['persen']}% ({$ringkasan['berkode']} dari {$ringkasan['total']})\n"
            . "Kunjungan RJ " . \App\Support\CakupanLocationPoli::HARI_PEMAKAIAN . " hari: {$ringkasan['persenPakai']}% ({$ringkasan['pakaiBerkode']} dari {$ringkasan['pakaiTotal']})\n"
            . 'Klik untuk melihat yang belum ber-Location';
    @endphp

    <a href="{{ route('master.poli', ['location' => 'belum']) }}" wire:navigate title="{{ $keterangan }}" aria-label="{{ $keterangan }}"
        class="hidden px-2 py-1 text-xs font-semibold rounded-full sm:inline-block whitespace-nowrap hover:opacity-80 {{ $warnaBadge }}">
        Poli {{ round($angkaBadge) }}%
    </a>
@endif
