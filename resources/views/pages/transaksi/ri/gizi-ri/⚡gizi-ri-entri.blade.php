<?php
// Modal worklist Gizi Rawat Inap (/ri/gizi) — wrapper tipis.
//
// Isi modal = komponen Form Penilaian Gizi EMR yang SAMA dengan tab
// EMR RI → Penilaian → Gizi (rm-penilaian-gizi-ri-actions): satu form,
// dua pintu. Wrapper hanya menyiapkan identitas pasien untuk header
// (query ringan tanpa CLOB) lalu meneruskan event open/save ke komponen.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public ?string $riHdrNo = null;
    public array $identitas = [];

    #[On('gizi-ri-entri.open')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }

        $this->riHdrNo = $riHdrNo;

        $row = DB::table('rsview_rihdrs')
            ->selectRaw("reg_no, reg_name, bangsal_name, room_name, to_char(entry_date,'dd/mm/yyyy hh24:mi:ss') as entry_date_display")
            ->where('rihdr_no', $riHdrNo)
            ->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->identitas = [
            'regNo' => $row->reg_no,
            'regName' => $row->reg_name,
            'bangsal' => $row->bangsal_name,
            'room' => $row->room_name,
            'masuk' => $row->entry_date_display,
        ];

        // Muat data pasien ke komponen form (komponen yang sama dgn tab EMR)
        $this->dispatch('open-rm-penilaian-gizi-ri', $riHdrNo);
        $this->dispatch('open-modal', name: 'gizi-ri-entri');
    }

    public function simpan(): void
    {
        // Save dieksekusi komponen form; setelah tersimpan komponen itu
        // dispatch refresh-after-ri.saved → list worklist ikut segar.
        $this->dispatch('save-rm-penilaian-gizi-ri');
    }

    // Buka modal sibling di halaman worklist (event sama dgn footer EMR RI)
    public function openModulDokumen(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->dispatch('emr-ri.modul-dokumen.open', riHdrNo: $this->riHdrNo);
    }

    public function openAdministrasiPasien(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->dispatch('emr-ri.administrasi.open', riHdrNo: $this->riHdrNo);
    }
};
?>

<div>
    <x-modal name="gizi-ri-entri" size="full" height="full" focusable>
        {{-- wire:key per pasien: ganti pasien → konten remount → Alpine reset ke tab gizi.
             Komponen tab EMR memuat data lewat event open-rm-* (bukan prop) — dikirim
             lazy saat tab pertama kali dibuka, sama semangat reloadEvent di emr-ri. --}}
        <div class="flex flex-col min-h-[calc(100vh-4rem)]"
            x-data="{
                activeTab: 'gizi',
                loaded: {},
                switchTab(key, openEvent = null) {
                    this.activeTab = key;
                    if (openEvent && !this.loaded[key]) {
                        this.loaded[key] = true;
                        Livewire.dispatch(openEvent, { riHdrNo: '{{ $riHdrNo }}' });
                    }
                }
            }"
            wire:key="gizi-entri-content-{{ $riHdrNo ?? 'new' }}">

            {{-- ═══════════ HEADER — display pasien RI (pola emr-ri) ═══════════ --}}
            <div class="relative px-6 py-4 border-b border-hairline dark:border-gray-700 shrink-0">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10] pointer-events-none"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        @if ($riHdrNo)
                            <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                                wire:key="gizi-entri-display-pasien-{{ $riHdrNo }}" />
                        @else
                            <h2 class="font-semibold text-xl text-ink dark:text-gray-100">Penilaian Gizi</h2>
                        @endif
                    </div>

                    <x-icon-button color="gray" type="button" class="shrink-0"
                        x-on:click="$dispatch('close-modal', { name: 'gizi-ri-entri' })">
                        <span class="sr-only">Tutup</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>

                {{-- ── TAB NAVIGATION — set tab relevan gizi (selaras filter role Gizi di emr-ri) ── --}}
                <div class="relative mt-3">
                    <x-scrollable-tabs class="w-full">
                        <x-tabs variant="underline" class="flex-nowrap w-max min-w-full gap-1">
                            @php
                                $giziTabs = [
                                    [
                                        'key' => 'gizi',
                                        'label' => 'Penilaian Gizi',
                                        'icon' =>
                                            'M12 4v16m8-8H4',
                                    ],
                                    [
                                        'key' => 'pengkajian-perawat',
                                        'label' => 'Pengkajian Perawat',
                                        'icon' =>
                                            'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
                                    ],
                                    [
                                        'key' => 'pengkajian-dokter',
                                        'label' => 'Pengkajian Dokter',
                                        'icon' =>
                                            'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
                                    ],
                                    [
                                        'key' => 'pemeriksaan',
                                        'label' => 'Pemeriksaan',
                                        'icon' =>
                                            'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
                                    ],
                                    [
                                        'key' => 'cppt',
                                        'label' => 'CPPT',
                                        'icon' =>
                                            'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
                                    ],
                                    [
                                        'key' => 'sbar',
                                        'label' => 'SBAR',
                                        'icon' =>
                                            'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
                                    ],
                                    [
                                        'key' => 'riwayat',
                                        'label' => 'Riwayat Kunjungan',
                                        'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                                    ],
                                ];

                                // Event pemuat data per tab (pola reloadEvent emr-ri).
                                // gizi & riwayat tidak perlu: gizi dimuat saat modal dibuka,
                                // riwayat memuat sendiri dari prop regNo.
                                $tabOpenEvents = [
                                    'pengkajian-perawat' => 'open-rm-pengkajian-awal-ri',
                                    'pengkajian-dokter' => 'open-rm-pengkajian-dokter-ri',
                                    'pemeriksaan' => 'open-rm-pemeriksaan-ri',
                                    'cppt' => 'open-rm-cppt-ri',
                                    'sbar' => 'open-rm-sbar-ri',
                                ];
                            @endphp

                            @foreach ($giziTabs as $tab)
                                @php
                                    // String argumen disiapkan di sini — ekspresi bertanda
                                    // kutip di atribut komponen merusak parser tag Blade.
                                    $tabOpenEvent = $tabOpenEvents[$tab['key']] ?? null;
                                    $switchTabArgs = "'" . $tab['key'] . "', " . ($tabOpenEvent !== null ? "'" . $tabOpenEvent . "'" : 'null');
                                @endphp
                                <x-tab variant="underline" active-expr="activeTab === '{{ $tab['key'] }}'"
                                    x-on:click="switchTab({{ $switchTabArgs }})"
                                    class="inline-flex items-center gap-2">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="{{ $tab['icon'] }}" />
                                    </svg>
                                    {{ $tab['label'] }}
                                </x-tab>
                            @endforeach
                        </x-tabs>
                    </x-scrollable-tabs>
                </div>
            </div>

            {{-- ═══════════ BODY — TAB PANELS ═══════════ --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div
                    class="p-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                    {{-- TAB — PENILAIAN GIZI (default) — komponen yang sama dgn tab EMR RI --}}
                    <div x-show="activeTab === 'gizi'" x-transition.opacity.duration.200ms>
                        <livewire:pages::transaksi.ri.emr-ri.penilaian-ri.gizi-ri.rm-penilaian-gizi-ri-actions
                            wire:key="gizi-worklist-form" />
                    </div>

                    @if ($riHdrNo)
                        {{-- TAB — PENGKAJIAN PERAWAT (ada skrining gizi; edit di-gate role di dalam komponen) --}}
                        <div x-show="activeTab === 'pengkajian-perawat'" x-transition.opacity.duration.200ms>
                            @hasanyrole('Perawat|Dokter|Admin|Casemix|Mr|Apoteker|Gizi|Laboratorium')
                                <livewire:pages::transaksi.ri.emr-ri.pengkajian-awal-ri.rm-pengkajian-awal-ri-actions
                                    :riHdrNo="$riHdrNo" wire:key="gizi-entri-pengkajian-awal-{{ $riHdrNo }}" />
                            @endhasanyrole
                        </div>

                        {{-- TAB — PENGKAJIAN DOKTER (alergi + instruksi diet) --}}
                        <div x-show="activeTab === 'pengkajian-dokter'" x-transition.opacity.duration.200ms>
                            @hasanyrole('Dokter|Perawat|Admin|Casemix|Mr|Apoteker|Gizi|Laboratorium')
                                <livewire:pages::transaksi.ri.emr-ri.pengkajian-dokter-ri.rm-pengkajian-dokter-ri-actions
                                    :riHdrNo="$riHdrNo" wire:key="gizi-entri-pengkajian-dokter-{{ $riHdrNo }}" />
                            @endhasanyrole
                        </div>

                        {{-- TAB — PEMERIKSAAN (TTV / Nutrisi / Lab / Radiologi) --}}
                        <div x-show="activeTab === 'pemeriksaan'" x-transition.opacity.duration.200ms>
                            <livewire:pages::transaksi.ri.emr-ri.pemeriksaan-ri.rm-pemeriksaan-ri-actions
                                :riHdrNo="$riHdrNo" wire:key="gizi-entri-pemeriksaan-{{ $riHdrNo }}" />
                        </div>

                        {{-- TAB — CPPT (petugas gizi menulis CPPT profesi Gizi) --}}
                        <div x-show="activeTab === 'cppt'" x-transition.opacity.duration.200ms>
                            <livewire:pages::transaksi.ri.emr-ri.cppt-ri.rm-cppt-ri-actions :riHdrNo="$riHdrNo"
                                wire:key="gizi-entri-cppt-{{ $riHdrNo }}" />
                        </div>

                        {{-- TAB — SBAR --}}
                        <div x-show="activeTab === 'sbar'" x-transition.opacity.duration.200ms>
                            <livewire:pages::transaksi.ri.emr-ri.sbar-ri.rm-sbar-ri-actions :riHdrNo="$riHdrNo"
                                wire:key="gizi-entri-sbar-{{ $riHdrNo }}" />
                        </div>

                        {{-- TAB — RIWAYAT KUNJUNGAN --}}
                        <div x-show="activeTab === 'riwayat'" x-transition.opacity.duration.200ms>
                            <livewire:pages::components.rekam-medis.rekam-medis-display.rekam-medis-display
                                :regNo="$identitas['regNo'] ?? ''" :rjNoRefCopyTo="0" :contextRI="true"
                                wire:key="gizi-entri-rekam-medis-display-{{ $identitas['regNo'] ?? 'new' }}" />
                        </div>
                    @endif

                </div>
            </div>

            {{-- FOOTER (justify-between: aksi | Tutup+Simpan — pola footer EMR RI) --}}
            <div class="sticky bottom-0 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">

                    {{-- KIRI: aksi lintas modul --}}
                    <div class="flex flex-wrap items-center gap-2">

                        @hasanyrole('Admin|Perawat|Dokter|Casemix|Apoteker|Gizi')
                            {{-- Modul Dokumen — indigo solid --}}
                            <x-primary-button type="button" wire:click="openModulDokumen"
                                wire:loading.attr="disabled" wire:target="openModulDokumen"
                                class="gap-1 !bg-indigo-600 hover:!bg-indigo-700 !text-white focus:!ring-indigo-300 dark:!bg-indigo-600 dark:!text-white dark:hover:!bg-indigo-700 dark:focus:!ring-indigo-900">
                                <span wire:loading.remove wire:target="openModulDokumen" class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>Modul Dokumen
                                </span>
                                <span wire:loading wire:target="openModulDokumen"
                                    class="flex items-center gap-1"><x-loading /> Memuat...</span>
                            </x-primary-button>
                        @endhasanyrole

                        @hasanyrole('Admin|Perawat|Casemix|Apoteker|Gizi')
                            {{-- Administrasi — teal solid --}}
                            <x-primary-button type="button" wire:click="openAdministrasiPasien"
                                wire:loading.attr="disabled" wire:target="openAdministrasiPasien"
                                class="gap-1 !bg-teal-600 hover:!bg-teal-700 !text-white focus:!ring-teal-300 dark:!bg-teal-600 dark:!text-white dark:hover:!bg-teal-700 dark:focus:!ring-teal-900">
                                <span wire:loading.remove wire:target="openAdministrasiPasien"
                                    class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z" />
                                    </svg>Administrasi
                                </span>
                                <span wire:loading wire:target="openAdministrasiPasien"
                                    class="flex items-center gap-1"><x-loading /> Memuat...</span>
                            </x-primary-button>
                        @endhasanyrole

                    </div>

                    {{-- KANAN: Tutup + Simpan (Simpan hanya di tab Penilaian Gizi — tab lain punya alur simpan sendiri) --}}
                    <div class="flex items-center gap-2">
                        <x-secondary-button type="button"
                            x-on:click="$dispatch('close-modal', { name: 'gizi-ri-entri' })">
                            Tutup
                        </x-secondary-button>
                        <x-primary-button type="button" wire:click="simpan" wire:loading.attr="disabled"
                            x-show="activeTab === 'gizi'" class="min-w-[120px]">
                            <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                            </svg>
                            Simpan Penilaian Gizi
                        </x-primary-button>

                        {{-- Simpan Pengkajian Perawat/Dokter — hanya role yang boleh simpan
                             di komponennya (Gizi read-only, tidak melihat tombol ini) --}}
                        @hasanyrole('Perawat|Admin')
                            <x-primary-button type="button" x-show="activeTab === 'pengkajian-perawat'" x-cloak
                                class="min-w-[120px]"
                                x-on:click="Livewire.dispatch('save-rm-pengkajian-awal-ri')">
                                <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                </svg>
                                Simpan Pengkajian Perawat
                            </x-primary-button>
                        @endhasanyrole

                        @hasanyrole('Dokter|Admin')
                            <x-primary-button type="button" x-show="activeTab === 'pengkajian-dokter'" x-cloak
                                class="min-w-[120px]"
                                x-on:click="Livewire.dispatch('save-rm-pengkajian-dokter-ri')">
                                <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                </svg>
                                Simpan Pengkajian Dokter
                            </x-primary-button>
                        @endhasanyrole

                        {{-- Simpan CPPT/SBAR — komponennya tanpa tombol simpan sendiri (pola
                             emr-ri: footer dispatch save-rm-*); label ikut mode edit --}}
                        <x-primary-button type="button" x-show="activeTab === 'cppt'" x-cloak
                            class="min-w-[120px]" x-data="{ editing: false }"
                            x-on:cppt-edit-mode.window="editing = $event.detail?.editing ?? false"
                            x-on:open-modal.window="editing = false"
                            x-on:click="Livewire.dispatch('save-rm-cppt-ri')">
                            <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                            </svg>
                            <span x-text="editing ? 'Perbarui CPPT' : 'Simpan CPPT'"></span>
                        </x-primary-button>

                        <x-primary-button type="button" x-show="activeTab === 'sbar'" x-cloak
                            class="min-w-[120px]" x-data="{ editing: false }"
                            x-on:sbar-edit-mode.window="editing = $event.detail?.editing ?? false"
                            x-on:open-modal.window="editing = false"
                            x-on:click="Livewire.dispatch('save-rm-sbar-ri')">
                            <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                            </svg>
                            <span x-text="editing ? 'Perbarui SBAR' : 'Simpan SBAR'"></span>
                        </x-primary-button>
                    </div>

                </div>
            </div>

        </div>
    </x-modal>
</div>
