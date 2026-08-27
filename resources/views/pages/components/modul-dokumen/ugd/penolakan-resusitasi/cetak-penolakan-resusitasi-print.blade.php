{{-- resources/views/pages/components/modul-dokumen/ugd/penolakan-resusitasi/cetak-penolakan-resusitasi-print.blade.php --}}

@use('App\Support\Clause\PenolakanResusitasiClause')
@use('App\Support\Options\PenolakanResusitasiOptions')

<x-pdf.layout-a4-with-out-background title="SURAT PERNYATAAN PENOLAKAN TINDAKAN RESUSITASI (DNR)">

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

        // Peta label tindakan = SUMBER TUNGGAL (App\Support\Options\PenolakanResusitasiOptions)
        $lingkupText = PenolakanResusitasiOptions::lingkupTerpilih($form['lingkupPenolakan'] ?? []);

        // Teks klausa per-versi (SUMBER TUNGGAL: App\Support\Clause\PenolakanResusitasiClause; fallback v1 utk record legacy)
        $clause = PenolakanResusitasiClause::get($form['clauseVersion'] ?? 'v1');
    @endphp

    {{-- ── DATA PEMBUAT PERNYATAAN ── --}}
    <div class="text-[10px] leading-snug mb-2">
        <p class="mb-1">{{ $clause['pembukaIntro'] }}</p>
    </div>
    <table class="w-full text-[10px] border-collapse mb-2">
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
    <div class="text-[10px] leading-snug mb-2">
        <p>{{ $clause['statementPre'] }}</p>
    </div>
    <table class="w-full text-[10px] border-collapse mb-2">
        @forelse ($lingkupText as $lingkupSatu)
            <tr>
                <td class="px-1 py-0 align-top w-4 text-right">{{ $loop->iteration }}.</td>
                <td class="px-1 py-0 font-bold">{{ $lingkupSatu }}</td>
            </tr>
        @empty
            <tr>
                <td class="px-1 py-0.5 align-top w-4 text-right">-</td>
                <td class="px-1 py-0.5">-</td>
            </tr>
        @endforelse
    </table>
    <div class="text-[10px] leading-snug mb-2">
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
    <div class="mb-2">
        <x-pdf.identitas-pasien textClass="text-[10px]"
            :rm="$data['regNo'] ?? null"
            :nama="$data['regName'] ?? null"
            :jenisKelamin="$data['jenisKelamin']['jenisKelaminDesc'] ?? null"
            :tempatLahir="$data['tempatLahir'] ?? null"
            :tglLahir="$data['tglLahir'] ?? null"
            :umur="$data['thn'] ?? null"
            :alamat="$alamatPasien" />
    </div>

    {{-- ── KONDISI PASIEN & PENJELASAN DOKTER ── --}}
    <table class="w-full text-[10px] border-collapse mb-2">
        <tr>
            <td class="px-1 py-0.5 w-1/3 align-top">Diagnosis</td>
            <td class="px-1 py-0.5 w-2 align-top">:</td>
            <td class="px-1 py-0.5 font-bold">{{ ($form['diagnosis'] ?? '') ?: '-' }}</td>
        </tr>
        @if (!empty($form['dasarDiagnosis']))
            <tr>
                <td class="px-1 py-0.5 align-top">Dasar Diagnosis</td>
                <td class="px-1 py-0.5 align-top">:</td>
                <td class="px-1 py-0.5">{{ $form['dasarDiagnosis'] }}</td>
            </tr>
        @endif
        <tr>
            <td class="px-1 py-0.5 align-top">Dokter Penanggung Jawab Pelayanan</td>
            <td class="px-1 py-0.5 align-top">:</td>
            <td class="px-1 py-0.5">{{ ($form['dokterNama'] ?? '') ?: '-' }}</td>
        </tr>
        <tr>
            <td class="px-1 py-0.5 align-top">Berlaku Sejak</td>
            <td class="px-1 py-0.5 align-top">:</td>
            <td class="px-1 py-0.5">{{ ($form['mulaiBerlaku'] ?? '') ?: '-' }}</td>
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

    {{-- ── KLAUSUL PENJELASAN, PERAWATAN LAIN, PENCABUTAN & TANGGUNG JAWAB ── --}}
    <div class="text-[10px] leading-snug mb-2">
        <p class="mb-1">{{ $clause['penjelasanRisiko'] }}</p>
        <p class="mb-1">{{ $clause['perawatanTetap'] }}</p>
        <p class="mb-1">{{ $clause['pencabutan'] }}</p>
        <p class="mb-1">{{ $clause['tanggungJawab'] }}</p>
    </div>

    {{-- ── PENUTUP ── --}}
    <div class="text-[10px] leading-snug mb-2">
        <p>{{ $clause['penutup'] }}</p>
    </div>

    {{-- ── TANDA TANGAN ── --}}
    <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
        <tr>
            {{-- Yang membuat pernyataan (KIRI — pembuat kiri, saksi tengah, petugas kanan) --}}
            <td class="w-1/3 align-top text-center px-2 py-0">
                <p class="font-bold mb-1">Yang membuat pernyataan</p>
                <p class="text-[9px] text-gray-500 mb-1">{{ $data['tglCetak'] ?? '-' }}</p>

                <div class="text-center">
                    @if (!empty($form['signature']))
                        <img src="{{ $form['signature'] }}" class="h-12" alt="Tanda Tangan Pembuat Pernyataan" />
                    @else
                        <div class="h-12">&nbsp;</div>
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
            <td class="w-1/3 align-top text-center px-2 py-0">
                <p class="font-bold mb-1">Saksi</p>
                <p class="text-[9px] text-gray-500 mb-1">&nbsp;</p>

                <div class="text-center">
                    @if (!empty($form['signatureSaksi']))
                        <img src="{{ $form['signatureSaksi'] }}" class="h-12" alt="Tanda Tangan Saksi" />
                    @else
                        <div class="h-12">&nbsp;</div>
                    @endif
                </div>

                <div class="border-t border-black pt-[3px] mt-1 min-w-[120px] inline-block">
                    <p class="font-bold">{{ strtoupper(($form['saksiNama'] ?? '') ?: '-') }}</p>
                </div>
            </td>

            {{-- Petugas RS (KANAN) --}}
            <td class="w-1/3 align-top text-center px-2 py-0">
                <p class="font-bold mb-1">Petugas RS</p>
                <p class="text-[9px] text-gray-500 mb-1">{{ $form['petugasDate'] ?? '-' }}</p>

                <div class="text-center">
                    @if (!empty($data['ttdPetugasPath']))
                        <img src="{{ $data['ttdPetugasPath'] }}" class="h-12" alt="Tanda Tangan Petugas RS" />
                    @else
                        <div class="h-12">&nbsp;</div>
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
    <table class="w-full text-[9px] mt-1">
        <tr>
            <td class="px-1.5 py-0.5 text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                {{ $rsName }}{{ $rsAddress ? ', ' . $rsAddress : '' }}
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
