@props([
    'type' => 'submit',
])

<button type="{{ $type }}"
    {{ $attributes->class([
        // layout
        'inline-flex items-center justify-center',
        'px-6 py-2.5 rounded-xl',
        'text-sm font-semibold tracking-wide',
        'transition ease-in-out duration-150',
    
        // light mode
        'text-white bg-brand-green',
        'hover:bg-brand-green/90',
        'active:bg-brand-green',
        'focus:outline-none focus:ring-2 focus:ring-brand-lime',
        'focus:ring-offset-2 focus:ring-offset-white',
    
        // dark mode
        'dark:text-slate-900',
        'dark:bg-brand-lime',
        'dark:hover:bg-brand-lime/90',
        'dark:active:bg-brand-lime',
        'dark:focus:ring-brand-green',
        'dark:focus:ring-offset-[#0a0a0a]',
    ]) }}>
    {{ $slot }}
</button>
