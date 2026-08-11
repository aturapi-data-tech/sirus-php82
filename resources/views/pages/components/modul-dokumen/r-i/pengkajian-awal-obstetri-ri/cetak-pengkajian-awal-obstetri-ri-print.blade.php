{{-- resources/views/pages/components/modul-dokumen/r-i/pengkajian-awal-obstetri-ri/cetak-pengkajian-awal-obstetri-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="PENGKAJIAN AWAL OBSTETRI">

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

    {{-- 5. Status Obstetri & KB --}}
    <div class="pa-sec">5. STATUS OBSTETRI & KB</div>
    <table class="pa">
        <tr><td class="lbl">G - P - A</td><td>{{ $nilaiForm('gravida') }} - {{ $nilaiForm('para') }} - {{ $nilaiForm('abortus') }}</td><td class="lbl">KB Terakhir</td><td>{{ $nilaiForm('kbTerakhir') }}</td></tr>
        <tr><td class="lbl">ANC</td><td>{{ $nilaiForm('anc') }}</td><td class="lbl">TT</td><td>{{ $nilaiForm('tt') }}</td></tr>
        <tr><td class="lbl">Menikah</td><td>{{ $nilaiForm('menikahKali') }} kali, lama {{ $nilaiForm('menikahLama') }} th</td><td class="lbl">TB / BB</td><td>{{ $nilaiForm('tinggiBadan') }} cm / {{ $nilaiForm('beratBadan') }} kg</td></tr>
        <tr><td class="lbl">HPHT</td><td>{{ $nilaiForm('hpht') }}</td><td class="lbl">HPL / TP</td><td>{{ $nilaiForm('hpl') }}</td></tr>
    </table>

    {{-- 6. Riwayat Persalinan Sekarang --}}
    <div class="pa-sec">6. RIWAYAT PERSALINAN SEKARANG</div>
    <table class="pa">
        <tr><td class="lbl">ANC dilakukan di</td><td>{{ $nilaiForm('ancDilakukanDi') }}</td><td class="lbl">Ketuban</td><td>{{ $nilaiForm('ketubanStatus') }}</td></tr>
        <tr><td class="lbl">His mulai</td><td>{{ $nilaiForm('hisMulai') }}</td><td class="lbl">Ketuban pecah</td><td>{{ $nilaiForm('ketubanPecah') }}</td></tr>
        <tr><td class="lbl">Darah/lendir</td><td>{{ $nilaiForm('keluarDarah') }}</td><td class="lbl">Rasa mengejan</td><td>{{ $nilaiForm('rasaMengejan') }}</td></tr>
        <tr><td class="lbl">Perawatan sebelumnya</td><td colspan="3">{{ $nilaiForm('perawatanSebelumnya') }}</td></tr>
    </table>

    {{-- 7. Status Umum / TTV --}}
    <div class="pa-sec">7. STATUS UMUM & TANDA VITAL</div>
    <table class="pa">
        <tr><td class="lbl">Keadaan Umum</td><td>{{ $nilaiForm('keadaanUmum') }}</td><td class="lbl">TD</td><td>{{ filled($form['sistolik'] ?? null) || filled($form['diastolik'] ?? null) ? e(($form['sistolik'] ?? '-') . '/' . ($form['diastolik'] ?? '-')) : $nilaiForm('td') }} mmHg</td></tr>
        <tr><td class="lbl">Nadi / RR</td><td>{{ $nilaiForm('nadi') }} / {{ $nilaiForm('respirasi') }} x/mnt</td><td class="lbl">Suhu (R/Ax)</td><td>{{ $nilaiForm('suhuRectal') }} / {{ $nilaiForm('suhuAxiler') }} °C</td></tr>
        <tr><td class="lbl">Conjungtiva / Edema</td><td>{{ $nilaiForm('conjungtiva') }} / {{ $nilaiForm('edema') }}</td><td class="lbl">Cor / Pulmo</td><td>{{ $nilaiForm('cor') }} / {{ $nilaiForm('pulmo') }}</td></tr>
    </table>

    {{-- 8 & 9. Status Obstetri + VT --}}
    <div class="pa-sec">8. STATUS OBSTETRI (LUAR) & 9. PEMERIKSAAN DALAM (VT)</div>
    <table class="pa">
        <tr><td class="lbl">TFU</td><td>{{ $nilaiForm('tfu') }} cm</td><td class="lbl">Letak Janin</td><td>{{ $nilaiForm('letakJanin') }}</td></tr>
        <tr><td class="lbl">His / DJJ</td><td>{{ $nilaiForm('his') }} / {{ $nilaiForm('djj') }} x/mnt</td><td class="lbl">TBJ</td><td>{{ $nilaiForm('tbj') }} gr</td></tr>
        <tr><td class="lbl">VT — Pembukaan</td><td>{{ $nilaiForm('vtPembukaan') }}</td><td class="lbl">Effacement</td><td>{{ $nilaiForm('vtEffacement') }}</td></tr>
        <tr><td class="lbl">Presentasi / Denominator</td><td>{{ $nilaiForm('vtPresentasi') }} / {{ $nilaiForm('vtDenominator') }}</td><td class="lbl">Ketuban / Hodge</td><td>{{ $nilaiForm('vtKetuban') }} / {{ $nilaiForm('vtHodge') }}</td></tr>
        <tr><td class="lbl">Ukuran Panggul Dalam</td><td colspan="3">{{ $nilaiForm('vtPanggul') }}</td></tr>
    </table>

    {{-- 10. Skrining --}}
    <div class="pa-sec">10. SKRINING (PP 1.2)</div>
    <table class="pa">
        <tr><td class="lbl">Skala Nyeri</td><td>{{ $nilaiForm('skalaNyeri') }}</td><td class="lbl">Risiko Jatuh</td><td>{{ $nilaiForm('risikoJatuh') }}</td></tr>
        <tr><td class="lbl">Skrining Gizi</td><td>{{ $nilaiForm('skriningGizi') }}</td><td class="lbl">Pengkajian Fungsional</td><td>{{ $nilaiForm('pengkajianFungsional') }}</td></tr>
        <tr><td class="lbl">Kebutuhan Edukasi</td><td colspan="3">{{ $nilaiForm('kebutuhanEdukasi') }}</td></tr>
    </table>

    {{-- 11 & 12. Lab, Diagnosa & Rencana --}}
    <div class="pa-sec">11. LABORATORIUM &nbsp; · &nbsp; 12. DIAGNOSA & RENCANA</div>
    <table class="pa">
        <tr><td class="lbl">Lab Darah / Urine</td><td colspan="3">{{ $nilaiForm('labDarah') }} / {{ $nilaiForm('labUrine') }}</td></tr>
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
