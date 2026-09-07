{{-- resources/views/pages/components/modul-dokumen/ri/pasca-anestesi-ri/cetak-pasca-anestesi-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="MONITORING PASCA ANESTESI (RECOVERY ROOM)">

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
        $aldreteItems = $data['aldreteItems'] ?? [];
        $bromageOptions = $data['bromageOptions'] ?? [];

        $val = fn($nilai) => filled($nilai) ? e($nilai) : '-';
        $aldrete = $form['aldrete'] ?? [];
        $isRegional = ($form['jenisAnestesi'] ?? '') === 'Regional / Spinal';
    @endphp

    <table class="w-full text-[10px] border-collapse" cellpadding="0" cellspacing="0">

        <tr>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p><span class="font-bold">Jam Masuk RR:</span> {!! $val($form['jamMasuk'] ?? '') !!}</p>
                <p><span class="font-bold">Jam Keluar RR:</span> {!! $val($form['jamKeluar'] ?? '') !!}</p>
                <p><span class="font-bold">Jenis Anestesi:</span> {!! $val($form['jenisAnestesi'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p><span class="font-bold">Keadaan Umum:</span> {!! $val($form['keadaanUmum'] ?? '') !!}</p>
                @php $adaTtvMasuk = filled($form['sistolik'] ?? '') || filled($form['diastolik'] ?? '') || filled($form['nadi'] ?? '') || filled($form['rr'] ?? '') || filled($form['suhu'] ?? '') || filled($form['spo2'] ?? '') || filled($form['gda'] ?? '') || filled($form['td'] ?? ''); @endphp
                @if ($adaTtvMasuk)
                    <p><span class="font-bold">TTV:</span>
                        TD {!! $val(filled($form['sistolik'] ?? '') || filled($form['diastolik'] ?? '') ? ($form['sistolik'] ?? '') . '/' . ($form['diastolik'] ?? '') : ($form['td'] ?? '')) !!} mmHg · N {!! $val($form['nadi'] ?? '') !!} ·
                        RR {!! $val($form['rr'] ?? '') !!} · S {!! $val($form['suhu'] ?? '') !!} ·
                        SpO2 {!! $val($form['spo2'] ?? '') !!} · GDA {!! $val($form['gda'] ?? '') !!}</p>
                @endif
            </td>
        </tr>

        {{-- ── OBSERVASI LANJUTAN (pemantauan TTV berkala di RR) ── --}}
        @php $obsCetak = $form['observasi'] ?? []; @endphp
        @if (!empty($obsCetak))
            <tr>
                <td colspan="2" class="border border-black px-2 py-1.5 align-top">
                    <p class="font-bold mb-1">Observasi Lanjutan (pemantauan berkala)</p>
                    <table class="w-full text-[9px] border-collapse" cellpadding="2" cellspacing="0">
                        <thead>
                            <tr>
                                <td class="border border-black font-bold">Waktu</td>
                                <td class="border border-black font-bold text-center">TD (mmHg)</td>
                                <td class="border border-black font-bold text-center">Nadi</td>
                                <td class="border border-black font-bold text-center">Nafas</td>
                                <td class="border border-black font-bold text-center">Suhu</td>
                                <td class="border border-black font-bold text-center">SpO2</td>
                                <td class="border border-black font-bold text-center">GDA</td>
                                <td class="border border-black font-bold">Catatan</td>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($obsCetak as $row)
                                <tr>
                                    <td class="border border-black">{!! $val($row['waktu'] ?? '') !!}</td>
                                    <td class="border border-black text-center">{!! $val((filled($row['sistolik'] ?? '') || filled($row['diastolik'] ?? '')) ? (($row['sistolik'] ?? '') . '/' . ($row['diastolik'] ?? '')) : '') !!}</td>
                                    <td class="border border-black text-center">{!! $val($row['nadi'] ?? '') !!}</td>
                                    <td class="border border-black text-center">{!! $val($row['nafas'] ?? '') !!}</td>
                                    <td class="border border-black text-center">{!! $val($row['suhu'] ?? '') !!}</td>
                                    <td class="border border-black text-center">{!! $val($row['spo2'] ?? '') !!}</td>
                                    <td class="border border-black text-center">{!! $val($row['gda'] ?? '') !!}</td>
                                    <td class="border border-black">{!! $val($row['catatan'] ?? '') !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        @endif

        {{-- ── ALDRETE ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 align-top">
                <p class="font-bold mb-1">Aldrete Score — Total: {{ $form['totalAldrete'] ?? '-' }}/10
                    @if (($form['totalAldrete'] ?? -1) >= 8) <span class="text-gray-600">(boleh pindah ruangan)</span> @endif
                </p>
                <table class="w-full text-[9.5px]" cellpadding="2" cellspacing="0">
                    @foreach ($aldreteItems as $key => $item)
                        @php $skor = $aldrete[$key] ?? ''; @endphp
                        <tr>
                            <td class="w-2/5 align-top">{{ $item['label'] }}</td>
                            <td class="align-top">: <span class="font-bold">{{ $skor === '' ? '-' : $skor }}</span>
                                @if ($skor !== '' && isset($item['opsi'][$skor]))
                                    — {{ $item['opsi'][$skor] }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>

        {{-- ── BROMAGE ── --}}
        @if ($isRegional)
            <tr>
                <td colspan="2" class="border border-black px-2 py-1.5 align-top">
                    <p class="font-bold mb-0.5">Bromage Score (regional/spinal)</p>
                    <p>
                        <span class="font-bold">{{ $form['bromage'] === '' || !isset($form['bromage']) ? '-' : $form['bromage'] }}</span>
                        @if (filled($form['bromage'] ?? '') && isset($bromageOptions[$form['bromage']]))
                            — {{ $bromageOptions[$form['bromage']] }}
                        @endif
                    </p>
                </td>
            </tr>
        @endif

        {{-- ── NYERI & REKOMENDASI ── --}}
        <tr>
            <td class="border border-black px-2 py-1.5 align-top">
                <p><span class="font-bold">Skala Nyeri:</span>
                    {{ filled($form['skalaNyeri'] ?? '') ? $form['skalaNyeri'] . '/10' : '-' }}</p>
            </td>
            <td class="border border-black px-2 py-1.5 align-top">
                <p><span class="font-bold">Rekomendasi:</span> {!! $val($form['rekomendasi'] ?? '') !!}</p>
                @if (filled($form['keteranganRekomendasi'] ?? ''))
                    <p class="text-[9px] text-gray-600">{!! e($form['keteranganRekomendasi']) !!}</p>
                @endif
            </td>
        </tr>

        {{-- ── TTD ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="w-1/2"></td>
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Petugas Recovery Room</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $form['ttdDate'] ?? $data['tglCetak'] ?? '-' }}</p>
                            <div class="text-center my-1">
                                @if (!empty($data['ttdPath']))
                                    <img src="{{ $data['ttdPath'] }}" class="h-16" alt="Tanda Tangan" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>
                            <div class="border-t border-black pt-[3px] mt-1 min-w-[160px] inline-block">
                                <p class="font-bold">{{ strtoupper($form['ttd'] ?? '-') }}</p>
                                @if (!empty($form['ttdCode']))
                                    <p class="text-[9px] text-gray-500">Kode: {{ $form['ttdCode'] }}</p>
                                @endif
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
                {{ $rsName }}{{ $rsAddress ? ', ' . $rsAddress : '' }}
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
