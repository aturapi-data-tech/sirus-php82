{{-- resources/views/pages/components/manajemen/cetak-slip-gaji/cetak-slip-gaji-print.blade.php --}}
{{-- Template print: Slip Gaji Dokter --}}
{{-- Cetak SATUAN. Memakai x-pdf.layout-kwitansi apa adanya: kop ber-logo +
     judul sejajar, lalu isi lewat slot. Badan slipnya ada di partial isi-slip
     supaya sama persis dengan cetak massal.

     SATU LEMBAR saja — model dua lembar berdampingan (Arsip & Dokter) warisan
     workbook Excel ditinggalkan. Kalau butuh salinan untuk arsip, cetak dua kali.

     Ukuran teks memakai skala Tailwind: text-base (16px) untuk isi, text-sm
     (14px) untuk catatan kecil, text-lg (18px) untuk Gaji Diterima. Sejak jadi
     satu lembar A4, isinya hanya memakai bagian atas kertas sehingga ruangnya
     masih longgar untuk ukuran ini. --}}

<x-pdf.layout-kwitansi title="Slip Gaji Dokter">
    @include('pages.components.manajemen.cetak-slip-gaji.isi-slip')
</x-pdf.layout-kwitansi>
