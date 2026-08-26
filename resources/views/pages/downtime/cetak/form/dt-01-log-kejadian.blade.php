<x-downtime.halaman kode="DT-01" judul="Log Kejadian & Penanganan Down Time SIMRS"
    subjudul="Diisi Unit IT / Penyelenggara SIMRS untuk setiap kejadian waktu henti" unit="Unit IT"
    entriUlang="Diarsipkan Unit IT — bahan laporan evaluasi ke pimpinan RS" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Data Kejadian</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:18%;">Jenis Waktu Henti</td>
            <td class="dt-isi" style="width:32%;">
                <span class="dt-opsi"><span class="dt-box"></span>Terencana</span>
                <span class="dt-opsi"><span class="dt-box"></span>Tidak terencana</span>
            </td>
            <td class="dt-tbl-label" style="width:18%;">No. Log</td>
            <td class="dt-isi" style="width:32%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Mulai (tgl &amp; jam)</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Pulih (tgl &amp; jam)</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Durasi</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Lingkup Gangguan</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Seluruh sistem</span>
                <span class="dt-opsi"><span class="dt-box"></span>Sebagian modul</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Modul / Layanan Terdampak</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">B. Pelaporan Awal</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:18%;">Dilaporkan oleh</td>
            <td class="dt-isi" style="width:32%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:18%;">Unit pelapor</td>
            <td class="dt-isi" style="width:32%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Jam laporan diterima</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Media laporan</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Telepon</span>
                <span class="dt-opsi"><span class="dt-box"></span>Grup</span>
                <span class="dt-opsi"><span class="dt-box"></span>Langsung</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Gejala / keluhan awal</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">C. Identifikasi & Penanganan</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:18%;">Hasil identifikasi penyebab</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Estimasi pemulihan yang diinformasikan</td>
            <td class="dt-isi" style="width:32%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:18%;">Jam informasi disampaikan</td>
            <td class="dt-isi" style="width:32%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Tindakan penanganan</td>
            <td class="dt-isi-3" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Hasil penanganan</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">D. Dampak terhadap Pelayanan</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:5%;">No</th>
            <th style="width:25%;">Unit Pelayanan</th>
            <th style="width:14%;">Beralih ke Manual</th>
            <th style="width:16%;">Jml Pasien / Transaksi</th>
            <th>Catatan Dampak</th>
        </tr>
        @foreach (['Pendaftaran / TU', 'Poli Rawat Jalan', 'Unit Gawat Darurat', 'Rawat Inap', 'Laboratorium', 'Radiologi', 'Apotek', 'Kasir', 'Rekam Medis'] as $i => $unitDampak)
            <tr>
                <td class="dt-tengah">{{ $i + 1 }}</td>
                <td>{{ $unitDampak }}</td>
                <td class="dt-tengah dt-isi">
                    <span class="dt-box"></span>Ya
                </td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
    </table>

    <div class="dt-sec">E. Evaluasi & Rencana Tindak Lanjut</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:18%;">Analisis akar masalah</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Rencana tindak lanjut</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label" style="width:18%;">Penanggung jawab</td>
            <td class="dt-isi" style="width:32%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:18%;">Target selesai</td>
            <td class="dt-isi" style="width:32%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Status pencadangan (backup) terakhir</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
    </table>

    <x-downtime.ttd tempat="Tulungagung"
        :kolom="['Petugas IT Penanganan' => 'Pelaksana', 'Ka. Unit IT / SIMRS' => 'Verifikator', 'Mengetahui' => 'Manajemen RS']" />

</x-downtime.halaman>
