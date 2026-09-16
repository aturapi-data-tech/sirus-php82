{{-- resources/views/pages/components/rekam-medis/ri/pengkajian-awal-ri/cetak-pengkajian-awal-ri-print.blade.php
     Cetak Pengkajian Awal Keperawatan Rawat Inap — sumber node JSON pengkajianAwalPasienRawatInap.
     Label pilihan dari App\Support\Options\PengkajianAwalRiOptions (satu sumber dgn form EMR). --}}

@use('App\Support\Options\PengkajianAwalRiOptions')

<x-pdf.layout-a4-with-out-background kode="RM-03.11 · Rev.0" title="PENGKAJIAN AWAL KEPERAWATAN RAWAT INAP">

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
            :alamat="$alamatPasien" />
    </x-slot>

    @php
        $dataRawatInap = $data['dataRi'] ?? [];
        $pengkajian = $data['pengkajian'] ?? [];
        $dataUmum = $pengkajian['bagian1DataUmum'] ?? [];
        $riwayatPasien = $pengkajian['bagian2RiwayatPasien'] ?? [];
        $psikososial = $pengkajian['bagian3PsikososialDanEkonomi'] ?? [];
        $pemeriksaanFisik = $pengkajian['bagian4PemeriksaanFisik'] ?? [];
        $tandaVital = $pemeriksaanFisik['tandaVital'] ?? [];
        $sistemOrgan = $pemeriksaanFisik['pemeriksaanSistemOrgan'] ?? [];
        $catatanTandaTangan = $pengkajian['bagian5CatatanDanTandaTangan'] ?? [];
        $daftarLevelingDokter = $pengkajian['levelingDokter'] ?? [];

        // Teks bebas: kosong → '-', baris baru dipertahankan (dompdf tak andal dgn white-space:pre-line).
        $teksBebas = fn($nilai) => filled($nilai) ? nl2br(e($nilai)) : '-';
        $nilaiAtauStrip = fn($nilai) => filled($nilai) ? $nilai : '-'; // dipakai di {{ }} — sudah di-escape Blade

        $kebiasaan = function (array $kebiasaanItem) {
            $teks = PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::KEBIASAAN, $kebiasaanItem['pilihan'] ?? null);
            $detail = collect([data_get($kebiasaanItem, 'detail.jenis'), filled(data_get($kebiasaanItem, 'detail.jumlahPerHari')) ? data_get($kebiasaanItem, 'detail.jumlahPerHari') . '/hari' : null])
                ->filter(fn($isi) => filled($isi))->implode(', ');
            return $detail !== '' && in_array($kebiasaanItem['pilihan'] ?? '', ['ya', 'berhenti'], true) ? "{$teks} ({$detail})" : $teks;
        };

        $nilaiKebudayaan = $psikososial['nilaiKebudayaan'] ?? [];
        $teksNilaiKebudayaan = match ($nilaiKebudayaan['pilihan'] ?? null) {
            'ya' => filled($nilaiKebudayaan['keterangan'] ?? '') ? 'Ada — ' . $nilaiKebudayaan['keterangan'] : 'Ada',
            'tidak' => 'Tidak ada',
            default => '-',
        };

        $identifikasiHambatan = $psikososial['identifikasiHambatan'] ?? [];
        $teksIdentifikasiHambatan = match ($identifikasiHambatan['pilihan'] ?? null) {
            'ya' => PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::HAMBATAN, $identifikasiHambatan['jenis'] ?? null, $identifikasiHambatan['keterangan'] ?? null, true),
            'tidak' => 'Tidak ada',
            default => '-',
        };

        $teksTindakLanjutHambatan = ($identifikasiHambatan['pilihan'] ?? null) === 'ya' && filled($identifikasiHambatan['tindakLanjut'] ?? '')
            ? $identifikasiHambatan['tindakLanjut']
            : '-';

        $kelasJudulBagian = 'text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5';
        $kelasLabel = 'w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top';
        $kelasNilai = 'border border-[#999] px-[5px] py-[2px] align-top';
        $kelasKepalaTabel = 'text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top font-bold';
    @endphp

    {{-- DATA RAWAT INAP --}}
    <table class="w-full border-collapse text-[10px] mt-1.5">
        <tr>
            <td class="{{ $kelasLabel }}">No. Rawat Inap</td>
            <td class="{{ $kelasNilai }}">{{ $nilaiAtauStrip($dataRawatInap['riHdrNo'] ?? null) }}</td>
            <td class="{{ $kelasLabel }}">Tanggal Masuk</td>
            <td class="{{ $kelasNilai }}">{{ $nilaiAtauStrip($dataRawatInap['entryDate'] ?? null) }}</td>
        </tr>
        <tr>
            <td class="{{ $kelasLabel }}">Ruang / Kamar</td>
            <td class="{{ $kelasNilai }}">{{ trim(($dataRawatInap['bangsalDesc'] ?? '') . (filled($dataRawatInap['roomDesc'] ?? '') ? ' / ' . $dataRawatInap['roomDesc'] : '')) ?: '-' }}</td>
            <td class="{{ $kelasLabel }}">Dokter Penerima</td>
            <td class="{{ $kelasNilai }}">{{ $nilaiAtauStrip($dataRawatInap['drDesc'] ?? null) }}</td>
        </tr>
        <tr>
            <td class="{{ $kelasLabel }}">DPJP</td>
            {{-- Leveling Dokter — pola sama dgn kolom DPJP Daftar RI --}}
            <td colspan="3" class="{{ $kelasNilai }}">
                @forelse (array_filter($dataRawatInap['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [], fn($dokterLeveling) => !empty($dokterLeveling['drName'])) as $dokterLeveling)
                    <div>
                        {{ $dokterLeveling['drName'] }}
                        @if (!empty($dokterLeveling['levelDokter']))
                            ({{ $dokterLeveling['levelDokter'] === 'RawatGabung' ? 'Rawat Gabung' : $dokterLeveling['levelDokter'] }})
                        @endif
                    </div>
                @empty
                    -
                @endforelse
            </td>
        </tr>
    </table>

    {{-- BAGIAN 1 — DATA UMUM --}}
    <div class="[page-break-inside:avoid]">
        <div class="{{ $kelasJudulBagian }}">BAGIAN 1 — DATA UMUM</div>
        <table class="w-full border-collapse text-[10px]">
            <tr>
                <td class="{{ $kelasLabel }}">Kondisi Saat Masuk</td>
                <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::KONDISI_SAAT_MASUK, $dataUmum['kondisiSaatMasuk'] ?? null) }}</td>
                <td class="{{ $kelasLabel }}">Asal Pasien</td>
                <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::ASAL_PASIEN, data_get($dataUmum, 'asalPasien.pilihan'), data_get($dataUmum, 'asalPasien.keterangan')) }}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Diagnosis Masuk</td>
                <td colspan="3" class="{{ $kelasNilai }}">{!! $teksBebas($dataUmum['diagnosaMasuk'] ?? null) !!}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Barang Berharga</td>
                <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::BARANG_BERHARGA, data_get($dataUmum, 'barangBerharga.pilihan'), data_get($dataUmum, 'barangBerharga.catatan'), true) }}</td>
                <td class="{{ $kelasLabel }}">Alat Bantu</td>
                <td class="{{ $kelasNilai }}">
                    {{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::ALAT_BANTU, data_get($dataUmum, 'alatBantu.pilihan'), data_get($dataUmum, 'alatBantu.keterangan')) }}{{ filled(data_get($dataUmum, 'alatBantu.catatan')) ? ' (' . data_get($dataUmum, 'alatBantu.catatan') . ')' : '' }}
                </td>
            </tr>
        </table>
    </div>

    {{-- BAGIAN 2 — RIWAYAT PASIEN --}}
    <div class="[page-break-inside:avoid]">
        <div class="{{ $kelasJudulBagian }}">BAGIAN 2 — RIWAYAT PASIEN</div>
        <table class="w-full border-collapse text-[10px]">
            <tr>
                <td class="{{ $kelasLabel }}">Riwayat Penyakit / Operasi / Cedera</td>
                <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::RIWAYAT_PENYAKIT, data_get($riwayatPasien, 'riwayatPenyakitOperasiCedera.pilihan'), data_get($riwayatPasien, 'riwayatPenyakitOperasiCedera.keterangan')) }}</td>
                <td class="{{ $kelasLabel }}">Riwayat Penyakit Keluarga</td>
                <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::RIWAYAT_KELUARGA, data_get($riwayatPasien, 'riwayatKeluarga.pilihan'), data_get($riwayatPasien, 'riwayatKeluarga.keterangan')) }}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Deskripsi Riwayat</td>
                <td colspan="3" class="{{ $kelasNilai }}">{!! $teksBebas(data_get($riwayatPasien, 'riwayatPenyakitOperasiCedera.deskripsi')) !!}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Kebiasaan Merokok</td>
                <td class="{{ $kelasNilai }}">{{ $kebiasaan((array) data_get($riwayatPasien, 'kebiasaan.merokok', [])) }}</td>
                <td class="{{ $kelasLabel }}">Kebiasaan Alkohol / Obat</td>
                <td class="{{ $kelasNilai }}">{{ $kebiasaan((array) data_get($riwayatPasien, 'kebiasaan.alkoholObat', [])) }}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Vaksinasi Influenza</td>
                <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::VAKSINASI, data_get($riwayatPasien, 'vaksinasi.influenza.pilihan')) }}</td>
                <td class="{{ $kelasLabel }}">Vaksinasi Pneumonia</td>
                <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::VAKSINASI, data_get($riwayatPasien, 'vaksinasi.pneumonia.pilihan')) }}</td>
            </tr>
        </table>
    </div>

    {{-- BAGIAN 3 — PSIKOSOSIAL & EKONOMI --}}
    <div class="[page-break-inside:avoid]">
        <div class="{{ $kelasJudulBagian }}">BAGIAN 3 — PSIKOSOSIAL &amp; EKONOMI</div>
        <table class="w-full border-collapse text-[10px]">
            <tr>
                <td class="{{ $kelasLabel }}">Agama / Kepercayaan</td>
                <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::AGAMA, data_get($psikososial, 'agamaKepercayaan.pilihan'), data_get($psikososial, 'agamaKepercayaan.keterangan')) }}</td>
                <td class="{{ $kelasLabel }}">Status Pernikahan</td>
                <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::STATUS_PERNIKAHAN, data_get($psikososial, 'statusPernikahan.pilihan')) }}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Tempat Tinggal</td>
                <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::TEMPAT_TINGGAL, data_get($psikososial, 'tempatTinggal.pilihan'), data_get($psikososial, 'tempatTinggal.keterangan')) }}</td>
                <td class="{{ $kelasLabel }}">Aktivitas</td>
                <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::AKTIVITAS, data_get($psikososial, 'aktivitas.pilihan')) }}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Status Emosional</td>
                <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::STATUS_EMOSIONAL, data_get($psikososial, 'statusEmosional.pilihan'), data_get($psikososial, 'statusEmosional.keterangan')) }}</td>
                <td class="{{ $kelasLabel }}">Informasi Didapat Dari</td>
                <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::INFORMASI_DARI, data_get($psikososial, 'informasiDidapatDari.pilihan'), data_get($psikososial, 'informasiDidapatDari.keterangan')) }}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Nilai Kebudayaan yang Dipercaya</td>
                <td colspan="3" class="{{ $kelasNilai }}">{{ $teksNilaiKebudayaan }}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Identifikasi Hambatan</td>
                <td class="{{ $kelasNilai }}">{{ $teksIdentifikasiHambatan }}</td>
                <td class="{{ $kelasLabel }}">Tindak Lanjut Hambatan</td>
                <td class="{{ $kelasNilai }}">{{ $teksTindakLanjutHambatan }}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Keluarga Dekat</td>
                <td colspan="3" class="{{ $kelasNilai }}">
                    {{ $nilaiAtauStrip(data_get($psikososial, 'keluargaDekat.nama')) }}
                    &nbsp;·&nbsp; Hubungan: {{ $nilaiAtauStrip(data_get($psikososial, 'keluargaDekat.hubungan')) }}
                    &nbsp;·&nbsp; Telp: {{ $nilaiAtauStrip(data_get($psikososial, 'keluargaDekat.telp')) }}
                </td>
            </tr>
        </table>
    </div>

    {{-- BAGIAN 4 — TANDA VITAL & PEMERIKSAAN FISIK --}}
    <div class="[page-break-inside:avoid]">
        <div class="{{ $kelasJudulBagian }}">BAGIAN 4 — TANDA VITAL &amp; PEMERIKSAAN FISIK</div>
        <table class="w-full border-collapse text-[10px]">
            <tr>
                <td class="{{ $kelasLabel }}">Tekanan Darah</td>
                <td class="{{ $kelasNilai }}">{{ $nilaiAtauStrip($tandaVital['sistolik'] ?? null) }} / {{ $nilaiAtauStrip($tandaVital['distolik'] ?? null) }} mmHg</td>
                <td class="{{ $kelasLabel }}">Nadi / Nafas</td>
                <td class="{{ $kelasNilai }}">{{ $nilaiAtauStrip($tandaVital['frekuensiNadi'] ?? null) }} / {{ $nilaiAtauStrip($tandaVital['frekuensiNafas'] ?? null) }} x/mnt</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Suhu / SpO2</td>
                <td class="{{ $kelasNilai }}">{{ $nilaiAtauStrip($tandaVital['suhu'] ?? null) }} °C / {{ $nilaiAtauStrip($tandaVital['spo2'] ?? null) }} %</td>
                <td class="{{ $kelasLabel }}">GDA</td>
                <td class="{{ $kelasNilai }}">{{ $nilaiAtauStrip($tandaVital['gda'] ?? null) }} mg/dL</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Berat / Tinggi Badan</td>
                <td colspan="3" class="{{ $kelasNilai }}">{{ $nilaiAtauStrip($tandaVital['bb'] ?? null) }} kg / {{ $nilaiAtauStrip($tandaVital['tb'] ?? null) }} cm</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Keluhan Utama</td>
                <td colspan="3" class="{{ $kelasNilai }}">{!! $teksBebas($pemeriksaanFisik['keluhanUtama'] ?? null) !!}</td>
            </tr>
            @foreach (array_chunk(PengkajianAwalRiOptions::SISTEM_ORGAN, 2, true) as $pasanganOrgan)
                <tr>
                    @foreach ($pasanganOrgan as $pathOrgan => $organ)
                        <td class="{{ $kelasLabel }}">{{ $organ['label'] }}</td>
                        <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks($organ['opsi'], data_get($sistemOrgan, "{$pathOrgan}.pilihan"), data_get($sistemOrgan, "{$pathOrgan}.keterangan")) }}</td>
                    @endforeach
                </tr>
            @endforeach
            <tr>
                <td class="{{ $kelasLabel }}">Neurologi — Kesadaran</td>
                <td class="{{ $kelasNilai }}">{{ PengkajianAwalRiOptions::teks(PengkajianAwalRiOptions::TINGKAT_KESADARAN, data_get($sistemOrgan, 'neurologi.tingkatKesadaran.pilihan')) }}</td>
                <td class="{{ $kelasLabel }}">GCS (E/V/M)</td>
                <td class="{{ $kelasNilai }}">{{ $nilaiAtauStrip(data_get($sistemOrgan, 'neurologi.gcs')) }}</td>
            </tr>
        </table>
    </div>

    {{-- LEVELING DOKTER (DPJP) --}}
    @if (!empty($daftarLevelingDokter))
        <div class="[page-break-inside:avoid]">
            <div class="{{ $kelasJudulBagian }}">DOKTER PENANGGUNG JAWAB (DPJP)</div>
            <table class="w-full border-collapse text-[10px]">
                <tr>
                    <td class="{{ $kelasKepalaTabel }}">Dokter</td>
                    <td class="{{ $kelasKepalaTabel }}">Poli</td>
                    <td class="{{ $kelasKepalaTabel }}">Level</td>
                    <td class="{{ $kelasKepalaTabel }}">Tanggal</td>
                </tr>
                @foreach ($daftarLevelingDokter as $levelingDokter)
                    <tr>
                        <td class="{{ $kelasNilai }}">{{ $nilaiAtauStrip($levelingDokter['drName'] ?? null) }}</td>
                        <td class="{{ $kelasNilai }}">{{ $nilaiAtauStrip($levelingDokter['poliDesc'] ?? null) }}</td>
                        <td class="{{ $kelasNilai }}">{{ ($levelingDokter['levelDokter'] ?? '') === 'RawatGabung' ? 'Rawat Gabung' : $nilaiAtauStrip($levelingDokter['levelDokter'] ?? null) }}</td>
                        <td class="{{ $kelasNilai }}">{{ $nilaiAtauStrip($levelingDokter['tglEntry'] ?? null) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    {{-- BAGIAN 5 — CATATAN + TTD: satu blok tak terpecah, supaya tanda tangan tidak yatim di halaman sendiri --}}
    <div class="[page-break-inside:avoid]">
        <div class="{{ $kelasJudulBagian }}">BAGIAN 5 — CATATAN &amp; RUMUSAN MASALAH</div>
        <table class="w-full border-collapse text-[10px]">
            <tr>
                <td class="{{ $kelasLabel }}">Catatan Umum</td>
                <td class="{{ $kelasNilai }}">{!! $teksBebas($catatanTandaTangan['catatanUmum'] ?? null) !!}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Rumusan Masalah</td>
                <td class="{{ $kelasNilai }}">{!! $teksBebas($catatanTandaTangan['rumusanMasalah'] ?? null) !!}</td>
            </tr>
        </table>

    {{-- TANDA TANGAN PERAWAT — pola 3-stack (docs/ttd-pattern-pdf-print.md §3) --}}
    <table class="w-full mt-4 text-[10px]">
        <tr>
            <td>&nbsp;</td>
            <td class="w-[40%] text-center align-top">
                <div class="text-center mb-0.5">
                    {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ ($catatanTandaTangan['jamPengkaji'] ?? '') ?: ($data['tglCetak'] ?? '') }}
                </div>
                <div class="text-center">Perawat Pengkaji</div>
                <div class="text-center my-1">
                    @if (!empty($data['ttdPath']))
                        <img class="h-16" src="{{ $data['ttdPath'] }}" alt="TTD Perawat Pengkaji">
                    @else
                        <div class="h-16">&nbsp;</div>
                    @endif
                </div>
                <div class="text-center">
                    <span class="inline-block min-w-[150px] border-t border-black pt-0.5 font-bold">
                        {{ ($catatanTandaTangan['petugasPengkaji'] ?? '') ?: 'Nama Terang' }}
                    </span>
                </div>
            </td>
        </tr>
    </table>
    </div>

</x-pdf.layout-a4-with-out-background>
