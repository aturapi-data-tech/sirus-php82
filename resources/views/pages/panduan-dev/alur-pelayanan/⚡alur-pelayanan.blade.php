<?php

use Livewire\Component;

// Tutorial ALUR PELAYANAN pasien di SIMRS — pendaftaran → pelayanan/EMR →
// apotek → kasir, diklasifikasikan per jalur RJ / UGD / RI.
// Beda dari koding-administrasi (fokus kode/tabel): halaman ini menjelaskan
// URUTAN OPERASIONAL — siapa membuka menu apa, status apa yang berubah,
// dan stempel antrean BPJS (taskId) mana yang tercatat di tiap tahap.
// Gaya sidebar per-seksi sama dgn koding-satusehat.
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
            'Mulai' => [
                'pendahuluan' => 'Pendahuluan',
                'peta-modul' => 'Peta Modul & Role',
                'master-pasien' => 'Master Pasien (Identitas)',
            ],
            'Rawat Jalan' => [
                'rj-daftar' => '1. Daftar RJ (Pendaftaran)',
                'rj-pelayanan' => '2. Pelayanan RJ (EMR)',
                'rj-apotek' => '3. Apotek RJ',
                'rj-kasir' => '4. Kasir RJ',
                'rj-status' => 'Siklus Status & taskId',
            ],
            'Cabang dari EMR' => [
                'rj-laborat' => 'Order Lab → Modul Laborat',
                'rj-radiologi' => 'Order Rad → Modul Radiologi',
                'rj-eresep' => 'E-Resep → Apotek',
                'ok' => 'Kamar Operasi (OK)',
            ],
            'UGD' => [
                'ugd-daftar' => '1. Daftar UGD',
                'ugd-pelayanan' => '2. Pelayanan UGD (EMR)',
                'ugd-apotek' => '3. Apotek UGD',
                'ugd-kasir' => '4. Kasir UGD',
            ],
            'Rawat Inap' => [
                'ri-daftar' => '1. Masuk RI (Pendaftaran)',
                'ri-emr' => '2. EMR RI Harian',
                'ri-apotek' => '3. Apotek RI (per lembar)',
                'ri-administrasi' => '4. Administrasi & Pulang',
                'ri-kasir' => '5. Kasir RI',
            ],
            'Gudang' => [
                'gudang-penerimaan' => 'Penerimaan Medis & Non-Medis',
                'gudang-transfer' => 'Transfer Stok (Distribusi)',
            ],
        ];

        $labels = array_merge(...array_values($menuGroups));
    @endphp

    <div class="ds" style="min-height:100vh"
        x-data='{
            section: "pendahuluan",
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
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Alur Pelayanan</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('panduan-dev.koding-administrasi') }}" wire:navigate
                        class="ds-btn ds-btn-secondary" style="height:34px; padding:6px 12px; font-size:13px">←
                        Tutorial Administrasi</a>
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
                            Pendamping: <a href="{{ route('panduan-dev.koding-administrasi') }}" wire:navigate
                                class="hover:underline" style="color:var(--primary)">Tutorial Koding Administrasi</a><br>
                            Ruang lingkup aktif: <span class="ds-code">Rawat Jalan</span><br>
                            UGD &amp; RI menyusul di halaman ini juga.
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    @include('pages.panduan-dev.alur-pelayanan.alur-pelayanan-dasar')

                    @include('pages.panduan-dev.alur-pelayanan.alur-pelayanan-rj-inti')

                    @include('pages.panduan-dev.alur-pelayanan.alur-pelayanan-rj-penunjang')

                    @include('pages.panduan-dev.alur-pelayanan.alur-pelayanan-ok')

                    @include('pages.panduan-dev.alur-pelayanan.alur-pelayanan-ugd')

                    @include('pages.panduan-dev.alur-pelayanan.alur-pelayanan-ri')

                    @include('pages.panduan-dev.alur-pelayanan.alur-pelayanan-gudang')

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
