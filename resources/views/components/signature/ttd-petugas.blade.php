@props([
    // ══ Data TTD ══ (nama prop mengikuti key JSON EMR: $newForm['ttd'] / 'ttdCode' / 'ttdDate')
    // Nama penanda-tangan. Kosong = belum TTD → tombol stamp muncul; terisi = kartu TTD tampil.
    'ttd' => '',
    // Waktu/jam TTD; string ditampilkan apa adanya (mis. "07/07/2026 09:00:00").
    'date' => '',
    // Kode user penanda-tangan (myuser_code). Jika diisi: tampil "Kode: xxx", gambar TTD
    // user (myuser_ttd_image) ditampilkan di layar, & dipakai me-resolve gambar saat cetak.
    'code' => '',

    // ══ Kontrol kemunculan tombol ══
    // true → form terkunci / read-only: tombol TTD & Ganti-Hapus disembunyikan (lempar $isFormLocked).
    'locked' => false,
    // false → tombol disembunyikan walau form TIDAK terkunci (mis. role tak berwenang);
    //         field readonly tetap terlihat. Pasangkan cek role:
    //         :canSign="auth()->user()?->hasAnyRole(['Perawat','Admin'])".
    'canSign' => true,
    // false → sekali TTD tak bisa diubah (tombol Ganti/Hapus disembunyikan); mis. serah-terima / pengkajian.
    'allowClear' => true,

    // ══ Method Livewire di komponen induk (dipanggil via wire:click) ══
    // Nama method untuk men-stamp TTD (nama+kode+tgl user login).
    'sign' => 'ttdSaya',
    // Nama method untuk menghapus/mengganti TTD (hanya relevan saat allowClear=true).
    'clear' => 'hapusTtd',

    // ══ Tata letak ══
    // true  → dibungkus kartu bingkai (setara border-form) bertajuk $title, kolom di tengah.
    // false → tanpa bingkai, rata kiri; cocok di grid-cell / kolom serah-terima.
    'framed' => true,
    // Judul kartu bingkai — hanya dipakai saat framed=true.
    'title' => 'Tanda Tangan',
    // Judul kolom (gaya standar kolom TTD: uppercase rata tengah); kosongkan bila judul kolom sudah ada di luar komponen.
    'label' => '',

    // ══ Teks label field & tombol (gaya EMR: 2 field readonly ditumpuk) ══
    // Label field nama (mis. "Petugas Pengkaji", "Dokter Pengkaji").
    'nameLabel' => 'Petugas',
    // Label field waktu (mis. "Jam Pengkajian", "Jam TTD").
    'dateLabel' => 'Waktu TTD',
    // Teks tombol stamp.
    'signLabel' => 'TTD Saya',
    // Teks tombol hapus/ganti.
    'clearLabel' => 'Ganti / Hapus TTD',
    // Hint saat terkunci & belum TTD (mis. "Menunggu TTD Pengirim.").
    'emptyText' => 'Belum ditandatangani.',
])

@php $signed = !empty($ttd); @endphp

{{-- Komponen TTD reusable — gaya EMR (field Petugas + Waktu/Jam readonly + tombol
     "TTD Saya" yang men-stamp nama user login + kode + tgl). SATU file.
     Bingkai (framed=true) sengaja pakai <div> biasa (BUKAN tag komponen x-border-form)
     supaya bisa dibungkus per-@if tanpa memecah tag komponen — jika pakai
     <x-border-form> yang dibelah antar @if, isinya HILANG saat framed=false.
     Lihat feedback_blade_split_component_tag. --}}
@if ($framed)
    <div class="border shadow-sm border-hairline rounded-2xl bg-canvas dark:border-gray-700 dark:bg-gray-900">
        <div class="p-4">
            <div class="mb-4 text-center ds-caption-up">{{ $title }}</div>
@endif

            <div class="{{ $framed ? 'max-w-xl mx-auto' : '' }}">
                @if ($label)
                    <div class="mb-2 text-sm font-semibold tracking-wide text-center uppercase text-muted dark:text-gray-400">
                        {{ $label }}
                    </div>
                @endif

                <div class="space-y-2">
                    {{-- Gambar TTD penanda-tangan; bila user tak punya myuser_ttd_image kotak tetap tampil berisi teks (kolom tetangga tetap sejajar) --}}
                    @if ($signed)
                        <x-signature.ttd-gambar :code="$code" :name="$ttd" teksKosong="Gambar tanda tangan tidak tersedia" class="mb-1" />
                    @endif

                    {{-- Nama penanda-tangan: label + input box (readonly) --}}
                    <div>
                        <x-input-label :value="$nameLabel" />
                        <x-text-input value="{{ $signed ? $ttd : '-' }}" class="mt-1" :disabled="true" readonly />
                    </div>

                    {{-- Kode & Waktu sebagai teks (label muted + nilai di-bold); Kode di atas --}}
                    <div class="space-y-0.5 text-sm">
                        @if (!empty($code))
                            <p><span class="text-muted">Kode:</span>
                                <span class="font-semibold text-ink dark:text-gray-200">{{ $code }}</span></p>
                        @endif
                        <p><span class="text-muted">{{ $dateLabel }}:</span>
                            <span class="font-semibold text-ink dark:text-gray-200">{{ $date ?: '-' }}</span></p>
                    </div>

                    {{-- Tombol stamp / ganti (tersembunyi saat terkunci atau tak berwenang) --}}
                    @unless ($locked || !$canSign)
                        @if (!$signed)
                            <div class="pt-1">
                                <x-primary-button type="button" wire:click="{{ $sign }}" wire:loading.attr="disabled" wire:target="{{ $sign }}" class="justify-center w-full gap-1.5">
                                    <span wire:loading.remove wire:target="{{ $sign }}" class="flex items-center gap-1.5">
                                        <x-ikon nama="ttd" />
                                        {{ $signLabel }}
                                    </span>
                                    <span wire:loading wire:target="{{ $sign }}">Menyimpan...</span>
                                </x-primary-button>
                            </div>
                        @elseif ($allowClear)
                            <div class="pt-1">
                                <x-secondary-button type="button" wire:click="{{ $clear }}" wire:loading.attr="disabled" wire:target="{{ $clear }}" class="justify-center w-full gap-1">
                                    <x-ikon nama="hapus" ukuran="w-3 h-3" />
                                    {{ $clearLabel }}
                                </x-secondary-button>
                            </div>
                        @endif
                    @endunless
                </div>

                @if ($locked && !$signed && $emptyText)
                    <p class="mt-2 text-sm italic text-muted-soft">{{ $emptyText }}</p>
                @endif
            </div>

@if ($framed)
        </div>
    </div>
@endif
