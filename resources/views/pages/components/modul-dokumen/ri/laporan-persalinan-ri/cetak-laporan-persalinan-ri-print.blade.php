{{-- resources/views/pages/components/modul-dokumen/ri/laporan-persalinan-ri/cetak-laporan-persalinan-ri-print.blade.php --}}

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


    {{-- 1. Jenis Partus --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">1. JENIS PARTUS</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Jenis Partus</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('jenisPartus') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Indikasi</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('indikasi') }}</td></tr>
    </table>

    {{-- 2. Bayi — satu baris per bayi (kelahiran kembar / gemelli) --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">2. BAYI</div>
    <table class="w-full border-collapse text-[10px]">
        <tr>
            <td class="w-[5%] text-[#333] bg-[#f7f7f7] font-bold text-center border border-[#999] px-[5px] py-[2px] align-top">No</td>
            <td class="text-[#333] bg-[#f7f7f7] font-bold text-center border border-[#999] px-[5px] py-[2px] align-top">Lahir</td>
            <td class="text-[#333] bg-[#f7f7f7] font-bold text-center border border-[#999] px-[5px] py-[2px] align-top">JK</td>
            <td class="text-[#333] bg-[#f7f7f7] font-bold text-center border border-[#999] px-[5px] py-[2px] align-top">Keadaan</td>
            <td class="text-[#333] bg-[#f7f7f7] font-bold text-center border border-[#999] px-[5px] py-[2px] align-top">BB (gr)</td>
            <td class="text-[#333] bg-[#f7f7f7] font-bold text-center border border-[#999] px-[5px] py-[2px] align-top">PB (cm)</td>
            <td class="text-[#333] bg-[#f7f7f7] font-bold text-center border border-[#999] px-[5px] py-[2px] align-top">APGAR</td>
            <td class="text-[#333] bg-[#f7f7f7] font-bold text-center border border-[#999] px-[5px] py-[2px] align-top">Resusitasi</td>
        </tr>
        @forelse ($daftarBayi as $nomor => $baris)
            <tr>
                <td class="text-center border border-[#999] px-[5px] py-[2px] align-top">{{ $nomor + 1 }}</td>
                <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'lahir') }}</td>
                <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'jenisKelamin') }}</td>
                <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'keadaan') }}</td>
                <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'bb') }}</td>
                <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'pb') }}</td>
                <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'apgar') }}</td>
                <td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiBaris($baris, 'resusitasi') }}</td>
            </tr>
            <tr>
                <td class="text-center border border-[#999] px-[5px] py-[2px] align-top">&nbsp;</td>
                <td colspan="7" class="border border-[#999] px-[5px] py-[2px] align-top">
                    Ukuran kepala: {{ $ukuranKepala($baris) ?: '-' }} &middot;
                    Caput Suksedanium: {{ $nilaiBaris($baris, 'caputSuksedanium') }} &middot;
                    Cephal Hematoma: {{ $nilaiBaris($baris, 'cephalHematoma') }} &middot;
                    Atresia Ani: {{ $nilaiBaris($baris, 'atresiaAni') }} &middot;
                    Lain-lain: {{ $nilaiBaris($baris, 'lain') }} &middot;
                    Petugas: {{ $nilaiBaris($baris, 'petugas') }}
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center border border-[#999] px-[5px] py-[2px] align-top">Belum ada data bayi.</td></tr>
        @endforelse
    </table>

    {{-- 3. Plasenta --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">3. PLASENTA</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Lahir</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('plasentaLahirTgl') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Cara Lahir</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('plasentaCara') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Jenis</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('plasentaJenis') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Berat / Diameter</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('plasentaBerat') }} gr / {{ $nilaiForm('plasentaDiameter') }} cm</td></tr>
    </table>

    {{-- 4. Tali Pusat --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">4. TALI PUSAT</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Insersi</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('taliPusatInsersi') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Panjang</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('taliPusatPanjang') }} cm</td></tr>
    </table>

    {{-- 5. Selaput Janin --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">5. SELAPUT JANIN</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Keadaan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('selaputKeadaan') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Robekan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('selaputRobekan') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Lain-lain</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('selaputLain') }}</td></tr>
    </table>

    {{-- 6. Perlukaan Jalan Lahir --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">6. PERLUKAAN JALAN LAHIR</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Luka Perineum</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('lukaPerineum') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Episiotomi</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('episiotomi') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Ruptura Perinei</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('rupturaPerinei') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Luka Vagina</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('lukaVagina') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Luka Serviks</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('lukaServiks') }}</td></tr>
    </table>

    {{-- 7. Kala IV --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">7. KALA IV</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Hb</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('kalaIvHb') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Suhu</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('kalaIvSuhu') }} °C</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">TD</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $tdKalaIv }} mmHg</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Nadi / RR</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('kalaIvNadi') }} / {{ $nilaiForm('kalaIvRr') }} x/mnt</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">TFU</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('kalaIvTfu') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Kontraksi Uterus</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('kalaIvKontraksi') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Perdarahan Kala III</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('perdarahanKalaIii') }} cc</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Perdarahan Kala IV</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('perdarahanKalaIv') }} cc</td></tr>
    </table>

    {{-- 8. IMD, Rawat Gabung & ASI (PONEK / Prognas 1) --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">8. IMD, RAWAT GABUNG &amp; ASI (PONEK / PROGNAS 1)</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">IMD Dilakukan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('imdDilakukan') }}{{ filled($form['imdTglJam'] ?? null) ? ' — ' . e($form['imdTglJam']) : '' }}{{ filled($form['imdDurasiMenit'] ?? null) ? ' (' . e($form['imdDurasiMenit']) . ' menit)' : '' }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Alasan bila tidak</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('imdAlasanTidak') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Rawat Gabung</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('rawatGabung') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Konseling ASI</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('asiKonseling') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">PMK (Metode Kanguru)</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('pmkDilakukan') }}</td></tr>
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
