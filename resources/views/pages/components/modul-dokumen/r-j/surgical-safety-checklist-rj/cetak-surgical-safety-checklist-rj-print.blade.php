{{-- resources/views/pages/components/modul-dokumen/r-j/surgical-safety-checklist-rj/cetak-surgical-safety-checklist-rj-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="SURGICAL SAFETY CHECKLIST">

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
            :rm="$data['regNo'] ?? null" :nama="$data['regName'] ?? null"
            :jenisKelamin="$data['jenisKelamin']['jenisKelaminDesc'] ?? null"
            :tempatLahir="$data['tempatLahir'] ?? null" :tglLahir="$data['tglLahir'] ?? null"
            :umur="$data['thn'] ?? null" :alamat="$alamatPasien" />
    </x-slot>

    @php
        $form = $data['form'] ?? [];
        $identitasRs = $data['identitasRs'] ?? null;
        $rsName = $identitasRs->int_name ?? 'RSI MADINAH';
        $rsAddress = $identitasRs->int_address ?? '';
        $val = fn($nilai) => filled($nilai) ? e($nilai) : '-';
        $yn = fn($nilai) => !empty($nilai) ? 'Ya' : 'Tidak';
        $sb = fn($nilai) => filled($nilai) ? e($nilai) : 'Belum';
    @endphp

    <table class="w-full text-[10px] border-collapse" cellpadding="0" cellspacing="0">
        {{-- Header informasi operasi --}}
        <tr>
            <td class="border border-black px-2 py-1.5 align-top" style="width: 50%;">
                <p><span class="font-bold">Tanggal:</span> {!! $val($form['tanggal'] ?? '') !!}</p>
                <p><span class="font-bold">Diagnosa:</span> {!! $val($form['diagnosa'] ?? '') !!}</p>
                <p><span class="font-bold">Tindakan:</span> {!! $val($form['tindakan'] ?? '') !!}</p>
                <p><span class="font-bold">Operator:</span> {!! $val($form['operator'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 align-top" style="width: 50%;">
                <p><span class="font-bold">Anestesi:</span> {!! $val($form['anestesi'] ?? '') !!}</p>
                <p><span class="font-bold">Instrumen:</span> {!! $val($form['instrumen'] ?? '') !!}</p>
                <p><span class="font-bold">Asisten:</span> {!! $val(trim(($form['asisten1'] ?? '') . (!empty($form['asisten1']) && !empty($form['asisten2']) ? ' & ' : '') . ($form['asisten2'] ?? ''))) !!}</p>
                <p>
                    <span class="font-bold">Jam Induksi:</span> {!! $val($form['jamInduksi'] ?? '') !!}
                    &nbsp;&bull;&nbsp;
                    <span class="font-bold">Jam Insisi:</span> {!! $val($form['jamInsisi'] ?? '') !!}
                    &nbsp;&bull;&nbsp;
                    <span class="font-bold">Jam Selesai:</span> {!! $val($form['jamSelesai'] ?? '') !!}
                </p>
            </td>
        </tr>

        {{-- Tiga fase --}}
        <tr>
            {{-- SIGN IN --}}
            <td class="border border-black px-2 py-1.5 align-top" style="width: 33.33%; vertical-align: top;">
                <p class="font-bold mb-1.5">Sebelum Anestesi (SIGN IN)</p>
                <p class="mb-1"><span class="font-bold">Jam:</span> {!! $val($form['jamSignIn'] ?? '') !!}</p>
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="py-0.5 align-top" style="width: 70%;">Identitas/area/tindakan/persetujuan</td>
                        <td class="py-0.5 align-top font-bold">{!! $sb($form['identitasAreaTindakanPersetujuan'] ?? '') !!}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top">Area operasi ditandai</td>
                        <td class="py-0.5 align-top font-bold">{!! $sb($form['areaOperasiDitandai'] ?? '') !!}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top">Mesin anestesi & obat diperiksa</td>
                        <td class="py-0.5 align-top font-bold">{!! $sb($form['mesinAnestesiObatDiperiksa'] ?? '') !!}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top">Pulse oksimeter berfungsi</td>
                        <td class="py-0.5 align-top font-bold">{{ $yn($form['pulseOksimeterBerfungsi'] ?? false) }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top">Riwayat alergi</td>
                        <td class="py-0.5 align-top font-bold">
                            {{ $yn($form['riwayatAlergi'] ?? false) }}
                            {!! filled($form['riwayatAlergiKet'] ?? '') ? '<br/>' . e($form['riwayatAlergiKet']) : '' !!}
                        </td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top">Penyulit airway / resiko aspirasi</td>
                        <td class="py-0.5 align-top font-bold">
                            {{ $yn($form['penyulitAirwayResikoAspirasi'] ?? false) }}
                            {!! filled($form['penyulitAirwayKet'] ?? '') ? '<br/>' . e($form['penyulitAirwayKet']) : '' !!}
                        </td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top">Resiko kehilangan darah >500ml / 7cc/kgBB</td>
                        <td class="py-0.5 align-top font-bold">{{ $yn($form['resikoKehilanganDarah'] ?? false) }}</td>
                    </tr>
                </table>

                <div class="mt-4 text-center">
                    <p class="font-bold mb-1">Dokter Anestesi</p>
                    <p class="text-[9px] text-gray-500 mb-2">{{ $form['ttdDokterAnestesiDate'] ?? $data['tglCetak'] ?? '-' }}</p>
                    <div class="text-center my-1">
                        @if (!empty($data['ttdPath']))
                            <img src="{{ $data['ttdPath'] }}" class="h-16" alt="TTD" />
                        @else
                            <div class="h-16">&nbsp;</div>
                        @endif
                    </div>
                    <div class="border-t border-black pt-[3px] mt-1 min-w-[160px] inline-block">
                        <p class="font-bold">{{ strtoupper($form['ttdDokterAnestesi'] ?? '-') }}</p>
                        @if (!empty($form['ttdDokterAnestesiCode'])) <p class="text-[9px] text-gray-500">Kode: {{ $form['ttdDokterAnestesiCode'] }}</p> @endif
                    </div>
                </div>
            </td>

            {{-- TIME OUT --}}
            <td class="border border-black px-2 py-1.5 align-top" style="width: 33.33%; vertical-align: top;">
                <p class="font-bold mb-1.5">Sebelum Insisi (TIME OUT)</p>
                <p class="mb-1"><span class="font-bold">Jam:</span> {!! $val($form['jamTimeOut'] ?? '') !!}</p>
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="py-0.5 align-top" style="width: 70%;">Tim memperkenalkan nama & tugas</td>
                        <td class="py-0.5 align-top font-bold">{!! $sb($form['timMemperkenalkanNamaTugas'] ?? '') !!}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top">Konfirmasi nama/tindakan/area</td>
                        <td class="py-0.5 align-top font-bold">{!! $sb($form['konfirmasiNamaTindakanArea'] ?? '') !!}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top">Antibiotik profilaksis <60 menit</td>
                        <td class="py-0.5 align-top font-bold">{!! $sb($form['antibiotikProfilaksis'] ?? '') !!}</td>
                    </tr>
                </table>

                <p class="font-bold mt-2 mb-1">Antisipasi Kejadian Kritis</p>
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="py-0.5 align-top font-bold" colspan="2">Operator</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top pl-2" style="width: 70%;">Tindakan darurat / luar standar</td>
                        <td class="py-0.5 align-top font-bold">{{ $yn($form['operatorTindakanDarurat'] ?? false) }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top pl-2">Lama operasi</td>
                        <td class="py-0.5 align-top font-bold">{!! $val($form['operatorLamaOperasi'] ?? '') !!}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top pl-2">Antisipasi kehilangan darah</td>
                        <td class="py-0.5 align-top font-bold">{!! $val($form['operatorAntisipasiKehilanganDarah'] ?? '') !!}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top font-bold" colspan="2">Anestesi</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top pl-2">Perhatian khusus pembiusan</td>
                        <td class="py-0.5 align-top font-bold">{{ $yn($form['anestesiPerhatianKhusus'] ?? false) }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top font-bold" colspan="2">Instrumen</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top pl-2">Peralatan disterilisasi</td>
                        <td class="py-0.5 align-top font-bold">{{ $yn($form['instrumenPeralatanDisterilisasi'] ?? false) }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top pl-2">Perhatian khusus peralatan</td>
                        <td class="py-0.5 align-top font-bold">{!! $val($form['instrumenPerhatianKhususPeralatan'] ?? '') !!}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top pl-2">Instrumentasi radiologi</td>
                        <td class="py-0.5 align-top font-bold">{{ $yn($form['instrumenInstrumentasiRadiologi'] ?? false) }}</td>
                    </tr>
                </table>

                <div class="mt-4 text-center">
                    <p class="font-bold mb-1">Perawat Instrumen</p>
                    <p class="text-[9px] text-gray-500 mb-2">{{ $form['ttdPerawatInstrumenDate'] ?? $data['tglCetak'] ?? '-' }}</p>
                    <div class="h-16">&nbsp;</div>
                    <div class="border-t border-black pt-[3px] mt-1 min-w-[160px] inline-block">
                        <p class="font-bold">{{ strtoupper($form['ttdPerawatInstrumen'] ?? '-') }}</p>
                        @if (!empty($form['ttdPerawatInstrumenCode'])) <p class="text-[9px] text-gray-500">Kode: {{ $form['ttdPerawatInstrumenCode'] }}</p> @endif
                    </div>
                </div>
            </td>

            {{-- SIGN OUT --}}
            <td class="border border-black px-2 py-1.5 align-top" style="width: 33.33%; vertical-align: top;">
                <p class="font-bold mb-1.5">Sebelum Meninggalkan Kamar Operasi (SIGN OUT)</p>
                <p class="mb-1"><span class="font-bold">Jam:</span> {!! $val($form['jamSignOut'] ?? '') !!}</p>
                <p class="font-bold mb-1">Perawat membacakan:</p>
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="py-0.5 align-top" style="width: 70%;">Jenis tindakan</td>
                        <td class="py-0.5 align-top font-bold">{{ $yn($form['perawatMembacakanJenisTindakan'] ?? false) }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top">Kecocokan jumlah instrumen/kasa/jarum</td>
                        <td class="py-0.5 align-top font-bold">{{ $yn($form['kecocokanJumlahInstrumenKasaJarum'] ?? false) }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top">Label spesimen</td>
                        <td class="py-0.5 align-top font-bold">{{ $yn($form['labelSpesimen'] ?? false) }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top">Permasalahan alat</td>
                        <td class="py-0.5 align-top font-bold">{{ $yn($form['permasalahanAlat'] ?? false) }}</td>
                    </tr>
                </table>

                <p class="font-bold mt-2 mb-1">Perhatian khusus masa pemulihan / recovery:</p>
                <p class="whitespace-pre-line">{!! $val($form['perhatianKhususRecovery'] ?? '') !!}</p>

                <div class="mt-4 text-center">
                    <p class="font-bold mb-1">Operator</p>
                    <p class="text-[9px] text-gray-500 mb-2">{{ $form['ttdOperatorDate'] ?? $data['tglCetak'] ?? '-' }}</p>
                    <div class="h-16">&nbsp;</div>
                    <div class="border-t border-black pt-[3px] mt-1 min-w-[160px] inline-block">
                        <p class="font-bold">{{ strtoupper($form['ttdOperator'] ?? '-') }}</p>
                        @if (!empty($form['ttdOperatorCode'])) <p class="text-[9px] text-gray-500">Kode: {{ $form['ttdOperatorCode'] }}</p> @endif
                    </div>
                </div>
            </td>
        </tr>

        <tr>
            <td colspan="3" class="px-1.5 py-1 text-[9px] text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }} &nbsp;&bull;&nbsp; No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp; {{ $rsName }}{{ $rsAddress ? ', ' . $rsAddress : '' }}
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
