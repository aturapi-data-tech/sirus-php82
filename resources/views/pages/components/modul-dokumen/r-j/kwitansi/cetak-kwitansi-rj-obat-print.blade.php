{{-- cetak-kwitansi-rj-obat-print.blade.php --}}

<x-pdf.layout-kwitansi :title="$data['judul'] ?? 'KWITANSI OBAT - Rawat Jalan'">

    {{-- ══════════════════════════════════════
         IDENTITAS KUNJUNGAN — dua kolom
         Kiri  : identitas pasien (standar) + No. Rawat Jalan
         Kanan : data kunjungan (tanggal, poli, dokter, penjamin)
         Pakai tabel biasa (dompdf tidak mendukung flex/grid).
    ══════════════════════════════════════ --}}
    <table class="w-full mb-4" cellpadding="0" cellspacing="0">
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
                    textClass="text-[14px]"
                    class="w-full">
                    <tr>
                        <td class="py-0.5 text-[14px] text-gray-500 whitespace-nowrap">No. Rawat Jalan</td>
                        <td class="py-0.5 text-[14px] px-1">:</td>
                        <td class="py-0.5 text-[14px] font-bold">{{ $data['rjNo'] ?? '-' }}</td>
                    </tr>
                </x-pdf.identitas-pasien>
            </td>

            <td class="w-1/2 align-top pl-4">
                <table class="w-full" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="py-0.5 text-[14px] text-gray-500 whitespace-nowrap">Tgl. Rawat Jalan</td>
                        <td class="py-0.5 text-[14px] px-1">:</td>
                        <td class="py-0.5 text-[14px] font-bold">{{ $data['rjDate'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 text-[14px] text-gray-500 whitespace-nowrap">Poli / Klinik</td>
                        <td class="py-0.5 text-[14px] px-1">:</td>
                        <td class="py-0.5 text-[14px]">{{ $data['poliName'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 text-[14px] text-gray-500 whitespace-nowrap align-top">Dokter</td>
                        <td class="py-0.5 text-[14px] px-1 align-top">:</td>
                        <td class="py-0.5 text-[14px]">{{ $data['drName'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 text-[14px] text-gray-500 whitespace-nowrap">Jenis Pembayaran</td>
                        <td class="py-0.5 text-[14px] px-1">:</td>
                        <td class="py-0.5 text-[14px]">{{ $data['klaimName'] ?? '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════
         TABEL RINCIAN OBAT
    ══════════════════════════════════════ --}}
    <table class="w-full mb-1 text-[14px]" cellpadding="0" cellspacing="0">
        <thead>
            <tr class="border-b border-t border-gray-400">
                <th class="py-1 text-left font-semibold text-gray-900 w-8">No.</th>
                <th class="py-1 text-left font-semibold text-gray-900">Nama Obat</th>
                <th class="py-1 text-right font-semibold text-gray-900 w-16">Qty</th>
                <th class="py-1 text-right font-semibold text-gray-900 w-28">Harga (Rp)</th>
                <th class="py-1 text-right font-semibold text-gray-900 w-32">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['rincianObat'] as $index => $item)
                @php
                    $qtyVal = (float) ($item->qty ?? 0);
                    $totalVal = (int) ($item->obat ?? 0);
                    $hargaSatuan = $qtyVal > 0 ? $totalVal / $qtyVal : 0;
                    $qtyDisplay = floor($qtyVal) == $qtyVal
                        ? number_format($qtyVal, 0, ',', '.')
                        : rtrim(rtrim(number_format($qtyVal, 2, ',', '.'), '0'), ',');
                @endphp
                <tr class="border-b border-gray-100">
                    <td class="py-1 text-gray-700">{{ $index + 1 }}.</td>
                    <td class="py-1 text-gray-900">{{ $item->keterangan }}</td>
                    <td class="py-1 text-right tabular-nums text-gray-900">{{ $qtyDisplay }}</td>
                    <td class="py-1 text-right tabular-nums text-gray-900">
                        {{ number_format((int) round($hargaSatuan), 0, ',', '.') }}
                    </td>
                    <td class="py-1 text-right tabular-nums text-gray-900">
                        {{ number_format($totalVal, 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-3 text-center text-gray-700 italic">
                        Tidak ada data obat.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            {{-- Total Obat --}}
            <tr class="border-t-2 border-gray-400">
                <td colspan="4" class="pt-2 pb-1 font-bold text-[14px] text-right pr-3 text-gray-900">Total</td>
                <td class="pt-2 pb-1 font-bold text-[15px] text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['totalObat'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Terbilang — dari totalObat --}}
    <div class="mb-5 px-3 py-2 bg-gray-50 border border-gray-400 rounded text-[12px] text-gray-700 italic">
        Terbilang:
        <strong class="not-italic text-gray-900">
            {{ \App\Support\Terbilang::rupiah((int) ($data['totalObat'] ?? 0)) }}
        </strong>
    </div>

    {{-- ══════════════════════════════════════
         TANDA TANGAN
    ══════════════════════════════════════ --}}
    <table class="w-full mt-4 text-[14px]" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-5/12 text-center align-bottom">
                <p class="mb-16 text-gray-700">Petugas Farmasi</p>
                <div class="inline-block pt-1 border-t border-gray-300" style="min-width:140px;">
                    <span class="text-gray-900">
                        {{ $data['kasirName'] ?? '( ................................ )' }}
                    </span>
                </div>
            </td>

            <td class="w-2/12"></td>

            <td class="w-5/12 text-center align-bottom">
                <p class="text-gray-700">Tulungagung, {{ $data['tglCetak'] ?? '' }}</p>
                <p class="mb-16 text-gray-700">Pasien / Wali</p>
                <div class="inline-block pt-1 border-t border-gray-300" style="min-width:140px;">
                    <span class="text-gray-900">
                        {{ $data['regName'] ?? '( ................................ )' }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════
         FOOTER INFO CETAK
    ══════════════════════════════════════ --}}
    <div class="mt-6 pt-2 border-t border-gray-400 text-[11px] text-gray-700 flex justify-between">
        <span>
            Dicetak oleh: {{ $data['cetakOleh'] ?? '-' }} —
            {{ $data['tglCetak'] ?? '' }}, pukul {{ $data['jamCetak'] ?? '' }}
        </span>
        <span>No. RJ: {{ $data['rjNo'] ?? '-' }}</span>
    </div>

</x-pdf.layout-kwitansi>
