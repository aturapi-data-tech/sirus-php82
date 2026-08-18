{{-- Halaman sampul + daftar isi untuk bundel formulir manual down time (sosialisasi).
     Kop RS dicetak layout standar (blok pertama dokumen), jadi di sini tidak
     dicetak ulang. --}}
@props([
    'judul' => 'Bundel Formulir Manual Down Time SIMRS',
    'daftar' => [],
    'dicetakOleh' => null,
    'tglCetak' => null,
])

@php
    $perArea = [];
    foreach ($daftar as $item) {
        $perArea[$item['area']][] = $item;
    }
@endphp

<div class="dt-halaman">

    <div class="dt-judul-wrap" style="margin-top:22px;">
        <div class="dt-judul" style="font-size:15px;">{{ $judul }}</div>
        <div class="dt-subjudul" style="font-size:10px;">
            Prosedur alternatif pelayanan saat sistem data / aplikasi SIMRS tidak dapat diakses
        </div>
    </div>

    <div class="dt-pita">
        Cetak, gandakan, dan simpan di tiap unit &mdash; formulir ini hanya dipakai saat SIMRS mengalami waktu henti
    </div>

    <table class="dt-tbl" style="margin-top:12px;">
        <tr>
            <td class="dt-tbl-label" style="width:24%;">Dicetak pada</td>
            <td style="width:26%;">{{ $tglCetak ?? '-' }}</td>
            <td class="dt-tbl-label" style="width:24%;">Dicetak oleh</td>
            <td style="width:26%;">{{ $dicetakOleh ?? '-' }}</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Jumlah formulir</td>
            <td>{{ count($daftar) }} formulir</td>
            <td class="dt-tbl-label">Acuan</td>
            <td>SPO Waktu Henti (Down Time) Sistem Data</td>
        </tr>
    </table>

    <div class="dt-sec">Daftar Formulir</div>
    <table class="dt-tbl">
        <tr>
            <th style="width:5%;">No</th>
            <th style="width:12%;">Kode</th>
            <th>Nama Formulir</th>
            <th style="width:30%;">Unit Pengguna</th>
        </tr>
        @php $nomorUrut = 0; @endphp
        @foreach ($perArea as $areaKunci => $formulirArea)
            <tr>
                <td class="dt-tebal" colspan="4">
                    {{ \App\Support\Downtime\FormulirDowntime::labelArea($areaKunci) }}
                </td>
            </tr>
            @foreach ($formulirArea as $item)
                @php $nomorUrut++; @endphp
                <tr>
                    <td class="dt-tengah">{{ $nomorUrut }}</td>
                    <td>{{ $item['kode'] }}</td>
                    <td>{{ $item['judul'] }}</td>
                    <td>{{ $item['unit'] }}</td>
                </tr>
            @endforeach
        @endforeach
    </table>

    <div class="dt-sec">Ringkasan Alur Waktu Henti</div>
    <table class="dt-tbl">
        <tr>
            <th style="width:22%;">Tahap</th>
            <th style="width:39%;">Waktu Henti Terencana</th>
            <th>Waktu Henti Tidak Terencana</th>
        </tr>
        <tr>
            <td class="dt-tebal">Pemberitahuan</td>
            <td>Unit IT memberitahu seluruh unit minimal 3 hari sebelumnya (DT-02).</td>
            <td>Unit yang mendeteksi gangguan segera melapor ke Unit IT; IT menginformasikan estimasi pemulihan.</td>
        </tr>
        <tr>
            <td class="dt-tebal">Pelaksanaan</td>
            <td>Seluruh unit memakai formulir manual sesuai daftar di atas.</td>
            <td>Seluruh unit langsung beralih ke formulir manual sesuai daftar di atas.</td>
        </tr>
        <tr>
            <td class="dt-tebal">Pemulihan</td>
            <td>IT mengumumkan sistem pulih; data manual dientri ulang oleh petugas berwenang.</td>
            <td>IT melakukan troubleshooting &amp; mengumumkan sistem pulih; data manual dientri ulang.</td>
        </tr>
        <tr>
            <td class="dt-tebal">Verifikasi</td>
            <td colspan="2">
                Tiap unit mengisi DT-03 (ceklist rekonsiliasi &amp; entri ulang); Unit IT mengisi DT-01 (log kejadian)
                dan menyusun evaluasi serta rencana tindak lanjut untuk pimpinan RS.
            </td>
        </tr>
    </table>

    <div class="dt-note">
        Selama waktu henti, komunikasi antar unit dilakukan melalui telepon dan grup komunikasi. Seluruh berkas
        manual wajib disimpan dan diarsipkan setelah dientri ulang ke SIMRS.
    </div>

</div>
