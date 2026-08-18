@php
    // Butir telaah disalin persis dari cetak e-resep RJ (cetak-eresep-print) supaya
    // pengkajian manual dapat dipindahkan satu-satu saat entri ulang.
    $telaahResep = [
        'Kejelasan tulisan resep',
        'Tepat obat',
        'Tepat dosis',
        'Tepat rute',
        'Tepat waktu',
        'Duplikasi',
        'Alergi',
        'Interaksi obat',
        'Berat badan pasien anak',
        'Kontra indikasi lain',
    ];

    $telaahObat = [
        'Obat sesuai resep',
        'Jumlah / dosis sesuai resep',
        'Rute sesuai resep',
        'Waktu & frekuensi pemberian',
    ];
@endphp

<x-downtime.halaman kode="APT-01" judul="Resep Manual, Telaah Resep & Telaah Obat"
    subjudul="Pengganti e-resep RJ / UGD / RI beserta pengkajian resep dan pengkajian obat"
    unit="Dokter & apoteker RJ / UGD / RI"
    entriUlang="Pelayanan RJ/UGD/RI > E-Resep, lalu Apotek > Antrian Apotek jalur terkait"
    identitas="lengkap" :break="$dtBreak ?? false">

    <table style="width:100%; border-collapse:collapse; margin-top:6px;">
        <tr>
            {{-- KIRI: penulisan resep --}}
            <td style="width:56%; vertical-align:top; padding-right:8px;">
                <div class="dt-sec" style="margin-top:0;">A. Resep &mdash; Non Racikan</div>
                <table class="dt-tbl dt-tbl-kecil">
                    <tr>
                        <th style="width:8%;">R/</th>
                        <th>Nama Obat</th>
                        <th style="width:14%;">Jml</th>
                        <th style="width:24%;">Signa</th>
                    </tr>
                    @for ($baris = 1; $baris <= 8; $baris++)
                        <tr>
                            <td class="dt-tengah">R/</td>
                            <td class="dt-isi">&nbsp;</td>
                            <td class="dt-isi">&nbsp;</td>
                            <td class="dt-isi">&nbsp;</td>
                        </tr>
                    @endfor
                </table>

                <div class="dt-sec">B. Resep &mdash; Racikan</div>
                <table class="dt-tbl dt-tbl-kecil">
                    <tr>
                        <th style="width:10%;">No. Rac</th>
                        <th>Nama Obat &amp; Dosis</th>
                        <th style="width:14%;">Jml</th>
                        <th style="width:24%;">Signa / Takaran</th>
                    </tr>
                    @for ($baris = 1; $baris <= 6; $baris++)
                        <tr>
                            <td class="dt-isi">&nbsp;</td>
                            <td class="dt-isi">&nbsp;</td>
                            <td class="dt-isi">&nbsp;</td>
                            <td class="dt-isi">&nbsp;</td>
                        </tr>
                    @endfor
                </table>
                <div class="dt-note">
                    Tulis nama obat lengkap dengan kekuatan sediaan. Obat racikan dikelompokkan per nomor racikan.
                </div>
            </td>

            {{-- KANAN: pengkajian --}}
            <td style="width:44%; vertical-align:top; padding-left:8px; border-left:1px solid #d1d5db;">
                <div class="dt-sec" style="margin-top:0;">C. Pengkajian Resep</div>
                <table class="dt-tbl dt-tbl-kecil">
                    <tr>
                        <th>Butir Telaah</th>
                        <th style="width:12%;">Ya</th>
                        <th style="width:12%;">Tidak</th>
                        <th style="width:26%;">Ket.</th>
                    </tr>
                    @foreach ($telaahResep as $butir)
                        <tr>
                            <td>{{ $butir }}</td>
                            <td class="dt-tengah"><span class="dt-box"></span></td>
                            <td class="dt-tengah"><span class="dt-box"></span></td>
                            <td>&nbsp;</td>
                        </tr>
                    @endforeach
                </table>

                <div class="dt-sec">D. Pengkajian Obat</div>
                <table class="dt-tbl dt-tbl-kecil">
                    <tr>
                        <th>Butir Telaah</th>
                        <th style="width:12%;">Ya</th>
                        <th style="width:12%;">Tidak</th>
                        <th style="width:26%;">Ket.</th>
                    </tr>
                    @foreach ($telaahObat as $butir)
                        <tr>
                            <td>{{ $butir }}</td>
                            <td class="dt-tengah"><span class="dt-box"></span></td>
                            <td class="dt-tengah"><span class="dt-box"></span></td>
                            <td>&nbsp;</td>
                        </tr>
                    @endforeach
                </table>

                <div class="dt-sec">E. Penyerahan</div>
                <table class="dt-tbl dt-tbl-kecil">
                    <tr>
                        <td class="dt-tbl-label" style="width:42%;">Status resep</td>
                        <td class="dt-isi">
                            <span class="dt-opsi"><span class="dt-box"></span>Ditunggu</span>
                            <span class="dt-opsi"><span class="dt-box"></span>Ditinggal</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="dt-tbl-label">Iter / PRB</td>
                        <td class="dt-isi">
                            <span class="dt-opsi"><span class="dt-box"></span>Iter</span>
                            <span class="dt-opsi"><span class="dt-box"></span>PRB</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="dt-tbl-label">Jam serah obat</td>
                        <td class="dt-isi">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="dt-tbl-label">Edukasi obat diberikan</td>
                        <td class="dt-isi">
                            <span class="dt-opsi"><span class="dt-box"></span>Ya</span>
                            <span class="dt-opsi"><span class="dt-box"></span>Tidak</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <x-downtime.ttd tempat="Tulungagung"
        :kolom="['Dokter Penulis Resep' => 'Nama & SIP', 'Pengkajian Resep' => 'Apoteker', 'Pengkajian Obat' => 'Apoteker / TTK', 'Penerima Obat' => 'Pasien / keluarga']" />

</x-downtime.halaman>
