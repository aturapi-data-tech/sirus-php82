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

    {{-- 1. Identitas Bayi --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">1. IDENTITAS BAYI</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Tanggal / Jam Lahir</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('tglLahir') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Cara Persalinan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('caraPersalinan') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Nama Ayah</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('namaAyah') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Nama Ibu</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('namaIbu') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Ruangan Ibu</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('ruanganIbu') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">No. RM Ibu</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('noRmIbu') }}</td></tr>
    </table>

    {{-- 2. Nilai APGAR --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">2. NILAI APGAR</div>
    <table class="w-full border-collapse text-[10px] text-center">
        <thead>
            <tr>
                <th class="bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px]">Komponen</th>
                @foreach ($apgarMenit as $menitKode => $menitLabel)<th class="bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px]">Menit {{ $menitLabel }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($apgarRows as $baris)
                <tr>
                    <td class="text-left bg-[#f7f7f7] w-[40%] border border-[#999] px-[5px] py-[2px]">{{ $baris[0] }}</td>
                    @foreach ($apgarMenit as $menitKode => $menitLabel)<td class="border border-[#999] px-[5px] py-[2px]">{{ $nilaiForm($baris[1] . $menitKode) }}</td>@endforeach
                </tr>
            @endforeach
            <tr>
                <td class="text-left w-[40%] font-bold bg-[#eef2ee] border border-[#999] px-[5px] py-[2px]">Jumlah</td>
                @foreach ($apgarMenit as $menitKode => $menitLabel)<td class="font-bold bg-[#eef2ee] border border-[#999] px-[5px] py-[2px]">{{ $jumlahApgarMenit($menitKode) }}</td>@endforeach
            </tr>
        </tbody>
    </table>

    {{-- 3. Pemeriksaan Fisik --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">3. PEMERIKSAAN FISIK</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Keadaan Tali Pusat</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('keadaanTaliPusat') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Jantung</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('jantung') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Paru</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('paru') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Abdomen / Hati</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('abdomenHati') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Limpa</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('limpa') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Anus</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('anus') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Ekstremitas</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('ekstremitas') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Imunisasi</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('imunisasi') }}</td></tr>
    </table>

    {{-- 4. Antropometri --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">4. ANTROPOMETRI</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Lingkar Kepala</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('lingkarKepala') }} cm</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Berat Badan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('beratBadan') }} gr</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Tinggi Badan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('tinggiBadan') }} cm</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Lingkar Dada</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('lingkarDada') }} cm</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Jenis Kelamin</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('jenisKelamin') }}</td></tr>
    </table>

    {{-- 5. Keadaan Bayi Waktu Lahir --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">5. KEADAAN BAYI WAKTU LAHIR</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Sianosis</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $lahirKeadaan('sianosis', 'sianosisKet') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Asphyxia</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $lahirKeadaan('asphyxia', 'asphyxiaKet') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Trauma Lahir</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $lahirKeadaan('traumaLahir', 'traumaLahirKet') }}</td></tr>
    </table>

    {{-- 6. Diagnosa --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">6. DIAGNOSA</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Diagnosa Utama</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('diagnosaUtama') }}</td></tr>
    </table>

    {{-- 7. Rencana --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">7. RENCANA</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Rencana Diagnosa</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('rencanaDiagnosa') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Terapi</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('terapi') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Diet</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('diet') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Edukasi</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('edukasi') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Monitoring</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('monitoring') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Discharge Planning</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('dischargePlanning') }}</td></tr>
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
