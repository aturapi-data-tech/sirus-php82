{{-- resources/views/pages/components/modul-dokumen/ri/pelaporan-eso-ri/cetak-pelaporan-eso-ri-print.blade.php --}}
{{-- RM 37 — Formulir Pelaporan Efek Samping Obat (ESO).
     Susunan bagian mengikuti Form Kuning MESO BPOM 2026 (acuan di docs/referensi/),
     TAPI pilihan tidak lagi dicetak sebagai deret kotak centang: sejak penyeragaman
     cetak modul dokumen, setiap pilihan hanya menampilkan nilai yang dipilih.
     Konsekuensinya lembar ini bukan lagi salinan persis Form Kuning — bila suatu saat
     harus dikirim apa adanya ke Pusat Farmakovigilans, deret kotaknya perlu dipulihkan. --}}

<x-pdf.layout-a4-with-out-background title="FORMULIR PELAPORAN EFEK SAMPING OBAT (ESO)">

    {{-- ── IDENTITAS PASIEN ── --}}
    <x-slot name="patientData">
        @php
            $identitasPasien = $data['identitas'] ?? [];
            $alamatPasien = trim(
                ($identitasPasien['alamat'] ?? '-') .
                    (!empty($identitasPasien['rt']) ? ' RT ' . $identitasPasien['rt'] : '') .
                    (!empty($identitasPasien['rw']) ? '/RW ' . $identitasPasien['rw'] : '') .
                    (!empty($identitasPasien['desaName']) ? ', ' . $identitasPasien['desaName'] : '') .
                    (!empty($identitasPasien['kecamatanName']) ? ', ' . $identitasPasien['kecamatanName'] : ''),
            );
        @endphp
        <x-pdf.identitas-pasien :rm="$data['regNo'] ?? null" :nama="$data['regName'] ?? null"
            :jenisKelamin="$data['jenisKelamin']['jenisKelaminDesc'] ?? null" :tempatLahir="$data['tempatLahir'] ?? null"
            :tglLahir="$data['tglLahir'] ?? null" :umur="$data['thn'] ?? null" :alamat="$alamatPasien">
            <tr>
                <td style="width:22%;">Ruang / Kelas</td>
                <td style="width:3%;">:</td>
                <td>{{ trim((data_get($data, 'dataRi.bangsalDesc') ?? '') . ' / ' . (data_get($data, 'dataRi.roomDesc') ?? ''), ' /') ?: '-' }}</td>
            </tr>
            <tr>
                <td>Tgl. Masuk</td>
                <td>:</td>
                <td>{{ data_get($data, 'dataRi.entryDate') ?: '-' }}</td>
            </tr>
        </x-pdf.identitas-pasien>
    </x-slot>

    @php
        $entry = $data['entry'] ?? [];
        $form = $entry['form'] ?? [];
        $opsiLabel = $data['opsiLabel'] ?? [];

        $nilai = fn(string $path, string $kosong = '-') => filled(data_get($form, $path)) ? data_get($form, $path) : $kosong;

        $kondisiTerpilih = (array) data_get($form, 'penderita.kondisiMenyertai', []);

        // Pilihan jamak: cetak hanya kondisi yang dicentang, dipisah koma.
        // Butir "lainLain" membawa keterangan bebasnya sekalian.
        $kondisiTercetak = collect($opsiLabel['kondisiMenyertai'] ?? [])
            ->filter(fn($labelKondisi, $kunciKondisi) => in_array($kunciKondisi, $kondisiTerpilih, true))
            ->map(function ($labelKondisi, $kunciKondisi) use ($form) {
                $keteranganLain = data_get($form, 'penderita.kondisiMenyertaiLainnya');

                return $kunciKondisi === 'lainLain' && filled($keteranganLain)
                    ? $labelKondisi . ' : ' . $keteranganLain
                    : $labelKondisi;
            })
            ->implode(', ');
        $daftarObat = (array) data_get($form, 'obat', []);
    @endphp

    {{-- ══════════ PENDERITA ══════════ --}}
    <table style="width:100%; border-collapse:collapse; margin-top:6px;" class="text-[10px]">
        <tr style="background-color:#e5e5e5;">
            <td colspan="4" style="border:1px solid #000; padding:2px 4px; font-weight:bold;">PENDERITA</td>
        </tr>
        <tr>
            <td style="border:1px solid #000; padding:2px 4px; width:28%;">Nama (Singkatan) :
                <strong>{{ $nilai('penderita.namaSingkatan') }}</strong>
            </td>
            <td style="border:1px solid #000; padding:2px 4px; width:18%;">Umur : {{ $nilai('penderita.umur') }}</td>
            <td style="border:1px solid #000; padding:2px 4px; width:18%;">Suku : {{ $nilai('penderita.suku') }}</td>
            <td style="border:1px solid #000; padding:2px 4px;">Berat Badan : {{ $nilai('penderita.beratBadan') }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #000; padding:2px 4px;" colspan="2">Alamat : {{ $nilai('penderita.alamat') }}</td>
            <td style="border:1px solid #000; padding:2px 4px;">Pekerjaan : {{ $nilai('penderita.pekerjaan') }}</td>
            <td style="border:1px solid #000; padding:2px 4px;">Tgl. MRS : {{ $nilai('penderita.tglMrs') }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #000; padding:2px 4px; vertical-align:top;">
                <strong>Kelamin :</strong><br>
                {{ $nilai('penderita.kelamin') }}
                @if (data_get($form, 'penderita.kelamin') === 'Wanita')
                    <br><span style="padding-left:12px;">{{ $nilai('penderita.statusKehamilan') }}</span>
                @endif
            </td>
            <td style="border:1px solid #000; padding:2px 4px; vertical-align:top;" colspan="2">
                <strong>Penyakit Utama :</strong><br>
                {!! nl2br(e($nilai('penderita.penyakitUtama', ' '))) !!}
            </td>
            <td style="border:1px solid #000; padding:2px 4px; vertical-align:top;">
                <strong>Kesudahan Penyakit Utama :</strong><br>
                {{ $nilai('penderita.kesudahanPenyakitUtama') }}
            </td>
        </tr>
        <tr>
            <td style="border:1px solid #000; padding:2px 4px;" colspan="4">
                <strong>Penyakit / Kondisi Lain yang Menyertai :</strong><br>
                {{ $kondisiTercetak ?: '-' }}
            </td>
        </tr>
    </table>

    {{-- ══════════ EFEK SAMPING OBAT ══════════ --}}
    <table style="width:100%; border-collapse:collapse; margin-top:6px;" class="text-[10px]">
        <tr style="background-color:#e5e5e5;">
            <td colspan="3" style="border:1px solid #000; padding:2px 4px; font-weight:bold;">EFEK SAMPING OBAT</td>
        </tr>
        <tr>
            <td style="border:1px solid #000; padding:2px 4px; vertical-align:top; width:42%;">
                <strong>Bentuk / Manifestasi ESO yang Terjadi / Keluhan Lain :</strong><br>
                {!! nl2br(e($nilai('eso.manifestasi', ' '))) !!}
            </td>
            <td style="border:1px solid #000; padding:2px 4px; vertical-align:top; width:26%;">
                <strong>Masalah pada Mutu / Kualitas Produk Obat :</strong><br>
                {!! nl2br(e($nilai('eso.masalahMutuProduk', ' '))) !!}
                <br><br>
                <strong>Saat / Tanggal Mula Terjadi :</strong><br>
                {{ $nilai('eso.tglMulaTerjadi') }}
            </td>
            <td style="border:1px solid #000; padding:2px 4px; vertical-align:top;">
                <strong>Kesudahan ESO :</strong><br>
                Tanggal : {{ $nilai('eso.tglKesudahanEso') }}<br>
                {{ $nilai('eso.kesudahanEso') }}
            </td>
        </tr>
        <tr>
            <td style="border:1px solid #000; padding:2px 4px;" colspan="3">
                <strong>Riwayat ESO yang Pernah Dialami :</strong>
                {!! nl2br(e($nilai('eso.riwayatEso', ' '))) !!}
            </td>
        </tr>
    </table>

    {{-- ══════════ OBAT ══════════ --}}
    <table style="width:100%; border-collapse:collapse; margin-top:6px;" class="text-[9px]">
        <tr style="background-color:#e5e5e5;">
            <td colspan="11" style="border:1px solid #000; padding:2px 4px; font-weight:bold;">OBAT</td>
        </tr>
        <tr style="background-color:#f2f2f2;">
            <th style="border:1px solid #000; padding:2px; width:4%;">No</th>
            <th style="border:1px solid #000; padding:2px; width:20%;">Nama<br>(Dagang / Generik / Industri Farmasi)</th>
            <th style="border:1px solid #000; padding:2px; width:9%;">Bentuk<br>Sediaan</th>
            <th style="border:1px solid #000; padding:2px; width:6%;">Obat<br>JKN</th>
            <th style="border:1px solid #000; padding:2px; width:8%;">No. Bets</th>
            <th style="border:1px solid #000; padding:2px; width:7%;">Obat<br>Dicurigai</th>
            <th style="border:1px solid #000; padding:2px; width:8%;">Cara</th>
            <th style="border:1px solid #000; padding:2px; width:9%;">Dosis /<br>Waktu</th>
            <th style="border:1px solid #000; padding:2px; width:9%;">Tgl.<br>Mula</th>
            <th style="border:1px solid #000; padding:2px; width:9%;">Tgl.<br>Akhir</th>
            <th style="border:1px solid #000; padding:2px;">Indikasi<br>Penggunaan</th>
        </tr>
        @forelse ($daftarObat as $indexObat => $barisObat)
            <tr>
                <td style="border:1px solid #000; padding:2px; text-align:center;">{{ $indexObat + 1 }}</td>
                <td style="border:1px solid #000; padding:2px;">{{ $barisObat['namaObat'] ?: '-' }}</td>
                <td style="border:1px solid #000; padding:2px;">{{ $barisObat['bentukSediaan'] ?: '-' }}</td>
                <td style="border:1px solid #000; padding:2px; text-align:center;">
                    {{ filled($barisObat['obatJkn'] ?? null) ? $barisObat['obatJkn'] : 'Tidak' }}
                </td>
                <td style="border:1px solid #000; padding:2px;">{{ $barisObat['noBets'] ?: '-' }}</td>
                <td style="border:1px solid #000; padding:2px; text-align:center;">
                    {{ filled($barisObat['dicurigai'] ?? null) ? $barisObat['dicurigai'] : 'Tidak' }}
                </td>
                <td style="border:1px solid #000; padding:2px;">{{ $barisObat['cara'] ?: '-' }}</td>
                <td style="border:1px solid #000; padding:2px;">{{ $barisObat['dosisWaktu'] ?: '-' }}</td>
                <td style="border:1px solid #000; padding:2px;">{{ $barisObat['tglMula'] ?: '-' }}</td>
                <td style="border:1px solid #000; padding:2px;">{{ $barisObat['tglAkhir'] ?: '-' }}</td>
                <td style="border:1px solid #000; padding:2px;">{{ $barisObat['indikasi'] ?: '-' }}</td>
            </tr>
        @empty
            <tr>
                <td style="border:1px solid #000; padding:2px;" colspan="11">&nbsp;</td>
            </tr>
        @endforelse
    </table>

    {{-- ══════════ PENUTUP ══════════ --}}
    <table style="width:100%; border-collapse:collapse; margin-top:6px;" class="text-[10px]">
        <tr>
            <td style="border:1px solid #000; padding:2px 4px; vertical-align:top; width:55%;">
                <strong>Keterangan Tambahan</strong>
                <span style="font-size:8px;">(mis. kecepatan timbulnya ESO, reaksi setelah obat dihentikan,
                    pengobatan yang diberikan untuk mengatasi ESO)</span><br>
                {!! nl2br(e($nilai('keteranganTambahan', ' '))) !!}
            </td>
            <td style="border:1px solid #000; padding:2px 4px; vertical-align:top;">
                <strong>Data Laboratorium (bila ada) :</strong><br>
                {!! nl2br(e($nilai('dataLaboratorium', ' '))) !!}
                <br><br>
                Tgl. Pemeriksaan : {{ $nilai('tglPemeriksaanLab') }}
            </td>
        </tr>
    </table>

    {{-- ══════════ PENGIRIM + TTD ══════════ --}}
    <table style="width:100%; border-collapse:collapse; margin-top:6px;" class="text-[10px]">
        <tr>
            <td style="border:1px solid #000; padding:4px; vertical-align:top; width:55%;">
                <strong>PENGIRIM :</strong>
                <table style="width:100%; margin-top:2px;" class="text-[10px]">
                    @foreach ([['Nama', 'pengirim.nama'], ['Keahlian', 'pengirim.keahlian'], ['Instansi', 'pengirim.instansi'], ['Alamat', 'pengirim.alamat'], ['Nomor Telepon', 'pengirim.telepon']] as [$labelPengirim, $pathPengirim])
                        <tr>
                            <td style="width:32%;">{{ $labelPengirim }}</td>
                            <td style="width:3%;">:</td>
                            <td>{{ $nilai($pathPengirim) }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
            <td style="border:1px solid #000; padding:4px; text-align:center; vertical-align:top;">
                {{ data_get($data, 'identitasRs.int_city') ?: 'Tulungagung' }},
                {{ data_get($form, 'ttd.petugasDate') ?: ($data['tglCetak'] ?? '') }}<br>
                Tanda Tangan Pelapor
                <div style="height:64px;">
                    @if (!empty($data['ttdPetugasPath']))
                        <img src="{{ $data['ttdPetugasPath'] }}" style="height:56px;" alt="TTD Pelapor">
                    @else
                        &nbsp;
                    @endif
                </div>
                <span style="border-top:1px solid #000; padding:0 18px;">
                    {{ data_get($form, 'ttd.petugasName') ?: '(Nama terang & tanda tangan)' }}
                </span>
            </td>
        </tr>
    </table>

    <p class="text-[8px]" style="margin-top:4px;">
        RAHASIA &middot; MONITORING EFEK SAMPING OBAT NASIONAL &middot; diserahkan kepada Pusat
        Farmakovigilans/MESO Nasional, Badan POM RI &middot; dapat juga dilaporkan lewat
        https://e-meso.pom.go.id/
    </p>

</x-pdf.layout-a4-with-out-background>
