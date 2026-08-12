{{-- resources/views/pages/components/modul-dokumen/r-i/pengkajian-neonatal-perawat-ri/cetak-pengkajian-neonatal-perawat-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="PENGKAJIAN KEPERAWATAN NEONATAL">

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
        $nilaiForm = fn(string $field) => filled($form[$field] ?? null) ? e($form[$field]) : '-';
        $nilaiFormList = fn(string $field) => collect($form[$field] ?? [])->filter()->implode(', ') ?: '-';
    @endphp

    {{-- 1. Riwayat Penyakit --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">1. RIWAYAT PENYAKIT</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Keluhan Utama</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('keluhanUtama') }}</td></tr>
    </table>

    {{-- 2. Antenatal --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">2. ANTENATAL</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">ANC</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('anc') }}{{ filled($form['ancTempat'] ?? null) ? ' — ' . e($form['ancTempat']) : '' }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">TT</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('tt') }}{{ filled($form['ttKali'] ?? null) ? ' (' . e($form['ttKali']) . ' kali)' : '' }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Penyulit Kehamilan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiFormList('penyulitKehamilan') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Penyakit Menyertai</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiFormList('penyakitMenyertai') }}</td></tr>
    </table>

    {{-- 3. Intranatal --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">3. INTRANATAL</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Umur Kehamilan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('umurKehamilan') }} minggu</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Kondisi Kelahiran</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('kondisiKelahiran') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Jenis Persalinan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('jenisPersalinan') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Penolong</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('penolong') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Penyulit Persalinan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiFormList('penyulitPersalinan') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Komplikasi</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiFormList('komplikasi') }}{{ filled($form['kpdLamaJam'] ?? null) ? ' (KPD ' . e($form['kpdLamaJam']) . ' jam)' : '' }}</td></tr>
    </table>

    {{-- 4. Postnatal — Antropometri --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">4. POSTNATAL — ANTROPOMETRI</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">BBL / PB</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('bbl') }} gr / {{ $nilaiForm('pb') }} cm</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">LK / LD</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('lk') }} / {{ $nilaiForm('ld') }} cm</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">LILA / Lingkar Perut</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('lila') }} / {{ $nilaiForm('lingkarPerut') }} cm</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">APGAR (1'/5')</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('apgar1') }} / {{ $nilaiForm('apgar5') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Trauma Lahir</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('traumaLahir') }}{{ filled($form['traumaKet'] ?? null) ? ' — ' . e($form['traumaKet']) : '' }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Usaha Nafas</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('usahaNafas') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Imunisasi</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('imunisasi') }}{{ filled($form['imunisasiKet'] ?? null) ? ' — ' . e($form['imunisasiKet']) : '' }}</td></tr>
    </table>

    {{-- 5. Pemeriksaan Fisik --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">5. PEMERIKSAAN FISIK</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Kepala</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('kepalaBentuk') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Mata (Konjungtiva/Sklera)</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('mataKonjungtiva') }} / {{ $nilaiForm('mataSklera') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Telinga / Hidung</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('telinga') }} / {{ $nilaiForm('hidung') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Mulut (Reflek Isap/Bentuk)</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('mulutReflekIsap') }} / {{ $nilaiForm('mulutBentuk') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Dada / Perut</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('dada') }} / {{ $nilaiForm('perutBentuk') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Tali Pusat</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('taliPusat') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Anus / Ekstremitas</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('anus') }} / {{ $nilaiForm('ekstremitas') }}</td></tr>
    </table>

    {{-- 6. Review Sistem --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">6. REVIEW SISTEM (B1–B6)</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">B1 Pernafasan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('b1Pernafasan') }}, RR {{ $nilaiForm('b1FrekuensiNafas') }} x/mnt, {{ $nilaiFormList('b1SuaraNafas') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">B2 Kardiovaskuler</td><td class="border border-[#999] px-[5px] py-[2px] align-top">Bunyi {{ $nilaiForm('b2Bunyi') }}, CRT {{ $nilaiForm('b2CRT') }}, Akral {{ $nilaiForm('b2Akral') }}, Nadi {{ $nilaiForm('b2Nadi') }} x/mnt, Suhu {{ $nilaiForm('b2Suhu') }} °C</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">B3 Persyarafan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">Kesadaran {{ $nilaiForm('b3Kesadaran') }}, Reflek {{ $nilaiFormList('b3Reflek') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">B4 Perkemihan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">BAK {{ $nilaiForm('b4Bak') }}, Warna {{ $nilaiForm('b4Warna') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">B5 Pencernaan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">BAB {{ $nilaiFormList('b5Bab') }}, Minum {{ $nilaiFormList('b5Minum') }}, Jenis Susu {{ $nilaiForm('b5JenisSusu') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">B6 Musk. & Integumen</td><td class="border border-[#999] px-[5px] py-[2px] align-top">Pergerakan {{ $nilaiForm('b6Pergerakan') }}, Kulit {{ $nilaiFormList('b6Kulit') }}, Turgor {{ $nilaiForm('b6Turgor') }}</td></tr>
    </table>

    {{-- 7. Skala Nyeri NIPS & 8. Diagnosa Keperawatan --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">7. SKALA NYERI (NIPS) &nbsp; · &nbsp; 8. DIAGNOSA KEPERAWATAN</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">NIPS</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">Total {{ $nilaiForm('nipsTotal') }} — {{ $nilaiForm('nipsInterpretasi') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Diagnosa Keperawatan</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiFormList('diagnosaKeperawatan') }}</td></tr>
    </table>

    {{-- 9. Penunjang --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">9. PENUNJANG</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Laboratorium</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('labPenunjang') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Lain-lain</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('lainPenunjang') }}</td></tr>
    </table>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $form['ttdDate'] ?? ($data['tglCetak'] ?? '') }}<br>
                {{ $form['ttd'] ?? 'Perawat/Bidan' }}<br>
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
