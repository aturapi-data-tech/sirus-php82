{{-- resources/views/components/pdf/layout-kwitansi.blade.php --}}
@props([
    'title' => null,
    'data' => [],
    // Kode formulir RM (mis. "RM-02.01 · Rev.0") — lihat /panduan-dev/koding-formulir-rm.
    'kode' => null,
    // true = kop selebar kertas + judul di bawahnya (untuk kertas A6: judul di samping kop menjepit alamat).
    'judulDiBawah' => false,
    // true = isi dicetak di kotak seperempat A4 (105 × 148.5 mm) di TENGAH ATAS kertas A4 —
    // untuk kertas kecil (A4 bagi 4) yang diumpankan di tengah printer; pemanggil tetap setPaper('A4').
    // Dibuat lewat margin @page (dompdf mengabaikan width/height @page tetapi menghormati margin):
    // atas 0, (210 − 105) / 2 = 52.5 mm kiri-kanan, 297 − 148.5 = 148.5 mm bawah. Isi yang lebih
    // panjang dari kotak berlanjut ke kotak yang sama di halaman berikutnya.
    // false = kertas sudah diatur sendiri oleh pemanggil (mis. setPaper('a6')) — tanpa margin.
    'tengahA4' => true,
])
@php
    $manifestPath = public_path('build/manifest.json');
    $pdfCss = null;
    if (file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $pdfCss = $manifest['resources/css/app.css']['file'] ?? null;
    }
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Kwitansi' }}</title>
    <style>
        @page {
            margin: {{ $tengahA4 ? '0 52.5mm 148.5mm' : '0' }};
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-size: 9px;
            font-family: sans-serif;
        }

        .kwitansi-wrapper {
            padding: {{ $tengahA4 ? '4mm 6mm' : '6mm 8mm' }};
        }

        /* ── KODE FORMULIR RM (pojok kiri bawah) ── */
        .kode-formulir {
            position: fixed;
            bottom: 2mm;
            left: 8mm;
            font-size: 6px;
            color: #6b7280;
        }

        {!! $pdfCss ? file_get_contents(public_path('build/' . $pdfCss)) : '' !!}
    </style>
</head>

<body>
    {{-- KODE FORMULIR RM — pojok kiri bawah SETIAP halaman (fixed, di margin bawah halaman
         sehingga tak menimpa isi). Dicetak apa adanya, mis. "RM-02.01 · Rev.0". --}}
    @if (filled($kode))
        <div class="kode-formulir">{{ $kode }}</div>
    @endif
    <div class="kwitansi-wrapper">

        @if ($judulDiBawah || $tengahA4)
            {{-- KOP RINGKAS (logo & huruf kecil — kertas A6), garis, lalu judul di bawahnya.
                 Sengaja tidak memakai x-logo.identitas-horisontal: ukurannya baku untuk A4 dan
                 komponen itu dipakai banyak cetakan lain. --}}
            <table class="w-full border-collapse">
                <tr>
                    <td class="w-10 pr-2 align-middle">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/Logo Persegi.png'))) }}"
                            alt="Logo Rumah Sakit Islam Madinah" class="block w-auto h-10">
                    </td>
                    <td class="text-[6.5px] leading-tight text-gray-600 align-middle">
                        <div class="text-[9px] font-bold text-gray-900">Rumah Sakit Islam Madinah</div>
                        <div>Jl. Jati Wayang, Lk. 2, Ngunut, Kec. Ngunut, Kabupaten Tulungagung, Jawa Timur 66292</div>
                        <div>Telp. (0355) 396055&ensp;Fax. (0355) 396824&ensp;www.rsimadinah.com</div>
                    </td>
                </tr>
            </table>
            <div class="mt-0.5 border-t border-gray-400"></div>
            <div class="mt-1 text-center text-[9px] font-bold uppercase tracking-wide text-gray-900">
                {{ $title ?? 'Kwitansi Pembayaran' }}
            </div>
        @else
            {{-- KOP: Identitas + Judul sejajar --}}
            <table class="w-full border-collapse">
                <tr>
                    <td class="align-middle">
                        <x-logo.identitas-horisontal :showGaris="false" />
                    </td>
                    <td
                        class="align-middle text-right text-[14px] font-bold uppercase tracking-wide w-auto whitespace-nowrap text-gray-900">
                        {{ $title ?? 'Kwitansi Pembayaran' }}
                    </td>
                </tr>
            </table>

            {{-- Garis --}}
            <div class="mt-0.5 border-t border-gray-400"></div>
        @endif

        {{-- Konten utama (dari blade view) --}}
        {{ $slot }}

    </div>
</body>

</html>
