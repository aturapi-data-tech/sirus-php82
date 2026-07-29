<x-downtime.halaman kode="RI-EMR-01" judul="Asesmen Awal Keperawatan Rawat Inap"
    subjudul="Pengganti Pengkajian Awal & tab Penilaian pada EMR Rawat Inap" unit="Perawat ruangan"
    entriUlang="Daftar Rawat Inap > EMR > Pengkajian Awal & tab Penilaian" identitas="ringkas" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Data Masuk Ruangan</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Tgl / jam masuk ruangan</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Ruangan / kamar / kelas</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Asal pasien</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>UGD</span>
                <span class="dt-opsi"><span class="dt-box"></span>Poli</span>
                <span class="dt-opsi"><span class="dt-box"></span>Rujukan</span>
                <span class="dt-opsi"><span class="dt-box"></span>Pindah ruangan</span>
            </td>
            <td class="dt-tbl-label">DPJP</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Keluhan utama saat masuk</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Riwayat penyakit / operasi / obat rutin</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Alergi</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Tidak</span>
                <span class="dt-opsi"><span class="dt-box"></span>Ya, sebutkan</span>
            </td>
            <td class="dt-tbl-label">Gelang identitas terpasang</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Ya</span>
                <span class="dt-opsi"><span class="dt-box"></span>Tidak</span>
            </td>
        </tr>
    </table>

    <div class="dt-sec">B. Tanda Vital & Antropometri</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th>Sistolik</th>
            <th>Diastolik</th>
            <th>Nadi</th>
            <th>Nafas</th>
            <th>Suhu (&deg;C)</th>
            <th>SpO2 (%)</th>
            <th>BB (kg)</th>
            <th>TB (cm)</th>
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

    <div class="dt-sec">C. Penilaian Risiko</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:24%;">Penilaian</th>
            <th style="width:12%;">Skor</th>
            <th style="width:30%;">Kategori</th>
            <th>Tindak lanjut / intervensi</th>
        </tr>
        <tr>
            <td>Nyeri (0&ndash;10)</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Ringan</span>
                <span class="dt-opsi"><span class="dt-box"></span>Sedang</span>
                <span class="dt-opsi"><span class="dt-box"></span>Berat</span>
            </td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td>Risiko jatuh (Morse / Humpty)</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Rendah</span>
                <span class="dt-opsi"><span class="dt-box"></span>Sedang</span>
                <span class="dt-opsi"><span class="dt-box"></span>Tinggi</span>
            </td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td>Skrining gizi</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Normal</span>
                <span class="dt-opsi"><span class="dt-box"></span>Berisiko malnutrisi</span>
            </td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td>Risiko dekubitus (Braden)</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Rendah</span>
                <span class="dt-opsi"><span class="dt-box"></span>Sedang</span>
                <span class="dt-opsi"><span class="dt-box"></span>Tinggi</span>
            </td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">Rincian butir skoring memakai formulir RJ-EMR-03 bila dibutuhkan perhitungan lengkap.</div>

    <div class="dt-sec">D. Orientasi & Rencana Awal</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:24%;">Orientasi ruangan diberikan</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Tata tertib</span>
                <span class="dt-opsi"><span class="dt-box"></span>Fasilitas kamar</span>
                <span class="dt-opsi"><span class="dt-box"></span>Hak &amp; kewajiban</span>
                <span class="dt-opsi"><span class="dt-box"></span>Jam kunjung</span>
                <span class="dt-opsi"><span class="dt-box"></span>Cuci tangan</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Masalah keperawatan utama</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
    </table>

    <x-downtime.ttd :kolom="['Pasien / Keluarga' => 'Nama terang', 'Perawat Pengkaji' => 'Nama & paraf']" />

</x-downtime.halaman>
