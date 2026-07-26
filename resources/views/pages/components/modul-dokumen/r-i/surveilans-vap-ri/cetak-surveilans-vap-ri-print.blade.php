{{-- resources/views/pages/components/modul-dokumen/r-i/surveilans-vap-ri/cetak-surveilans-vap-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="SURVEILANS HAIs — PNEUMONIA VENTILATOR">

    <x-slot name="patientData">
        @php
            $id = $data['identitas'] ?? [];
            $alamatPasien = trim(
                ($id['alamat'] ?? '-') .
                    (!empty($id['rt']) ? ' RT ' . $id['rt'] : '') .
                    (!empty($id['rw']) ? '/RW ' . $id['rw'] : '') .
                    (!empty($id['desaName']) ? ', ' . $id['desaName'] : '') .
                    (!empty($id['kecamatanName']) ? ', ' . $id['kecamatanName'] : ''),
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
        $opsi = $data['opsiLabel'] ?? [];
        $nilai = fn($key) => filled($form[$key] ?? null) ? e($form[$key]) : '-';
        $labelDari = function (string $peta, ?string $key) use ($opsi) {
            if (!filled($key)) {
                return '-';
            }
            return e($opsi[$peta][$key] ?? $key);
        };
        $flagAktif = function (string $peta, array $flags) use ($opsi) {
            $hasil = [];
            foreach ($opsi[$peta] ?? [] as $key => $label) {
                if (!empty($flags[$key])) {
                    $hasil[] = $label;
                }
            }
            return $hasil ? e(implode(', ', $hasil)) : '-';
        };

        $barisTempat = collect($form['tempatDirawat'] ?? [])->filter(fn($baris) => filled($baris['ruang'] ?? null) || filled($baris['dokter'] ?? null));
        $barisObat = collect($form['antibiotik'] ?? [])->filter(fn($baris) => filled($baris['namaObat'] ?? null));
    @endphp

    <style>
        .sv-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:6px; }
        table.sv { width:100%; border-collapse:collapse; font-size:10px; }
        table.sv td, table.sv th { border:1px solid #999; padding:2px 5px; vertical-align:top; }
        table.sv td.lbl { width:22%; color:#333; background:#f7f7f7; }
        table.sv th { background:#f2f2f2; text-align:left; font-size:9px; }
    </style>

    {{-- 1. Data dasar --}}
    <div class="sv-sec">1. DATA DASAR SURVEILANS</div>
    <table class="sv">
        <tr>
            <td class="lbl">Tgl / Jam Surveilans</td><td>{{ $nilai('tanggal') }}</td>
            <td class="lbl">Pemakaian Ventilator</td><td>{{ $nilai('ventilator') }}</td>
        </tr>
        <tr>
            <td class="lbl">Cara Masuk RS</td><td>{{ $labelDari('caraMasuk', $form['caraMasuk'] ?? null) }}</td>
            <td class="lbl">Cara Keluar RS</td><td>{{ $labelDari('caraKeluar', $form['caraKeluar'] ?? null) }}</td>
        </tr>
        <tr>
            <td class="lbl">Diagnosis Akhir</td><td colspan="3">{{ $nilai('diagnosisAkhir') }}</td>
        </tr>
    </table>

    @if ($barisTempat->isNotEmpty())
        <table class="sv" style="margin-top:4px;">
            <tr><th style="width:28%">Ruang</th><th style="width:26%">Tgl Mulai</th><th style="width:26%">s/d Tgl</th><th>Dokter</th></tr>
            @foreach ($barisTempat as $baris)
                <tr>
                    <td>{{ $baris['ruang'] ?: '-' }}{{ !empty($baris['bedNo']) ? ' — Bed ' . $baris['bedNo'] : '' }}</td>
                    <td>{{ $baris['tglMulai'] ?: '-' }}</td>
                    <td>{{ $baris['tglSelesai'] ?: '-' }}</td>
                    <td>{{ $baris['dokter'] ?: '-' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- 2. Faktor risiko --}}
    <div class="sv-sec">2. FAKTOR RISIKO</div>
    <table class="sv">
        <tr><td>{{ $flagAktif('faktorRisiko', $form['faktorRisiko'] ?? []) }}</td></tr>
    </table>

    {{-- 3. Ventilator & tanda klinis --}}
    <div class="sv-sec">3. PEMAKAIAN VENTILATOR &amp; TANDA KLINIS</div>
    <table class="sv">
        <tr>
            <td class="lbl">Tgl / Jam Pasang</td><td>{{ $nilai('tglPasang') }}</td>
            <td class="lbl">s/d Tgl / Jam Lepas</td><td>{{ $nilai('tglLepas') }}</td>
        </tr>
        <tr>
            <td class="lbl">Demam &ge; 38 &deg;C</td><td>{{ $nilai('demam') }}</td>
            <td class="lbl">Demam Hari Ke</td><td>{{ $nilai('demamHariKe') }}</td>
        </tr>
        <tr>
            <td class="lbl">Sekresi Dahak Purulen</td><td>{{ $nilai('sekresiPurulen') }}</td>
            <td class="lbl">Foto Toraks</td><td>{{ $flagAktif('fotoToraks', $form['fotoToraks'] ?? []) }}</td>
        </tr>
        <tr>
            <td class="lbl">FiO2 / PO2 &ge; 240 mmHg</td><td>Hari ke {{ $nilai('fio2Ge240HariKe') }}</td>
            <td class="lbl">FiO2 / PO2 &lt; 240 mmHg</td><td>Hari ke {{ $nilai('fio2Lt240HariKe') }}</td>
        </tr>
        <tr>
            <td class="lbl">Keterangan Foto Toraks</td><td colspan="3">{{ $nilai('fotoToraksKeterangan') }}</td>
        </tr>
    </table>

    {{-- 4. Kultur aspirat --}}
    <div class="sv-sec">4. KULTUR ASPIRAT / BIOPSI</div>
    <table class="sv">
        <tr><td class="lbl">Dilakukan Kultur</td><td colspan="3">{{ $nilai('kulturAspirat') }}</td></tr>
        @forelse ($form['kulturAspiratHasil'] ?? [] as $i => $baris)
            <tr>
                <td class="lbl">Kultur ke-{{ $i + 1 }} — Tgl</td><td>{{ $baris['tgl'] ?: '-' }}</td>
                <td class="lbl">Hasil</td><td>{{ $baris['hasil'] ?: '-' }}</td>
            </tr>
        @empty
            <tr><td class="lbl">Hasil Kultur Aspirat</td><td colspan="3">-</td></tr>
        @endforelse
    </table>

    {{-- 5. Antibiotik --}}
    <div class="sv-sec">5. PEMAKAIAN ANTIBIOTIK ({{ $nilai('adaAntibiotik') }})</div>
    @if ($barisObat->isNotEmpty())
        <table class="sv">
            <tr>
                <th style="width:26%">Nama Obat</th>
                <th style="width:17%">Tgl Mulai</th>
                <th style="width:17%">s/d Tgl</th>
                <th style="width:14%">Dosis</th>
                <th style="width:10%">Rute</th>
                <th>Indikasi</th>
            </tr>
            @foreach ($barisObat as $baris)
                <tr>
                    <td>{{ $baris['namaObat'] ?: '-' }}</td>
                    <td>{{ $baris['tglMulai'] ?: '-' }}</td>
                    <td>{{ $baris['tglSelesai'] ?: '-' }}</td>
                    <td>{{ $baris['dosis'] ?: '-' }}</td>
                    <td>{{ $labelDari('ruteAntibiotik', $baris['rute'] ?? null) }}</td>
                    <td>{{ $labelDari('indikasiAntibiotik', $baris['indikasi'] ?? null) }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <table class="sv"><tr><td>-</td></tr></table>
    @endif

    {{-- 6. Catatan --}}
    @if (filled($form['catatan'] ?? null))
        <div class="sv-sec">6. CATATAN</div>
        <table class="sv"><tr><td>{{ $nilai('catatan') }}</td></tr></table>
    @endif

    {{-- TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:50%; text-align:center;">
                Mengetahui,<br>Dokter yang Merawat<br>
                <br><br><br>
                <span style="border-top:1px solid #000; padding:0 25px;">{{ $form['dokterMerawat'] ?: '(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)' }}</span>
            </td>
            <td style="width:50%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $form['ttdDate'] ?: ($data['tglCetak'] ?? '') }}<br>
                Perawat / IPCLN<br>
                @if (!empty($data['ttdPath']))
                    <img src="{{ $data['ttdPath'] }}" style="height:44px; margin:4px 0;" alt="Tanda Tangan"><br>
                @else
                    <br><br><br>
                @endif
                <span style="border-top:1px solid #000; padding:0 25px;">{{ $form['ttd'] ?: '(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)' }}</span>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
