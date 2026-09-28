{{--
    Tombol bantuan topbar — membuka modal Panduan Penulisan Rekam Medis
    (singkatan & simbol boleh/dilarang, SPO 382 + SK 006). Modalnya dipasang sekali di layouts/app.
--}}
<button type="button" x-data x-on:click="$dispatch('open-modal', { name: 'panduan-penulisan-rm' })"
    class="w-9 h-9 flex items-center justify-center rounded-full border
           border-[#e3e3e0] dark:border-[#3E3E3A]
           bg-white dark:bg-[#161615]
           hover:bg-surface-soft dark:hover:bg-gray-700
           transition"
    aria-label="Panduan penulisan rekam medis" title="Panduan penulisan rekam medis: singkatan & simbol">
    <svg class="w-5 h-5 text-[#1b1b18] dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
</button>
