<x-downtime.halaman kode="RI-ADM-01" judul="Pendaftaran & Penjaminan Rawat Inap"
    subjudul="Pengganti layar Daftar Rawat Inap saat SIMRS tidak dapat diakses" unit="Pendaftaran / TU"
    entriUlang="Rawat Inap > Daftar Rawat Inap (pasien baru: Master Pasien lebih dulu)" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Data Masuk Rawat Inap</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Tanggal / jam masuk</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">No. urut manual</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Cara masuk</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Dari UGD</span>
                <span class="dt-opsi"><span class="dt-box"></span>Dari poli / rawat jalan</span>
                <span class="dt-opsi"><span class="dt-box"></span>Rujukan faskes lain</span>
                <span class="dt-opsi"><span class="dt-box"></span>Datang sendiri</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Bangsal / kamar / bed</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Kelas rawat</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>VIP</span>
                <span class="dt-opsi"><span class="dt-box"></span>I</span>
                <span class="dt-opsi"><span class="dt-box"></span>II</span>
                <span class="dt-opsi"><span class="dt-box"></span>III</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">DPJP</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Diagnosa masuk</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">B. Identitas Pasien</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">No. RM</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Nama lengkap</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Tempat / tanggal lahir</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Jenis kelamin</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Laki-laki</span>
                <span class="dt-opsi"><span class="dt-box"></span>Perempuan</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">NIK / no. telepon</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Alamat</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Penanggung jawab, hubungan &amp; telepon</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">C. Pembiayaan & Penjaminan</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Cara bayar</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Umum</span>
                <span class="dt-opsi"><span class="dt-box"></span>BPJS</span>
                <span class="dt-opsi"><span class="dt-box"></span>Asuransi / perusahaan</span>
                <span class="dt-opsi"><span class="dt-box"></span>Lainnya</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">No. kartu BPJS</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">No. SEP rawat inap</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Kelas rawat hak</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Naik kelas (atas permintaan)</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Tidak</span>
                <span class="dt-opsi"><span class="dt-box"></span>Ya, ke kelas ..........</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">No. SPRI / surat perintah rawat</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Uang muka / deposit (Rp)</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Kasus laka lantas / medikolegal</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Tidak</span>
                <span class="dt-opsi"><span class="dt-box"></span>Ya &mdash; tanggal &amp; lokasi kejadian
                    ............................................</span>
            </td>
        </tr>
    </table>
    <div class="dt-note">
        Naik kelas atas permintaan sendiri berdampak pada selisih biaya &mdash; wajib disertai persetujuan tertulis
        penanggung jawab dan dicatat agar tarifnya benar saat entri ulang.
    </div>

    <x-downtime.ttd :kolom="['Penanggung Jawab Pasien' => 'Nama terang', 'Petugas Pendaftaran' => 'Nama & paraf']" />

</x-downtime.halaman>
