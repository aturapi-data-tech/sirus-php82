<x-pdf.layout-a4-with-out-background title="PERINCIAN BIAYA PENGOBATAN DAN PERAWATAN">

    {{-- ══════════════════════════════════════
         HEADER PASIEN (slot patientData) — dua kolom, sebelah kop
         Kiri  : identitas pasien (standar)
         Kanan : data perawatan (tgl masuk/keluar, jenis klaim)
         Huruf 10px supaya muat. Lebar dipatok 540px — melebihi jatah 50%
         dari layout — untuk memakai kolom kosong 45% di dalam sel kop;
         sisanya masih cukup untuk blok alamat RS. Nilai tanggal dikunci
         nowrap agar jam tidak turun baris. Lebar pakai inline style
         karena kelas arbitrary Tailwind tidak ter-render di dompdf.
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

        // Class helpers (4 kolom: label | qty | amount | subtotal section)
        $hdrSection = 'pt-1 pb-px font-bold';
        $cellLabel  = 'py-px pl-3.5 pr-1';
        $cellQty    = 'py-px px-1.5 text-center text-gray-600 w-[60px]';
        $cellAmt    = 'py-px px-1.5 text-right tabular-nums';
        $cellSubTot = 'py-0.5 px-1.5 text-right tabular-nums border-t border-gray-600';
    @endphp

    {{-- ══════════════════════════════════════
         MAIN TABLE — 4 kolom konsisten:
         (1) label | (2) qty (X) | (3) nominal item | (4) subtotal section
    ══════════════════════════════════════ --}}
    <table width="100%" cellpadding="0" cellspacing="0" class="text-[10px] mt-2 border-collapse">

        {{-- ─── A. BIAYA KAMAR ─── --}}
        <tr><td colspan="4" class="{{ $hdrSection }}">A. BIAYA KAMAR</td></tr>
        <tr>
            <td colspan="4" class="p-0">
                <table width="100%" cellpadding="0" cellspacing="0" class="text-[10px] border-collapse">
                    <tr class="text-gray-700">
                        <td class="py-px pl-3.5 pr-1.5 w-[115px]">Tgl. Masuk</td>
                        <td class="py-px px-1.5 w-[115px]">Tgl. Keluar</td>
                        <td class="py-px px-1.5">Kamar</td>
                        <td class="py-px px-1.5 w-10 text-center">Hari</td>
                        <td class="py-px px-1.5 w-[110px] text-right">Biaya Kamar</td>
                        <td class="py-px px-1.5 w-[110px] text-right">Total Biaya</td>
                    </tr>
                    @forelse ($data['trfrooms'] as $kamar)
                        <tr>
                            <td class="py-px pl-3.5 pr-1.5">{{ $kamar->start_date }}</td>
                            <td class="py-px px-1.5">{{ $kamar->end_date ?? '-' }}</td>
                            <td class="py-px px-1.5">{{ $kamar->room_label }}</td>
                            <td class="py-px px-1.5 text-center">{{ $kamar->day }}</td>
                            <td class="py-px px-1.5 text-right tabular-nums">{{ $rp($kamar->room_price) }}</td>
                            <td class="py-px px-1.5 text-right tabular-nums">{{ $rp($kamar->room_total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-1.5 px-1.5 text-center italic text-gray-500">-</td></tr>
                    @endforelse
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }} w-[110px]">{{ $rp($data['aTotal']) }}</td>
        </tr>

        {{-- ─── B. JASA MEDIS — per-item (dgn qty X) ─── --}}
        <tr><td colspan="4" class="{{ $hdrSection }}">B. JASA MEDIS</td></tr>
        @foreach ($data['bItems'] as $item)
        <tr>
            <td class="{{ $cellLabel }} uppercase">{{ $item->desc }}</td>
            <td class="{{ $cellQty }}">@if (!is_null($item->qty)){{ $item->qty }} (X)@endif</td>
            <td class="{{ $cellAmt }} w-[110px]">{{ $rp($item->total) }}</td>
            <td class="w-[110px]"></td>
        </tr>
        @endforeach
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }}">{{ $rp($data['bTotal']) }}</td>
        </tr>

        {{-- ─── C. PENUNJANG DIAGNOSTIK ─── --}}
        <tr><td colspan="4" class="{{ $hdrSection }}">C. PENUNJANG DIAGNOSTIK</td></tr>
        <tr>
            <td class="{{ $cellLabel }}">LABORATORIUM</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp($data['cLab']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="{{ $cellLabel }}">RADIOLOGI</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp($data['cRad']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }}">{{ $rp($data['cTotal']) }}</td>
        </tr>

        {{-- ─── D. PEMAKAIAN OBAT ─── --}}
        <tr><td colspan="4" class="{{ $hdrSection }}">D. PEMAKAIAN OBAT</td></tr>
        <tr>
            <td class="{{ $cellLabel }}">OBAT PINJAM</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp($data['dObatPinjam']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="{{ $cellLabel }}">BON RESEP</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp($data['dBonResep']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="{{ $cellLabel }}">RESEP LUNAS</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp($data['dResepLunas']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }}">{{ $rp($data['dTotal']) }}</td>
        </tr>

        {{-- ─── E. OPERASI (hanya tampil bila ada) ─── --}}
        @if ($data['eOperasi'] > 0)
        <tr><td colspan="4" class="{{ $hdrSection }}">E. OPERASI</td></tr>
        <tr>
            <td class="{{ $cellLabel }}">KAMAR OPERASI (OK)</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp($data['eOperasi']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }}">{{ $rp($data['eOperasi']) }}</td>
        </tr>
        @endif

        {{-- ─── F. ADMINISTRASI DAN LAIN-LAIN ─── --}}
        <tr><td colspan="4" class="{{ $hdrSection }}">F. ADMINISTRASI DAN LAIN-LAIN</td></tr>
        <tr>
            <td class="{{ $cellLabel }}">BIAYA SELAMA DI RAWAT JALAN DAN UGD</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp($data['fTrfRjUgd']) }}</td>
            <td></td>
        </tr>
        @foreach ($data['fOthers'] as $oth)
        <tr>
            <td class="{{ $cellLabel }} uppercase">{{ $oth->desc }}</td>
            <td class="{{ $cellQty }}">{{ $oth->qty }} (X)</td>
            <td class="{{ $cellAmt }}">{{ $rp($oth->total) }}</td>
            <td></td>
        </tr>
        @endforeach
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }}">{{ $rp($data['fTotal']) }}</td>
        </tr>

        {{-- ─── G. RETURN OBAT ─── --}}
        @if ($data['gReturObat'] > 0)
        <tr><td colspan="4" class="{{ $hdrSection }}">G. RETURN OBAT</td></tr>
        <tr>
            <td class="{{ $cellLabel }}">RETURN OBAT</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">( {{ $rp($data['gReturObat']) }} )</td>
            <td class="{{ $cellAmt }}">( {{ $rp($data['gReturObat']) }} )</td>
        </tr>
        @endif

        {{-- ─── FOOTER ─── --}}
        <tr><td colspan="4" class="p-0 border-t border-gray-800"></td></tr>
        <tr>
            <td colspan="3" class="py-0.5 px-1.5 text-right font-semibold">TOTAL BIAYA</td>
            <td class="py-0.5 px-1.5 text-right tabular-nums font-semibold">{{ $rp($data['subtotal']) }}</td>
        </tr>
        {{-- Selalu tampil walau 0 — baris yang kadang ada kadang hilang bikin
             pembaca ragu apakah cetakannya beda perlakuan. --}}
        <tr>
            <td colspan="3" class="py-px px-1.5 text-right">RESEP LUNAS (dibayar di apotek)</td>
            <td class="py-px px-1.5 text-right tabular-nums">( {{ $rp($data['resepLunasFooter']) }} )</td>
        </tr>
        <tr>
            <td colspan="3" class="py-px px-1.5 text-right">SUBSIDI</td>
            <td class="py-px px-1.5 text-right tabular-nums">( {{ $rp($data['subsidi']) }} )</td>
        </tr>
        <tr>
            <td colspan="3" class="py-0.5 px-1.5 text-right font-semibold border-t border-gray-400">TOTAL BAYAR</td>
            <td class="py-0.5 px-1.5 text-right tabular-nums font-semibold border-t border-gray-400">
                {{ $rp($data['grandTotal']) }}
            </td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════
         TELAH DIBAYAR — TERBILANG
    ══════════════════════════════════════ --}}
    <p class="mt-2 mb-px text-[10px]">Telah dibayar secara tunai sebesar :</p>
    <p class="m-0 text-[10px] font-semibold uppercase italic">
        {{ \App\Support\Terbilang::rupiah((int) $data['grandTotal']) }}
    </p>

    {{-- ══════════════════════════════════════
         TANDA TANGAN
    ══════════════════════════════════════ --}}
    <table width="100%" cellpadding="0" cellspacing="0" class="text-[10px] mt-2.5">
        <tr class="align-top">
            <td width="50%" class="text-center pr-4">
                <p class="m-0">Yang Membayar</p>
                <p class="m-0 text-gray-600">ttd</p>
                <p class="mt-9 mb-0">( ......................................... )</p>
            </td>
            <td width="50%" class="text-center pl-4">
                <p class="m-0">Tulungagung, {{ $data['tglCetak'] }}</p>
                <p class="m-0 text-gray-600">Petugas Administrasi</p>
                <p class="mt-9 mb-0 font-semibold">( {{ $data['kasirName'] ?? '.........................................' }} )</p>
            </td>
        </tr>
    </table>

    <p class="mt-2.5 text-[10px] text-center text-gray-500 italic">
        TERIMAKASIH ATAS KEPERCAYAAN ANDA TERHADAP PELAYANAN KAMI
    </p>

    <p class="mt-1 text-[9px] text-gray-500 italic">
        Dicetak: {{ $data['tglCetak'] }} {{ $data['jamCetak'] }} oleh {{ $data['cetakOleh'] }}
    </p>

</x-pdf.layout-a4-with-out-background>
