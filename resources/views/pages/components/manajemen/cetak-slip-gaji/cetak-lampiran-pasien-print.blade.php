{{-- resources/views/pages/components/manajemen/cetak-slip-gaji/cetak-lampiran-pasien-print.blade.php --}}
{{-- Template print: Lampiran Rincian Pasien Slip Gaji Dokter — CETAK SATUAN.

     Berkas TERPISAH dari slip, bukan halaman tambahan di belakangnya. Slip
     tetap satu lembar yang ditandatangani; lampiran ini bisa puluhan halaman
     untuk dokter poli yang ramai, dan tidak semua orang yang menerima slip
     membutuhkannya.

     Memakai layout-a4-with-out-background: dokumen berhalaman banyak, ornamen
     latar akan tercetak berulang di tiap halaman daftar.

     Slot patientData dipakai untuk identitas dokter — slot itu memang dirancang
     berdampingan dengan logo (kiri: data, kanan: identitas RS), persis tata
     letak yang diminta. Namanya saja yang menyebut pasien.

     Badan lampirannya ada di partial isi-lampiran supaya sama persis dengan
     cetak massal.

     CATATAN WAKTU CETAK: layout ini menyuntikkan seluruh build Tailwind
     (~188 KB) ke dokumen, dan dompdf mencocokkan setiap aturan CSS ke setiap
     elemen. Pada 420 baris uji, biayanya terukur 23,7 detik berbanding 3,9
     detik bila memakai lembar gaya ringkas — karena itu aksi cetaknya menaikkan
     set_time_limit. Kalau kelak lampiran gagal lagi dengan "Maximum execution
     time exceeded", di sinilah sebabnya.

     Butuh: $header, $detail, $lampiran, $grupKapita, $tanggalCetak. --}}

<x-pdf.layout-a4-with-out-background title="Lampiran Rincian Pasien — Slip Gaji Dokter" :showGaris="false">
    <x-slot name="patientData">
        @include('pages.components.manajemen.cetak-slip-gaji.identitas-lampiran')
    </x-slot>

    @include('pages.components.manajemen.cetak-slip-gaji.isi-lampiran')
</x-pdf.layout-a4-with-out-background>
