{{--
    Surat Pengantar Rujukan + Data Medis (Resume Klinis) Pasien Rujukan.

    Tata letak mengikuti format Kemkes/BPJS yang dibagikan 10/11/2026 (contoh
    "Surat Pengantar Rujuk Balik" + "Data Medis (Resume Klinis) Pasien Rujuk Balik"),
    diterapkan untuk rujukan keluar. Dasar isi: PMK 16/2024 Pasal 17 — identitas
    pasien, identitas fasyankes penerima, rekam medis, dan alasan rujukan.

    SENGAJA A4 POLOS tanpa kop/logo RS: format ini universal (sama untuk semua
    fasyankes), jadi tidak memakai x-pdf.layout-a4 yang selalu memasang kop.
    Identitas RS perujuk cukup tertulis di isian "Asal Fasyankes Perujuk".

    Berlaku untuk SEMUA jalur rujukan (RJ/UGD/RI, SISRUTE maupun FHIR) — seluruh
    isi datang dari $data yang sudah dinormalkan komponen pemanggil.

    Catatan dompdf: tanpa CSS Tailwind (kelas arbitrary tak ter-render), dua kolom
    memakai <table>, garis kotak ditulis per sel (atribut border diabaikan), blok
    tanda tangan bertinggi tetap. Huruf DejaVu Serif agar °, — ikut tercetak.
--}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>SURAT PENGANTAR RUJUKAN</title>
    <style>
        @page {
            size: A4;
            margin: 1.6cm 1.8cm 1.6cm 1.8cm;
        }

        body {
            margin: 0;
            font-family: 'DejaVu Serif', serif;
            font-size: 11px;
            line-height: 1.35;
            color: #000;
        }

        .judul {
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 16px;
        }

        table {
            border-collapse: collapse;
        }

        td {
            vertical-align: top;
        }

        /* Daftar isian "Label : nilai" halaman 1 */
        .isian {
            width: 100%;
            margin-bottom: 12px;
        }

        .isian td {
            padding: 1px 0;
        }

        .isian .label {
            width: 200px;
        }

        .isian .titik {
            width: 12px;
        }

        .paragraf {
            text-align: justify;
            margin-bottom: 14px;
        }

        /* Kotak resume klinis halaman 2 */
        .resume {
            width: 100%;
            border: 1px solid #000;
        }

        .resume td {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            padding: 2px 5px;
        }

        .resume .no {
            width: 32px;
        }

        .resume .label {
            width: 230px;
        }

        .resume .titik {
            width: 10px;
            text-align: center;
        }

        /* Garis atas penanda awal bagian (I, II, ...); garis bawah di baris terakhir. */
        .resume tr.awal td {
            border-top: 1px solid #000;
        }

        .resume tr.akhir td {
            border-bottom: 1px solid #000;
        }

        .ttd td {
            text-align: center;
            padding: 1px 0;
        }
    </style>
</head>

<body>
    @php
        $isi = fn($nilai) => filled($nilai) ? $nilai : '-';
        $tempatTanggal = ($data['perujuk']['kota'] ? $data['perujuk']['kota'] . ', ' : '') . 'Tanggal ' . ($data['tanggal'] ?: '');
    @endphp

    {{-- ══════════════════ HALAMAN 1 — SURAT PENGANTAR RUJUKAN ══════════════════ --}}
    <div class="judul">SURAT PENGANTAR RUJUKAN</div>

    <table class="isian">
        <tr>
            <td class="label">Nomor Rujukan (SSR)</td>
            <td class="titik">:</td>
            <td style="font-weight:bold;">{{ $isi($data['noRujukan']) }}</td>
        </tr>
        <tr>
            <td class="label">Nomor Rujukan BPJS</td>
            <td class="titik">:</td>
            <td style="font-weight:bold;">{{ $isi($data['noRujukanBpjs']) }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Rujukan</td>
            <td class="titik">:</td>
            <td>{{ $isi($data['tanggal']) }}</td>
        </tr>
    </table>

    <table class="isian">
        <tr>
            <td class="label">Asal Fasyankes Perujuk</td>
            <td class="titik">:</td>
            <td>{{ $isi($data['perujuk']['nama']) }}</td>
        </tr>
        <tr>
            <td class="label">Kode Fasyankes</td>
            <td class="titik">:</td>
            <td>{{ $isi($data['perujuk']['kode']) }}</td>
        </tr>
        <tr>
            <td class="label">Alamat</td>
            <td class="titik">:</td>
            <td>{{ $isi($data['perujuk']['alamat']) }}</td>
        </tr>
    </table>

    <table class="isian" style="margin-bottom:20px;">
        <tr>
            <td class="label">Fasyankes Penerima Rujukan</td>
            <td class="titik">:</td>
            <td style="font-weight:bold;">{{ $isi($data['tujuan']['nama']) }}</td>
        </tr>
        <tr>
            <td class="label">Kode Fasyankes</td>
            <td class="titik">:</td>
            <td>{{ $isi($data['tujuan']['kode']) }}</td>
        </tr>
        <tr>
            <td class="label">Alamat</td>
            <td class="titik">:</td>
            <td>{{ $isi($data['tujuan']['alamat']) }}</td>
        </tr>
    </table>

    <div>Dengan hormat,</div>
    <div style="margin-bottom:4px;">Mohon untuk dilakukan pemeriksaan dan penanganan lebih lanjut pada pasien.</div>

    <table class="isian" style="margin-bottom:16px;">
        <tr>
            <td class="label">Nama Pasien</td>
            <td class="titik">:</td>
            <td style="font-weight:bold;">{{ $isi($data['pasien']['nama']) }}</td>
        </tr>
        <tr>
            <td class="label">No. Medical Record (MR)</td>
            <td class="titik">:</td>
            <td>{{ $isi($data['pasien']['noRm']) }}</td>
        </tr>
        <tr>
            <td class="label">NIK</td>
            <td class="titik">:</td>
            <td>{{ $isi($data['pasien']['nik']) }}</td>
        </tr>
        <tr>
            <td class="label">Nomor Jaminan</td>
            <td class="titik">:</td>
            <td>
                {{ $isi($data['pasien']['nomorJaminan']) }}
                @if (filled($data['pasien']['jenisJaminan']))
                    ({{ $data['pasien']['jenisJaminan'] }})
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Umur</td>
            <td class="titik">:</td>
            <td>{{ $isi($data['pasien']['umur']) }}</td>
        </tr>
        <tr>
            <td class="label">Alamat Rumah</td>
            <td class="titik">:</td>
            <td>{{ $isi($data['pasien']['alamat']) }}</td>
        </tr>
        <tr>
            <td class="label">Jenis Kelamin</td>
            <td class="titik">:</td>
            <td>{{ $isi($data['pasien']['jenisKelamin']) }}</td>
        </tr>
        <tr>
            <td class="label">Diagnosis Rujukan</td>
            <td class="titik">:</td>
            <td>{{ $isi($data['diagnosaSementara']) }}</td>
        </tr>
        <tr>
            <td class="label">Terapi yang telah diberikan</td>
            <td class="titik">:</td>
            <td style="white-space:pre-line;">{{ $isi($data['terapiDiberikan']) }}</td>
        </tr>
    </table>

    <div class="paragraf">
        Rujukan telah dijelaskan kepada pasien dan atau keluarga pasien, dan telah mendapatkan persetujuan
        baik lisan maupun tertulis dari pasien dan atau keluarga pasien untuk dirujuk guna mendapatkan
        pemeriksaan dan atau pengobatan lanjutan sesuai kebutuhan pasien. Demikian kami sampaikan, atas
        perhatian saudara, kami ucapkan terima kasih.
    </div>

    {{-- Tanda tangan: tinggi tetap, tanpa flex/br (jebakan dompdf) --}}
    <table class="ttd" style="width:100%; margin-top:10px;">
        <tr>
            <td style="width:50%;">&nbsp;</td>
            <td style="width:50%;">{{ $tempatTanggal }}</td>
        </tr>
        <tr>
            <td>Nakes Fasyankes Penerima Rujukan</td>
            <td>Dokter Penanggung Jawab Pasien</td>
        </tr>
        <tr>
            <td>(Penerima Rujukan)</td>
            <td>(Perujuk)</td>
        </tr>
        <tr>
            <td style="height:70px;">&nbsp;</td>
            <td style="height:70px;">&nbsp;</td>
        </tr>
        <tr>
            <td>..................................................</td>
            <td>
                @if (filled($data['dpjp']))
                    <span style="text-decoration:underline;">{{ $data['dpjp'] }}</span>
                @else
                    ..................................................
                @endif
            </td>
        </tr>
    </table>

    <div style="margin-top:26px;">
        Nb: Data pasien rujukan telah disampaikan ke
        <strong>{{ $data['respon']['faskes'] ?: '..............................' }}</strong>
        melalui sistem informasi rujukan terintegrasi
        @if (filled($data['respon']['noRujukan']))
            dengan No. Rujukan <strong>{{ $data['respon']['noRujukan'] }}</strong>
        @endif
        dan telah mendapat persetujuan dari Dokter Penanggung Jawab Pasien (DPJP).
    </div>

    {{-- ══════════════════ HALAMAN 2 — DATA MEDIS (RESUME KLINIS) ══════════════════ --}}
    @php
        $resume = $data['resume'];
        $ttv = $resume['ttv'];
        // Spasi pemisah memakai NBSP — spasi biasa beruntun diciutkan jadi satu oleh HTML.
        $jeda = str_repeat("\u{00A0}", 3);
        $teksTtv =
            'TD: ' . $isi($ttv['tensi']) .
            $jeda . 'Nadi: ' . $isi($ttv['nadi']) .
            $jeda . 'Suhu: ' . $isi($ttv['suhu']) .
            $jeda . 'Frek. Nafas: ' . $isi($ttv['nafas']) .
            (filled($ttv['spo2']) ? $jeda . 'SpO2: ' . $ttv['spo2'] : '');

        // Daftar berhuruf a., b., c. — kosong dicetak '-'.
        $hurufList = fn(array $baris) => $baris === []
            ? '-'
            : collect($baris)->values()->map(fn($b, $i) => chr(97 + $i) . '. ' . $b)->implode("\n");
        $angkaList = fn(array $baris) => $baris === []
            ? '-'
            : collect($baris)->values()->map(fn($b, $i) => ($i + 1) . '. ' . $b)->implode("\n");

        // Penunjang = teks bebas EMR, dipecah per baris lalu diberi huruf.
        $penunjang = collect(preg_split('/\r\n|\r|\n/', (string) $resume['penunjang']))
            ->map(fn($b) => trim($b))->filter()->values()->all();

        $identitas = [
            'a. Nama Pasien' => $data['pasien']['nama'],
            'b. Umur' => $data['pasien']['umur'],
            'c. Jenis Kelamin' => $data['pasien']['jenisKelamin'],
            'd. Alamat' => $data['pasien']['alamat'],
            'e. No. BPJS' => $data['pasien']['noBpjs'],
        ];
        $fisik = [
            'a. Keadaan Umum' => $resume['keadaanUmum'],
            'b. Tingkat Kesadaran Kualitatif dan Kuantitatif (GCS)' => $resume['gcs'],
            'c. Tanda-Tanda Vital' => $teksTtv,
            'd. Kondisi Kelainan Bermasalah' => $resume['kelainan'],
            'e. Alergi (Ada/Tidak) Sebutkan' => $resume['alergi'],
        ];

        // Bagian berjudul + isi di bawahnya (II, VI–IX): [no, judul, isi]
        $bagianBawah = [
            ['VI', 'Prosedur diagnostik/pemeriksaan penunjang yang sudah dilakukan dan hasilnya', $hurufList($penunjang)],
            ['VII', 'Terapi Yang Telah Diberikan', $hurufList($resume['terapi'])],
            ['VIII', 'Tindakan Medis Yang Telah Dilakukan', $hurufList($resume['tindakan'])],
            ['IX', 'Alasan Merujuk & Rencana Tindak Lanjut', $isi($resume['alasan'])],
        ];
    @endphp

    <div style="page-break-before:always;"></div>
    <div class="judul">DATA MEDIS (RESUME KLINIS) PASIEN RUJUKAN</div>

    <table class="resume">
        {{-- I. Identitas pasien --}}
        <tr class="awal">
            <td class="no" rowspan="{{ count($identitas) + 1 }}">I</td>
            <td colspan="3"><strong>IDENTITAS PASIEN</strong></td>
        </tr>
        @foreach ($identitas as $label => $nilai)
            <tr>
                <td class="label">{{ $label }}</td>
                <td class="titik">:</td>
                <td>{{ $isi($nilai) }}</td>
            </tr>
        @endforeach

        {{-- II. Keluhan --}}
        <tr class="awal">
            <td class="no" rowspan="2">II</td>
            <td colspan="3"><strong>Keluhan Pasien :</strong></td>
        </tr>
        <tr>
            <td colspan="3" style="white-space:pre-line; padding-bottom:6px;">{{ $isi($resume['keluhanUtama']) }}</td>
        </tr>

        {{-- III. Pemeriksaan fisik --}}
        <tr class="awal">
            <td class="no" rowspan="{{ count($fisik) + 1 }}">III</td>
            <td colspan="3"><strong>Pemeriksaan Fisik</strong></td>
        </tr>
        @foreach ($fisik as $label => $nilai)
            <tr>
                <td class="label">{{ $label }}</td>
                <td class="titik">:</td>
                <td style="white-space:pre-line;">{{ $isi($nilai) }}</td>
            </tr>
        @endforeach

        {{-- IV. Diagnosa --}}
        <tr class="awal">
            <td class="no">IV</td>
            <td class="label"><strong>Diagnosa Saat Rujukan</strong></td>
            <td class="titik">:</td>
            <td style="white-space:pre-line;">{{ $angkaList($resume['diagnosa']) }}</td>
        </tr>

        {{-- V. Kriteria rujukan --}}
        <tr class="awal">
            <td class="no">V</td>
            <td class="label"><strong>Kriteria Rujukan</strong></td>
            <td class="titik">:</td>
            <td style="white-space:pre-line;">{{ $angkaList($resume['kriteria']) }}</td>
        </tr>

        {{-- VI–IX. Judul + isi di bawahnya --}}
        @foreach ($bagianBawah as [$no, $judul, $teks])
            <tr class="awal">
                <td class="no" rowspan="2">{{ $no }}</td>
                <td colspan="3"><strong>{{ $judul }}</strong></td>
            </tr>
            <tr class="{{ $loop->last ? 'akhir' : '' }}">
                <td colspan="3" style="white-space:pre-line; padding-bottom:6px;">{{ $teks }}</td>
            </tr>
        @endforeach
    </table>

    <table class="ttd" style="width:100%; margin-top:16px;">
        <tr>
            <td style="width:45%;">&nbsp;</td>
            <td style="width:55%;">{{ $tempatTanggal }}</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>Dokter Penanggung Jawab Pasien (Perujuk)</td>
        </tr>
        <tr>
            <td style="height:70px;">&nbsp;</td>
            <td style="height:70px;">&nbsp;</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>
                @if (filled($data['dpjp']))
                    <span style="text-decoration:underline;">{{ $data['dpjp'] }}</span>
                @else
                    ..................................................
                @endif
            </td>
        </tr>
    </table>
</body>

</html>
