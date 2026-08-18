@php
    $pecahan = ['100.000', '50.000', '20.000', '10.000', '5.000', '2.000', '1.000', 'Uang logam', 'Lainnya'];
@endphp

<x-downtime.halaman kode="KSR-03" judul="Berita Acara Serah Terima Kas Shift Kasir"
    subjudul="Serah terima uang fisik antar shift selama waktu henti" unit="Kasir & supervisor TU"
    entriUlang="Diarsipkan kasir; nilai akhir dicocokkan ke Keuangan > Saldo Kas" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Identitas Serah Terima</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Tanggal / jam</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Shift</td>
            <td class="dt-isi" style="width:30%;">
                <span class="dt-opsi"><span class="dt-box"></span>1</span>
                <span class="dt-opsi"><span class="dt-box"></span>2</span>
                <span class="dt-opsi"><span class="dt-box"></span>3</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Yang menyerahkan</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Yang menerima</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">B. Rincian Uang Fisik</div>
    <table class="dt-tbl">
        <tr>
            <th style="width:5%;">No</th>
            <th style="width:28%;">Pecahan</th>
            <th style="width:20%;">Jumlah Lembar / Keping</th>
            <th>Nilai (Rp)</th>
        </tr>
        @foreach ($pecahan as $i => $nominal)
            <tr>
                <td class="dt-tengah">{{ $i + 1 }}</td>
                <td>{{ $nominal }}</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
        <tr>
            <td class="dt-tebal dt-kanan" colspan="3">TOTAL UANG FISIK</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">C. Pencocokan</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:26%;">Total register manual (KSR-02)</td>
            <td class="dt-isi" style="width:24%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:26%;">Total uang fisik</td>
            <td class="dt-isi" style="width:24%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Saldo awal shift (uang kembalian)</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Selisih</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Jumlah kwitansi manual dipakai</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Nomor kwitansi (dari &ndash; sampai)</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Transaksi tertunda / belum selesai</td>
            <td class="dt-isi-3" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Catatan lain</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
    </table>

    <x-downtime.ttd tempat="Tulungagung"
        :kolom="['Kasir yang Menyerahkan' => 'Nama & paraf', 'Kasir yang Menerima' => 'Nama & paraf', 'Supervisor TU' => 'Saksi / verifikator']" />

</x-downtime.halaman>
