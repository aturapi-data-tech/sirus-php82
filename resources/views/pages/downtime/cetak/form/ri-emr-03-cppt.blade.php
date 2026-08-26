<x-downtime.halaman kode="RI-EMR-03" judul="Catatan Perkembangan Pasien Terintegrasi (CPPT)"
    subjudul="Pengganti CPPT elektronik — format SOAP per PPA, diverifikasi DPJP utama"
    unit="Seluruh PPA (dokter, perawat, apoteker, gizi, fisioterapi)"
    entriUlang="Daftar Rawat Inap > EMR > CPPT (entri per catatan sesuai tanggal & profesi)"
    identitas="ringkas" :break="$dtBreak ?? false">

    @foreach ([1, 2, 3] as $entri)
        <div class="dt-sec">Catatan {{ $entri }}</div>
        <table class="dt-tbl dt-tbl-kecil">
            <tr>
                <td class="dt-tbl-label" style="width:14%;">Tanggal / jam</td>
                <td class="dt-isi" style="width:22%;">&nbsp;</td>
                <td class="dt-tbl-label" style="width:14%;">Profesi (PPA)</td>
                <td class="dt-isi" style="width:50%;">
                    <span class="dt-opsi"><span class="dt-box"></span>Dokter</span>
                    <span class="dt-opsi"><span class="dt-box"></span>Perawat</span>
                    <span class="dt-opsi"><span class="dt-box"></span>Apoteker</span>
                    <span class="dt-opsi"><span class="dt-box"></span>Gizi</span>
                    <span class="dt-opsi"><span class="dt-box"></span>Fisioterapi</span>
                </td>
            </tr>
            <tr>
                <td class="dt-tbl-label">S &mdash; Subjektif</td>
                <td class="dt-isi" colspan="3">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">O &mdash; Objektif</td>
                <td class="dt-isi" colspan="3">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">A &mdash; Asesmen</td>
                <td class="dt-isi" colspan="3">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">P &mdash; Plan</td>
                <td class="dt-isi" colspan="3">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">Instruksi PPA</td>
                <td class="dt-isi" colspan="3">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">Nama &amp; paraf PPA</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-tbl-label">Review / verifikasi DPJP</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        </table>
    @endforeach

    <div class="dt-note">
        Tulis jam penulisan catatan yang sebenarnya &mdash; jam inilah yang dipakai saat entri ulang ke SIMRS,
        bukan jam saat data dimasukkan. Satu lembar memuat 3 catatan; gunakan lembar tambahan bila kurang.
    </div>

</x-downtime.halaman>
