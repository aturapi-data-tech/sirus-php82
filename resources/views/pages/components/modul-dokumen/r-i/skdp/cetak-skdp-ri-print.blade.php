{{-- cetak-skdp-ri-print.blade.php --}}

<x-pdf.layout-kwitansi title="">

    {{-- JUDUL --}}
    <div class="text-center mt-1 mb-2">
        <span class="text-[12px] font-bold uppercase tracking-wide">Surat Rencana Kontrol</span>
    </div>

    {{-- ══ DATA KONTROL ══ --}}
    <table class="w-full" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-28 py-0.5 text-[9px] text-gray-500">No SKDP BPJS</td>
            <td class="w-2  py-0.5 text-[9px]">:</td>
            <td class="py-0.5 text-[9px] font-bold">{{ $data['kontrol']['noSKDPBPJS'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[9px] text-gray-500">Kode Reservasi RS</td>
            <td class="py-0.5 text-[9px]">:</td>
            <td class="py-0.5 text-[9px]">{{ $data['kontrol']['noKontrolRS'] ?? '-' }}</td>
        </tr>
    </table>

    <div class="my-1 border-t border-gray-300"></div>

    {{-- ══ IDENTITAS PASIEN ══ --}}
    <table class="w-full" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-28 py-0.5 text-[9px] text-gray-500">No. BPJS</td>
            <td class="w-2  py-0.5 text-[9px]">:</td>
            <td class="py-0.5 text-[9px] font-bold">{{ $data['pasien']['identitas']['idbpjs'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[9px] text-gray-500">No. Rekam Medis</td>
            <td class="py-0.5 text-[9px]">:</td>
            <td class="py-0.5 text-[9px]">{{ $data['pasien']['regNo'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[9px] text-gray-500">Nama Peserta</td>
            <td class="py-0.5 text-[9px]">:</td>
            <td class="py-0.5 text-[9px] font-bold uppercase">{{ $data['pasien']['regName'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[9px] text-gray-500">Tgl. Lahir</td>
            <td class="py-0.5 text-[9px]">:</td>
            <td class="py-0.5 text-[9px]">
                {{ $data['pasien']['tglLahir'] ?? '-' }}
                @if (!empty($data['pasien']['thn']))
                    ({{ $data['pasien']['thn'] }})
                @endif
            </td>
        </tr>
    </table>

    <div class="my-1 border-t border-gray-300"></div>

    {{-- ══ RENCANA KONTROL ══ --}}
    <p class="text-[9px] text-gray-700 mb-0.5">
        Kepada Yth : dr. <strong>{{ $data['kontrol']['drKontrolDesc'] ?? '-' }}</strong>
    </p>
    <p class="text-[9px] text-gray-700 mb-1">
        Mohon Pemeriksaan dan Penanganan Lebih Lanjut :
    </p>

    <table class="w-full" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-28 py-0.5 text-[9px] text-gray-500">Poli Tujuan</td>
            <td class="w-2  py-0.5 text-[9px]">:</td>
            <td class="py-0.5 text-[9px] font-bold">{{ $data['kontrol']['poliKontrolDesc'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[9px] text-gray-500">Tgl. Rencana Kontrol</td>
            <td class="py-0.5 text-[9px]">:</td>
            <td class="py-0.5 text-[9px] font-bold">{{ $data['kontrol']['tglKontrol'] ?? '-' }}</td>
        </tr>
        @if (!empty($data['kontrol']['catatan']))
            <tr>
                <td class="py-0.5 text-[9px] text-gray-500">Catatan</td>
                <td class="py-0.5 text-[9px]">:</td>
                <td class="py-0.5 text-[9px]">{{ $data['kontrol']['catatan'] }}</td>
            </tr>
        @endif
    </table>

    {{-- ══ PENUTUP ══ --}}
    <p class="mt-2 text-[9px] text-gray-700">
        Demikian atas bantuannya, diucapkan banyak terima kasih.
    </p>

    <p class="mt-2 text-[8px] text-gray-400 text-right">
        Dicetak: {{ $data['tglCetak'] ?? '-' }}
    </p>

</x-pdf.layout-kwitansi>
