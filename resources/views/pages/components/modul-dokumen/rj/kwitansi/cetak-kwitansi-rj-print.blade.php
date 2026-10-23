{{-- cetak-kwitansi-rj-print.blade.php --}}

<x-pdf.layout-kwitansi :title="$data['judul'] ?? 'KWITANSI PEMBAYARAN - Rawat Jalan'">

    {{-- ══════════════════════════════════════
         IDENTITAS KUNJUNGAN — dua kolom
         Kiri  : identitas pasien (standar) + No. Rawat Jalan
         Kanan : data kunjungan (tanggal, poli, dokter, penjamin, SEP)
         Pakai tabel biasa (dompdf tidak mendukung flex/grid).
    ══════════════════════════════════════ --}}
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
                    class="w-full">
                    <tr>
                        <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap">No. Rawat Jalan</td>
                        <td class="py-0 text-[8px] px-1">:</td>
                        <td class="py-0 text-[8px] font-bold">{{ $data['rjNo'] ?? '-' }}</td>
                    </tr>
                </x-pdf.identitas-pasien>
            </td>

            <td class="w-1/2 align-top pl-2">
                <table class="w-full" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap">Tgl. Rawat Jalan</td>
                        <td class="py-0 text-[8px] px-1">:</td>
                        <td class="py-0 text-[8px] font-bold">{{ $data['rjDate'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap">Poli / Klinik</td>
                        <td class="py-0 text-[8px] px-1">:</td>
                        <td class="py-0 text-[8px]">{{ $data['poliName'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap align-top">Dokter</td>
                        <td class="py-0 text-[8px] px-1 align-top">:</td>
                        <td class="py-0 text-[8px]">{{ $data['drName'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap">Jenis Pembayaran</td>
                        <td class="py-0 text-[8px] px-1">:</td>
                        <td class="py-0 text-[8px]">{{ $data['klaimName'] ?? '-' }}</td>
                    </tr>

                    {{-- ── Baris SEP — hanya tampil jika pasien BPJS ── --}}
                    @if (!empty($data['isBpjs']) && !empty($data['sep']))
                        <tr>
                            <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap">No. SEP</td>
                            <td class="py-0 text-[8px] px-1">:</td>
                            <td class="py-0 text-[8px] font-bold tracking-wide">
                                {{ $data['sep']['noSep'] ?? '-' }}
                            </td>
                        </tr>
                        @if (!empty($data['sep']['noReferensi']))
                            <tr>
                                <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap">No. Referensi</td>
                                <td class="py-0 text-[8px] px-1">:</td>
                                <td class="py-0 text-[8px]">{{ $data['sep']['noReferensi'] }}</td>
                            </tr>
                        @endif
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════
         TABEL RINCIAN BIAYA
    ══════════════════════════════════════ --}}
    <table class="w-full mb-1 text-[8px]" cellpadding="0" cellspacing="0">
        <thead>
            <tr class="border-b border-t border-gray-400">
                <th class="py-0 text-left font-semibold text-gray-900 w-5">No.</th>
                <th class="py-0 text-left font-semibold text-gray-900">Keterangan</th>
                <th class="py-0 text-right font-semibold text-gray-900 w-20">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['rincian'] as $index => $item)
                <tr class="border-b border-gray-100">
                    <td class="py-0 text-gray-700">{{ $index + 1 }}.</td>
                    <td class="py-0 text-gray-900">{{ $item->txn_desc }}</td>
                    <td class="py-0 text-right tabular-nums text-gray-900">
                        {{ number_format((int) $item->txn_nominal, 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="py-3 text-center text-gray-700 italic">
                        Tidak ada rincian biaya.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>

            {{-- Subtotal --}}
            <tr class="border-t border-gray-300">
                <td colspan="2" class="pt-1 pb-0.5 text-right pr-2 text-gray-700">Subtotal</td>
                <td class="pt-1 pb-0.5 text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['subtotal'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>

            {{-- Diskon --}}
            <tr>
                <td colspan="2" class="pb-0.5 text-right pr-2 text-gray-700">Diskon</td>
                <td class="pb-0.5 text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['rjDiskon'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>

            {{-- Total --}}
            <tr class="border-t-2 border-gray-400">
                <td colspan="2" class="pt-1 pb-1 font-bold text-[8px] text-right pr-2 text-gray-900">Total</td>
                <td class="pt-1 pb-1 font-bold text-[9px] text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['grandTotal'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>

            {{-- Pembayaran --}}
            {{-- <tr class="border-t border-gray-200">
                <td colspan="2" class="pt-1 pb-0.5 text-right pr-2 text-gray-700">Pembayaran</td>
                <td class="pt-1 pb-0.5 text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['sudahBayar'] ?? 0, 0, ',', '.') }}
                </td>
            </tr> --}}

            {{-- Sisa --}}
            {{-- <tr>
                <td colspan="2" class="pb-1 text-right pr-2 text-gray-700">Sisa</td>
                <td class="pb-1 text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['sisa'] ?? 0, 0, ',', '.') }}
                </td>
            </tr> --}}

        </tfoot>
    </table>

    {{-- Terbilang — dari grandTotal (total yang harus dibayar) --}}
    <div class="mb-2 px-2 py-1 bg-gray-50 border border-gray-400 rounded text-[7px] text-gray-700 italic">
        Terbilang:
        <strong class="not-italic text-gray-900">
            {{ \App\Support\Terbilang::rupiah((int) ($data['grandTotal'] ?? 0)) }}
        </strong>
    </div>

    {{-- ══════════════════════════════════════
         TANDA TANGAN
    ══════════════════════════════════════ --}}
    <table class="w-full mt-2 text-[8px]" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-5/12 text-center align-bottom">
                <p class="mb-6 text-gray-700">Kasir / Petugas Administrasi</p>
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

    {{-- ══════════════════════════════════════
         FOOTER INFO CETAK
    ══════════════════════════════════════ --}}
    <div class="mt-2 pt-1 border-t border-gray-400 text-[6px] text-gray-700 flex justify-between">
        <span>
            Dicetak oleh: {{ $data['cetakOleh'] ?? '-' }} —
            {{ $data['tglCetak'] ?? '' }}, pukul {{ $data['jamCetak'] ?? '' }}
        </span>
        <span>No. RJ: {{ $data['rjNo'] ?? '-' }}</span>
    </div>

</x-pdf.layout-kwitansi>
