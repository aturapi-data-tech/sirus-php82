{{-- resources/views/pages/components/modul-dokumen/u-g-d/general-consent/cetak-general-consent-ugd-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="FORMULIR PERSETUJUAN UMUM (GENERAL CONSENT)">

    {{-- ── IDENTITAS PASIEN ── --}}
    <x-slot name="patientData">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Rekam Medis</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">{{ $data['regNo'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Nama Pasien</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">{{ strtoupper($data['regName'] ?? '-') }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Jenis Kelamin</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $data['jenisKelamin']['jenisKelaminDesc'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tempat, Tgl. Lahir</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">
                    {{ $data['tempatLahir'] ?? '-' }}, {{ $data['tglLahir'] ?? '-' }}
                    ({{ $data['thn'] ?? '-' }})
                </td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap align-top">Alamat</td>
                <td class="py-0.5 text-[11px] px-1 align-top">:</td>
                <td class="py-0.5 text-[11px]">
                    @php
                        $id = $data['identitas'] ?? [];
                        echo trim(
                            ($id['alamat'] ?? '-') .
                                (!empty($id['rt']) ? ' RT ' . $id['rt'] : '') .
                                (!empty($id['rw']) ? '/RW ' . $id['rw'] : '') .
                                (!empty($id['desaName']) ? ', ' . $id['desaName'] : '') .
                                (!empty($id['kecamatanName']) ? ', ' . $id['kecamatanName'] : ''),
                        );
                    @endphp
                </td>
            </tr>
        </table>
    </x-slot>

    @php
        $consent = $data['consent'] ?? [];
        $identitasRs = $data['identitasRs'] ?? null;
        $rsName = $identitasRs->int_name ?? 'RSI MADINAH';
        $rsAddress = $identitasRs->int_address ?? '';
        $rsCity = $identitasRs->int_city ?? 'Tulungagung';
        $agreementText = ($consent['agreement'] ?? '1') === '1' ? 'SETUJU' : 'TIDAK SETUJU';
        $agreementClass =
            ($consent['agreement'] ?? '1') === '1' ? 'font-bold text-green-700' : 'font-bold text-red-700';
    @endphp

    <table class="w-full text-[10px] border-collapse">

        {{-- ── ISI PERSETUJUAN ── --}}
        <tr>
            <td colspan="4" class="border border-black px-2 py-2 text-[10px] leading-relaxed">
                <p>
                    Saya yang bertanda tangan di bawah ini, <strong>{{ strtoupper($consent['wali'] ?? '-') }}</strong>,
                    menyatakan bahwa saya telah mendapat penjelasan yang cukup mengenai tujuan, prosedur, risiko,
                    dan manfaat dari pelayanan medis yang akan diberikan di <strong>{{ $rsName }}</strong>.
                </p>
                <br>
                <p>
                    Dengan ini saya menyatakan <strong class="{{ $agreementClass }}">{{ $agreementText }}</strong>
                    untuk menerima pelayanan kesehatan, pemeriksaan, dan tindakan yang diperlukan sesuai dengan
                    standar pelayanan medis yang berlaku di rumah sakit ini.
                </p>
                <br>
                <p>
                    Saya memahami bahwa:
                </p>
                <p style="padding-left: 12px;">
                    1. Informasi medis saya dapat digunakan untuk keperluan pendidikan dan penelitian dengan tetap
                    menjaga kerahasiaan.<br>
                    2. Saya berhak mendapat informasi yang jelas mengenai kondisi kesehatan saya.<br>
                    3. Saya berhak menolak tindakan setelah mendapat penjelasan dan menandatangani surat penolakan.<br>
                    4. Biaya pelayanan akan diinformasikan sesuai ketentuan yang berlaku.
                </p>
            </td>
        </tr>

        {{-- ── TANDA TANGAN — 2 kolom: pasien/wali & petugas ── --}}
        <tr>
            <td colspan="4" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        {{-- Kolom kiri: TTD Pasien/Wali --}}
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Pasien / Wali</p>
                            <p class="text-[9px] text-gray-500 mb-2">
                                {{ $data['tglCetak'] ?? '-' }}
                            </p>

                            @if (!empty($consent['signature']))
                                {{-- Signature dari canvas (base64 dataURL) --}}
                                <div style="height: 60px; display: flex; align-items: center; justify-content: center;">
                                    <img src="{{ $consent['signature'] }}"
                                        style="max-height: 55px; max-width: 160px; object-fit: contain;"
                                        alt="Tanda Tangan Pasien" />
                                </div>
                            @else
                                <br><br><br>
                            @endif

                            <div
                                style="border-top: 1px solid #000; padding-top: 3px; margin-top: 4px; min-width: 140px; display: inline-block;">
                                <p class="font-bold">{{ strtoupper($consent['wali'] ?? '-') }}</p>
                                @if (!empty($consent['signatureDate']))
                                    <p class="text-[9px] text-gray-500">{{ $consent['signatureDate'] }}</p>
                                @endif
                            </div>
                        </td>

                        {{-- Garis pemisah --}}
                        <td style="border-left: 1px solid #d1d5db; width: 1px;"></td>

                        {{-- Kolom kanan: TTD Petugas Pemeriksa --}}
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Petugas Pemeriksa</p>
                            <p class="text-[9px] text-gray-500 mb-2">
                                {{ $consent['petugasPemeriksaDate'] ?? '-' }}
                            </p>

                            @if (!empty($data['ttdPetugasPath']))
                                <div style="height: 60px; display: flex; align-items: center; justify-content: center;">
                                    <img src="{{ $data['ttdPetugasPath'] }}"
                                        style="max-height: 55px; max-width: 160px; object-fit: contain;"
                                        alt="Tanda Tangan Petugas" />
                                </div>
                            @else
                                <br><br><br>
                            @endif

                            <div
                                style="border-top: 1px solid #000; padding-top: 3px; margin-top: 4px; min-width: 140px; display: inline-block;">
                                <p class="font-bold">{{ strtoupper($consent['petugasPemeriksa'] ?? '-') }}</p>
                                @if (!empty($consent['petugasPemeriksaCode']))
                                    <p class="text-[9px] text-gray-500">Kode: {{ $consent['petugasPemeriksaCode'] }}
                                    </p>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── FOOTER INFO ── --}}
        <tr>
            <td colspan="4" class="px-1.5 py-1 text-[9px] text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                {{ $rsName }}, {{ $rsAddress }}
            </td>
        </tr>

    </table>

</x-pdf.layout-a4-with-out-background>
