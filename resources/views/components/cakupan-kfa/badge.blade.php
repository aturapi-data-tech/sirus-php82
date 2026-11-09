{{--
    Badge topbar cakupan KFA obat (role diatur di layouts/app-topbar).
    Klik = Master Obat tersaring "aktif & belum KFA", urut dari yang paling sering dipakai.
    Tidak tampil bila angkanya gagal dihitung (App\Support\CakupanKfa::ringkasan()).
--}}
@php
    $ringkasan = \App\Support\CakupanKfa::ringkasan();
@endphp

@if ($ringkasan)
    @php
        $warnaBadge = match (true) {
            $ringkasan['persen'] >= 90 => 'text-green-700 bg-green-100',
            $ringkasan['persen'] >= 60 => 'text-amber-700 bg-amber-100',
            default => 'text-red-700 bg-red-100',
        };
        $keterangan = "Obat aktif ber-KFA: {$ringkasan['persen']}% ({$ringkasan['berkode']} dari {$ringkasan['total']})\n"
            . "Resep " . \App\Support\CakupanKfa::HARI_PEMAKAIAN . " hari: {$ringkasan['persenPakai']}% ({$ringkasan['pakaiBerkode']} dari {$ringkasan['pakaiTotal']} baris)\n"
            . 'Klik untuk melihat yang belum ber-KFA';
    @endphp

    <a href="{{ route('master.obat', ['kfa' => 'belum']) }}" wire:navigate title="{{ $keterangan }}" aria-label="{{ $keterangan }}"
        class="hidden px-2 py-1 text-xs font-semibold rounded-full sm:inline-block whitespace-nowrap hover:opacity-80 {{ $warnaBadge }}">
        KFA {{ round($ringkasan['persen']) }}%
    </a>
@endif
