{{-- resources/views/components/signature-result.blade.php --}}
{{--
    Komponen tampilan TTD tersimpan + tombol hapus.

    Props:
      - signature  (string) : base64 dataURL dari canvas
      - date       (string) : tanggal TTD (format d/m/Y H:i:s)
      - label      (string) : label opsional di atas gambar
      - disabled   (bool)   : sembunyikan tombol hapus (mode read-only)
      - wireMethod (string) : method Livewire untuk hapus/reset TTD
                              contoh: wireMethod="clearSignature"

    Pemakaian:
      <x-signature-result
          :signature="$consent['signature']"
          :date="$consent['signatureDate']"
          :disabled="$isFormLocked"
          wireMethod="clearSignature" />
--}}

@props([
    'signature' => '',
    'date' => '',
    'label' => '',
    'disabled' => false,
    'wireMethod' => 'clearSignature',
])

<div class="text-center">
    @if ($label)
        <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ $label }}</p>
    @endif

    @if ($date)
        <p class="text-xs text-gray-500 mb-2">Ditandatangani: {{ $date }}</p>
    @endif

    <div class="border border-gray-200 rounded-xl overflow-hidden dark:border-gray-700 bg-white">
        <img src="{{ $signature }}" alt="Tanda Tangan" class="mx-auto max-h-40 object-contain p-2" />
    </div>

    @if (!$disabled)
        <x-secondary-button wire:click="{{ $wireMethod }}" type="button" class="mt-3 text-xs gap-1">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Hapus &amp; Ulangi TTD
        </x-secondary-button>
    @endif
</div>
