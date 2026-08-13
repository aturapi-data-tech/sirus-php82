{{-- resources/views/pages/components/modul-dokumen/rj/rekam-medis/cetak-rekam-medis-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="ASSESMENT AWAL RAWAT JALAN">

    {{-- IDENTITAS PASIEN — sejajar dengan logo --}}
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

    {{-- ================================================================ --}}
    {{-- ASSESMENT AWAL RAWAT JALAN                                        --}}
    {{-- ================================================================ --}}
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
    @endphp

    <table class="w-full text-[10px] border-collapse">

        {{-- ──  PERAWAT (full width) ────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top w-28">
                PERAWAT
            </td>
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
                &nbsp;&nbsp;
                <span class="font-bold">Keterangan Status Psikologis</span>
                <br>
                <span class="font-bold">Status Mental :</span>
                {{ $dataDaftarTxn['anamnesa']['statusMental']['statusMental'] ?? '-' }}
                @if (!empty($dataDaftarTxn['anamnesa']['statusMental']['sebutstatusPsikologis']))
                    &mdash; {{ $dataDaftarTxn['anamnesa']['statusMental']['sebutstatusPsikologis'] }}
                @endif
                &nbsp;&nbsp;
                <span class="font-bold">Keterangan Status Mental :</span>
                {{ $dataDaftarTxn['anamnesa']['statusMental']['keteranganStatusMental'] ?? '-' }}
            </td>
        </tr>

        {{-- ── ANAMNESA + panel kanan (Perawat, Tanda Vital, Nutrisi) ──── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">
                ANAMNESA
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top">
                <span class="font-bold">Keluhan Utama :</span>
                {!! nl2br(e($dataDaftarTxn['anamnesa']['keluhanUtama']['keluhanUtama'] ?? '-')) !!}
                <br>
                <span class="font-bold">Screening Batuk :</span>
                {{ $dataDaftarTxn['anamnesa']['screeningBatuk'] ?? '-' }}
                <br>

                {{-- ── SKALA NYERI ── --}}
                <span class="font-bold">Skala Nyeri :</span>
                Metode : {{ $ringkasNyeri['metode'] }} /
                Skor : {{ $ringkasNyeri['skor'] }} /
                {{ $ringkasNyeri['label'] }}@if ($ringkasNyeri['catatan']) ({{ $ringkasNyeri['catatan'] }})@endif /
                Pencetus : {{ $nyeriTerakhir['nyeri']['pencetus'] ?? '-' }} /
                Durasi : {{ $nyeriTerakhir['nyeri']['durasi'] ?? '-' }} /
                Lokasi : {{ $nyeriTerakhir['nyeri']['lokasi'] ?? '-' }}
                <br>

                {{-- ── RESIKO JATUH ── --}}
                <span class="font-bold">Resiko Jatuh :</span>
                Metode : {{ $resikoJatuhTerakhir['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '-' }} /
                Skor : {{ $resikoJatuhTerakhir['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] ?? '-' }} /
                {{ $resikoJatuhTerakhir['resikoJatuh']['kategoriResiko'] ?? '-' }}
                <br>

                {{-- ── RISIKO BUNUH DIRI C-SSRS (tampil hanya jika ada data) ── --}}
                @if ($resikoBunuhDiriTerakhir)
                    <span class="font-bold">Risiko Bunuh Diri (C-SSRS) :</span>
                    Skor keparahan : {{ $resikoBunuhDiriTerakhir['skorKeparahan'] ?? '-' }} /
                    {{ $resikoBunuhDiriTerakhir['kategoriResiko'] ?? '-' }}{{ !empty($resikoBunuhDiriTerakhir['tindakLanjut']) ? ' / ' . implode(', ', $resikoBunuhDiriTerakhir['tindakLanjut']) : '' }}
                    <br>
                @endif

                {{-- ── DEKUBITUS (tampil hanya jika ada data) ── --}}
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

                {{-- ── GIZI (tampil hanya jika ada data) ── --}}
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

                <span class="font-bold">Riwayat Penyakit Sekarang :</span>
                {!! nl2br(e($dataDaftarTxn['anamnesa']['riwayatPenyakitSekarangUmum']['riwayatPenyakitSekarangUmum'] ?? '-')) !!}
                <br>
                <span class="font-bold">Riwayat Penyakit Dahulu :</span>
                {!! nl2br(e($dataDaftarTxn['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? '-')) !!}
                <br>
                <span class="font-bold">Alergi :</span>
                {!! nl2br(e(\App\Support\Terminologi\AlergiSnomed::untukCetak($dataDaftarTxn['anamnesa']['alergi'] ?? []))) !!}
                <br>
                {{-- Rekonsiliasi Obat — TIDAK dicetak di RJ: entry-nya memang tidak ada di
                     EMR RJ (skema rekonsiliasiObat dikomentari di rm-anamnesa-rj-actions.blade.php),
                     jadi tabelnya selalu kosong. Fitur ini hidup di UGD & RI.
                <span class="font-bold">Rekonsiliasi Obat :</span>
                <table class="w-full border-collapse mt-0.5 text-[10px]">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-500 px-1 py-0.5 text-left">Nama Obat</th>
                            <th class="border border-gray-500 px-1 py-0.5 text-left">Dosis</th>
                            <th class="border border-gray-500 px-1 py-0.5 text-left">Rute</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dataDaftarTxn['anamnesa']['rekonsiliasiObat'] ?? [] as $obat)
                            <tr>
                                <td class="border border-gray-500 px-1 py-0.5">{{ $obat['namaObat'] ?? '-' }}</td>
                                <td class="border border-gray-500 px-1 py-0.5">{{ $obat['dosis'] ?? '-' }}</td>
                                <td class="border border-gray-500 px-1 py-0.5">{{ $obat['rute'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="border border-gray-500 px-1 py-0.5">&nbsp;</td>
                                <td class="border border-gray-500 px-1 py-0.5">&nbsp;</td>
                                <td class="border border-gray-500 px-1 py-0.5">&nbsp;</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                --}}
            </td>

            {{-- ── PANEL KANAN: Perawat + Tanda Vital + Nutrisi ────────── --}}
            <td class="border border-black px-1.5 py-0.5 align-top w-44">
                <p class="font-bold mb-0.5">PERAWAT / TERAPIS :</p>

                {{-- TTD Perawat --}}
                @isset($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerima'])
                    @if ($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerima'])
                        @isset($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])
                            @if ($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])
                                @php
                                    $ttdPerawat = App\Models\User::where(
                                        'myuser_code',
                                        $dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'],
                                    )->value('myuser_ttd_image');
                                @endphp
                                @if (!empty($ttdPerawat))
                                    <img class="h-16" src="@ttdSrc($ttdPerawat)" alt="">
                                @endif
                            @endif
                        @endisset
                    @endif
                @endisset

                <p class="mb-1.5">
                    {{ isset($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerima'])
                        ? strtoupper($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerima'])
                        : '-' }}
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

        {{-- ── KEADAAN UMUM ─────────────────────────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-middle">
                KEADAAN UMUM
            </td>
            <td class="border border-black px-1.5 py-0.5 align-middle" colspan="2">
                {{ $dataDaftarTxn['pemeriksaan']['tandaVital']['keadaanUmum'] ?? 'BAIK' }}
                &nbsp;/&nbsp;
                <span class="font-bold">Tingkat Kesadaran :</span>
                {{ $dataDaftarTxn['pemeriksaan']['tandaVital']['tingkatKesadaran'] ?? '-' }}
            </td>
        </tr>

        {{-- ── FUNGSIONAL ───────────────────────────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-middle">
                FUNGSIONAL
            </td>
            <td class="border border-black px-1.5 py-0.5 align-middle" colspan="2">
                <span class="font-bold">Alat Bantu :</span> {{ $dataDaftarTxn['pemeriksaan']['fungsional']['alatBantu'] ?? '-' }}
                &nbsp;/&nbsp;
                <span class="font-bold">Prothesa :</span> {{ $dataDaftarTxn['pemeriksaan']['fungsional']['prothesa'] ?? '-' }}
                &nbsp;/&nbsp;
                <span class="font-bold">Cacat Tubuh :</span>
                {{ $dataDaftarTxn['pemeriksaan']['fungsional']['cacatTubuh'] ?? '-' }}
                &nbsp;/&nbsp;
                <span class="font-bold">Suspek Akibat Kecelakaan Kerja :</span>
                @php
                    $suspekAK = $dataDaftarTxn['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerja'] ?? '-';
                    $ketAK = trim($dataDaftarTxn['pemeriksaan']['suspekAkibatKerja']['keteranganSuspekAkibatKerja'] ?? '');
                @endphp
                {{ $suspekAK }}@if ($suspekAK === 'Ya' && $ketAK !== '') &nbsp;({{ $ketAK }})@endif
            </td>
        </tr>

        {{-- ── PEMERIKSAAN ────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">
                PEMERIKSAAN
            </td>
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
                            <span class="font-semibold">{{ strtoupper($key) }}</span>:
                            {{ $kelainan }} &mdash; {!! nl2br(e($pAnatomi['desc'] ?? '-')) !!}<br>
                        @endif
                    @endforeach
                @endif
            </td>
        </tr>

        {{-- ── PENUNJANG ────────────────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">
                PENUNJANG
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="2">
                <span class="font-bold">Pemeriksaan Penunjang Lab / Foto / EKG / Lan-lain :</span><br>
                {!! nl2br(e($dataDaftarTxn['pemeriksaan']['penunjang'] ?? '-')) !!}
            </td>
        </tr>

        {{-- ── DIAGNOSIS ────────────────────────────────────────────────── --}}
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
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">
                DIAGNOSIS
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="2">
                {!! nl2br(e($diagnosisText)) !!}
            </td>
        </tr>

        {{-- ── PROSEDUR ─────────────────────────────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">
                PROSEDUR
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="2">
                {!! nl2br(e($procedureText)) !!}
            </td>
        </tr>

        {{-- ── TINDAK LANJUT ────────────────────────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-middle">
                TINDAK LANJUT
            </td>
            <td class="border border-black px-1.5 py-0.5 align-middle" colspan="2">
                {{ $dataDaftarTxn['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-' }}
                @if (!empty($dataDaftarTxn['perencanaan']['tindakLanjut']['keteranganTindakLanjut']))
                    / {{ $dataDaftarTxn['perencanaan']['tindakLanjut']['keteranganTindakLanjut'] }}
                @endif
            </td>
        </tr>

        {{-- ── TERAPI + TTD DOKTER ──────────────────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">
                TERAPI
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top">
                {!! nl2br(e($dataDaftarTxn['perencanaan']['terapi']['terapi'] ?? '-')) !!}
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top text-center">
                Tulungagung, {{ $data['tglCetak'] ?? \Carbon\Carbon::now()->format('d/m/Y') }}
                <br>

                {{-- TTD Dokter --}}
                @isset($dataDaftarTxn['perencanaan']['pengkajianMedis']['drPemeriksa'])
                    @if ($dataDaftarTxn['perencanaan']['pengkajianMedis']['drPemeriksa'])
                        @php
                            $ttdDokter = App\Models\User::where('myuser_code', $dataDaftarTxn['drId'] ?? '')->value(
                                'myuser_ttd_image',
                            );
                        @endphp
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
                    <span class="text-[10px]">
                        {{ $data['namaDokter'] ?? 'dr. ............................................' }}
                    </span>
                    @if (!empty($data['strDokter']))
                        <div class="text-[9px] text-gray-500">STR: {{ $data['strDokter'] }}</div>
                    @endif
                </div>
            </td>
        </tr>

    </table>

</x-pdf.layout-a4-with-out-background>
