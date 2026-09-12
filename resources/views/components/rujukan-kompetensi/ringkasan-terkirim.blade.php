{{-- resources/views/components/rujukan-kompetensi/ringkasan-terkirim.blade.php

    Ringkasan LENGKAP rujukan yang sudah terkirim — dipakai keenam panel
    (RJ/UGD/RI × SISRUTE/FHIR) di kotak hijau "Rujukan sudah terkirim".

    Dulu kotak itu hanya memuat nomor, tanggal, tujuan, dan pengirim; isian
    lain (diagnosa, kriteria, spesialis, poli, rencana kunjungan, wilayah,
    SEP/Encounter, catatan) lenyap begitu formulir disembunyikan, padahal
    itulah yang perlu dicek petugas saat faskes tujuan menelepon. Semua
    dibaca dari $form (formRujukan tersimpan) — tidak ada isian, murni tampilan.

    Prop:
      :form            array formRujukan (termasuk 'hasil')
      jalur            'sisrute' (BPJS→SATUSEHAT) | 'fhir' (langsung SATUSEHAT)
      tanggalRujukan   string tanggal rujukan siap tampil (dd/mm/yyyy)
      :noSep           null = jalur tak memakai SEP → baris tak tampil
      encounterId      UUID Encounter SATUSEHAT kunjungan ini
--}}

@use('App\Support\Options\RujukanKompetensiOptions')

@props(['form' => [], 'jalur' => 'sisrute', 'tanggalRujukan' => '', 'noSep' => null, 'encounterId' => ''])

@php
    $hasil = $form['hasil'] ?? [];
    $isi = fn ($nilai) => filled($nilai) ? $nilai : '-';

    $diagnosa = trim(trim((string) ($form['kodeDiagnosa'] ?? '')) . ' ' . trim((string) ($form['diagnosaDesc'] ?? '')));

    $icd9 = trim((string) ($form['kriteriaIcd9'] ?? ''));
    $icd9Desc = trim((string) ($form['kriteriaIcd9Desc'] ?? ''));
    $icd9Teks = $icd9 === '' ? '' : trim($icd9 . ' ' . $icd9Desc);

    // Kriteria yang benar-benar terkirim, dibentuk sama seperti cetak surat rujukan.
    $kriteria = [];
    if ($jalur === 'fhir' && ($form['jalur'] ?? '') === 'igd') {
        $kriteria = collect($form['kriteriaIgd'] ?? [])
            ->filter(fn ($dicentang) => $dicentang === true)
            ->keys()
            ->map(fn ($linkId) => RujukanKompetensiOptions::PERTANYAAN_IGD[$linkId] ?? (string) $linkId)
            ->values()->all();
    } elseif ($jalur === 'fhir') {
        $teks = RujukanKompetensiOptions::KRITERIA_RANAP[$form['kriteriaPilih'] ?? ''] ?? '';
        $kriteria = $teks === '' ? [] : [$teks];
    } else {
        $terpilih = collect($form['kriteriaList'] ?? [])->firstWhere('linkId', $form['kriteriaPilih'] ?? null);
        $teks = trim((string) ($terpilih['text'] ?? ''));
        $kriteria = $teks === '' ? [] : [$teks . ' (linkId ' . ($form['kriteriaPilih'] ?? '') . ')'];
    }

    $wilayah = trim((string) ($form['namaKabupaten'] ?? '')) . ' (' . ($form['kodeKabupaten'] ?? '') . ') · Prov. '
        . trim((string) ($form['namaPropinsi'] ?? '')) . ' (' . ($form['kodePropinsi'] ?? '') . ')';

    $catatan = trim((string) ($jalur === 'fhir' ? ($form['deskripsi'] ?? '') : ($form['catatan'] ?? '')));

    $statusApproval = (string) ($form['statusApproval'] ?? '');
    $labelApproval = match ($statusApproval) {
        'accepted' => 'Diterima',
        'rejected' => 'Ditolak',
        default => 'Belum dijawab',
    };
    $warnaApproval = match ($statusApproval) {
        'accepted' => 'text-green-700 dark:text-green-300',
        'rejected' => 'text-red-700 dark:text-red-300',
        default => 'text-amber-700 dark:text-amber-300',
    };

    $kandidatTerpilih = $form['kandidatList'][$form['kandidatIdx'] ?? -1] ?? [];
    // Rujukan yang terbit sebelum tujuanAlamat disimpan: pakai alamat kandidat terpilih.
    $tujuanAlamat = trim((string) (($hasil['tujuanAlamat'] ?? '') ?: ($kandidatTerpilih['alamat'] ?? '')));

    $tujuanKode = collect([
        filled($hasil['tujuanPpk'] ?? '') ? 'PPK ' . $hasil['tujuanPpk'] : '',
        filled($hasil['tujuanSatuSehat'] ?? ($hasil['tujuanOrgId'] ?? '')) ? 'Org ID ' . ($hasil['tujuanSatuSehat'] ?? $hasil['tujuanOrgId']) : '',
    ])->filter()->implode(' · ');

    $judul = 'text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400 pt-2';
    $label = 'pr-3 align-top whitespace-nowrap text-muted dark:text-gray-400';
@endphp

<table class="text-gray-700 dark:text-gray-200">
    {{-- Nomor & waktu --}}
    @if ($jalur === 'sisrute')
        <tr><td class="{{ $label }}">No Rujukan BPJS</td><td class="font-mono font-semibold">{{ $isi($hasil['noRujukan'] ?? '') }}</td></tr>
    @endif
    <tr><td class="{{ $label }}">No Rujukan SATUSEHAT</td><td class="font-mono font-semibold">{{ $isi($hasil['noRujukanSatuSehat'] ?? '') }}</td></tr>
    @if ($jalur === 'fhir')
        <tr><td class="{{ $label }}">ServiceRequest</td><td class="font-mono break-all">{{ $isi($hasil['serviceRequestId'] ?? '') }}</td></tr>
        <tr><td class="{{ $label }}">CarePlan</td><td class="font-mono break-all">{{ $isi($form['carePlanId'] ?? '') }}</td></tr>
    @endif
    <tr><td class="{{ $label }}">Tanggal Rujukan</td><td class="font-semibold">{{ $isi($tanggalRujukan) }}</td></tr>
    <tr><td class="{{ $label }}">Tgl Rencana Kunjungan</td><td>{{ $isi($form['tglRencanaKunjungan'] ?? '') }}</td></tr>
    <tr><td class="{{ $label }}">Dikirim</td><td>{{ $isi($hasil['dikirimPada'] ?? '') }} oleh {{ $isi($hasil['dikirimOleh'] ?? '') }}</td></tr>

    {{-- Tujuan --}}
    <tr><td colspan="2" class="{{ $judul }}">Faskes Tujuan</td></tr>
    @if ($jalur === 'fhir')
        <tr><td class="{{ $label }}">Jalur</td><td>{{ ($form['jalur'] ?? '') === 'ranap' ? 'Rawat Inap' : 'Gawat Darurat (IGD)' }}</td></tr>
    @endif
    <tr><td class="{{ $label }}">Faskes</td><td class="font-semibold">{{ $isi($hasil['tujuanNama'] ?? '') }}</td></tr>
    <tr><td class="{{ $label }}">Kode</td><td class="font-mono">{{ $isi($tujuanKode) }}</td></tr>
    <tr><td class="{{ $label }}">Alamat</td><td>{{ $isi($tujuanAlamat) }}</td></tr>
    @if ($jalur === 'fhir')
        <tr><td class="{{ $label }}">Persetujuan Faskes</td><td class="font-semibold {{ $warnaApproval }}">{{ $labelApproval }}@if (filled($form['approvalOrgNama'] ?? '')) <span class="font-normal text-muted dark:text-gray-400">— {{ $form['approvalOrgNama'] }}</span>@endif</td></tr>
    @endif
    <tr><td class="{{ $label }}">Jejaring Wilayah</td><td>{{ $wilayah }}</td></tr>

    {{-- Klinis --}}
    <tr><td colspan="2" class="{{ $judul }}">Diagnosa &amp; Kriteria</td></tr>
    <tr><td class="{{ $label }}">Diagnosa (ICD-10)</td><td>{{ $isi($diagnosa) }}</td></tr>
    <tr>
        <td class="{{ $label }}">Kriteria Rujukan</td>
        <td>
            @forelse ($kriteria as $baris)
                <div>{{ count($kriteria) > 1 ? $loop->iteration . '. ' : '' }}{{ $baris }}</div>
            @empty
                -
            @endforelse
        </td>
    </tr>
    @if ($icd9Teks !== '')
        <tr><td class="{{ $label }}">Tindakan (ICD-9-CM)</td><td>{{ $icd9Teks }}</td></tr>
    @endif

    {{-- Layanan --}}
    <tr><td colspan="2" class="{{ $judul }}">Layanan</td></tr>
    @if ($jalur === 'sisrute')
        <tr><td class="{{ $label }}">Kode Spesialis</td><td class="font-mono">{{ $isi($form['kodeSpesialis'] ?? '') }}</td></tr>
        <tr><td class="{{ $label }}">Poli Rujukan</td><td class="font-mono">{{ filled($form['poliRujukan'] ?? '') ? $form['poliRujukan'] : $isi($form['kodeSpesialis'] ?? '') }}@if (blank($form['poliRujukan'] ?? '') && filled($form['kodeSpesialis'] ?? '')) <span class="font-sans text-muted dark:text-gray-400">(mengikuti Kode Spesialis)</span>@endif</td></tr>
        @if (filled($form['kodeSarana'] ?? ''))
            <tr><td class="{{ $label }}">Kode Sarana</td><td class="font-mono">{{ $form['kodeSarana'] }}</td></tr>
        @endif
    @else
        <tr><td class="{{ $label }}">Layanan Klinis</td><td><span class="font-mono">{{ $isi($form['specialityCode'] ?? '') }}</span>@if (filled($form['specialityDisplay'] ?? '')) — {{ $form['specialityDisplay'] }}@endif</td></tr>
        @if (filled($form['kelompokLayananKode'] ?? ''))
            <tr><td class="{{ $label }}">Kelompok Layanan</td><td><span class="font-mono">{{ $form['kelompokLayananKode'] }}</span> — {{ RujukanKompetensiOptions::KELOMPOK_LAYANAN[$form['kelompokLayananKode']] ?? '' }}</td></tr>
        @endif
        @if (filled($form['performerTypeKode'] ?? ''))
            <tr><td class="{{ $label }}">Tenaga Pelaksana</td><td><span class="font-mono">{{ $form['performerTypeKode'] }}</span> — {{ RujukanKompetensiOptions::PERFORMER_TYPE[$form['performerTypeKode']] ?? '' }}</td></tr>
        @endif
    @endif

    {{-- Identitas kiriman --}}
    <tr><td colspan="2" class="{{ $judul }}">Identitas Kiriman</td></tr>
    @if ($noSep !== null)
        <tr><td class="{{ $label }}">No. SEP</td><td class="font-mono">{{ $isi($noSep) }}</td></tr>
    @endif
    <tr><td class="{{ $label }}">Encounter SATUSEHAT</td><td class="font-mono break-all">{{ $isi($encounterId) }}</td></tr>

    {{-- Catatan --}}
    <tr><td colspan="2" class="{{ $judul }}">Catatan Rujukan</td></tr>
    <tr><td colspan="2" class="whitespace-pre-line">{{ $isi($catatan) }}</td></tr>
</table>
