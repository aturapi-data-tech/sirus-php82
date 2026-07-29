{{-- Kotak kode formulir manual down time — dipasang berdampingan dengan kop RS
     (slot patientData layout cetak untuk formulir pertama, atau tabel kop di
     x-downtime.halaman untuk formulir berikutnya dalam bundel). --}}
@props([
    'kode' => '',
])

<table class="dt-kode">
    <tr>
        <td style="width:44%; background-color:#fafbfa;">Kode Form</td>
        <td class="dt-tebal">{{ $kode }}</td>
    </tr>
    <tr>
        <td style="background-color:#fafbfa;">Tanggal / Jam</td>
        <td>&nbsp;</td>
    </tr>
</table>
