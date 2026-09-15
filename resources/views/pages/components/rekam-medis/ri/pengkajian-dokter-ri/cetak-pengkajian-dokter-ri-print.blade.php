{{-- resources/views/pages/components/rekam-medis/ri/pengkajian-dokter-ri/cetak-pengkajian-dokter-ri-print.blade.php
     Cetak Pengkajian Medis (Dokter) Rawat Inap — sumber node JSON pengkajianDokter.
     Label anatomi/rencana dari App\Support\Options\PengkajianDokterRiOptions (satu sumber dgn form EMR). --}}

@use('App\Support\Options\PengkajianDokterRiOptions')
@use('App\Support\RekonsiliasiObat')

<x-pdf.layout-a4-with-out-background kode="RM-03.12 · Rev.0" title="PENGKAJIAN MEDIS RAWAT INAP">

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
        $anamnesa = $pengkajian['anamnesa'] ?? [];
        $riwayatPenyakit = $anamnesa['riwayatPenyakit'] ?? [];
        $daftarRekonsiliasiObat = $anamnesa['rekonsiliasiObat'] ?? [];
        $statusRekonsiliasi = $anamnesa[RekonsiliasiObat::STATUS_KEY] ?? null;
        $daftarAnatomiDiperiksa = PengkajianDokterRiOptions::anatomiDiperiksa($pengkajian['anatomi'] ?? []);
        $penunjang = $pengkajian['hasilPemeriksaanPenunjang'] ?? [];
        $rencana = $pengkajian['rencana'] ?? [];
        $ringkasanPulang = $pengkajian['ringkasanPasienPulang'] ?? [];
        $tandaTanganDokter = $pengkajian['tandaTanganDokter'] ?? [];
        $adaAlergi = ($anamnesa['adaAlergi'] ?? '') === 'Ya';

        // Teks bebas: kosong → '-', baris baru dipertahankan (dompdf tak andal dgn white-space:pre-line).
        $teksBebas = fn($nilai) => filled($nilai) ? nl2br(e($nilai)) : '-';
        $nilaiAtauStrip = fn($nilai) => filled($nilai) ? $nilai : '-'; // dipakai di {{ }} — sudah di-escape Blade

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
                @forelse (array_filter($dataRawatInap['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [], fn($ld) => !empty($ld['drName'])) as $ld)
                    <div>
                        {{ $ld['drName'] }}
                        @if (!empty($ld['levelDokter']))
                            ({{ $ld['levelDokter'] === 'RawatGabung' ? 'Rawat Gabung' : $ld['levelDokter'] }})
                        @endif
                    </div>
                @empty
                    -
                @endforelse
            </td>
        </tr>
    </table>

    {{-- BAGIAN 1 — ANAMNESA --}}
    <div class="[page-break-inside:avoid]">
        <div class="{{ $kelasJudulBagian }}">BAGIAN 1 — ANAMNESA</div>
        <table class="w-full border-collapse text-[10px]">
            <tr>
                <td class="{{ $kelasLabel }}">Keluhan Utama</td>
                <td class="{{ $kelasNilai }}">{!! $teksBebas($anamnesa['keluhanUtama'] ?? null) !!}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Keluhan Tambahan</td>
                <td class="{{ $kelasNilai }}">{!! $teksBebas($anamnesa['keluhanTambahan'] ?? null) !!}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Riwayat Penyakit Sekarang</td>
                <td class="{{ $kelasNilai }}">{!! $teksBebas($riwayatPenyakit['sekarang'] ?? null) !!}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Riwayat Penyakit Dahulu</td>
                <td class="{{ $kelasNilai }}">{!! $teksBebas($riwayatPenyakit['dahulu'] ?? null) !!}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Riwayat Penyakit Keluarga</td>
                <td class="{{ $kelasNilai }}">{!! $teksBebas($riwayatPenyakit['keluarga'] ?? null) !!}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Alergi</td>
                <td class="{{ $kelasNilai }}">
                    @if ($adaAlergi)
                        <b>Ada</b> — {!! $teksBebas($anamnesa['jenisAlergi'] ?? null) !!}
                    @else
                        Tidak ada alergi
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- REKONSILIASI OBAT --}}
    <div class="[page-break-inside:avoid]">
        <div class="{{ $kelasJudulBagian }}">REKONSILIASI OBAT — {{ RekonsiliasiObat::teksStatus($statusRekonsiliasi) }}</div>
        <table class="w-full border-collapse text-[10px]">
            <tr>
                <td class="{{ $kelasKepalaTabel }} w-[4%] text-center">No</td>
                <td class="{{ $kelasKepalaTabel }} w-[30%]">Obat (Dosis · Rute)</td>
                <td class="{{ $kelasKepalaTabel }} w-[11%] text-center">Dibawa Ranap</td>
                <td class="{{ $kelasKepalaTabel }} w-[11%] text-center">Digunakan Ranap</td>
                <td class="{{ $kelasKepalaTabel }} w-[11%] text-center">Lanjut Pulang</td>
                <td class="{{ $kelasKepalaTabel }}">Petugas</td>
            </tr>
            @forelse ($daftarRekonsiliasiObat as $nomorObat => $obat)
                @php
                    $dosisRute = collect([$obat['dosis'] ?? null, $obat['rute'] ?? null])->filter(fn($isi) => filled($isi))->implode(' · ');
                @endphp
                <tr>
                    <td class="{{ $kelasNilai }} text-center">{{ $nomorObat + 1 }}</td>
                    <td class="{{ $kelasNilai }}">
                        <b>{{ $nilaiAtauStrip($obat['namaObat'] ?? null) }}</b>{{ $dosisRute !== '' ? ' (' . $dosisRute . ')' : '' }}
                    </td>
                    @foreach (['dibawaRanap', 'digunakanRanap', 'lanjutPulang'] as $kolomKeputusan)
                        <td class="{{ $kelasNilai }} text-center">{{ ($obat[$kolomKeputusan] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak' }}</td>
                    @endforeach
                    <td class="{{ $kelasNilai }}">
                        {{ $nilaiAtauStrip($obat['petugasRekonsiliasi'] ?? null) }}{{ filled($obat['tglRekonsiliasi'] ?? null) ? ' · ' . $obat['tglRekonsiliasi'] : '' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="{{ $kelasNilai }} text-center italic">{{ RekonsiliasiObat::teksDaftarKosong($statusRekonsiliasi) }}</td>
                </tr>
            @endforelse
        </table>
    </div>

    {{-- BAGIAN 2 — PEMERIKSAAN FISIK & ANATOMI --}}
    <div class="[page-break-inside:avoid]">
        <div class="{{ $kelasJudulBagian }}">BAGIAN 2 — PEMERIKSAAN FISIK &amp; ANATOMI</div>
        <table class="w-full border-collapse text-[10px]">
            <tr>
                <td class="{{ $kelasLabel }}">Pemeriksaan Fisik</td>
                <td colspan="2" class="{{ $kelasNilai }}">{!! $teksBebas($pengkajian['fisik'] ?? null) !!}</td>
            </tr>
            @forelse ($daftarAnatomiDiperiksa as $anatomi)
                <tr>
                    <td class="{{ $kelasLabel }}">{{ $anatomi['label'] }}</td>
                    <td class="{{ $kelasNilai }} w-[20%]">{{ $anatomi['kelainan'] }}</td>
                    <td class="{{ $kelasNilai }}">{!! filled($anatomi['deskripsi']) ? nl2br(e($anatomi['deskripsi'])) : '&nbsp;' !!}</td>
                </tr>
            @empty
                <tr>
                    <td class="{{ $kelasLabel }}">Anatomi</td>
                    <td colspan="2" class="{{ $kelasNilai }} italic">Tidak ada bagian anatomi yang diperiksa.</td>
                </tr>
            @endforelse
            <tr>
                <td class="{{ $kelasLabel }}">Status Lokalis</td>
                <td colspan="2" class="{{ $kelasNilai }}">{!! $teksBebas(data_get($pengkajian, 'statusLokalis.deskripsiGambar')) !!}</td>
            </tr>
        </table>
    </div>

    {{-- BAGIAN 3 — HASIL PEMERIKSAAN PENUNJANG --}}
    <div class="[page-break-inside:avoid]">
        <div class="{{ $kelasJudulBagian }}">BAGIAN 3 — HASIL PEMERIKSAAN PENUNJANG</div>
        <table class="w-full border-collapse text-[10px]">
            <tr>
                <td class="{{ $kelasLabel }}">Laboratorium</td>
                <td class="{{ $kelasNilai }}">{!! $teksBebas($penunjang['laboratorium'] ?? null) !!}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Radiologi</td>
                <td class="{{ $kelasNilai }}">{!! $teksBebas($penunjang['radiologi'] ?? null) !!}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Penunjang Lain</td>
                <td class="{{ $kelasNilai }}">{!! $teksBebas($penunjang['penunjangLain'] ?? null) !!}</td>
            </tr>
        </table>
    </div>

    {{-- BAGIAN 4 — DIAGNOSIS & RENCANA --}}
    <div class="[page-break-inside:avoid]">
        <div class="{{ $kelasJudulBagian }}">BAGIAN 4 — DIAGNOSIS &amp; RENCANA</div>
        <table class="w-full border-collapse text-[10px]">
            <tr>
                <td class="{{ $kelasLabel }}">Diagnosis Awal / Assessment</td>
                <td class="{{ $kelasNilai }}">{!! $teksBebas(data_get($pengkajian, 'diagnosaAssesment.diagnosaAwal')) !!}</td>
            </tr>
            @foreach (PengkajianDokterRiOptions::RENCANA as $kunciRencana => $labelRencana)
                <tr>
                    <td class="{{ $kelasLabel }}">{{ $labelRencana }}</td>
                    <td class="{{ $kelasNilai }}">{!! $teksBebas($rencana[$kunciRencana] ?? null) !!}</td>
                </tr>
            @endforeach
        </table>
    </div>

    {{-- BAGIAN 5 — RINGKASAN PASIEN PULANG --}}
    <div class="[page-break-inside:avoid]">
        <div class="{{ $kelasJudulBagian }}">BAGIAN 5 — RINGKASAN PASIEN PULANG</div>
        <table class="w-full border-collapse text-[10px]">
            <tr>
                <td class="{{ $kelasLabel }}">Kondisi Pulang</td>
                <td class="{{ $kelasNilai }}">{!! $teksBebas($ringkasanPulang['kondisiPulang'] ?? null) !!}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Instruksi Pulang</td>
                <td class="{{ $kelasNilai }}">{!! $teksBebas($ringkasanPulang['instruksiPulang'] ?? null) !!}</td>
            </tr>
            <tr>
                <td class="{{ $kelasLabel }}">Kontrol Ke</td>
                <td class="{{ $kelasNilai }}">{{ $nilaiAtauStrip($ringkasanPulang['kontrolKe'] ?? null) }}</td>
            </tr>
        </table>
    </div>

    {{-- TANDA TANGAN DOKTER — pola 3-stack (docs/ttd-pattern-pdf-print.md §3) --}}
    <table class="w-full mt-4 text-[10px] [page-break-inside:avoid]">
        <tr>
            <td>&nbsp;</td>
            <td class="w-[40%] text-center align-top">
                <div class="text-center mb-0.5">
                    {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ ($tandaTanganDokter['jamDokterPengkaji'] ?? '') ?: ($data['tglCetak'] ?? '') }}
                </div>
                <div class="text-center">Dokter Pengkaji</div>
                <div class="text-center my-1">
                    @if (!empty($data['ttdPath']))
                        <img class="h-16" src="{{ $data['ttdPath'] }}" alt="TTD Dokter Pengkaji">
                    @else
                        <div class="h-16">&nbsp;</div>
                    @endif
                </div>
                <div class="text-center">
                    <span class="inline-block min-w-[150px] border-t border-black pt-0.5 font-bold">
                        {{ ($tandaTanganDokter['dokterPengkaji'] ?? '') ?: 'Nama Terang' }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
