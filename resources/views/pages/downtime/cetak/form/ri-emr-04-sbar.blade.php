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
                <td class="dt-tbl-label">S &mdash; Situation<br><span class="dt-kecil">kondisi &amp; keluhan saat ini</span></td>
                <td class="dt-isi-2" colspan="3">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">B &mdash; Background<br><span class="dt-kecil">diagnosa, riwayat, terapi berjalan</span></td>
                <td class="dt-isi-2" colspan="3">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">A &mdash; Assessment<br><span class="dt-kecil">tanda vital, hasil penunjang, masalah</span></td>
                <td class="dt-isi-2" colspan="3">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">R &mdash; Recommendation<br><span class="dt-kecil">yang perlu dilanjutkan / dipantau</span></td>
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
