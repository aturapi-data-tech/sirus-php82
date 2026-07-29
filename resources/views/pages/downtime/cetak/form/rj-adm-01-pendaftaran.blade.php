<x-downtime.halaman kode="RJ-ADM-01" judul="Pendaftaran Pasien Rawat Jalan"
    subjudul="Pengganti layar Daftar Rawat Jalan saat SIMRS tidak dapat diakses" unit="Pendaftaran / TU"
    entriUlang="Rawat Jalan > Daftar Rawat Jalan (pasien baru: Master Pasien lebih dulu)" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Data Kunjungan</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">No. urut manual</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Tanggal / jam daftar</td>
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
            <td class="dt-tbl-label">Jenis kunjungan</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Rawat jalan</span>
                <span class="dt-opsi"><span class="dt-box"></span>Kontrol</span>
                <span class="dt-opsi"><span class="dt-box"></span>Post inap</span>
                <span class="dt-opsi"><span class="dt-box"></span>Penunjang</span>
            </td>
            <td class="dt-tbl-label">Poli tujuan</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Dokter (DPJP)</td>
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
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Agama / pekerjaan</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Penanggung jawab &amp; hubungan</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        Untuk pasien baru, No. RM manual diambil dari buku nomor RM cadangan. Saat entri ulang, pasien didaftarkan
        lebih dulu di Master Pasien memakai nomor tersebut agar tidak terbit nomor ganda.
    </div>

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
            <td class="dt-tbl-label" style="width:20%;">No. SEP (bila sudah terbit)</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Jenis SEP</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Rujukan</span>
                <span class="dt-opsi"><span class="dt-box"></span>Kontrol</span>
            </td>
            <td class="dt-tbl-label">Kelas rawat hak</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">No. &amp; tanggal rujukan</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">PPK asal rujukan</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">No. surat kontrol / SKDP</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Laka lantas</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Tidak</span>
                <span class="dt-opsi"><span class="dt-box"></span>Ya</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Catatan</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        SEP tidak boleh dikarang. Bila aplikasi V-Claim ikut mati, kolom No. SEP dikosongkan dan SEP diterbitkan
        menyusul saat sistem pulih, sesuai ketentuan BPJS yang berlaku.
    </div>

    <x-downtime.ttd :kolom="['Pasien / Keluarga' => 'Nama terang', 'Petugas Pendaftaran' => 'Nama & paraf']" />

</x-downtime.halaman>
