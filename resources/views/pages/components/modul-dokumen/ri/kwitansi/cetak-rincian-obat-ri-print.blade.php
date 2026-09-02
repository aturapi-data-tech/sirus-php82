<x-pdf.layout-a4-with-out-background title="PERINCIAN BIAYA PENGOBATAN DAN PERAWATAN">

    {{-- ══════════════════════════════════════
         HEADER PASIEN (slot patientData) — dua kolom, sebelah kop
         Sama persis dengan kwitansi detail supaya lembar ini bisa
         dilampirkan di belakangnya tanpa terlihat beda format.
    ══════════════════════════════════════ --}}
    <x-slot name="patientData">
        <table cellpadding="0" cellspacing="0" style="width:540px;">
            <tr>
                <td class="align-top" style="width:62%;">
                    <x-pdf.identitas-pasien
                        :rm="$data['regNo'] ?? null"
                        :nama="$data['regName'] ?? null"
                        :jenisKelamin="($data['sex'] ?? '') === 'L' ? 'Laki-laki' : (($data['sex'] ?? '') === 'P' ? 'Perempuan' : null)"
                        :tempatLahir="$data['birthPlace'] ?? null"
                        :tglLahir="$data['birthDate'] ?? null"
                        :umur="$data['umur'] ?? null"
                        :alamat="$data['address'] ?? null"
                        textClass="text-[10px]"
                        class="w-full" />
                </td>

                <td class="align-top pl-2" style="width:38%;">
                    <table class="w-full" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="py-0.5 text-[10px] text-gray-500 whitespace-nowrap">Tgl. Masuk</td>
                            <td class="py-0.5 text-[10px] px-1">:</td>
                            <td class="py-0.5 text-[10px] whitespace-nowrap">{{ $data['entryDate'] }}</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 text-[10px] text-gray-500 whitespace-nowrap">Tgl. Keluar</td>
                            <td class="py-0.5 text-[10px] px-1">:</td>
                            <td class="py-0.5 text-[10px] whitespace-nowrap">{{ $data['exitDate'] }}</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 text-[10px] text-gray-500 whitespace-nowrap">Jenis Klaim</td>
                            <td class="py-0.5 text-[10px] px-1">:</td>
                            <td class="py-0.5 text-[10px] whitespace-nowrap">{{ $data['klaimName'] }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </x-slot>

    @php
        $rp = fn(int $nilai) => number_format($nilai, 0, ',', '.');

        // Class helpers — 4 kolom: label | qty | nominal item | subtotal section
        $hdrSection = 'pt-1 pb-px font-bold';
        $cellLabel  = 'py-px pl-3.5 pr-1';
        $cellQty    = 'py-px px-1.5 text-center text-gray-600 w-[60px]';
        $cellAmt    = 'py-px px-1.5 text-right tabular-nums w-[110px]';
        $cellSubTot = 'py-0.5 px-1.5 text-right tabular-nums border-t border-gray-600';
    @endphp

    <p class="mt-2 mb-px text-[10px] font-bold">RINCIAN PEMAKAIAN OBAT PASIEN RAWAT INAP</p>

    <table width="100%" cellpadding="0" cellspacing="0" class="text-[10px] border-collapse">

        {{-- ─── OBAT PINJAM ─── --}}
        <tr><td colspan="4" class="{{ $hdrSection }}">OBAT PINJAM</td></tr>
        @forelse ($data['obatPinjam'] as $obat)
        <tr>
            <td class="{{ $cellLabel }} uppercase">{{ $obat->product_name }}</td>
            <td class="{{ $cellQty }}">{{ (int) $obat->qty }} (X)</td>
            <td class="{{ $cellAmt }}">{{ $rp((int) $obat->total) }}</td>
            <td class="w-[110px]"></td>
        </tr>
        @empty
        <tr>
            <td class="{{ $cellLabel }} italic text-gray-500">Tidak ada obat pinjam</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp(0) }}</td>
            <td class="w-[110px]"></td>
        </tr>
        @endforelse
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }}">{{ $rp($data['totalObatPinjam']) }}</td>
        </tr>

        {{-- ─── RESEP OBAT ─── --}}
        <tr><td colspan="4" class="{{ $hdrSection }}">RESEP OBAT</td></tr>
        @forelse ($data['resepObat'] as $obat)
        <tr>
            <td class="{{ $cellLabel }} uppercase">{{ $obat->product_name }}</td>
            <td class="{{ $cellQty }}">{{ (int) $obat->qty }} (X)</td>
            <td class="{{ $cellAmt }}">{{ $rp((int) $obat->total) }}</td>
            <td class="w-[110px]"></td>
        </tr>
        @empty
        <tr>
            <td class="{{ $cellLabel }} italic text-gray-500">Tidak ada resep obat</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp(0) }}</td>
            <td class="w-[110px]"></td>
        </tr>
        @endforelse
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }}">{{ $rp($data['totalResepObat']) }}</td>
        </tr>

        {{-- ─── RETUR OBAT (hanya bila ada) — mengurangi total pemakaian ─── --}}
        @if ($data['totalReturObat'] > 0)
        <tr><td colspan="4" class="{{ $hdrSection }}">RETUR OBAT</td></tr>
        @foreach ($data['returObat'] as $obat)
        <tr>
            <td class="{{ $cellLabel }} uppercase">{{ $obat->product_name }}</td>
            <td class="{{ $cellQty }}">{{ (int) $obat->qty }} (X)</td>
            <td class="{{ $cellAmt }}">( {{ $rp((int) $obat->total) }} )</td>
            <td class="w-[110px]"></td>
        </tr>
        @endforeach
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }}">( {{ $rp($data['totalReturObat']) }} )</td>
        </tr>
        @endif

        {{-- ─── BIAYA TINDAKAN LAIN-LAIN (jasa karyawan tiap nota resep) ─── --}}
        <tr>
            <td class="pt-1 pb-px font-bold">BIAYA TINDAKAN LAIN-LAIN</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}"></td>
            <td class="py-0.5 px-1.5 text-right tabular-nums border-t border-gray-600 w-[110px]">
                {{ $rp($data['tindakanLain']) }}
            </td>
        </tr>

        {{-- ─── TOTAL ─── --}}
        <tr>
            <td colspan="3" class="py-0.5 px-1.5 text-right font-bold">TOTAL PEMAKAIAN OBAT</td>
            <td class="py-0.5 px-1.5 text-right tabular-nums font-bold border-t border-gray-600">
                {{ $rp($data['totalPemakaian']) }}
            </td>
        </tr>
    </table>

    <p class="mt-2 mb-px text-[10px]">Terbilang :</p>
    <p class="m-0 text-[10px] font-semibold uppercase italic">
        {{ \App\Support\Terbilang::rupiah((int) $data['totalPemakaian']) }}
    </p>

    {{-- ══════════════════════════════════════
         TANDA TANGAN
    ══════════════════════════════════════ --}}
    <table width="100%" cellpadding="0" cellspacing="0" class="text-[10px] mt-2.5">
        <tr class="align-top">
            <td width="50%" class="text-center pr-4">&nbsp;</td>
            <td width="50%" class="text-center pl-4">
                <p class="m-0">Tulungagung, {{ $data['tglCetak'] }}</p>
                <p class="m-0 text-gray-600">Petugas Administrasi</p>
                <p class="mt-9 mb-0 font-semibold">( {{ $data['kasirName'] ?? '.........................................' }} )</p>
            </td>
        </tr>
    </table>

    <p class="mt-1 text-[9px] text-gray-500 italic">
        Dicetak: {{ $data['tglCetak'] }} {{ $data['jamCetak'] }} oleh {{ $data['cetakOleh'] }}
    </p>

</x-pdf.layout-a4-with-out-background>
