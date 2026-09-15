@props([
    // Kunci entri → argumen method: editEntry('kunci'). Nilai apa adanya, di-escape sekali.
    'kunci' => '',
    // Entri sudah final (TTD petugas / terkunci).
    'final' => false,
    // Seluruh form read-only ($isFormLocked): tombol ubah & kelompok berisiko disembunyikan.
    'terkunci' => false,
    // Syarat tambahan Lanjutkan, Buka Kunci & Hapus (mis. entri lama tanpa id) — false = disembunyikan.
    'bisaDiubah' => true,

    // ══ Nama method Livewire — string kosong "" = tombolnya tidak ada (null jatuh ke default) ══
    'lanjut' => 'editEntry',
    'lihat' => 'viewEntry',
    'cetak' => 'cetak',
    'bukaKunci' => 'bukaKunci',
    'hapus' => 'hapus',
    // Argumen pertama tambahan untuk Buka Kunci & Hapus (Case Manager: 'formA' → hapusForm('formA','kunci')).
    'argumenAwal' => null,

    // ══ Variasi perilaku ══
    // true → Cetak hanya untuk entri final (dokumen legal: consent, penolakan, dll.).
    'cetakHanyaFinal' => false,
    // true → Hapus hanya untuk entri draft (Form Pindah Antar Ruang).
    'hapusHanyaDraft' => false,

    // ══ Teks ══
    'judulLanjut' => 'Lanjutkan mengisi entri ini',
    'judulLihat' => 'Lihat detail (read-only)',
    'judulBukaKunci' => 'Buka Kunci',
    'pesanBukaKunci' => 'TTD petugas akan dicabut & entri kembali menjadi draft untuk dikoreksi. Lanjutkan?',
    'konfirmasiHapus' => 'Yakin hapus entri ini?',
])

@php
    $user = auth()->user();
    $tampilLanjut = $lanjut && !$final && !$terkunci && $bisaDiubah;
    $bolehBukaKunci = $bukaKunci && $final && $bisaDiubah && $user?->can('dokumen.bukaKunci');
    $bolehHapus = $hapus && $bisaDiubah && !($hapusHanyaDraft && $final) && $user?->can('dokumen.hapus');
    $tampilRisiko = !$terkunci && ($bolehBukaKunci || $bolehHapus);
    $ikonGembok = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-8 4h10a2 2 0 012 2v5a2 2 0 01-2 2H8a2 2 0 01-2-2v-5a2 2 0 012-2z" /></svg>';
@endphp

{{-- Sel Aksi baris tabel daftar modul dokumen (docs/modul-dokumen-ri-pattern.md §2a "Tabel daftar"):
     [Lanjutkan Pengisian (draft) · slot · Lihat (final) · Cetak]  │  [Buka Kunci (final) · Hapus]
     Kelompok kanan hanya dirender bila ada tombol yang boleh tampil (hak Gate dokumen.bukaKunci /
     dokumen.hapus) — tak menyisakan garis kosong. Hapus selalu paling kanan, Buka Kunci kuning.
     Slot bawaan = tombol khusus modul di kelompok kiri (mis. TTD Petugas menyusul, TTD Saya).
     Atribut wire:* ditulis LITERAL (wire:click="m('{{ kunci }}')"), jangan binding :wire:click —
     binding di-escape Blade lalu di-escape lagi oleh lihat/cetak/hapus-button → wire:target rusak. --}}
<div class="flex items-center justify-end gap-2">
    <div class="flex items-center justify-center gap-2">
        @if ($tampilLanjut)
            <x-primary-button type="button" wire:click="{{ $lanjut }}('{{ $kunci }}')" wire:loading.attr="disabled"
                wire:target="{{ $lanjut }}('{{ $kunci }}')" class="gap-1.5 whitespace-nowrap" :title="$judulLanjut">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Lanjutkan Pengisian
            </x-primary-button>
        @endif
        {{ $slot }}
        @if ($lihat && $final)
            <x-lihat-button wire:click="{{ $lihat }}('{{ $kunci }}')" :title="$judulLihat" />
        @endif
        @if ($cetak && ($final || !$cetakHanyaFinal))
            <x-cetak-button wire:click="{{ $cetak }}('{{ $kunci }}')" title="Cetak" />
        @endif
    </div>

    @if ($tampilRisiko)
        <div class="flex items-center gap-2 pl-3 ml-1 border-l border-hairline dark:border-gray-700">
            {{-- argumenAwal dipisah per cabang: tanda kutip di dalam nilai atribut komponen memutus parser,
                 dan confirm-button mencetak action ke JS lewat echo — argumen harus literal di template. --}}
            @if ($bolehBukaKunci)
                @if (filled($argumenAwal))
                    <x-confirm-button variant="warning-soft" action="{{ $bukaKunci }}('{{ $argumenAwal }}','{{ $kunci }}')"
                        :title="$judulBukaKunci" :message="$pesanBukaKunci" confirmText="Ya, Buka Kunci" class="gap-1.5 whitespace-nowrap">
                        {!! $ikonGembok !!} Buka Kunci
                    </x-confirm-button>
                @else
                    <x-confirm-button variant="warning-soft" action="{{ $bukaKunci }}('{{ $kunci }}')"
                        :title="$judulBukaKunci" :message="$pesanBukaKunci" confirmText="Ya, Buka Kunci" class="gap-1.5 whitespace-nowrap">
                        {!! $ikonGembok !!} Buka Kunci
                    </x-confirm-button>
                @endif
            @endif
            @if ($bolehHapus)
                @if (filled($argumenAwal))
                    <x-hapus-button wire:click.prevent="{{ $hapus }}('{{ $argumenAwal }}','{{ $kunci }}')" :confirm="$konfirmasiHapus" />
                @else
                    <x-hapus-button wire:click.prevent="{{ $hapus }}('{{ $kunci }}')" :confirm="$konfirmasiHapus" />
                @endif
            @endif
        </div>
    @endif
</div>
