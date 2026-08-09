{{-- resources/views/pages/components/modul-dokumen/r-i/pengkajian-pre-op-ri/cetak-pengkajian-pre-op-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="PENGKAJIAN PRE OPERASI">

    {{-- ── IDENTITAS PASIEN ── --}}
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
        $identitasRs = $data['identitasRs'] ?? null;
        $rsName = $identitasRs->int_name ?? 'RSI MADINAH';
        $rsAddress = $identitasRs->int_address ?? '';

        $val = fn($nilai) => filled($nilai) ? e($nilai) : '-';
        $yn = fn($nilai) => !empty($nilai) ? 'Ya' : 'Tidak';
    @endphp

    <table class="w-full text-[10px] border-collapse" cellpadding="0" cellspacing="0">

        {{-- ── DATA OPERASI ── --}}
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p><span class="font-bold">Diagnosa Pre Op:</span> {!! $val($form['diagnosaPreOp'] ?? '') !!}</p>
                <p><span class="font-bold">Rencana Operasi:</span> {!! $val($form['rencanaOperasi'] ?? '') !!}</p>
                <p><span class="font-bold">Dokter Operator:</span> {!! $val($form['dokterOperator'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p><span class="font-bold">Tgl/Jam Operasi:</span> {!! $val($form['tanggalOperasi'] ?? '') !!}</p>
                <p><span class="font-bold">Perjanjian dgn Perawat OK:</span> {!! $val($form['perjanjianPerawatOk'] ?? '') !!}</p>
                <p><span class="font-bold">Urgensi:</span> {!! $val($form['urgensi'] ?? '') !!}</p>
            </td>
        </tr>

        {{-- ── KEADAAN PRA BEDAH ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <span class="font-bold">Keadaan Pra Bedah:</span>
                TD {!! $val($form['sistolik'] ?? '') !!}/{!! $val($form['diastolik'] ?? '') !!} mmHg ·
                N {!! $val($form['nadi'] ?? '') !!} · RR {!! $val($form['rr'] ?? '') !!} ·
                S {!! $val($form['suhu'] ?? '') !!} · SPO2 {!! $val($form['spo2'] ?? '') !!} ·
                GDA {!! $val($form['gda'] ?? '') !!} ·
                BB {!! $val($form['bb'] ?? '') !!} · TB {!! $val($form['tb'] ?? '') !!} ·
                IMT {!! $val($form['imt'] ?? '') !!} ·
                Hb {!! $val($form['hb'] ?? '') !!} ·
                Gol. Darah {!! $val($form['golDarah'] ?? '') !!}
            </td>
        </tr>

        {{-- ── PERSIAPAN PASIEN ── --}}
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p class="font-bold mb-0.5">Persiapan Pasien</p>
                <p class="font-bold">Pre Medikasi / Cairan / Obat:</p>
                @forelse ($form['persiapanObatCairan'] ?? [] as $persiapanItem)
                    <p>
                        {{ $loop->iteration }}. {{ $persiapanItem['jenis'] ?? '-' }}:
                        {!! $val($persiapanItem['nama'] ?? '') !!}{{ !empty($persiapanItem['tglJam']) ? ' · ' . $persiapanItem['tglJam'] : '' }}
                    </p>
                @empty
                    <p>-</p>
                @endforelse
                <p>Tgl/Jam Mulai Puasa: {!! $val($form['puasaMulaiJam'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p>Dicukur/dibersihkan: <span class="font-bold">{{ $yn($form['sudahDicukur'] ?? false) }}</span></p>
                <p>Persiapan darah: <span class="font-bold">{{ $yn($form['persiapanDarah'] ?? false) }}</span></p>
                <p>Gigi palsu/lensa/perhiasan dilepas: <span class="font-bold">{{ $yn($form['gigiPalsuDilepas'] ?? false) }}</span></p>
                <p>Pengosongan kandung kemih: <span class="font-bold">{{ $yn($form['pengosonganKandungKemih'] ?? false) }}</span></p>
                <p>Clysma/glyserin: <span class="font-bold">{{ $yn($form['clysma'] ?? false) }}</span></p>
                <p>Riwayat penyakit: <span class="font-bold">{{ $yn($form['riwayatPenyakit'] ?? false) }}</span>
                    @if (!empty($form['riwayatPenyakit']) && filled($form['riwayatPenyakitKet'] ?? ''))
                        — {!! e($form['riwayatPenyakitKet']) !!}
                    @endif
                </p>
            </td>
        </tr>

        @if (filled($form['lainLain'] ?? ''))
            <tr>
                <td colspan="2" class="border border-black px-2 py-1.5">
                    <span class="font-bold">Lain-lain:</span> {!! e($form['lainLain']) !!}
                </td>
            </tr>
        @endif

        {{-- ── PERSIAPAN ADMINISTRASI ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p class="font-bold mb-0.5">Persiapan Administrasi (disertakan ke OK)</p>
                <p>
                    Rekam Medis: <span class="font-bold">{{ $yn($form['adaRekamMedis'] ?? false) }}</span> &nbsp;&bull;&nbsp;
                    Surat Ijin Operasi: <span class="font-bold">{{ $yn($form['adaSuratIjin'] ?? false) }}</span> &nbsp;&bull;&nbsp;
                    Lab: <span class="font-bold">{{ $yn($form['adaLab'] ?? false) }}</span>
                </p>
                <p>
                    Radiologi: <span class="font-bold">{{ $yn($form['adaRadiologi'] ?? false) }}</span>{{ filled($form['radiologiJenis'] ?? '') ? ' (' . e($form['radiologiJenis']) . ')' : '' }}
                    &nbsp;&bull;&nbsp;
                    Diagnostik: <span class="font-bold">{{ $yn($form['adaDiagnostik'] ?? false) }}</span>{{ filled($form['diagnostikJenis'] ?? '') ? ' (' . e($form['diagnostikJenis']) . ')' : '' }}
                </p>
            </td>
        </tr>

        {{-- ── PENANDAAN LOKASI OPERASI (SITE MARKING, SKP 4) ── --}}
        @php
            $perluPenandaan = ($form['perluPenandaan'] ?? '') === 'Ya';
            $marks = $form['marks'] ?? [];
        @endphp
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <span class="font-bold">Penandaan Lokasi Operasi:</span> {!! $val($form['perluPenandaan'] ?? '') !!}
                @if (!$perluPenandaan && filled($form['alasanTidakPerlu'] ?? ''))
                    &nbsp;— <span class="italic">{!! e($form['alasanTidakPerlu']) !!}</span>
                @endif
            </td>
        </tr>

        @if ($perluPenandaan)
            <tr>
                <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                    <p><span class="font-bold">Region Anatomi:</span> {!! $val($form['regionAnatomi'] ?? '') !!}</p>
                    <p><span class="font-bold">Sisi / Lateralitas:</span> {!! $val($form['sisi'] ?? '') !!}</p>
                    <p><span class="font-bold">Detail Lokasi:</span> {!! $val($form['detailLokasi'] ?? '') !!}</p>
                </td>
                <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                    <p><span class="font-bold">Metode Penandaan:</span> {!! $val($form['metodePenandaan'] ?? '') !!}</p>
                    <p><span class="font-bold">Pasien Dilibatkan:</span> {{ !empty($form['pasienDilibatkan']) ? 'Ya' : 'Tidak' }}</p>
                </td>
            </tr>

            {{-- Diagram penandaan (hanya panel yang ada tanda) --}}
            @if (count($marks) > 0)
                <tr>
                    <td colspan="2" class="border border-black px-2 py-2">
                        <p class="font-bold mb-1">Diagram Penandaan Lokasi</p>
                        <x-site-marking-diagram :marks="$marks" :editable="false" :pdf="true" />
                    </td>
                </tr>
            @endif
        @endif

        {{-- ── TTD 3 PIHAK ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        @foreach ([['ttdPerawatRuangan', 'Perawat Ruangan'], ['ttdPerawatKamarBedah', 'Perawat Kamar Bedah'], ['ttdDokterOperator', 'Dokter Operator']] as [$ttdField, $ttdLabel])
                            <td class="w-1/3 align-top text-center px-2 py-2">
                                <p class="font-bold mb-1">{{ $ttdLabel }}</p>
                                <p class="text-[9px] text-gray-500 mb-2">{{ ($form[$ttdField . 'Date'] ?? '') ?: '-' }}</p>
                                <div class="text-center my-1">
                                    @if (!empty($data[$ttdField . 'Path']))
                                        <img src="{{ $data[$ttdField . 'Path'] }}" class="h-16" alt="TTD {{ $ttdLabel }}" />
                                    @else
                                        <div class="h-16">&nbsp;</div>
                                    @endif
                                </div>
                                <div class="border-t border-black pt-[3px] mt-1 min-w-[110px] inline-block">
                                    <p class="font-bold">{{ strtoupper(($form[$ttdField] ?? '') ?: '-') }}</p>
                                    @if (!empty($form[$ttdField . 'Code']))
                                        <p class="text-[9px] text-gray-500">Kode: {{ $form[$ttdField . 'Code'] }}</p>
                                    @endif
                                </div>
                            </td>
                        @endforeach
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
                {{ $rsName }}{{ $rsAddress ? ', ' . $rsAddress : '' }}
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
