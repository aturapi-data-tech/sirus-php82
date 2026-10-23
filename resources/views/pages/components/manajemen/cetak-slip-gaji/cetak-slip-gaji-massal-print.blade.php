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
        /* Kotak seperempat A4 di tengah atas kertas — sama dengan tengahA4 milik x-pdf.layout-kwitansi. */
        @page {
            margin: 0 52.5mm 148.5mm;
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
        .slip { padding: 4mm 6mm; }
        .putus-halaman { page-break-after: always; }

        {!! $pdfCss ? file_get_contents(public_path('build/' . $pdfCss)) : '' !!}
    </style>
</head>

<body class="text-[9px] text-gray-900">
    @foreach ($slipList as $slip)
        @php
            $header = $slip['header'];
            $detail = $slip['detail'];
        @endphp

        <div class="slip {{ $loop->last ? '' : 'putus-halaman' }}">
            {{-- Kop ringkas & judul di bawahnya: susunan yang sama dengan x-pdf.layout-kwitansi --}}
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
                Slip Gaji Dokter
            </div>

            @include('pages.components.manajemen.cetak-slip-gaji.isi-slip')
        </div>
    @endforeach
</body>

</html>
