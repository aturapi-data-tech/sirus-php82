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

        // Nilai kolom satu baris bayi. Escaping diserahkan ke {{ }} (tanpa e() ganda).
        $nilaiBaris = fn(array $baris, string $field) => filled($baris[$field] ?? null) ? $baris[$field] : '-';
        $ukuranKepala = fn(array $baris) => collect(['ukKepalaBt' => 'BT', 'ukKepalaBp' => 'BP', 'ukKepalaFo' => 'FO', 'ukKepalaMo' => 'MO', 'ukKepalaOb' => 'OB'])
            ->filter(fn(string $label, string $field) => filled($baris[$field] ?? null))
            ->map(fn(string $label, string $field) => $label . ' ' . $baris[$field] . ' cm')
            ->implode(', ');

        // Data bayi kini baris berulang (kelahiran kembar). Entri LAMA masih datar di akar
        // form (bayiBb, bayiLahirTgl, ...) → dibaca sebagai satu baris bayi.
        $petaBayiLegacy = [
            'bayiLahirTgl' => 'lahir',
            'bayiJenisKelamin' => 'jenisKelamin',
            'bayiKeadaan' => 'keadaan',
            'bayiBb' => 'bb',
            'bayiPb' => 'pb',
            'bayiApgar' => 'apgar',
            'bayiResusitasi' => 'resusitasi',
            'ukKepalaBt' => 'ukKepalaBt',
            'ukKepalaBp' => 'ukKepalaBp',
            'ukKepalaFo' => 'ukKepalaFo',
            'ukKepalaMo' => 'ukKepalaMo',
            'ukKepalaOb' => 'ukKepalaOb',
            'caputSuksedanium' => 'caputSuksedanium',
            'cephalHematoma' => 'cephalHematoma',
            'atresiaAni' => 'atresiaAni',
            'bayiLain' => 'lain',
        ];
        if (is_array($form['bayi'] ?? null)) {
            $daftarBayi = array_values(array_filter($form['bayi'], fn($baris) => is_array($baris)));
        } else {
            $bayiLegacy = [];
            foreach ($petaBayiLegacy as $fieldLama => $kunciBaris) {
                $bayiLegacy[$kunciBaris] = (string) ($form[$fieldLama] ?? '');
            }
            $daftarBayi = collect($bayiLegacy)->contains(fn($nilai) => filled($nilai)) ? [$bayiLegacy] : [];
        }

        // TD Kala IV: sistolik/diastolik; entri lama masih menyimpan kalaIvTd gabungan "120/80".
        $kalaIvSistolik = trim((string) ($form['kalaIvSistolik'] ?? ''));
        $kalaIvDiastolik = trim((string) ($form['kalaIvDiastolik'] ?? ''));
        if (blank($kalaIvSistolik) && blank($kalaIvDiastolik) && filled($form['kalaIvTd'] ?? null)) {
            [$kalaIvSistolik, $kalaIvDiastolik] = array_pad(explode('/', (string) $form['kalaIvTd'], 2), 2, '');
            $kalaIvSistolik = trim($kalaIvSistolik);
            $kalaIvDiastolik = trim($kalaIvDiastolik);
        }
        $tdKalaIv = filled($kalaIvSistolik) || filled($kalaIvDiastolik)
            ? ($kalaIvSistolik ?: '-') . '/' . ($kalaIvDiastolik ?: '-')
            : '-';
    @endphp

    <style>
        .pa-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:6px; }
        table.pa { width:100%; border-collapse:collapse; font-size:10px; }
        table.pa td { border:1px solid #999; padding:2px 5px; vertical-align:top; }
        table.pa td.lbl { width:22%; color:#333; background:#f7f7f7; }
        table.pa td.hdr { color:#333; background:#f7f7f7; font-weight:bold; text-align:center; }
        table.pa td.num { text-align:center; }
    </style>

    {{-- 1. Jenis Partus --}}
    <div class="pa-sec">1. JENIS PARTUS</div>
    <table class="pa">
        <tr><td class="lbl">Jenis Partus</td><td>{{ $nilaiForm('jenisPartus') }}</td><td class="lbl">Indikasi</td><td>{{ $nilaiForm('indikasi') }}</td></tr>
    </table>

    {{-- 2. Bayi — satu baris per bayi (kelahiran kembar / gemelli) --}}
    <div class="pa-sec">2. BAYI</div>
    <table class="pa">
        <tr>
            <td class="hdr" style="width:5%;">No</td>
            <td class="hdr">Lahir</td>
            <td class="hdr">JK</td>
            <td class="hdr">Keadaan</td>
            <td class="hdr">BB (gr)</td>
            <td class="hdr">PB (cm)</td>
            <td class="hdr">APGAR</td>
            <td class="hdr">Resusitasi</td>
        </tr>
        @forelse ($daftarBayi as $nomor => $baris)
            <tr>
                <td class="num">{{ $nomor + 1 }}</td>
                <td>{{ $nilaiBaris($baris, 'lahir') }}</td>
                <td>{{ $nilaiBaris($baris, 'jenisKelamin') }}</td>
                <td>{{ $nilaiBaris($baris, 'keadaan') }}</td>
                <td>{{ $nilaiBaris($baris, 'bb') }}</td>
                <td>{{ $nilaiBaris($baris, 'pb') }}</td>
                <td>{{ $nilaiBaris($baris, 'apgar') }}</td>
                <td>{{ $nilaiBaris($baris, 'resusitasi') }}</td>
            </tr>
            <tr>
                <td class="num">&nbsp;</td>
                <td colspan="7">
                    Ukuran kepala: {{ $ukuranKepala($baris) ?: '-' }} &middot;
                    Caput Suksedanium: {{ $nilaiBaris($baris, 'caputSuksedanium') }} &middot;
                    Cephal Hematoma: {{ $nilaiBaris($baris, 'cephalHematoma') }} &middot;
                    Atresia Ani: {{ $nilaiBaris($baris, 'atresiaAni') }} &middot;
                    Lain-lain: {{ $nilaiBaris($baris, 'lain') }} &middot;
                    Petugas: {{ $nilaiBaris($baris, 'petugas') }}
                </td>
            </tr>
        @empty
            <tr><td class="num" colspan="8">Belum ada data bayi.</td></tr>
        @endforelse
    </table>

    {{-- 3. Plasenta --}}
    <div class="pa-sec">3. PLASENTA</div>
    <table class="pa">
        <tr><td class="lbl">Lahir</td><td>{{ $nilaiForm('plasentaLahirTgl') }}</td><td class="lbl">Cara Lahir</td><td>{{ $nilaiForm('plasentaCara') }}</td></tr>
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
        <tr><td class="lbl">TD</td><td>{{ $tdKalaIv }} mmHg</td><td class="lbl">Nadi / RR</td><td>{{ $nilaiForm('kalaIvNadi') }} / {{ $nilaiForm('kalaIvRr') }} x/mnt</td></tr>
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
