{{-- Kwitansi Pembayaran Piutang (mode pasien) / Bukti Penerimaan (mode bundel, banyak pasien).
     Data dirakit di pembayaran-piutang-actions::kwitansiPembayaran() dari hasil alokasi FIFO. --}}
<x-pdf.layout-kwitansi :title="$data['judul']">

    @php
        $jalurLabel = ['RJ' => 'Rawat Jalan', 'UGD' => 'UGD', 'RI' => 'Rawat Inap'];
        $pasien = $data['pasien'];
    @endphp

    {{-- IDENTITAS (kiri) + DATA PEMBAYARAN (kanan) — tabel, dompdf tak dukung flex/grid --}}
    <table class="w-full mb-2" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-1/2 align-top">
                @if ($data['satuPasien'] && $pasien)
                    <x-pdf.identitas-pasien
                        :rm="$pasien->reg_no"
                        :nama="$pasien->reg_name"
                        :jenisKelamin="($pasien->sex ?? '') === 'L' ? 'Laki-laki' : (($pasien->sex ?? '') === 'P' ? 'Perempuan' : null)"
                        :tempatLahir="$pasien->birth_place"
                        :tglLahir="$pasien->birth_date"
                        :umur="$data['umur']"
                        :alamat="$pasien->address"
                        textClass="text-[8px]"
                        class="w-full" />
                @else
                    <table class="w-full" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap align-top">Kelompok</td>
                            <td class="py-0 text-[8px] px-1 align-top">:</td>
                            <td class="py-0 text-[8px] font-bold">{{ $data['judulKonteks'] ?: 'Pembayaran piutang beberapa pasien' }}</td>
                        </tr>
                        <tr>
                            <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap">Jumlah Nota</td>
                            <td class="py-0 text-[8px] px-1">:</td>
                            <td class="py-0 text-[8px]">{{ count($data['rincian']) }} nota</td>
                        </tr>
                    </table>
                @endif
            </td>

            <td class="w-1/2 align-top pl-2">
                <table class="w-full" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap align-top">Tgl Bayar</td>
                        <td class="py-0 text-[8px] px-1 align-top">:</td>
                        <td class="py-0 text-[8px] font-bold">{{ $data['tanggal'] }} · Shift {{ $data['shift'] }}</td>
                    </tr>
                    <tr>
                        <td class="py-0 text-[8px] text-gray-500 whitespace-nowrap align-top">Akun Kas</td>
                        <td class="py-0 text-[8px] px-1 align-top">:</td>
                        <td class="py-0 text-[8px]">{{ $data['accName'] ?? '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- UNTUK PEMBAYARAN — keterangan kasir ditonjolkan (mis. "biaya ambulans" saat yang lain
         sudah tercover); kosong = kalimat umum. --}}
    <div class="mb-2 px-2 py-1 border border-gray-400 rounded text-[8px] text-gray-900">
        <span class="text-gray-500">Untuk pembayaran:</span>
        <strong class="text-[9px]">{{ filled($data['keterangan']) ? $data['keterangan'] : 'Tagihan biaya pelayanan yang belum lunas' }}</strong>
    </div>

    {{-- RINCIAN NOTA — urut FIFO (nota terlama dilunasi lebih dulu) --}}
    <table class="w-full mb-1 text-[8px]" cellpadding="0" cellspacing="0">
        <thead>
            <tr class="border-b border-t border-gray-400">
                <th class="py-0 text-left font-semibold text-gray-900 w-5">No.</th>
                <th class="py-0 text-left font-semibold text-gray-900">Keterangan</th>
                <th class="py-0 text-right font-semibold text-gray-900 w-14">Dibayar</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['rincian'] as $indeks => $row)
                <tr class="border-b border-gray-100">
                    <td class="py-0 text-gray-700 align-top">{{ $indeks + 1 }}.</td>
                    <td class="py-0 text-gray-900 align-top">
                        {{ $row['jenis'] ?? ($row['lunas'] ? 'Pelunasan' : 'Angsuran') }} tagihan
                        {{ $jalurLabel[$row['jalur']] ?? $row['jalur'] }} No. {{ $row['no'] }}
                        <span class="text-gray-500">(kunjungan {{ \Illuminate\Support\Str::before($row['tgl'], ' ') ?: '-' }})</span>
                        @unless ($data['satuPasien'])
                            <br><span class="text-gray-700 uppercase">{{ $row['nama'] }}</span>
                            <span class="text-gray-500">· {{ $row['regNo'] }}</span>
                        @endunless
                    </td>
                    <td class="py-0 text-right tabular-nums text-gray-900 align-top">
                        {{ number_format($row['dibayar'], 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-gray-400">
                <td colspan="2" class="pt-1 pb-1 pr-2 font-bold text-[8px] text-right text-gray-900">Total Dibayar</td>
                <td class="pt-1 pb-1 font-bold text-[9px] text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['total'], 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- TERBILANG --}}
    <div class="mb-2 px-2 py-1 bg-gray-50 border border-gray-400 rounded text-[7px] text-gray-700 italic">
        Terbilang:
        <strong class="not-italic text-gray-900">{{ \App\Support\Terbilang::rupiah((int) $data['total']) }}</strong>
    </div>

    {{-- TANDA TANGAN --}}
    <table class="w-full mt-2 text-[8px]" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-5/12 text-center align-bottom">
                <p class="mb-6 text-gray-700">Kasir</p>
                <div class="inline-block pt-1 border-t border-gray-300" style="min-width:80px;">
                    <span class="text-gray-900">{{ $data['kasirName'] ?? '( ................................ )' }}</span>
                </div>
            </td>

            <td class="w-2/12"></td>

            <td class="w-5/12 text-center align-bottom">
                <p class="text-gray-700">Tulungagung, {{ $data['tglCetak'] }}</p>
                <p class="mb-6 text-gray-700">{{ $data['satuPasien'] ? 'Pasien / Wali' : 'Penyetor' }}</p>
                <div class="inline-block pt-1 border-t border-gray-300" style="min-width:80px;">
                    <span class="text-gray-900">
                        {{ $data['satuPasien'] && $pasien ? $pasien->reg_name : '( ................................ )' }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    {{-- FOOTER --}}
    <div class="mt-2 pt-1 border-t border-gray-400 text-[6px] text-gray-700">
        @if ($data['cetakUlang'] ?? false)
            <strong>CETAK ULANG</strong> —
        @endif
        Dicetak oleh: {{ $data['cetakOleh'] }} — {{ $data['tglCetak'] }}, pukul {{ $data['jamCetak'] }}
    </div>

</x-pdf.layout-kwitansi>
