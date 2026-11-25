{{--
    Isi EMR RJ (body S/O/A/P/N/R + footer aksi) — di-@include oleh emr-rj, baik mode modal sendiri
    maupun mode embedded (tab "Rekam Medis Rawat Jalan" di modal gabungan emr-coding-rj).
    Variabel/metode dari komponen emr-rj ($rjNo, $isFormLocked, $noSep, $regNoPasien, $this->hasEresep()).
--}}
            {{-- BODY --}}
            <div class="flex-1 px-4 pb-4 bg-surface-soft/70 dark:bg-gray-950/20 text-base">
                <div class="max-w-full mx-auto">
                    <div
                        class="p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">


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
                                <livewire:pages::transaksi.rj.emr-rj.anamnesa.rm-anamnesa-rj-actions :rjNo="$rjNo"
                                    wire:key="anamnesa-rj-{{ $rjNo }}" />
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
                                <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.rm-pemeriksaan-rj-actions
                                    :rjNo="$rjNo" wire:key="pemeriksaan-rj-{{ $rjNo }}" />
                            </div>
                        </div>

                        {{-- Kelompok APN (kiri, 2/3) + R (kanan, 1/3) --}}
                        <div class="grid grid-cols-3 gap-2">
                            {{-- KELOMPOK APN — A | P di atas, N (span 2) di bawah --}}
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
                                    <x-border-form :align="__('start')" :bgcolor="__('bg-canvas')">
                                        <livewire:pages::transaksi.rj.emr-rj.diagnosa.rm-diagnosa-rj-actions
                                            :rjNo="$rjNo" wire:key="diagnosa-rj-{{ $rjNo }}" />
                                    </x-border-form>
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
                                    <livewire:pages::transaksi.rj.emr-rj.perencanaan.rm-perencanaan-rj-actions
                                        :rjNo="$rjNo" wire:key="perencanaan-rj-{{ $rjNo }}" />
                                </div>

                                {{-- N: Penilaian — di bawah AP, span 2 --}}
                                <div class="col-span-2 -mt-1">
                                    <div
                                        class="mb-2 p-2 flex items-center gap-2 rounded-t-lg border-2 bg-purple-100 dark:border-purple-700">
                                        <span
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-purple-100 text-purple-700 text-base font-bold dark:bg-purple-900/40 dark:text-purple-300">N</span>
                                        <span class="text-base font-semibold text-body dark:text-gray-300">Penilaian
                                            — Nyeri / Risiko Jatuh / Dekubitus / Gizi</span>
                                    </div>
                                    <livewire:pages::transaksi.rj.emr-rj.penilaian.rm-penilaian-rj-actions
                                        :rjNo="$rjNo" wire:key="penilaian-rj-{{ $rjNo }}" />
                                </div>
                            </div>

                            {{-- R: Rekam Medis — sebelah kanan kelompok APN --}}
                            <div>
                                <div
                                    class="mb-2 p-2 flex items-center gap-2 rounded-t-lg border-2 bg-surface-soft dark:border-gray-600">
                                    <span
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-surface-soft text-muted text-base font-bold dark:bg-gray-700 dark:text-gray-300">R</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Rekam
                                        Medis</span>
                                </div>
                                {{-- Viewer-nya dipasang di pelayanan-rj.blade.php (satu-satunya host EMR RJ). --}}
                                <livewire:pages::components.rekam-medis.rekam-medis-display.rekam-medis-display
                                    :regNo="$regNoPasien" :rjNoRefCopyTo="$rjNo ?? 0" :pasangViewer="false"
                                    wire:key="emr-rj.eresep-rj-rekam-medis-display-rj-{{ $regNoPasien ?: 'new' }}" />
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-2 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">

                    {{-- KIRI: Status + Action buttons (i-Care, Administrasi, E-Resep) --}}
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($isFormLocked)
                            <x-badge variant="danger">
                                Read Only
                            </x-badge>
                        @endif

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

                        @can('rj.emr.bukaScreening')
                            {{-- Screening RJ — teal outline (skrining awal poli, buka via dispatch) --}}
                            <x-primary-button type="button"
                                wire:click="$dispatch('open-rm-screening-rj', { rjNo: {{ $rjNo }} })"
                                class="gap-1 !bg-teal-600 hover:!bg-teal-700 !text-white focus:!ring-teal-300 dark:!bg-teal-600 dark:!text-white dark:hover:!bg-teal-700 dark:focus:!ring-teal-900">
                                <x-ikon nama="setujui" />Screening
                            </x-primary-button>
                        @endcan

                        @can('rj.emr.bukaAdministrasi')
                            <x-primary-button type="button" wire:click="openAdministrasiPasien('{{ $rjNo }}')"
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

                        @can('rj.emr.bukaModulDokumen')
                            {{-- Dokumen — indigo solid (modal modul dokumen ada di page pelayanan yang sama) --}}
                            <x-primary-button type="button"
                                wire:click="$dispatch('emr-rj.modul-dokumen.open', { rjNo: '{{ $rjNo }}' })"
                                class="gap-1 !bg-indigo-600 hover:!bg-indigo-700 !text-white focus:!ring-indigo-300 dark:!bg-indigo-600 dark:!text-white dark:hover:!bg-indigo-700 dark:focus:!ring-indigo-900">
                                <x-ikon nama="dokumen" />Modul Dokumen
                            </x-primary-button>
                        @endcan

                        @can('emr.logAktivitas')
                            {{-- Log Aktivitas — slate solid (manager ke atas) --}}
                            <x-primary-button type="button" wire:click="openLogAktivitas('{{ $rjNo }}')"
                                wire:loading.attr="disabled" wire:target="openLogAktivitas"
                                class="order-first gap-1 !bg-slate-600 hover:!bg-slate-700 !text-white focus:!ring-slate-300 dark:!bg-slate-600 dark:!text-white dark:hover:!bg-slate-700 dark:focus:!ring-slate-900">
                                <span wire:loading.remove wire:target="openLogAktivitas" class="flex items-center gap-1">
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
                                                'save-rm-anamnesa-rj',
                                                'save-rm-pemeriksaan-rj',
                                                'save-rm-diagnosa-rj',
                                                'save-rm-perencanaan-rj',
                                            ];
                                            let saved = 0;
                                            const onSaved = () => saved++;
                                            window.addEventListener('refresh-after-rj.saved', onSaved);
                                            try {
                                                events.forEach(e => Livewire.dispatch(e, { silent: true }));
                                                const deadline = Date.now() + 3000;
                                                while (saved < events.length && Date.now() < deadline) {
                                                    await new Promise(r => setTimeout(r, 50));
                                                }
                                            } finally {
                                                window.removeEventListener('refresh-after-rj.saved', onSaved);
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
                        @can('rj.emr.bukaRekamMedisNasional')
                            <x-primary-button type="button"
                                wire:click="$dispatch('rekam-medis-nasional.open', { jalur: 'RJ', nomor: '{{ $rjNo }}' })"
                                class="gap-1 !bg-emerald-600 hover:!bg-emerald-700 !text-white focus:!ring-emerald-300 dark:!bg-emerald-600 dark:!text-white dark:hover:!bg-emerald-700 dark:focus:!ring-emerald-900">
                                <x-ikon nama="dokumen" />
                                Rekam Medis Nasional
                            </x-primary-button>
                        @endcan
                    </div>

                    {{-- KANAN: Tutup + Simpan sebelahan --}}
                    <div class="flex items-center gap-2">
                        {{-- Event, bukan tryClose() langsung: dirty guard milik host (modal EMR sendiri / pembungkus emr-coding-rj) --}}
                        <x-secondary-button x-on:click="$dispatch('emr-rj-tutup')">
                            <x-ikon nama="batal" />
                            Tutup
                        </x-secondary-button>

                        @if (!$isFormLocked)
                            <x-primary-button wire:click.prevent="save()" class="min-w-[120px]"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove>
                                    <x-ikon nama="simpan" class="inline mr-1 -ml-1" />
                                    Simpan
                                </span>
                                <span wire:loading>
                                    <x-loading />
                                    Menyimpan...
                                </span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

