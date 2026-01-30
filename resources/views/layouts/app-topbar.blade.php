<div class="flex items-center gap-2">
    {{-- TOP NAV (desktop only) --}}
    <nav class="items-center hidden gap-1 mr-2 lg:flex">
        {{-- Dashboard (active) --}}
        <a href="{{ route('dashboard') }}"
            class="px-3 py-2 text-sm font-medium transition-colors duration-200 rounded-md text-brand-green bg-brand-green/10 dark:text-brand-lime dark:bg-brand-lime/15">
            Dashboard
        </a>

        {{-- Transaksi --}}
        <a href="#"
            class="px-3 py-2 text-sm font-medium text-gray-700 transition-colors duration-200 rounded-md hover:bg-brand-green/10 hover:text-brand-green dark:text-gray-300 dark:hover:bg-brand-lime/15 dark:hover:text-brand-lime">
            Transaksi
        </a>

        {{-- Laporan --}}
        <a href="#"
            class="px-3 py-2 text-sm font-medium text-gray-700 transition-colors duration-200 rounded-md hover:bg-brand-green/10 hover:text-brand-green dark:text-gray-300 dark:hover:bg-brand-lime/15 dark:hover:text-brand-lime">
            Laporan
        </a>
    </nav>


    <x-theme-toggle />

    <x-dropdown align="right" width="48">
        <x-slot name="trigger">
            <button class="flex items-center">
                <div class="overflow-hidden rounded-full w-9 h-9 bg-emerald-100 dark:bg-emerald-900/30">
                    <img class="object-cover w-full h-full"
                        src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'User') }}"
                        alt="avatar">
                </div>
            </button>
        </x-slot>

        <x-slot name="content">
            <x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                    Logout
                </x-dropdown-link>
            </form>
        </x-slot>
    </x-dropdown>
</div>
