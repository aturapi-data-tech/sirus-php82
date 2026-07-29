{{-- Kotak identitas pasien kosong untuk formulir manual down time.
     variant "lengkap" = identitas + kunjungan + cara bayar (formulir per pasien),
     variant "ringkas" = hanya identitas inti (formulir yang ruangnya sempit). --}}
@props([
    'variant' => 'lengkap',
])

<table class="dt-tbl" style="margin-top:6px;">
    <tr>
        <td class="dt-tbl-label" style="width:14%;">No. RM</td>
        <td class="dt-isi" style="width:36%;">&nbsp;</td>
        <td class="dt-tbl-label" style="width:14%;">Nama Pasien</td>
        <td class="dt-isi" style="width:36%;">&nbsp;</td>
    </tr>
    <tr>
        <td class="dt-tbl-label">Tgl Lahir / Umur</td>
        <td class="dt-isi">&nbsp;</td>
        <td class="dt-tbl-label">Jenis Kelamin</td>
        <td class="dt-isi">
            <span class="dt-opsi"><span class="dt-box"></span>Laki-laki</span>
            <span class="dt-opsi"><span class="dt-box"></span>Perempuan</span>
        </td>
    </tr>

    @if ($variant === 'lengkap')
        <tr>
            <td class="dt-tbl-label">Alamat</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">NIK</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">No. Kartu BPJS</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Poli / Ruang</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Dokter</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Cara Bayar</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Umum</span>
                <span class="dt-opsi"><span class="dt-box"></span>BPJS</span>
                <span class="dt-opsi"><span class="dt-box"></span>Lainnya</span>
            </td>
            <td class="dt-tbl-label">No. SEP / Rujukan</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    @else
        <tr>
            <td class="dt-tbl-label">Poli / Dokter</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Tgl Kunjungan</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    @endif
</table>
