{{-- Blok tanda tangan formulir manual. Pola cetak repo: kolom rata tengah,
     ruang tanda tangan pakai tinggi tetap (bukan flex/br) lalu garis nama. --}}
@props([
    // ['Perawat Poli', 'Dokter Pemeriksa'] atau ['Kasir' => 'Nama & paraf', ...]
    'kolom' => [],
    // Baris "Tulungagung, ....." di atas kolom terakhir. Kosongkan untuk menyembunyikan.
    'tempat' => null,
])

@php
    $daftar = [];
    foreach ($kolom as $key => $val) {
        $daftar[] = is_int($key) ? ['label' => $val, 'ket' => null] : ['label' => $key, 'ket' => $val];
    }
    $jumlahKolom = count($daftar);
    // Dengan 1-2 kolom, garis nama jadi terlalu lebar bila dibagi rata — kolom
    // dipatok 30% lalu didorong ke kanan memakai sel kosong.
    $lebar = $jumlahKolom >= 3 ? round(100 / $jumlahKolom, 2) : 30;
    $lebarSpacer = max(0, 100 - $lebar * $jumlahKolom);
@endphp

<table class="dt-ttd">
    @if (filled($tempat))
        <tr>
            @if ($lebarSpacer > 0)
                <td style="width:{{ $lebarSpacer }}%;">&nbsp;</td>
            @endif
            @foreach ($daftar as $i => $item)
                <td style="width:{{ $lebar }}%;">
                    {{ $i === $jumlahKolom - 1 ? $tempat . ', ' . str_repeat('.', 20) : '' }}
                </td>
            @endforeach
        </tr>
    @endif
    <tr>
        @if ($lebarSpacer > 0)
            <td style="width:{{ $lebarSpacer }}%;">&nbsp;</td>
        @endif
        @foreach ($daftar as $item)
            <td style="width:{{ $lebar }}%;">{{ $item['label'] }}</td>
        @endforeach
    </tr>
    <tr>
        @if ($lebarSpacer > 0)
            <td>&nbsp;</td>
        @endif
        @foreach ($daftar as $item)
            <td class="dt-ttd-ruang">&nbsp;</td>
        @endforeach
    </tr>
    <tr>
        @if ($lebarSpacer > 0)
            <td>&nbsp;</td>
        @endif
        @foreach ($daftar as $item)
            <td>
                <div class="dt-ttd-nama">( nama terang )</div>
                @if (filled($item['ket']))
                    <div class="dt-kecil">{{ $item['ket'] }}</div>
                @endif
            </td>
        @endforeach
    </tr>
</table>
