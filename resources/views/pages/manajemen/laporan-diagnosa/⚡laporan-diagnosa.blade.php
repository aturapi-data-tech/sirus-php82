<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <x-page-title
        title="Laporan Diagnosa"
        subtitle="Kumpulan laporan diagnosa ICD-10 — hitung per diagnosa & laporan SIRS RL 4.x / 5.x" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-800">
        <div class="px-6 py-10 space-y-8">

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- LAPORAN SIRS ONLINE (KEMENKES) — MORBIDITAS & MORTALITAS --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div class="max-w-3xl mx-auto">
                <div class="mb-3 flex items-center gap-2">
                    <h3 class="text-sm font-semibold tracking-wider text-muted uppercase dark:text-gray-400">
                        Laporan SIRS Online
                    </h3>
                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                        Kemenkes
                    </span>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <a href="{{ route('manajemen.laporan-diagnosa.hitung-diagnosa') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 group-hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">Hitung Jumlah Diagnosa</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Pilih ICD-10 + periode &mdash; jumlah kunjungan &amp; pasien di RJ / UGD / RI (per gender, per sub-kode)
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.sirs.ri.laporan-rl-4-1-morbiditas') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-rose-50 text-rose-700 group-hover:bg-rose-100 dark:bg-rose-900/30 dark:text-rose-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">RL 4.1 Morbiditas RI</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Tahunan &mdash; rekap morbiditas per ICD-10 × 25 kelompok umur × gender × hidup/mati
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.sirs.ri.laporan-rl-4-2-10besar') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-orange-50 text-orange-700 group-hover:bg-orange-100 dark:bg-orange-900/30 dark:text-orange-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">RL 4.2 Top 10 Penyakit RI</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Tahunan &mdash; 10 ICD-10 terbanyak di RI (gender + jumlah meninggal)
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.sirs.ri.laporan-rl-4-3-10besar-mati') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-rose-50 text-rose-700 group-hover:bg-rose-100 dark:bg-rose-900/30 dark:text-rose-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">RL 4.3 Top 10 Kematian RI</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Tahunan &mdash; 10 ICD-10 penyebab kematian terbanyak di RI
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.sirs.rj.laporan-rl-5-1-morbiditas') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-blue-50 text-blue-700 group-hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">RL 5.1 Morbiditas RJ</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Tahunan &mdash; rekap morbiditas RJ poliklinik per ICD-10 × 25 umur × gender (kasus baru &amp; kunjungan)
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.sirs.rj.laporan-rl-5-3-10besar-kunjungan') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-cyan-50 text-cyan-700 group-hover:bg-cyan-100 dark:bg-cyan-900/30 dark:text-cyan-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">RL 5.3 Top 10 Kunjungan RJ</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Tahunan &mdash; 10 ICD-10 dengan kunjungan terbanyak di RJ (kasus baru &amp; kunjungan per gender)
                            </div>
                        </div>
                    </a>
                </div>
                <p class="mt-3 text-[10px] text-muted-soft dark:text-gray-500 text-center">
                    Format SIRS Online Kemenkes &mdash; <a href="https://sirs6.kemkes.go.id" class="hover:underline" target="_blank" rel="noopener">sirs6.kemkes.go.id</a>
                </p>
            </div>

        </div>
    </div>
</div>
