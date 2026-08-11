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

    <style>
        .pa-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:6px; }
        table.pa { width:100%; border-collapse:collapse; font-size:10px; }
        table.pa td { border:1px solid #999; padding:2px 5px; vertical-align:top; }
        table.pa td.lbl { width:22%; color:#333; background:#f7f7f7; }
    </style>

    {{-- 1. Riwayat Penyakit --}}
    <div class="pa-sec">1. RIWAYAT PENYAKIT</div>
    <table class="pa">
        <tr><td class="lbl">Keluhan Utama</td><td>{{ $nilaiForm('keluhanUtama') }}</td></tr>
    </table>

    {{-- 2. Antenatal --}}
    <div class="pa-sec">2. ANTENATAL</div>
    <table class="pa">
        <tr><td class="lbl">ANC</td><td>{{ $nilaiForm('anc') }}{{ filled($form['ancTempat'] ?? null) ? ' — ' . e($form['ancTempat']) : '' }}</td><td class="lbl">TT</td><td>{{ $nilaiForm('tt') }}{{ filled($form['ttKali'] ?? null) ? ' (' . e($form['ttKali']) . ' kali)' : '' }}</td></tr>
        <tr><td class="lbl">Penyulit Kehamilan</td><td>{{ $nilaiFormList('penyulitKehamilan') }}</td><td class="lbl">Penyakit Menyertai</td><td>{{ $nilaiFormList('penyakitMenyertai') }}</td></tr>
    </table>

    {{-- 3. Intranatal --}}
    <div class="pa-sec">3. INTRANATAL</div>
    <table class="pa">
        <tr><td class="lbl">Umur Kehamilan</td><td>{{ $nilaiForm('umurKehamilan') }} minggu</td><td class="lbl">Kondisi Kelahiran</td><td>{{ $nilaiForm('kondisiKelahiran') }}</td></tr>
        <tr><td class="lbl">Jenis Persalinan</td><td>{{ $nilaiForm('jenisPersalinan') }}</td><td class="lbl">Penolong</td><td>{{ $nilaiForm('penolong') }}</td></tr>
        <tr><td class="lbl">Penyulit Persalinan</td><td>{{ $nilaiFormList('penyulitPersalinan') }}</td><td class="lbl">Komplikasi</td><td>{{ $nilaiFormList('komplikasi') }}{{ filled($form['kpdLamaJam'] ?? null) ? ' (KPD ' . e($form['kpdLamaJam']) . ' jam)' : '' }}</td></tr>
    </table>

    {{-- 4. Postnatal — Antropometri --}}
    <div class="pa-sec">4. POSTNATAL — ANTROPOMETRI</div>
    <table class="pa">
        <tr><td class="lbl">BBL / PB</td><td>{{ $nilaiForm('bbl') }} gr / {{ $nilaiForm('pb') }} cm</td><td class="lbl">LK / LD</td><td>{{ $nilaiForm('lk') }} / {{ $nilaiForm('ld') }} cm</td></tr>
        <tr><td class="lbl">LILA / Lingkar Perut</td><td>{{ $nilaiForm('lila') }} / {{ $nilaiForm('lingkarPerut') }} cm</td><td class="lbl">APGAR (1'/5')</td><td>{{ $nilaiForm('apgar1') }} / {{ $nilaiForm('apgar5') }}</td></tr>
        <tr><td class="lbl">Trauma Lahir</td><td>{{ $nilaiForm('traumaLahir') }}{{ filled($form['traumaKet'] ?? null) ? ' — ' . e($form['traumaKet']) : '' }}</td><td class="lbl">Usaha Nafas</td><td>{{ $nilaiForm('usahaNafas') }}</td></tr>
        <tr><td class="lbl">Imunisasi</td><td colspan="3">{{ $nilaiForm('imunisasi') }}{{ filled($form['imunisasiKet'] ?? null) ? ' — ' . e($form['imunisasiKet']) : '' }}</td></tr>
    </table>

    {{-- 5. Pemeriksaan Fisik --}}
    <div class="pa-sec">5. PEMERIKSAAN FISIK</div>
    <table class="pa">
        <tr><td class="lbl">Kepala</td><td>{{ $nilaiForm('kepalaBentuk') }}</td><td class="lbl">Mata (Konjungtiva/Sklera)</td><td>{{ $nilaiForm('mataKonjungtiva') }} / {{ $nilaiForm('mataSklera') }}</td></tr>
        <tr><td class="lbl">Telinga / Hidung</td><td>{{ $nilaiForm('telinga') }} / {{ $nilaiForm('hidung') }}</td><td class="lbl">Mulut (Reflek Isap/Bentuk)</td><td>{{ $nilaiForm('mulutReflekIsap') }} / {{ $nilaiForm('mulutBentuk') }}</td></tr>
        <tr><td class="lbl">Dada / Perut</td><td>{{ $nilaiForm('dada') }} / {{ $nilaiForm('perutBentuk') }}</td><td class="lbl">Tali Pusat</td><td>{{ $nilaiForm('taliPusat') }}</td></tr>
        <tr><td class="lbl">Anus / Ekstremitas</td><td colspan="3">{{ $nilaiForm('anus') }} / {{ $nilaiForm('ekstremitas') }}</td></tr>
    </table>

    {{-- 6. Review Sistem --}}
    <div class="pa-sec">6. REVIEW SISTEM (B1–B6)</div>
    <table class="pa">
        <tr><td class="lbl">B1 Pernafasan</td><td>{{ $nilaiForm('b1Pernafasan') }}, RR {{ $nilaiForm('b1FrekuensiNafas') }} x/mnt, {{ $nilaiFormList('b1SuaraNafas') }}</td></tr>
        <tr><td class="lbl">B2 Kardiovaskuler</td><td>Bunyi {{ $nilaiForm('b2Bunyi') }}, CRT {{ $nilaiForm('b2CRT') }}, Akral {{ $nilaiForm('b2Akral') }}, Nadi {{ $nilaiForm('b2Nadi') }} x/mnt, Suhu {{ $nilaiForm('b2Suhu') }} °C</td></tr>
        <tr><td class="lbl">B3 Persyarafan</td><td>Kesadaran {{ $nilaiForm('b3Kesadaran') }}, Reflek {{ $nilaiFormList('b3Reflek') }}</td></tr>
        <tr><td class="lbl">B4 Perkemihan</td><td>BAK {{ $nilaiForm('b4Bak') }}, Warna {{ $nilaiForm('b4Warna') }}</td></tr>
        <tr><td class="lbl">B5 Pencernaan</td><td>BAB {{ $nilaiFormList('b5Bab') }}, Minum {{ $nilaiFormList('b5Minum') }}, Jenis Susu {{ $nilaiForm('b5JenisSusu') }}</td></tr>
        <tr><td class="lbl">B6 Musk. & Integumen</td><td>Pergerakan {{ $nilaiForm('b6Pergerakan') }}, Kulit {{ $nilaiFormList('b6Kulit') }}, Turgor {{ $nilaiForm('b6Turgor') }}</td></tr>
    </table>

    {{-- 7. Skala Nyeri NIPS & 8. Diagnosa Keperawatan --}}
    <div class="pa-sec">7. SKALA NYERI (NIPS) &nbsp; · &nbsp; 8. DIAGNOSA KEPERAWATAN</div>
    <table class="pa">
        <tr><td class="lbl">NIPS</td><td colspan="3">Total {{ $nilaiForm('nipsTotal') }} — {{ $nilaiForm('nipsInterpretasi') }}</td></tr>
        <tr><td class="lbl">Diagnosa Keperawatan</td><td colspan="3">{{ $nilaiFormList('diagnosaKeperawatan') }}</td></tr>
    </table>

    {{-- 9. Penunjang --}}
    <div class="pa-sec">9. PENUNJANG</div>
    <table class="pa">
        <tr><td class="lbl">Laboratorium</td><td colspan="3">{{ $nilaiForm('labPenunjang') }}</td></tr>
        <tr><td class="lbl">Lain-lain</td><td colspan="3">{{ $nilaiForm('lainPenunjang') }}</td></tr>
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
