{{-- resources/views/pages/components/modul-dokumen/u-g-d/inform-consent/cetak-inform-consent-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="FORMULIR PERSETUJUAN TINDAKAN MEDIS (INFORM CONSENT)">

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
        $agreementText = ($consent['agreement'] ?? '1') === '1' ? 'MENYETUJUI' : 'MENOLAK';
        $agreementClass = ($consent['agreement'] ?? '1') === '1' ? 'font-bold' : 'font-bold';
    @endphp

    <table class="w-full text-[10px] border-collapse">

        {{-- Nama Tindakan --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top w-36">NAMA TINDAKAN</td>
            <td class="border border-black px-1.5 py-0.5 align-top">
                {{ strtoupper($consent['tindakan'] ?? '-') }}
            </td>
        </tr>

        {{-- Tujuan / Penjelasan --}}
        @if (!empty($consent['tujuan']))
            <tr>
                <td class="border border-black px-1.5 py-0.5 font-bold align-top">TUJUAN / PENJELASAN</td>
                <td class="border border-black px-1.5 py-0.5 align-top">
                    {!! nl2br(e($consent['tujuan'])) !!}
                </td>
            </tr>
        @endif

        {{-- Risiko --}}
        @if (!empty($consent['resiko']))
            <tr>
                <td class="border border-black px-1.5 py-0.5 font-bold align-top">RISIKO</td>
                <td class="border border-black px-1.5 py-0.5 align-top">
                    {!! nl2br(e($consent['resiko'])) !!}
                </td>
            </tr>
        @endif

        {{-- Alternatif --}}
        @if (!empty($consent['alternatif']))
            <tr>
                <td class="border border-black px-1.5 py-0.5 font-bold align-top">ALTERNATIF</td>
                <td class="border border-black px-1.5 py-0.5 align-top">
                    {!! nl2br(e($consent['alternatif'])) !!}
                </td>
            </tr>
        @endif

        {{-- Pernyataan --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-2 text-[10px] leading-relaxed">
                <p>
                    Saya yang bertanda tangan di bawah ini,
                    <strong>{{ strtoupper($consent['wali'] ?? '-') }}</strong>
                    @if (!empty($consent['hubunganWali']) && $consent['hubunganWali'] !== 'Pasien Sendiri')
                        (selaku <strong>{{ $consent['hubunganWali'] }}</strong> dari pasien)
                    @endif,
                    dengan ini menyatakan bahwa saya telah mendapatkan penjelasan yang cukup
                    mengenai tindakan medis di atas dari dokter / petugas yang berwenang.
                </p>
                <br>
                <p>
                    Setelah mempertimbangkan dengan seksama, saya dengan penuh kesadaran
                    <strong class="{{ $agreementClass }}">{{ $agreementText }}</strong>
                    dilakukannya tindakan tersebut pada diri saya / pasien.
                </p>
            </td>
        </tr>

        {{-- TTD 3 kolom: Pasien/Wali | Dokter/Petugas | Saksi --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>

                        {{-- Kolom 1: Pasien / Wali --}}
                        <td class="w-1/3 align-top text-center px-2 py-2">
                            <p class="font-bold mb-1">Pasien / Wali</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $consent['signatureDate'] ?? '-' }}</p>

                            @if (!empty($consent['signature']))
                                <div style="height:60px;display:flex;align-items:center;justify-content:center;">
                                    <img src="{{ $consent['signature'] }}"
                                        style="max-height:55px;max-width:160px;object-fit:contain;"
                                        alt="TTD Pasien/Wali" />
                                </div>
                            @else
                                <br><br><br>
                            @endif

                            <div
                                style="border-top:1px solid #000;padding-top:3px;margin-top:4px;min-width:120px;display:inline-block;">
                                <p class="font-bold">{{ strtoupper($consent['wali'] ?? '-') }}</p>
                                @if (!empty($consent['signatureDate']))
                                    <p class="text-[9px] text-gray-500">{{ $consent['signatureDate'] }}</p>
                                @endif
                            </div>
                        </td>

                        {{-- Garis pemisah --}}
                        <td style="border-left:1px solid #d1d5db;width:1px;"></td>

                        {{-- Kolom 2: Dokter / Petugas Penjelas --}}
                        <td class="w-1/3 align-top text-center px-2 py-2">
                            <p class="font-bold mb-1">Dokter / Petugas Penjelas</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $consent['dokterDate'] ?? '-' }}</p>

                            @if (!empty($data['ttdDokterPath']))
                                <div style="height:60px;display:flex;align-items:center;justify-content:center;">
                                    <img src="{{ $data['ttdDokterPath'] }}"
                                        style="max-height:55px;max-width:160px;object-fit:contain;" alt="TTD Dokter" />
                                </div>
                            @else
                                <br><br><br>
                            @endif

                            <div
                                style="border-top:1px solid #000;padding-top:3px;margin-top:4px;min-width:120px;display:inline-block;">
                                <p class="font-bold">
                                    {{ strtoupper($consent['dokter'] ?? '..............................') }}</p>
                                @if (!empty($consent['dokterCode']))
                                    <p class="text-[9px] text-gray-500">Kode: {{ $consent['dokterCode'] }}</p>
                                @endif
                            </div>
                        </td>

                        {{-- Garis pemisah --}}
                        <td style="border-left:1px solid #d1d5db;width:1px;"></td>

                        {{-- Kolom 3: Saksi --}}
                        <td class="w-1/3 align-top text-center px-2 py-2">
                            <p class="font-bold mb-1">Saksi</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $consent['signatureSaksiDate'] ?? '-' }}</p>

                            @if (!empty($consent['signatureSaksi']))
                                <div style="height:60px;display:flex;align-items:center;justify-content:center;">
                                    <img src="{{ $consent['signatureSaksi'] }}"
                                        style="max-height:55px;max-width:160px;object-fit:contain;" alt="TTD Saksi" />
                                </div>
                            @else
                                <br><br><br>
                            @endif

                            <div
                                style="border-top:1px solid #000;padding-top:3px;margin-top:4px;min-width:120px;display:inline-block;">
                                <p>{{ strtoupper($consent['saksi'] ?? '..............................') }}</p>
                            </div>
                        </td>

                    </tr>
                </table>
            </td>
        </tr>

        {{-- Footer --}}
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
