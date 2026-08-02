{{-- resources/views/pages/components/manajemen/cetak-slip-gaji/cetak-slip-gaji-massal-print.blade.php --}}
{{-- Template print: Slip Gaji Dokter — CETAK MASSAL satu periode. --}}
{{-- Satu berkas berisi banyak slip, tiap dokter satu halaman.

     Tidak memakai <x-pdf.layout-kwitansi> karena komponen itu memancarkan satu
     dokumen <html> utuh — dipanggil berulang akan menghasilkan dokumen bersarang.
     Kerangkanya disalin ke sini, sementara kop dan badan slipnya tetap memakai
     komponen & partial yang sama dengan cetak satuan, jadi hasilnya identik.

     Butuh: $slipList (koleksi objek {header, detail}), $tanggalCetak. --}}

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
    <title>Slip Gaji Dokter &mdash; {{ $judulPeriode }}</title>
    <style>
        @page {
            margin: 0;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: sans-serif;
        }

        /* Tiap slip satu halaman. page-break-after pada elemen TERAKHIR akan
           menyisakan halaman kosong di ujung, jadi pemutusnya dipasang lewat
           kelas terpisah yang tidak dipakai slip terakhir. */
        .slip { padding: 6mm 8mm; }
        .putus-halaman { page-break-after: always; }

        {!! $pdfCss ? file_get_contents(public_path('build/' . $pdfCss)) : '' !!}
    </style>
</head>

<body class="text-sm text-gray-900">
    @foreach ($slipList as $slip)
        @php
            $header = $slip['header'];
            $detail = $slip['detail'];
        @endphp

        <div class="slip {{ $loop->last ? '' : 'putus-halaman' }}">
            {{-- Kop & judul: susunan yang sama dengan x-pdf.layout-kwitansi --}}
            <table class="w-full border-collapse">
                <tr>
                    <td class="align-middle">
                        <x-logo.identitas-horisontal :showGaris="false" />
                    </td>
                    <td
                        class="align-middle text-right text-[14px] font-bold uppercase tracking-wide w-auto whitespace-nowrap text-gray-900">
                        Slip Gaji Dokter
                    </td>
                </tr>
            </table>

            <div class="mt-0.5 border-t border-gray-400"></div>

            @include('pages.components.manajemen.cetak-slip-gaji.isi-slip')
        </div>
    @endforeach
</body>

</html>
