{{--
    Surat Pengantar Rujukan + Data Medis (Resume Klinis) Pasien Rujukan.

    Memakai STANDAR DOKUMEN CETAK RS: x-pdf.layout-a4-with-out-background (kop x-logo.identitas
    + CSS Tailwind hasil build ter-inline). Tanpa kode formulir RM — belum ada di daftar
    /panduan-dev/koding-formulir-rm (jangan mengarang kode).

    Dasar isi: PMK 16/2024 Pasal 17 — identitas pasien, identitas fasyankes penerima, rekam medis,
    dan alasan rujukan. Halaman 1 punya DUA format:
      - 'bpjs' (Rawat Jalan SISRUTE, ada Nomor Rujukan BPJS): isi & GAYA GARIS meniru contoh BPJS
        16/11/26 "rujukan-vclaim-*.pdf" — kotak bergaris abu tipis membulat, nomor | QR dipisah garis
        tegak, kotak Dari/Kepada berjarak, latar abu untuk poli tujuan & kode diagnosa, garis kaki.
      - 'kepmenkes' (jalur FHIR Ranap/IGD): daftar isian "Label : nilai" (contoh Kemkes 10/11/26).
    Halaman 2 (Resume Klinis) sama untuk keduanya.

    Catatan dompdf: <table> untuk kolom (flex/grid tak didukung); kelas `underline` tak ter-render
    (Tailwind menulis text-decoration-line) → inline style; DejaVu agar °, —, · tercetak.
--}}
<x-pdf.layout-a4-with-out-background>
    <style>

        .surat-rujukan {
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


        .ttd td {
            text-align: center;
            padding: 1px 0;
        }
    </style>

    <div class="surat-rujukan">
    @php
        $isi = fn($nilai) => filled($nilai) ? $nilai : '-';
        $tempatTanggal = ($data['perujuk']['kota'] ? $data['perujuk']['kota'] . ', ' : '') . 'Tanggal ' . ($data['tanggal'] ?: '');
    @endphp

    {{-- ══════════ HALAMAN 1 (Rawat Jalan BPJS) — isi & gaya garis contoh BPJS 16/11/26
         "rujukan-vclaim-*.pdf"; kop = standar RS (layout). ══════════ --}}
    @if ($data['format'] === 'bpjs')
        @php
            // Gaya kotak BPJS: garis abu tipis, sudut membulat, isi berjarak.
            $kotak = 'border border-gray-300 rounded p-3';
            $label = 'text-[8px] text-gray-500';
        @endphp
        <div class="text-[10px] text-black" style="font-family:'DejaVu Sans', sans-serif;">
            {{-- JUDUL + NOMOR | QR — satu kotak, dipisah garis tegak --}}
            <table class="w-full mt-3 mb-3 {{ $kotak }}">
                <tr>
                    <td class="p-3 align-top">
                        <div class="text-lg font-bold">Surat Pengantar Rujukan</div>
                        <div class="mb-2 text-[8px] font-bold tracking-wider text-gray-500">RUJUKAN FKRTL &middot; VCLAIM</div>
                        <div class="{{ $label }}">Nomor Rujukan BPJS</div>
                        <div class="mb-2 text-[20px] font-bold font-mono">{{ $isi($data['noRujukanBpjs']) }}</div>
                        <table class="w-full">
                            <tr class="{{ $label }}">
                                <td class="pr-2">No. Rujukan Satu Sehat (SSR)</td>
                                <td class="pr-2">Tanggal Rujukan</td>
                                <td>Rencana Berkunjung</td>
                            </tr>
                            <tr class="font-bold">
                                <td class="pr-2 font-mono">{{ $isi($data['noRujukan']) }}</td>
                                <td class="pr-2">{{ $isi($data['tglRujukanPanjang'] ?: $data['tanggal']) }}</td>
                                <td>{{ $isi($data['tglRencana']) }}</td>
                            </tr>
                        </table>
                    </td>
                    <td class="w-32 p-3 text-center align-middle border-l border-gray-300">
                        @if (filled($data['qrNoRujukan']))
                            <img src="data:image/png;base64,{{ $data['qrNoRujukan'] }}" class="w-20 h-20" alt="QR">
                            <div class="mt-1 {{ $label }}">Pindai nomor rujukan</div>
                        @endif
                    </td>
                </tr>
            </table>

            {{-- DARI | KEPADA — dua kotak berjarak --}}
            <table class="w-full mb-3">
                <tr>
                    <td class="w-1/2 align-top {{ $kotak }}">
                        <div class="{{ $label }} font-bold tracking-wider">DARI &middot; FASYANKES PERUJUK</div>
                        <div class="mb-2 text-[11px] font-bold">{{ $isi($data['perujuk']['nama']) }}</div>
                        <div><span class="text-gray-500">Kode Faskes</span> <span class="font-bold font-mono">{{ $isi($data['perujuk']['kode']) }}</span></div>
                        <div>{{ $isi($data['perujuk']['alamat']) }}</div>
                    </td>
                    <td class="w-4">&nbsp;</td>
                    <td class="w-1/2 align-top {{ $kotak }}">
                        <div class="{{ $label }} font-bold tracking-wider">KEPADA &middot; FASYANKES PENERIMA</div>
                        <div class="mb-2 text-[11px] font-bold">{{ $isi($data['tujuan']['nama']) }}</div>
                        <div><span class="text-gray-500">Kode Faskes</span> <span class="font-bold font-mono">{{ $isi($data['tujuan']['kode']) }}</span></div>
                        <div>{{ $isi($data['tujuan']['alamat']) }}</div>
                        <div class="px-2 py-1 mt-2 bg-gray-100 border border-gray-200 rounded">
                            <span class="text-gray-500">Poli tujuan:</span> <span class="font-bold">{{ $isi($data['poliTujuan']) }}</span>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="mb-2">Dengan hormat, mohon dilakukan pemeriksaan, perawatan dan pengobatan lanjutan pada pasien berikut:</div>

            {{-- DATA PASIEN --}}
            <table class="w-full mb-3">
                <tr>
                    <td class="{{ $kotak }}">
                        <table class="w-full">
                            <tr>
                                <td class="{{ $label }} font-bold tracking-wider">DATA PASIEN</td>
                                <td class="text-right {{ $label }}">No. RM <span class="text-[10px] font-bold text-black">{{ $isi($data['pasien']['noRm']) }}</span></td>
                            </tr>
                        </table>
                        <div class="mt-1 {{ $label }}">Nama</div>
                        <div class="text-[12px] font-bold">{{ $isi($data['pasien']['nama']) }}</div>
                        <table class="w-full mt-1">
                            <tr class="{{ $label }}">
                                <td class="w-1/3">Umur</td>
                                <td class="w-1/3">Jenis Kelamin</td>
                                <td class="w-1/3">Nomor Kartu BPJS</td>
                            </tr>
                            <tr class="font-bold">
                                <td>{{ $isi($data['pasien']['umur']) }}</td>
                                <td>{{ $isi($data['pasien']['jenisKelamin']) }}</td>
                                <td class="font-mono">{{ $isi($data['pasien']['noBpjs']) }}</td>
                            </tr>
                        </table>
                        <div class="mt-2 {{ $label }}">Alamat</div>
                        <div>{{ $isi($data['pasien']['alamat']) }}</div>
                        <div class="mt-2 {{ $label }}">Diagnosa Saat Dirujuk</div>
                        <table class="mt-1">
                            <tr>
                                <td class="px-3 py-1 font-bold font-mono bg-gray-100 border border-gray-200 rounded">{{ $isi($data['diagnosaKode']) }}</td>
                                <td class="pl-3 font-bold align-middle">{{ $data['diagnosaDesc'] }}</td>
                            </tr>
                        </table>
                        <div class="mt-2 {{ $label }}">Catatan</div>
                        <div class="whitespace-pre-line">{{ $isi($data['catatan']) }}</div>
                    </td>
                </tr>
            </table>

            <div class="mb-2 text-justify leading-snug">
                Rujukan telah mendapatkan persetujuan baik lisan maupun tertulis dari pasien dan/atau keluarga pasien
                untuk dirujuk. Demikian kami sampaikan, atas perhatian Saudara kami ucapkan terima kasih.
            </div>

            {{-- Tanda tangan: tinggi tetap, tanpa flex/br (jebakan dompdf) --}}
            <table class="w-full mt-2 text-center">
                <tr>
                    <td class="w-1/2">&nbsp;</td>
                    <td class="w-1/2">{{ $data['perujuk']['kota'] }}, {{ $data['tglRujukanPanjang'] ?: $data['tanggal'] }}</td>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                    <td class="font-bold">Dokter Penanggung Jawab Pasien (Perujuk)</td>
                </tr>
                <tr>
                    <td class="h-20">&nbsp;</td>
                    <td class="h-20">&nbsp;</td>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                    <td class="font-bold">
                        @if (filled($data['dpjp']))
                            {{-- dompdf tak kenal text-decoration-line (kelas underline) --}}
                            <span style="text-decoration:underline;">{{ $data['dpjp'] }}</span>
                        @else
                            ..................................................
                        @endif
                    </td>
                </tr>
            </table>

            {{-- Garis kaki seperti contoh BPJS --}}
            <div class="pt-1 mt-6 text-right text-[8px] text-gray-500 border-t border-gray-300">Dicetak dari SIRUS &middot; {{ $data['dicetakPada'] }}</div>
        </div>
    @else
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

    @endif

    {{-- ══════════════════ HALAMAN 2 — DATA MEDIS (RESUME KLINIS) ══════════════════ --}}
    @php
        $resume = $data['resume'];
        $ttv = $resume['ttv'];
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
    @endphp

    {{-- Gaya BPJS (kotak garis abu membulat, label kecil abu, sans-serif); isi & penomoran I–IX
         format Kemkes. DIRANCANG MUAT 1 LEMBAR walau terapi banyak: identitas sebaris (lengkapnya
         di halaman 1), bagian IV–IX menumpuk berurutan, terapi dua kolom bila > 6 baris,
         kotak & tanda tangan rapat. --}}
    @php
        $kotak2 = 'border border-gray-300 rounded px-2 py-1';
        $label2 = 'text-[8px] text-gray-500';
        $judulBagian = 'text-[8px] font-bold tracking-wider text-gray-500';
        $terapiList = array_values($resume['terapi']);
        $terapiDuaKolom = count($terapiList) > 6;
        $belahTerapi = (int) ceil(count($terapiList) / 2);
        $terapiTeks = fn(array $baris, int $mulai) => collect($baris)->values()
            ->map(fn($b, $i) => chr(97 + (($mulai + $i) % 26)) . '. ' . $b)->implode("\n");
    @endphp
    <div style="page-break-before:always;"></div>
    <div class="text-[9px] leading-snug text-black" style="font-family:'DejaVu Sans', sans-serif;">
        <table class="w-full mt-2 mb-1">
            <tr>
                <td class="align-bottom">
                    <div class="text-[13px] font-bold">Data Medis (Resume Klinis) Pasien Rujukan</div>
                </td>
                <td class="text-right align-bottom {{ $label2 }} font-bold tracking-wider">
                    @if (filled($data['noRujukanBpjs']))
                        NO. BPJS {{ $data['noRujukanBpjs'] }} &middot;
                    @endif
                    SSR {{ $isi($data['noRujukan']) }}
                </td>
            </tr>
        </table>

        {{-- I. IDENTITAS — satu baris --}}
        <table class="w-full mb-1">
            <tr>
                <td class="{{ $kotak2 }}">
                    <div class="{{ $judulBagian }}">I &middot; IDENTITAS PASIEN</div>
                    <table class="w-full">
                        <tr class="{{ $label2 }}">
                            <td class="w-2/5">Nama</td><td>Umur</td><td>Jenis Kelamin</td><td>No. BPJS</td><td>No. RM</td>
                        </tr>
                        <tr class="font-bold">
                            <td class="text-[10px]">{{ $isi($data['pasien']['nama']) }}</td>
                            <td>{{ $isi($data['pasien']['umur']) }}</td>
                            <td>{{ $isi($data['pasien']['jenisKelamin']) }}</td>
                            <td class="font-mono">{{ $isi($data['pasien']['noBpjs']) }}</td>
                            <td class="font-mono">{{ $isi($data['pasien']['noRm']) }}</td>
                        </tr>
                    </table>
                    <div><span class="{{ $label2 }}">Alamat</span> {{ $isi($data['pasien']['alamat']) }}</div>
                </td>
            </tr>
        </table>

        {{-- II. KELUHAN --}}
        <table class="w-full mb-1">
            <tr>
                <td class="{{ $kotak2 }}">
                    <span class="{{ $judulBagian }}">II &middot; KELUHAN PASIEN</span>
                    <div class="whitespace-pre-line">{{ $isi($resume['keluhanUtama']) }}</div>
                </td>
            </tr>
        </table>

        {{-- III. PEMERIKSAAN FISIK --}}
        <table class="w-full mb-1">
            <tr>
                <td class="{{ $kotak2 }}">
                    <div class="{{ $judulBagian }}">III &middot; PEMERIKSAAN FISIK</div>
                    <table class="w-full">
                        <tr class="{{ $label2 }}">
                            <td class="w-1/4">a. Keadaan Umum</td>
                            <td class="w-1/4">b. Kesadaran (GCS)</td>
                            <td class="w-1/2">e. Alergi (Ada/Tidak) Sebutkan</td>
                        </tr>
                        <tr class="font-bold">
                            <td>{{ $isi($resume['keadaanUmum']) }}</td>
                            <td>{{ $isi($resume['gcs']) }}</td>
                            <td class="font-normal whitespace-pre-line">{{ $isi($resume['alergi']) }}</td>
                        </tr>
                    </table>
                    <div class="{{ $label2 }}">c. Tanda-Tanda Vital</div>
                    <table class="w-full">
                        <tr>
                            @foreach (['TD' => $ttv['tensi'], 'Nadi' => $ttv['nadi'], 'Suhu' => $ttv['suhu'], 'Frek. Nafas' => $ttv['nafas'], 'SpO2' => $ttv['spo2']] as $namaTtv => $nilaiTtv)
                                <td class="px-2 py-1.5 bg-gray-100 border border-gray-200 rounded">
                                    <span class="{{ $label2 }}">{{ $namaTtv }}</span> <span class="font-bold">{{ $isi($nilaiTtv) }}</span>
                                </td>
                                @if (!$loop->last)
                                    <td class="w-2">&nbsp;</td>
                                @endif
                            @endforeach
                        </tr>
                    </table>
                    <div><span class="{{ $label2 }}">d. Kondisi Kelainan Bermasalah</span> <span class="whitespace-pre-line">{{ $isi($resume['kelainan']) }}</span></div>
                </td>
            </tr>
        </table>

        {{-- IV–VIII menumpuk ke bawah sesuai urutan Kemkes (permintaan user 16/11); terapi tetap 2 kolom bila banyak --}}
        <table class="w-full mb-1">
            <tr>
                <td class="{{ $kotak2 }}">
                    <div class="{{ $judulBagian }}">IV &middot; DIAGNOSA SAAT RUJUKAN</div>
                    <div class="whitespace-pre-line">{{ $angkaList($resume['diagnosa']) }}</div>
                </td>
            </tr>
        </table>
        <table class="w-full mb-1">
            <tr>
                <td class="{{ $kotak2 }}">
                    <div class="{{ $judulBagian }}">V &middot; KRITERIA RUJUKAN</div>
                    <div class="whitespace-pre-line">{{ $angkaList($resume['kriteria']) }}</div>
                </td>
            </tr>
        </table>
        <table class="w-full mb-1">
            <tr>
                <td class="{{ $kotak2 }}">
                    <div class="{{ $judulBagian }}">VI &middot; PEMERIKSAAN PENUNJANG &amp; HASILNYA</div>
                    <div class="whitespace-pre-line">{{ $hurufList($penunjang) }}</div>
                </td>
            </tr>
        </table>

        {{-- VII. TERAPI — dua kolom bila banyak --}}
        <table class="w-full mb-1">
            <tr>
                <td class="{{ $kotak2 }}">
                    <div class="{{ $judulBagian }}">VII &middot; TERAPI YANG TELAH DIBERIKAN</div>
                    @if ($terapiList === [])
                        <div>-</div>
                    @elseif ($terapiDuaKolom)
                        <table class="w-full">
                            <tr>
                                <td class="w-1/2 pr-2 align-top whitespace-pre-line">{{ $terapiTeks(array_slice($terapiList, 0, $belahTerapi), 0) }}</td>
                                <td class="w-1/2 align-top whitespace-pre-line">{{ $terapiTeks(array_slice($terapiList, $belahTerapi), $belahTerapi) }}</td>
                            </tr>
                        </table>
                    @else
                        <div class="whitespace-pre-line">{{ $terapiTeks($terapiList, 0) }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <table class="w-full mb-1">
            <tr>
                <td class="{{ $kotak2 }}">
                    <div class="{{ $judulBagian }}">VIII &middot; TINDAKAN MEDIS YANG TELAH DILAKUKAN</div>
                    <div class="whitespace-pre-line">{{ $hurufList($resume['tindakan']) }}</div>
                </td>
            </tr>
        </table>

        {{-- IX. ALASAN --}}
        <table class="w-full mb-1">
            <tr>
                <td class="{{ $kotak2 }}">
                    <div class="{{ $judulBagian }}">IX &middot; ALASAN MERUJUK &amp; RENCANA TINDAK LANJUT</div>
                    <div class="whitespace-pre-line">{{ $isi($resume['alasan']) }}</div>
                </td>
            </tr>
        </table>

        {{-- Tanda tangan rapat, tak boleh terbelah halaman --}}
        <table class="w-full mt-1 text-center" style="page-break-inside:avoid;">
            <tr>
                <td class="w-1/2">&nbsp;</td>
                <td class="w-1/2">{{ $data['perujuk']['kota'] }}, {{ $data['tglRujukanPanjang'] ?: $data['tanggal'] }}</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td class="font-bold">Dokter Penanggung Jawab Pasien (Perujuk)</td>
            </tr>
            <tr>
                <td class="h-12">&nbsp;</td>
                <td class="h-12">&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td class="font-bold">
                    @if (filled($data['dpjp']))
                        {{-- dompdf tak kenal text-decoration-line (kelas underline) --}}
                        <span style="text-decoration:underline;">{{ $data['dpjp'] }}</span>
                    @else
                        ..................................................
                    @endif
                </td>
            </tr>
        </table>

        <div class="pt-1 mt-2 text-right text-[8px] text-gray-500 border-t border-gray-300">Dicetak dari SIRUS &middot; {{ $data['dicetakPada'] }}</div>
    </div>
    </div>
</x-pdf.layout-a4-with-out-background>
