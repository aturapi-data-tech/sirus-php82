{{-- resources/views/pages/components/modul-dokumen/ri/pengkajian-awal-ginekologi-ri/cetak-pengkajian-awal-ginekologi-ri-print.blade.php --}}

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

    {{-- 1. Data Pengkajian --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">1. DATA PENGKAJIAN</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Tgl / Jam Pengkajian</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('tglJamPengkajian') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Cara Masuk</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('caraMasuk') }}{{ filled($form['caraMasukRujukan'] ?? null) ? ' — ' . e($form['caraMasukRujukan']) : '' }}</td></tr>
    </table>

    {{-- 2 & 3. Sosial --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">2. DATA SOSIAL PASIEN & SUAMI/PENANGGUNG JAWAB</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Pekerjaan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('pekerjaan') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Pendidikan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('pendidikan') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Agama</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('agama') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Suku Bangsa</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('suku') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Psiko-sosio-spiritual</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('psikososial') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Ekonomi</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('ekonomi') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Nama Suami/PJ</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('namaSuami') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Umur</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('umurSuami') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Pekerjaan Suami</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('pekerjaanSuami') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Pendidikan Suami</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('pendidikanSuami') }}</td></tr>
    </table>

    {{-- 4. Riwayat --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">4. RIWAYAT</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Alergi Obat</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('alergiObat') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Riwayat Obat</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('riwayatObat') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Penyakit Penting</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $penyakit ?: '-' }}</td></tr>
    </table>

    {{-- 5. Riwayat Ginekologi --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">5. RIWAYAT GINEKOLOGI</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">HPHT</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('hpht') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Menarche</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('menarcheUmur') }} th</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Menopause</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('menopause') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Kontrasepsi</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('kontrasepsi') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Menikah</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('menikahKali') }} kali, lama {{ $nilaiForm('menikahLama') }} th</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Anak Hidup / Mati</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('anakHidup') }} / {{ $nilaiForm('anakMati') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Umur Anak Terkecil</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('anakTerkecilUmur') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Riwayat Haid</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('riwayatHaid') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Riwayat Keputihan</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('riwayatKeputihan') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Riwayat Persalinan Lalu</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('riwayatPersalinanLalu') }}</td></tr>
    </table>

    {{-- 6. Keluhan --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">6. KELUHAN</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Keluhan Utama</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('keluhanUtama') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Riwayat Penyakit Sekarang</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('riwayatPenyakitSekarang') }}</td></tr>
    </table>

    {{-- 7. Status Umum / TTV --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">7. STATUS UMUM & TANDA VITAL</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Keadaan Umum</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('keadaanUmum') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">TD</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ filled($form['sistolik'] ?? null) || filled($form['diastolik'] ?? null) ? e(($form['sistolik'] ?? '-') . '/' . ($form['diastolik'] ?? '-')) : $nilaiForm('td') }} mmHg</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Nadi / RR</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('nadi') }} / {{ $nilaiForm('respirasi') }} x/mnt</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Suhu (R/Ax)</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('suhuRectal') }} / {{ $nilaiForm('suhuAxiler') }} °C</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Conjungtiva / Edema</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('conjungtiva') }} / {{ $nilaiForm('edema') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Cor / Pulmo</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('cor') }} / {{ $nilaiForm('pulmo') }}</td></tr>
    </table>

    {{-- 8. Pemeriksaan Dalam --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">8. PEMERIKSAAN DALAM</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Jenis Pemeriksaan</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('jenisPemeriksaan') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Vulva / Vagina</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('vulvaVagina') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Corpus Uteri</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('corpusUteri') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Portio</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('portio') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Adnexa Kanan / Kiri</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('adnexaKanan') }} / {{ $nilaiForm('adnexaKiri') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Cavum Douglasi</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('cavumDouglasi') }}</td></tr>
    </table>

    {{-- 9. Skrining --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">9. SKRINING (PP 1.2)</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Skala Nyeri</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('skalaNyeri') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Risiko Jatuh</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('risikoJatuh') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Skrining Gizi</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('skriningGizi') }}</td><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Pengkajian Fungsional</td><td class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('pengkajianFungsional') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Kebutuhan Edukasi</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('kebutuhanEdukasi') }}</td></tr>
    </table>

    {{-- 10. Status Lokalis (Dokter) --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">10. STATUS LOKALIS (DOKTER)</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Abdomen</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('abdomen') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Genitalia</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('genitalia') }}</td></tr>
    </table>

    {{-- 11. Diagnosa & Rencana --}}
    <div class="text-[11px] font-bold bg-[#eef2ee] px-1.5 py-[3px] border border-[#999] mt-1.5">11. DIAGNOSA & RENCANA</div>
    <table class="w-full border-collapse text-[10px]">
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Diagnosa</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('diagnosa') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Rencana Tindakan/Terapi</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('rencanaTindakan') }}</td></tr>
        <tr><td class="w-[22%] text-[#333] bg-[#f7f7f7] border border-[#999] px-[5px] py-[2px] align-top">Discharge Planning</td><td colspan="3" class="border border-[#999] px-[5px] py-[2px] align-top">{{ $nilaiForm('dischargePlanning') }}</td></tr>
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
