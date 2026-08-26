<x-downtime.halaman kode="UGD-EMR-03" judul="Lembar Observasi & Pemberian Obat / Cairan UGD"
    subjudul="Pengganti tab Observasi dan Obat & Cairan pada EMR UGD" unit="Perawat UGD"
    entriUlang="Pelayanan UGD > EMR > tab Observasi dan Obat & Cairan" identitas="ringkas" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Observasi Tanda Vital</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:9%;">Jam</th>
            <th style="width:11%;">TD (mmHg)</th>
            <th style="width:9%;">Nadi</th>
            <th style="width:9%;">RR</th>
            <th style="width:9%;">Suhu</th>
            <th style="width:9%;">SpO2</th>
            <th style="width:9%;">GCS</th>
            <th style="width:9%;">GDA</th>
            <th>Keadaan Umum / Keluhan</th>
            <th style="width:8%;">Paraf</th>
        </tr>
        @for ($baris = 1; $baris <= 10; $baris++)
            <tr>
                <td class="dt-isi">&nbsp;</td>
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

    <div class="dt-sec">B. Cairan Infus</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:9%;">Jam</th>
            <th>Jenis Cairan</th>
            <th style="width:13%;">Volume</th>
            <th style="width:13%;">Tetesan / mnt</th>
            <th style="width:16%;">Keterangan</th>
            <th style="width:8%;">Paraf</th>
        </tr>
        @for ($baris = 1; $baris <= 4; $baris++)
            <tr>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endfor
    </table>

    <div class="dt-sec">C. Pemberian Obat</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:9%;">Jam</th>
            <th>Nama Obat</th>
            <th style="width:13%;">Dosis</th>
            <th style="width:13%;">Jumlah</th>
            <th style="width:12%;">Rute</th>
            <th style="width:13%;">Keterangan</th>
            <th style="width:8%;">Paraf</th>
        </tr>
        @for ($baris = 1; $baris <= 7; $baris++)
            <tr>
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
    <div class="dt-note">
        Obat &amp; alkes yang dipakai di UGD tetap harus masuk rincian biaya (UGD-ADM-02) dan mengurangi stok saat
        dientri ulang &mdash; jangan sampai terlewat.
    </div>

    <x-downtime.ttd :kolom="['Perawat Pelaksana' => 'Nama & paraf', 'Dokter Jaga UGD' => 'Verifikasi']" />

</x-downtime.halaman>
