{{-- resources/views/pages/components/modul-dokumen/r-i/observasi-nifas-ri/cetak-observasi-nifas-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="LEMBAR OBSERVASI NIFAS">

    {{-- ── IDENTITAS PASIEN ── --}}
    <x-slot name="patientData">
        @php
            $identitas = $data['identitas'] ?? [];
            $alamatPasien = trim(
                ($identitas['alamat'] ?? '-') .
                    (!empty($identitas['rt']) ? ' RT ' . $identitas['rt'] : '') .
                    (!empty($identitas['rw']) ? '/RW ' . $identitas['rw'] : '') .
                    (!empty($identitas['desaName']) ? ', ' . $identitas['desaName'] : '') .
                    (!empty($identitas['kecamatanName']) ? ', ' . $identitas['kecamatanName'] : ''),
            );
        @endphp
        <x-pdf.identitas-pasien
            :rm="$data['regNo'] ?? null"
            :nama="$data['regName'] ?? null"
            :jenisKelamin="$data['jenisKelamin']['jenisKelaminDesc'] ?? null"
            :tempatLahir="$data['tempatLahir'] ?? null"
            :tglLahir="$data['tglLahir'] ?? null"
            :umur="$data['thn'] ?? null"
            :alamat="$alamatPasien" />
    </x-slot>

    @php
        $rows = $data['rows'] ?? [];
        $nilaiSel = fn($nilai) => filled($nilai) ? e($nilai) : '-';
        $nilaiLochia = function (array $baris) {
            $lochia = trim(($baris['lochiaJenis'] ?? '') . ' ' . ($baris['lochiaJumlah'] ?? ''));
            return $lochia !== '' ? e($lochia) : '-';
        };
    @endphp

    <style>
        table.on { width:100%; border-collapse:collapse; font-size:8px; }
        table.on th, table.on td { border:1px solid #999; padding:2px 3px; vertical-align:top; text-align:center; }
        table.on th { background:#eef2ee; font-weight:bold; }
        table.on td.ket { text-align:left; }
    </style>

    <table class="on">
        <thead>
            <tr>
                <th style="width:9%;">Tgl / Jam</th>
                <th style="width:6%;">TD</th>
                <th style="width:4%;">N</th>
                <th style="width:4%;">RR</th>
                <th style="width:4%;">S</th>
                <th style="width:4%;">EWS</th>
                <th style="width:8%;">TFU</th>
                <th style="width:6%;">Kontraksi</th>
                <th style="width:8%;">Lochia</th>
                <th style="width:4%;">Drh (cc)</th>
                <th style="width:6%;">Luka</th>
                <th style="width:5%;">Laktasi</th>
                <th style="width:4%;">ASI</th>
                <th style="width:18%;">Keterangan</th>
                <th style="width:10%;">Petugas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $baris)
                @php
                    $keterangan = trim(
                        (filled($baris['keluhan'] ?? null) ? 'Keluhan: ' . $baris['keluhan'] . '. ' : '') .
                        (filled($baris['asuhanTindakan'] ?? null) ? $baris['asuhanTindakan'] : '')
                    );
                @endphp
                <tr>
                    <td>{{ $nilaiSel($baris['tglJam'] ?? null) }}</td>
                    <td>{{ $nilaiSel(filled($baris['sistolik'] ?? null) || filled($baris['diastolik'] ?? null) ? ($baris['sistolik'] ?? '-') . '/' . ($baris['diastolik'] ?? '-') : ($baris['td'] ?? null)) }}</td>
                    <td>{{ $nilaiSel($baris['nadi'] ?? null) }}</td>
                    <td>{{ $nilaiSel($baris['rr'] ?? null) }}</td>
                    <td>{{ $nilaiSel($baris['suhu'] ?? null) }}</td>
                    <td>{{ $nilaiSel($baris['ewsScore'] ?? null) }}</td>
                    <td>{{ $nilaiSel($baris['tfu'] ?? null) }}</td>
                    <td>{{ $nilaiSel($baris['kontraksiUterus'] ?? null) }}</td>
                    <td>{{ $nilaiLochia($baris) }}</td>
                    <td>{{ $nilaiSel($baris['perdarahanCc'] ?? null) }}</td>
                    <td>{{ $nilaiSel($baris['lukaJalanLahir'] ?? null) }}</td>
                    <td>{{ $nilaiSel($baris['laktasi'] ?? null) }}</td>
                    <td>{{ $nilaiSel($baris['asiEksklusif'] ?? null) }}</td>
                    <td class="ket">{{ $keterangan !== '' ? e($keterangan) : '-' }}</td>
                    <td>{{ $nilaiSel($baris['petugas'] ?? null) }}</td>
                </tr>
            @empty
                <tr><td colspan="15">Belum ada baris observasi nifas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="font-size:8px; margin-top:4px; color:#555;">
        Keterangan: TD = Tekanan Darah, N = Nadi, RR = Respirasi, S = Suhu, EWS = Maternal Early Warning Score,
        TFU = Tinggi Fundus Uteri, Drh = Perdarahan, ASI = ASI Eksklusif. Lochia: Rubra / Sanguinolenta / Serosa / Alba.
    </div>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $data['ttdDate'] ?? ($data['tglCetak'] ?? '') }}<br>
                {{ $data['ttd'] ?? 'Bidan / Perawat' }}<br>
                @if (!empty($data['ttdPath']))
                    <img src="{{ $data['ttdPath'] }}" style="height:44px; margin:4px 0;" alt="Tanda Tangan"><br>
                @else
                    <br><br><br>
                @endif
                <span style="border-top:1px solid #000; padding:0 30px;">Tanda Tangan &amp; Nama Terang</span>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
