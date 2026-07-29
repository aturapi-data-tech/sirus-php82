<x-downtime.halaman kode="RJ-ADM-03" judul="Register Harian Kunjungan Rawat Jalan"
    subjudul="Buku bantu urutan kunjungan selama waktu henti — 20 baris per lembar" unit="Pendaftaran / TU"
    entriUlang="Dicocokkan dengan Rawat Jalan > Daftar Rawat Jalan pada tanggal yang sama" :break="$dtBreak ?? false">

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
            <th style="width:10%;">No. RM</th>
            <th style="width:22%;">Nama Pasien</th>
            <th style="width:13%;">Poli</th>
            <th style="width:15%;">Dokter</th>
            <th style="width:10%;">Cara Bayar</th>
            <th style="width:13%;">No. SEP / Rujukan</th>
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
        Kolom &quot;Entri v&quot; dicentang petugas yang memasukkan data ke SIMRS setelah sistem pulih.
        Rekapitulasi jumlah lembar &amp; jumlah kunjungan dicatat pada formulir DT-03.
    </div>

    <x-downtime.ttd :kolom="['Petugas Pendaftaran' => 'Pencatat', 'Supervisor / Ka. Unit' => 'Verifikator']" />

</x-downtime.halaman>
