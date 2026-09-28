<x-downtime.halaman kode="RI-EMR-04" judul="SBAR — Serah Terima Pasien Antar Shift"
    subjudul="Pengganti SBAR elektronik pada EMR Rawat Inap" unit="Perawat ruangan"
    entriUlang="Daftar Rawat Inap > EMR > SBAR (entri per catatan sesuai tanggal & profesi)"
    identitas="ringkas" :break="$dtBreak ?? false">

    @foreach ([1, 2] as $entri)
        <div class="dt-sec">Serah Terima {{ $entri }}</div>
        <table class="dt-tbl dt-tbl-kecil">
            <tr>
                <td class="dt-tbl-label" style="width:16%;">Tanggal / jam</td>
                <td class="dt-isi" style="width:34%;">&nbsp;</td>
                <td class="dt-tbl-label" style="width:16%;">Shift</td>
                <td class="dt-isi" style="width:34%;">
                    <span class="dt-opsi"><span class="dt-box"></span>Pagi</span>
                    <span class="dt-opsi"><span class="dt-box"></span>Sore</span>
                    <span class="dt-opsi"><span class="dt-box"></span>Malam</span>
                </td>
            </tr>
            <tr>
                <td class="dt-tbl-label">S &mdash; Subjective<br><span class="dt-kecil">keluhan pasien / keluarga</span></td>
                <td class="dt-isi-2" colspan="3">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">O &mdash; Objective<br><span class="dt-kecil">tanda vital, pemeriksaan fisik &amp; penunjang</span></td>
                <td class="dt-isi-2" colspan="3">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">A &mdash; Assessment<br><span class="dt-kecil">penilaian klinis / masalah</span></td>
                <td class="dt-isi-2" colspan="3">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">P &mdash; Plan<br><span class="dt-kecil">rencana tindakan / yang perlu dipantau</span></td>
                <td class="dt-isi-2" colspan="3">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">Perawat menyerahkan</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-tbl-label">Perawat menerima</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        </table>
    @endforeach

    <div class="dt-note">
        Satu lembar memuat 2 serah terima. Bila selama waktu henti terjadi lebih dari itu, tambahkan lembar baru
        dan urutkan berdasarkan jam.
    </div>

</x-downtime.halaman>
