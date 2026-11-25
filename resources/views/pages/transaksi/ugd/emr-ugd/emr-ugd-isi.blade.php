{{--
    Isi EMR UGD (body + footer aksi) — di-@include oleh emr-ugd, baik mode modal sendiri maupun
    mode embedded (tab "Rekam Medis UGD" di modal gabungan emr-coding-ugd).
    Tombol Tutup memancarkan 'emr-ugd-tutup' → ditangkap dirty guard host.
--}}
            {{-- ═══════════ BODY ═══════════ --}}
            <div class="flex-1 px-4 pb-4 bg-surface-soft/70 dark:bg-gray-950/20 text-base">
                <div class="max-w-full mx-auto">
                    <div class="space-y-6">

                        {{-- Screening UGD (x-modal, dibuka via tombol / dispatch) --}}
                        <livewire:pages::transaksi.ugd.emr-ugd.screening.rm-screening-ugd-actions :rjNo="$rjNo"
                            wire:key="screening-ugd-{{ $rjNo }}" />

                        {{-- Row 1: S | O --}}
                        <div class="grid grid-cols-2 gap-2">
                            {{-- ANAMNESA — S: Subjective --}}
                            <div>
                                <div
                                    class="mb-2 p-2 flex items-center gap-2 rounded-t-lg border-2 bg-blue-100 dark:border-blue-700">
                                    <span
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 text-blue-700 text-base font-bold dark:bg-blue-900/40 dark:text-blue-300">S</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Subjective —
                                        Anamnesa</span>
                                </div>
                                <livewire:pages::transaksi.ugd.emr-ugd.anamnesa.rm-anamnesa-ugd-actions
                                    :rjNo="$rjNo" wire:key="anamnesa-ugd-{{ $rjNo }}" />
                            </div>

                            {{-- PEMERIKSAAN — O: Objective --}}
                            <div>
                                <div
                                    class="mb-2 p-2 flex items-center gap-2 rounded-t-lg border-2 bg-emerald-100 dark:border-emerald-700">
                                    <span
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 text-base font-bold dark:bg-emerald-900/40 dark:text-emerald-300">O</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Objective —
                                        Pemeriksaan</span>
                                </div>
                                <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.rm-pemeriksaan-ugd-actions
                                    :rjNo="$rjNo" wire:key="pemeriksaan-ugd-{{ $rjNo }}" />
                            </div>
                        </div>

                        {{-- Kelompok AP (kiri, 2/3) + R (kanan, 1/3) — struktur disamakan dgn EMR RJ --}}
                        <div class="grid grid-cols-3 gap-2">
                            {{-- KELOMPOK AP — A | P berdampingan --}}
                            <div class="col-span-2 grid grid-cols-2 gap-2">
                                {{-- DIAGNOSA — A: Assessment --}}
                                <div>
                                    <div
                                        class="mb-2 p-2 flex items-center gap-2 rounded-t-lg border-2 bg-amber-100 dark:border-amber-700">
                                        <span
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-100 text-amber-700 text-base font-bold dark:bg-amber-900/40 dark:text-amber-300">A</span>
                                        <span
                                            class="text-base font-semibold text-body dark:text-gray-300">Assessment
                                            — Diagnosis</span>
                                    </div>
                                    <livewire:pages::transaksi.ugd.emr-ugd.diagnosa.rm-diagnosa-ugd-actions
                                        :rjNo="$rjNo" wire:key="diagnosa-ugd-{{ $rjNo }}" />
                                </div>

                                {{-- PERENCANAAN — P: Plan --}}
                                <div>
                                    <div
                                        class="mb-2 p-2 flex items-center gap-2 rounded-t-lg border-2 bg-rose-100 dark:border-rose-700">
                                        <span
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-rose-100 text-error text-base font-bold dark:bg-rose-900/40 dark:text-rose-300">P</span>
                                        <span class="text-base font-semibold text-body dark:text-gray-300">Plan —
                                            Perencanaan</span>
                                    </div>
                                    <livewire:pages::transaksi.ugd.emr-ugd.perencanaan.rm-perencanaan-ugd-actions
                                        :rjNo="$rjNo" wire:key="perencanaan-ugd-{{ $rjNo }}" />
                                </div>
                            </div>

                            {{-- R: Rekam Medis — sebelah kanan kelompok AP --}}
                            <div>
                                <div
                                    class="mb-2 p-2 flex items-center gap-2 rounded-t-lg border-2 bg-surface-soft dark:border-gray-600">
                                    <span
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-surface-soft text-muted text-base font-bold dark:bg-gray-700 dark:text-gray-300">R</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Rekam
                                        Medis</span>
                                </div>
                                <livewire:pages::components.rekam-medis.rekam-medis-display.rekam-medis-display
                                    :regNo="$regNoPasien"
                                    wire:key="emr-ugd.rekam-medis-display-ugd-{{ $regNoPasien ?: 'new' }}" />
                            </div>
                        </div>

                    </div>

                    {{-- TAB GROUP N | L | T (Penilaian / Observasi / Obat-Cairan)
                         Pola disamakan dgn EMR RI (emr-ri) yg TERBUKTI aman saat Simpan:
                         panel = `x-show` + `x-transition` polos, TANPA wire:key & TANPA x-cloak.
                         wire:key HANYA di komponen anak (island). Panel ber-wire:key bikin morphdom
                         boleh melepas/pindah node saat parent morph → anak RE-MOUNT ($rjNo reset).
                         JANGAN pakai wire:ignore membungkus komponen Livewire nested: itu meng-orphan
                         listener #[On] (mis. lov.selected.obat-dan-cairan-ugd mati setelah Simpan). --}}
                    <div x-data="{ activeTab: 'penilaian' }"
                        class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-2 border-b border-hairline dark:border-gray-700">
                            <div class="flex flex-nowrap gap-2 -mb-px">
                                <x-tab variant="underline" active-expr="activeTab === 'penilaian'"
                                    x-on:click="activeTab = 'penilaian'" class="inline-flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-purple-100 text-purple-700 text-sm font-bold dark:bg-purple-900/40 dark:text-purple-300">N</span>
                                    Penilaian — Nyeri / Risiko Jatuh / Dekubitus / Gizi
                                </x-tab>
                                <x-tab variant="underline" active-expr="activeTab === 'observasi'"
                                    x-on:click="activeTab = 'observasi'" class="inline-flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 text-amber-700 text-sm font-bold dark:bg-amber-900/40 dark:text-amber-300">L</span>
                                    Observasi Lanjutan
                                </x-tab>
                                <x-tab variant="underline" active-expr="activeTab === 'terapi'"
                                    x-on:click="activeTab = 'terapi'" class="inline-flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-teal-100 text-teal-700 text-sm font-bold dark:bg-teal-900/40 dark:text-teal-300">T</span>
                                    Pemberian Obat &amp; Cairan
                                </x-tab>
                                <x-tab variant="underline" active-expr="activeTab === 'sbar'"
                                    x-on:click="activeTab = 'sbar'" class="inline-flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-sm font-bold dark:bg-indigo-900/40 dark:text-indigo-300">S</span>
                                    SBAR
                                </x-tab>
                            </div>
                        </div>

                        <div class="p-3">
                            {{-- Panel polos meniru EMR RI: x-show + x-transition, TANPA wire:key & x-cloak.
                                 Identitas komponen dipegang wire:key di tag <livewire:...> (island). --}}
                            <div x-show="activeTab === 'penilaian'" x-transition.opacity.duration.200ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.penilaian.rm-penilaian-ugd-actions :rjNo="$rjNo"
                                    wire:key="penilaian-ugd-{{ $rjNo }}" />
                            </div>

                            <div x-show="activeTab === 'observasi'" x-transition.opacity.duration.200ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.observasi.rm-observasi-ugd-actions :rjNo="$rjNo"
                                    wire:key="observasi-ugd-{{ $rjNo }}" />
                            </div>

                            <div x-show="activeTab === 'terapi'" x-transition.opacity.duration.200ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.obat-dan-cairan.rm-obat-dan-cairan-ugd-actions
                                    :rjNo="$rjNo" wire:key="obat-dan-cairan-ugd-{{ $rjNo }}" />
                            </div>

                            <div x-show="activeTab === 'sbar'" x-transition.opacity.duration.200ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.sbar.rm-sbar-ugd-actions :rjNo="$rjNo"
                                    wire:key="sbar-ugd-{{ $rjNo }}" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════ FOOTER (justify-between: Tutup | aksi | Simpan) ═══════════ --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-2 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">

                    {{-- KIRI: Status + Action buttons --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge variant="danger">UGD / IGD</x-badge>

                        @if ($isFormLocked)
                            <x-badge variant="danger">Read Only</x-badge>
                        @endif

                        @role(['Perawat', 'Dokter', 'Admin'])
                            {{-- Screening UGD — indigo (buka form skrining) --}}
                            <x-primary-button type="button"
                                wire:click="$dispatch('open-rm-screening-ugd', { rjNo: {{ $rjNo }} })"
                                class="gap-1 text-sm !bg-indigo-600 hover:!bg-indigo-700 !text-white focus:!ring-indigo-300 dark:!bg-indigo-600 dark:!text-white dark:hover:!bg-indigo-700 dark:focus:!ring-indigo-900">
                                <x-ikon nama="setujui" />
                                Screening
                            </x-primary-button>
                        @endrole

                        @role(['Dokter', 'Admin'])
                            @if (filled($noSep))
                                <x-primary-button type="button"
                                    wire:click="myiCare('{{ $noSep }}')"
                                    wire:loading.attr="disabled" wire:target="myiCare"
                                    class="gap-1 !bg-emerald-600 hover:!bg-emerald-700 !text-white focus:!ring-emerald-300 dark:!bg-emerald-600 dark:!text-white dark:hover:!bg-emerald-700 dark:focus:!ring-emerald-900">
                                    <span wire:loading.remove wire:target="myiCare" class="flex items-center gap-1">
                                        <x-ikon nama="riwayat" />
                                        i-Care
                                    </span>
                                    <span wire:loading wire:target="myiCare" class="flex items-center gap-1">
                                        <x-loading /> Memuat...
                                    </span>
                                </x-primary-button>
                            @endif
                        @endrole

                        @can('ugd.emr.bukaAdministrasi')
                            <x-primary-button type="button" wire:click="openAdministrasiPasien({{ $rjNo }})"
                                wire:loading.attr="disabled" wire:target="openAdministrasiPasien"
                                class="gap-1 !bg-purple-600 hover:!bg-purple-700 !text-white dark:!bg-purple-600 dark:!text-white dark:hover:!bg-purple-700">
                                <span wire:loading.remove wire:target="openAdministrasiPasien"
                                    class="flex items-center gap-1">
                                    <x-ikon nama="pembayaran" class="shrink-0" />
                                    Administrasi
                                </span>
                                <span wire:loading wire:target="openAdministrasiPasien" class="flex items-center gap-1">
                                    <x-loading /> Memuat...
                                </span>
                            </x-primary-button>
                        @endcan

                        @can('ugd.emr.bukaModulDokumen')
                            {{-- Dokumen — indigo solid (modal modul dokumen ada di page pelayanan yang sama) --}}
                            <x-primary-button type="button"
                                wire:click="$dispatch('emr-ugd.modul-dokumen.open', { rjNo: {{ $rjNo }} })"
                                class="gap-1 !bg-indigo-600 hover:!bg-indigo-700 !text-white focus:!ring-indigo-300 dark:!bg-indigo-600 dark:!text-white dark:hover:!bg-indigo-700 dark:focus:!ring-indigo-900">
                                <x-ikon nama="dokumen" />Modul Dokumen
                            </x-primary-button>
                        @endcan

                        @can('emr.logAktivitas')
                            {{-- Log Aktivitas — slate solid (manager ke atas) --}}
                            <x-primary-button type="button" wire:click="openLogAktivitas({{ $rjNo }})"
                                wire:loading.attr="disabled" wire:target="openLogAktivitas"
                                class="order-first gap-1 !bg-slate-600 hover:!bg-slate-700 !text-white focus:!ring-slate-300 dark:!bg-slate-600 dark:!text-white dark:hover:!bg-slate-700 dark:focus:!ring-slate-900">
                                <span wire:loading.remove wire:target="openLogAktivitas"
                                    class="flex items-center gap-1">
                                    <x-ikon nama="riwayat" class="shrink-0" />
                                    Log Aktivitas
                                </span>
                                <span wire:loading wire:target="openLogAktivitas" class="flex items-center gap-1">
                                    <x-loading /> Memuat...
                                </span>
                            </x-primary-button>
                        @endcan

                        @hasanyrole('Dokter|Admin|Perawat')
                            <x-primary-button type="button" class="gap-1" x-data="{
                                loadingEresep: false,
                                async openEresepWithSave(rjNo) {
                                    if (this.loadingEresep) return;
                                    this.loadingEresep = true;
                                    try {
                                        if (!$wire.isFormLocked) {
                                            const events = [
                                                'save-rm-anamnesa-ugd',
                                                'save-rm-pemeriksaan-ugd',
                                                'save-rm-diagnosa-ugd',
                                                'save-rm-perencanaan-ugd',
                                            ];
                                            let saved = 0;
                                            const onSaved = () => saved++;
                                            window.addEventListener('refresh-after-ugd.saved', onSaved);
                                            try {
                                                events.forEach(e => Livewire.dispatch(e, { silent: true }));
                                                const deadline = Date.now() + 3000;
                                                while (saved < events.length && Date.now() < deadline) {
                                                    await new Promise(r => setTimeout(r, 50));
                                                }
                                            } finally {
                                                window.removeEventListener('refresh-after-ugd.saved', onSaved);
                                            }
                                        }
                                        await $wire.openEresep(rjNo);
                                    } finally {
                                        this.loadingEresep = false;
                                    }
                                }
                            }"
                                x-bind:disabled="loadingEresep"
                                x-on:click.prevent="openEresepWithSave({{ $rjNo }})">
                                <span x-show="!loadingEresep" class="flex items-center gap-1">
                                    <x-ikon nama="dokumen" />E-Resep
                                </span>
                                <span x-show="loadingEresep" x-cloak class="flex items-center gap-1">
                                    <x-loading /> Menyimpan & memuat...
                                </span>
                            </x-primary-button>
                        @endhasanyrole

                        @can('emr.cetakEresep')
                            @if ($this->hasEresep())
                                <x-cetak-button wire:click="cetakEresep('{{ $rjNo }}')" label="Cetak E-Resep" />
                            @endif
                        @endcan

                        {{-- Rekam Medis Nasional — riwayat pasien dari faskes lain (SATUSEHAT RME) --}}
                        @can('ugd.emr.bukaRekamMedisNasional')
                            <x-primary-button type="button"
                                wire:click="$dispatch('rekam-medis-nasional.open', { jalur: 'UGD', nomor: '{{ $rjNo }}' })"
                                class="gap-1 !bg-emerald-600 hover:!bg-emerald-700 !text-white focus:!ring-emerald-300 dark:!bg-emerald-600 dark:!text-white dark:hover:!bg-emerald-700 dark:focus:!ring-emerald-900">
                                <x-ikon nama="dokumen" />
                                Rekam Medis Nasional
                            </x-primary-button>
                        @endcan
                    </div>

                    {{-- KANAN: Tutup + Simpan sebelahan --}}
                    <div class="flex items-center gap-2">
                        <x-secondary-button x-on:click="$dispatch('emr-ugd-tutup')"><x-ikon nama="batal" /> Tutup</x-secondary-button>

                        @if (!$isFormLocked)
                            <x-primary-button wire:click.prevent="save()" class="min-w-[120px]"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove>
                                    <x-ikon nama="simpan" class="inline mr-1 -ml-1" />
                                    Simpan
                                </span>
                                <span wire:loading><x-loading /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

