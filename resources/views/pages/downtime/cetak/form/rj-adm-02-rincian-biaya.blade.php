@php
    // Kelompok biaya = tab pada layar Administrasi RJ, supaya entri ulang tinggal
    // memindahkan baris per tab tanpa menafsirkan ulang.
    $kelompokBiaya = [
        ['nama' => 'Administrasi & karcis', 'baris' => 2],
        ['nama' => 'Jasa medis / tindakan', 'baris' => 3],
        ['nama' => 'Jasa dokter (visit / konsul / tindakan)', 'baris' => 2],
        ['nama' => 'Jasa karyawan', 'baris' => 2],
        ['nama' => 'Laboratorium', 'baris' => 2],
        ['nama' => 'Radiologi', 'baris' => 2],
        ['nama' => 'Obat & alkes', 'baris' => 4],
        ['nama' => 'Lain-lain', 'baris' => 1],
    ];
@endphp

<x-downtime.halaman kode="RJ-ADM-02" judul="Rincian Biaya Pelayanan Rawat Jalan"
    subjudul="Pengganti layar Administrasi RJ sebelum pasien ke kasir" unit="Administrasi rawat jalan"
    entriUlang="Rawat Jalan > Daftar Rawat Jalan > Administrasi RJ (per tab biaya)" identitas="lengkap" :break="$dtBreak ?? false">

    <div class="dt-sec">Rincian Biaya</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:5%;">No</th>
            <th>Uraian Pelayanan / Item</th>
            <th style="width:8%;">Qty</th>
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
            <td class="dt-tbl-label" style="width:20%;">Ditanggung penjamin (Rp)</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Dibayar pasien (Rp)</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Status pengambilan obat</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Ditunggu</span>
                <span class="dt-opsi"><span class="dt-box"></span>Ditinggal</span>
            </td>
            <td class="dt-tbl-label">No. kwitansi manual (KSR-01)</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">Bila tarif berbeda saat entri ulang, yang dipakai tarif Master Jasa Medis / Jasa Dokter; selisih dicatat di DT-03.</div>

    <x-downtime.ttd tempat="Tulungagung" :kolom="['Petugas Administrasi' => 'Penyusun rincian', 'Pasien / Keluarga' => 'Menyetujui rincian']" />

</x-downtime.halaman>
