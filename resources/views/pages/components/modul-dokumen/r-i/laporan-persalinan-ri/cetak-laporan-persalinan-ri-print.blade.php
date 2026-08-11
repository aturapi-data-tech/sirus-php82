{{-- resources/views/pages/components/modul-dokumen/r-i/laporan-persalinan-ri/cetak-laporan-persalinan-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="LAPORAN TINDAKAN PERSALINAN">

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
        $tglJam = fn(string $fieldTgl, string $fieldJam) => trim((filled($form[$fieldTgl] ?? null) ? e($form[$fieldTgl]) : '') . ' ' . (filled($form[$fieldJam] ?? null) ? e($form[$fieldJam]) : '')) ?: '-';
        $ukuranKepala = collect(['ukKepalaBt' => 'BT', 'ukKepalaBp' => 'BP', 'ukKepalaFo' => 'FO', 'ukKepalaMo' => 'MO', 'ukKepalaOb' => 'OB'])
            ->filter(fn(string $label, string $field) => filled($form[$field] ?? null))
            ->map(fn(string $label, string $field) => $label . ' ' . e($form[$field]) . ' cm')
            ->implode(', ');
    @endphp

    <style>
        .pa-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:6px; }
        table.pa { width:100%; border-collapse:collapse; font-size:10px; }
        table.pa td { border:1px solid #999; padding:2px 5px; vertical-align:top; }
        table.pa td.lbl { width:22%; color:#333; background:#f7f7f7; }
    </style>

    {{-- 1. Jenis Partus --}}
    <div class="pa-sec">1. JENIS PARTUS</div>
    <table class="pa">
        <tr><td class="lbl">Jenis Partus</td><td>{{ $nilaiForm('jenisPartus') }}</td><td class="lbl">Indikasi</td><td>{{ $nilaiForm('indikasi') }}</td></tr>
    </table>

    {{-- 2. Bayi --}}
    <div class="pa-sec">2. BAYI</div>
    <table class="pa">
        <tr><td class="lbl">Lahir</td><td>{{ $tglJam('bayiLahirTgl','bayiLahirJam') }}</td><td class="lbl">Jenis Kelamin</td><td>{{ $nilaiForm('bayiJenisKelamin') }}</td></tr>
        <tr><td class="lbl">Berat / Panjang</td><td>{{ $nilaiForm('bayiBb') }} gr / {{ $nilaiForm('bayiPb') }} cm</td><td class="lbl">APGAR Score</td><td>{{ $nilaiForm('bayiApgar') }}</td></tr>
        <tr><td class="lbl">Resusitasi</td><td>{{ $nilaiForm('bayiResusitasi') }}</td><td class="lbl">Keadaan</td><td>{{ $nilaiForm('bayiKeadaan') }}</td></tr>
        <tr><td class="lbl">Ukuran Kepala</td><td colspan="3">{{ $ukuranKepala ?: '-' }}</td></tr>
        <tr><td class="lbl">Caput Suksedanium</td><td>{{ $nilaiForm('caputSuksedanium') }}</td><td class="lbl">Cephal Hematoma</td><td>{{ $nilaiForm('cephalHematoma') }}</td></tr>
        <tr><td class="lbl">Atresia Ani</td><td>{{ $nilaiForm('atresiaAni') }}</td><td class="lbl">Lain-lain</td><td>{{ $nilaiForm('bayiLain') }}</td></tr>
    </table>

    {{-- 3. Plasenta --}}
    <div class="pa-sec">3. PLASENTA</div>
    <table class="pa">
        <tr><td class="lbl">Lahir</td><td>{{ $tglJam('plasentaLahirTgl','plasentaLahirJam') }}</td><td class="lbl">Cara Lahir</td><td>{{ $nilaiForm('plasentaCara') }}</td></tr>
        <tr><td class="lbl">Jenis</td><td>{{ $nilaiForm('plasentaJenis') }}</td><td class="lbl">Berat / Diameter</td><td>{{ $nilaiForm('plasentaBerat') }} gr / {{ $nilaiForm('plasentaDiameter') }} cm</td></tr>
    </table>

    {{-- 4. Tali Pusat --}}
    <div class="pa-sec">4. TALI PUSAT</div>
    <table class="pa">
        <tr><td class="lbl">Insersi</td><td>{{ $nilaiForm('taliPusatInsersi') }}</td><td class="lbl">Panjang</td><td>{{ $nilaiForm('taliPusatPanjang') }} cm</td></tr>
    </table>

    {{-- 5. Selaput Janin --}}
    <div class="pa-sec">5. SELAPUT JANIN</div>
    <table class="pa">
        <tr><td class="lbl">Keadaan</td><td>{{ $nilaiForm('selaputKeadaan') }}</td><td class="lbl">Robekan</td><td>{{ $nilaiForm('selaputRobekan') }}</td></tr>
        <tr><td class="lbl">Lain-lain</td><td colspan="3">{{ $nilaiForm('selaputLain') }}</td></tr>
    </table>

    {{-- 6. Perlukaan Jalan Lahir --}}
    <div class="pa-sec">6. PERLUKAAN JALAN LAHIR</div>
    <table class="pa">
        <tr><td class="lbl">Luka Perineum</td><td>{{ $nilaiForm('lukaPerineum') }}</td><td class="lbl">Episiotomi</td><td>{{ $nilaiForm('episiotomi') }}</td></tr>
        <tr><td class="lbl">Ruptura Perinei</td><td>{{ $nilaiForm('rupturaPerinei') }}</td><td class="lbl">Luka Vagina</td><td>{{ $nilaiForm('lukaVagina') }}</td></tr>
        <tr><td class="lbl">Luka Serviks</td><td colspan="3">{{ $nilaiForm('lukaServiks') }}</td></tr>
    </table>

    {{-- 7. Kala IV --}}
    <div class="pa-sec">7. KALA IV</div>
    <table class="pa">
        <tr><td class="lbl">Hb</td><td>{{ $nilaiForm('kalaIvHb') }}</td><td class="lbl">Suhu</td><td>{{ $nilaiForm('kalaIvSuhu') }} °C</td></tr>
        <tr><td class="lbl">TD</td><td>{{ $nilaiForm('kalaIvTd') }} mmHg</td><td class="lbl">Nadi / RR</td><td>{{ $nilaiForm('kalaIvNadi') }} / {{ $nilaiForm('kalaIvRr') }} x/mnt</td></tr>
        <tr><td class="lbl">TFU</td><td>{{ $nilaiForm('kalaIvTfu') }}</td><td class="lbl">Kontraksi Uterus</td><td>{{ $nilaiForm('kalaIvKontraksi') }}</td></tr>
        <tr><td class="lbl">Perdarahan Kala III</td><td>{{ $nilaiForm('perdarahanKalaIii') }} cc</td><td class="lbl">Perdarahan Kala IV</td><td>{{ $nilaiForm('perdarahanKalaIv') }} cc</td></tr>
    </table>

    {{-- 8. IMD, Rawat Gabung & ASI (PONEK / Prognas 1) --}}
    <div class="pa-sec">8. IMD, RAWAT GABUNG &amp; ASI (PONEK / PROGNAS 1)</div>
    <table class="pa">
        <tr><td class="lbl">IMD Dilakukan</td><td>{{ $nilaiForm('imdDilakukan') }}{{ filled($form['imdTglJam'] ?? null) ? ' — ' . e($form['imdTglJam']) : '' }}{{ filled($form['imdDurasiMenit'] ?? null) ? ' (' . e($form['imdDurasiMenit']) . ' menit)' : '' }}</td><td class="lbl">Alasan bila tidak</td><td>{{ $nilaiForm('imdAlasanTidak') }}</td></tr>
        <tr><td class="lbl">Rawat Gabung</td><td>{{ $nilaiForm('rawatGabung') }}</td><td class="lbl">Konseling ASI</td><td>{{ $nilaiForm('asiKonseling') }}</td></tr>
        <tr><td class="lbl">PMK (Metode Kanguru)</td><td colspan="3">{{ $nilaiForm('pmkDilakukan') }}</td></tr>
    </table>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $form['ttdDate'] ?? ($data['tglCetak'] ?? '') }}<br>
                {{ $form['ttd'] ?? 'Dokter' }}<br>
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
