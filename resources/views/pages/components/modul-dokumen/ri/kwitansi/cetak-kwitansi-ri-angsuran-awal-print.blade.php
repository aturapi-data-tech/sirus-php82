<x-pdf.layout-kwitansi title="KWITANSI ANGSURAN AWAL - Rawat Inap">

    {{-- IDENTITAS — kiri pasien, kanan data perawatan (tabel: dompdf tak dukung flex/grid) --}}
    <table class="w-full mb-2" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-1/2 align-top">
                <x-pdf.identitas-pasien
                    :rm="$data['regNo'] ?? null"
                    :nama="$data['regName'] ?? null"
                    :jenisKelamin="($data['sex'] ?? '') === 'L' ? 'Laki-laki' : (($data['sex'] ?? '') === 'P' ? 'Perempuan' : null)"
                    :tempatLahir="$data['birthPlace'] ?? null"
                    :tglLahir="$data['birthDate'] ?? null"
                    :umur="$data['umur'] ?? null"
                    :alamat="$data['address'] ?? null"
                    textClass="text-[8px]"
                    class="w-full" />
            </td>

            <td class="w-1/2 align-top pl-2">
                <table class="w-full" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap align-top">No. / Tgl Terima</td>
                        <td class="py-0 text-[8px] px-1 align-top">:</td>
                        <td class="py-0 text-[8px] font-bold">
                            {{ $data['ripayNo'] ?? '-' }} / {{ $data['ripayDate'] ?? '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap">Tgl Masuk</td>
                        <td class="py-0 text-[8px] px-1">:</td>
                        <td class="py-0 text-[8px]">{{ $data['entryDate'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap">Ruangan</td>
                        <td class="py-0 text-[8px] px-1">:</td>
                        <td class="py-0 text-[8px]">{{ $data['roomDesc'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap align-top">Cara Bayar</td>
                        <td class="py-0 text-[8px] px-1 align-top">:</td>
                        <td class="py-0 text-[8px]">
                            {{ $data['accName'] ?? '-' }}
                            <span class="text-gray-400">·</span>
                            {{ $data['klaimName'] ?? '-' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- RINCIAN --}}
    <table class="w-full mb-1 text-[8px]" cellpadding="0" cellspacing="0">
        <thead>
            <tr class="border-b border-t border-gray-400">
                <th class="py-0 text-left font-semibold text-gray-900">Keterangan</th>
                <th class="py-0 text-right font-semibold text-gray-900 w-14">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b border-gray-100">
                <td class="py-0 text-gray-900">Angsuran awal (titipan) biaya perawatan Rawat Inap</td>
                <td class="py-0 text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['nominal'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-gray-400">
                <td class="pt-1 pb-1 font-bold text-[8px] text-right pr-2 text-gray-900">Diterima</td>
                <td class="pt-1 pb-1 font-bold text-[9px] text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['nominal'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td class="pr-2 text-right text-[8px] text-gray-700">Total angsuran awal s.d. kwitansi ini</td>
                <td class="text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['totalSampaiIni'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- TERBILANG --}}
    <div class="mb-2 px-2 py-1 bg-gray-50 border border-gray-400 rounded text-[7px] text-gray-700 italic">
        Terbilang:
        <strong class="not-italic text-gray-900">
            {{ \App\Support\Terbilang::rupiah((int) ($data['nominal'] ?? 0)) }}
        </strong>
        <div class="mt-1 text-[7px] not-italic text-gray-700">
            Titipan ini diperhitungkan sebagai pengurang tagihan saat pasien pulang.
        </div>
    </div>

    {{-- TANDA TANGAN --}}
    <table class="w-full mt-2 text-[8px]" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-5/12 text-center align-bottom">
                <p class="mb-6 text-gray-700">Kasir</p>
                <div class="inline-block pt-1 border-t border-gray-300" style="min-width:80px;">
                    <span class="text-gray-900">
                        {{ $data['kasirName'] ?? '( ................................ )' }}
                    </span>
                </div>
            </td>

            <td class="w-2/12"></td>

            <td class="w-5/12 text-center align-bottom">
                <p class="text-gray-700">Tulungagung, {{ $data['tglCetak'] ?? '' }}</p>
                <p class="mb-6 text-gray-700">Pasien / Wali</p>
                <div class="inline-block pt-1 border-t border-gray-300" style="min-width:80px;">
                    <span class="text-gray-900">
                        {{ $data['regName'] ?? '( ................................ )' }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    {{-- FOOTER --}}
    <div class="mt-2 pt-1 border-t border-gray-400 text-[6px] text-gray-700 flex justify-between">
        <span>
            Dicetak oleh: {{ $data['cetakOleh'] ?? '-' }} —
            {{ $data['tglCetak'] ?? '' }}, pukul {{ $data['jamCetak'] ?? '' }}
        </span>
        <span>No. Angsuran: {{ $data['ripayNo'] ?? '-' }} · Shift {{ $data['shift'] ?? '-' }}</span>
    </div>

</x-pdf.layout-kwitansi>
