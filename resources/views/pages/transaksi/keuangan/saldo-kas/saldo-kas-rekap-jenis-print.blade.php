{{-- resources/views/pages/transaksi/keuangan/saldo-kas/saldo-kas-rekap-jenis-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="TOTAL KAS PER JENIS TRANSAKSI">

    @php
        // Data sudah disusun di method cetakPerJenis() — di sini hanya format angka.
        $formatAngka = fn($nilai) => number_format((float) $nilai, 0, '.', ',');
    @endphp

    {{-- ── INFO AKUN & PERIODE ── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:10px; font-size:11px;">
        <tr>
            <td style="width:75px; color:#555; padding:1px 0;">Akun Kas</td>
            <td style="width:8px;">:</td>
            <td style="font-weight:bold;">{{ $accId }} — {{ $accDesc }}</td>
            <td style="width:70px; color:#555;">Tampilan</td>
            <td style="width:8px;">:</td>
            <td style="font-weight:bold; width:120px;">{{ $modeLabel }}</td>
        </tr>
        <tr>
            <td style="color:#555; padding:1px 0;">Periode</td>
            <td>:</td>
            <td style="font-weight:bold;">{{ $periodeLabel }}</td>
            <td style="color:#555;">Dicetak</td>
            <td>:</td>
            <td>{{ $dicetakPada }}</td>
        </tr>
    </table>

    {{-- ── RINGKASAN ── --}}
    <table width="100%" cellpadding="5" cellspacing="0" style="margin-bottom:10px; font-size:11px; border-collapse:collapse; text-align:center;">
        <tr>
            <td style="border:1px solid #999; background:#f3f4f6; width:25%;">Saldo Awal per {{ $saldoAwalTgl }}<br><b>{{ $formatAngka($saldoAwal) }}</b></td>
            <td style="border:1px solid #999; background:#eff6ff; width:25%;">Debit<br><b>{{ $formatAngka($totalDebit) }}</b></td>
            <td style="border:1px solid #999; background:#fff1f2; width:25%;">Kredit<br><b>{{ $formatAngka($totalKredit) }}</b></td>
            <td style="border:1px solid #999; background:#ecfdf5; width:25%;">Saldo Akhir per {{ $sampaiLabel }}<br><b style="font-size:12px;">{{ $formatAngka($saldoAkhir) }}</b></td>
        </tr>
    </table>

    {{-- ── TABEL PER JENIS ──
         Kolom mengikuti alur kas: SALDO AWAL + DEBIT − KREDIT = SALDO. Baris jenis hanya mengisi
         DEBIT/KREDIT; saldo awal & saldo akhir hanya di baris subtotal shift dan TOTAL. --}}
    <table width="100%" cellpadding="4" cellspacing="0" style="font-size:10px; border-collapse:collapse;">
        <thead>
            <tr style="background:#f3f4f6;">
                <th style="border:1px solid #999; text-align:center; width:24px;">NO</th>
                <th style="border:1px solid #999; text-align:left;">JENIS TRANSAKSI</th>
                <th style="border:1px solid #999; text-align:right; width:44px;">JML TRX</th>
                <th style="border:1px solid #999; text-align:right; width:78px;">SALDO AWAL</th>
                <th style="border:1px solid #999; text-align:right; width:78px;">DEBIT</th>
                <th style="border:1px solid #999; text-align:right; width:78px;">KREDIT</th>
                <th style="border:1px solid #999; text-align:right; width:78px;">SALDO</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($kelompokJenis as $kelompok)
                @if ($kelompok->judul)
                    <tr style="background:#e8f5ee;">
                        <td colspan="7" style="border:1px solid #999; font-weight:bold;">{{ $kelompok->judul }}</td>
                    </tr>
                @endif
                @foreach ($kelompok->rekap as $nomor => $rekap)
                    <tr>
                        <td style="border:1px solid #999; text-align:center;">{{ $nomor + 1 }}</td>
                        <td style="border:1px solid #999;">{{ $rekap->jenis }}</td>
                        <td style="border:1px solid #999; text-align:right;">{{ $rekap->count }}</td>
                        <td style="border:1px solid #999;"></td>
                        <td style="border:1px solid #999; text-align:right;">{{ $rekap->debit > 0 ? $formatAngka($rekap->debit) : '—' }}</td>
                        <td style="border:1px solid #999; text-align:right;">{{ $rekap->kredit > 0 ? $formatAngka($rekap->kredit) : '—' }}</td>
                        <td style="border:1px solid #999;"></td>
                    </tr>
                @endforeach
                @if ($kelompok->subtotalLabel)
                    <tr style="background:#f3f4f6; font-weight:bold;">
                        <td colspan="2" style="border:1px solid #999; text-align:right;">{{ $kelompok->subtotalLabel }}</td>
                        <td style="border:1px solid #999; text-align:right;">{{ $kelompok->jumlahTrx }}</td>
                        <td style="border:1px solid #999; text-align:right;">{{ $formatAngka($kelompok->saldoAwal) }}</td>
                        <td style="border:1px solid #999; text-align:right;">{{ $formatAngka($kelompok->subtotalDebit) }}</td>
                        <td style="border:1px solid #999; text-align:right;">{{ $formatAngka($kelompok->subtotalKredit) }}</td>
                        <td style="border:1px solid #999; text-align:right;">{{ $formatAngka($kelompok->saldoAkhir) }}</td>
                    </tr>
                @endif
            @endforeach

            <tr style="background:#ecfdf5; font-weight:bold;">
                <td colspan="2" style="border:1px solid #999;">TOTAL</td>
                <td style="border:1px solid #999; text-align:right;">{{ $jumlahTrx }}</td>
                <td style="border:1px solid #999; text-align:right;">{{ $formatAngka($saldoAwal) }}</td>
                <td style="border:1px solid #999; text-align:right;">{{ $formatAngka($totalDebit) }}</td>
                <td style="border:1px solid #999; text-align:right;">{{ $formatAngka($totalKredit) }}</td>
                <td style="border:1px solid #999; text-align:right; font-size:11px;">{{ $formatAngka($saldoAkhir) }}</td>
            </tr>
        </tbody>
    </table>

</x-pdf.layout-a4-with-out-background>
