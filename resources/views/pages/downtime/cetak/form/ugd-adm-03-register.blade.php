<x-downtime.halaman kode="UGD-ADM-03" judul="Register Harian Kunjungan UGD"
    subjudul="Buku bantu urutan kunjungan UGD selama waktu henti — 20 baris per lembar"
    unit="Pendaftaran / TU & perawat UGD" entriUlang="Dicocokkan dengan UGD > Daftar UGD pada tanggal yang sama"
    :break="$dtBreak ?? false">

    <table class="dt-tbl" style="margin-top:6px;">
        <tr>
            <td class="dt-tbl-label" style="width:14%;">Tanggal</td>
            <td class="dt-isi" style="width:19%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:14%;">Shift</td>
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
            <th style="width:19%;">Nama Pasien</th>
            <th style="width:7%;">Triase</th>
            <th style="width:14%;">Dokter Jaga</th>
            <th style="width:9%;">Cara Bayar</th>
            <th style="width:15%;">Disposisi</th>
            <th style="width:6%;">Entri v</th>
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
            </tr>
        @endfor
        <tr>
            <td class="dt-tebal dt-kanan" colspan="8">Jumlah kunjungan pada lembar ini</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        Kolom Disposisi diisi: pulang / rawat inap / rujuk / pulang paksa / meninggal. Rekapitulasi jumlah lembar
        dicatat pada formulir DT-03.
    </div>

    <x-downtime.ttd :kolom="['Petugas Pendaftaran' => 'Pencatat', 'Penanggung Jawab Shift UGD' => 'Verifikator']" />

</x-downtime.halaman>
