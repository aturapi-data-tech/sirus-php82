<x-downtime.halaman kode="RI-EMR-02" judul="Asesmen Awal Medis Rawat Inap (DPJP)"
    subjudul="Pengganti Pengkajian Dokter & tab Diagnosa pada EMR Rawat Inap" unit="DPJP / dokter jaga ruangan"
    entriUlang="Daftar Rawat Inap > EMR > Pengkajian Dokter & tab Diagnosa" identitas="ringkas" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Anamnesis</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:22%;">Keluhan utama &amp; riwayat penyakit sekarang</td>
            <td class="dt-isi-2">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Riwayat dahulu / keluarga / alergi</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Indikasi rawat inap</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">B. Pemeriksaan Fisik</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:24%;">Regio / Sistem</th>
            <th style="width:12%;">Normal</th>
            <th>Kelainan / Deskripsi</th>
        </tr>
        @foreach (['Keadaan umum & kesadaran', 'Kepala, wajah, mata & THT', 'Leher', 'Thorax — jantung & paru', 'Abdomen', 'Ekstremitas & kulit'] as $regio)
            <tr>
                <td>{{ $regio }}</td>
                <td class="dt-tengah dt-isi"><span class="dt-box"></span></td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
        <tr>
            <td class="dt-tbl-label">Status lokalis / hasil penunjang</td>
            <td class="dt-isi" colspan="2">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">C. Diagnosa</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:5%;">No</th>
            <th style="width:16%;">Jenis</th>
            <th style="width:14%;">Kode ICD-10</th>
            <th>Diagnosa</th>
        </tr>
        @foreach ([0, 1, 2] as $barisDiagnosa)
            <tr>
                <td class="dt-tengah">{{ $barisDiagnosa + 1 }}</td>
                <td class="dt-isi">
                    <span class="dt-opsi"><span class="dt-box"></span>Kerja</span>
                    <span class="dt-opsi"><span class="dt-box"></span>Banding</span>
                </td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
    </table>

    <div class="dt-sec">D. Rencana Pelayanan</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:22%;">Rencana pemeriksaan penunjang</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Rencana terapi / instruksi</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Rencana tindakan / operasi</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Diet</td>
            <td class="dt-isi" style="width:28%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:22%;">Konsul / rawat bersama</td>
            <td class="dt-isi" style="width:28%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Perkiraan lama rawat</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Rencana pulang / kriteria pulang</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        Instruksi harian selanjutnya ditulis pada CPPT (RI-EMR-03); resep obat pada APT-01.
    </div>

    <x-downtime.ttd tempat="Tulungagung" :kolom="['Perawat Pendamping' => 'Paraf', 'DPJP' => 'Nama & SIP']" />

</x-downtime.halaman>
