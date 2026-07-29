<x-downtime.halaman kode="UGD-EMR-05" judul="Transfer / Rujukan Pasien UGD"
    subjudul="Serah terima pasien ke rawat inap, ruang tindakan, atau rujukan ke RS lain" unit="Perawat & dokter UGD"
    entriUlang="Transfer ke RI: Daftar Rawat Inap (cara masuk UGD); rujuk keluar: EMR UGD > Rujukan Antar RS"
    identitas="ringkas" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Data Transfer</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Tanggal / jam transfer</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Tujuan</td>
            <td class="dt-isi" style="width:30%;">
                <span class="dt-opsi"><span class="dt-box"></span>Rawat inap</span>
                <span class="dt-opsi"><span class="dt-box"></span>Kamar operasi</span>
                <span class="dt-opsi"><span class="dt-box"></span>VK / bersalin</span>
                <span class="dt-opsi"><span class="dt-box"></span>Rujuk RS lain</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Ruangan / kelas / RS tujuan</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">DPJP yang menerima</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Diagnosa saat transfer</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Alasan transfer / rujukan</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">B. Kondisi Pasien Saat Transfer</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:12%;">Jam</th>
            <th>TD</th>
            <th>Nadi</th>
            <th>RR</th>
            <th>Suhu</th>
            <th>SpO2</th>
            <th>GCS</th>
            <th style="width:26%;">Kesadaran / keluhan</th>
        </tr>
        <tr>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">C. Yang Diserahterimakan</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Alat terpasang</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Infus</span>
                <span class="dt-opsi"><span class="dt-box"></span>Kateter</span>
                <span class="dt-opsi"><span class="dt-box"></span>NGT</span>
                <span class="dt-opsi"><span class="dt-box"></span>Oksigen</span>
                <span class="dt-opsi"><span class="dt-box"></span>Drain</span>
                <span class="dt-opsi"><span class="dt-box"></span>Bidai / spalk</span>
                <span class="dt-opsi"><span class="dt-box"></span>Lainnya</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Obat & cairan dibawa</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Terapi terakhir (jam)</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Dokumen diserahkan</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Formulir UGD-EMR-01 s.d. 04</span>
                <span class="dt-opsi"><span class="dt-box"></span>Hasil lab</span>
                <span class="dt-opsi"><span class="dt-box"></span>Hasil radiologi</span>
                <span class="dt-opsi"><span class="dt-box"></span>Surat rujukan</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Petugas pendamping / transportasi</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Jam tiba di tujuan</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Catatan penerima</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        Bagian penerima diisi dan ditandatangani setelah pasien benar-benar diterima di ruangan / RS tujuan
        &mdash; jangan ditandatangani lebih dulu.
    </div>

    <x-downtime.ttd tempat="Tulungagung"
        :kolom="['Perawat Pengirim (UGD)' => 'Nama & paraf', 'Perawat Penerima' => 'Nama & paraf', 'Dokter UGD' => 'Nama & SIP']" />

</x-downtime.halaman>
