{{-- Cetak Daftar Tarif Down Time — dokumen berdiri sendiri, BUKAN memakai
     x-pdf.layout-a4-with-out-background.

     Alasannya performa: layout cetak standar menyuntikkan seluruh CSS build
     (±193 KB Tailwind) ke dalam dokumen. Untuk dokumen naratif satu-dua halaman
     itu tidak terasa, tetapi daftar tarif berisi ribuan baris — dompdf mencocokkan
     tiap elemen ke seluruh selektor, sehingga 1.542 baris obat saja menghabiskan
     ±815 MB memori & ~2 menit. Dengan CSS seperlunya di bawah ini, dokumen yang
     sama turun drastis. Kop RS tetap komponen standar (x-logo.identitas) yang
     memang murni inline style.

     $paket      : array paket kategori dari App\Support\Downtime\TarifDowntime::paketCetak()
     $judul      : judul dokumen
     $dicetakOleh: nama petugas pencetak
     $tglCetak   : tanggal & jam cetak (tarif ikut master SAAT dicetak) --}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Daftar Tarif Down Time</title>
    <style>
        @page {
            size: A4;
            margin: 16px 0 14px 0;
        }

        @page :first {
            margin-top: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: sans-serif;
            font-size: 9px;
            color: #111;
        }

        .trf-isi {
            padding: 14px 22px 14px 22px;
        }

        .trf-break {
            page-break-before: always;
        }

        .trf-judul {
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .trf-subjudul {
            font-size: 9px;
            text-align: center;
            color: #555;
            margin-top: 2px;
        }

        .trf-kode {
            width: 190px;
            border-collapse: collapse;
        }

        .trf-kode td {
            border: 1px solid #9aa89e;
            padding: 2px 5px;
            font-size: 8.5px;
        }

        .trf-pita {
            margin-top: 6px;
            border: 1px solid #b91c1c;
            background-color: #fef2f2;
            color: #7f1d1d;
            font-size: 8.5px;
            font-weight: bold;
            text-align: center;
            padding: 3px 6px;
            text-transform: uppercase;
        }

        .trf-info {
            margin-top: 4px;
            margin-bottom: 4px;
            font-size: 8px;
            color: #444;
        }

        .trf-potong {
            margin-top: 4px;
            border: 1px solid #b45309;
            background-color: #fffbeb;
            color: #78350f;
            font-size: 8px;
            padding: 3px 6px;
        }

        .trf-tbl {
            width: 100%;
            border-collapse: collapse;
        }

        .trf-tbl th,
        .trf-tbl td {
            border: 1px solid #9aa89e;
            padding: 2px 4px;
            font-size: 8px;
            vertical-align: top;
        }

        .trf-tbl th {
            background-color: #f3f5f4;
            font-weight: bold;
            text-align: center;
        }

        /* Tabel jasa medis/dokter: 12 kolom nominal dalam satu baris A4 potrait. */
        .trf-tbl-padat th,
        .trf-tbl-padat td {
            font-size: 7px;
            padding: 1px 2px;
        }

        /* Pembeda kelompok kolom — sama seperti di layar, tetapi memakai nada muda
           yang tetap terbaca saat dicetak hitam-putih atau difotokopi. */
        .trf-tbl .trf-w-poli {
            background-color: #fdf6e3;
        }

        .trf-tbl .trf-w-inap,
        .trf-tbl .trf-w-inap-a {
            background-color: #eef4fa;
        }

        .trf-tbl .trf-w-inap-b {
            background-color: #f6f2fb;
        }

        /* Judul grup tetap lebih tua daripada sel isinya. */
        .trf-tbl th.trf-w-poli {
            background-color: #f7ecc9;
        }

        .trf-tbl th.trf-w-inap,
        .trf-tbl th.trf-w-inap-a {
            background-color: #dde8f4;
        }

        .trf-tbl th.trf-w-inap-b {
            background-color: #e9e2f6;
        }

        /* Garis tebal di kolom pembuka tiap kelompok (tarif poli & tiap kelas). */
        .trf-tbl .trf-batas {
            border-left: 1.6px solid #4b5563;
        }

        .trf-kanan {
            text-align: right;
        }

        .trf-tengah {
            text-align: center;
        }

        .trf-kelas {
            font-size: 7px;
            color: #4b5563;
        }

        /* Tarif kelas yang tidak punya baris sendiri di master — mengikuti tarif poli.
           Dibedakan abu-abu supaya pembaca tahu itu bukan tarif khusus kelas. */
        .trf-ikut {
            color: #9ca3af;
        }

        .trf-note {
            font-size: 8px;
            color: #555;
            font-style: italic;
            margin-top: 4px;
        }

        .trf-footer {
            margin-top: 4px;
            border-top: 1px solid #9aa89e;
            width: 100%;
            border-collapse: collapse;
        }

        .trf-footer td {
            font-size: 7.5px;
            color: #555;
            padding-top: 3px;
        }
    </style>
</head>

<body>
    <div class="trf-isi">
        @foreach ($paket as $paketKategori)
            @php $keterangan = \App\Support\Downtime\TarifDowntime::KATEGORI[$paketKategori['kategori']] ?? []; @endphp

            <div class="{{ $loop->first ? '' : 'trf-break' }}">

                <x-logo.identitas :showGaris="false" />

                {{-- KODE + JUDUL --}}
                <table style="width:100%; border-collapse:collapse; margin-top:8px;">
                    <tr>
                        <td style="width:190px; vertical-align:top;">
                            <table class="trf-kode">
                                <tr>
                                    <td style="width:44%; background-color:#fafbfa;">Kode</td>
                                    <td style="font-weight:bold;">DT-TARIF</td>
                                </tr>
                                <tr>
                                    <td style="background-color:#fafbfa;">Dicetak</td>
                                    <td>{{ $tglCetak }}</td>
                                </tr>
                            </table>
                        </td>
                        <td style="vertical-align:middle; padding-left:10px;">
                            <div class="trf-judul">Daftar Tarif Down Time &mdash; {{ $paketKategori['label'] }}</div>
                            <div class="trf-subjudul">{{ $judul }}</div>
                        </td>
                    </tr>
                </table>

                <div class="trf-pita">
                    Acuan nominal saat SIMRS tidak dapat diakses &mdash; tarif dapat berubah, cetak ulang secara berkala
                </div>

                <div class="trf-info">
                    {{ $keterangan['desc'] ?? '' }}
                    <br>
                    <strong>{{ number_format($paketKategori['jumlah'], 0, ',', '.') }}</strong> baris &middot;
                    sumber master: {{ $keterangan['sumber'] ?? '-' }}
                    @if (filled($paketKategori['kataKunci']))
                        &middot; disaring dengan kata kunci &ldquo;{{ $paketKategori['kataKunci'] }}&rdquo;
                    @endif
                    &middot; dicetak {{ $tglCetak }} oleh {{ $dicetakOleh }}
                    &middot; <strong>seluruh nominal dalam rupiah</strong>
                </div>

                @if ($paketKategori['terpotong'])
                    <div class="trf-potong">
                        Daftar dipotong pada
                        {{ number_format(\App\Support\Downtime\TarifDowntime::MAKS_CETAK, 0, ',', '.') }}
                        baris pertama dari {{ number_format($paketKategori['jumlah'], 0, ',', '.') }} baris.
                        Persempit dengan kata kunci pencarian lalu cetak per bagian agar lengkap.
                    </div>
                @endif

                {{-- TABEL TARIF --}}
                @php $header = \App\Support\Downtime\TarifDowntime::headerKolom($paketKategori['kategori']); @endphp
                <table class="trf-tbl {{ $header['tingkat'] >= 3 ? 'trf-tbl-padat' : '' }}" style="margin-top:4px;">
                    <thead>
                        <tr>
                            <th style="width:5%;" rowspan="{{ $header['tingkat'] }}">No</th>
                            @foreach ($header['atas'] as $judulKolom)
                                <th colspan="{{ $judulKolom['colspan'] ?? 1 }}" rowspan="{{ $judulKolom['rowspan'] ?? 1 }}"
                                    class="{{ filled($judulKolom['warna'] ?? null) ? 'trf-w-' . $judulKolom['warna'] : '' }} {{ ($judulKolom['batas'] ?? false) ? 'trf-batas' : '' }}"
                                    @if (filled($judulKolom['lebar'] ?? null)) style="width:{{ $judulKolom['lebar'] }};" @endif>
                                    {{ $judulKolom['label'] }}
                                </th>
                            @endforeach
                        </tr>
                        @if ($header['tingkat'] >= 3)
                            <tr>
                                @foreach ($header['tengah'] as $judulKolom)
                                    <th colspan="{{ $judulKolom['colspan'] ?? 1 }}"
                                        class="{{ filled($judulKolom['warna'] ?? null) ? 'trf-w-' . $judulKolom['warna'] : '' }} {{ ($judulKolom['batas'] ?? false) ? 'trf-batas' : '' }}">
                                        {{ $judulKolom['label'] }}</th>
                                @endforeach
                            </tr>
                        @endif
                        @if ($header['berlapis'])
                            <tr>
                                @foreach ($header['bawah'] as $judulKolom)
                                    <th class="{{ filled($judulKolom['warna'] ?? null) ? 'trf-w-' . $judulKolom['warna'] : '' }} {{ ($judulKolom['batas'] ?? false) ? 'trf-batas' : '' }}"
                                        @if (filled($judulKolom['lebar'] ?? null)) style="width:{{ $judulKolom['lebar'] }};" @endif>
                                        {{ $judulKolom['label'] }}
                                    </th>
                                @endforeach
                            </tr>
                        @endif
                    </thead>
                    <tbody>
                        @forelse ($paketKategori['baris'] as $baris)
                            <tr>
                                <td class="trf-tengah">{{ $loop->iteration }}</td>
                                @foreach ($paketKategori['kolom'] as $kolom)
                                    <td
                                        class="{{ ($kolom['rata'] ?? 'kiri') === 'kanan' ? 'trf-kanan' : '' }} {{ filled($kolom['warna'] ?? null) ? 'trf-w-' . $kolom['warna'] : '' }} {{ ($kolom['batas'] ?? false) ? 'trf-batas' : '' }}">
                                        @if (($kolom['tipe'] ?? '') === 'tarifKelas')
                                            @php $tarif = $baris[$kolom['key']] ?? []; @endphp
                                            <span
                                                class="{{ ($tarif['asal'] ?? 'poli') === 'kelas' ? '' : 'trf-ikut' }}">{{ \App\Support\Downtime\TarifDowntime::angka($tarif['harga'] ?? 0) }}</span>
                                        @elseif ($kolom['uang'] ?? false)
                                            {{ \App\Support\Downtime\TarifDowntime::angka($baris[$kolom['key']] ?? 0) }}
                                        @else
                                            {{ $baris[$kolom['key']] ?? '-' }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($paketKategori['kolom']) + 1 }}" class="trf-tengah" style="padding:10px;">
                                    Tidak ada data tarif pada kategori ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="trf-note">
                    Tanda &ldquo;&mdash;&rdquo; berarti tarif belum diisi di master &mdash; konfirmasi ke bagian
                    administrasi sebelum menuliskan nominal pada formulir manual.
                    @if ($header['berlapis'])
                        Kolom kelas yang tercetak abu-abu berarti kelas itu belum punya tarif sendiri sehingga
                        mengikuti tarif poli.
                    @endif
                </div>

                <table class="trf-footer">
                    <tr>
                        <td style="width:62%;">
                            DT-TARIF &mdash; Daftar Tarif Down Time &middot; {{ $paketKategori['label'] }}
                        </td>
                        <td style="width:38%;" class="trf-kanan">Dicetak {{ $tglCetak }}</td>
                    </tr>
                </table>

            </div>
        @endforeach
    </div>
</body>

</html>
