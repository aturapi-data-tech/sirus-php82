{{-- resources/views/pages/components/modul-dokumen/ri/catatan-terapi-neonatal-ri/cetak-catatan-terapi-neonatal-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="CATATAN TERAPI & PERENCANAAN KEPERAWATAN NEONATAL">

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
        $terapiDokter = $data['terapiDokter'] ?? [];
        $perencanaan = $data['perencanaan'] ?? [];
        $nilaiSel = fn($nilai) => filled($nilai) ? e($nilai) : '-';
    @endphp

    {{-- A. Terapi Dokter --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-2.5">A. CATATAN TERAPI DOKTER</div>
    @if (count($terapiDokter) > 0)
        <table class="w-full border-collapse text-[10px] mt-0.5">
            <thead>
                <tr>
                    <th class="w-[5%] bg-[#f7f7f7] text-left border border-[#999] px-[5px] py-[3px] align-top">No</th>
                    <th class="w-[20%] bg-[#f7f7f7] text-left border border-[#999] px-[5px] py-[3px] align-top">Tgl &amp; Jam</th>
                    <th class="w-[52%] bg-[#f7f7f7] text-left border border-[#999] px-[5px] py-[3px] align-top">Penatalaksanaan / Terapi</th>
                    <th class="w-[23%] bg-[#f7f7f7] text-left border border-[#999] px-[5px] py-[3px] align-top">ICD 9 CM</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($terapiDokter as $nomor => $entri)
                    <tr>
                        <td class="text-center border border-[#999] px-[5px] py-[3px] align-top">{{ $nomor + 1 }}</td>
                        <td class="border border-[#999] px-[5px] py-[3px] align-top">{{ $nilaiSel($entri['tglJam'] ?? ($entri['createdAt'] ?? '')) }}</td>
                        <td class="border border-[#999] px-[5px] py-[3px] align-top">{{ $nilaiSel($entri['keterangan'] ?? '') }}</td>
                        <td class="border border-[#999] px-[5px] py-[3px] align-top">{{ $nilaiSel($entri['icd9'] ?? '') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="text-[10px] text-[#666] px-0.5 py-1">Tidak ada catatan terapi dokter.</div>
    @endif

    {{-- B. Perencanaan Keperawatan --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-2.5">B. PERENCANAAN KEPERAWATAN</div>
    @if (count($perencanaan) > 0)
        <table class="w-full border-collapse text-[10px] mt-0.5">
            <thead>
                <tr>
                    <th class="w-[5%] bg-[#f7f7f7] text-left border border-[#999] px-[5px] py-[3px] align-top">No</th>
                    <th class="w-[20%] bg-[#f7f7f7] text-left border border-[#999] px-[5px] py-[3px] align-top">Jam &amp; Tgl</th>
                    <th class="w-[52%] bg-[#f7f7f7] text-left border border-[#999] px-[5px] py-[3px] align-top">Perencanaan &amp; Tindakan</th>
                    <th class="w-[23%] bg-[#f7f7f7] text-left border border-[#999] px-[5px] py-[3px] align-top">Nama</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($perencanaan as $nomor => $entri)
                    <tr>
                        <td class="text-center border border-[#999] px-[5px] py-[3px] align-top">{{ $nomor + 1 }}</td>
                        <td class="border border-[#999] px-[5px] py-[3px] align-top">{{ $nilaiSel($entri['tglJam'] ?? ($entri['createdAt'] ?? '')) }}</td>
                        <td class="border border-[#999] px-[5px] py-[3px] align-top">{{ $nilaiSel($entri['keterangan'] ?? '') }}</td>
                        <td class="border border-[#999] px-[5px] py-[3px] align-top">{{ $nilaiSel($entri['ttd'] ?? '') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="text-[10px] text-[#666] px-0.5 py-1">Tidak ada perencanaan keperawatan.</div>
    @endif

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:18px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $data['tglCetak'] ?? '' }}<br>
                Petugas<br>
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
