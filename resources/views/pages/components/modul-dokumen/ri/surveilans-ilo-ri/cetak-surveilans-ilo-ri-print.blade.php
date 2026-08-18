{{-- resources/views/pages/components/modul-dokumen/ri/surveilans-ilo-ri/cetak-surveilans-ilo-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="SURVEILANS HAIs — INFEKSI LUKA OPERASI (ILO)">

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

        $barisObat = collect($form['antibiotik'] ?? [])->filter(fn($baris) => filled($baris['namaObat'] ?? null));
        $paramPantau = $opsi['paramPemantauanIlo'] ?? [];
        $hariPantau = $form['pemantauan'] ?? [];
        $lamaOperasi = trim(($form['lamaOperasiJam'] ?? '') . ' jam ' . ($form['lamaOperasiMenit'] ?? '') . ' menit');
    @endphp

    <style>
        .sv-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:6px; }
        table.sv { width:100%; border-collapse:collapse; font-size:10px; }
        table.sv td, table.sv th { border:1px solid #999; padding:2px 5px; vertical-align:top; }
        table.sv td.lbl { width:22%; color:#333; background:#f7f7f7; }
        table.sv th { background:#f2f2f2; text-align:left; font-size:9px; }
        table.sv-pantau { width:100%; border-collapse:collapse; font-size:8px; }
        table.sv-pantau td, table.sv-pantau th { border:1px solid #999; padding:1px 2px; text-align:center; }
        table.sv-pantau td.par { text-align:left; background:#f7f7f7; white-space:nowrap; }
    </style>

    {{-- 1. Data dasar --}}
    <div class="sv-sec">1. DATA DASAR SURVEILANS</div>
    <table class="sv">
        <tr>
            <td class="lbl">Tgl / Jam Surveilans</td><td>{{ $nilai('tanggal') }}</td>
            <td class="lbl">Dilakukan Operasi</td><td>{{ $nilai('operasi') }}</td>
        </tr>
        <tr>
            <td class="lbl">Diagnosis Akhir</td><td colspan="3">{{ $nilai('diagnosisAkhir') }}</td>
        </tr>
    </table>

    {{-- 2. Faktor risiko --}}
    <div class="sv-sec">2. FAKTOR RISIKO</div>
    <table class="sv">
        <tr><td>{{ $flagAktif('faktorRisiko', $form['faktorRisiko'] ?? []) }}</td></tr>
    </table>

    {{-- 3. Data operasi --}}
    <div class="sv-sec">3. DATA OPERASI</div>
    <table class="sv">
        <tr>
            <td class="lbl">Tgl / Jam Operasi</td><td>{{ $nilai('tanggalOperasi') }}</td>
            <td class="lbl">Tindakan Operasi</td><td>{{ $nilai('tindakanOperasi') }}</td>
        </tr>
        <tr>
            <td class="lbl">Dokter Operator</td><td>{{ $nilai('dokterOperator') }}</td>
            <td class="lbl">Dokter Konsultan</td><td>{{ $nilai('dokterKonsultan') }}</td>
        </tr>
        <tr>
            <td class="lbl">Operasi Emergensi</td><td>{{ $nilai('emergensi') }}</td>
            <td class="lbl">Jenis Operasi</td><td>{{ $labelDari('jenisOperasi', $form['jenisOperasi'] ?? null) }}</td>
        </tr>
        <tr>
            <td class="lbl">Anestesi Umum</td><td>{{ $nilai('anestesiUmum') }}</td>
            <td class="lbl">ASA Score</td><td>{{ $labelDari('asaScore', $form['asaScore'] ?? null) }}</td>
        </tr>
        <tr>
            <td class="lbl">Kamar Operasi</td><td>{{ $nilai('kamarOperasi') }}</td>
            <td class="lbl">Ronde Ke</td><td>{{ $nilai('rondeKe') }}</td>
        </tr>
        <tr>
            <td class="lbl">Implan</td><td>{{ $nilai('implan') }}</td>
            <td class="lbl">Trauma</td><td>{{ $nilai('trauma') }}</td>
        </tr>
        <tr>
            <td class="lbl">Pendekatan Endoskopi</td><td>{{ $nilai('pendekatanEndoskopi') }}</td>
            <td class="lbl">Prosedur Multipel</td><td>{{ $nilai('prosedurMultipel') }}</td>
        </tr>
        <tr>
            <td class="lbl">Lama Operasi</td><td>{{ $lamaOperasi !== 'jam  menit' ? $lamaOperasi : '-' }}</td>
            <td class="lbl">PJ Kamar Operasi</td><td>{{ $nilai('penanggungJawabKamarOperasi') }}</td>
        </tr>
    </table>

    {{-- 4. Pemantauan luka operasi --}}
    <div class="sv-sec">4. PEMANTAUAN LUKA OPERASI (HARI KE-1 s/d {{ count($hariPantau) }})</div>
    <table class="sv-pantau">
        <tr>
            <th class="par" style="text-align:left;">Parameter</th>
            @foreach ($hariPantau as $h => $hari)
                <th>{{ $h + 1 }}</th>
            @endforeach
        </tr>
        @foreach ($paramPantau as $param => $labelParam)
            <tr>
                <td class="par">{{ $labelParam }}</td>
                @foreach ($hariPantau as $h => $hari)
                    <td>{{ !empty($hari[$param]) ? 'V' : '' }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    {{-- 5. Kultur --}}
    <div class="sv-sec">5. HASIL KULTUR</div>
    <table class="sv">
        @forelse ($form['kulturHasil'] ?? [] as $i => $baris)
            <tr>
                <td class="lbl">Kultur ke-{{ $i + 1 }} — Tgl</td><td>{{ $baris['tgl'] ?: '-' }}</td>
                <td class="lbl">Hasil</td><td>{{ $baris['hasil'] ?: '-' }}</td>
            </tr>
        @empty
            <tr><td class="lbl">Hasil Kultur</td><td colspan="3">-</td></tr>
        @endforelse
    </table>

    {{-- 6. Antibiotik --}}
    <div class="sv-sec">6. PEMAKAIAN ANTIBIOTIK</div>
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

    {{-- 7. Catatan --}}
    @if (filled($form['catatan'] ?? null))
        <div class="sv-sec">7. CATATAN</div>
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
