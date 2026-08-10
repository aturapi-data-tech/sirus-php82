{{-- resources/views/pages/components/modul-dokumen/r-i/pra-induksi-ri/cetak-pra-induksi-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="ASESMEN PRA INDUKSI">

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
    @endphp

    <table class="w-full text-[10px] border-collapse" cellpadding="0" cellspacing="0">
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p><span class="font-bold">Tanggal:</span> {!! $val($form['tanggal'] ?? '') !!}</p>
                <p><span class="font-bold">Tempat:</span> {!! $val($form['tempat'] ?? '') !!}</p>
                <p><span class="font-bold">Diagnosis Pra Anestesi:</span> {!! $val($form['diagnosisPraAnestesi'] ?? '') !!}</p>
                <p><span class="font-bold">Rencana Tindakan:</span> {!! $val($form['rencanaTindakan'] ?? '') !!}</p>
                <p><span class="font-bold">Dokter Operator:</span> {!! $val($form['dokterOperator'] ?? '') !!} &nbsp;&bull;&nbsp; <span class="font-bold">Dokter Anestesi:</span> {!! $val($form['dokterAnestesi'] ?? '') !!}</p>
                <p><span class="font-bold">Asisten Operator:</span> {!! $val($form['asistenOperator'] ?? '') !!} &nbsp;&bull;&nbsp; <span class="font-bold">Asisten Anestesi:</span> {!! $val($form['asistenAnestesi'] ?? '') !!}</p>
                <p><span class="font-bold">Instrumen:</span> {!! $val($form['instrumen'] ?? '') !!} &nbsp;&bull;&nbsp; <span class="font-bold">On Loop:</span> {!! $val($form['onLoop'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p><span class="font-bold">Amnanese:</span> {!! $val($form['amnanese'] ?? '') !!}</p>
                <p><span class="font-bold">Riwayat Anestesi:</span> {{ $yn($form['riwayatAnestesi'] ?? false) }}{{ filled($form['riwayatAnestesiJenis'] ?? '') ? ' — ' . e($form['riwayatAnestesiJenis']) : '' }}</p>
                <p><span class="font-bold">Riwayat Alergi:</span> {{ $yn($form['riwayatAlergi'] ?? false) }}{{ filled($form['riwayatAlergiJenis'] ?? '') ? ' — ' . e($form['riwayatAlergiJenis']) : '' }}</p>
                <p>
                    <span class="font-bold">Merokok:</span> {{ $yn($form['merokok'] ?? false) }}{{ filled($form['merokokKet'] ?? '') ? ' — ' . e($form['merokokKet']) : '' }}
                    &nbsp;&bull;&nbsp;
                    <span class="font-bold">Alkohol:</span> {{ $yn($form['alkohol'] ?? false) }}{{ filled($form['alkoholKet'] ?? '') ? ' — ' . e($form['alkoholKet']) : '' }}
                </p>
                <p><span class="font-bold">Persiapan Transfusi:</span> {{ $yn($form['persiapanTransfusi'] ?? false) }}{{ filled($form['transfusiJumlah'] ?? '') ? ' (' . e($form['transfusiJumlah']) . ')' : '' }}</p>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <span class="font-bold">TTV:</span>
                TD {!! $val(($form['sistolik'] ?? '') . '/' . ($form['diastolik'] ?? '')) !!} mmHg
                · N {!! $val($form['nadi'] ?? '') !!} x/mnt
                · RR {!! $val($form['rr'] ?? '') !!} x/mnt
                · S {!! $val($form['suhu'] ?? '') !!} °C
                · SpO2 {!! $val($form['spo2'] ?? '') !!}%
                · GDA {!! $val($form['gda'] ?? '') !!} g/dl
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p class="font-bold mb-0.5">Pemeriksaan Fisik & Penunjang</p>
                <p>Pernafasan: {!! $val($form['pemFisikPernafasan'] ?? '') !!} · Tulang Belakang: {!! $val($form['pemFisikTulangBelakang'] ?? '') !!} · Jantung/Paru: {!! $val($form['pemFisikJantungParu'] ?? '') !!} · Abdomen: {!! $val($form['pemFisikAbdomen'] ?? '') !!}</p>
                <p>Lab: {!! $val($form['penunjangLab'] ?? '') !!} · EKG: {!! $val($form['penunjangEkg'] ?? '') !!} · Thorak: {!! $val($form['penunjangThorak'] ?? '') !!}</p>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p class="font-bold mb-0.5">Rencana Anestesi</p>
                <p>
                    <span class="font-bold">ASA:</span> {!! $val($form['klasifikasiAsa'] ?? '') !!} &nbsp;&bull;&nbsp;
                    <span class="font-bold">Rencana Anestesi:</span> {!! $val($form['rencanaAnestesi'] ?? '') !!} &nbsp;&bull;&nbsp;
                    <span class="font-bold">Pemulihan:</span> {!! $val($form['pemulihanPasca'] ?? '') !!} &nbsp;&bull;&nbsp;
                    <span class="font-bold">Manajemen Nyeri:</span> {!! $val($form['manajemenNyeri'] ?? '') !!}
                </p>
                @php
                    $preMedikasiRows = is_array($form['obatPreMedikasi'] ?? null)
                        ? array_values($form['obatPreMedikasi'])
                        : (filled($form['obatPreMedikasi'] ?? '') ? [['obat' => $form['obatPreMedikasi'], 'dosis' => '', 'jam' => '', 'pelaksana' => '']] : []);
                @endphp
                <p class="font-bold mt-1 mb-0.5">Obat Pre Medikasi</p>
                <table class="w-full text-[10px] border-collapse" cellpadding="2" cellspacing="0">
                    <tr>
                        <td class="border border-black font-bold px-1" style="width: 40%;">Obat Pre Medikasi</td>
                        <td class="border border-black font-bold px-1" style="width: 15%;">Dosis</td>
                        <td class="border border-black font-bold px-1" style="width: 15%;">Jam</td>
                        <td class="border border-black font-bold px-1" style="width: 30%;">Pelaksana</td>
                    </tr>
                    @forelse ($preMedikasiRows as $preMedikasiRow)
                        <tr>
                            <td class="border border-black px-1">{!! $val($preMedikasiRow['obat'] ?? '') !!}</td>
                            <td class="border border-black px-1">{!! $val($preMedikasiRow['dosis'] ?? '') !!}</td>
                            <td class="border border-black px-1">{!! $val($preMedikasiRow['jam'] ?? '') !!}</td>
                            <td class="border border-black px-1">{!! $val($preMedikasiRow['pelaksana'] ?? '') !!}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="border border-black px-1 text-center" colspan="4">-</td>
                        </tr>
                    @endforelse
                </table>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="w-1/2"></td>
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Dokter Anestesi</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $form['ttdDate'] ?? $data['tglCetak'] ?? '-' }}</p>
                            <div class="text-center my-1">
                                @if (!empty($data['ttdPath']))
                                    <img src="{{ $data['ttdPath'] }}" class="h-16" alt="TTD" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>
                            <div class="border-t border-black pt-[3px] mt-1 min-w-[160px] inline-block">
                                <p class="font-bold">{{ strtoupper($form['ttd'] ?? '-') }}</p>
                                @if (!empty($form['ttdCode'])) <p class="text-[9px] text-gray-500">Kode: {{ $form['ttdCode'] }}</p> @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="px-1.5 py-1 text-[9px] text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }} &nbsp;&bull;&nbsp; No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp; {{ $rsName }}{{ $rsAddress ? ', ' . $rsAddress : '' }}
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
