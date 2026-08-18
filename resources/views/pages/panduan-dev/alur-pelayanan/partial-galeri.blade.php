{{--
    Galeri screenshot program — dipakai per seksi tutorial alur-pelayanan.

    Pemakaian:
    @include('pages.panduan-dev.alur-pelayanan.partial-galeri', ['gambarList' => [
        ['src' => 'images/panduan-dev/alur/rj-daftar/01-list.png', 'caption' => 'List Daftar RJ'],
        ...
    ]])

    Gambar disimpan per program: public/images/panduan-dev/alur/<program>/NN-nama.png
    (mis. rj-daftar/, rj-pelayanan/, ugd-daftar/ — crop tanpa chrome browser, data pasien di-blur),
    di-render full-width + caption bernomor; loading lazy supaya halaman tetap ringan.
--}}
<div class="space-y-5">
    @foreach ($gambarList as $gambar)
        <figure class="ds-card-outline" style="padding:10px; overflow:hidden">
            <img src="{{ asset($gambar['src']) }}" alt="{{ $gambar['caption'] }}" loading="lazy"
                class="w-full rounded-lg" style="border:1px solid var(--hairline)">
            <figcaption class="ds-body-sm mt-2 px-1" style="color:var(--muted)">
                <span class="font-semibold" style="color:var(--primary)">{{ $loop->iteration }}.</span>
                {{ $gambar['caption'] }}
            </figcaption>
        </figure>
    @endforeach
</div>
