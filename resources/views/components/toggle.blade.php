@props([
    'trueValue' => 'Y',
    'falseValue' => 'N',
    'label' => null,
    'disabled' => false, // Tambahin ini
])

<div x-data="{
    value: @entangle($attributes->wire('model')).live,
    trueValue: @js($trueValue),
    falseValue: @js($falseValue),
    disabled: @js($disabled),
    toggle() {
        if (!this.disabled) {
            this.value = (this.value === this.trueValue) ?
                this.falseValue :
                this.trueValue
        }
    }
}" class="flex items-center space-x-2"
    :class="{
        'cursor-pointer': !disabled,
        'cursor-not-allowed opacity-60': disabled
    }" @click="toggle">

    <div class="h-6 transition rounded-full w-11"
        :class="{
            'bg-brand': value === trueValue && !disabled,
            'bg-gray-400': value === trueValue && disabled,
            'bg-gray-300': value !== trueValue && !disabled,
            'bg-gray-200': value !== trueValue && disabled
        }">
        <div class="w-4 h-4 mt-1 transition transform bg-white rounded-full shadow"
            :class="{
                'translate-x-6 ml-1': value === trueValue,
                'translate-x-1': value !== trueValue
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
