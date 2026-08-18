{{-- resources/views/pages/components/modul-dokumen/ri/surveilans-isk-ri/cetak-surveilans-isk-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="SURVEILANS HAIs — INFEKSI SALURAN KEMIH">

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
        $petaTanda = $usia === 'balita' ? 'tandaIskBalita' : 'tandaIskDewasa';

        $barisPasang = collect($form['pemasangan'] ?? [])->filter(fn($baris) => filled($baris['pasangKe'] ?? null) || filled($baris['tglMulai'] ?? null));
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

    {{-- 3. Pemasangan kateter urin --}}
    <div class="sv-sec">3. PEMASANGAN KATETER URIN</div>
    <table class="sv">
        <tr>
            <td class="lbl">Jenis Kateter</td><td>{{ $labelDari('jenisKateterIsk', $form['jenisKateter'] ?? null) }}</td>
            <td class="lbl">Tgl / Jam Pasang Pertama</td><td>{{ $nilai('tglPasangKateter') }}</td>
        </tr>
    </table>

    @if ($barisPasang->isNotEmpty())
        <table class="sv" style="margin-top:4px;">
            <tr>
                <th style="width:22%">Pemasangan</th>
                <th style="width:19%">Tgl Pasang</th>
                <th style="width:19%">s/d Tgl Lepas</th>
                <th style="width:8%">Hari Ke</th>
                <th>Tanda Infeksi</th>
            </tr>
            @foreach ($barisPasang as $i => $baris)
                <tr>
                    <td>{{ $baris['pasangKe'] ?: 'Pasang ke-' . ($i + 1) }}</td>
                    <td>{{ $baris['tglMulai'] ?: '-' }}</td>
                    <td>{{ $baris['tglSelesai'] ?: '-' }}</td>
                    <td>{{ $baris['hariKe'] ?: '-' }}</td>
                    <td>{{ $flagAktif($petaTanda, $baris['tanda'] ?? []) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- 4. Pemeriksaan & biakan urin --}}
    <div class="sv-sec">4. PEMERIKSAAN &amp; BIAKAN URIN</div>
    <table class="sv">
        @forelse ($form['pemeriksaanUrinHasil'] ?? [] as $i => $baris)
            <tr>
                <td class="lbl">Periksa Urin ke-{{ $i + 1 }}</td>
                <td>{{ $baris['tgl'] ?: '-' }}</td>
                <td class="lbl">Leukosit Urin</td>
                <td>{{ $baris['hasil'] ?: '-' }}</td>
            </tr>
        @empty
            <tr><td class="lbl">Hasil Pemeriksaan Urin</td><td colspan="3">-</td></tr>
        @endforelse
        @forelse ($form['biakanUrinHasil'] ?? [] as $i => $baris)
            <tr>
                <td class="lbl">Biakan Urin ke-{{ $i + 1 }}</td>
                <td>{{ $baris['tgl'] ?: '-' }}</td>
                <td class="lbl">Hasil</td>
                <td>{{ $baris['hasil'] ?: '-' }}</td>
            </tr>
        @empty
            <tr><td class="lbl">Hasil Biakan Urin</td><td colspan="3">-</td></tr>
        @endforelse
    </table>

    {{-- 5. Antibiotik --}}
    <div class="sv-sec">5. PEMAKAIAN ANTIBIOTIK</div>
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
