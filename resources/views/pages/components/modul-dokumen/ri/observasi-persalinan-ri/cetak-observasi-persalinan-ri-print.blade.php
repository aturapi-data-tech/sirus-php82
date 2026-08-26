{{-- resources/views/pages/components/modul-dokumen/ri/observasi-persalinan-ri/cetak-observasi-persalinan-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="OBSERVASI PERSALINAN">

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
        $diagnosa = $data['diagnosa'] ?? '';
        $nilaiSel = fn($nilai) => filled($nilai) ? e($nilai) : '-';
    @endphp

    @if (filled($diagnosa))
        <div class="text-[10px] mt-1.5 mb-1"><strong>Diagnosa:</strong> {{ e($diagnosa) }}</div>
    @endif

    <table class="w-full border-collapse text-[9px]">
        <thead>
            <tr>
                <th class="w-[8%] bg-[#eef2ee] font-bold border border-[#999] px-1 py-[3px] align-top text-center">Jam</th>
                <th class="w-[10%] bg-[#eef2ee] font-bold border border-[#999] px-1 py-[3px] align-top text-center">TD (mmHg)</th>
                <th class="w-[7%] bg-[#eef2ee] font-bold border border-[#999] px-1 py-[3px] align-top text-center">N (x/mnt)</th>
                <th class="w-[7%] bg-[#eef2ee] font-bold border border-[#999] px-1 py-[3px] align-top text-center">RR (x/mnt)</th>
                <th class="w-[7%] bg-[#eef2ee] font-bold border border-[#999] px-1 py-[3px] align-top text-center">S (&deg;C)</th>
                <th class="w-[8%] bg-[#eef2ee] font-bold border border-[#999] px-1 py-[3px] align-top text-center">DJJ (x/mnt)</th>
                <th class="w-[11%] bg-[#eef2ee] font-bold border border-[#999] px-1 py-[3px] align-top text-center">His</th>
                <th class="w-[6%] bg-[#eef2ee] font-bold border border-[#999] px-1 py-[3px] align-top text-center">EWS</th>
                <th class="w-[19%] bg-[#eef2ee] font-bold border border-[#999] px-1 py-[3px] align-top text-center">Obat / Keterangan</th>
                <th class="w-[12%] bg-[#eef2ee] font-bold border border-[#999] px-1 py-[3px] align-top text-center">Petugas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $baris)
                <tr>
                    <td class="border border-[#999] px-1 py-[3px] align-top text-center">{{ $nilaiSel($baris['jam'] ?? null) }}</td>
                    <td class="border border-[#999] px-1 py-[3px] align-top text-center">{{ $nilaiSel(filled($baris['sistolik'] ?? null) || filled($baris['diastolik'] ?? null) ? ($baris['sistolik'] ?? '-') . '/' . ($baris['diastolik'] ?? '-') : ($baris['td'] ?? null)) }}</td>
                    <td class="border border-[#999] px-1 py-[3px] align-top text-center">{{ $nilaiSel($baris['nadi'] ?? null) }}</td>
                    <td class="border border-[#999] px-1 py-[3px] align-top text-center">{{ $nilaiSel($baris['rr'] ?? null) }}</td>
                    <td class="border border-[#999] px-1 py-[3px] align-top text-center">{{ $nilaiSel($baris['suhu'] ?? null) }}</td>
                    <td class="border border-[#999] px-1 py-[3px] align-top text-center">{{ $nilaiSel($baris['djj'] ?? null) }}</td>
                    <td class="border border-[#999] px-1 py-[3px] align-top text-center">{{ $nilaiSel($baris['his'] ?? null) }}</td>
                    <td class="border border-[#999] px-1 py-[3px] align-top text-center">{{ $nilaiSel($baris['ewsScore'] ?? null) }}</td>
                    <td class="border border-[#999] px-1 py-[3px] align-top text-left">{{ $nilaiSel($baris['obatKeterangan'] ?? null) }}</td>
                    <td class="border border-[#999] px-1 py-[3px] align-top text-center">{{ $nilaiSel($baris['petugas'] ?? null) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="border border-[#999] px-1 py-[3px] align-top text-center">Belum ada baris observasi.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="font-size:8px; margin-top:4px; color:#555;">
        Keterangan: TD = Tekanan Darah, N = Nadi, RR = Respirasi, S = Suhu, DJJ = Denyut Jantung Janin,
        EWS = Maternal Early Warning Score.
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
