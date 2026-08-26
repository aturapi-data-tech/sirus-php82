{{-- resources/views/pages/components/manajemen/cetak-slip-gaji/isi-slip.blade.php --}}
{{-- Badan slip gaji — dipakai bersama oleh cetak satuan (cetak-slip-gaji-print)
     dan cetak massal (cetak-slip-gaji-massal-print). Dipisah supaya keduanya
     tidak pernah berbeda isi; kop & kerangka halaman diurus pemanggilnya.

     Butuh: $header (baris RSTXN_GAJIDOCTORHDRS + dr_name), $detail (koleksi
     RSTXN_GAJIDOCTORDTLS), $tanggalCetak (teks kota + tanggal). --}}

@php
    use App\Support\GajiDokter\GajiDokter;
    use App\Support\Terbilang;

    $rupiah = fn($nilai) => number_format((float) $nilai, 0, ',', '.');

    $bulanNama = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    ];

    $periodeJasa = ($bulanNama[$header->bulan_jasa] ?? $header->bulan_jasa) . ' ' . $header->tahun_jasa;
    $periodeGaji = ($bulanNama[$header->bulan_gaji] ?? $header->bulan_gaji) . ' ' . $header->tahun_gaji;

    $jasa = $detail->where('jenis', 'J')->filter(fn($baris) => !str_starts_with($baris->kode, 'TUNJ '));
    $tunjangan = $detail->where('jenis', 'J')->filter(fn($baris) => str_starts_with($baris->kode, 'TUNJ '));
    $potongan = $detail->where('jenis', 'P');
    $tambahan = $detail->where('jenis', 'T');

    // Skema garanty fee: yang dibayar hanya yang TERBESAR antara jasa dan gaji
    // pokok. Kalau jasanya menang, gaji pokok tidak ikut menambah — dicetak
    // sebagai selisih supaya penjumlahan kolom tetap menghasilkan Total Gaji.
    $nilaiPokokCetak = $header->skema_gaji_pokok === 'G'
        ? max(0, (float) $header->total_gaji - (float) $header->jasa_total)
        : (float) $header->nilai_gaji_pokok;
@endphp

<table class="w-full mt-3 text-base">
    <tr>
        <td class="w-28 text-gray-600">Nama Dokter</td>
        <td class="w-2 text-gray-600">:</td>
        <td class="font-bold text-gray-900">{{ $header->dr_name }}</td>
    </tr>
    <tr>
        <td class="text-gray-600">Periode Jasa</td>
        <td class="text-gray-600">:</td>
        <td class="text-gray-900">{{ $periodeJasa }}</td>
    </tr>
    <tr>
        <td class="text-gray-600">Dibayarkan</td>
        <td class="text-gray-600">:</td>
        <td class="text-gray-900">{{ $periodeGaji }}</td>
    </tr>
</table>

<table class="w-full mt-3 border-collapse text-base">
    <thead>
        <tr class="border-b border-gray-800">
            <th class="py-1.5 text-left text-sm uppercase tracking-wide text-gray-600">Uraian</th>
            <th class="w-32 py-1.5 text-right text-sm uppercase tracking-wide text-gray-600">Jumlah (Rp)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="2" class="pt-1.5 pb-0.5 font-bold text-gray-900 border-b border-gray-300">A. Jasa</td>
        </tr>
        @forelse ($jasa as $baris)
            <tr>
                <td class="py-1 text-gray-800">
                    {{ GajiDokter::labelKomponen($baris->kode, $baris->keterangan) }}
                    @if ((int) $baris->jumlah_pasien > 0)
                        <span class="text-sm text-gray-500">({{ $baris->jumlah_pasien }} pasien)</span>
                    @endif
                </td>
                <td class="py-1 text-right whitespace-nowrap text-gray-900">{{ $rupiah($baris->nilai) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" class="py-1 text-sm text-gray-500">&mdash; tidak ada &mdash;</td>
            </tr>
        @endforelse

        @foreach ($tunjangan as $baris)
            <tr>
                <td class="py-1 text-gray-800">{{ GajiDokter::labelKomponen($baris->kode, $baris->keterangan) }}</td>
                <td class="py-1 text-right whitespace-nowrap text-gray-900">{{ $rupiah($baris->nilai) }}</td>
            </tr>
        @endforeach

        @if ((float) $header->nilai_gaji_pokok > 0)
            <tr>
                <td class="py-1 text-gray-800">
                    Gaji Pokok
                    @if ($header->skema_gaji_pokok === 'G')
                        <span class="text-sm text-gray-500">(garanty fee)</span>
                    @endif
                </td>
                <td class="py-1 text-right whitespace-nowrap text-gray-900">{{ $rupiah($nilaiPokokCetak) }}</td>
            </tr>
        @endif

        <tr class="border-t border-gray-800">
            <td class="py-1 font-bold text-gray-900">TOTAL GAJI</td>
            <td class="py-1 text-right font-bold whitespace-nowrap text-gray-900">
                {{ $rupiah($header->total_gaji) }}
            </td>
        </tr>

        <tr>
            <td colspan="2" class="pt-1.5 pb-0.5 font-bold text-gray-900 border-b border-gray-300">B. Potongan</td>
        </tr>
        @forelse ($potongan as $baris)
            <tr>
                <td class="py-1 text-gray-800">{{ GajiDokter::labelKomponen($baris->kode, $baris->keterangan) }}</td>
                <td class="py-1 text-right whitespace-nowrap text-gray-900">{{ $rupiah($baris->nilai) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" class="py-1 text-sm text-gray-500">&mdash; tidak ada &mdash;</td>
            </tr>
        @endforelse

        @if ($tambahan->isNotEmpty())
            <tr>
                <td colspan="2" class="pt-1.5 pb-0.5 font-bold text-gray-900 border-b border-gray-300">C. Tambahan</td>
            </tr>
            @foreach ($tambahan as $baris)
                <tr>
                    <td class="py-1 text-gray-800">{{ GajiDokter::labelKomponen($baris->kode, $baris->keterangan) }}</td>
                    <td class="py-1 text-right whitespace-nowrap text-gray-900">{{ $rupiah($baris->nilai) }}</td>
                </tr>
            @endforeach
        @endif

        <tr class="border-t-2 border-b-2 border-gray-900">
            <td class="py-2 text-lg font-bold text-gray-900">GAJI DITERIMA</td>
            <td class="py-2 text-right text-lg font-bold whitespace-nowrap text-gray-900">
                {{ $rupiah($header->gaji_diterima) }}
            </td>
        </tr>
    </tbody>
</table>

{{-- Terbilang::rupiah() menerima int — pecahan rupiah dibulatkan lebih dulu. --}}
<div class="mt-2 text-sm italic text-gray-600">
    Terbilang: {{ Terbilang::rupiah((int) round((float) $header->gaji_diterima)) }}
</div>

{{-- Ruang tanda tangan: tinggi tetap lewat style + &nbsp;, JANGAN <br>
     berulang — dompdf kerap mengabaikannya sehingga tanda tangan menumpuk. --}}
<table class="w-full mt-4 text-sm text-center text-gray-700">
    <tr>
        <td class="w-1/2 align-top">
            Mengetahui,<br>Bagian Keuangan
            <div style="height: 34px;">&nbsp;</div>
            <div class="pt-0.5 border-t border-gray-800">(&emsp;&emsp;&emsp;&emsp;)</div>
        </td>
        <td class="w-1/2 align-top">
            {{ $tanggalCetak }}<br>Penerima
            <div style="height: 34px;">&nbsp;</div>
            <div class="pt-0.5 border-t border-gray-800 font-medium text-gray-900">{{ $header->dr_name }}</div>
        </td>
    </tr>
</table>
