{{-- resources/views/pages/components/downtime/pelaporan-downtime/cetak-pelaporan-downtime-print.blade.php --}}
{{-- Cetakan DT-01 yang SUDAH TERISI. Versi kosongnya (untuk diisi tangan saat
     SIMRS mati) ada di pages/downtime/cetak/form/dt-01-log-kejadian.blade.php —
     dua berkas berbeda dengan tujuan berbeda, jangan disatukan.
     Import TIDAK diwarisi dari komponen pemanggil — @use wajib ada di sini. --}}
@use('App\Support\Options\PelaporanDowntimeOptions')

<x-pdf.layout-a4-with-out-background title="LOG KEJADIAN & PENANGANAN DOWN TIME SIMRS">

    @php
        $kejadian = $data['kejadian'] ?? [];
        $pelaporan = $data['pelaporan'] ?? [];
        $penanganan = $data['penanganan'] ?? [];
        $evaluasi = $data['evaluasi'] ?? [];
        $dampak = $data['dampak'] ?? [];

        // filled(), bukan ?: — "0" adalah jumlah transaksi yang sah.
        $isi = fn ($nilai) => filled($nilai) ? $nilai : '-';
    @endphp

    <p class="mb-2 text-center" style="font-size: 9px;">
        DT-01 &middot; MRMIK 13.1 Penanganan Down Time &middot; Laporan No. {{ $data['lembarNo'] ?? '-' }}
        
    </p>

    {{-- ── A ── --}}
    <p class="mt-3 mb-1 font-bold" style="font-size: 10px;">A. DATA KEJADIAN</p>
    <table class="w-full border border-black border-collapse" style="font-size: 9px;">
        <tbody>
            <tr>
                <td class="px-2 py-1 align-top border border-black" style="width: 18%;">Jenis Waktu Henti</td>
                <td class="px-2 py-1 align-top border border-black" style="width: 32%;">{{ PelaporanDowntimeOptions::labelJenis($kejadian['jenis'] ?? null) }}</td>
                <td class="px-2 py-1 align-top border border-black" style="width: 18%;">No. Log</td>
                <td class="px-2 py-1 align-top border border-black">{{ $isi($kejadian['noLog'] ?? null) }}</td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Mulai (tgl &amp; jam)</td>
                <td class="px-2 py-1 align-top border border-black">{{ $isi(($data['waktuMulai']['tanggal'] ?? '') . ' ' . ($data['waktuMulai']['jam'] ?? '')) }}</td>
                <td class="px-2 py-1 align-top border border-black">Pulih (tgl &amp; jam)</td>
                <td class="px-2 py-1 align-top border border-black">{{ $isi(trim(($data['waktuPulih']['tanggal'] ?? '') . ' ' . ($data['waktuPulih']['jam'] ?? ''))) }}</td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Durasi</td>
                <td class="px-2 py-1 align-top border border-black">{{ $isi($kejadian['durasi'] ?? null) }}</td>
                <td class="px-2 py-1 align-top border border-black">Lingkup Gangguan</td>
                <td class="px-2 py-1 align-top border border-black">{{ PelaporanDowntimeOptions::labelLingkup($kejadian['lingkup'] ?? null) }}</td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Modul / Layanan Terdampak</td>
                <td class="px-2 py-1 align-top border border-black" colspan="3">{{ $isi(PelaporanDowntimeOptions::modulTerdampakDari($dampak)) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ── B ── --}}
    <p class="mt-3 mb-1 font-bold" style="font-size: 10px;">B. PELAPORAN AWAL</p>
    <table class="w-full border border-black border-collapse" style="font-size: 9px;">
        <tbody>
            <tr>
                <td class="px-2 py-1 align-top border border-black" style="width: 18%;">Dilaporkan oleh</td>
                <td class="px-2 py-1 align-top border border-black" style="width: 32%;">{{ $isi($pelaporan['dilaporkanOleh'] ?? null) }}</td>
                <td class="px-2 py-1 align-top border border-black" style="width: 18%;">Jam laporan diterima</td>
                <td class="px-2 py-1 align-top border border-black">{{ $isi($pelaporan['jamLaporanDiterima'] ?? null) }}</td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Media laporan</td>
                <td class="px-2 py-1 align-top border border-black" colspan="3">{{ PelaporanDowntimeOptions::labelMediaLaporan($pelaporan['mediaLaporan'] ?? null) }}</td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Gejala / keluhan awal</td>
                <td class="px-2 py-1 align-top border border-black" colspan="3" style="white-space: pre-line;">{{ $isi($pelaporan['gejalaAwal'] ?? null) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ── C ── --}}
    <p class="mt-3 mb-1 font-bold" style="font-size: 10px;">C. IDENTIFIKASI &amp; PENANGANAN</p>
    <table class="w-full border border-black border-collapse" style="font-size: 9px;">
        <tbody>
            <tr>
                <td class="px-2 py-1 align-top border border-black" style="width: 18%;">Hasil identifikasi penyebab</td>
                <td class="px-2 py-1 align-top border border-black" colspan="3" style="white-space: pre-line;">{{ $isi($penanganan['penyebab'] ?? null) }}</td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Estimasi pemulihan</td>
                <td class="px-2 py-1 align-top border border-black" style="width: 32%;">{{ $isi($penanganan['estimasiPemulihan'] ?? null) }}</td>
                <td class="px-2 py-1 align-top border border-black" style="width: 18%;">Jam informasi disampaikan</td>
                <td class="px-2 py-1 align-top border border-black">{{ $isi($penanganan['jamInformasi'] ?? null) }}</td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Tindakan penanganan</td>
                <td class="px-2 py-1 align-top border border-black" colspan="3" style="white-space: pre-line;">{{ $isi($penanganan['tindakan'] ?? null) }}</td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Hasil penanganan</td>
                {{-- Diturunkan dari waktu pulih — tak diketik ulang oleh petugas. --}}
                <td class="px-2 py-1 align-top border border-black">{{ PelaporanDowntimeOptions::hasilPenangananDari($kejadian) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ── D ── --}}
    <p class="mt-3 mb-1 font-bold" style="font-size: 10px;">D. DAMPAK TERHADAP PELAYANAN</p>
    <table class="w-full border border-black border-collapse" style="font-size: 8px;">
        <thead>
            <tr>
                <th class="px-1 py-1 border border-black" style="width: 5%;">No</th>
                <th class="px-1 py-1 border border-black" style="width: 25%;">Unit Pelayanan</th>
                <th class="px-1 py-1 border border-black" style="width: 14%;">Beralih ke Manual</th>
                <th class="px-1 py-1 border border-black" style="width: 16%;">Jml Pasien / Transaksi</th>
                <th class="px-1 py-1 border border-black">Catatan Dampak</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dampak as $urut => $baris)
                <tr>
                    <td class="px-1 py-1 text-center align-top border border-black">{{ $urut + 1 }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ PelaporanDowntimeOptions::labelUnitDampak($baris['unit'] ?? null) }}</td>
                    <td class="px-1 py-1 text-center align-top border border-black">
                        {{-- Centang WAJIB dibungkus span DejaVu Sans, kalau tidak dompdf
                             mencetaknya sebagai tanda tanya. --}}
                        @if (!empty($baris['manual']))
                            <span style="font-family: 'DejaVu Sans', sans-serif;">&#10003;</span> Ya
                        @else
                            Tidak
                        @endif
                    </td>
                    <td class="px-1 py-1 text-center align-top border border-black">{{ $isi($baris['jumlah'] ?? null) }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $isi($baris['catatan'] ?? null) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── E ── --}}
    <p class="mt-3 mb-1 font-bold" style="font-size: 10px;">E. EVALUASI &amp; RENCANA TINDAK LANJUT</p>
    <table class="w-full border border-black border-collapse" style="font-size: 9px;">
        <tbody>
            <tr>
                <td class="px-2 py-1 align-top border border-black" style="width: 18%;">Analisis akar masalah</td>
                <td class="px-2 py-1 align-top border border-black" colspan="3" style="white-space: pre-line;">{{ $isi($evaluasi['akarMasalah'] ?? null) }}</td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Rencana tindak lanjut</td>
                <td class="px-2 py-1 align-top border border-black" colspan="3" style="white-space: pre-line;">{{ $isi($evaluasi['rencanaTindakLanjut'] ?? null) }}</td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Penanggung jawab</td>
                <td class="px-2 py-1 align-top border border-black" style="width: 32%;">{{ $isi($evaluasi['penanggungJawab'] ?? null) }}</td>
                <td class="px-2 py-1 align-top border border-black" style="width: 18%;">Target selesai</td>
                <td class="px-2 py-1 align-top border border-black">{{ $isi($evaluasi['targetSelesai'] ?? null) }}</td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Status pencadangan (backup) terakhir</td>
                <td class="px-2 py-1 align-top border border-black" colspan="3">{{ $isi($evaluasi['statusBackup'] ?? null) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ── TANDA TANGAN ──
         Blok TTD memakai <table> + tinggi tetap + &nbsp;, bukan flex/<br> —
         dompdf memperlakukan keduanya berbeda dari peramban. --}}
    {{-- ── TANDA TANGAN ──
         Sengaja KOSONG: diteken basah di kertas, tiga peran sesuai formulir DT-01.
         Blok TTD memakai <table> + tinggi tetap + &nbsp;, bukan flex/<br> —
         dompdf memperlakukan keduanya berbeda dari peramban. --}}
    <table class="w-full mt-6" style="font-size: 9px;">
        <tr>
            <td class="text-center" style="width: 33%;">Petugas IT Penanganan,<br>Pelaksana</td>
            <td class="text-center" style="width: 33%;">Ka. Unit IT / SIMRS,<br>Verifikator</td>
            <td class="text-center" style="width: 34%;">Mengetahui,<br>Manajemen RS</td>
        </tr>
        <tr>
            <td class="h-16 text-center align-bottom">&nbsp;</td>
            <td class="h-16 text-center align-bottom">&nbsp;</td>
            <td class="h-16 text-center align-bottom">&nbsp;</td>
        </tr>
        <tr>
            <td class="text-center">( ......................... )</td>
            <td class="text-center">( ......................... )</td>
            <td class="text-center">( ......................... )</td>
        </tr>
    </table>

    <p class="mt-3 italic" style="font-size: 8px;">
        Direkam ke sistem oleh: {{ $isi($data['paraf']['nama'] ?? null) }}
        @if (filled($data['paraf']['tanggal'] ?? null))
            &middot; {{ $data['paraf']['tanggal'] }}
        @endif
    </p>

    <p class="mt-4" style="font-size: 8px;">Dicetak: {{ $data['tglCetak'] ?? '-' }}</p>

</x-pdf.layout-a4-with-out-background>
