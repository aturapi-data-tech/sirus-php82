<div class="w-full mb-1">

    @if (auth()->user()->hasRole('Dokter'))
        @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.pengkajian-perawatan-tab-dokter-view')
    @else
        @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.pengkajian-perawatan-tab-perawat-view')
    @endif

    {{-- Include tab-tab anamnesa --}}
    @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.riwayat-penyakit-sekarang-tab')
    @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.riwayat-penyakit-dahulu-tab')
</div>
