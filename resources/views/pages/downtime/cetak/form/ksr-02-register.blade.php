<x-downtime.halaman kode="KSR-02" judul="Register Penerimaan Kasir"
    subjudul="Rekap penerimaan RJ / UGD / RI selama waktu henti — 20 baris per lembar" unit="Kasir RJ / UGD / RI"
    entriUlang="Dicocokkan dengan Kasir > Antrian Kasir tiap jalur dan Keuangan > Saldo Kas"
    :break="$dtBreak ?? false">

    <table class="dt-tbl" style="margin-top:6px;">
        <tr>
            <td class="dt-tbl-label" style="width:14%;">Tanggal</td>
            <td class="dt-isi" style="width:19%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:14%;">Shift / kasir</td>
            <td class="dt-isi" style="width:19%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:14%;">Lembar ke</td>
            <td class="dt-isi" style="width:20%;">&nbsp;</td>
        </tr>
    </table>

    <table class="dt-tbl dt-tbl-kecil" style="margin-top:6px;">
        <tr>
            <th style="width:4%;">No</th>
            <th style="width:7%;">Jam</th>
            <th style="width:7%;">Jalur</th>
            <th style="width:10%;">No. Kwitansi</th>
            <th style="width:9%;">No. RM</th>
            <th style="width:16%;">Nama Pasien</th>
            <th style="width:12%;">Total (Rp)</th>
            <th style="width:11%;">Tunai (Rp)</th>
            <th style="width:11%;">Non Tunai (Rp)</th>
            <th style="width:9%;">Piutang (Rp)</th>
            <th style="width:6%;">Entri v</th>
        </tr>
        @for ($baris = 1; $baris <= 20; $baris++)
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
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endfor
        <tr>
            <td class="dt-tebal dt-kanan" colspan="6">SUBTOTAL LEMBAR INI</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
    </table>

    <table class="dt-tbl" style="margin-top:6px;">
        <tr>
            <td class="dt-tbl-label" style="width:26%;">Total seluruh lembar (shift ini)</td>
            <td class="dt-isi" style="width:24%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:26%;">Uang fisik dihitung</td>
            <td class="dt-isi" style="width:24%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Selisih (bila ada)</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Penjelasan selisih</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        Total register ini harus sama dengan total penerimaan kasir di SIMRS setelah seluruh transaksi dientri
        ulang. Selisih yang belum terselesaikan dilaporkan pada formulir DT-03 dan KSR-03.
    </div>

    <x-downtime.ttd :kolom="['Kasir Pelaksana' => 'Pencatat', 'Supervisor TU' => 'Verifikator']" />

</x-downtime.halaman>
