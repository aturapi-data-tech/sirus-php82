{{-- resources/views/pages/components/modul-dokumen/r-i/penolakan-obat-ri/cetak-penolakan-obat-ri-print.blade.php --}}

@use('App\Support\PenolakanObatClause')

<x-pdf.layout-a4-with-out-background title="SURAT PERNYATAAN PENOLAKAN PENGOBATAN / OBAT TERTENTU">

    {{-- Identitas pasien TIDAK di header — ditampilkan di body ("terhadap pasien di bawah ini") --}}

    @php
        $form = $data['form'] ?? [];
        $identitasRs = $data['identitasRs'] ?? null;
        $rsName = $identitasRs->int_name ?? 'RSI MADINAH';
        $rsAddress = $identitasRs->int_address ?? '';

        $hubunganMap = [
            'pasien' => 'Diri Sendiri (Pasien)',
            'suami' => 'Suami',
            'istri' => 'Istri',
            'ayah' => 'Ayah',
            'ibu' => 'Ibu',
            'anak' => 'Anak',
            'saudara' => 'Saudara',
            'wali_hukum' => 'Wali Hukum',
            'lainnya' => 'Lainnya',
        ];
        $hubunganText = $hubunganMap[$form['hubunganPasien'] ?? ''] ?? '-';
        $namaObatText = ($form['namaObat'] ?? '') ?: '-';

        // Teks klausa per-versi (SUMBER TUNGGAL: App\Support\PenolakanObatClause; fallback v1 utk record legacy)
        $clause = PenolakanObatClause::get($form['clauseVersion'] ?? 'v1');
    @endphp

    {{-- ── DATA PEMBUAT PERNYATAAN ── --}}
    <div class="text-[11px] leading-relaxed mb-2">
        <p class="mb-1">{{ $clause['pembukaIntro'] }}</p>
    </div>
    <table class="w-full text-[10px] border-collapse mb-3">
        <tr>
            <td class="px-1 py-0.5 w-1/3">Nama</td>
            <td class="px-1 py-0.5 w-2">:</td>
            <td class="px-1 py-0.5">{{ $form['pembuatNama'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="px-1 py-0.5">Hubungan dengan Pasien</td>
            <td class="px-1 py-0.5">:</td>
            <td class="px-1 py-0.5">{{ $hubunganText }}</td>
        </tr>
    </table>

    {{-- ── PERNYATAAN PENOLAKAN ── --}}
    <div class="text-[11px] leading-relaxed mb-2">
        <p>{{ $clause['statementPre'] }}</p>
    </div>
    <table class="w-full text-[10px] border-collapse mb-2">
        <tr>
            <td class="px-1 py-0.5 w-1/3">Nama Pengobatan / Obat</td>
            <td class="px-1 py-0.5 w-2">:</td>
            <td class="px-1 py-0.5 font-bold">{{ $namaObatText }}</td>
        </tr>
        @if (!empty($form['alasanPenolakan']))
            <tr>
                <td class="px-1 py-0.5 align-top">Alasan Penolakan</td>
                <td class="px-1 py-0.5 align-top">:</td>
                <td class="px-1 py-0.5">{{ $form['alasanPenolakan'] }}</td>
            </tr>
        @endif
        @if (!empty($form['risikoDijelaskan']))
            <tr>
                <td class="px-1 py-0.5 align-top">Risiko / Akibat yang Telah Dijelaskan</td>
                <td class="px-1 py-0.5 align-top">:</td>
                <td class="px-1 py-0.5">{{ $form['risikoDijelaskan'] }}</td>
            </tr>
        @endif
    </table>
    <div class="text-[11px] leading-relaxed mb-2">
        <p>{{ $clause['statementPost'] }}</p>
    </div>

    {{-- ── DATA PASIEN (blok identitas STANDAR — satu-satunya, di body) ── --}}
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
    <div class="mb-3">
        <x-pdf.identitas-pasien
            :rm="$data['regNo'] ?? null"
            :nama="$data['regName'] ?? null"
            :jenisKelamin="$data['jenisKelamin']['jenisKelaminDesc'] ?? null"
            :tempatLahir="$data['tempatLahir'] ?? null"
            :tglLahir="$data['tglLahir'] ?? null"
            :umur="$data['thn'] ?? null"
            :alamat="$alamatPasien" />
    </div>

    {{-- ── KLAUSUL PENJELASAN & TANGGUNG JAWAB ── --}}
    <div class="text-[11px] leading-relaxed mb-2">
        <p class="mb-2">{{ $clause['penjelasanRisiko'] }}</p>
        <p class="mb-2">{{ $clause['tanggungJawab'] }}</p>
    </div>

    {{-- ── PENUTUP ── --}}
    <div class="text-[11px] leading-relaxed mb-4">
        <p>{{ $clause['penutup'] }}</p>
    </div>

    {{-- ── TANDA TANGAN ── --}}
    <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
        <tr>
            {{-- Yang membuat pernyataan (KIRI — pembuat kiri, saksi tengah, petugas kanan) --}}
            <td class="w-1/3 align-top text-center px-3 py-2">
                <p class="font-bold mb-1">Yang membuat pernyataan</p>
                <p class="text-[9px] text-gray-500 mb-2">{{ $data['tglCetak'] ?? '-' }}</p>

                <div class="text-center my-1">
                    @if (!empty($form['signature']))
                        <img src="{{ $form['signature'] }}" class="h-16" alt="Tanda Tangan Pembuat Pernyataan" />
                    @else
                        <div class="h-16">&nbsp;</div>
                    @endif
                </div>

                <div class="border-t border-black pt-[3px] mt-1 min-w-[120px] inline-block">
                    <p class="font-bold">{{ strtoupper($form['pembuatNama'] ?? '-') }}</p>
                    <p class="text-[9px] text-gray-500">{{ $hubunganText }}</p>
                    @if (!empty($form['signatureDate']))
                        <p class="text-[9px] text-gray-500">{{ $form['signatureDate'] }}</p>
                    @endif
                </div>
            </td>

            {{-- Saksi (TENGAH — opsional) --}}
            <td class="w-1/3 align-top text-center px-3 py-2">
                <p class="font-bold mb-1">Saksi</p>
                <p class="text-[9px] text-gray-500 mb-2">&nbsp;</p>

                <div class="text-center my-1">
                    @if (!empty($form['signatureSaksi']))
                        <img src="{{ $form['signatureSaksi'] }}" class="h-16" alt="Tanda Tangan Saksi" />
                    @else
                        <div class="h-16">&nbsp;</div>
                    @endif
                </div>

                <div class="border-t border-black pt-[3px] mt-1 min-w-[120px] inline-block">
                    <p class="font-bold">{{ strtoupper(($form['saksiNama'] ?? '') ?: '-') }}</p>
                </div>
            </td>

            {{-- Petugas RS (KANAN) --}}
            <td class="w-1/3 align-top text-center px-3 py-2">
                <p class="font-bold mb-1">Petugas RS</p>
                <p class="text-[9px] text-gray-500 mb-2">{{ $form['petugasDate'] ?? '-' }}</p>

                <div class="text-center my-1">
                    @if (!empty($data['ttdPetugasPath']))
                        <img src="{{ $data['ttdPetugasPath'] }}" class="h-16" alt="Tanda Tangan Petugas RS" />
                    @else
                        <div class="h-16">&nbsp;</div>
                    @endif
                </div>

                <div class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                    <p class="font-bold">{{ strtoupper($form['petugas'] ?? '-') }}</p>
                    @if (!empty($form['petugasCode']))
                        <p class="text-[9px] text-gray-500">Kode: {{ $form['petugasCode'] }}</p>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- ── FOOTER INFO ── --}}
    <table class="w-full text-[9px] mt-4">
        <tr>
            <td class="px-1.5 py-1 text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                {{ $rsName }}{{ $rsAddress ? ', ' . $rsAddress : '' }}
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
