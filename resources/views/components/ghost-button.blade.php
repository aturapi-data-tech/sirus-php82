<button
    {{ $attributes->class([
        'inline-flex items-center justify-center',
        'px-3 py-2 rounded-lg',
        'text-sm font-medium',
        'transition',
    
        'text-slate-600 bg-transparent',
        'hover:bg-slate-100 hover:text-slate-900',
        'focus:outline-none focus:ring-2 focus:ring-slate-200',
    ]) }}>
    {{ $slot }}
</button>
