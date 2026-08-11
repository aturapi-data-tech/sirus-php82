{{-- resources/views/pages/components/modul-dokumen/r-i/identifikasi-bayi-ri/cetak-identifikasi-bayi-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="IDENTIFIKASI BAYI">

    {{-- ── IDENTITAS PASIEN (IBU) ── --}}
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
    @endphp

    <style>
        .ib-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:6px; }
        table.ib { width:100%; border-collapse:collapse; font-size:10px; }
        table.ib td { border:1px solid #999; padding:2px 5px; vertical-align:top; }
        table.ib td.lbl { width:22%; color:#333; background:#f7f7f7; }
    </style>

    {{-- 1. Identitas --}}
    <div class="ib-sec">1. IDENTITAS BAYI & ORANG TUA</div>
    <table class="ib">
        <tr><td class="lbl">Nama Ibu</td><td>{{ $nilaiForm('namaIbu') }}</td><td class="lbl">Nama Ayah</td><td>{{ $nilaiForm('namaAyah') }}</td></tr>
        <tr><td class="lbl">No. Register Ibu</td><td>{{ $nilaiForm('noRegisterIbu') }}</td><td class="lbl">No. Register Bayi</td><td>{{ $nilaiForm('noRegisterBayi') }}</td></tr>
        <tr><td class="lbl">Nama Bayi</td><td>{{ $nilaiForm('namaBayi') }}</td><td class="lbl">Jenis Kelamin</td><td>{{ $nilaiForm('jenisKelamin') }}</td></tr>
        <tr><td class="lbl">Warna Gelang</td><td>{{ $nilaiForm('warnaGelang') }}</td><td class="lbl">Tgl / Jam Lahir</td><td>{{ trim($nilaiForm('tglLahir') . ' ' . $nilaiForm('jamLahir')) }}</td></tr>
        <tr><td class="lbl">Berat / Panjang</td><td>{{ $nilaiForm('bb') }} gr / {{ $nilaiForm('pb') }} cm</td><td class="lbl">APGAR Score</td><td>{{ $nilaiForm('apgar') }}</td></tr>
    </table>

    {{-- 2. Serah Terima --}}
    <div class="ib-sec">2. SERAH TERIMA KE RUANG NEONATUS</div>
    <table class="ib">
        <tr><td class="lbl">Penolong Persalinan</td><td>{{ $nilaiForm('penolongPersalinan') }}</td><td class="lbl">Pemasang Gelang</td><td>{{ $nilaiForm('pemasangGelang') }}</td></tr>
        <tr><td class="lbl">Yang Menyerahkan</td><td>{{ $nilaiForm('yangMenyerahkan') }}</td><td class="lbl">Yang Menerima</td><td>{{ $nilaiForm('yangMenerima') }}</td></tr>
    </table>

    {{-- 3. Cap Identifikasi --}}
    <div class="ib-sec">3. CAP IDENTIFIKASI</div>
    <table class="ib">
        <tr><td class="lbl">Cap sidik jari ibu &amp; telapak kaki bayi</td><td>{{ $nilaiForm('capDilakukan') == 'Sudah' ? 'Sudah dilakukan' : ($nilaiForm('capDilakukan') == 'Belum' ? 'Belum dilakukan' : '-') }}</td></tr>
    </table>
    <p style="font-size:9px; color:#555; margin-top:3px;">
        Catatan: Cap sidik jari ibu dan cap telapak kaki bayi dilakukan secara manual pada berkas rekam medis fisik.
    </p>

    {{-- 4. Pernyataan Pulang --}}
    <div class="ib-sec">4. PERNYATAAN SERAH TERIMA BAYI SAAT PULANG</div>
    <table class="ib">
        <tr><td class="lbl">Pernyataan</td><td colspan="3">{{ $nilaiForm('serahTerimaPulang') }}</td></tr>
        <tr><td class="lbl">Saksi (Perawat/Bidan)</td><td>{{ $nilaiForm('saksiPerawat') }}</td><td class="lbl">Orang Tua Bayi</td><td>{{ $nilaiForm('orangTuaBayi') }}</td></tr>
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
