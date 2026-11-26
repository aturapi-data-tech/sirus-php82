{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/umum-tab.blade.php --}}
<div class="space-y-4">
    {{-- Satu tampilan saja: punya hak isi (Perawat/Admin/Casemix) → form isian, walau juga Dokter
         (mis. fisioterapis = Perawat + Dokter untuk TTD). Selain itu → tampilan baca. --}}
    @if (auth()->user()->hasAnyRole(['Perawat', 'Admin', 'Casemix']))
        @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.umum-tab-perawat-view')
    @else
        @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.umum-tab-dokter-view')
    @endif

    @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.fisik-tab')
    @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.suspek-akibat-kercelakaan-kerja-tab')

    {{-- Uji Fungsi + Pemeriksaan Penunjang sebelahan --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.uji-fungsi-tab')
        @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.penunjang-tab')
    </div>

    @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.fungsional-tab')
</div>
