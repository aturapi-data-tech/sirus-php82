{{-- resources/views/pages/components/manajemen/cetak-slip-gaji/identitas-lampiran.blade.php --}}
{{-- Identitas dokter & periode untuk lampiran — ditaruh di KIRI kop, sejajar
     dengan logo identitas RS (slot patientData milik layout A4).

     Dipisah dari isi-lampiran karena posisinya di luar badan dokumen: cetak
     satuan mengisinya lewat slot komponen layout, cetak massal menyusun kop
     itu sendiri. Keduanya menyertakan partial yang sama supaya tidak berbeda.

     Karena hanya kebagian separuh lebar halaman, susunannya menurun (satu
     pasang label-nilai per baris), bukan dua pasang seperti versi lebar penuh.

     Ukuran teks dipasang lewat style sebaris — kelas arbitrary Tailwind
     (mis. text-[10px]) tidak ikut ter-build sehingga hilang di PDF.

     Butuh: $header (RSTXN_GAJIDOCTORHDRS + dr_name), $lampiran. --}}

@php
    $bulanNamaLampiran = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    ];

    $periodeJasa = ($bulanNamaLampiran[$header->bulan_jasa] ?? $header->bulan_jasa) . ' ' . $header->tahun_jasa;
    $periodeGaji = ($bulanNamaLampiran[$header->bulan_gaji] ?? $header->bulan_gaji) . ' ' . $header->tahun_gaji;
@endphp

<table class="border-collapse" style="font-size: 10px;">
    <tr>
        <td class="text-gray-600" style="width: 78px;">Nama Dokter</td>
        <td class="text-gray-600" style="width: 8px;">:</td>
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
    <tr>
        <td class="text-gray-600">Jumlah Baris</td>
        <td class="text-gray-600">:</td>
        <td class="text-gray-900">{{ count($lampiran) }} transaksi</td>
    </tr>
</table>
