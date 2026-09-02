{{-- resources/views/pages/components/sistem/pemantauan-suhu-ruang-server/cetak-pemantauan-suhu-ruang-server-print.blade.php --}}
{{-- Formulir bulanan DIRAKIT di sini: kop dari konstanta (nilainya tetap, tak
     disimpan per baris) + seluruh pengukuran bulan terpilih + garis tanda tangan
     kosong untuk diteken basah.
     Import TIDAK diwarisi dari komponen pemanggil — @use wajib ada di sini. --}}
@use('App\Support\Options\RuangServerOptions')
@use('App\Support\Options\SuhuRuangServerOptions')

<x-pdf.layout-a4-with-out-background title="FORMULIR PEMANTAUAN RUANG & SUHU SERVER">

    @php
        $catatanList = $data['catatanList'] ?? [];
        $rekap = $data['rekap'] ?? [];

        // filled(), bukan ?: — nilai "0" yang sah tak boleh jadi tanda hubung.
        $isi = fn ($nilai) => filled($nilai) ? $nilai : '-';

        $barisRuang = [
            'Nama / Lokasi Ruang Server' => RuangServerOptions::NAMA_RUANG,
            'Gedung / Lantai' => RuangServerOptions::GEDUNG_LANTAI,
            'Jumlah Perangkat Server / Rack' => RuangServerOptions::JUMLAH_PERANGKAT,
            'Kapasitas Pendingin (AC) - PK / BTU' => RuangServerOptions::KAPASITAS_PENDINGIN,
            'Standar Suhu Ruang Server' => SuhuRuangServerOptions::SUHU_MIN_DEFAULT . ' - '
                . SuhuRuangServerOptions::SUHU_MAX_DEFAULT . ' °C (rekomendasi ASHRAE / SNI)',
            'Alat Ukur yang Digunakan' => SuhuRuangServerOptions::ALAT_UKUR_DEFAULT,
        ];
    @endphp

    <p class="mb-2 text-center" style="font-size: 9px;">
        No. Dokumen 001/FORM/RSUI-MDN/01/2026 &middot; No. Revisi 00 &middot; Halaman 1/1
        &middot; Tanggal Terbit: 05 Januari 2026
    </p>

    {{-- ── BAGIAN A ── --}}
    <p class="mt-3 mb-1 font-bold" style="font-size: 10px;">A. DATA RUANG SERVER</p>

    <table class="w-full border border-black border-collapse" style="font-size: 9px;">
        <tbody>
            @foreach ($barisRuang as $label => $nilai)
                <tr>
                    <td class="px-2 py-1 align-top border border-black" style="width: 38%;">{{ $label }}</td>
                    <td class="px-2 py-1 align-top border border-black">{{ $isi($nilai) }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="px-2 py-1 align-top border border-black">Periode Pemantauan</td>
                <td class="px-2 py-1 align-top border border-black">{{ $data['periodeLabel'] ?? '-' }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ── BAGIAN B ── --}}
    <p class="mt-4 mb-1 font-bold" style="font-size: 10px;">B. PEMANTAUAN SUHU HARIAN</p>

    {{-- Lebar kolom dipatok persen supaya dompdf tak melebar tak karuan.
         Hindari whitespace-nowrap: dompdf mengabaikan lebar bila ada nowrap. --}}
    <table class="w-full border border-black border-collapse" style="font-size: 8px;">
        <thead>
            <tr>
                {{-- Jumlah lebar HARUS 100%: 4+10+7+8+9+14+9+25+14. --}}
                <th class="px-1 py-1 border border-black" style="width: 4%;">No</th>
                <th class="px-1 py-1 border border-black" style="width: 10%;">Tanggal</th>
                <th class="px-1 py-1 border border-black" style="width: 7%;">Jam</th>
                <th class="px-1 py-1 border border-black" style="width: 8%;">Suhu AC (°C)</th>
                <th class="px-1 py-1 border border-black" style="width: 9%;">Suhu Ruang (°C)</th>
                <th class="px-1 py-1 border border-black" style="width: 14%;">Status AC</th>
                <th class="px-1 py-1 border border-black" style="width: 9%;">Kondisi (N / TN)*</th>
                <th class="px-1 py-1 border border-black" style="width: 25%;">Tindak Lanjut</th>
                <th class="px-1 py-1 border border-black" style="width: 14%;">Paraf</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($catatanList as $urut => $catatan)
                <tr>
                    <td class="px-1 py-1 text-center align-top border border-black">{{ $urut + 1 }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $isi($catatan['tanggal']) }}</td>
                    <td class="px-1 py-1 text-center align-top border border-black">{{ $isi($catatan['jam']) }}</td>
                    <td class="px-1 py-1 text-center align-top border border-black">{{ $isi($catatan['suhuAc']) }}</td>
                    <td class="px-1 py-1 text-center align-top border border-black">{{ $isi($catatan['suhuRuang']) }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $catatan['statusAcLabel'] }}</td>
                    <td class="px-1 py-1 text-center align-top border border-black">{{ $isi($catatan['kondisi']) }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $isi($catatan['tindakLanjut']) }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $isi($catatan['paraf']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-1 py-4 text-center border border-black">Belum ada pengukuran pada bulan ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="mt-1 italic" style="font-size: 8px;">
        *) N = Normal (suhu RUANG {{ SuhuRuangServerOptions::SUHU_MIN_DEFAULT }}&ndash;{{ SuhuRuangServerOptions::SUHU_MAX_DEFAULT }} °C; suhu AC tidak ikut dinilai)
        &nbsp;|&nbsp; TN = Tidak Normal (di luar rentang, wajib tindak lanjut)
    </p>

    {{-- ── REKAP ── --}}
    <p class="mt-3 mb-1 font-bold" style="font-size: 10px;">C. REKAPITULASI BULAN INI</p>
    <table class="w-full border border-black border-collapse" style="font-size: 9px;">
        <tbody>
            <tr>
                <td class="px-2 py-1 align-top border border-black" style="width: 25%;">Jumlah Pengukuran</td>
                <td class="px-2 py-1 align-top border border-black" style="width: 25%;">{{ $rekap['jumlah'] ?? 0 }}</td>
                <td class="px-2 py-1 align-top border border-black" style="width: 25%;">Suhu Ruang Terendah / Tertinggi</td>
                <td class="px-2 py-1 align-top border border-black">
                    {{ ($rekap['suhuMin'] ?? null) === null ? '-' : $rekap['suhuMin'] . ' / ' . $rekap['suhuMax'] . ' °C' }}
                </td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Suhu Ruang Rata-rata</td>
                <td class="px-2 py-1 align-top border border-black">
                    {{ ($rekap['suhuRata'] ?? null) === null ? '-' : $rekap['suhuRata'] . ' °C' }}
                </td>
                <td class="px-2 py-1 align-top border border-black">Kondisi Tidak Normal</td>
                <td class="px-2 py-1 align-top border border-black">{{ $rekap['tidakNormal'] ?? 0 }} kali</td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Suhu AC Rata-rata</td>
                <td class="px-2 py-1 align-top border border-black" colspan="3">
                    {{ ($rekap['suhuAcRata'] ?? null) === null ? '-' : $rekap['suhuAcRata'] . ' °C' }}
                </td>
            </tr>
        </tbody>
    </table>

    {{-- ── TANDA TANGAN ──
         Sengaja KOSONG: diteken basah di kertas. Blok TTD memakai <table> +
         tinggi tetap + &nbsp;, bukan flex/<br> — dompdf memperlakukan keduanya
         berbeda dari peramban. --}}
    <table class="w-full mt-6" style="font-size: 9px;">
        <tr>
            <td class="text-center" style="width: 50%;">Mengetahui,<br>Kepala Unit IT / SIMRS</td>
            <td class="text-center" style="width: 50%;">Petugas Pemantau,</td>
        </tr>
        <tr>
            <td class="h-16 text-center align-bottom">&nbsp;</td>
            <td class="h-16 text-center align-bottom">&nbsp;</td>
        </tr>
        <tr>
            <td class="text-center">( ............................. )</td>
            <td class="text-center">( ............................. )</td>
        </tr>
    </table>

    <p class="mt-4" style="font-size: 8px;">Dicetak: {{ $data['tglCetak'] ?? '-' }}</p>

</x-pdf.layout-a4-with-out-background>
