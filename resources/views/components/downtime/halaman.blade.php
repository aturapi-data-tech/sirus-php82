{{-- Satu halaman formulir manual down time: kop RS + kode form + judul + pita
     peringatan + isi (slot) + blok entri ulang + footer.

     Kop halaman pertama dicetak oleh layout standar
     (x-pdf.layout-a4-with-out-background); formulir kedua dan seterusnya dalam
     satu bundel mencetak kop sendiri memakai komponen kop standar
     x-logo.identitas. --}}
@props([
    'kode' => '',
    'judul' => '',
    'subjudul' => null,
    'unit' => null,
    'entriUlang' => null,
    'break' => false,
    // Varian blok identitas pasien di kop: 'lengkap' / 'ringkas' / null (tanpa).
    'identitas' => null,
    // Padding sel dipangkas (default) supaya satu formulir tetap muat 1 lembar A4.
    // Set false bila formulir pendek dan ingin baris lebih lega.
    'padat' => true,
])

<div class="dt-halaman {{ $break ? 'dt-break' : '' }} {{ $padat ? 'dt-padat' : '' }}">

    {{-- KOP — hanya formulir ke-2 dst.; formulir pertama memakai kop layout
         (blok identitas pasiennya dititipkan lewat slot patientData layout).
         Identitas pasien di kiri, sejajar logo — pola sama dengan layout. --}}
    @if ($break)
        @if (filled($identitas))
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="width:50%; vertical-align:bottom;">
                        <x-downtime.identitas :variant="$identitas" />
                    </td>
                    <td style="width:50%; vertical-align:bottom; padding-left:8px;">
                        <x-logo.identitas :showGaris="false" />
                    </td>
                </tr>
            </table>
            <hr style="border: none; border-top: 2px solid #000; margin: 8px 0;">
        @else
            <x-logo.identitas :showGaris="true" />
        @endif
    @endif

    {{-- KOTAK KODE + JUDUL (sebaris agar hemat tinggi halaman) --}}
    <table style="width:100%; border-collapse:collapse; margin-top:8px;">
        <tr>
            <td style="width:190px; vertical-align:top;">
                <x-downtime.kotak-kode :kode="$kode" />
            </td>
            <td style="vertical-align:middle; padding-left:10px;">
                <div class="dt-judul">{{ $judul }}</div>
                @if (filled($subjudul))
                    <div class="dt-subjudul">{{ $subjudul }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- PITA PERINGATAN --}}
    <div class="dt-pita">
        Formulir manual waktu henti (down time) SIMRS &mdash; diisi tulis tangan, wajib dientri ulang ke SIMRS setelah sistem pulih
    </div>

    {{-- ISI FORMULIR --}}
    {{ $slot }}

    {{-- BLOK ENTRI ULANG — seragam di semua formulir --}}
    <div class="dt-entri">
        <span class="dt-entri-judul">Diisi setelah sistem pulih (rekonsiliasi data)</span>
        <table class="dt-plain">
            <tr>
                <td style="width:16%;">Down time mulai</td>
                <td style="width:22%;" class="dt-garis">&nbsp;</td>
                <td style="width:12%;">&nbsp;s.d.</td>
                <td style="width:22%;" class="dt-garis">&nbsp;</td>
                <td style="width:12%;">Durasi</td>
                <td style="width:16%;" class="dt-garis">&nbsp;</td>
            </tr>
            <tr>
                <td>Dientri ulang oleh</td>
                <td class="dt-garis">&nbsp;</td>
                <td>&nbsp;Tanggal / jam</td>
                <td class="dt-garis">&nbsp;</td>
                <td>Paraf</td>
                <td class="dt-garis">&nbsp;</td>
            </tr>
        </table>
        @if (filled($entriUlang))
            <span class="dt-kecil" style="font-style:italic;">Entri ulang di: {{ $entriUlang }}</span>
        @endif
    </div>

    {{-- FOOTER --}}
    <table class="dt-footer">
        <tr>
            <td style="width:62%;">
                {{ $kode }} &mdash; {{ $judul }}@if (filled($unit)) &middot; {{ $unit }} @endif
            </td>
            <td style="width:38%;" class="dt-kanan">Formulir Manual Down Time SIMRS</td>
        </tr>
    </table>

</div>
