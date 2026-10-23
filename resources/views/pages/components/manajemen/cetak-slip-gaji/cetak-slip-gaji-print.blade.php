{{-- resources/views/pages/components/manajemen/cetak-slip-gaji/cetak-slip-gaji-print.blade.php --}}
{{-- Template print: Slip Gaji Dokter --}}
{{-- Cetak SATUAN. Memakai x-pdf.layout-kwitansi apa adanya: kop ber-logo +
     judul sejajar, lalu isi lewat slot. Badan slipnya ada di partial isi-slip
     supaya sama persis dengan cetak massal.

     SATU LEMBAR saja — model dua lembar berdampingan (Arsip & Dokter) warisan
     workbook Excel ditinggalkan. Kalau butuh salinan untuk arsip, cetak dua kali.

     Dicetak di kotak seperempat A4 di tengah atas kertas (tengahA4 bawaan layout),
     jadi ukuran teksnya kecil: 9px untuk isi, 8px untuk catatan, 10px untuk
     Gaji Diterima. --}}

<x-pdf.layout-kwitansi title="Slip Gaji Dokter">
    @include('pages.components.manajemen.cetak-slip-gaji.isi-slip')
</x-pdf.layout-kwitansi>
