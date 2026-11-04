{{--
    Isi Coding SATUSEHAT RJ (grid SOAP + "Yang akan dikirim" + footer) — di-@include oleh coding-fhir-rj,
    baik mode modal sendiri (Daftar RJ) maupun embedded (tab Coding di modal gabungan emr-coding-rj).
    Variabel dari komponen: $rjNo, $versi, $tampilkanKiriman, $this::KARTU_PRATINJAU.
--}}
                {{-- BODY — grid SOAP seperti EMR RJ --}}
                <div class="flex-1 px-4 pb-4 overflow-y-auto text-base bg-surface-soft/70 dark:bg-gray-950/20">
                    <div class="p-4 mt-3 space-y-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <p class="text-sm text-muted dark:text-gray-400">
                            Teks klinis diisi saat pelayanan (EMR) dan di sini hanya dibaca — yang perlu dibetulkan diubah di EMR.
                            Isi kode di kotak <span class="font-semibold text-brand-green dark:text-brand-lime">Coding SATUSEHAT</span>.
                        </p>

                        <div class="grid grid-cols-1 gap-2 xl:grid-cols-2">
                            {{-- S --}}
                            <div>
                                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg bg-blue-100 dark:border-blue-700">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900/40 dark:text-blue-300">S</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Subjective — Anamnesa</span>
                                </div>
                                <livewire:pages::transaksi.rj.coding-fhir-rj.anamnesa.coding-anamnesa-rj :rjNo="$rjNo"
                                    wire:key="coding-anamnesa-rj-{{ $rjNo }}-{{ $versi }}" />
                            </div>

                            {{-- O --}}
                            <div>
                                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg bg-emerald-100 dark:border-emerald-700">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">O</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Objective — Pemeriksaan</span>
                                </div>
                                <livewire:pages::transaksi.rj.coding-fhir-rj.pemeriksaan.coding-pemeriksaan-rj :rjNo="$rjNo"
                                    wire:key="coding-pemeriksaan-rj-{{ $rjNo }}-{{ $versi }}" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2 xl:grid-cols-2">
                            {{-- A --}}
                            <div>
                                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg bg-amber-100 dark:border-amber-700">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">A</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Assessment — Diagnosis</span>
                                </div>
                                <livewire:pages::transaksi.rj.coding-fhir-rj.diagnosa.coding-diagnosa-rj :rjNo="$rjNo"
                                    wire:key="coding-diagnosa-rj-{{ $rjNo }}-{{ $versi }}" />
                            </div>

                            {{-- P --}}
                            <div>
                                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg bg-rose-100 dark:border-rose-700">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full bg-rose-100 text-error dark:bg-rose-900/40 dark:text-rose-300">P</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Plan — Perencanaan</span>
                                </div>
                                <livewire:pages::transaksi.rj.coding-fhir-rj.perencanaan.coding-perencanaan-rj :rjNo="$rjNo"
                                    wire:key="coding-perencanaan-rj-{{ $rjNo }}-{{ $versi }}" />
                            </div>
                        </div>
                    </div>

                    {{-- YANG AKAN DIKIRIM — kartu SATUSEHAT RJ mode pratinjau (tanpa tombol Kirim). Dimuat saat diminta. --}}
                    {{-- Frame buka-tutup (pola panel Status Psikiatri, x-border-form collapsible). Kartu baru
                         di-mount saat frame PERTAMA kali dibuka ($wire.toggleKiriman) — layar Coding tetap ringan. --}}
                    <x-border-form :title="__('Yang akan dikirim ke SATUSEHAT')" :align="__('start')" :bgcolor="__('bg-canvas')"
                        class="mt-4" :collapsible="true" :open="$tampilkanKiriman"
                        x-init="$watch('open', (buka) => { if (buka && !$wire.tampilkanKiriman) $wire.toggleKiriman() })">
                        <p class="mb-3 text-sm text-muted dark:text-gray-400">
                            Pratinjau tiap kartu, dari sumber yang sama dengan tombol Kirim di modal SATUSEHAT.
                            Pengiriman tetap dari menu Kirim Satu Sehat.
                        </p>
                        <div wire:loading.flex wire:target="toggleKiriman" class="items-center gap-2 py-2 text-sm text-muted dark:text-gray-400">
                            <x-loading /> Memuat kartu…
                        </div>
                        @if ($tampilkanKiriman)
                            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                @foreach ($this::KARTU_PRATINJAU as $huruf => [$judulKelompok, $kelasHeader, $kelasHuruf, $kartuList])
                                    <div>
                                        <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg {{ $kelasHeader }}">
                                            <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full {{ $kelasHuruf }}">{{ $huruf }}</span>
                                            <span class="text-base font-semibold text-body dark:text-gray-300">{{ $judulKelompok }}</span>
                                        </div>
                                        <div class="space-y-2">
                                            @foreach ($kartuList as $kartu)
                                                <livewire:dynamic-component :is="'pages::transaksi.rj.satu-sehat.kirim-' . $kartu"
                                                    :rjNo="$rjNo" :hanyaPratinjau="true"
                                                    :key="'coding-pratinjau-' . $kartu . '-' . $rjNo . '-' . $versi" />
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </x-border-form>
                </div>

                {{-- FOOTER --}}
                <div class="sticky bottom-0 z-10 flex justify-end gap-2 px-6 py-2 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                    {{-- Event: penutupnya milik host (modal sendiri / pembungkus emr-coding-rj yang punya dirty guard) --}}
                    <x-secondary-button type="button" x-on:click="$dispatch('coding-fhir-rj-tutup')">Tutup</x-secondary-button>
                    <x-primary-button type="button" wire:click="simpan">Simpan Coding</x-primary-button>
                </div>
