{{-- resources/views/pages/components/modul-dokumen/r-i/indikator-sc-ri/cetak-indikator-sc-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="INDIKATOR PROSES SC">

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
        $indikatorPertanyaan = $data['indikatorPertanyaan'] ?? [];
        $klasifikasiOptions = $data['klasifikasiOptions'] ?? [];
        $indikator = $form['indikator'] ?? [];

        $klasifikasiKode = $form['diagnosisKlasifikasi'] ?? '';
        $klasifikasiLabel = $klasifikasiKode !== '' ? ($klasifikasiOptions[$klasifikasiKode] ?? '') : '';

        $indikasi = collect($form['indikasiSc'] ?? [])->filter()->implode(', ');
        if (filled($form['indikasiScLain'] ?? null)) {
            $indikasi = trim($indikasi . ($indikasi ? ', ' : '') . e($form['indikasiScLain']));
        }
    @endphp

    {{-- 1. Indikator Proses SC --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">1. INDIKATOR PROSES SC</div>
    <table class="w-full border-collapse text-[10px]">
        <thead>
            <tr>
                <th class="w-[5%] text-center bg-[#f0f0f0] border border-[#999] px-[5px] py-[2px] align-top">No</th>
                <th class="bg-[#f0f0f0] border border-[#999] px-[5px] py-[2px] align-top">Pertanyaan</th>
                <th class="w-[9%] text-center bg-[#f0f0f0] border border-[#999] px-[5px] py-[2px] align-top">Ya</th>
                <th class="w-[9%] text-center bg-[#f0f0f0] border border-[#999] px-[5px] py-[2px] align-top">Tidak</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($indikatorPertanyaan as $nomor => $pertanyaan)
                @php $nilai = $indikator[$nomor] ?? ''; @endphp
                <tr>
                    <td class="w-[5%] text-center border border-[#999] px-[5px] py-[2px] align-top">{{ $nomor + 1 }}</td>
                    <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $pertanyaan }}</td>
                    <td class="w-[9%] text-center border border-[#999] px-[5px] py-[2px] align-top">{{ $nilai === 'Ya' ? 'X' : '' }}</td>
                    <td class="w-[9%] text-center border border-[#999] px-[5px] py-[2px] align-top">{{ $nilai === 'Tidak' ? 'X' : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 2. Klasifikasi Diagnosis --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">2. KLASIFIKASI DIAGNOSIS (ROBSON)</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Klasifikasi Terpilih</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $klasifikasiKode !== '' ? strtoupper($klasifikasiKode) . '. ' . e($klasifikasiLabel) : '-' }}</td></tr>
    </table>

    {{-- 3. Indikasi SC --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">3. INDIKASI SC</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Indikasi</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $indikasi ?: '-' }}</td></tr>
    </table>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $form['ttdDate'] ?? ($data['tglCetak'] ?? '') }}<br>
                Dokter<br>
                @if (!empty($data['ttdPath']))
                    <img src="{{ $data['ttdPath'] }}" style="height:44px; margin:4px 0;" alt="Tanda Tangan"><br>
                @else
                    <br><br><br>
                @endif
                <span style="border-top:1px solid #000; padding:0 30px;">{{ $form['ttd'] ?? '(Tanda Tangan &amp; Nama Terang)' }}</span>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
