{{-- resources/views/components/logo/identitas.blade.php --}}
{{-- Pemakaian: <x-logo.identitas /> atau <x-logo.identitas :showGaris="false" /> --}}
@props([
    'namaRs' => 'Rumah Sakit Islam Madinah',
    'alamat' => 'Jl. Jati Wayang, Lk. 2, Ngunut, Kec. Ngunut,<br>Kabupaten Tulungagung, Jawa Timur 66292',
    'telp' => '(0355) 396055',
    'fax' => '(0355) 396824',
    'website' => 'www.rsimadinah.com',
    'showGaris' => true,
])

<table style="width:100%; border-collapse:collapse; margin-bottom:0;">
    <tr>
        {{-- Kiri: kosong (area ornamen bulan sabit) --}}
        <td style="width:45%; vertical-align:top;"></td>

        {{-- Kanan: logo persegi + identitas RS (right-align) --}}
        <td style="width:55%; text-align:right; vertical-align:top;">
            <img src="{{ public_path('images/Logo Persegi.png') }}" alt="Logo {{ $namaRs }}"
                style="height:80px; width:auto; display:inline-block; margin-bottom:6px;">
            <div style="font-size:9.5px; line-height:1.55; color:#333;">
                <div style="font-weight:bold; font-size:11px; color:#111;">{{ $namaRs }}</div>
                <div>{!! $alamat !!}</div>
                <div>Telp. {{ $telp }}&nbsp;&nbsp;Fax. {{ $fax }}</div>
                <div>{{ $website }}</div>
            </div>
        </td>
    </tr>

    @if ($showGaris)
        <tr>
            <td colspan="2" style="padding-top:10px;">
                <div style="border-top:2.5px solid #157547;"></div>
                <div style="border-top:1px solid #157547; margin-top:2px;"></div>
            </td>
        </tr>
    @endif
</table>
