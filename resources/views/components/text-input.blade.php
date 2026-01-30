@props(['disabled' => false])

<input @disabled($disabled)
    {{ $attributes->merge([
        'class' => 'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-brand-lime focus:ring-brand-lime rounded-md shadow-sm disabled:opacity-60 disabled:cursor-not-allowed
            ',
    ]) }}>
