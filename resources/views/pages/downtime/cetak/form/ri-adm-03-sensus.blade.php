<x-downtime.halaman kode="RI-ADM-03" judul="Sensus Harian & Mutasi Kamar Rawat Inap"
    subjudul="Rekap harian pasien per ruangan — dasar sinkronisasi tempat tidur & indikator BOR"
    unit="Perawat ruangan & rekam medis"
    entriUlang="Rawat Inap > Daftar Rawat Inap (pindah kamar) & Sinkronisasi Tempat Tidur"
    :break="$dtBreak ?? false">

    <table class="dt-tbl" style="margin-top:6px;">
        <tr>
            <td class="dt-tbl-label" style="width:14%;">Tanggal</td>
            <td class="dt-isi" style="width:19%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:14%;">Ruangan / bangsal</td>
            <td class="dt-isi" style="width:19%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:14%;">Jumlah TT tersedia</td>
            <td class="dt-isi" style="width:20%;">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">A. Rekap Sensus</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th>Pasien awal (sisa kemarin)</th>
            <th>Masuk hari ini</th>
            <th>Pindahan masuk</th>
            <th>Pindah keluar</th>
            <th>Pulang / keluar</th>
            <th>Meninggal</th>
            <th>Sisa akhir</th>
        </tr>
        <tr>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">B. Daftar Pasien per Kamar</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:4%;">No</th>
            <th style="width:11%;">Kamar / Bed</th>
            <th style="width:9%;">No. RM</th>
            <th style="width:22%;">Nama Pasien</th>
            <th style="width:8%;">Kelas</th>
            <th style="width:14%;">DPJP</th>
            <th style="width:9%;">Tgl Masuk</th>
            <th>Keterangan (masuk / pindah / pulang)</th>
            <th style="width:6%;">Entri v</th>
        </tr>
        @for ($baris = 1; $baris <= 16; $baris++)
            <tr>
                <td class="dt-tengah">{{ $baris }}</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endfor
    </table>

    <div class="dt-sec">C. Tempat Tidur Kosong per Kelas</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th>VIP</th>
            <th>Kelas I</th>
            <th>Kelas II</th>
            <th>Kelas III</th>
            <th>Isolasi</th>
            <th>Lainnya</th>
            <th style="width:26%;">Catatan</th>
        </tr>
        <tr>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        Setelah sistem pulih, jumlah tempat tidur kosong wajib disinkronkan ulang ke Aplicares BPJS &amp; SIRS
        Kemenkes lewat menu Sinkronisasi Tempat Tidur.
    </div>

    <x-downtime.ttd :kolom="['Perawat Penanggung Jawab Ruangan' => 'Pencatat', 'Rekam Medis' => 'Verifikator']" />

</x-downtime.halaman>
