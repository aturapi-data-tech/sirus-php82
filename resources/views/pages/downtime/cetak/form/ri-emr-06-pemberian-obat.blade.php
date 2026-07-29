<x-downtime.halaman kode="RI-EMR-06" judul="Rekam Pemberian Obat Rawat Inap"
    subjudul="Catatan pemberian obat per pasien selama waktu henti — dasar penagihan & penyesuaian stok ruangan"
    unit="Perawat & apoteker ruangan"
    entriUlang="Daftar Rawat Inap > E-Resep / Administrasi RI (obat), lalu cek Kartu Stock ruangan"
    identitas="ringkas" :break="$dtBreak ?? false">

    <table class="dt-tbl" style="margin-top:6px;">
        <tr>
            <td class="dt-tbl-label" style="width:16%;">Tanggal</td>
            <td class="dt-isi" style="width:17%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:16%;">Ruangan / kamar</td>
            <td class="dt-isi" style="width:17%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:16%;">DPJP</td>
            <td class="dt-isi" style="width:18%;">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">A. Pemberian Obat</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:22%;">Nama Obat</th>
            <th style="width:10%;">Dosis</th>
            <th style="width:9%;">Rute</th>
            <th style="width:12%;">Frekuensi</th>
            <th style="width:11%;">Pagi (jam/paraf)</th>
            <th style="width:11%;">Siang</th>
            <th style="width:11%;">Sore</th>
            <th>Malam</th>
        </tr>
        @for ($baris = 1; $baris <= 10; $baris++)
            <tr>
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
    <div class="dt-note">
        Tulis jam pemberian pada kotak lalu bubuhkan paraf. Prinsip 7 benar tetap berlaku: benar pasien, obat,
        dosis, rute, waktu, dokumentasi, dan informasi.
    </div>

    <div class="dt-sec">B. Obat Ditunda / Tidak Diberikan</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:10%;">Jam</th>
            <th style="width:28%;">Nama Obat</th>
            <th>Alasan (puasa, alergi, stok kosong, instruksi DPJP)</th>
            <th style="width:12%;">Paraf</th>
        </tr>
        @for ($baris = 1; $baris <= 3; $baris++)
            <tr>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endfor
    </table>

    <div class="dt-sec">C. Verifikasi Apoteker</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:24%;">Telaah obat dilakukan</td>
            <td class="dt-isi" style="width:26%;">
                <span class="dt-opsi"><span class="dt-box"></span>Ya</span>
                <span class="dt-opsi"><span class="dt-box"></span>Tidak</span>
            </td>
            <td class="dt-tbl-label" style="width:24%;">Catatan interaksi / duplikasi</td>
            <td class="dt-isi" style="width:26%;">&nbsp;</td>
        </tr>
    </table>

    <x-downtime.ttd :kolom="['Perawat Penanggung Jawab' => 'Nama & paraf', 'Apoteker Ruangan' => 'Nama & paraf']" />

</x-downtime.halaman>
