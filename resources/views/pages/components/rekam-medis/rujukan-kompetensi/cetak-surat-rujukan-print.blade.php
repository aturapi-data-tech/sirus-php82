{{--
    Surat Pengantar Rujukan + Resume Klinis Pasien Rujukan.

    Mengikuti "Lampiran 1: Format Surat Pengantar Rujukan Manual" yang dibagikan
    Kemkes/BPJS di grup SATUSEHAT Rujukan 27/08/2026 dan akan dituangkan dalam
    Kepmenkes. Dasar isi: PMK 16/2024 Pasal 17 — surat rujukan elektronik paling
    sedikit memuat identitas pasien, identitas fasyankes & unit layanan penerima,
    rekam medis, dan alasan rujukan; serta dapat dicetak sesuai kebutuhan pasien.

    Berlaku untuk SEMUA jalur rujukan (RJ/UGD/RI, SISRUTE maupun FHIR) — karena itu
    blade ini jalur-agnostik: seluruh isinya datang dari $data yang sudah dinormalkan
    oleh komponen pemanggil.

    Catatan dompdf: lebar & ukuran huruf dipakai lewat inline style (kelas arbitrary
    Tailwind tidak ter-render), tata letak dua kolom memakai <table>, dan blok tanda
    tangan memakai tinggi tetap + &nbsp; (bukan flex/br).
--}}
<x-pdf.layout-a4 title="SURAT PENGANTAR RUJUKAN">

    {{-- ══════════════════ HALAMAN 1 — SURAT PENGANTAR ══════════════════ --}}
    @php
        // dompdf mengabaikan atribut border="1" pada <table>; garis kotak harus
        // ditulis sebagai style per sel.
        $sel = 'border:1px solid #000; padding:4px; vertical-align:top;';
    @endphp

    <div style="padding:0 28px;">

        {{-- Nomor & tanggal --}}
        <table cellpadding="0" cellspacing="0" style="width:100%; font-size:11px; margin-bottom:10px;">
            <tr>
                <td style="width:150px; padding:1px 0;">NOMOR RUJUKAN</td>
                <td style="width:10px;">:</td>
                <td style="font-weight:bold;">{{ $data['noRujukan'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0;">NOMOR RUJUKAN BPJS</td>
                <td>:</td>
                <td style="font-weight:bold;">{{ $data['noRujukanBpjs'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0;">Tanggal</td>
                <td>:</td>
                <td>{{ $data['tanggal'] ?: '-' }}</td>
            </tr>
        </table>

        <div style="border-top:1px solid #000; margin-bottom:8px;"></div>

        {{-- Fasyankes perujuk --}}
        <table cellpadding="0" cellspacing="0" style="width:100%; font-size:11px; margin-bottom:8px;">
            <tr>
                <td style="width:150px; padding:1px 0;">Fasyankes Perujuk</td>
                <td style="width:10px;">:</td>
                <td>{{ $data['perujuk']['nama'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0;">Kode Register</td>
                <td>:</td>
                <td>{{ $data['perujuk']['kode'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0; vertical-align:top;">Alamat</td>
                <td style="vertical-align:top;">:</td>
                <td>{{ $data['perujuk']['alamat'] ?: '-' }}</td>
            </tr>
        </table>

        <div style="border-top:1px solid #000; margin-bottom:8px;"></div>

        {{-- Fasyankes tujuan --}}
        <table cellpadding="0" cellspacing="0" style="width:100%; font-size:11px; margin-bottom:14px;">
            <tr>
                <td style="width:150px; padding:1px 0;">Kepada Yth.</td>
                <td style="width:10px;">:</td>
                <td></td>
            </tr>
            <tr>
                <td style="padding:1px 0;">Fasyankes</td>
                <td>:</td>
                <td style="font-weight:bold;">{{ $data['tujuan']['nama'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0;">Kode Register</td>
                <td>:</td>
                <td>{{ $data['tujuan']['kode'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0; vertical-align:top;">Alamat</td>
                <td style="vertical-align:top;">:</td>
                <td>{{ $data['tujuan']['alamat'] ?: '-' }}</td>
            </tr>
        </table>

        <div style="font-size:11px; margin-bottom:2px;">Dengan hormat,</div>
        <div style="font-size:11px; margin-bottom:8px;">Mohon untuk dilakukan pada pasien:</div>

        {{-- Identitas pasien --}}
        <table cellpadding="0" cellspacing="0"
            style="width:100%; font-size:11px; border-collapse:collapse; margin-bottom:16px;">
            <tr>
                <td style="{{ $sel }} width:22%;">Nama</td>
                <td style="{{ $sel }} width:28%;">{{ $data['pasien']['nama'] ?: '-' }}</td>
                <td style="{{ $sel }} width:25%;">&nbsp;</td>
                <td style="{{ $sel }} width:25%;">&nbsp;</td>
            </tr>
            <tr>
                <td style="{{ $sel }}">NIK</td>
                <td style="{{ $sel }}">{{ $data['pasien']['nik'] ?: '-' }}</td>
                <td style="{{ $sel }}">&nbsp;</td>
                <td style="{{ $sel }}">&nbsp;</td>
            </tr>
            <tr>
                <td style="{{ $sel }}">Umur</td>
                <td style="{{ $sel }}">{{ $data['pasien']['umur'] ?: '-' }}</td>
                <td style="{{ $sel }}">Jenis Kelamin</td>
                <td style="{{ $sel }}">{{ $data['pasien']['jenisKelamin'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $sel }}">Alamat</td>
                <td style="{{ $sel }}" colspan="3">{{ $data['pasien']['alamat'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $sel }}">Jenis Jaminan</td>
                <td style="{{ $sel }}">{{ $data['pasien']['jenisJaminan'] ?: '-' }}</td>
                <td style="{{ $sel }}">Nomor Jaminan</td>
                <td style="{{ $sel }}">{{ $data['pasien']['nomorJaminan'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $sel }}">Diagnosa Sementara</td>
                <td style="{{ $sel }}" colspan="3">{{ $data['diagnosaSementara'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $sel }}">Terapi yang telah diberikan</td>
                <td style="{{ $sel }} white-space:pre-line;" colspan="3">{{ $data['terapiDiberikan'] ?: '-' }}</td>
            </tr>
        </table>

        <div style="font-size:11px; margin-bottom:2px;">
            Rujukan telah mendapatkan persetujuan baik lisan maupun tertulis dari Pasien dan atau keluarga Pasien
            setuju untuk dirujuk.
        </div>
        <div style="font-size:11px; margin-bottom:18px;">
            Demikian kami sampaikan, atas perhatian saudara, kami ucapkan terima kasih.
        </div>

        {{-- Tanda tangan: tinggi tetap + &nbsp;, hindari flex/br (jebakan dompdf) --}}
        <table cellpadding="0" cellspacing="0" style="width:100%; font-size:11px;">
            <tr>
                <td style="width:50%; text-align:center;">&nbsp;</td>
                <td style="width:50%; text-align:center;">
                    {{ $data['perujuk']['kota'] ? $data['perujuk']['kota'] . ', ' : '' }}{{ $data['tanggal'] ?: '' }}
                </td>
            </tr>
            <tr>
                <td style="text-align:center;">Tenaga Kesehatan Penerima Rujuk</td>
                <td style="text-align:center;">Dokter Penanggungjawab Pasien</td>
            </tr>
            <tr>
                <td style="height:64px; text-align:center;">&nbsp;</td>
                <td style="height:64px; text-align:center;">&nbsp;</td>
            </tr>
            <tr>
                <td style="text-align:center;">
                    <span style="border-top:1px solid #000; padding:0 40px;">&nbsp;</span>
                </td>
                <td style="text-align:center;">
                    <span style="border-top:1px solid #000; padding:0 40px;">{{ $data['dpjp'] ?: '&nbsp;' }}</span>
                </td>
            </tr>
        </table>

        <div style="font-size:10px; margin-top:20px;">
            Nb.: Rujukan telah direspon oleh petugas dari
            <span style="border-bottom:1px dotted #000;">&nbsp;{{ $data['respon']['faskes'] ?: '' }}&nbsp;</span>
            dengan No Rujukan
            <span style="border-bottom:1px dotted #000;">&nbsp;{{ $data['respon']['noRujukan'] ?: '' }}&nbsp;</span>
        </div>
    </div>

    {{-- ══════════════════ HALAMAN 2 — RESUME KLINIS ══════════════════ --}}
    <div style="page-break-before:always; padding:0 28px;">

        <div style="text-align:center; font-size:13px; font-weight:bold; margin-bottom:12px;">
            RESUME KLINIS PASIEN RUJUKAN
        </div>

        @php
            $resume = $data['resume'];
            $selNo = $sel . ' width:24px; text-align:center;';
            $selJudul = $sel . ' width:210px;';
            $selIsi = $sel;
        @endphp

        <table cellpadding="0" cellspacing="0"
            style="width:100%; font-size:11px; border-collapse:collapse;">

            {{-- I. Identitas pasien --}}
            <tr>
                <td style="{{ $selNo }}">I</td>
                <td style="{{ $selJudul }}"><strong>IDENTITAS PASIEN</strong></td>
                <td style="{{ $selIsi }}">&nbsp;</td>
            </tr>
            @foreach ([
        'a. Nama Pasien' => $data['pasien']['nama'],
        'b. Umur' => $data['pasien']['umur'],
        'c. Jenis Kelamin' => $data['pasien']['jenisKelamin'],
        'd. Alamat' => $data['pasien']['alamat'],
        'e. No. BPJS' => $data['pasien']['noBpjs'],
    ] as $label => $nilai)
                <tr>
                    <td style="{{ $selNo }}">&nbsp;</td>
                    <td style="{{ $selJudul }}">{{ $label }}</td>
                    <td style="{{ $selIsi }}">{{ $nilai ?: '-' }}</td>
                </tr>
            @endforeach

            {{-- II. Keluhan utama --}}
            <tr>
                <td style="{{ $selNo }}">II</td>
                <td style="{{ $selJudul }}"><strong>Keluhan Utama Pasien</strong></td>
                <td style="{{ $selIsi }}">{{ $resume['keluhanUtama'] ?: '-' }}</td>
            </tr>

            {{-- III. Pemeriksaan fisik --}}
            <tr>
                <td style="{{ $selNo }}">III</td>
                <td style="{{ $selJudul }}"><strong>Pemeriksaan Fisik</strong></td>
                <td style="{{ $selIsi }}">&nbsp;</td>
            </tr>
            <tr>
                <td style="{{ $selNo }}">&nbsp;</td>
                <td style="{{ $selJudul }}">a. Keadaan Umum</td>
                <td style="{{ $selIsi }}">{{ $resume['keadaanUmum'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $selNo }}">&nbsp;</td>
                <td style="{{ $selJudul }}">b. GCS</td>
                <td style="{{ $selIsi }}">{{ $resume['gcs'] ?: '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $selNo }}">&nbsp;</td>
                <td style="{{ $selJudul }}">c. Tanda-Tanda Vital</td>
                <td style="{{ $selIsi }}">
                    Tensi: {{ $resume['ttv']['tensi'] ?: '-' }} &nbsp;&nbsp;
                    Nadi: {{ $resume['ttv']['nadi'] ?: '-' }} &nbsp;&nbsp;
                    Suhu: {{ $resume['ttv']['suhu'] ?: '-' }} &nbsp;&nbsp;
                    Frek. Nafas: {{ $resume['ttv']['nafas'] ?: '-' }}
                    @if ($resume['ttv']['spo2'])
                        &nbsp;&nbsp; SpO2: {{ $resume['ttv']['spo2'] }}
                    @endif
                </td>
            </tr>
            <tr>
                <td style="{{ $selNo }}">&nbsp;</td>
                <td style="{{ $selJudul }}">d. Kelainan Yang Bermasalah</td>
                <td style="{{ $selIsi }} white-space:pre-line;">{{ $resume['kelainan'] ?: '-' }}</td>
            </tr>

            {{-- IV. Diagnosa --}}
            <tr>
                <td style="{{ $selNo }}">IV</td>
                <td style="{{ $selJudul }}"><strong>Diagnosa</strong></td>
                <td style="{{ $selIsi }}">
                    @forelse ($resume['diagnosa'] as $baris)
                        {{ $loop->iteration }}. {{ $baris }}<br>
                    @empty
                        -
                    @endforelse
                </td>
            </tr>

            {{-- V. Kriteria rujukan --}}
            <tr>
                <td style="{{ $selNo }}">V</td>
                <td style="{{ $selJudul }}"><strong>Kriteria Rujukan</strong></td>
                <td style="{{ $selIsi }}">
                    @forelse ($resume['kriteria'] as $baris)
                        {{ $loop->iteration }}. {{ $baris }}<br>
                    @empty
                        -
                    @endforelse
                </td>
            </tr>

            {{-- VI. Tindakan --}}
            <tr>
                <td style="{{ $selNo }}">VI</td>
                <td style="{{ $selJudul }}"><strong>Tindakan Yang Telah Dilakukan</strong></td>
                <td style="{{ $selIsi }}">
                    @forelse ($resume['tindakan'] as $baris)
                        {{ chr(96 + $loop->iteration) }}. {{ $baris }}<br>
                    @empty
                        -
                    @endforelse
                </td>
            </tr>

            {{-- VII. Terapi --}}
            <tr>
                <td style="{{ $selNo }}">VII</td>
                <td style="{{ $selJudul }}"><strong>Terapi Yang Telah Diberikan</strong></td>
                <td style="{{ $selIsi }}">
                    @forelse ($resume['terapi'] as $baris)
                        {{ chr(96 + $loop->iteration) }}. {{ $baris }}<br>
                    @empty
                        -
                    @endforelse
                </td>
            </tr>

            {{-- VIII. Alasan merujuk --}}
            <tr>
                <td style="{{ $selNo }}">VIII</td>
                <td style="{{ $selJudul }}"><strong>Alasan Merujuk</strong></td>
                <td style="{{ $selIsi }} white-space:pre-line;">{{ $resume['alasan'] ?: '-' }}</td>
            </tr>
        </table>

        {{-- Tanda tangan resume --}}
        <table cellpadding="0" cellspacing="0" style="width:100%; font-size:11px; margin-top:18px;">
            <tr>
                <td style="width:60%;">&nbsp;</td>
                <td style="width:40%; text-align:center;">
                    {{ $data['perujuk']['kota'] ? $data['perujuk']['kota'] . ', ' : '' }}{{ $data['tanggal'] ?: '' }}
                </td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td style="height:64px; text-align:center;">&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td style="text-align:center;">
                    <span style="border-top:1px solid #000; padding:0 40px;">{{ $data['dpjp'] ?: '&nbsp;' }}</span>
                </td>
            </tr>
        </table>
    </div>

</x-pdf.layout-a4>
