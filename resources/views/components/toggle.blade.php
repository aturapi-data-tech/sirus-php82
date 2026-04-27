{{-- resources/views/components/toggle.blade.php --}}
{{--
    Pemakaian:
      Mode 1 (model binding):
        <x-toggle wire:model.live="passStatus" trueValue="N" falseValue="O" label="Pasien Baru" :disabled="$isFormLocked" />
        <x-toggle wire:model.live="dataDaftarUGD.passStatus" trueValue="N" falseValue="O" label="Pasien Baru" />
        <x-toggle wire:model.live="activeStatus" trueValue="1" falseValue="0">Status Aktif</x-toggle>

      Mode 2 (per-row di table — pakai `current` + `wireClick`):
        <x-toggle :current="$row->active_record" trueValue="1" falseValue="0"
                  wireClick="toggleActive('{{ $row->emp_id }}')">Aktif</x-toggle>

    Catatan:
      - Mendukung nested array (dataDaftarXxx.field) tanpa @entangle
      - Nilai awal diambil via $current (jika diisi) atau data_get($__livewire, $wireModel)
      - Toggle via $wire.set() (Mode 1) atau $wire.{method}(...) (Mode 2)
--}}

@props([
    'trueValue' => 'Y',
    'falseValue' => 'N',
    'label' => null,
    'disabled' => false,
    'current' => null,    // override initial value (untuk Mode 2 / per-row)
    'wireClick' => null,  // alt wire:model — panggil method server saat klik (Mode 2)
])

@php
    // Ambil nama model dari wire:model
    $wireModel = $attributes->whereStartsWith('wire:model')->first();

    // Initial value: prioritas $current → wire:model lookup → falseValue
    $currentValue = null;
    if ($current !== null) {
        $currentValue = $current;
    } elseif ($wireModel && isset($__livewire)) {
        try {
            $currentValue = data_get($__livewire, $wireModel);
        } catch (\Throwable) {
        }
    }
    $currentValue ??= $falseValue;

    // Strip wire:model — dihandle sendiri via $wire.set()
    $attrs = $attributes->whereDoesntStartWith('wire:model');
@endphp

<div x-data="{
    value: @js($currentValue),
    trueValue: @js($trueValue),
    falseValue: @js($falseValue),
    disabled: @js($disabled),
    toggle() {
        if (this.disabled) return;
        this.value = (this.value == this.trueValue) ? this.falseValue : this.trueValue;
        @if ($wireModel) $wire.set('{{ $wireModel }}', this.value); @endif
        @if ($wireClick) $wire.{{ $wireClick }}; @endif
    }
}" class="flex items-center space-x-2"
    :class="{
        'cursor-pointer': !disabled,
        'cursor-not-allowed opacity-60': disabled
    }"
    @click="toggle" {{ $attrs }}>
    <div class="h-6 transition rounded-full w-11"
        :class="{
            'bg-brand': value == trueValue && !disabled,
            'bg-gray-400': value == trueValue && disabled,
            'bg-gray-300': value != trueValue && !disabled,
            'bg-gray-200': value != trueValue && disabled
        }">
        <div class="w-4 h-4 mt-1 transition transform bg-white rounded-full shadow"
            :class="{
                'translate-x-6 ml-1': value == trueValue,
                'translate-x-1': value != trueValue
            }">
        </div>
    </div>

    @if ($label)
        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300"
            :class="{ 'opacity-60': disabled }">{{ $label }}</span>
    @else
        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300"
            :class="{ 'opacity-60': disabled }">{{ $slot }}</span>
    @endif
</div>
