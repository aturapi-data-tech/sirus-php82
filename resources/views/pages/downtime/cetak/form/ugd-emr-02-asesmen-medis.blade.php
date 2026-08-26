<x-downtime.halaman kode="UGD-EMR-02" judul="Asesmen Medis, Diagnosa & Tindakan UGD"
    subjudul="Pengganti tab Pemeriksaan (dokter), Diagnosa & Perencanaan pada EMR UGD" unit="Dokter jaga UGD"
    entriUlang="Pelayanan UGD > EMR > tab Pemeriksaan, Diagnosa, Perencanaan" identitas="ringkas" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Anamnesis</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Riwayat penyakit sekarang</td>
            <td class="dt-isi-2">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Riwayat dahulu / obat / alergi</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Mekanisme cedera (bila trauma)</td>
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
        @foreach (['Kepala, wajah, mata & THT', 'Leher', 'Thorax — jantung & paru', 'Abdomen', 'Ekstremitas', 'Kulit / genitalia'] as $regio)
            <tr>
                <td>{{ $regio }}</td>
                <td class="dt-tengah dt-isi"><span class="dt-box"></span></td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
        <tr>
            <td class="dt-tbl-label">Status lokalis / hasil penunjang</td>
            <td class="dt-isi-2" colspan="2">&nbsp;</td>
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

    <div class="dt-sec">D. Tindakan Kegawatdaruratan</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:5%;">No</th>
            <th style="width:14%;">Kode ICD-9-CM</th>
            <th>Tindakan</th>
            <th style="width:10%;">Jam</th>
            <th style="width:18%;">Pelaksana</th>
        </tr>
        @foreach ([0, 1, 2] as $barisTindakan)
            <tr>
                <td class="dt-tengah">{{ $barisTindakan + 1 }}</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
    </table>
    <div class="dt-note">Obat &amp; cairan yang diberikan dicatat pada UGD-EMR-03; resep pulang pada APT-01.</div>

    <div class="dt-sec">E. Disposisi Pasien</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Disposisi</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Pulang</span>
                <span class="dt-opsi"><span class="dt-box"></span>Rawat inap</span>
                <span class="dt-opsi"><span class="dt-box"></span>Rujuk RS lain</span>
                <span class="dt-opsi"><span class="dt-box"></span>Pulang paksa</span>
                <span class="dt-opsi"><span class="dt-box"></span>Meninggal di UGD</span>
                <span class="dt-opsi"><span class="dt-box"></span>DOA</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Jam keputusan / jam keluar UGD</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Ruangan / RS tujuan</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Kondisi saat keluar UGD</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Edukasi / anjuran kontrol</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        Bila pasien dirawat inap atau dirujuk, lengkapi juga formulir UGD-EMR-05 (transfer / rujukan).
    </div>

    <x-downtime.ttd tempat="Tulungagung" :kolom="['Perawat Pendamping' => 'Paraf', 'Dokter Jaga UGD' => 'Nama & SIP']" />

</x-downtime.halaman>
