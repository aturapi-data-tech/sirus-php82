@props([
    'trueValue' => 'Y',
    'falseValue' => 'N',
    'label' => null,
])

<div x-data="{
    value: @entangle($attributes->wire('model')).live,
    trueValue: @js($trueValue),
    falseValue: @js($falseValue),
    toggle() {
        this.value = (this.value === this.trueValue) ?
            this.falseValue :
            this.trueValue
    }
}" class="flex items-center space-x-2 cursor-pointer" @click="toggle">
    <div class="h-6 transition rounded-full w-11" :class="value === trueValue ? 'bg-brand' : 'bg-gray-300'">
        <div class="w-4 h-4 mt-1 transition transform bg-white rounded-full shadow"
            :class="value === trueValue ? 'translate-x-6 ml-1' : 'translate-x-1'"></div>
    </div>

    @if ($label)
        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
    @else
        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $slot }}</span>
    @endif
</div>
