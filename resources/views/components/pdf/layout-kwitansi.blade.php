{{-- resources/views/components/pdf/layout-kwitansi.blade.php --}}
@props([
    'title' => null,
    'data' => [],
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
            width: 105mm;
            height: 148.5mm;
            margin: 0;
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
            padding: 6mm 8mm;
        }

        {!! $pdfCss ? file_get_contents(public_path('build/' . $pdfCss)) : '' !!}
    </style>
</head>

<body>
    <div class="kwitansi-wrapper">

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

        {{-- Konten utama (dari blade view) --}}
        {{ $slot }}

    </div>
</body>

</html>
