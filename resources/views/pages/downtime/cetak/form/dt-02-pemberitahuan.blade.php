<x-downtime.halaman kode="DT-02" judul="Pemberitahuan & Ceklist Persiapan Down Time Terencana"
    subjudul="Disampaikan Unit IT minimal 3 (tiga) hari sebelum pelaksanaan" unit="Unit IT & seluruh unit pelayanan"
    entriUlang="Diarsipkan Unit IT bersama DT-01" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Rencana Waktu Henti</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Tanggal pemberitahuan</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">No. surat / edaran</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Pelaksanaan (tgl &amp; jam)</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Perkiraan selesai</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Kegiatan</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Maintenance server</span>
                <span class="dt-opsi"><span class="dt-box"></span>Update aplikasi</span>
                <span class="dt-opsi"><span class="dt-box"></span>Migrasi data</span>
                <span class="dt-opsi"><span class="dt-box"></span>Perbaikan jaringan</span>
                <span class="dt-opsi"><span class="dt-box"></span>Lainnya</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Modul / layanan yang berhenti</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Prosedur alternatif</td>
            <td class="dt-isi-2" colspan="3">Seluruh unit beralih ke formulir manual down time sesuai daftar formulir masing-masing unit.</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Media pemberitahuan</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Surat edaran</span>
                <span class="dt-opsi"><span class="dt-box"></span>Grup komunikasi</span>
                <span class="dt-opsi"><span class="dt-box"></span>Papan pengumuman</span>
                <span class="dt-opsi"><span class="dt-box"></span>Apel / briefing</span>
            </td>
        </tr>
    </table>

    <div class="dt-sec">B. Tanda Terima Pemberitahuan & Kesiapan Formulir Manual per Unit</div>
    <table class="dt-tbl">
        <tr>
            <th style="width:5%;">No</th>
            <th style="width:24%;">Unit Pelayanan</th>
            <th style="width:16%;">Formulir Manual Disiapkan</th>
            <th style="width:12%;">Jumlah Lembar</th>
            <th style="width:23%;">Nama Penerima</th>
            <th>Tgl / Paraf</th>
        </tr>
        @foreach ([
            'Pendaftaran / TU',
            'Poli Rawat Jalan',
            'Unit Gawat Darurat',
            'Rawat Inap',
            'Laboratorium',
            'Radiologi',
            'Apotek',
            'Kasir',
            'Rekam Medis',
            'Gizi',
            'Manajemen',
        ] as $i => $unitSiap)
            <tr>
                <td class="dt-tengah">{{ $i + 1 }}</td>
                <td>{{ $unitSiap }}</td>
                <td class="dt-tengah dt-isi">
                    <span class="dt-opsi"><span class="dt-box"></span>Ya</span>
                    <span class="dt-opsi"><span class="dt-box"></span>Belum</span>
                </td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
    </table>

    <div class="dt-sec">C. Ceklist Kesiapan Unit IT sebelum Pelaksanaan</div>
    <table class="dt-tbl">
        <tr>
            <th style="width:5%;">No</th>
            <th>Item Kesiapan</th>
            <th style="width:14%;">Sudah</th>
            <th style="width:26%;">Keterangan</th>
        </tr>
        @foreach ([
            'Pemberitahuan disampaikan minimal 3 hari sebelumnya',
            'Pencadangan (backup) data terbaru selesai & terverifikasi',
            'Prosedur pemulihan (restore) sudah diuji',
            'Petugas jaga IT selama waktu henti ditetapkan',
            'Nomor kontak darurat IT disebarkan ke seluruh unit',
            'Rencana pemulihan & pengumuman sistem pulih disiapkan',
        ] as $i => $itemSiap)
            <tr>
                <td class="dt-tengah">{{ $i + 1 }}</td>
                <td>{{ $itemSiap }}</td>
                <td class="dt-tengah dt-isi"><span class="dt-box"></span></td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
    </table>

    <x-downtime.ttd tempat="Tulungagung"
        :kolom="['Petugas IT' => 'Penyampai pemberitahuan', 'Ka. Unit IT / SIMRS' => 'Penanggung jawab', 'Mengetahui' => 'Manajemen RS']" />

</x-downtime.halaman>
