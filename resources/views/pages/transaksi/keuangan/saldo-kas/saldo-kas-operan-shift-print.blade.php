{{-- resources/views/pages/transaksi/keuangan/saldo-kas/saldo-kas-operan-shift-print.blade.php --}}
{{--
    Laporan Operan Kas: SATU akun SATU shift, untuk serah terima kas antar petugas shift.
    Ukuran kwitansi: layout-kwitansi (tengahA4 bawaan) mencetak isi di kotak ¼ A4 (105 × 148.5 mm)
    tengah atas kertas A4 — cetakOperanShift() tetap setPaper('A4'). Lebar isi ±93 mm, jadi
    huruf 7–8px. SENGAJA tanpa rincian transaksi: kertas ini laporan serah terima;
    rincian per shift diunduh sebagai data Excel (unduhCsv($shift)) untuk compare data.
--}}
@php
    // Semua tanggal sudah dipra-format di method cetakOperanShift() — di sini hanya format angka.
    $formatAngka = fn($nilai) => number_format((float) $nilai, 0, '.', ',');
    $sel = 'border border-gray-400 px-0.5 py-px';
@endphp

<x-pdf.layout-kwitansi title="LAPORAN OPERAN KAS PER SHIFT">
    <div class="mt-1 text-[7px]">

        {{-- ── INFO AKUN & SHIFT ── --}}
        <table class="w-full mb-1 border-collapse">
            <tr>
                <td class="w-[34px] text-gray-600">Akun Kas</td>
                <td class="font-bold">: {{ $accId }} — {{ $accDesc }}</td>
            </tr>
            <tr>
                <td class="text-gray-600">Shift</td>
                <td class="font-bold">: {{ $tanggalLabel }} — SHIFT {{ $shift }}@if ($range) ({{ $range }})@endif</td>
            </tr>
            <tr>
                <td class="text-gray-600">Dicetak</td>
                <td>: {{ $dicetakPada }}@if ($dicetakOleh !== '') oleh {{ $dicetakOleh }}@endif</td>
            </tr>
        </table>

        {{-- ── RINGKASAN SHIFT ── --}}
        <table class="w-full mb-1 border-collapse text-center">
            <tr>
                <td class="{{ $sel }} w-1/4 bg-gray-100">
                    <div class="text-gray-700">Saldo Awal</div>
                    <div class="font-bold text-gray-900">{{ $formatAngka($saldoAwal) }}</div>
                </td>
                <td class="{{ $sel }} w-1/4 bg-blue-50">
                    <div class="text-gray-700">Masuk</div>
                    <div class="font-bold text-gray-900">{{ $formatAngka($totalDebit) }}</div>
                </td>
                <td class="{{ $sel }} w-1/4 bg-rose-50">
                    <div class="text-gray-700">Keluar</div>
                    <div class="font-bold text-gray-900">{{ $formatAngka($totalKredit) }}</div>
                </td>
                <td class="{{ $sel }} w-1/4 bg-emerald-50">
                    <div class="text-gray-700">Saldo Akhir</div>
                    <div class="text-[8px] font-bold text-gray-900">{{ $formatAngka($saldoAkhir) }}</div>
                </td>
            </tr>
        </table>

        {{-- ── REKAP PER JENIS ── --}}
        <table class="w-full mb-1 border-collapse">
            <thead>
                <tr class="bg-gray-100">
                    <th class="{{ $sel }} text-left">JENIS</th>
                    <th class="{{ $sel }} w-5 text-center">JML</th>
                    <th class="{{ $sel }} w-12 text-right">MASUK</th>
                    <th class="{{ $sel }} w-12 text-right">KELUAR</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rekapJenis as $rekap)
                    <tr>
                        <td class="{{ $sel }}">{{ $rekap->jenis }}</td>
                        <td class="{{ $sel }} text-center">{{ $rekap->count }}</td>
                        <td class="{{ $sel }} text-right">{{ $rekap->debit > 0 ? $formatAngka($rekap->debit) : '—' }}</td>
                        <td class="{{ $sel }} text-right">{{ $rekap->kredit > 0 ? $formatAngka($rekap->kredit) : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="{{ $sel }} py-1 text-center text-gray-500">
                            Tidak ada transaksi pada shift ini — saldo tidak berubah.
                        </td>
                    </tr>
                @endforelse
                <tr class="font-bold bg-gray-100">
                    <td class="{{ $sel }} text-right">TOTAL</td>
                    <td class="{{ $sel }} text-center">{{ $jumlahTrx }}</td>
                    <td class="{{ $sel }} text-right">{{ $formatAngka($totalDebit) }}</td>
                    <td class="{{ $sel }} text-right">{{ $formatAngka($totalKredit) }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Hitung fisik + TTD tak boleh terpisah halaman. Tailwind v3 tak punya kelas page-break-inside
             (break-inside-avoid tak dikenali dompdf) → satu-satunya inline style di berkas ini. --}}
        <div style="page-break-inside: avoid;">

            {{-- ── HITUNG FISIK (diisi tangan saat serah terima) ── --}}
            <table class="w-full mt-1.5 border-collapse">
                <tr>
                    <td class="{{ $sel }} w-1/3 align-top">
                        Saldo sistem
                        <div class="font-bold">{{ $formatAngka($saldoAkhir) }}</div>
                    </td>
                    <td class="{{ $sel }} w-1/3 align-top">Uang fisik<div>&nbsp;</div></td>
                    <td class="{{ $sel }} w-1/3 align-top">Selisih<div>&nbsp;</div></td>
                </tr>
            </table>

            {{-- ── TTD SERAH TERIMA — TTD basah, ruang berisi &nbsp; agar tak collapse di dompdf ── --}}
            <table class="w-full mt-1.5 border-collapse">
                <tr>
                    <td class="w-1/2 text-center align-top">
                        <div class="text-gray-900">Yang Menyerahkan</div>
                        <div class="text-gray-700">(Petugas Shift {{ $shift }})</div>
                        <div class="h-8">&nbsp;</div>
                        <span class="inline-block w-24 border-t border-black pt-0.5 font-bold">{!! $dicetakOleh !== '' ? e($dicetakOleh) : '&nbsp;' !!}</span>
                    </td>
                    <td class="w-1/2 text-center align-top">
                        <div class="text-gray-900">Yang Menerima</div>
                        <div class="text-gray-700">(Petugas Shift Berikutnya)</div>
                        <div class="h-8">&nbsp;</div>
                        <span class="inline-block w-24 border-t border-black pt-0.5 font-bold">&nbsp;</span>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</x-pdf.layout-kwitansi>
