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

    {{-- Header G-P-A --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">STATUS OBSTETRI</div>
    <table class="w-full border-collapse text-[10px] mt-1">
        <tr>
            <td class="w-[16%] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top"><b>Gravida (G)</b></td><td class="w-[17%] border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('gravida') }}</td>
            <td class="w-[16%] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top"><b>Para (P)</b></td><td class="w-[17%] border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('para') }}</td>
            <td class="w-[16%] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top"><b>Abortus (A)</b></td><td class="w-[18%] border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('abortus') }}</td>
        </tr>
    </table>

    {{-- Tabel Riwayat Kehamilan Lalu --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">RIWAYAT KEHAMILAN / PERSALINAN YANG LALU</div>
    <table class="w-full border-collapse text-[10px] mt-1">
        <thead>
            <tr>
                <th class="w-[3%] bg-[#f0f4f0] text-center border border-[#999] px-[5px] py-[2px] align-top">No</th>
                <th class="w-[8%] bg-[#f0f4f0] text-center border border-[#999] px-[5px] py-[2px] align-top">Kehamilan</th>
                <th class="w-[8%] bg-[#f0f4f0] text-center border border-[#999] px-[5px] py-[2px] align-top">Cara</th>
                <th class="w-[7%] bg-[#f0f4f0] text-center border border-[#999] px-[5px] py-[2px] align-top">Tempat</th>
                <th class="w-[8%] bg-[#f0f4f0] text-center border border-[#999] px-[5px] py-[2px] align-top">Penolong</th>
                <th class="w-[13%] bg-[#f0f4f0] text-center border border-[#999] px-[5px] py-[2px] align-top">Komplikasi</th>
                <th class="w-[5%] bg-[#f0f4f0] text-center border border-[#999] px-[5px] py-[2px] align-top">JK</th>
                <th class="w-[7%] bg-[#f0f4f0] text-center border border-[#999] px-[5px] py-[2px] align-top">Keadaan</th>
                <th class="w-[7%] bg-[#f0f4f0] text-center border border-[#999] px-[5px] py-[2px] align-top">Umur</th>
                <th class="w-[7%] bg-[#f0f4f0] text-center border border-[#999] px-[5px] py-[2px] align-top">BBL (gr)</th>
                <th class="w-[14%] bg-[#f0f4f0] text-center border border-[#999] px-[5px] py-[2px] align-top">Keterangan</th>
                <th class="w-[13%] bg-[#f0f4f0] text-center border border-[#999] px-[5px] py-[2px] align-top">Petugas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $nomor => $baris)
                <tr>
                    <td class="text-center border border-[#999] px-[5px] py-[2px] align-top">{{ $nomor + 1 }}</td>
                    <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'kehamilan') }}</td>
                    <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'caraPersalinan') }}</td>
                    <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'tempat') }}</td>
                    <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'penolong') }}</td>
                    <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'komplikasi') }}</td>
                    <td class="text-center border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'jenisKelaminAnak') }}</td>
                    <td class="text-center border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'keadaanAnak') }}</td>
                    <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'umurAnak') }}</td>
                    <td class="text-center border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'bbl') }}</td>
                    <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'keterangan') }}</td>
                    <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'petugas') }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center border border-[#999] px-[5px] py-[2px] align-top">Tidak ada riwayat kehamilan sebelumnya.</td></tr>
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
