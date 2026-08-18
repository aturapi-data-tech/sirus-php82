<x-downtime.halaman kode="KSR-01" judul="Kwitansi Pembayaran Manual"
    subjudul="Rangkap 2 — lembar pasien & arsip kasir; berlaku untuk RJ, UGD dan RI" unit="Kasir RJ / UGD / RI"
    entriUlang="Kasir > Antrian Kasir RJ / UGD / RI; nomor kwitansi manual dicatat di keterangan"
    :break="$dtBreak ?? false">

    @foreach (['LEMBAR PASIEN', 'ARSIP KASIR'] as $rangkap)
        @if (!$loop->first)
            <div class="dt-potong">&mdash; &mdash; &mdash; potong di sini &mdash; &mdash; &mdash;</div>
        @endif

        <table class="dt-tbl" style="margin-top:6px;">
            <tr>
                <td class="dt-tebal dt-tengah" colspan="4" style="background-color:#f3f5f4;">
                    KWITANSI PEMBAYARAN &mdash; {{ $rangkap }}
                </td>
            </tr>
            <tr>
                <td class="dt-tbl-label" style="width:20%;">No. kwitansi manual</td>
                <td class="dt-isi" style="width:30%;">&nbsp;</td>
                <td class="dt-tbl-label" style="width:20%;">Tanggal / jam</td>
                <td class="dt-isi" style="width:30%;">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">Jalur pelayanan</td>
                <td class="dt-isi" colspan="3">
                    <span class="dt-opsi"><span class="dt-box"></span>Rawat Jalan</span>
                    <span class="dt-opsi"><span class="dt-box"></span>UGD</span>
                    <span class="dt-opsi"><span class="dt-box"></span>Rawat Inap</span>
                </td>
            </tr>
            <tr>
                <td class="dt-tbl-label">Sudah terima dari</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-tbl-label">No. RM / unit</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">Untuk pembayaran</td>
                <td class="dt-isi" colspan="3">Pelayanan tanggal .......................................</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">Rincian ringkas</td>
                <td class="dt-isi-3" colspan="3">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">Total tagihan (Rp)</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-tbl-label">Dibayar (Rp)</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
            <tr>
                <td class="dt-tbl-label">Kembalian (Rp)</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-tbl-label">Cara bayar</td>
                <td class="dt-isi">
                    <span class="dt-opsi"><span class="dt-box"></span>Tunai</span>
                    <span class="dt-opsi"><span class="dt-box"></span>Transfer / EDC</span>
                    <span class="dt-opsi"><span class="dt-box"></span>Piutang</span>
                </td>
            </tr>
            <tr>
                <td class="dt-tbl-label">Terbilang</td>
                <td class="dt-isi-2" colspan="3">&nbsp;</td>
            </tr>
        </table>

        <x-downtime.ttd tempat="Tulungagung" :kolom="['Penerima / Pasien' => 'Nama terang', 'Kasir' => 'Nama & paraf']" />
    @endforeach

    <div class="dt-note">
        Nomor kwitansi manual memakai blok nomor cadangan kasir. Saat entri ulang, nomor tersebut wajib ditulis
        pada kolom keterangan pembayaran di SIMRS agar uang fisik dapat ditelusuri ke transaksi elektroniknya.
    </div>

</x-downtime.halaman>
