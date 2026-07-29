@php
    // Kelompok biaya = tab pada layar Administrasi RI.
    $kelompokBiaya = [
        ['nama' => 'Akomodasi kamar (per hari x kelas)', 'baris' => 2],
        ['nama' => 'Visite dokter & konsul', 'baris' => 3],
        ['nama' => 'Jasa medis / tindakan & jasa karyawan', 'baris' => 3],
        ['nama' => 'Laboratorium & radiologi', 'baris' => 3],
        ['nama' => 'Kamar operasi / VK', 'baris' => 1],
        ['nama' => 'Obat, cairan & alkes', 'baris' => 4],
        ['nama' => 'Administrasi & lain-lain', 'baris' => 2],
    ];
@endphp

<x-downtime.halaman kode="RI-ADM-02" judul="Rincian Biaya Pelayanan Rawat Inap"
    subjudul="Pengganti layar Administrasi RI sebelum pasien ke kasir" unit="Administrasi rawat inap"
    entriUlang="Rawat Inap > Daftar Rawat Inap > Administrasi RI (per tab biaya)" :break="$dtBreak ?? false">

    <x-downtime.identitas variant="lengkap" />

    <div class="dt-sec">Rincian Biaya</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:5%;">No</th>
            <th>Uraian Pelayanan / Item</th>
            <th style="width:8%;">Qty / Hari</th>
            <th style="width:16%;">Tarif (Rp)</th>
            <th style="width:18%;">Jumlah (Rp)</th>
        </tr>
        @foreach ($kelompokBiaya as $kelompok)
            <tr>
                <td class="dt-tebal" colspan="5">{{ $kelompok['nama'] }}</td>
            </tr>
            @for ($baris = 1; $baris <= $kelompok['baris']; $baris++)
                <tr>
                    <td class="dt-tengah">{{ $baris }}</td>
                    <td class="dt-isi">&nbsp;</td>
                    <td class="dt-isi">&nbsp;</td>
                    <td class="dt-isi">&nbsp;</td>
                    <td class="dt-isi">&nbsp;</td>
                </tr>
            @endfor
        @endforeach
        <tr>
            <td class="dt-tebal dt-kanan" colspan="4">TOTAL TAGIHAN</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">Penjaminan & Penyelesaian</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Uang muka / deposit (Rp)</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Ditanggung penjamin (Rp)</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Selisih kelas / naik kelas (Rp)</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Sisa dibayar pasien (Rp)</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Biaya UGD / RJ yang dibawa ke tagihan RI</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">No. kwitansi manual (KSR-01)</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        Biaya UGD atau rawat jalan yang mendahului rawat inap ikut ditarik ke tagihan RI &mdash; jangan ditagihkan
        dua kali saat entri ulang. Tarif kamar mengikuti kelas yang benar-benar ditempati per hari.
    </div>

    <x-downtime.ttd tempat="Tulungagung" :kolom="['Petugas Administrasi' => 'Penyusun rincian', 'Penanggung Jawab Pasien' => 'Menyetujui rincian']" />

</x-downtime.halaman>
