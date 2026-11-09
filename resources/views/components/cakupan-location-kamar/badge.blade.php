{{--
    Badge topbar cakupan Location kamar rawat inap (role diatur di layouts/app-topbar).
    Klik = Master Kamar tersaring "aktif belum Location", urut dari yang paling sering dipakai.
    Tidak tampil bila angkanya gagal dihitung (App\Support\CakupanLocationKamar::ringkasan()).
--}}
@php
    $ringkasan = \App\Support\CakupanLocationKamar::ringkasan();
@endphp

@if ($ringkasan)
    @php
        $angkaBadge = $ringkasan['persen'];
        $warnaBadge = match (true) {
            $angkaBadge >= 90 => 'text-green-700 bg-green-100',
            $angkaBadge >= 60 => 'text-amber-700 bg-amber-100',
            default => 'text-red-700 bg-red-100',
        };
        $keterangan = "Kamar aktif ber-Location: {$ringkasan['persen']}% ({$ringkasan['berkode']} dari {$ringkasan['total']})\n"
            . "Masuk rawat " . \App\Support\CakupanLocationKamar::HARI_PEMAKAIAN . " hari: {$ringkasan['persenPakai']}% ({$ringkasan['pakaiBerkode']} dari {$ringkasan['pakaiTotal']})\n"
            . 'Klik untuk melihat yang belum ber-Location';
    @endphp

    <a href="{{ route('master.kamar', ['location' => 'belum']) }}" wire:navigate title="{{ $keterangan }}" aria-label="{{ $keterangan }}"
        class="hidden px-2 py-1 text-xs font-semibold rounded-full sm:inline-block whitespace-nowrap hover:opacity-80 {{ $warnaBadge }}">
        Kamar {{ round($angkaBadge) }}%
    </a>
@endif
