{{-- resources/views/pages/components/modul-dokumen/r-i/edukasi-pasien/cetak-edukasi-pasien-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="FORMULIR EDUKASI PASIEN — RAWAT INAP">

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
            :alamat="$alamatPasien">
            @if (!empty($data['dataRi']['riHdrNo']))
                <tr>
                    <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Rawat Inap</td>
                    <td class="py-0.5 text-[11px] px-1">:</td>
                    <td class="py-0.5 text-[11px] font-bold">{{ $data['dataRi']['riHdrNo'] }}</td>
                </tr>
            @endif
        </x-pdf.identitas-pasien>
    </x-slot>

    @php
        $entri = $data['entry'] ?? [];
        $identitasRs = $data['identitasRs'] ?? null;
        $rsName = $identitasRs->int_name ?? 'RSI MADINAH';
        $rsAddress = $identitasRs->int_address ?? '';

        $tglEdukasi   = $entri['tglEdukasi'] ?? '-';
        $petugas      = $entri['petugasEdukasi'] ?? '-';
        $sasaran      = $entri['sasaranEdukasi'] ?? '-';
        $hubungan     = $entri['hubunganSasaranEdukasidgnPasien'] ?? '-';

        $kategori     = data_get($entri, 'edukasi.kategoriEdukasi', []);
        // Centang aman-font: font default dompdf tak punya glyph U+2713 -> tampil "?".
        $centang      = '<span style="font-family: DejaVu Sans, sans-serif;">&#10003;</span>';
        $materiTopik  = data_get($entri, 'edukasi.materiTopikEdukasi', '');
        $keterangan   = data_get($entri, 'edukasi.keteranganEdukasi', '');
        $status       = data_get($entri, 'edukasi.statusEdukasi', '');
        $reEdukasiPerlu   = (bool) data_get($entri, 'edukasi.reEdukasi.perlu', false);
        $reEdukasiTanggal = data_get($entri, 'edukasi.reEdukasi.tglReEdukasi', '');
        $reEdukasiPetugas = data_get($entri, 'edukasi.reEdukasi.petugasReEdukasi', '');
        $sasaranTTD   = $entri['sasaranEdukasiSignature'] ?? '';
    @endphp

    <table class="w-full text-[10px] border-collapse" cellpadding="0" cellspacing="0">

        {{-- ── HEADER: Tanggal & Petugas ── --}}
        <tr>
            <td class="border border-black px-1.5 py-1 w-1/2">
                <strong>Tanggal Edukasi:</strong> {{ $tglEdukasi }}
            </td>
            <td class="border border-black px-1.5 py-1 w-1/2">
                <strong>Petugas Edukasi:</strong> {{ $petugas }}
            </td>
        </tr>

        {{-- ── SASARAN EDUKASI ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <strong>Sasaran Edukasi:</strong> {{ $sasaran }}
                <span class="text-gray-600">({{ $hubungan }})</span>
            </td>
        </tr>

        {{-- ── 1. MATERI EDUKASI ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <p class="font-bold mb-1">1. Materi / Topik Edukasi</p>
                <div class="mb-1">{{ $materiTopik !== '' ? $materiTopik : '-' }}</div>
                @if (!empty($kategori))
                    <p class="mb-0.5"><strong>Kategori Edukasi:</strong></p>
                    <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                        @foreach (array_chunk((array) $kategori, 2) as $pasanganKategori)
                            <tr>
                                @foreach ($pasanganKategori as $kategoriItem)
                                    <td class="py-0.5 w-1/2">{!! $centang !!} {{ $kategoriItem }}</td>
                                @endforeach
                                @if (count($pasanganKategori) === 1)
                                    <td class="py-0.5 w-1/2"></td>
                                @endif
                            </tr>
                        @endforeach
                    </table>
                @else
                    <span class="text-gray-500">-</span>
                @endif
            </td>
        </tr>

        {{-- ── 2. KETERANGAN / CATATAN ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <p class="font-bold mb-1">2. Keterangan / Catatan Edukasi</p>
                <div>{{ $keterangan !== '' ? $keterangan : '-' }}</div>
            </td>
        </tr>

        {{-- ── 3. EVALUASI & TINDAK LANJUT ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <p class="font-bold mb-1">3. Evaluasi & Tindak Lanjut</p>
                <div><strong>Status Pemahaman:</strong> {{ $status !== '' ? $status : '-' }}</div>
                <div class="mt-0.5">
                    <strong>Re-Edukasi:</strong>
                    @if ($reEdukasiPerlu)
                        Perlu
                        @if (!empty($reEdukasiTanggal))
                            &nbsp;&bull;&nbsp; Tanggal: {{ $reEdukasiTanggal }}
                        @endif
                        @if (!empty($reEdukasiPetugas))
                            &nbsp;&bull;&nbsp; Petugas: {{ $reEdukasiPetugas }}
                        @endif
                    @else
                        Tidak perlu
                    @endif
                </div>
            </td>
        </tr>

        {{-- ── TANDA TANGAN — 2 kolom ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        {{-- Kiri: Sasaran Edukasi (Pasien/Keluarga) --}}
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Sasaran Edukasi</p>
                            <div class="text-center my-1">
                                @if (!empty($sasaranTTD))
                                    <img src="{{ $sasaranTTD }}" class="h-16" alt="Tanda Tangan Sasaran" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>
                            <div class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                                <p class="font-bold">{{ strtoupper($sasaran !== '-' ? $sasaran : '-') }}</p>
                                <p class="text-[9px] text-gray-500">{{ $hubungan }}</p>
                            </div>
                        </td>

                        {{-- Garis pemisah --}}
                        <td style="border-left: 1px solid #d1d5db; width: 1px;"></td>

                        {{-- Kanan: Petugas Pemberi Edukasi --}}
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Petugas Pemberi Edukasi</p>
                            <div class="text-center my-1">
                                @if (!empty($data['ttdPetugasPath']))
                                    <img src="{{ $data['ttdPetugasPath'] }}" class="h-16" alt="Tanda Tangan Petugas" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>
                            <div class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                                <p class="font-bold">{{ strtoupper($petugas !== '-' ? $petugas : '-') }}</p>
                                <p class="text-[9px] text-gray-500">{{ $data['tglCetak'] ?? '-' }}</p>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── FOOTER INFO ── --}}
        <tr>
            <td colspan="2" class="px-1.5 py-1 text-[9px] text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                {{ $rsName }}, {{ $rsAddress }}
            </td>
        </tr>

    </table>

</x-pdf.layout-a4-with-out-background>
