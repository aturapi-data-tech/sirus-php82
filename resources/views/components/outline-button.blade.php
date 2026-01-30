<button
    {{ $attributes->class([
        'inline-flex items-center justify-center',
        'px-6 py-2.5 rounded-xl',
        'text-sm font-semibold',
        'transition',
    
        'text-brand-green border border-brand-green/30',
        'hover:bg-brand-green/10',
        'focus:outline-none focus:ring-2 focus:ring-brand-lime',
    ]) }}>
    {{ $slot }}
</button>
