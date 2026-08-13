{{-- resources/views/pages/components/modul-dokumen/ri/identifikasi-bayi-ri/cetak-identifikasi-bayi-ri-print.blade.php --}}

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

    {{-- 1. Identitas --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">1. IDENTITAS BAYI & ORANG TUA</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Nama Ibu</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('namaIbu') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Nama Ayah</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('namaAyah') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">No. Register Ibu</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('noRegisterIbu') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">No. Register Bayi</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('noRegisterBayi') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Nama Bayi</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('namaBayi') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Jenis Kelamin</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('jenisKelamin') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Warna Gelang</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('warnaGelang') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Tgl / Jam Lahir</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('tglLahir') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Berat / Panjang</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('bb') }} gr / {{ $nilaiForm('pb') }} cm</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">APGAR Score</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('apgar') }}</td></tr>
    </table>

    {{-- 2. Serah Terima --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">2. SERAH TERIMA KE RUANG NEONATUS</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Penolong Persalinan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('penolongPersalinan') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Pemasang Gelang</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('pemasangGelang') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Yang Menyerahkan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('yangMenyerahkan') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Yang Menerima</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('yangMenerima') }}</td></tr>
    </table>

    {{-- 3. Cap Identifikasi --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">3. CAP IDENTIFIKASI</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Cap sidik jari ibu &amp; telapak kaki bayi</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('capDilakukan') == 'Sudah' ? 'Sudah dilakukan' : ($nilaiForm('capDilakukan') == 'Belum' ? 'Belum dilakukan' : '-') }}</td></tr>
    </table>
    <p style="font-size:9px; color:#555; margin-top:3px;">
        Catatan: Cap sidik jari ibu dan cap telapak kaki bayi dilakukan secara manual pada berkas rekam medis fisik.
    </p>

    {{-- 4. Pernyataan Pulang --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">4. PERNYATAAN SERAH TERIMA BAYI SAAT PULANG</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Pernyataan</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('serahTerimaPulang') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Saksi (Perawat/Bidan)</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('saksiPerawat') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Orang Tua Bayi</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('orangTuaBayi') }}</td></tr>
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
