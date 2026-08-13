{{-- resources/views/pages/components/rekam-medis/ugd/cetak-rekam-medis/cetak-rekam-medis-ugd-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="ASSESMENT AWAL UGD">

    <x-slot name="patientData">
        @php
            $identitas = $data['identitas'] ?? [];
            $alamatFull = trim(
                ($identitas['alamat'] ?? '') .
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
            :alamat="$alamatFull">
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tgl. Masuk</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $data['dataDaftarTxn']['rjDate'] ?? '-' }}</td>
            </tr>
        </x-pdf.identitas-pasien>
    </x-slot>

    @php
        $dataDaftarTxn = $data['dataDaftarTxn'];
        // entriTerakhir(): record lama menyimpan penilaian.nyeri sebagai SATU entri
        // (assoc) — end() di atasnya mengembalikan isi kolom terakhir, bukan entri.
        // Nama kelas ditulis lengkap: berkas cetak ini tidak bisa mengimpor kelas —
        // di dalam komponen PDF blok ini terkompilasi bukan di scope berkas, dan
        // impor di atas tag komponen bikin tag pembuka gagal terkompilasi.
        $nyeriTerakhir = \App\Support\Options\NyeriOptions::entriTerakhir($dataDaftarTxn['penilaian']['nyeri'] ?? null);
        $ringkasNyeri = \App\Support\Options\NyeriOptions::ringkasEntri($nyeriTerakhir);
        $resikoJatuhTerakhir = !empty($dataDaftarTxn['penilaian']['resikoJatuh']) ? end($dataDaftarTxn['penilaian']['resikoJatuh']) : null;
        $resikoBunuhDiriTerakhir = !empty($dataDaftarTxn['penilaian']['resikoBunuhDiri']) ? end($dataDaftarTxn['penilaian']['resikoBunuhDiri']) : null;
        $dekubitusTerakhir = !empty($dataDaftarTxn['penilaian']['dekubitus']) ? end($dataDaftarTxn['penilaian']['dekubitus']) : null;
        $giziTerakhir = !empty($dataDaftarTxn['penilaian']['gizi']) ? end($dataDaftarTxn['penilaian']['gizi']) : null;

        $kajian = $dataDaftarTxn['anamnesa']['pengkajianPerawatan'] ?? [];
        $tingkatKegawatan = $kajian['tingkatKegawatan'] ?? '-';
        $tingkatKegawatanLabel = match ($tingkatKegawatan) {
            'P1' => 'P1 — Kritis',
            'P2' => 'P2 — Urgent',
            'P3' => 'P3 — Minor',
            'P0' => 'P0 — Death',
            default => '-',
        };
        // Warna tebal supaya triase jelas tercetak (terutama print B/W kalau di-fotocopy
        // tetap kelihatan kontrasnya). Pasangkan dengan text putih untuk P1/P3/P0.
        $tingkatKegawatanBg = match ($tingkatKegawatan) {
            'P1' => '#dc2626', // red-600
            'P2' => '#facc15', // yellow-400
            'P3' => '#16a34a', // green-600
            'P0' => '#1f2937', // gray-800
            default => '#ffffff',
        };
        $tingkatKegawatanFg = match ($tingkatKegawatan) {
            'P2' => '#111827', // gray-900 — kontras di atas kuning
            'P1', 'P3', 'P0' => '#ffffff',
            default => '#111827',
        };
    @endphp

    <table class="w-full text-[10px] border-collapse">

        {{-- TRIASE --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top w-28">TRIASE</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="2"
                style="background-color: {{ $tingkatKegawatanBg }}; color: {{ $tingkatKegawatanFg }};">
                <span class="font-bold">Tingkat Kegawatan :</span>
                <strong>{{ $tingkatKegawatanLabel }}</strong>
                @if (!empty($kajian['caraMasukIgd']))
                    &nbsp;/&nbsp;<span class="font-bold">Cara Masuk IGD :</span> {{ $kajian['caraMasukIgd'] }}
                @endif
                @if (!empty($kajian['jamDatang']))
                    &nbsp;/&nbsp;<span class="font-bold">Jam Datang :</span> {{ $kajian['jamDatang'] }}
                @endif
                @if (!empty($kajian['perawatPenerima']))
                    <br>
                    <span class="font-bold">Perawat Penerima :</span> {{ strtoupper($kajian['perawatPenerima']) }}
                    @if (!empty($kajian['perawatPenerimaCode']))
                        ({{ $kajian['perawatPenerimaCode'] }})
                    @endif
                @endif
            </td>
        </tr>

        {{-- PERAWAT --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top w-28">PERAWAT</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="2">
                <span class="font-bold">Status Psikologis :</span>
                {{ isset($dataDaftarTxn['anamnesa']['statusPsikologis']['tidakAdaKelainan']) ? ($dataDaftarTxn['anamnesa']['statusPsikologis']['tidakAdaKelainan'] ? 'Tidak Ada Kelainan' : '-') : '-' }}
                {{ isset($dataDaftarTxn['anamnesa']['statusPsikologis']['marah']) ? ($dataDaftarTxn['anamnesa']['statusPsikologis']['marah'] ? '/ Marah' : '') : '' }}
                {{ isset($dataDaftarTxn['anamnesa']['statusPsikologis']['ccemas']) ? ($dataDaftarTxn['anamnesa']['statusPsikologis']['ccemas'] ? '/ Cemas' : '') : '' }}
                {{ isset($dataDaftarTxn['anamnesa']['statusPsikologis']['takut']) ? ($dataDaftarTxn['anamnesa']['statusPsikologis']['takut'] ? '/ Takut' : '') : '' }}
                {{ isset($dataDaftarTxn['anamnesa']['statusPsikologis']['sedih']) ? ($dataDaftarTxn['anamnesa']['statusPsikologis']['sedih'] ? '/ Sedih' : '') : '' }}
                {{ isset($dataDaftarTxn['anamnesa']['statusPsikologis']['cenderungBunuhDiri']) ? ($dataDaftarTxn['anamnesa']['statusPsikologis']['cenderungBunuhDiri'] ? '/ Resiko Bunuh Diri' : '') : '' }}
                @if (!empty($dataDaftarTxn['anamnesa']['statusPsikologis']['sebutstatusPsikologis']))
                    &mdash; {{ $dataDaftarTxn['anamnesa']['statusPsikologis']['sebutstatusPsikologis'] }}
                @endif
                <br>
                <span class="font-bold">Status Mental :</span>
                {{ $dataDaftarTxn['anamnesa']['statusMental']['statusMental'] ?? '-' }}
                @if (!empty($dataDaftarTxn['anamnesa']['statusMental']['keteranganStatusMental']))
                    &mdash; {{ $dataDaftarTxn['anamnesa']['statusMental']['keteranganStatusMental'] }}
                @endif
            </td>
        </tr>

        {{-- ANAMNESA --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">ANAMNESA</td>
            <td class="border border-black px-1.5 py-0.5 align-top">
                <span class="font-bold">Keluhan Utama :</span> {!! nl2br(e($dataDaftarTxn['anamnesa']['keluhanUtama']['keluhanUtama'] ?? '-')) !!}<br>
                <span class="font-bold">Screening Batuk :</span> {{ $dataDaftarTxn['anamnesa']['screeningBatuk'] ?? '-' }}<br>
                <span class="font-bold">Status Medik :</span>
                {{ ($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['statusMedik']['statusMedik'] ?? '') !== '' ? $dataDaftarTxn['anamnesa']['pengkajianPerawatan']['statusMedik']['statusMedik'] : '-' }}<br>
                <span class="font-bold">Skala Nyeri :</span>
                Metode : {{ $ringkasNyeri['metode'] }} /
                Skor : {{ $ringkasNyeri['skor'] }} /
                {{ $ringkasNyeri['label'] }}@if ($ringkasNyeri['catatan']) ({{ $ringkasNyeri['catatan'] }})@endif /
                Pencetus : {{ $nyeriTerakhir['nyeri']['pencetus'] ?? '-' }} /
                Durasi : {{ $nyeriTerakhir['nyeri']['durasi'] ?? '-' }} /
                Lokasi : {{ $nyeriTerakhir['nyeri']['lokasi'] ?? '-' }} /
                Sensasi : {{ $nyeriTerakhir['nyeri']['sensasi'] ?? '-' }}<br>
                <span class="font-bold">Resiko Jatuh :</span>
                Metode : {{ $resikoJatuhTerakhir['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '-' }} /
                Skor : {{ $resikoJatuhTerakhir['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] ?? '-' }} /
                {{ $resikoJatuhTerakhir['resikoJatuh']['kategoriResiko'] ?? '-' }}<br>
                @if ($resikoBunuhDiriTerakhir)
                    <span class="font-bold">Risiko Bunuh Diri (C-SSRS) :</span>
                    Skor keparahan : {{ $resikoBunuhDiriTerakhir['skorKeparahan'] ?? '-' }} /
                    {{ $resikoBunuhDiriTerakhir['kategoriResiko'] ?? '-' }}{{ !empty($resikoBunuhDiriTerakhir['tindakLanjut']) ? ' / ' . implode(', ', $resikoBunuhDiriTerakhir['tindakLanjut']) : '' }}<br>
                @endif
                @if ($dekubitusTerakhir)
                    <span class="font-bold">Dekubitus :</span>
                    {{ $dekubitusTerakhir['dekubitus']['dekubitus'] ?? '-' }} /
                    Skor Braden : {{ $dekubitusTerakhir['dekubitus']['bradenScore'] ?? '-' }} /
                    {{ $dekubitusTerakhir['dekubitus']['kategoriResiko'] ?? '-' }}
                    @if (!empty($dekubitusTerakhir['dekubitus']['rekomendasi']))
                        / {{ $dekubitusTerakhir['dekubitus']['rekomendasi'] }}
                    @endif
                    <br>
                @endif
                @if ($giziTerakhir)
                    <span class="font-bold">Gizi :</span>
                    BB : {{ $giziTerakhir['gizi']['beratBadan'] ?? '-' }} kg /
                    TB : {{ $giziTerakhir['gizi']['tinggiBadan'] ?? '-' }} cm /
                    IMT : {{ $giziTerakhir['gizi']['imt'] ?? '-' }} /
                    Skor Skrining : {{ $giziTerakhir['gizi']['skorSkrining'] ?? '-' }} /
                    {{ $giziTerakhir['gizi']['kategoriGizi'] ?? '-' }}
                    @if (!empty($giziTerakhir['gizi']['catatan']))
                        / {{ $giziTerakhir['gizi']['catatan'] }}
                    @endif
                    <br>
                @endif
                <span class="font-bold">Riwayat Penyakit Sekarang :</span> {!! nl2br(e($dataDaftarTxn['anamnesa']['riwayatPenyakitSekarangUmum']['riwayatPenyakitSekarangUmum'] ?? '-')) !!}<br>
                <span class="font-bold">Riwayat Penyakit Dahulu :</span> {!! nl2br(e($dataDaftarTxn['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? '-')) !!}<br>
                <span class="font-bold">Alergi :</span> {!! nl2br(e(\App\Support\Terminologi\AlergiSnomed::untukCetak($dataDaftarTxn['anamnesa']['alergi'] ?? []))) !!}<br>
                <span class="font-bold">Rekonsiliasi Obat :</span>
                <table class="w-full border-collapse mt-0.5 text-[10px]">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-500 px-1 py-0.5 text-left" style="width:40%">Nama Obat</th>
                            <th class="border border-gray-500 px-1 py-0.5 text-left" style="width:14%">Dosis</th>
                            <th class="border border-gray-500 px-1 py-0.5 text-left" style="width:12%">Rute</th>
                            {{-- Dua keputusan digabung satu kolom (label atas-bawah) supaya nama obat lebih lega --}}
                            <th class="border border-gray-500 px-1 py-0.5 text-left" style="width:34%">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dataDaftarTxn['anamnesa']['rekonsiliasiObat'] ?? [] as $obat)
                            @php
                                $dibawaRanap = filled($obat['dibawaRanap'] ?? null) ? $obat['dibawaRanap'] : '-';
                                $lanjutPulang = filled($obat['lanjutPulang'] ?? null) ? $obat['lanjutPulang'] : '-';
                            @endphp
                            <tr>
                                <td class="border border-gray-500 px-1 py-0.5">{{ $obat['namaObat'] ?? '-' }}</td>
                                <td class="border border-gray-500 px-1 py-0.5">{{ $obat['dosis'] ?? '-' }}</td>
                                <td class="border border-gray-500 px-1 py-0.5">{{ $obat['rute'] ?? '-' }}</td>
                                <td class="border border-gray-500 px-1 py-0.5" style="white-space:nowrap">
                                    Dibawa saat ranap : {{ $dibawaRanap }}<br>
                                    Lanjut saat pulang : {{ $lanjutPulang }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="border border-gray-500 px-1 py-0.5">&nbsp;</td>
                                <td class="border border-gray-500 px-1 py-0.5">&nbsp;</td>
                                <td class="border border-gray-500 px-1 py-0.5">&nbsp;</td>
                                <td class="border border-gray-500 px-1 py-0.5">&nbsp;</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>

            {{-- PANEL KANAN --}}
            <td class="border border-black px-1.5 py-0.5 align-top w-44">
                <p class="font-bold mb-0.5">PERAWAT / TERAPIS :</p>
                @isset($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])
                    @if ($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])
                        @php $ttdPerawat = App\Models\User::where('myuser_code', $dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])->value('myuser_ttd_image'); @endphp
                        @if (!empty($ttdPerawat))
                            <img class="h-16" src="@ttdSrc($ttdPerawat)" alt="">
                        @endif
                    @endif
                @endisset
                <p class="mb-1.5">
                    {{ isset($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerima']) ? strtoupper($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerima']) : '-' }}
                </p>

                <p class="font-bold mb-0.5">TANDA VITAL :</p>
                <table cellpadding="0" cellspacing="0" class="w-full text-[10px]">
                    <tr>
                        <td class="w-20">TD</td>
                        <td>:&nbsp;{{ $dataDaftarTxn['pemeriksaan']['tandaVital']['sistolik'] ?? '-' }} /
                            {{ $dataDaftarTxn['pemeriksaan']['tandaVital']['distolik'] ?? '-' }} mmhg</td>
                    </tr>
                    <tr>
                        <td>Nadi</td>
                        <td>:&nbsp;{{ $dataDaftarTxn['pemeriksaan']['tandaVital']['frekuensiNadi'] ?? '-' }} x/mnt</td>
                    </tr>
                    <tr>
                        <td>Suhu</td>
                        <td>:&nbsp;{{ $dataDaftarTxn['pemeriksaan']['tandaVital']['suhu'] ?? '-' }} °C</td>
                    </tr>
                    <tr>
                        <td>Pernafasan</td>
                        <td>:&nbsp;{{ $dataDaftarTxn['pemeriksaan']['tandaVital']['frekuensiNafas'] ?? '-' }} x/mnt</td>
                    </tr>
                    <tr>
                        <td>SPO2</td>
                        <td>:&nbsp;{{ $dataDaftarTxn['pemeriksaan']['tandaVital']['spo2'] ?? '-' }} %</td>
                    </tr>
                    <tr>
                        <td>GDA</td>
                        <td>:&nbsp;{{ $dataDaftarTxn['pemeriksaan']['tandaVital']['gda'] ?? '-' }} mg/dL</td>
                    </tr>
                    <tr>
                        <td>GCS</td>
                        <td>:&nbsp;E{{ $dataDaftarTxn['pemeriksaan']['tandaVital']['e'] ?? '-' }}
                            V{{ $dataDaftarTxn['pemeriksaan']['tandaVital']['v'] ?? '-' }}
                            M{{ $dataDaftarTxn['pemeriksaan']['tandaVital']['m'] ?? '-' }}
                            ({{ $dataDaftarTxn['pemeriksaan']['tandaVital']['gcs'] ?? '-' }})</td>
                    </tr>
                </table>

                <p class="font-bold mt-1.5 mb-0.5">NUTRISI :</p>
                <table cellpadding="0" cellspacing="0" class="w-full text-[10px]">
                    <tr>
                        <td class="w-20">Berat Badan</td>
                        <td>:&nbsp;{{ $dataDaftarTxn['pemeriksaan']['nutrisi']['bb'] ?? '-' }} Kg</td>
                    </tr>
                    <tr>
                        <td>Tinggi Badan</td>
                        <td>:&nbsp;{{ $dataDaftarTxn['pemeriksaan']['nutrisi']['tb'] ?? '-' }} cm</td>
                    </tr>
                    <tr>
                        <td>Index Masa Tubuh</td>
                        <td>:&nbsp;{{ $dataDaftarTxn['pemeriksaan']['nutrisi']['imt'] ?? '-' }} Kg/M2</td>
                    </tr>
                    <tr>
                        <td>Lingkar Kepala</td>
                        <td>:&nbsp;{{ $dataDaftarTxn['pemeriksaan']['nutrisi']['lk'] ?? '-' }} cm</td>
                    </tr>
                    <tr>
                        <td>Lingkar Lengan Atas</td>
                        <td>:&nbsp;{{ $dataDaftarTxn['pemeriksaan']['nutrisi']['lila'] ?? '-' }} cm</td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- KEADAAN UMUM --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-middle">KEADAAN UMUM</td>
            <td class="border border-black px-1.5 py-0.5 align-middle" colspan="2">
                {{ $dataDaftarTxn['pemeriksaan']['tandaVital']['keadaanUmum'] ?? 'BAIK' }} &nbsp;/&nbsp;
                <span class="font-bold">Tingkat Kesadaran :</span>
                {{ $dataDaftarTxn['pemeriksaan']['tandaVital']['tingkatKesadaran'] ?? '-' }}
            </td>
        </tr>

        {{-- PENGKAJIAN PRIMER (ABCD) --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-middle">PENGKAJIAN PRIMER</td>
            <td class="border border-black px-1.5 py-0.5 align-middle" colspan="2">
                <span class="font-bold">A — Jalan Nafas :</span>
                {{ $dataDaftarTxn['pemeriksaan']['tandaVital']['jalanNafas']['jalanNafas'] ?? '-' }}
                &nbsp;/&nbsp;
                <span class="font-bold">B — Pernafasan :</span>
                {{ $dataDaftarTxn['pemeriksaan']['tandaVital']['pernafasan']['pernafasan'] ?? '-' }}
                &nbsp;/&nbsp;
                <span class="font-bold">Gerak Dada :</span>
                {{ $dataDaftarTxn['pemeriksaan']['tandaVital']['gerakDada']['gerakDada'] ?? '-' }}
                &nbsp;/&nbsp;
                <span class="font-bold">C — Sirkulasi :</span>
                {{ $dataDaftarTxn['pemeriksaan']['tandaVital']['sirkulasi']['sirkulasi'] ?? '-' }}
                &nbsp;/&nbsp;
                <span class="font-bold">D — Disability :</span>
                {{ $dataDaftarTxn['pemeriksaan']['tandaVital']['disability']['disability'] ?? '-' }}
            </td>
        </tr>

        {{-- FUNGSIONAL --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-middle">FUNGSIONAL</td>
            <td class="border border-black px-1.5 py-0.5 align-middle" colspan="2">
                <span class="font-bold">Alat Bantu :</span> {{ $dataDaftarTxn['pemeriksaan']['fungsional']['alatBantu'] ?? '-' }}
                &nbsp;/&nbsp;
                <span class="font-bold">Prothesa :</span> {{ $dataDaftarTxn['pemeriksaan']['fungsional']['prothesa'] ?? '-' }}
                &nbsp;/&nbsp;
                <span class="font-bold">Cacat Tubuh :</span>
                {{ $dataDaftarTxn['pemeriksaan']['fungsional']['cacatTubuh'] ?? '-' }} &nbsp;/&nbsp;
                <span class="font-bold">Suspek Akibat Kecelakaan Kerja :</span>
                @php
                    $suspekAK = $dataDaftarTxn['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerja'] ?? '-';
                    $ketAK = trim($dataDaftarTxn['pemeriksaan']['suspekAkibatKerja']['keteranganSuspekAkibatKerja'] ?? '');
                @endphp
                {{ $suspekAK }}@if ($suspekAK === 'Ya' && $ketAK !== '') &nbsp;({{ $ketAK }})@endif
            </td>
        </tr>

        {{-- PEMERIKSAAN --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">PEMERIKSAAN</td>
            <td class="border border-black px-1.5 py-0.5 align-top">
                <span class="font-bold">Fisik dan Uji Fungsi:</span><br>
                {!! nl2br(e($dataDaftarTxn['pemeriksaan']['fisik'] ?? '-')) !!}
                {!! nl2br(e($dataDaftarTxn['pemeriksaan']['FisikujiFungsi']['FisikujiFungsi'] ?? '')) !!}
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top">
                <span class="font-bold">Anatomi :</span><br>
                @if (!empty($dataDaftarTxn['pemeriksaan']['anatomi']))
                    @foreach ($dataDaftarTxn['pemeriksaan']['anatomi'] as $key => $pAnatomi)
                        @php $kelainan = $pAnatomi['kelainan'] ?? false; @endphp
                        @if ($kelainan && $kelainan !== 'Tidak Diperiksa')
                            <span class="font-semibold">{{ strtoupper($key) }}</span>: {{ $kelainan }} &mdash;
                            {!! nl2br(e($pAnatomi['desc'] ?? '-')) !!}<br>
                        @endif
                    @endforeach
                @endif
            </td>
        </tr>

        {{-- PENUNJANG --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">PENUNJANG</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="2">
                <span class="font-bold">Pemeriksaan Penunjang Lab / Foto / EKG / Lain-lain :</span><br>
                {!! nl2br(e($dataDaftarTxn['pemeriksaan']['penunjang'] ?? '-')) !!}
            </td>
        </tr>

        {{-- DIAGNOSIS --}}
        @php
            // Prioritas freetext dari dokter; fallback ke keterangan ICD-10 (kode disembunyikan).
            $diagnosisText = trim((string) ($dataDaftarTxn['diagnosisFreeText'] ?? ''));
            if ($diagnosisText === '') {
                $diagnosisDescriptions = collect($dataDaftarTxn['diagnosis'] ?? [])
                    ->pluck('diagDesc')
                    ->map(fn($desc) => trim((string) $desc))
                    ->filter()
                    ->values()
                    ->all();
                $diagnosisText = $diagnosisDescriptions ? implode("\n", $diagnosisDescriptions) : '-';
            }

            // Prioritas freetext dari dokter; fallback ke keterangan ICD-9-CM (kode disembunyikan).
            $procedureText = trim((string) ($dataDaftarTxn['procedureFreeText'] ?? ''));
            if ($procedureText === '') {
                $procedureDescriptions = collect($dataDaftarTxn['procedure'] ?? [])
                    ->pluck('procedureDesc')
                    ->map(fn($desc) => trim((string) $desc))
                    ->filter()
                    ->values()
                    ->all();
                $procedureText = $procedureDescriptions ? implode("\n", $procedureDescriptions) : '-';
            }
        @endphp
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">DIAGNOSIS</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="2">{!! nl2br(e($diagnosisText)) !!}</td>
        </tr>

        {{-- PROSEDUR --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">PROSEDUR</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="2">{!! nl2br(e($procedureText)) !!}</td>
        </tr>

        {{-- TINDAK LANJUT --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-middle">TINDAK LANJUT</td>
            <td class="border border-black px-1.5 py-0.5 align-middle" colspan="2">
                {{ $dataDaftarTxn['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-' }}
                @if (!empty($dataDaftarTxn['perencanaan']['tindakLanjut']['keteranganTindakLanjut']))
                    / {{ $dataDaftarTxn['perencanaan']['tindakLanjut']['keteranganTindakLanjut'] }}
                @endif
            </td>
        </tr>

        {{-- TERAPI + TTD --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">TERAPI</td>
            <td class="border border-black px-1.5 py-0.5 align-top">{!! nl2br(e($dataDaftarTxn['perencanaan']['terapi']['terapi'] ?? '-')) !!}</td>
            <td class="border border-black px-1.5 py-0.5 align-top text-center">
                Tulungagung, {{ $data['tglCetak'] ?? \Carbon\Carbon::now()->format('d/m/Y') }}<br>
                @isset($dataDaftarTxn['perencanaan']['pengkajianMedis']['drPemeriksa'])
                    @if ($dataDaftarTxn['perencanaan']['pengkajianMedis']['drPemeriksa'])
                        @php $ttdDokter = App\Models\User::where('myuser_code', $dataDaftarTxn['drId'] ?? '')->value('myuser_ttd_image'); @endphp
                        @if (!empty($ttdDokter))
                            <img class="h-16" src="@ttdSrc($ttdDokter)" alt="">
                        @else
                            <div class="h-16">&nbsp;</div>
                        @endif
                    @else
                        <div class="h-16">&nbsp;</div>
                    @endif
                @else
                    <div class="h-16">&nbsp;</div>
                @endisset
                <div class="inline-block min-w-[130px] border-t border-black pt-0.5">
                    <span
                        class="text-[10px]">{{ $data['namaDokter'] ?? 'dr. ............................................' }}</span>
                    @if (!empty($data['strDokter']))
                        <div class="text-[9px] text-gray-500">STR: {{ $data['strDokter'] }}</div>
                    @endif
                </div>
            </td>
        </tr>

    </table>

</x-pdf.layout-a4-with-out-background>
