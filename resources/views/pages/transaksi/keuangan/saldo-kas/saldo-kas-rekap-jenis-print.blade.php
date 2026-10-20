{{-- resources/views/pages/transaksi/keuangan/saldo-kas/saldo-kas-rekap-jenis-print.blade.php --}}
{{--
    Ukuran kwitansi (A4 bagi 4): dirender di kertas A6 lewat setPaper('a6') di cetakPerJenis().
    layout-kwitansi sendiri TIDAK menyempitkan isi — di kertas A4 isinya melebar selebar A4 —
    jadi ukuran kertasnya yang menentukan. PDF A6 yang dicetak ke kertas A4 skala 100%
    diletakkan printer/peramban di tengah kertas.
    Kelas Tailwind ter-render karena layout meng-inline CSS build — kelas BARU wajib npm run build.
--}}
@php
    // Data sudah disusun di method cetakPerJenis() — di sini hanya format angka.
    $formatAngka = fn($nilai) => number_format((float) $nilai, 0, '.', ',');
    $sel = 'border border-gray-400 px-0.5 py-px';
@endphp

<x-pdf.layout-kwitansi title="TOTAL KAS PER JENIS TRANSAKSI" :judulDiBawah="true">
    <div class="mt-1 text-[7px]">
        {{-- ── INFO AKUN & PERIODE ── --}}
        <table class="w-full mb-1 border-collapse">
            <tr>
                <td class="w-[34px] text-gray-600">Akun Kas</td>
                <td class="font-bold">: {{ $accId }} — {{ $accDesc }}</td>
            </tr>
            <tr>
                <td class="text-gray-600">Periode</td>
                <td class="font-bold">: {{ $periodeLabel }} &nbsp;<span class="font-normal text-gray-600">({{ $modeLabel }})</span></td>
            </tr>
            <tr>
                <td class="text-gray-600">Dicetak</td>
                <td>: {{ $dicetakPada }}</td>
            </tr>
        </table>

        {{-- ── RINGKASAN ── --}}
        <table class="w-full mb-1 text-center border-collapse">
            <tr>
                <td class="{{ $sel }} w-1/4 bg-gray-100">Saldo Awal<br><span class="text-[6px] text-gray-600">per {{ $saldoAwalTgl }}</span><br><b>{{ $formatAngka($saldoAwal) }}</b></td>
                <td class="{{ $sel }} w-1/4 bg-blue-50">Debit<br><span class="text-[6px]">&nbsp;</span><br><b>{{ $formatAngka($totalDebit) }}</b></td>
                <td class="{{ $sel }} w-1/4 bg-rose-50">Kredit<br><span class="text-[6px]">&nbsp;</span><br><b>{{ $formatAngka($totalKredit) }}</b></td>
                <td class="{{ $sel }} w-1/4 bg-emerald-50">Saldo Akhir<br><span class="text-[6px] text-gray-600">per {{ $sampaiLabel }}</span><br><b>{{ $formatAngka($saldoAkhir) }}</b></td>
            </tr>
        </table>

        {{-- ── TABEL PER JENIS ──
             Kolom mengikuti alur kas: SALDO AWAL + DEBIT − KREDIT = SALDO. Baris jenis hanya mengisi
             DEBIT/KREDIT; saldo awal & saldo akhir hanya di baris subtotal shift dan TOTAL. --}}
        <table class="w-full border-collapse">
            <thead>
                <tr class="text-[6px] bg-gray-100">
                    <th class="{{ $sel }} w-[4mm] text-center">NO</th>
                    <th class="{{ $sel }} text-left">JENIS TRANSAKSI</th>
                    <th class="{{ $sel }} w-[6mm] text-right">TRX</th>
                    <th class="{{ $sel }} w-[14mm] text-right">SALDO AWAL</th>
                    <th class="{{ $sel }} w-[14mm] text-right">DEBIT</th>
                    <th class="{{ $sel }} w-[14mm] text-right">KREDIT</th>
                    <th class="{{ $sel }} w-[14mm] text-right">SALDO</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kelompokJenis as $kelompok)
                    @if ($kelompok->judul)
                        <tr class="bg-green-50">
                            <td colspan="7" class="{{ $sel }} font-bold">{{ $kelompok->judul }}</td>
                        </tr>
                    @endif
                    @foreach ($kelompok->rekap as $nomor => $rekap)
                        <tr>
                            <td class="{{ $sel }} text-center">{{ $nomor + 1 }}</td>
                            <td class="{{ $sel }}">{{ $rekap->jenis }}</td>
                            <td class="{{ $sel }} text-right">{{ $rekap->count }}</td>
                            <td class="{{ $sel }}"></td>
                            <td class="{{ $sel }} text-right">{{ $rekap->debit > 0 ? $formatAngka($rekap->debit) : '—' }}</td>
                            <td class="{{ $sel }} text-right">{{ $rekap->kredit > 0 ? $formatAngka($rekap->kredit) : '—' }}</td>
                            <td class="{{ $sel }}"></td>
                        </tr>
                    @endforeach
                    @if ($kelompok->subtotalLabel)
                        <tr class="font-bold bg-gray-100">
                            <td colspan="2" class="{{ $sel }} text-right">{{ $kelompok->subtotalLabel }}</td>
                            <td class="{{ $sel }} text-right">{{ $kelompok->jumlahTrx }}</td>
                            <td class="{{ $sel }} text-right">{{ $formatAngka($kelompok->saldoAwal) }}</td>
                            <td class="{{ $sel }} text-right">{{ $formatAngka($kelompok->subtotalDebit) }}</td>
                            <td class="{{ $sel }} text-right">{{ $formatAngka($kelompok->subtotalKredit) }}</td>
                            <td class="{{ $sel }} text-right">{{ $formatAngka($kelompok->saldoAkhir) }}</td>
                        </tr>
                    @endif
                @endforeach

                <tr class="font-bold bg-emerald-50">
                    <td colspan="2" class="{{ $sel }}">TOTAL</td>
                    <td class="{{ $sel }} text-right">{{ $jumlahTrx }}</td>
                    <td class="{{ $sel }} text-right">{{ $formatAngka($saldoAwal) }}</td>
                    <td class="{{ $sel }} text-right">{{ $formatAngka($totalDebit) }}</td>
                    <td class="{{ $sel }} text-right">{{ $formatAngka($totalKredit) }}</td>
                    <td class="{{ $sel }} text-right">{{ $formatAngka($saldoAkhir) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-pdf.layout-kwitansi>
