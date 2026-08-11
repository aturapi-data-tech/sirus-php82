{{-- resources/views/pages/components/modul-dokumen/r-i/pengkajian-awal-ginekologi-ri/cetak-pengkajian-awal-ginekologi-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="PENGKAJIAN AWAL GINEKOLOGI">

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
        $form = $data['form'] ?? [];
        $nilaiForm = fn(string $field) => filled($form[$field] ?? null) ? e($form[$field]) : '-';
        $penyakit = collect($form['penyakitPenting'] ?? [])->filter()->implode(', ');
        if (filled($form['penyakitLain'] ?? null)) {
            $penyakit = trim($penyakit . ($penyakit ? ', ' : '') . e($form['penyakitLain']));
        }
    @endphp

    <style>
        .pa-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:6px; }
        table.pa { width:100%; border-collapse:collapse; font-size:10px; }
        table.pa td { border:1px solid #999; padding:2px 5px; vertical-align:top; }
        table.pa td.lbl { width:22%; color:#333; background:#f7f7f7; }
    </style>

    {{-- 1. Data Pengkajian --}}
    <div class="pa-sec">1. DATA PENGKAJIAN</div>
    <table class="pa">
        <tr><td class="lbl">Tgl / Jam Pengkajian</td><td>{{ $nilaiForm('tglJamPengkajian') }}</td><td class="lbl">Cara Masuk</td><td>{{ $nilaiForm('caraMasuk') }}{{ filled($form['caraMasukRujukan'] ?? null) ? ' — ' . e($form['caraMasukRujukan']) : '' }}</td></tr>
    </table>

    {{-- 2 & 3. Sosial --}}
    <div class="pa-sec">2. DATA SOSIAL PASIEN & SUAMI/PENANGGUNG JAWAB</div>
    <table class="pa">
        <tr><td class="lbl">Pekerjaan</td><td>{{ $nilaiForm('pekerjaan') }}</td><td class="lbl">Pendidikan</td><td>{{ $nilaiForm('pendidikan') }}</td></tr>
        <tr><td class="lbl">Agama</td><td>{{ $nilaiForm('agama') }}</td><td class="lbl">Suku Bangsa</td><td>{{ $nilaiForm('suku') }}</td></tr>
        <tr><td class="lbl">Psiko-sosio-spiritual</td><td>{{ $nilaiForm('psikososial') }}</td><td class="lbl">Ekonomi</td><td>{{ $nilaiForm('ekonomi') }}</td></tr>
        <tr><td class="lbl">Nama Suami/PJ</td><td>{{ $nilaiForm('namaSuami') }}</td><td class="lbl">Umur</td><td>{{ $nilaiForm('umurSuami') }}</td></tr>
        <tr><td class="lbl">Pekerjaan Suami</td><td>{{ $nilaiForm('pekerjaanSuami') }}</td><td class="lbl">Pendidikan Suami</td><td>{{ $nilaiForm('pendidikanSuami') }}</td></tr>
    </table>

    {{-- 4. Riwayat --}}
    <div class="pa-sec">4. RIWAYAT</div>
    <table class="pa">
        <tr><td class="lbl">Alergi Obat</td><td>{{ $nilaiForm('alergiObat') }}</td><td class="lbl">Riwayat Obat</td><td>{{ $nilaiForm('riwayatObat') }}</td></tr>
        <tr><td class="lbl">Penyakit Penting</td><td colspan="3">{{ $penyakit ?: '-' }}</td></tr>
    </table>

    {{-- 5. Riwayat Ginekologi --}}
    <div class="pa-sec">5. RIWAYAT GINEKOLOGI</div>
    <table class="pa">
        <tr><td class="lbl">HPHT</td><td>{{ $nilaiForm('hpht') }}</td><td class="lbl">Menarche</td><td>{{ $nilaiForm('menarcheUmur') }} th</td></tr>
        <tr><td class="lbl">Menopause</td><td>{{ $nilaiForm('menopause') }}</td><td class="lbl">Kontrasepsi</td><td>{{ $nilaiForm('kontrasepsi') }}</td></tr>
        <tr><td class="lbl">Menikah</td><td>{{ $nilaiForm('menikahKali') }} kali, lama {{ $nilaiForm('menikahLama') }} th</td><td class="lbl">Anak Hidup / Mati</td><td>{{ $nilaiForm('anakHidup') }} / {{ $nilaiForm('anakMati') }}</td></tr>
        <tr><td class="lbl">Umur Anak Terkecil</td><td>{{ $nilaiForm('anakTerkecilUmur') }}</td><td class="lbl">Riwayat Haid</td><td>{{ $nilaiForm('riwayatHaid') }}</td></tr>
        <tr><td class="lbl">Riwayat Keputihan</td><td colspan="3">{{ $nilaiForm('riwayatKeputihan') }}</td></tr>
        <tr><td class="lbl">Riwayat Persalinan Lalu</td><td colspan="3">{{ $nilaiForm('riwayatPersalinanLalu') }}</td></tr>
    </table>

    {{-- 6. Keluhan --}}
    <div class="pa-sec">6. KELUHAN</div>
    <table class="pa">
        <tr><td class="lbl">Keluhan Utama</td><td colspan="3">{{ $nilaiForm('keluhanUtama') }}</td></tr>
        <tr><td class="lbl">Riwayat Penyakit Sekarang</td><td colspan="3">{{ $nilaiForm('riwayatPenyakitSekarang') }}</td></tr>
    </table>

    {{-- 7. Status Umum / TTV --}}
    <div class="pa-sec">7. STATUS UMUM & TANDA VITAL</div>
    <table class="pa">
        <tr><td class="lbl">Keadaan Umum</td><td>{{ $nilaiForm('keadaanUmum') }}</td><td class="lbl">TD</td><td>{{ filled($form['sistolik'] ?? null) || filled($form['diastolik'] ?? null) ? e(($form['sistolik'] ?? '-') . '/' . ($form['diastolik'] ?? '-')) : $nilaiForm('td') }} mmHg</td></tr>
        <tr><td class="lbl">Nadi / RR</td><td>{{ $nilaiForm('nadi') }} / {{ $nilaiForm('respirasi') }} x/mnt</td><td class="lbl">Suhu (R/Ax)</td><td>{{ $nilaiForm('suhuRectal') }} / {{ $nilaiForm('suhuAxiler') }} °C</td></tr>
        <tr><td class="lbl">Conjungtiva / Edema</td><td>{{ $nilaiForm('conjungtiva') }} / {{ $nilaiForm('edema') }}</td><td class="lbl">Cor / Pulmo</td><td>{{ $nilaiForm('cor') }} / {{ $nilaiForm('pulmo') }}</td></tr>
    </table>

    {{-- 8. Pemeriksaan Dalam --}}
    <div class="pa-sec">8. PEMERIKSAAN DALAM</div>
    <table class="pa">
        <tr><td class="lbl">Jenis Pemeriksaan</td><td>{{ $nilaiForm('jenisPemeriksaan') }}</td><td class="lbl">Vulva / Vagina</td><td>{{ $nilaiForm('vulvaVagina') }}</td></tr>
        <tr><td class="lbl">Corpus Uteri</td><td>{{ $nilaiForm('corpusUteri') }}</td><td class="lbl">Portio</td><td>{{ $nilaiForm('portio') }}</td></tr>
        <tr><td class="lbl">Adnexa Kanan / Kiri</td><td>{{ $nilaiForm('adnexaKanan') }} / {{ $nilaiForm('adnexaKiri') }}</td><td class="lbl">Cavum Douglasi</td><td>{{ $nilaiForm('cavumDouglasi') }}</td></tr>
    </table>

    {{-- 9. Skrining --}}
    <div class="pa-sec">9. SKRINING (PP 1.2)</div>
    <table class="pa">
        <tr><td class="lbl">Skala Nyeri</td><td>{{ $nilaiForm('skalaNyeri') }}</td><td class="lbl">Risiko Jatuh</td><td>{{ $nilaiForm('risikoJatuh') }}</td></tr>
        <tr><td class="lbl">Skrining Gizi</td><td>{{ $nilaiForm('skriningGizi') }}</td><td class="lbl">Pengkajian Fungsional</td><td>{{ $nilaiForm('pengkajianFungsional') }}</td></tr>
        <tr><td class="lbl">Kebutuhan Edukasi</td><td colspan="3">{{ $nilaiForm('kebutuhanEdukasi') }}</td></tr>
    </table>

    {{-- 10. Status Lokalis (Dokter) --}}
    <div class="pa-sec">10. STATUS LOKALIS (DOKTER)</div>
    <table class="pa">
        <tr><td class="lbl">Abdomen</td><td colspan="3">{{ $nilaiForm('abdomen') }}</td></tr>
        <tr><td class="lbl">Genitalia</td><td colspan="3">{{ $nilaiForm('genitalia') }}</td></tr>
    </table>

    {{-- 11. Diagnosa & Rencana --}}
    <div class="pa-sec">11. DIAGNOSA & RENCANA</div>
    <table class="pa">
        <tr><td class="lbl">Diagnosa</td><td colspan="3">{{ $nilaiForm('diagnosa') }}</td></tr>
        <tr><td class="lbl">Rencana Tindakan/Terapi</td><td colspan="3">{{ $nilaiForm('rencanaTindakan') }}</td></tr>
        <tr><td class="lbl">Discharge Planning</td><td colspan="3">{{ $nilaiForm('dischargePlanning') }}</td></tr>
    </table>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $form['ttdDate'] ?? ($data['tglCetak'] ?? '') }}<br>
                {{ $form['ttd'] ?? 'Bidan/Dokter' }}<br>
                @if (!empty($data['ttdPath']))
                    <img src="{{ $data['ttdPath'] }}" style="height:44px; margin:4px 0;" alt="Tanda Tangan"><br>
                @else
                    <br><br><br>
                @endif
                <span style="border-top:1px solid #000; padding:0 30px;">Tanda Tangan &amp; Nama Terang</span>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
