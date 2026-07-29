<x-downtime.halaman kode="RI-EMR-05" judul="Observasi Tanda Vital & Balance Cairan Harian"
    subjudul="Pengganti tab Observasi pada EMR Rawat Inap — satu lembar untuk satu hari perawatan"
    unit="Perawat ruangan"
    entriUlang="Daftar Rawat Inap > EMR > Observasi (observasi lanjutan, cairan, oksigen, alat invasif)"
    :break="$dtBreak ?? false">

    <x-downtime.identitas variant="ringkas" />

    <div class="dt-sec">A. Observasi Tanda Vital</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:9%;">Jam</th>
            <th style="width:11%;">TD (mmHg)</th>
            <th style="width:9%;">Nadi</th>
            <th style="width:9%;">RR</th>
            <th style="width:9%;">Suhu</th>
            <th style="width:9%;">SpO2</th>
            <th style="width:9%;">Nyeri</th>
            <th>Keluhan / catatan</th>
            <th style="width:8%;">Paraf</th>
        </tr>
        @for ($baris = 1; $baris <= 8; $baris++)
            <tr>
                <td class="dt-isi">&nbsp;</td>
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

    <div class="dt-sec">B. Balance Cairan</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:16%;">Shift</th>
            <th>Intake &mdash; oral (cc)</th>
            <th>Intake &mdash; infus (cc)</th>
            <th>Output &mdash; urine (cc)</th>
            <th>Output &mdash; lain (cc)</th>
            <th style="width:14%;">Balance (cc)</th>
        </tr>
        @foreach (['Pagi', 'Sore', 'Malam'] as $shift)
            <tr>
                <td>{{ $shift }}</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
        <tr>
            <td class="dt-tebal">Total 24 jam</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">Output lain: muntah, drain, feses cair, IWL. Balance = total intake - total output.</div>

    <div class="dt-sec">C. Oksigen & Alat Invasif Terpasang</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Terapi oksigen</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Nasal kanul</span>
                <span class="dt-opsi"><span class="dt-box"></span>Simple mask</span>
                <span class="dt-opsi"><span class="dt-box"></span>NRM</span>
                <span class="dt-opsi"><span class="dt-box"></span>Lainnya</span>
                &nbsp;&nbsp;Aliran: ............ lpm
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Alat invasif</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Infus perifer</span>
                <span class="dt-opsi"><span class="dt-box"></span>Kateter urine</span>
                <span class="dt-opsi"><span class="dt-box"></span>NGT</span>
                <span class="dt-opsi"><span class="dt-box"></span>Drain</span>
                <span class="dt-opsi"><span class="dt-box"></span>Lainnya</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Tanggal pasang / rencana lepas</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Tanda infeksi lokal</td>
            <td class="dt-isi" style="width:30%;">
                <span class="dt-opsi"><span class="dt-box"></span>Tidak</span>
                <span class="dt-opsi"><span class="dt-box"></span>Ya</span>
            </td>
        </tr>
    </table>
    <div class="dt-note">
        Alat invasif yang terpasang wajib dicatat untuk surveilans HAIs &mdash; data ini dipakai saat entri ulang
        formulir surveilans di SIMRS.
    </div>

    <x-downtime.ttd :kolom="['Perawat Shift Pagi' => 'Paraf', 'Perawat Shift Sore' => 'Paraf', 'Perawat Shift Malam' => 'Paraf']" />

</x-downtime.halaman>
