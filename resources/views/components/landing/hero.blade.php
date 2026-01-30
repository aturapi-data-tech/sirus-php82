@php
    $slides = [
        asset('images/landing/slide-1.png'),
        asset('images/landing/slide-2.png'),
        asset('images/landing/slide-3.png'),
        asset('images/landing/slide-4.png'),
        asset('images/landing/slide-5.png'),
        asset('images/landing/slide-6.png'),
        asset('images/landing/slide-7.png'),
        asset('images/landing/slide-8.png'),
        asset('images/landing/slide-9.png'),
    ];
@endphp

<section class="min-h-screen flex flex-col bg-white text-slate-900 dark:bg-[#0a0a0a] dark:text-slate-100">

    {{-- TOP BAR --}}
    <header class="border-b border-slate-200/70 dark:border-white/10">
        <div class="flex items-center justify-between px-6 py-4 mx-auto max-w-7xl">

            {{-- Logo (BUTTON) --}}
            <button type="button" onclick="location.href='{{ url('/') }}'" class="flex items-center gap-3">
                <img src="{{ asset('images/Logo Horizontal.png') }}" alt="RSI Madinah" class="h-10 sm:h-12 dark:hidden" />
                <img src="{{ asset('images/Logo Horizontal white.png') }}" alt="RSI Madinah"
                    class="hidden h-10 sm:h-12 dark:block" />
            </button>

            {{-- Actions --}}
            <div class="flex items-center gap-2 sm:gap-3">

                {{-- Theme Toggle (COMPONENT) --}}
                <x-theme-toggle />

                @if (Route::has('login'))
                    @auth
                        {{-- Dashboard (secondary look via override class) --}}
                        <x-secondary-button type="button" onclick="location.href='{{ url('/dashboard') }}'">
                            Dashboard
                        </x-secondary-button>
                    @else
                        {{-- Masuk --}}
                        <x-primary-button type="button" onclick="location.href='{{ route('login') }}'">
                            Masuk
                        </x-primary-button>

                        {{-- Bantuan (outline look) --}}
                        <x-secondary-button type="button" onclick="location.href='#tentang'">
                            Bantuan
                        </x-secondary-button>
                    @endauth
                @endif
            </div>
        </div>
    </header>

    {{-- HERO --}}
    <div class="grid items-center grid-cols-1 gap-10 px-6 py-12 mx-auto max-w-7xl lg:grid-cols-2 lg:py-20">

        {{-- LEFT --}}
        <div>
            <p
                class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold bg-white border rounded-full border-slate-200 dark:bg-white/5 dark:border-white/10">
                <span class="w-2 h-2 rounded-full bg-brand-lime"></span>
                Sistem Informasi Rumah Sakit
            </p>

            <h1 class="mt-5 text-4xl font-extrabold sm:text-5xl lg:text-6xl">
                Selamat Datang di
                <span class="text-brand-green whitespace-nowrap">SIRus</span>
            </h1>

            <p class="max-w-xl mt-5 text-slate-600 dark:text-slate-300">
                Sistem Informasi Rumah Sakit dan E-Rekam Medis untuk rawat jalan, unit gawat darurat & rawat inap.
            </p>

            {{-- CTA (BUTTON SEMUA) --}}
            <div class="flex flex-wrap items-center gap-3 mt-8">
                <x-primary-button type="button"
                    onclick="location.href='{{ Route::has('login') ? route('login') : '#' }}'" class="px-5 py-3">
                    Masuk
                </x-primary-button>

                <x-secondary-button type="button" onclick="location.href='#tentang'">
                    Pelajari lebih lanjut
                </x-secondary-button>
            </div>

            {{-- Partner --}}
            <div class="pt-6 mt-10 border-t border-slate-200/70 dark:border-white/10">
                <div class="flex items-center gap-4 mt-3">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                        Powered by Aturapi Data Technology
                    </p>
                    <span class="w-px h-6 bg-slate-200 dark:bg-white/10"></span>
                    <div class="w-16 h-1 rounded-full bg-brand-lime"></div>
                </div>
            </div>
        </div>

        {{-- RIGHT (Slider) --}}
        <div x-data="{
            i: 0,
            slides: @js($slides),
            interval: null,
            next() { this.i = (this.i + 1) % this.slides.length },
            prev() { this.i = (this.i - 1 + this.slides.length) % this.slides.length },
            start() { this.interval = setInterval(() => this.next(), 3000) },
            stop() {
                clearInterval(this.interval);
                this.interval = null
            },
        }" x-init="start()" class="relative">
            <div
                class="relative overflow-hidden border rounded-2xl border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/5">
                <div class="aspect-[16/10] relative">
                    <template x-for="(src, idx) in slides" :key="idx">
                        <img x-show="i === idx" x-transition:enter="transition-opacity duration-500 ease-out"
                            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                            x-transition:leave="transition-opacity duration-500 ease-in"
                            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                            :src="src" alt="Landing image"
                            class="absolute inset-0 object-cover w-full h-full" />
                    </template>
                </div>

                {{-- Controls --}}
                <button type="button" @click="stop(); prev(); start();"
                    class="absolute p-2 transition -translate-y-1/2 rounded-full shadow left-3 top-1/2 bg-white/80 hover:bg-white dark:bg-black/40 dark:hover:bg-black/55"
                    aria-label="Previous">
                    <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M12.78 15.53a.75.75 0 0 1-1.06 0l-5-5a.75.75 0 0 1 0-1.06l5-5a.75.75 0 1 1 1.06 1.06L8.31 10l4.47 4.47a.75.75 0 0 1 0 1.06z"
                            clip-rule="evenodd" />
                    </svg>
                </button>

                <button type="button" @click="stop(); next(); start();"
                    class="absolute p-2 transition -translate-y-1/2 rounded-full shadow right-3 top-1/2 bg-white/80 hover:bg-white dark:bg-black/40 dark:hover:bg-black/55"
                    aria-label="Next">
                    <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M7.22 4.47a.75.75 0 0 1 1.06 0l5 5a.75.75 0 0 1 0 1.06l-5 5a.75.75 0 1 1-1.06-1.06L11.69 10 7.22 5.53a.75.75 0 0 1 0-1.06z"
                            clip-rule="evenodd" />
                    </svg>
                </button>

                {{-- Dots --}}
                <div class="absolute flex items-center gap-2 -translate-x-1/2 bottom-3 left-1/2">
                    <template x-for="(src, idx) in slides" :key="'dot-' + idx">
                        <button type="button" @click="stop(); i = idx; start();"
                            class="h-2.5 w-2.5 rounded-full transition"
                            :class="i === idx ? 'bg-brand-lime ring-2 ring-white/70 dark:ring-white/20' : 'bg-white/75'"
                            aria-label="Go to slide">
                        </button>
                    </template>
                </div>
            </div>

            {{-- Accent --}}
            <div class="absolute w-24 h-24 rounded-full -top-6 -right-6 bg-brand-lime blur-2xl opacity-40 -z-10"></div>
            <div class="absolute w-32 h-32 rounded-full opacity-25 -bottom-10 -left-10 bg-brand-green blur-3xl -z-10">
            </div>
        </div>
    </div>

    {{-- FOOTER --}}
    <footer class="py-6 mt-auto border-t border-slate-200/70 dark:border-white/10">
        <p class="text-xs text-center text-slate-500 dark:text-slate-400">
            © {{ date('Y') }} SIRus — RSI Madinah
        </p>
    </footer>
</section>
