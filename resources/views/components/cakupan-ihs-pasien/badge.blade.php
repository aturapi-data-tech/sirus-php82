{{--
    Badge topbar cakupan IHS pasien berkunjung (role diatur di layouts/app-topbar).
    Klik = Master Pasien tersaring "berkunjung belum IHS", urut dari yang paling sering dipakai.
    Tidak tampil bila angkanya gagal dihitung (App\Support\CakupanIhsPasien::ringkasan()).
--}}
@php
    $ringkasan = \App\Support\CakupanIhsPasien::ringkasan();
@endphp

@if ($ringkasan)
    @php
        $angkaBadge = $ringkasan['persen'];
        $warnaBadge = match (true) {
            $angkaBadge >= 90 => 'text-green-700 bg-green-100',
            $angkaBadge >= 60 => 'text-amber-700 bg-amber-100',
            default => 'text-red-700 bg-red-100',
        };
        $keterangan = "Pasien berkunjung 90 hari ber-IHS: {$ringkasan['persen']}% ({$ringkasan['berkode']} dari {$ringkasan['total']})\n"
            . "Kunjungan " . \App\Support\CakupanIhsPasien::HARI_PEMAKAIAN . " hari: {$ringkasan['persenPakai']}% ({$ringkasan['pakaiBerkode']} dari {$ringkasan['pakaiTotal']})\n"
            . 'Klik untuk melihat yang belum ber-IHS';
    @endphp

    <a href="{{ route('master.pasien', ['ihs' => 'belum']) }}" wire:navigate title="{{ $keterangan }}" aria-label="{{ $keterangan }}"
        class="hidden px-2 py-1 text-xs font-semibold rounded-full sm:inline-block whitespace-nowrap hover:opacity-80 {{ $warnaBadge }}">
        Pasien {{ round($angkaBadge) }}%
    </a>
@endif
