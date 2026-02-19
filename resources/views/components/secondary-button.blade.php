<button
    {{ $attributes->class([
        // layout
        'inline-flex items-center justify-center',
        'px-6 py-2.5 rounded-xl',
        'text-sm font-semibold',
        'transition ease-in-out duration-150',
    
        // light mode
        'text-slate-700 bg-slate-200',
        'hover:bg-slate-300 hover:text-slate-900',
        'focus:outline-none focus:ring-2 focus:ring-slate-300',
        'focus:ring-offset-2 focus:ring-offset-white',
    
        // dark mode
        'dark:text-slate-200',
        'dark:bg-white/10',
        'dark:hover:bg-white/20',
        'dark:focus:ring-white/20',
        'dark:focus:ring-offset-[#0a0a0a]',
    ]) }}>
    {{ $slot }}
</button>
