{{-- resources/views/pages/components/modul-dokumen/ri/surveilans-plebitis-ri/cetak-surveilans-plebitis-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="SURVEILANS HAIs — IADP & PLEBITIS">

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

        $usia = $form['kelompokUsia'] ?? '';
        $petaTanda = $usia === 'balita' ? 'tandaIadpBalita' : 'tandaIadpDewasa';

        $barisPasang = collect($form['pemasangan'] ?? [])->filter(fn($baris) => filled($baris['lokasi'] ?? null) || filled($baris['tglMulai'] ?? null));
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
            <td class="lbl">Kelompok Usia</td><td>{{ $labelDari('kelompokUsia', $form['kelompokUsia'] ?? null) }}</td>
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

    {{-- 3. Pemasangan kateter --}}
    <div class="sv-sec">3. PEMASANGAN KATETER (IADP &amp; PLEBITIS)</div>
    <table class="sv">
        <tr>
            <td class="lbl">Kateter Perifer</td><td>{{ $nilai('kateterPerifer') }}</td>
            <td class="lbl">Kateter Vena Central</td><td>{{ $nilai('kateterVCentral') }}</td>
        </tr>
        <tr>
            <td class="lbl">Kateter Umbilikal</td><td colspan="3">{{ $nilai('kateterUmbilikal') }}</td>
        </tr>
    </table>

    @if ($barisPasang->isNotEmpty())
        <table class="sv" style="margin-top:4px;">
            <tr>
                <th style="width:16%">Jenis Akses</th>
                <th style="width:20%">Lokasi</th>
                <th style="width:17%">Tgl Pasang</th>
                <th style="width:17%">s/d Tgl Lepas</th>
                <th style="width:8%">Hari Ke</th>
                <th>Tanda Infeksi</th>
            </tr>
            @foreach ($barisPasang as $baris)
                <tr>
                    <td>{{ $labelDari('jenisAkses', $baris['jenisAkses'] ?? null) }}</td>
                    <td>{{ $baris['lokasi'] ?: '-' }}</td>
                    <td>{{ $baris['tglMulai'] ?: '-' }}</td>
                    <td>{{ $baris['tglSelesai'] ?: '-' }}</td>
                    <td>{{ $baris['hariKe'] ?: '-' }}</td>
                    <td>{{ $flagAktif($petaTanda, $baris['tanda'] ?? []) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- 4. Tujuan pemasangan --}}
    <div class="sv-sec">4. TUJUAN PEMASANGAN</div>
    <table class="sv">
        <tr><td class="lbl">Tujuan</td><td>{{ $flagAktif('tujuanPemasangan', $form['tujuanPemasangan'] ?? []) }}</td></tr>
        <tr><td class="lbl">Keterangan</td><td>{{ $nilai('tujuanKeterangan') }}</td></tr>
    </table>

    {{-- 5. Kultur --}}
    <div class="sv-sec">5. HASIL KULTUR</div>
    <table class="sv">
        @forelse ($form['kulturDarahHasil'] ?? [] as $i => $baris)
            <tr>
                <td class="lbl">Kultur Darah ke-{{ $i + 1 }}</td>
                <td>{{ $baris['tgl'] ?: '-' }}</td>
                <td class="lbl">Hasil</td>
                <td>{{ $baris['hasil'] ?: '-' }}</td>
            </tr>
        @empty
            <tr><td class="lbl">Hasil Kultur Darah</td><td colspan="3">-</td></tr>
        @endforelse
        @forelse ($form['kulturPusHasil'] ?? [] as $i => $baris)
            <tr>
                <td class="lbl">Kultur Pus ke-{{ $i + 1 }}</td>
                <td>{{ $baris['tgl'] ?: '-' }}</td>
                <td class="lbl">Hasil</td>
                <td>{{ $baris['hasil'] ?: '-' }}</td>
            </tr>
        @empty
            <tr><td class="lbl">Hasil Kultur Pus</td><td colspan="3">-</td></tr>
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
