@props([
    // Layar formulir ($this->diForm()); false = layar daftar.
    'formulir' => false,
    // Seluruh form read-only ($isFormLocked) — layar daftar: tombol Isi Formulir Baru disembunyikan.
    'terkunci' => false,
    // Mode lihat entri terkunci ($viewOnly) — keterangan "Mode lihat" + tombol Selesai Melihat.
    'lihat' => false,
    // Sedang melanjutkan entri tersimpan ($editingKey) — label simpan jadi "Simpan Perubahan".
    'mengedit' => false,
    // Tombol simpan boleh tampil. null = !terkunci && !lihat.
    'bisaSimpan' => null,
    // Label tombol simpan. null = "Simpan Perubahan" / "Simpan Draft" menurut prop mengedit.
    'labelSimpan' => null,

    // ══ Nama method Livewire — string kosong "" = tombolnya tidak ada (null jatuh ke default) ══
    'simpan' => 'saveDraft',
    'selesaiLihat' => 'cancelEdit',
    'kembali' => 'kembaliKeDaftar',
    'tutup' => 'closeModal',
    'tambah' => 'tambahEntri',
])

@php
    $bisaSimpan ??= !$terkunci && !$lihat;
    $labelSimpan ??= $mengedit ? 'Simpan Perubahan' : 'Simpan Draft';
    $adaPetunjuk = trim(strip_tags((string) $slot)) !== '';
    $ikonInfo = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
@endphp

{{-- Footer modal modul dokumen (docs/modul-dokumen-ri-pattern.md §2a/§2b) — saudara area isi ber-flex-1,
     sticky bottom-0. Dua layar:
       daftar   : [ⓘ Setiap entri berdiri sendiri …] ············ [Tutup] [Isi Formulir Baru]
       formulir : [ⓘ petunjuk modul | Mode lihat …] ··· [Kembali ke Daftar] [slot tombol] [Selesai Melihat | Simpan]
     Slot bawaan = petunjuk layar formulir (tampil bila tombol simpan tampil). Slot "tombol" = tombol
     tambahan layar formulir (Batal Edit, Cetak Catatan). Tutup hanya di layar daftar: di layar formulir
     jalan keluarnya Kembali ke Daftar supaya isian tak hilang tanpa disadari. --}}
<div {{ $attributes->merge(['class' => 'sticky bottom-0 z-10 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700']) }}>
    @if ($formulir)
        <div class="flex flex-wrap items-center justify-between gap-3">
            @if ($lihat)
                <p class="flex items-center gap-1.5 text-sm text-sky-600 dark:text-sky-400">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>Mode lihat — entri terkunci, tidak dapat diubah.</span>
                </p>
            @elseif ($bisaSimpan && $adaPetunjuk)
                <p class="flex items-center gap-1.5 text-sm text-muted dark:text-gray-400">
                    {!! $ikonInfo !!}
                    <span>{{ $slot }}</span>
                </p>
            @else
                <span></span>
            @endif

            <div class="flex flex-wrap items-center justify-end gap-3">
                @if ($kembali)
                    <x-secondary-button type="button" wire:click="{{ $kembali }}">Kembali ke Daftar</x-secondary-button>
                @endif

                {{ $tombol ?? '' }}

                @if ($lihat && $selesaiLihat)
                    <x-primary-button wire:click.prevent="{{ $selesaiLihat }}" wire:target="{{ $selesaiLihat }}"
                        wire:loading.attr="disabled" class="gap-1.5 min-w-[160px] justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Selesai Melihat
                    </x-primary-button>
                @elseif ($bisaSimpan && $simpan)
                    <x-primary-button wire:click.prevent="{{ $simpan }}" wire:loading.attr="disabled"
                        wire:target="{{ $simpan }}" class="gap-2 min-w-[160px] justify-center">
                        <span wire:loading.remove wire:target="{{ $simpan }}" class="flex items-center gap-1.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                            </svg>
                            {{ $labelSimpan }}
                        </span>
                        <span wire:loading wire:target="{{ $simpan }}"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                    </x-primary-button>
                @endif
            </div>
        </div>
    @else
        <div class="flex flex-wrap items-center justify-end gap-2">
            <p class="flex items-center gap-1.5 mr-auto text-sm text-muted dark:text-gray-400">
                {!! $ikonInfo !!}
                <span>Setiap entri berdiri sendiri — <strong>Isi Formulir Baru</strong> untuk entri baru, <strong>Lanjutkan Pengisian</strong> untuk melanjutkan draft.</span>
            </p>
            <x-secondary-button type="button" wire:click="{{ $tutup }}">Tutup</x-secondary-button>
            @if (!$terkunci && $tambah)
                <x-primary-button type="button" wire:click="{{ $tambah }}" wire:target="{{ $tambah }}"
                    wire:loading.attr="disabled" class="gap-1.5 min-w-[150px] justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Isi Formulir Baru
                </x-primary-button>
            @endif
        </div>
    @endif
</div>
