{{-- Blok identitas pasien kosong untuk formulir manual down time — dipasang di
     KOP, sebelah kiri logo RS (slot patientData layout cetak untuk formulir
     pertama, atau tabel kop di x-downtime.halaman untuk formulir berikutnya).

     Bentuknya "label : titik-titik" agar muat di kolom kop yang sempit, bukan
     tabel bergaris seperti isi formulir.

     variant "lengkap" = identitas + penjaminan (formulir yang butuh No. SEP),
     variant "ringkas" = identitas inti + poli/dokter + tanggal kunjungan. --}}
@props([
    'variant' => 'ringkas',
])

@php
    $barisRingkas = ['No. RM', 'Nama Pasien', 'Tgl Lahir / Umur', 'Poli / Ruang', 'Dokter', 'Tgl Kunjungan'];
    $barisLengkap = ['No. RM', 'Nama Pasien', 'Tgl Lahir / Umur', 'Alamat', 'NIK / No. BPJS', 'Poli / Ruang', 'Dokter', 'No. SEP / Rujukan'];
    $baris = $variant === 'lengkap' ? $barisLengkap : $barisRingkas;
@endphp

<table class="dt-identitas">
    @foreach ($baris as $label)
        <tr>
            <td class="dt-identitas-label">{{ $label }}</td>
            <td class="dt-identitas-titik">:</td>
            <td class="dt-garis">&nbsp;</td>
        </tr>
    @endforeach
    <tr>
        <td class="dt-identitas-label">Jenis Kelamin</td>
        <td class="dt-identitas-titik">:</td>
        <td>
            <span class="dt-opsi"><span class="dt-box"></span>Laki-laki</span>
            <span class="dt-opsi"><span class="dt-box"></span>Perempuan</span>
        </td>
    </tr>
    @if ($variant === 'lengkap')
        <tr>
            <td class="dt-identitas-label">Cara Bayar</td>
            <td class="dt-identitas-titik">:</td>
            <td>
                <span class="dt-opsi"><span class="dt-box"></span>Umum</span>
                <span class="dt-opsi"><span class="dt-box"></span>BPJS</span>
                <span class="dt-opsi"><span class="dt-box"></span>Lainnya</span>
            </td>
        </tr>
    @endif
</table>
