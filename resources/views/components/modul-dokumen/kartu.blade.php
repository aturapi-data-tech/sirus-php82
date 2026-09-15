@props([
    // Judul kartu. Tulis karakter asli ("&", "/"), BUKAN entity HTML — prop di-escape.
    'judul' => '',
    // Jumlah entri tersimpan → badge hijau "N {satuan}" atau kuning "Belum ada". null = tanpa badge
    // hitungan (pakai slot "badge" untuk status khusus, mis. General Consent "Sudah ditandatangani").
    'jumlah' => null,
    'satuan' => 'entri',
    // Label & method tombol pembuka modal.
    'tombol' => 'Buka Formulir',
    'buka' => 'openModal',
    // true → tombol nonaktif (mis. $disabled || !$riHdrNo).
    'nonaktif' => false,
    // false → tombol pembuka (dan slot "tombolLain") tidak ditampilkan (Surat Kematian: hanya pasien meninggal).
    'tampilTombol' => true,
])

{{-- Kartu modul dokumen di tab (docs/modul-dokumen-ri-pattern.md §2a "Kartu di tab"):
       [judul · badge · deskripsi ringkas ··················]  [Buka …]
       [slot ringkasan — dl/ul di bawah judul]
     [slot bawaan — tabel pratinjau entri terbaru; dibungkus mt-3 space-y-3, jangan beri mt-* sendiri]
     Slot "deskripsi" = teks deskripsi (otomatis x-deskripsi-ringkas + Selengkapnya).
     Slot "tombolLain" = tombol tambahan di sebelah tombol pembuka (mis. Cetak).
     Induk baris judul WAJIB min-w-0 supaya truncate menggigit dan tombol tak terdorong keluar. --}}
<div {{ $attributes->merge(['class' => 'p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700']) }}>
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex-1 min-w-0 space-y-3">
            <div class="flex items-baseline flex-1 min-w-0 gap-2">
                <h3 class="text-base font-semibold truncate shrink-0 text-ink dark:text-gray-200">{{ $judul }}</h3>
                @if ($jumlah !== null)
                    @if ($jumlah > 0)
                        <x-badge class="shrink-0 whitespace-nowrap" variant="success">{{ $jumlah }} {{ $satuan }}</x-badge>
                    @else
                        <x-badge class="shrink-0 whitespace-nowrap" variant="warning">Belum ada</x-badge>
                    @endif
                @endif
                {{ $badge ?? '' }}
                @isset($deskripsi)
                    <x-deskripsi-ringkas class="hidden text-sm sm:flex">{{ $deskripsi }}</x-deskripsi-ringkas>
                @endisset
            </div>

            {{ $ringkasan ?? '' }}
        </div>

        @if ($tampilTombol)
        <div class="flex items-center gap-2 shrink-0">
            <x-primary-button type="button" wire:click="{{ $buka }}" wire:loading.attr="disabled"
                wire:target="{{ $buka }}" :disabled="$nonaktif" class="gap-2">
                <span wire:loading.remove wire:target="{{ $buka }}" class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                    {{ $tombol }}
                </span>
                <span wire:loading wire:target="{{ $buka }}" class="flex items-center gap-1.5">
                    <x-loading class="w-4 h-4" /> Memuat...
                </span>
            </x-primary-button>
            {{ $tombolLain ?? '' }}
        </div>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="mt-3 space-y-3">
            {{ $slot }}
        </div>
    @endif
</div>
