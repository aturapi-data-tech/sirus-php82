@php
    // Baris ceklist diambil dari registry formulir supaya otomatis ikut saat
    // formulir manual baru ditambahkan (kecuali formulir Umum/IT itu sendiri),
    // dikelompokkan per area agar tiap unit cepat menemukan bloknya.
    $formulirUnit = array_values(array_filter(
        \App\Support\Downtime\FormulirDowntime::all(),
        fn($f) => $f['area'] !== 'umum',
    ));

    $formulirPerArea = [];
    foreach ($formulirUnit as $f) {
        $formulirPerArea[$f['area']][] = $f;
    }
@endphp

<x-downtime.halaman kode="DT-03" judul="Ceklist Rekonsiliasi & Entri Ulang Data Pasca Down Time"
    subjudul="Diisi tiap unit setelah sistem pulih, diverifikasi supervisor unit" unit="Seluruh unit pelayanan"
    entriUlang="Diarsipkan unit masing-masing, salinan ke Unit IT & Rekam Medis" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Identitas Rekonsiliasi</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Unit</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">No. Log Down Time (DT-01)</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Down time mulai</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Down time pulih</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Penanggung jawab entri ulang</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Target selesai entri ulang</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">B. Rekap Berkas Manual & Status Entri Ulang</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:5%;">No</th>
            <th style="width:11%;">Kode Form</th>
            <th>Nama Formulir</th>
            <th style="width:9%;">Jml Lembar Manual</th>
            <th style="width:9%;">Jml Sudah Dientri</th>
            <th style="width:8%;">Selisih</th>
            <th style="width:14%;">Petugas Entri</th>
            <th style="width:9%;">Paraf</th>
        </tr>
        @php $nomorUrut = 0; @endphp
        @foreach ($formulirPerArea as $areaKunci => $formulirArea)
            <tr>
                <td class="dt-tebal" colspan="8">
                    {{ \App\Support\Downtime\FormulirDowntime::labelArea($areaKunci) }}
                </td>
            </tr>
            @foreach ($formulirArea as $f)
                @php $nomorUrut++; @endphp
                <tr>
                    <td class="dt-tengah">{{ $nomorUrut }}</td>
                    <td class="dt-tengah">{{ $f['kode'] }}</td>
                    <td>{{ $f['judul'] }}</td>
                    <td class="dt-isi">&nbsp;</td>
                    <td class="dt-isi">&nbsp;</td>
                    <td class="dt-isi">&nbsp;</td>
                    <td class="dt-isi">&nbsp;</td>
                    <td class="dt-isi">&nbsp;</td>
                </tr>
            @endforeach
        @endforeach
    </table>
    <div class="dt-note">
        Isi hanya baris formulir yang benar-benar dipakai unit Anda. Selisih harus nol sebelum ceklist ditutup;
        bila masih ada selisih, tuliskan alasannya di bagian D.
    </div>

    <div class="dt-sec">C. Verifikasi Kelengkapan & Keakuratan Data</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:5%;">No</th>
            <th>Item Verifikasi</th>
            <th style="width:10%;">Ya</th>
            <th style="width:10%;">Tidak</th>
            <th style="width:26%;">Keterangan</th>
        </tr>
        @foreach ([
            'Seluruh pasien yang dilayani manual sudah terdaftar di SIMRS',
            'Tanggal & jam pelayanan dientri sesuai catatan manual (bukan jam entri)',
            'Data klinis (asesmen, diagnosa, terapi) sudah lengkap di EMR',
            'Order & hasil penunjang sudah tercatat pada pasien yang benar',
            'Resep dan pengeluaran obat sudah dientri, stok sudah sesuai fisik',
            'Seluruh pembayaran sudah dientri, nomor kwitansi manual dicatat',
            'Total penerimaan kasir manual = total penerimaan di SIMRS',
            'Berkas manual sudah diarsipkan di rekam medis / unit terkait',
        ] as $i => $verifikasi)
            <tr>
                <td class="dt-tengah">{{ $i + 1 }}</td>
                <td>{{ $verifikasi }}</td>
                <td class="dt-tengah dt-isi"><span class="dt-box"></span></td>
                <td class="dt-tengah dt-isi"><span class="dt-box"></span></td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
    </table>

    <div class="dt-sec">D. Catatan Selisih / Data Belum Lengkap</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-isi-2">&nbsp;</td>
        </tr>
    </table>

    <x-downtime.ttd tempat="Tulungagung"
        :kolom="['Petugas Entri Ulang' => 'Pelaksana', 'Supervisor / Ka. Unit' => 'Verifikator', 'Unit IT / SIMRS' => 'Mengetahui']" />

</x-downtime.halaman>
