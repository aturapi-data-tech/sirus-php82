{{-- resources/views/pages/transaksi/keuangan/saldo-kas/saldo-kas-rekap-jenis-print.blade.php --}}
{{--
    Ukuran kwitansi (A4 bagi 4): dirender di kertas A6 lewat setPaper('a6') di cetakPerJenis().
    layout-kwitansi sendiri TIDAK menyempitkan isi — di kertas A4 isinya melebar selebar A4 —
    jadi ukuran kertasnya yang menentukan. PDF A6 yang dicetak ke kertas A4 skala 100%
    diletakkan printer/peramban di tengah kertas.
    Semua gaya inline: kelas Tailwind arbitrary tidak dirender dompdf.
--}}
@php
    // Data sudah disusun di method cetakPerJenis() — di sini hanya format angka.
    $formatAngka = fn($nilai) => number_format((float) $nilai, 0, '.', ',');
    $sel = 'border:0.5pt solid #999; padding:1.5px 2.5px;';
@endphp

<x-pdf.layout-kwitansi title="TOTAL KAS PER JENIS">
    <div style="font-size:7px; margin-top:4px;">
        {{-- ── INFO AKUN & PERIODE ── --}}
        <table width="100%" cellspacing="0" style="border-collapse:collapse; margin-bottom:4px;">
            <tr>
                <td style="padding:0.5px 0; width:34px; color:#555;">Akun Kas</td>
                <td style="padding:0.5px 0; font-weight:bold;">: {{ $accId }} — {{ $accDesc }}</td>
            </tr>
            <tr>
                <td style="padding:0.5px 0; color:#555;">Periode</td>
                <td style="padding:0.5px 0; font-weight:bold;">: {{ $periodeLabel }} &nbsp;<span style="font-weight:normal; color:#555;">({{ $modeLabel }})</span></td>
            </tr>
            <tr>
                <td style="padding:0.5px 0; color:#555;">Dicetak</td>
                <td style="padding:0.5px 0;">: {{ $dicetakPada }}</td>
            </tr>
        </table>

        {{-- ── RINGKASAN ── --}}
        <table width="100%" cellspacing="0" style="border-collapse:collapse; margin-bottom:4px; text-align:center;">
            <tr>
                <td style="{{ $sel }} background:#f3f4f6; width:25%;">Saldo Awal<br><span style="font-size:6px; color:#555;">per {{ $saldoAwalTgl }}</span><br><b>{{ $formatAngka($saldoAwal) }}</b></td>
                <td style="{{ $sel }} background:#eff6ff; width:25%;">Debit<br><span style="font-size:6px;">&nbsp;</span><br><b>{{ $formatAngka($totalDebit) }}</b></td>
                <td style="{{ $sel }} background:#fff1f2; width:25%;">Kredit<br><span style="font-size:6px;">&nbsp;</span><br><b>{{ $formatAngka($totalKredit) }}</b></td>
                <td style="{{ $sel }} background:#ecfdf5; width:25%;">Saldo Akhir<br><span style="font-size:6px; color:#555;">per {{ $sampaiLabel }}</span><br><b>{{ $formatAngka($saldoAkhir) }}</b></td>
            </tr>
        </table>

        {{-- ── TABEL PER JENIS ──
             Kolom mengikuti alur kas: SALDO AWAL + DEBIT − KREDIT = SALDO. Baris jenis hanya mengisi
             DEBIT/KREDIT; saldo awal & saldo akhir hanya di baris subtotal shift dan TOTAL. --}}
        <table width="100%" cellspacing="0" style="border-collapse:collapse;">
            <thead>
                <tr style="background:#f3f4f6; font-size:6px;">
                    <th style="{{ $sel }} text-align:center; width:4mm;">NO</th>
                    <th style="{{ $sel }} text-align:left;">JENIS TRANSAKSI</th>
                    <th style="{{ $sel }} text-align:right; width:6mm;">TRX</th>
                    <th style="{{ $sel }} text-align:right; width:14mm;">SALDO AWAL</th>
                    <th style="{{ $sel }} text-align:right; width:14mm;">DEBIT</th>
                    <th style="{{ $sel }} text-align:right; width:14mm;">KREDIT</th>
                    <th style="{{ $sel }} text-align:right; width:14mm;">SALDO</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kelompokJenis as $kelompok)
                    @if ($kelompok->judul)
                        <tr style="background:#e8f5ee;">
                            <td colspan="7" style="{{ $sel }} font-weight:bold;">{{ $kelompok->judul }}</td>
                        </tr>
                    @endif
                    @foreach ($kelompok->rekap as $nomor => $rekap)
                        <tr>
                            <td style="{{ $sel }} text-align:center;">{{ $nomor + 1 }}</td>
                            <td style="{{ $sel }}">{{ $rekap->jenis }}</td>
                            <td style="{{ $sel }} text-align:right;">{{ $rekap->count }}</td>
                            <td style="{{ $sel }}"></td>
                            <td style="{{ $sel }} text-align:right;">{{ $rekap->debit > 0 ? $formatAngka($rekap->debit) : '—' }}</td>
                            <td style="{{ $sel }} text-align:right;">{{ $rekap->kredit > 0 ? $formatAngka($rekap->kredit) : '—' }}</td>
                            <td style="{{ $sel }}"></td>
                        </tr>
                    @endforeach
                    @if ($kelompok->subtotalLabel)
                        <tr style="background:#f3f4f6; font-weight:bold;">
                            <td colspan="2" style="{{ $sel }} text-align:right;">{{ $kelompok->subtotalLabel }}</td>
                            <td style="{{ $sel }} text-align:right;">{{ $kelompok->jumlahTrx }}</td>
                            <td style="{{ $sel }} text-align:right;">{{ $formatAngka($kelompok->saldoAwal) }}</td>
                            <td style="{{ $sel }} text-align:right;">{{ $formatAngka($kelompok->subtotalDebit) }}</td>
                            <td style="{{ $sel }} text-align:right;">{{ $formatAngka($kelompok->subtotalKredit) }}</td>
                            <td style="{{ $sel }} text-align:right;">{{ $formatAngka($kelompok->saldoAkhir) }}</td>
                        </tr>
                    @endif
                @endforeach

                <tr style="background:#ecfdf5; font-weight:bold;">
                    <td colspan="2" style="{{ $sel }}">TOTAL</td>
                    <td style="{{ $sel }} text-align:right;">{{ $jumlahTrx }}</td>
                    <td style="{{ $sel }} text-align:right;">{{ $formatAngka($saldoAwal) }}</td>
                    <td style="{{ $sel }} text-align:right;">{{ $formatAngka($totalDebit) }}</td>
                    <td style="{{ $sel }} text-align:right;">{{ $formatAngka($totalKredit) }}</td>
                    <td style="{{ $sel }} text-align:right;">{{ $formatAngka($saldoAkhir) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-pdf.layout-kwitansi>
