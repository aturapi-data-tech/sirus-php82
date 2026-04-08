{{-- ── TAB APLICARES ─────────────────────────────────────────────────── --}}
<div x-show="tab === 'aplicares'" class="flex flex-col h-full">

    {{-- Toolbar --}}
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 dark:border-gray-800 shrink-0">
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Tampil</span>
            <x-select-input wire:model.live="aplicLimit" wire:change="loadAplicares"
                            class="!text-xs !py-1 !px-2 w-20">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </x-select-input>
            <span class="text-xs text-gray-500 dark:text-gray-400">per halaman</span>
        </div>
        <x-secondary-button wire:click="loadAplicares" wire:loading.attr="disabled"
            wire:target="loadAplicares" class="!py-1 !px-3 !text-xs">
            <x-loading size="xs" wire:loading wire:target="loadAplicares" class="mr-1" />
            <svg wire:loading.remove wire:target="loadAplicares" class="w-3 h-3 mr-1"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5"/>
            </svg>
            <span wire:loading.remove wire:target="loadAplicares">
                {{ empty($aplicaresData) ? 'Ambil Data Aplicares' : 'Perbarui Data' }}
            </span>
            <span wire:loading wire:target="loadAplicares">Mengambil data…</span>
        </x-secondary-button>
    </div>

    @if ($aplicaresError)
        <div class="px-5 py-4 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 shrink-0">
            {{ $aplicaresError }}
        </div>
    @else
        {{-- Loading state --}}
        <div wire:loading wire:target="loadAplicares,aplicaresPrev,aplicaresNext"
             class="flex-1 flex flex-col items-center justify-center text-sm text-gray-400">
            <x-loading size="md" class="block mb-2" />
            Memuat data dari Aplicares…
        </div>

        {{-- Table --}}
        <div wire:loading.remove wire:target="loadAplicares,aplicaresPrev,aplicaresNext"
             class="flex-1 overflow-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold">Kode Ruang</th>
                        <th class="px-5 py-3 text-left font-semibold">Nama Ruang</th>
                        <th class="px-5 py-3 text-center font-semibold">Kelas</th>
                        <th class="px-5 py-3 text-center font-semibold">Kapasitas</th>
                        <th class="px-5 py-3 text-center font-semibold">Tersedia</th>
                        <th class="px-5 py-3 text-center font-semibold">Pria</th>
                        <th class="px-5 py-3 text-center font-semibold">Wanita</th>
                        <th class="px-5 py-3 text-center font-semibold">Campuran</th>
                        <th class="px-5 py-3 text-center font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    @forelse ($aplicaresData as $aplic)
                        @php
                            $koderuang = $aplic['koderuang'] ?? $aplic['kode_ruang'] ?? '';
                            $kodekelas = $aplic['kodekelas'] ?? $aplic['kode_kelas'] ?? '';
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <td class="px-5 py-3 font-mono font-semibold">{{ $koderuang ?: '-' }}</td>
                            <td class="px-5 py-3">{{ $aplic['namaruang'] ?? $aplic['nama_ruang'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-mono bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                    {{ $kodekelas ?: '-' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center font-mono font-semibold">{{ $aplic['kapasitas'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center font-mono font-semibold text-emerald-600 dark:text-emerald-400">{{ $aplic['tersedia'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center font-mono text-gray-500 dark:text-gray-400">{{ $aplic['tersediapria'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center font-mono text-gray-500 dark:text-gray-400">{{ $aplic['tersediawanita'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center font-mono text-gray-500 dark:text-gray-400">{{ $aplic['tersediapriawanita'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center">
                                <x-ghost-button
                                    wire:click="hapusAplicares('{{ $kodekelas }}', '{{ $koderuang }}')"
                                    wire:confirm="Hapus ruangan {{ $koderuang }} ({{ $kodekelas }}) dari Aplicares BPJS?"
                                    wire:loading.attr="disabled"
                                    wire:target="hapusAplicares('{{ $kodekelas }}', '{{ $koderuang }}')"
                                    class="!text-red-600 hover:!bg-red-50 dark:!text-red-400 dark:hover:!bg-red-900/20 !px-3 !py-1.5 !text-xs">
                                    <x-loading size="xs" wire:loading wire:target="hapusAplicares('{{ $kodekelas }}', '{{ $koderuang }}')" />
                                    <svg wire:loading.remove wire:target="hapusAplicares('{{ $kodekelas }}', '{{ $koderuang }}')"
                                         class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Hapus
                                </x-ghost-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-16 text-center text-gray-400 dark:text-gray-500 italic text-sm">
                                Belum ada data. Klik <strong>Ambil Data Aplicares</strong> untuk memuat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if (!empty($aplicaresData))
            <div wire:loading.remove wire:target="loadAplicares,aplicaresPrev,aplicaresNext"
                 class="flex items-center justify-between px-5 py-3 border-t border-gray-100 dark:border-gray-800
                        text-xs text-gray-500 dark:text-gray-400 shrink-0">
                <span>Start: {{ $aplicStart }} — {{ $aplicStart + count($aplicaresData) - 1 }}</span>
                <div class="flex gap-2">
                    <x-secondary-button wire:click="aplicaresPrev" :disabled="$aplicStart <= 1" class="!px-3 !py-1 !text-xs">← Prev</x-secondary-button>
                    <x-secondary-button wire:click="aplicaresNext" :disabled="count($aplicaresData) < $aplicLimit" class="!px-3 !py-1 !text-xs">Next →</x-secondary-button>
                </div>
            </div>
        @endif
    @endif

</div>
