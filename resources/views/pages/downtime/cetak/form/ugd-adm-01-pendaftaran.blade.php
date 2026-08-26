<x-downtime.halaman kode="UGD-ADM-01" judul="Pendaftaran Pasien UGD"
    subjudul="Pengganti layar Daftar UGD saat SIMRS tidak dapat diakses" unit="Pendaftaran / TU"
    entriUlang="UGD > Daftar UGD (pasien baru: Master Pasien lebih dulu)" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Data Kunjungan</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">No. urut manual</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Tanggal / jam datang</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Status pasien</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Pasien lama</span>
                <span class="dt-opsi"><span class="dt-box"></span>Pasien baru</span>
            </td>
            <td class="dt-tbl-label">Shift</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>1</span>
                <span class="dt-opsi"><span class="dt-box"></span>2</span>
                <span class="dt-opsi"><span class="dt-box"></span>3</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Cara datang</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Datang sendiri</span>
                <span class="dt-opsi"><span class="dt-box"></span>Diantar keluarga</span>
                <span class="dt-opsi"><span class="dt-box"></span>Ambulans</span>
                <span class="dt-opsi"><span class="dt-box"></span>Rujukan faskes</span>
                <span class="dt-opsi"><span class="dt-box"></span>Polisi</span>
            </td>
            <td class="dt-tbl-label">Dokter jaga UGD</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Pengantar (nama &amp; telepon)</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Petugas pendaftaran</td>
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
            <td class="dt-tbl-label">NIK</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">No. telepon</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Alamat</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Penanggung jawab &amp; hubungan</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        Pasien tidak dikenal dicatat sebagai Mr./Mrs. X dengan ciri-ciri fisik; identitas dilengkapi menyusul
        sebelum entri ulang ke SIMRS.
    </div>

    <div class="dt-sec">C. Pembiayaan & Penjaminan</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Cara bayar</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Umum</span>
                <span class="dt-opsi"><span class="dt-box"></span>BPJS</span>
                <span class="dt-opsi"><span class="dt-box"></span>Asuransi / perusahaan</span>
                <span class="dt-opsi"><span class="dt-box"></span>Jasa Raharja</span>
                <span class="dt-opsi"><span class="dt-box"></span>Lainnya</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">No. kartu BPJS</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">No. SEP gawat darurat</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Kasus laka lantas / medikolegal</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Tidak</span>
                <span class="dt-opsi"><span class="dt-box"></span>Ya</span>
            </td>
            <td class="dt-tbl-label">Tgl &amp; lokasi kejadian</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">No. LP / instansi pelapor</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Penjamin kecelakaan</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        SEP gawat darurat tidak boleh dikarang. Bila V-Claim ikut mati, kolom SEP dikosongkan dan diterbitkan
        menyusul saat sistem pulih sesuai ketentuan BPJS (maksimal 3x24 jam hari kerja).
    </div>

    <x-downtime.ttd :kolom="['Keluarga / Pengantar' => 'Nama terang', 'Petugas Pendaftaran' => 'Nama & paraf']" />

</x-downtime.halaman>
