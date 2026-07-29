@php
    // Kelompok biaya = tab pada layar Administrasi UGD, supaya entri ulang tinggal
    // memindahkan baris per tab tanpa menafsirkan ulang.
    $kelompokBiaya = [
        ['nama' => 'Administrasi & jasa UGD', 'baris' => 2],
        ['nama' => 'Jasa medis / tindakan', 'baris' => 3],
        ['nama' => 'Jasa dokter', 'baris' => 2],
        ['nama' => 'Jasa karyawan', 'baris' => 2],
        ['nama' => 'Laboratorium', 'baris' => 2],
        ['nama' => 'Radiologi', 'baris' => 2],
        ['nama' => 'Obat, cairan & alkes', 'baris' => 4],
        ['nama' => 'Lain-lain', 'baris' => 1],
    ];
@endphp

<x-downtime.halaman kode="UGD-ADM-02" judul="Rincian Biaya Pelayanan UGD"
    subjudul="Pengganti layar Administrasi UGD sebelum pasien ke kasir" unit="Administrasi UGD"
    entriUlang="UGD > Daftar UGD > Administrasi UGD (per tab biaya)" :break="$dtBreak ?? false">

    <x-downtime.identitas variant="lengkap" />

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
            <td class="dt-tbl-label">Disposisi pasien</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Pulang</span>
                <span class="dt-opsi"><span class="dt-box"></span>Rawat inap</span>
                <span class="dt-opsi"><span class="dt-box"></span>Rujuk</span>
            </td>
            <td class="dt-tbl-label">No. kwitansi manual (KSR-01)</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        Bila pasien dilanjutkan rawat inap, biaya UGD ikut dibawa ke tagihan rawat inap &mdash; jangan ditagihkan
        dua kali saat entri ulang. Bila tarif berbeda, yang dipakai tarif Master Jasa Medis / Jasa Dokter.
    </div>

    <x-downtime.ttd tempat="Tulungagung" :kolom="['Petugas Administrasi' => 'Penyusun rincian', 'Pasien / Keluarga' => 'Menyetujui rincian']" />

</x-downtime.halaman>
