@php
    // Butir & skor disalin dari opsi penilaian EMR RJ (rm-penilaian-rj-actions)
    // supaya skor manual identik dengan hasil hitung SIMRS saat dientri ulang.
    $morse = [
        'Riwayat jatuh' => ['Ya (25)', 'Tidak (0)'],
        'Diagnosis sekunder' => ['Ya (15)', 'Tidak (0)'],
        'Alat bantu' => ['Tidak ada / bed rest (0)', 'Tongkat / walker (15)', 'Furnitur (30)'],
        'Terapi IV / heparin lock' => ['Ya (20)', 'Tidak (0)'],
        'Gaya berjalan' => ['Normal / tirah baring (0)', 'Lemah (10)', 'Terganggu (20)'],
        'Status mental' => ['Baik (0)', 'Lupa / pelupa (15)'],
    ];

    $humpty = [
        'Umur' => ['< 3 th (4)', '3-7 th (3)', '7-13 th (2)', '13-18 th (1)'],
        'Jenis kelamin' => ['Laki-laki (2)', 'Perempuan (1)'],
        'Diagnosis' => ['Neurologis / perkembangan (4)', 'Ortopedi (3)', 'Lainnya (2)', 'Tidak ada khusus (1)'],
        'Gangguan kognitif' => ['Berat (3)', 'Sedang (2)', 'Ringan (1)', 'Tidak ada (0)'],
        'Faktor lingkungan' => ['Risiko tinggi (3)', 'Risiko sedang (2)', 'Risiko rendah (1)', 'Aman (0)'],
        'Respon terhadap obat' => ['Efek samping tinggikan risiko (3)', 'Efek samping ringan (2)', 'Tidak ada (1)'],
    ];

    $gizi = [
        'Perubahan berat badan' => ['Tidak ada perubahan (0)', 'Turun 5-10% (1)', 'Turun >10% (2)'],
        'Asupan makanan' => ['Cukup (0)', 'Kurang (1)', 'Sangat kurang (2)'],
        'Penyakit penyerta' => ['Tidak ada (0)', 'Ringan (1)', 'Berat (2)'],
    ];
@endphp

<x-downtime.halaman kode="RJ-EMR-03" judul="Penilaian Nyeri, Risiko Jatuh & Skrining Gizi Rawat Jalan"
    subjudul="Pengganti tab Penilaian pada EMR Rawat Jalan" unit="Perawat poli rawat jalan"
    entriUlang="Pelayanan Rawat Jalan > EMR > tab Penilaian" identitas="ringkas" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Penilaian Nyeri</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Tanggal / jam penilaian</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Status nyeri</td>
            <td class="dt-isi" style="width:30%;">
                <span class="dt-opsi"><span class="dt-box"></span>Tidak nyeri</span>
                <span class="dt-opsi"><span class="dt-box"></span>Nyeri</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Metode penilaian</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>NRS</span>
                <span class="dt-opsi"><span class="dt-box"></span>VAS</span>
                <span class="dt-opsi"><span class="dt-box"></span>FLACC</span>
                <span class="dt-opsi"><span class="dt-box"></span>BPS</span>
                <span class="dt-opsi"><span class="dt-box"></span>NIPS</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Skor nyeri (0&ndash;10)</td>
            <td class="dt-isi" colspan="3">
                @foreach (range(0, 10) as $skorNyeri)
                    <span class="dt-opsi"><span class="dt-box"></span>{{ $skorNyeri }}</span>
                @endforeach
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Lokasi</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Pencetus</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Intervensi non-farmakologi</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Intervensi farmakologi</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">B. Penilaian Risiko Jatuh &mdash; Skala Morse (dewasa)</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:26%;">Parameter</th>
            <th>Pilihan (skor) &mdash; Morse: 0-24 rendah, 25-44 sedang, &gt;=45 tinggi</th>
            <th style="width:10%;">Skor</th>
        </tr>
        @foreach ($morse as $parameter => $opsiMorse)
            <tr>
                <td>{{ $parameter }}</td>
                <td>
                    @foreach ($opsiMorse as $opsi)
                        <span class="dt-opsi"><span class="dt-box"></span>{{ $opsi }}</span>
                    @endforeach
                </td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
        <tr>
            <td class="dt-tebal" colspan="2">Total skor Morse</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>
    
    <div class="dt-sec">C. Penilaian Risiko Jatuh &mdash; Humpty Dumpty (anak)</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:26%;">Parameter</th>
            <th>Pilihan (skor)</th>
            <th style="width:10%;">Skor</th>
        </tr>
        @foreach ($humpty as $parameter => $opsiHumpty)
            <tr>
                <td>{{ $parameter }}</td>
                <td>
                    @foreach ($opsiHumpty as $opsi)
                        <span class="dt-opsi"><span class="dt-box"></span>{{ $opsi }}</span>
                    @endforeach
                </td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
        <tr>
            <td class="dt-tebal" colspan="2">Total skor Humpty Dumpty</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>

    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Kategori risiko jatuh</td>
            <td class="dt-isi" style="width:30%;">
                <span class="dt-opsi"><span class="dt-box"></span>Rendah</span>
                <span class="dt-opsi"><span class="dt-box"></span>Sedang</span>
                <span class="dt-opsi"><span class="dt-box"></span>Tinggi</span>
            </td>
            <td class="dt-tbl-label" style="width:20%;">Rekomendasi / intervensi</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">D. Skrining Gizi Awal</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:26%;">Parameter</th>
            <th>Pilihan (skor)</th>
            <th style="width:10%;">Skor</th>
        </tr>
        @foreach ($gizi as $parameter => $opsiGizi)
            <tr>
                <td>{{ $parameter }}</td>
                <td>
                    @foreach ($opsiGizi as $opsi)
                        <span class="dt-opsi"><span class="dt-box"></span>{{ $opsi }}</span>
                    @endforeach
                </td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
        <tr>
            <td class="dt-tebal" colspan="2">Total skor skrining gizi</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>
    <table class="dt-tbl">
        <tr>
            <th style="width:16%;">Berat Badan (kg)</th>
            <th style="width:16%;">Tinggi Badan (cm)</th>
            <th style="width:16%;">IMT</th>
            <th style="width:22%;">Kategori</th>
            <th>Kebutuhan gizi / rekomendasi (skor &gt;=2 = berisiko malnutrisi)</th>
        </tr>
        <tr>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">
                <span class="dt-opsi"><span class="dt-box"></span>Normal</span>
                <span class="dt-opsi"><span class="dt-box"></span>Berisiko malnutrisi</span>
            </td>
            <td class="dt-isi-2">&nbsp;</td>
        </tr>
    </table>
    
    <x-downtime.ttd :kolom="['Perawat Penilai' => 'Nama & paraf', 'Verifikasi Dokter / PJ Shift' => 'Nama & paraf']" />

</x-downtime.halaman>
