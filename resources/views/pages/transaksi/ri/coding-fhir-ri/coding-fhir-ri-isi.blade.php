{{--
    Isi Coding SATUSEHAT RI (grid SOAP + "Yang akan dikirim" + footer) — di-@include oleh coding-fhir-ri,
    baik mode modal sendiri maupun embedded (tab Coding di modal gabungan emr-coding-ri).
    Tombol Tutup memancarkan 'coding-fhir-ri-tutup' → ditangkap host.
--}}
                {{-- BODY — grid SOAP seperti EMR RI --}}
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
                                <livewire:pages::transaksi.ri.coding-fhir-ri.anamnesa.coding-anamnesa-ri :riHdrNo="$riHdrNo"
                                    wire:key="coding-anamnesa-ri-{{ $riHdrNo }}-{{ $versi }}" />
                            </div>

                            {{-- O --}}
                            <div>
                                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg bg-emerald-100 dark:border-emerald-700">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">O</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Objective — Pemeriksaan</span>
                                </div>
                                <livewire:pages::transaksi.ri.coding-fhir-ri.pemeriksaan.coding-pemeriksaan-ri :riHdrNo="$riHdrNo"
                                    wire:key="coding-pemeriksaan-ri-{{ $riHdrNo }}-{{ $versi }}" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2 xl:grid-cols-2">
                            {{-- A --}}
                            <div>
                                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg bg-amber-100 dark:border-amber-700">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">A</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Assessment — Diagnosis</span>
                                </div>
                                <livewire:pages::transaksi.ri.coding-fhir-ri.diagnosa.coding-diagnosa-ri :riHdrNo="$riHdrNo"
                                    wire:key="coding-diagnosa-ri-{{ $riHdrNo }}-{{ $versi }}" />
                            </div>

                            {{-- P --}}
                            <div>
                                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg bg-rose-100 dark:border-rose-700">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full bg-rose-100 text-error dark:bg-rose-900/40 dark:text-rose-300">P</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Plan — Perencanaan</span>
                                </div>
                                <livewire:pages::transaksi.ri.coding-fhir-ri.perencanaan.coding-perencanaan-ri :riHdrNo="$riHdrNo"
                                    wire:key="coding-perencanaan-ri-{{ $riHdrNo }}-{{ $versi }}" />
                            </div>
                        </div>
                    </div>

                    {{-- YANG AKAN DIKIRIM — kartu SATUSEHAT RI mode pratinjau (tanpa tombol Kirim). Dimuat saat diminta. --}}
                    <div class="p-4 mt-4 space-y-3 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-base font-semibold text-ink dark:text-gray-100">Yang akan dikirim ke SATUSEHAT</p>
                                <p class="text-sm text-muted dark:text-gray-400">
                                    Pratinjau tiap kartu, dari sumber yang sama dengan tombol Kirim di modal SATUSEHAT — termasuk CPPT,
                                    penilaian, edukasi, discharge planning, diet, lab, radiologi, dan obat. Pengiriman tetap dari menu Kirim Satu Sehat.
                                </p>
                            </div>
                            <x-secondary-button type="button" wire:click="toggleKiriman" wire:loading.attr="disabled" wire:target="toggleKiriman">
                                <span wire:loading.remove wire:target="toggleKiriman">{{ $tampilkanKiriman ? 'Sembunyikan' : 'Tampilkan yang akan dikirim' }}</span>
                                <span wire:loading wire:target="toggleKiriman" class="flex items-center gap-1"><x-loading /> Memuat...</span>
                            </x-secondary-button>
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
                                                <livewire:dynamic-component :is="'pages::transaksi.ri.satu-sehat.kirim-' . $kartu"
                                                    :riHdrNo="$riHdrNo" :hanyaPratinjau="true"
                                                    :key="'coding-pratinjau-' . $kartu . '-' . $riHdrNo . '-' . $versi" />
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- FOOTER --}}
                <div class="sticky bottom-0 z-10 flex justify-end gap-2 px-6 py-2 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                    <x-secondary-button type="button" x-on:click="$dispatch('coding-fhir-ri-tutup')">Tutup</x-secondary-button>
                    <x-primary-button type="button" wire:click="simpan">Simpan Coding</x-primary-button>
                </div>
