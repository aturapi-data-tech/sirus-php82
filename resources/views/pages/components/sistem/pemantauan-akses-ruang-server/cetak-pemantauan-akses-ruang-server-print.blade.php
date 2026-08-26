{{-- resources/views/pages/components/sistem/pemantauan-akses-ruang-server/cetak-pemantauan-akses-ruang-server-print.blade.php --}}
{{-- Formulir bulanan DIRAKIT di sini: kop dari RuangServerOptions (dipakai
     bersama modul suhu) + seluruh kunjungan bulan terpilih + garis tanda tangan
     kosong untuk diteken basah.
     Import TIDAK diwarisi dari komponen pemanggil — @use wajib ada di sini. --}}
@use('App\Support\Options\RuangServerOptions')

<x-pdf.layout-a4-with-out-background title="FORMULIR PEMANTAUAN AKSES RUANG SERVER">

    @php
        $catatanList = $data['catatanList'] ?? [];
        $rekap = $data['rekap'] ?? [];

        // filled(), bukan ?: — nilai "0" yang sah tak boleh jadi tanda hubung.
        $isi = fn ($nilai) => filled($nilai) ? $nilai : '-';
    @endphp

    <p class="mb-2 text-center" style="font-size: 9px;">
        MRMIK 2.2 &mdash; Perlindungan Data &middot; catatan keluar-masuk ruang server
    </p>

    {{-- ── BAGIAN A ── --}}
    <p class="mt-3 mb-1 font-bold" style="font-size: 10px;">A. DATA RUANG SERVER</p>

    <table class="w-full border border-black border-collapse" style="font-size: 9px;">
        <tbody>
            <tr>
                <td class="px-2 py-1 align-top border border-black" style="width: 22%;">Nama / Lokasi Ruang Server</td>
                <td class="px-2 py-1 align-top border border-black" style="width: 28%;">{{ RuangServerOptions::NAMA_RUANG }}</td>
                <td class="px-2 py-1 align-top border border-black" style="width: 22%;">Periode Pemantauan</td>
                <td class="px-2 py-1 align-top border border-black">{{ $data['periodeLabel'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Gedung / Lantai</td>
                <td class="px-2 py-1 align-top border border-black">{{ RuangServerOptions::GEDUNG_LANTAI }}</td>
                <td class="px-2 py-1 align-top border border-black">Penanggung Jawab Ruang</td>
                <td class="px-2 py-1 align-top border border-black">{{ RuangServerOptions::PENANGGUNG_JAWAB }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ── BAGIAN B ── --}}
    <p class="mt-4 mb-1 font-bold" style="font-size: 10px;">B. CATATAN KELUAR-MASUK RUANG SERVER</p>

    {{-- Lebar kolom dipatok persen supaya dompdf tak melebar tak karuan.
         Hindari whitespace-nowrap: dompdf mengabaikan lebar bila ada nowrap. --}}
    <table class="w-full border border-black border-collapse" style="font-size: 8px;">
        <thead>
            <tr>
                <th class="px-1 py-1 border border-black" style="width: 3%;">No</th>
                <th class="px-1 py-1 border border-black" style="width: 8%;">Tanggal</th>
                <th class="px-1 py-1 border border-black" style="width: 6%;">Masuk</th>
                <th class="px-1 py-1 border border-black" style="width: 6%;">Keluar</th>
                <th class="px-1 py-1 border border-black" style="width: 6%;">Lama</th>
                <th class="px-1 py-1 border border-black" style="width: 13%;">Nama</th>
                <th class="px-1 py-1 border border-black" style="width: 12%;">Unit / Instansi</th>
                <th class="px-1 py-1 border border-black" style="width: 10%;">Jenis</th>
                <th class="px-1 py-1 border border-black" style="width: 13%;">Keperluan</th>
                <th class="px-1 py-1 border border-black" style="width: 10%;">Didampingi</th>
                <th class="px-1 py-1 border border-black" style="width: 8%;">Perangkat</th>
                <th class="px-1 py-1 border border-black" style="width: 5%;">Paraf</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($catatanList as $urut => $catatan)
                <tr>
                    <td class="px-1 py-1 text-center align-top border border-black">{{ $urut + 1 }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $isi($catatan['tanggal']) }}</td>
                    <td class="px-1 py-1 text-center align-top border border-black">{{ $isi($catatan['jamMasuk']) }}</td>
                    {{-- Kosong dicetak apa adanya sebagai catatan bahwa kunjungan itu
                         tak pernah ditutup — bukan disembunyikan. --}}
                    <td class="px-1 py-1 text-center align-top border border-black">{{ $isi($catatan['jamKeluar']) }}</td>
                    <td class="px-1 py-1 text-center align-top border border-black">{{ $isi($catatan['lama']) }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $isi($catatan['nama']) }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $isi($catatan['unitInstansi']) }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $catatan['jenisLabel'] }}</td>
                    <td class="px-1 py-1 align-top border border-black">
                        {{ $catatan['keperluan'] }}
                        @if (filled($catatan['catatan']))
                            <div style="font-style: italic;">{{ $catatan['catatan'] }}</div>
                        @endif
                    </td>
                    <td class="px-1 py-1 align-top border border-black">{{ $isi($catatan['didampingi']) }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $isi($catatan['membawaPerangkat']) }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $isi($catatan['paraf']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="px-1 py-4 text-center border border-black">Belum ada kunjungan pada bulan ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="mt-1 italic" style="font-size: 8px;">
        Pengunjung dari luar Unit IT wajib didampingi petugas IT selama berada di ruang server.
    </p>

    {{-- ── REKAP ── --}}
    <p class="mt-3 mb-1 font-bold" style="font-size: 10px;">C. REKAPITULASI BULAN INI</p>
    <table class="w-full border border-black border-collapse" style="font-size: 9px;">
        <tbody>
            <tr>
                <td class="px-2 py-1 align-top border border-black" style="width: 25%;">Jumlah Kunjungan</td>
                <td class="px-2 py-1 align-top border border-black" style="width: 25%;">{{ $rekap['jumlah'] ?? 0 }}</td>
                <td class="px-2 py-1 align-top border border-black" style="width: 25%;">Pengunjung dari Luar</td>
                <td class="px-2 py-1 align-top border border-black">{{ $rekap['dariLuar'] ?? 0 }}</td>
            </tr>
            <tr>
                <td class="px-2 py-1 align-top border border-black">Belum Tercatat Keluar</td>
                <td class="px-2 py-1 align-top border border-black">{{ $rekap['belumKeluar'] ?? 0 }}</td>
                <td class="px-2 py-1 align-top border border-black">Tamu Luar Tanpa Pendamping</td>
                <td class="px-2 py-1 align-top border border-black">{{ $rekap['tanpaPendamping'] ?? 0 }}</td>
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
            <td class="text-center" style="width: 50%;">Petugas Pencatat,</td>
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
