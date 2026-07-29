<x-downtime.halaman kode="APT-03" judul="Register Pengeluaran Obat & Penyesuaian Stok Apotek"
    subjudul="Catatan manual pengeluaran obat selama waktu henti — 20 baris per lembar" unit="Apotek RJ / UGD / RI"
    entriUlang="E-resep dientri ulang > stok terpotong otomatis; selisih lewat Gudang > Kartu Stock Apotek"
    :break="$dtBreak ?? false">

    <table class="dt-tbl" style="margin-top:6px;">
        <tr>
            <td class="dt-tbl-label" style="width:14%;">Tanggal</td>
            <td class="dt-isi" style="width:19%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:14%;">Shift / petugas</td>
            <td class="dt-isi" style="width:19%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:14%;">Lembar ke</td>
            <td class="dt-isi" style="width:20%;">&nbsp;</td>
        </tr>
    </table>

    <table class="dt-tbl dt-tbl-kecil" style="margin-top:6px;">
        <tr>
            <th style="width:4%;">No</th>
            <th style="width:7%;">Jam</th>
            <th style="width:9%;">No. RM</th>
            <th style="width:6%;">Jalur</th>
            <th style="width:14%;">Nama Pasien</th>
            <th style="width:26%;">Nama Obat / Alkes</th>
            <th style="width:7%;">Jml</th>
            <th style="width:9%;">Satuan</th>
            <th style="width:9%;">Sisa Stok Fisik</th>
            <th style="width:5%;">Entri v</th>
        </tr>
        @for ($baris = 1; $baris <= 20; $baris++)
            <tr>
                <td class="dt-tengah">{{ $baris }}</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endfor
    </table>

    <div class="dt-sec">Penyesuaian Stok Setelah Sistem Pulih</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:26%;">Jumlah baris pengeluaran</td>
            <td class="dt-isi" style="width:24%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:26%;">Sudah dientri via e-resep</td>
            <td class="dt-isi" style="width:24%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Item yang masih selisih</td>
            <td class="dt-isi-3" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Tindakan penyesuaian</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        Stok terpotong otomatis saat e-resep dientri ulang &mdash; jangan melakukan penyesuaian stok manual sebelum
        seluruh e-resep dientri, agar stok tidak terpotong dua kali.
    </div>

    <x-downtime.ttd :kolom="['Petugas Apotek' => 'Pencatat', 'Apoteker Penanggung Jawab' => 'Verifikator']" />

</x-downtime.halaman>
