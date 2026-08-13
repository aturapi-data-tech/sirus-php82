{{-- resources/views/pages/components/modul-dokumen/ri/edukasi-terintegrasi/cetak-edukasi-terintegrasi-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="FORMULIR EDUKASI PASIEN TERINTEGRASI — RAWAT INAP">

    {{-- ── IDENTITAS PASIEN ── --}}
    <x-slot name="patientData">
        @php
            $identitas = $data['identitas'] ?? [];
            $alamatPasien = trim(
                ($identitas['alamat'] ?? '-') .
                    (!empty($identitas['rt']) ? ' RT ' . $identitas['rt'] : '') .
                    (!empty($identitas['rw']) ? '/RW ' . $identitas['rw'] : '') .
                    (!empty($identitas['desaName']) ? ', ' . $identitas['desaName'] : '') .
                    (!empty($identitas['kecamatanName']) ? ', ' . $identitas['kecamatanName'] : ''),
            );
        @endphp
        <x-pdf.identitas-pasien
            :rm="$data['regNo'] ?? null"
            :nama="$data['regName'] ?? null"
            :jenisKelamin="$data['jenisKelamin']['jenisKelaminDesc'] ?? null"
            :tempatLahir="$data['tempatLahir'] ?? null"
            :tglLahir="$data['tglLahir'] ?? null"
            :umur="$data['thn'] ?? null"
            :alamat="$alamatPasien">
            @if (!empty($data['dataRi']['riHdrNo']))
                <tr>
                    <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Rawat Inap</td>
                    <td class="py-0.5 text-[11px] px-1">:</td>
                    <td class="py-0.5 text-[11px] font-bold">{{ $data['dataRi']['riHdrNo'] }}</td>
                </tr>
            @endif
        </x-pdf.identitas-pasien>
    </x-slot>

    @php
        $entry = $data['entry'] ?? [];
        $form = $entry['form'] ?? [];
        $identitasRs = $data['identitasRs'] ?? null;
        $rsName = $identitasRs->int_name ?? 'RSI MADINAH';
        $rsAddress = $identitasRs->int_address ?? '';

        // ── Maps key → label (satu sumber: App\Support\EdukasiTerintegrasiOptions) ──
        $mapTujuan = \App\Support\EdukasiTerintegrasiOptions::tujuan();
        $mapPref = \App\Support\EdukasiTerintegrasiOptions::preferensi();
        $mapKebutuhan = \App\Support\EdukasiTerintegrasiOptions::kebutuhan();
        $mapMetode = \App\Support\EdukasiTerintegrasiOptions::metode();
        $mapHasil = \App\Support\EdukasiTerintegrasiOptions::hasil();
        $mapRujuk = \App\Support\EdukasiTerintegrasiOptions::rujuk();

        // Centang aman-font: font default dompdf (Helvetica) tak punya glyph U+2713 → tampil "?".
        // DejaVu Sans (dibundel dompdf) punya glyph-nya.
        $centang = '<span style="font-family: DejaVu Sans, sans-serif;">&#10003;</span>';

        // Helper boolean → label
        $boolLabel = function ($nilai) {
            if (in_array($nilai, [true, 1, '1'], true)) {
                return 'Ya';
            }
            if (in_array($nilai, [false, 0, '0'], true)) {
                return 'Tidak';
            }
            return '-';
        };

        // Data section
        $tglEdukasi = $form['tglEdukasi'] ?? '-';
        $petugasName = $form['pemberiInformasi']['petugasName'] ?? '-';

        $tujuanOpsi = (array) ($form['tujuan']['opsi'] ?? []);
        $tujuanLain = $form['tujuan']['lainnya'] ?? '';

        // Entri lama tidak punya key ini — dianggap BERSEDIA agar tetap tercetak utuh.
        $bersediaEdukasi = filter_var($form['bersediaMenerimaInformasi'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $evaluasiAwal = $form['evaluasiAwal'] ?? [];
        $literasi = $evaluasiAwal['literasi'] ?? '-';
        $motivasiBelajar = $evaluasiAwal['motivasiBelajar'] ?? '-';
        // Entri lama menyimpan satu kolom gabungan; pecah agar tetap tercetak
        // di dua baris terpisah tanpa mengubah data tersimpan.
        $bahasaLegacy = trim((string) ($evaluasiAwal['bahasaAtauPendidikan'] ?? ''));
        $bahasa = trim((string) ($evaluasiAwal['bahasa'] ?? ''));
        $tingkatPendidikan = trim((string) ($evaluasiAwal['tingkatPendidikan'] ?? ''));
        if ($bahasa === '' && $tingkatPendidikan === '' && $bahasaLegacy !== '') {
            $bagianLegacy = array_map('trim', explode('/', $bahasaLegacy, 2));
            $bahasa = $bagianLegacy[0] ?? '';
            $tingkatPendidikan = $bagianLegacy[1] ?? '';
        }
        $prefOpsi = (array) ($evaluasiAwal['preferensiInformasi']['opsi'] ?? []);
        $prefLain = $evaluasiAwal['preferensiInformasi']['lainnya'] ?? '';

        $kebutuhanOpsi = (array) ($form['kebutuhan']['opsi'] ?? []);
        $kebutuhanLain = $form['kebutuhan']['lainnya'] ?? '';

        $metodeOpsi = (array) ($form['metodeMedia']['opsi'] ?? []);
        $metodeLain = $form['metodeMedia']['lainnya'] ?? '';

        $hasil = $form['hasil'] ?? [];

        $tindakLanjut = $form['tindakLanjut'] ?? [];
        $tindakLanjutTanggal = $tindakLanjut['edukasiLanjutanTanggal'] ?? '';
        $tindakLanjutKeterangan = $tindakLanjut['edukasiLanjutanKeterangan'] ?? '';
        $tindakLanjutRujuk = (array) ($tindakLanjut['dirujukKe'] ?? []);
        $tindakLanjutTidakPerlu = !empty($tindakLanjut['tidakPerluTL']);
    @endphp

    <table class="w-full text-[10px] border-collapse">

        {{-- ── HEADER: Tanggal, Pemberi & Sasaran ── --}}
        <tr>
            <td class="border border-black px-2 py-1 w-1/2 text-[10px]">
                <strong>Tanggal Edukasi:</strong> {{ $tglEdukasi }}
            </td>
            <td class="border border-black px-2 py-1 w-1/2 text-[10px]">
                <strong>Pemberi Informasi:</strong> {{ $petugasName }}
            </td>
        </tr>
        @php
            $mapHubungan = \App\Support\EdukasiTerintegrasiOptions::hubungan();
            // Entri lama belum punya node sasaran → fallback ke penanda tangan (ttd.*).
            $sasaranNama = ($form['sasaran']['nama'] ?? '') ?: ($form['ttd']['pasienKeluargaNama'] ?? '');
            $sasaranHubungan = ($form['sasaran']['hubungan'] ?? '') ?: ($form['ttd']['pasienKeluargaHubungan'] ?? '');
        @endphp
        <tr>
            <td class="border border-black px-2 py-1 w-1/2 text-[10px]">
                <strong>Sasaran Edukasi:</strong> {{ $sasaranNama ?: '-' }}
            </td>
            <td class="border border-black px-2 py-1 w-1/2 text-[10px]">
                <strong>Hubungan dengan Pasien:</strong> {{ $mapHubungan[$sasaranHubungan] ?? ($sasaranHubungan ?: '-') }}
            </td>
        </tr>

        @unless ($bersediaEdukasi)
            {{-- Menolak menerima informasi: seksi 1-6 tidak dicetak, diganti satu
                 pernyataan. Blok tanda tangan di bawah tetap dicetak sebagai bukti. --}}
            <tr>
                <td colspan="2" class="border border-black px-2 py-2 text-[10px] leading-relaxed">
                    <p class="font-bold mb-1">Penolakan Menerima Informasi</p>
                    <p>Pasien / keluarga menyatakan <strong>tidak bersedia</strong> menerima informasi
                    dan edukasi pada waktu tersebut di atas. Materi edukasi tidak diberikan, dan
                    penolakan ini dibuktikan dengan tanda tangan di bawah ini.</p>
                </td>
            </tr>
        @else
        {{-- ── 1. TUJUAN EDUKASI ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 text-[10px] leading-relaxed">
                <p class="font-bold mb-1">1. Tujuan Edukasi</p>
                @if (count($tujuanOpsi) > 0)
                    @foreach ($tujuanOpsi as $opsi)
                        <div>{!! $centang !!} {{ $mapTujuan[$opsi] ?? $opsi }}@if ($opsi === 'lainnya' && !empty($tujuanLain)): {{ $tujuanLain }}@endif</div>
                    @endforeach
                @else
                    <span class="text-gray-500">-</span>
                @endif
            </td>
        </tr>

        {{-- ── 2. EVALUASI AWAL ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 text-[10px] leading-relaxed">
                <p class="font-bold mb-1">2. Evaluasi Awal Kemampuan & Nilai</p>
                <div>&bull; <strong>Kemampuan membaca, menulis dan menerima edukasi:</strong> {{ $literasi ?: '-' }}</div>
                <div>&bull; <strong>Kemauan / motivasi belajar:</strong> {{ $motivasiBelajar ?: '-' }}</div>
                <div>&bull; <strong>Bahasa yang digunakan:</strong> {{ $bahasa ?: '-' }}</div>
                <div>&bull; <strong>Tingkat pendidikan:</strong> {{ $tingkatPendidikan ?: '-' }}</div>
                @php $hambatanEmosional = $evaluasiAwal['hambatanEmosional'] ?? []; @endphp
                <div>&bull; <strong>Hambatan emosional / motivasi:</strong> {{ $boolLabel($hambatanEmosional['ada'] ?? null) }}@if (!empty($hambatanEmosional['keterangan'])) &mdash; {{ $hambatanEmosional['keterangan'] }}@endif</div>
                @php $keterbatasanFisikKognitif = $evaluasiAwal['keterbatasanFisikKognitif'] ?? []; @endphp
                <div>&bull; <strong>Keterbatasan fisik / kognitif:</strong> {{ $boolLabel($keterbatasanFisikKognitif['ada'] ?? null) }}@if (!empty($keterbatasanFisikKognitif['keterangan'])) &mdash; {{ $keterbatasanFisikKognitif['keterangan'] }}@endif</div>
                @php $nilaiBudaya = $evaluasiAwal['nilaiKeyakinanBudaya'] ?? []; @endphp
                <div>&bull; <strong>Nilai / keyakinan / budaya:</strong> {{ $boolLabel($nilaiBudaya['ada'] ?? null) }}@if (!empty($nilaiBudaya['deskripsi'])) &mdash; {{ $nilaiBudaya['deskripsi'] }}@endif</div>
                <div>&bull; <strong>Preferensi menerima informasi:</strong>
                    @if (count($prefOpsi) > 0)
                        @php
                            $prefLabels = [];
                            foreach ($prefOpsi as $opsi) {
                                $prefLabels[] = ($mapPref[$opsi] ?? $opsi) . ($opsi === 'lainnya' && !empty($prefLain) ? ' (' . $prefLain . ')' : '');
                            }
                        @endphp
                        {{ implode(', ', $prefLabels) }}
                    @else
                        -
                    @endif
                </div>
            </td>
        </tr>

        {{-- ── 3. KEBUTUHAN EDUKASI ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 text-[10px] leading-relaxed">
                <p class="font-bold mb-1">3. Kebutuhan Edukasi</p>
                @if (count($kebutuhanOpsi) > 0)
                    @foreach ($kebutuhanOpsi as $opsi)
                        <div>{!! $centang !!} {{ $mapKebutuhan[$opsi] ?? $opsi }}@if ($opsi === 'lainnya' && !empty($kebutuhanLain)): {{ $kebutuhanLain }}@endif</div>
                    @endforeach
                @else
                    <span class="text-gray-500">-</span>
                @endif
                @php
                    $materiTopik = $form['materi']['topik'] ?? '';
                    $materiKeterangan = $form['materi']['keterangan'] ?? '';
                @endphp
                @if ($materiTopik !== '' || $materiKeterangan !== '')
                    <div class="mt-1"><strong>Materi / Topik:</strong> {{ $materiTopik ?: '-' }}</div>
                    @if ($materiKeterangan !== '')
                        <div><strong>Keterangan:</strong> {{ $materiKeterangan }}</div>
                    @endif
                @endif
            </td>
        </tr>

        {{-- ── 4. METODE & MEDIA ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 text-[10px] leading-relaxed">
                <p class="font-bold mb-1">4. Metode & Media Edukasi</p>
                @if (count($metodeOpsi) > 0)
                    @foreach ($metodeOpsi as $opsi)
                        <div>{!! $centang !!} {{ $mapMetode[$opsi] ?? $opsi }}@if ($opsi === 'lainnya' && !empty($metodeLain)): {{ $metodeLain }}@endif</div>
                    @endforeach
                @else
                    <span class="text-gray-500">-</span>
                @endif
            </td>
        </tr>

        {{-- ── 5. HASIL / EVALUASI ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 text-[10px]">
                <p class="font-bold mb-1">5. Hasil / Evaluasi Edukasi</p>
                <table class="w-full text-[9px] border-collapse">
                    <thead>
                        <tr>
                            <th class="border border-black px-1 py-0.5 text-left">Indikator</th>
                            <th class="border border-black px-1 py-0.5 w-12 text-center">Hasil</th>
                            <th class="border border-black px-1 py-0.5 text-left">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mapHasil as $hasilKey => $hasilLabel)
                            @php $hasilBaris = $hasil[$hasilKey] ?? []; @endphp
                            <tr>
                                <td class="border border-black px-1 py-0.5">{{ $hasilLabel }}</td>
                                <td class="border border-black px-1 py-0.5 text-center">{{ $boolLabel($hasilBaris['ya'] ?? null) }}</td>
                                <td class="border border-black px-1 py-0.5">{{ $hasilBaris['keterangan'] ?? '' ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>

        {{-- ── 6. TINDAK LANJUT ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 text-[10px] leading-relaxed">
                <p class="font-bold mb-1">6. Tindak Lanjut</p>
                @if ($tindakLanjutTidakPerlu)
                    <div>{!! $centang !!} Tidak diperlukan tindak lanjut.</div>
                @else
                    <div>&bull; <strong>Tanggal edukasi lanjutan:</strong> {{ $tindakLanjutTanggal ?: '-' }}@if (!empty($tindakLanjutKeterangan)) &mdash; {{ $tindakLanjutKeterangan }}@endif</div>
                    @if (count($tindakLanjutRujuk) > 0)
                        <div>&bull; <strong>Dirujuk ke:</strong>
                            @php $rujukLabels = array_map(fn($rujuk) => $mapRujuk[$rujuk] ?? ucfirst($rujuk), $tindakLanjutRujuk); @endphp
                            {{ implode(', ', $rujukLabels) }}
                        </div>
                    @endif
                @endif
            </td>
        </tr>

        @endunless

        {{-- ── TANDA TANGAN — 2 kolom ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        {{-- Kolom kiri: Pasien / Keluarga --}}
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Pasien / Keluarga</p>

                            <div class="text-center my-1">
                                @if (!empty($form['ttd']['pasienKeluargaTTD']))
                                    <img src="{{ $form['ttd']['pasienKeluargaTTD'] }}" class="h-16" alt="Tanda Tangan Pasien" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>

                            <div class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                                <p class="font-bold">{{ strtoupper($form['ttd']['pasienKeluargaNama'] ?? '-') }}</p>
                                @php
                                    $hubunganMap = \App\Support\EdukasiTerintegrasiOptions::hubungan();
                                    $hubunganVal = $form['ttd']['pasienKeluargaHubungan'] ?? '';
                                @endphp
                                @if ($hubunganVal)
                                    <p class="text-[9px] text-gray-600">{{ $hubunganMap[$hubunganVal] ?? $hubunganVal }}</p>
                                @endif
                            </div>
                        </td>

                        {{-- Garis pemisah --}}
                        <td style="border-left: 1px solid #d1d5db; width: 1px;"></td>

                        {{-- Kolom kanan: Petugas Pemberi Edukasi --}}
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Petugas Pemberi Edukasi</p>

                            <div class="text-center my-1">
                                @if (!empty($data['ttdPetugasPath']))
                                    <img src="{{ $data['ttdPetugasPath'] }}" class="h-16" alt="Tanda Tangan Petugas" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>

                            <div class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                                <p class="font-bold">{{ strtoupper($form['pemberiInformasi']['petugasName'] ?? '-') }}</p>
                                <p class="text-[9px] text-gray-500">{{ $data['tglCetak'] ?? '-' }}</p>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── FOOTER INFO ── --}}
        <tr>
            <td colspan="2" class="px-1.5 py-1 text-[9px] text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                {{ $rsName }}, {{ $rsAddress }}
            </td>
        </tr>

    </table>

</x-pdf.layout-a4-with-out-background>
