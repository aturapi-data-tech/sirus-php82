@props([
    // $parameterBangsal milik KunjunganRITrait — daftar berindeks (indeks = kunci wire:model).
    'rows' => [],
    // Σ TT bangsal yang dihitung = TT total bawaan.
    'ttDihitung' => 0,
    // true bila ada TT yang diubah atau bangsal yang dikeluarkan.
    'diubah' => false,
])

@php
    $angka = fn($nilai) => number_format((float) $nilai);
    $ttDatabase = array_sum(array_column($rows, 'tt_db'));
    $jumlahDikeluarkan = collect($rows)->where('dihitung', false)->count();
@endphp

{{-- Kartu ini berada DI DALAM komponen Livewire pemanggil: wire:model / wire:click di sini
     mengarah ke properti $parameterBangsal & method toggleBangsalDihitung / resetParameterBangsal. --}}
<div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900"
    x-data="{ open: {{ $diubah ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open"
        class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl hover:bg-surface-soft dark:hover:bg-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-300">
        <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-body dark:text-gray-200">
                Parameter TT per Bangsal
                @if ($diubah)
                    <span class="ml-2 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">diubah dari master</span>
                @endif
            </div>
            <div class="text-xs text-muted dark:text-gray-400">
                TT dihitung <span class="font-medium text-blue-700 dark:text-blue-300">{{ $angka($ttDihitung) }}</span>
                dari <span class="font-medium text-body dark:text-gray-300">{{ $angka($ttDatabase) }}</span> bed di master
                @if ($jumlahDikeluarkan > 0)
                    · <span class="font-medium text-amber-700 dark:text-amber-400">{{ $jumlahDikeluarkan }} bangsal tidak dihitung BOR</span>
                @endif
            </div>
        </div>
        <span class="hidden text-xs sm:inline text-muted dark:text-gray-400" x-text="open ? 'Sembunyikan' : 'Atur'"></span>
        <svg class="w-4 h-4 transition-transform duration-200 text-muted-soft shrink-0" :class="open ? 'rotate-180' : ''"
            fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-cloak x-show="open" class="border-t border-hairline dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                        <th class="px-4 py-2 text-left">Bangsal</th>
                        <th class="px-3 py-2 text-right" title="Jumlah bed bangsal ini di master (rsmst_beds lewat kamar)">TT Master</th>
                        <th class="px-3 py-2 text-right text-blue-700 dark:text-blue-300" title="TT yang dipakai menghitung BOR / BTO / TOI">TT Dipakai</th>
                        <th class="px-3 py-2 text-left" title="Matikan untuk bangsal yang tidak dihitung BOR, mis. bed bayi atau UGD">Dihitung BOR</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $indeks => $row)
                        <tr class="border-t border-hairline-soft dark:border-gray-800 {{ $row['dihitung'] ? '' : 'bg-surface-soft/60 dark:bg-gray-800/40' }}"
                            wire:key="parameter-tt-{{ $row['bangsal_id'] !== '' ? $row['bangsal_id'] : 'tanpa-bangsal' }}">
                            <td class="px-4 py-2 font-medium {{ $row['dihitung'] ? 'text-ink dark:text-gray-100' : 'text-muted line-through dark:text-gray-500' }}">{{ $row['bangsal_name'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-muted dark:text-gray-400">{{ $angka($row['tt_db']) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                @if ((int) $row['tt'] !== (int) $row['tt_db'])
                                    <span class="mr-2 text-[10px] font-semibold text-amber-700 dark:text-amber-400">diubah</span>
                                @endif
                                <x-text-input type="number" min="0" max="9999"
                                    wire:model.live.debounce.600ms="parameterBangsal.{{ $indeks }}.tt"
                                    :disabled="!$row['dihitung']"
                                    class="!w-24 !py-1 text-right tabular-nums" />
                            </td>
                            <td class="px-3 py-2">
                                <x-toggle :current="$row['dihitung'] ? '1' : '0'" trueValue="1" falseValue="0"
                                    wireClick="toggleBangsalDihitung({{ $indeks }})">{{ $row['dihitung'] ? 'Dihitung' : 'Tidak dihitung' }}</x-toggle>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-muted-soft">Master bed belum berisi.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if (count($rows) > 0)
                    <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                        <tr class="text-sm font-bold text-ink dark:text-gray-100">
                            <td class="px-4 py-2">JUMLAH</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $angka($ttDatabase) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ $angka($ttDihitung) }}</td>
                            <td class="px-3 py-2">
                                @if ($diubah)
                                    <x-secondary-button type="button" wire:click="resetParameterBangsal" class="!py-1 text-xs">
                                        Kembalikan ke master
                                    </x-secondary-button>
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <div class="px-4 py-2 text-[10px] leading-snug text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800">
            Bawaan mengikuti master bed. Bangsal yang <strong>tidak dihitung</strong> dikeluarkan TT-nya <strong>sekaligus</strong> pasien &amp; hari rawatnya dari
            BOR / ALOS / TOI / BTO — membuang TT saja akan menaikkan BOR secara palsu. Jumlah kunjungan (Total, BPJS, UMUM) tetap menghitung semua pasien.
            Setelan diingat selama sesi login ini dan dipakai bersama tab Tahunan &amp; Multi-Tahun; tidak mengubah master.
        </div>
    </div>
</div>
