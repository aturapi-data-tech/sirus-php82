<?php

use Livewire\Component;

// Dokumentasi Approval Hub — panduan arsitektur, alur kerja, dan referensi teknis
// untuk modul Casemix AI / E-Klaim Bridging / SATUSEHAT.
// Layout mengikuti pola sidebar section switching (alur-pelayanan).
new class extends Component {
    //
};
?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap"
        rel="stylesheet" />

    @php
        $menuGroups = [
            'Dasar' => [
                'overview' => 'Overview',
                'architecture' => 'Arsitektur & File',
                'database' => 'Database (Queue Table)',
            ],
            'Alur Kerja' => [
                'scan' => 'Scan Transaksi',
                'ai-suggest' => 'AI Suggest ICD',
                'approve' => 'Approve & Sync EMR',
                'bridging' => 'Bridging E-Klaim (14 Step)',
                'claim-data' => 'Claim Data & Tarif',
            ],
            'Data & EMR' => [
                'emr-soap' => 'SOAP Extraction',
                'emr-completeness' => 'EMR Completeness',
                'diagnosa-format' => 'Format Diagnosa/Prosedur',
            ],
            'UI Pattern' => [
                'selection' => 'Row Selection (x-toggle)',
                'berkas' => 'Upload Berkas BPJS',
            ],
            'Pengembangan' => [
                'new-module' => 'Tambah Modul Baru',
            ],
        ];

        $labels = array_merge(...array_values($menuGroups));
    @endphp

    <div class="ds" style="min-height:100vh"
        x-data='{
            section: "overview",
            order: @json(array_keys($labels)),
            labels: @json($labels),
            idx() { return this.order.indexOf(this.section) },
            go(s) {
                this.section = s;
                history.replaceState(null, "", "#" + s);
                window.scrollTo({ top: 0, behavior: "smooth" });
            },
            init() {
                const h = window.location.hash.slice(1);
                if (this.order.includes(h)) this.section = h;
            }
        }'>
        <div class="ds-section" style="padding-top:32px; padding-bottom:96px">

            {{-- ============ HEADER ============ --}}
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="ds-spike"></span>
                    <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                    <a href="{{ route('panduan-dev') }}" wire:navigate class="ds-body-sm hover:underline"
                        style="color:var(--muted-soft)">/ Standarisasi UI</a>
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Approval Hub</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('approval-hub') }}" wire:navigate
                        class="ds-btn ds-btn-secondary" style="height:34px; padding:6px 12px; font-size:13px">
                        Buka Approval Hub →</a>
                    <x-theme-toggle />
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-10 lg:grid-cols-[240px_1fr]">

                {{-- ============ SIDEBAR ============ --}}
                <aside class="self-start lg:sticky lg:top-24">
                    @foreach ($menuGroups as $group => $items)
                        <div class="mb-6">
                            <div class="ds-caption-up mb-2 px-3">{{ $group }}</div>
                            <div class="space-y-0.5">
                                @foreach ($items as $key => $label)
                                    <button type="button" x-on:click="go('{{ $key }}')"
                                        class="block w-full px-3 py-1.5 text-sm text-left rounded-lg transition-colors"
                                        :class="section === '{{ $key }}' ? 'font-semibold' : 'font-normal'"
                                        :style="section === '{{ $key }}'
                                            ? 'background:var(--surface-card); color:var(--ink)'
                                            : 'color:var(--body)'">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="px-3 pt-4" style="border-top:1px solid var(--hairline)">
                        <div class="ds-caption" style="color:var(--muted-soft)">
                            Skill:
                            <span class="ds-code">sirus-approval-hub</span><br>
                            Modul aktif: Casemix, SATUSEHAT, Bundling
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    @include('pages.panduan-dev.approval-hub.approval-hub-dasar')

                    @include('pages.panduan-dev.approval-hub.approval-hub-alur')

                    @include('pages.panduan-dev.approval-hub.approval-hub-data')

                    @include('pages.panduan-dev.approval-hub.approval-hub-ui')

                    @include('pages.panduan-dev.approval-hub.approval-hub-dev')

                    {{-- ============ PAGER ============ --}}
                    <div class="flex items-center justify-between gap-3 mt-12 pt-6"
                        style="border-top:1px solid var(--hairline)">
                        <button type="button" class="ds-btn ds-btn-secondary" x-show="idx() > 0" x-cloak
                            x-on:click="go(order[idx() - 1])">← <span x-text="labels[order[idx() - 1]]"></span></button>
                        <span></span>
                        <button type="button" class="ds-btn ds-btn-primary" x-show="idx() < order.length - 1" x-cloak
                            x-on:click="go(order[idx() + 1])"><span x-text="labels[order[idx() + 1]]"></span> →</button>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>
