{{-- resources/views/pages/components/modul-dokumen/r-i/riwayat-obstetri-ri/cetak-riwayat-obstetri-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="RIWAYAT OBSTETRI">

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
        $form = $data['form'] ?? [];
        $rows = $form['rows'] ?? [];
        $nilaiForm = fn(string $field) => filled($form[$field] ?? null) ? e($form[$field]) : '-';
        $nilaiBaris = fn(array $baris, string $field) => filled($baris[$field] ?? null) ? e($baris[$field]) : '-';
    @endphp

    <style>
        .ro-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:6px; }
        table.ro { width:100%; border-collapse:collapse; font-size:10px; margin-top:4px; }
        table.ro th, table.ro td { border:1px solid #999; padding:2px 5px; vertical-align:top; }
        table.ro th { background:#f0f4f0; text-align:center; }
        table.ro td.c { text-align:center; }
    </style>

    {{-- Header G-P-A --}}
    <div class="ro-sec">STATUS OBSTETRI</div>
    <table class="ro">
        <tr>
            <td style="width:16%; background:#f7f7f7;"><b>Gravida (G)</b></td><td style="width:17%;">{{ $nilaiForm('gravida') }}</td>
            <td style="width:16%; background:#f7f7f7;"><b>Para (P)</b></td><td style="width:17%;">{{ $nilaiForm('para') }}</td>
            <td style="width:16%; background:#f7f7f7;"><b>Abortus (A)</b></td><td style="width:18%;">{{ $nilaiForm('abortus') }}</td>
        </tr>
    </table>

    {{-- Tabel Riwayat Kehamilan Lalu --}}
    <div class="ro-sec">RIWAYAT KEHAMILAN / PERSALINAN YANG LALU</div>
    <table class="ro">
        <thead>
            <tr>
                <th style="width:3%;">No</th>
                <th style="width:8%;">Kehamilan</th>
                <th style="width:8%;">Cara</th>
                <th style="width:7%;">Tempat</th>
                <th style="width:8%;">Penolong</th>
                <th style="width:13%;">Komplikasi</th>
                <th style="width:5%;">JK</th>
                <th style="width:7%;">Keadaan</th>
                <th style="width:7%;">Umur</th>
                <th style="width:7%;">BBL (gr)</th>
                <th style="width:14%;">Keterangan</th>
                <th style="width:13%;">Petugas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $nomor => $baris)
                <tr>
                    <td class="c">{{ $nomor + 1 }}</td>
                    <td>{{ $nilaiBaris($baris, 'kehamilan') }}</td>
                    <td>{{ $nilaiBaris($baris, 'caraPersalinan') }}</td>
                    <td>{{ $nilaiBaris($baris, 'tempat') }}</td>
                    <td>{{ $nilaiBaris($baris, 'penolong') }}</td>
                    <td>{{ $nilaiBaris($baris, 'komplikasi') }}</td>
                    <td class="c">{{ $nilaiBaris($baris, 'jenisKelaminAnak') }}</td>
                    <td class="c">{{ $nilaiBaris($baris, 'keadaanAnak') }}</td>
                    <td>{{ $nilaiBaris($baris, 'umurAnak') }}</td>
                    <td class="c">{{ $nilaiBaris($baris, 'bbl') }}</td>
                    <td>{{ $nilaiBaris($baris, 'keterangan') }}</td>
                    <td>{{ $nilaiBaris($baris, 'petugas') }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="c">Tidak ada riwayat kehamilan sebelumnya.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $form['ttdDate'] ?? ($data['tglCetak'] ?? '') }}<br>
                {{ $form['ttd'] ?? 'Bidan/Dokter' }}<br>
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
