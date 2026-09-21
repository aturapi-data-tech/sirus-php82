{{-- resources/views/pages/components/modul-dokumen/ri/kriteria-robson-ri/cetak-kriteria-robson-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background kode="RM-05.11 · Rev.0" title="KRITERIA ROBSON">

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
        $opsiLabel = $data['opsiLabel'] ?? [];

        $kelompok = (string) ($form['kelompok'] ?? '');
        $subKelompok = (string) ($form['subKelompok'] ?? '');

        $nilaiOpsi = fn(string $field) => $opsiLabel[$field][$form[$field] ?? ''] ?? '-';
        $usiaKehamilan = filled($form['usiaKehamilanMinggu'] ?? '') ? $form['usiaKehamilanMinggu'] . ' minggu' : '-';
    @endphp

    {{-- 1. Data Persalinan & Variabel Obstetri --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">1. DATA PERSALINAN &amp; VARIABEL OBSTETRI</div>
    <table class="w-full border-collapse text-[10px]">
        <tr>
            <td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Tgl / Jam Persalinan</td>
            <td class="w-[28%] border border-[#999] px-[5px] py-[2px] align-top">{{ ($form['tglPersalinan'] ?? '') ?: '-' }}</td>
            <td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Usia Kehamilan</td>
            <td class="w-[28%] border border-[#999] px-[5px] py-[2px] align-top">{{ $usiaKehamilan }}</td>
        </tr>
        <tr>
            <td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Paritas</td>
            <td class="w-[28%] border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiOpsi('paritas') }}</td>
            <td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Riwayat Sectio Caesarea</td>
            <td class="w-[28%] border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiOpsi('riwayatSc') }}</td>
        </tr>
        <tr>
            <td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Jumlah Janin</td>
            <td class="w-[28%] border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiOpsi('jumlahJanin') }}</td>
            <td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Presentasi / Letak Janin</td>
            <td class="w-[28%] border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiOpsi('presentasiJanin') }}</td>
        </tr>
        <tr>
            <td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Awal Persalinan</td>
            <td class="w-[28%] border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiOpsi('awalPersalinan') }}</td>
            <td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Cara Persalinan</td>
            <td class="w-[28%] border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiOpsi('caraPersalinan') }}</td>
        </tr>
    </table>

    {{-- 2. Kelompok Robson — seluruh 10 kelompok dicetak, kelompok pasien diberi tanda --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">2. KELOMPOK ROBSON (KLASIFIKASI 10 KELOMPOK WHO)</div>
    <table class="w-full border-collapse text-[10px]">
        <thead>
            <tr>
                <th class="w-[9%] text-center bg-[#f0f0f0] border border-[#999] px-[5px] py-[2px] align-top">Kelompok</th>
                <th class="bg-[#f0f0f0] border border-[#999] px-[5px] py-[2px] align-top">Kriteria</th>
                <th class="w-[9%] text-center bg-[#f0f0f0] border border-[#999] px-[5px] py-[2px] align-top">Pasien</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($opsiLabel['kelompok'] ?? [] as $kelompokKode => $kelompokLabel)
                @php $terpilih = (string) $kelompokKode === $kelompok; @endphp
                <tr>
                    <td class="w-[9%] text-center border border-[#999] px-[5px] py-[2px] align-top {{ $terpilih ? 'font-bold bg-[#eef2ee]' : '' }}">{{ $kelompokKode }}</td>
                    <td class="border border-[#999] px-[5px] py-[2px] align-top {{ $terpilih ? 'font-bold bg-[#eef2ee]' : '' }}">{{ $kelompokLabel }}</td>
                    <td class="w-[9%] text-center border border-[#999] px-[5px] py-[2px] align-top {{ $terpilih ? 'font-bold bg-[#eef2ee]' : '' }}">{{ $terpilih ? 'X' : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="w-full border-collapse text-[10px] mt-1.5">
        <tr>
            <td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Kelompok Pasien</td>
            <td class="border border-[#999] px-[5px] py-[2px] align-top font-bold">
                {{ $kelompok !== '' ? 'Kelompok ' . $kelompok : '-' }}
                @if ($subKelompok !== '')
                    — sub-kelompok {{ $subKelompok }} ({{ $opsiLabel['subKelompok'][$subKelompok] ?? '-' }})
                @endif
            </td>
        </tr>
        <tr>
            <td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Catatan</td>
            <td class="border border-[#999] px-[5px] py-[2px] align-top">{!! nl2br(e(($form['catatan'] ?? '') ?: '-')) !!}</td>
        </tr>
    </table>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ ($form['ttdDate'] ?? '') ?: ($data['tglCetak'] ?? '') }}<br>
                Petugas<br>
                @if (!empty($data['ttdPath']))
                    <img src="{{ $data['ttdPath'] }}" style="height:44px; margin:4px 0;" alt="Tanda Tangan"><br>
                @else
                    <br><br><br>
                @endif
                <span style="border-top:1px solid #000; padding:0 30px;">{{ ($form['ttd'] ?? '') ?: '(Tanda Tangan & Nama Terang)' }}</span>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
