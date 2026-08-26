<x-downtime.halaman kode="RJ-EMR-04" judul="Permintaan Pemeriksaan Penunjang Rawat Jalan"
    subjudul="Pengganti order laboratorium & radiologi elektronik" unit="Dokter poli > Laboratorium / Radiologi"
    entriUlang="Pelayanan RJ > Pemeriksaan > Penunjang; hasil di Transaksi Laboratorium / Upload Hasil Radiologi"
    identitas="ringkas" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Data Permintaan</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Tanggal / jam order</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Sifat permintaan</td>
            <td class="dt-isi" style="width:30%;">
                <span class="dt-opsi"><span class="dt-box"></span>Rutin</span>
                <span class="dt-opsi"><span class="dt-box"></span>Cito</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Dokter pengirim</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Tujuan</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Laboratorium</span>
                <span class="dt-opsi"><span class="dt-box"></span>Radiologi</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label dt-tebal">Keterangan klinis (wajib)</td>
            <td class="dt-isi-3" colspan="3">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        Keterangan klinis wajib diisi &mdash; SIMRS menolak order laboratorium &amp; radiologi tanpa keterangan
        klinis, sehingga kolom ini harus ada isinya sebelum berkas dikirim ke unit penunjang.
    </div>

    <div class="dt-sec">B. Permintaan Pemeriksaan Laboratorium</div>
    <table class="dt-tbl">
        <tr>
            <th style="width:5%;">No</th>
            <th style="width:22%;">Kelompok</th>
            <th>Nama Pemeriksaan</th>
            <th style="width:14%;">Jenis Sampel</th>
            <th style="width:12%;">Jam Ambil</th>
        </tr>
        @foreach (['Hematologi', 'Kimia Klinik', 'Urinalisa', 'Imunoserologi', 'Mikrobiologi', 'Lainnya'] as $i => $kelompok)
            <tr>
                <td class="dt-tengah">{{ $i + 1 }}</td>
                <td>{{ $kelompok }}</td>
                <td class="dt-isi-2">&nbsp;</td>
                <td class="dt-isi-2">&nbsp;</td>
                <td class="dt-isi-2">&nbsp;</td>
            </tr>
        @endforeach
    </table>

    <div class="dt-sec">C. Permintaan Pemeriksaan Radiologi</div>
    <table class="dt-tbl">
        <tr>
            <th style="width:5%;">No</th>
            <th style="width:22%;">Modalitas</th>
            <th>Regio / Proyeksi</th>
            <th style="width:26%;">Catatan (kontras, persiapan)</th>
        </tr>
        @foreach (['Foto polos (X-Ray)', 'USG', 'Panoramic / Dental', 'Lainnya'] as $i => $modalitas)
            <tr>
                <td class="dt-tengah">{{ $i + 1 }}</td>
                <td>{{ $modalitas }}</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
    </table>

    <div class="dt-sec">D. Serah Terima ke Unit Penunjang</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Diserahkan oleh / jam</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Diterima oleh / jam</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Hasil selesai (jam) / diserahkan kepada</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Nilai kritis dilaporkan?</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Tidak ada nilai kritis</span>
                <span class="dt-opsi"><span class="dt-box"></span>Ada &mdash; dilaporkan kepada dr. ..............................
                    jam ..............</span>
            </td>
        </tr>
    </table>
    <div class="dt-note">
        Lembar hasil manual dari unit penunjang dilampirkan pada formulir ini dan discan/di-upload ke SIMRS
        setelah sistem pulih.
    </div>

    <x-downtime.ttd tempat="Tulungagung" :kolom="['Petugas Unit Penunjang' => 'Penerima order', 'Dokter Pengirim' => 'Nama & SIP']" />

</x-downtime.halaman>
