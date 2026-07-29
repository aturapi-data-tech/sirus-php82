<x-downtime.halaman kode="APT-02" judul="Etiket Obat Manual"
    subjudul="8 etiket siap potong per lembar — dipakai bila pencetakan etiket SIMRS tidak tersedia"
    unit="Apotek RJ / UGD / RI"
    entriUlang="Tidak perlu dientri — etiket tercetak otomatis saat e-resep dientri ulang" :break="$dtBreak ?? false">

    <table style="width:100%; border-collapse:separate; border-spacing:4px; margin-top:4px;">
        @for ($barisEtiket = 0; $barisEtiket < 4; $barisEtiket++)
            <tr>
                @for ($kolomEtiket = 0; $kolomEtiket < 2; $kolomEtiket++)
                    <td style="width:50%; vertical-align:top; border:1px dashed #6b7280; padding:4px;">
                        <div style="font-size:7.5px; color:#555;">
                            ETIKET MANUAL ({{ $barisEtiket * 2 + $kolomEtiket + 1 }})
                        </div>
                        <table class="dt-plain" style="margin-top:2px;">
                            <tr>
                                <td style="width:26%;">No. RM</td>
                                <td class="dt-garis">&nbsp;</td>
                                <td style="width:20%;">Tgl</td>
                                <td class="dt-garis" style="width:26%;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td>Nama</td>
                                <td class="dt-garis" colspan="3">&nbsp;</td>
                            </tr>
                            <tr>
                                <td>Nama obat</td>
                                <td class="dt-garis" colspan="3">&nbsp;</td>
                            </tr>
                            <tr>
                                <td>Aturan pakai</td>
                                <td class="dt-garis" colspan="3">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; x sehari
                                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                            </tr>
                        </table>
                        <div style="font-size:7.5px; margin-top:3px;">
                            <span class="dt-opsi"><span class="dt-box"></span>Sebelum makan</span>
                            <span class="dt-opsi"><span class="dt-box"></span>Sesudah makan</span>
                            <span class="dt-opsi"><span class="dt-box"></span>Saat makan</span>
                        </div>
                        <div style="font-size:7.5px; margin-top:2px;">
                            <span class="dt-opsi"><span class="dt-box"></span>Pagi</span>
                            <span class="dt-opsi"><span class="dt-box"></span>Siang</span>
                            <span class="dt-opsi"><span class="dt-box"></span>Sore</span>
                            <span class="dt-opsi"><span class="dt-box"></span>Malam</span>
                        </div>
                        <table class="dt-plain">
                            <tr>
                                <td style="width:26%;">Catatan</td>
                                <td class="dt-garis">&nbsp;</td>
                            </tr>
                            <tr>
                                <td>ED / petugas</td>
                                <td class="dt-garis">&nbsp;</td>
                            </tr>
                        </table>
                    </td>
                @endfor
            </tr>
        @endfor
    </table>

    <div class="dt-note">
        Etiket manual ditulis dengan huruf cetak yang mudah dibaca. Obat tetap diserahkan dengan penjelasan lisan
        (nama obat, indikasi, aturan pakai, efek samping penting) sesuai standar pelayanan kefarmasian.
    </div>

</x-downtime.halaman>
