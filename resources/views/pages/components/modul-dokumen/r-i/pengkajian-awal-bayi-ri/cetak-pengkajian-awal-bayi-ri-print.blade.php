{{-- resources/views/pages/components/modul-dokumen/r-i/pengkajian-awal-bayi-ri/cetak-pengkajian-awal-bayi-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="PENGKAJIAN AWAL BAYI">

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

        $apgarRows = [
            ['Warna Kulit', 'warnaKulit'],
            ['Reflek', 'reflek'],
            ['Denyut Jantung', 'denyutJantung'],
            ['Tonus', 'tonus'],
            ['Usaha Bernafas', 'usahaNafas'],
        ];
        $apgarMenit = ['1' => "1'", '5' => "5'", '10' => "10'"];
        $jumlahApgarMenit = fn(string $menit) => collect($apgarRows)->sum(fn(array $baris) => (int) ($form[$baris[1] . $menit] ?? 0));
        $lahirKeadaan = fn(string $fieldPilihan, string $fieldKeterangan) =>
            trim((filled($form[$fieldPilihan] ?? null) ? e($form[$fieldPilihan]) : '-') . (filled($form[$fieldKeterangan] ?? null) ? ' — ' . e($form[$fieldKeterangan]) : ''));
    @endphp

    <style>
        .pa-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:6px; }
        table.pa { width:100%; border-collapse:collapse; font-size:10px; }
        table.pa td { border:1px solid #999; padding:2px 5px; vertical-align:top; }
        table.pa td.lbl { width:22%; color:#333; background:#f7f7f7; }
        table.apgar { width:100%; border-collapse:collapse; font-size:10px; text-align:center; }
        table.apgar td, table.apgar th { border:1px solid #999; padding:2px 5px; }
        table.apgar th { background:#f7f7f7; }
        table.apgar td.lbl { text-align:left; background:#f7f7f7; width:40%; }
        table.apgar tr.sum td { font-weight:bold; background:#eef2ee; }
    </style>

    {{-- 1. Identitas Bayi --}}
    <div class="pa-sec">1. IDENTITAS BAYI</div>
    <table class="pa">
        <tr><td class="lbl">Tanggal / Jam Lahir</td><td>{{ $nilaiForm('tglLahir') }} {{ filled($form['jamLahir'] ?? null) ? e($form['jamLahir']) : '' }}</td><td class="lbl">Cara Persalinan</td><td>{{ $nilaiForm('caraPersalinan') }}</td></tr>
        <tr><td class="lbl">Nama Ayah</td><td>{{ $nilaiForm('namaAyah') }}</td><td class="lbl">Nama Ibu</td><td>{{ $nilaiForm('namaIbu') }}</td></tr>
        <tr><td class="lbl">Ruangan Ibu</td><td>{{ $nilaiForm('ruanganIbu') }}</td><td class="lbl">No. RM Ibu</td><td>{{ $nilaiForm('noRmIbu') }}</td></tr>
    </table>

    {{-- 2. Nilai APGAR --}}
    <div class="pa-sec">2. NILAI APGAR</div>
    <table class="apgar">
        <thead>
            <tr>
                <th class="lbl">Komponen</th>
                @foreach ($apgarMenit as $menitKode => $menitLabel)<th>Menit {{ $menitLabel }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($apgarRows as $baris)
                <tr>
                    <td class="lbl">{{ $baris[0] }}</td>
                    @foreach ($apgarMenit as $menitKode => $menitLabel)<td>{{ $nilaiForm($baris[1] . $menitKode) }}</td>@endforeach
                </tr>
            @endforeach
            <tr class="sum">
                <td class="lbl">Jumlah</td>
                @foreach ($apgarMenit as $menitKode => $menitLabel)<td>{{ $jumlahApgarMenit($menitKode) }}</td>@endforeach
            </tr>
        </tbody>
    </table>

    {{-- 3. Pemeriksaan Fisik --}}
    <div class="pa-sec">3. PEMERIKSAAN FISIK</div>
    <table class="pa">
        <tr><td class="lbl">Keadaan Tali Pusat</td><td>{{ $nilaiForm('keadaanTaliPusat') }}</td><td class="lbl">Jantung</td><td>{{ $nilaiForm('jantung') }}</td></tr>
        <tr><td class="lbl">Paru</td><td>{{ $nilaiForm('paru') }}</td><td class="lbl">Abdomen / Hati</td><td>{{ $nilaiForm('abdomenHati') }}</td></tr>
        <tr><td class="lbl">Limpa</td><td>{{ $nilaiForm('limpa') }}</td><td class="lbl">Anus</td><td>{{ $nilaiForm('anus') }}</td></tr>
        <tr><td class="lbl">Ekstremitas</td><td>{{ $nilaiForm('ekstremitas') }}</td><td class="lbl">Imunisasi</td><td>{{ $nilaiForm('imunisasi') }}</td></tr>
    </table>

    {{-- 4. Antropometri --}}
    <div class="pa-sec">4. ANTROPOMETRI</div>
    <table class="pa">
        <tr><td class="lbl">Lingkar Kepala</td><td>{{ $nilaiForm('lingkarKepala') }} cm</td><td class="lbl">Berat Badan</td><td>{{ $nilaiForm('beratBadan') }} gr</td></tr>
        <tr><td class="lbl">Tinggi Badan</td><td>{{ $nilaiForm('tinggiBadan') }} cm</td><td class="lbl">Lingkar Dada</td><td>{{ $nilaiForm('lingkarDada') }} cm</td></tr>
        <tr><td class="lbl">Jenis Kelamin</td><td colspan="3">{{ $nilaiForm('jenisKelamin') }}</td></tr>
    </table>

    {{-- 5. Keadaan Bayi Waktu Lahir --}}
    <div class="pa-sec">5. KEADAAN BAYI WAKTU LAHIR</div>
    <table class="pa">
        <tr><td class="lbl">Sianosis</td><td colspan="3">{{ $lahirKeadaan('sianosis', 'sianosisKet') }}</td></tr>
        <tr><td class="lbl">Asphyxia</td><td colspan="3">{{ $lahirKeadaan('asphyxia', 'asphyxiaKet') }}</td></tr>
        <tr><td class="lbl">Trauma Lahir</td><td colspan="3">{{ $lahirKeadaan('traumaLahir', 'traumaLahirKet') }}</td></tr>
    </table>

    {{-- 6. Diagnosa --}}
    <div class="pa-sec">6. DIAGNOSA</div>
    <table class="pa">
        <tr><td class="lbl">Diagnosa Utama</td><td colspan="3">{{ $nilaiForm('diagnosaUtama') }}</td></tr>
    </table>

    {{-- 7. Rencana --}}
    <div class="pa-sec">7. RENCANA</div>
    <table class="pa">
        <tr><td class="lbl">Rencana Diagnosa</td><td colspan="3">{{ $nilaiForm('rencanaDiagnosa') }}</td></tr>
        <tr><td class="lbl">Terapi</td><td colspan="3">{{ $nilaiForm('terapi') }}</td></tr>
        <tr><td class="lbl">Diet</td><td colspan="3">{{ $nilaiForm('diet') }}</td></tr>
        <tr><td class="lbl">Edukasi</td><td colspan="3">{{ $nilaiForm('edukasi') }}</td></tr>
        <tr><td class="lbl">Monitoring</td><td colspan="3">{{ $nilaiForm('monitoring') }}</td></tr>
        <tr><td class="lbl">Discharge Planning</td><td colspan="3">{{ $nilaiForm('dischargePlanning') }}</td></tr>
    </table>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $data['tglCetak'] ?? '' }}<br>
                Dokter<br>
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
