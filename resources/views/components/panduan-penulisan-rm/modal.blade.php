{{--
    Modal Panduan Penulisan Rekam Medis — dibuka lewat event open-modal name=panduan-penulisan-rm
    (tombol bantuan di topbar). Isi: aturan SPO 382 + Lampiran I/II SK 006 dari App\Support\SingkatanRekamMedis.
    Daftar dirender Alpine (x-for) dari JSON supaya HTML layout tetap ringan; pencarian & tab
    tanpa round-trip Livewire. Dipasang sekali di layouts/app, sesudah main, agar tampil di atas modal EMR.
--}}
@php
    $singkatanRm = \App\Support\SingkatanRekamMedis::class;
    $daftarPanduan = $singkatanRm::untukPanduan();
@endphp

<x-modal name="panduan-penulisan-rm" size="full" height="full">
    <div class="flex flex-col h-full" x-data="{
        tab: 'wajib',
        cari: '',
        konteksAktif: '',
        ...@js($daftarPanduan),
        cocokKonteks(row) { return !this.konteksAktif || row.konteks.includes(this.konteksAktif) },
        cocok(row) {
            return this.cocokKonteks(row) && (!this.cari || row.cari.includes(this.cari.trim().toLowerCase()))
        },
        get konteksTerpilih() { return this.konteks.find(item => item.id === this.konteksAktif) },
        get dilarangTampil() { return this.dilarang.filter(row => this.cocok(row)) },
        get singkatanBolehTampil() { return this.singkatanBoleh.filter(row => this.cocok(row)) },
        get simbolBolehTampil() { return this.simbolBoleh.filter(row => this.cocok(row)) },
        get doNotUse() { return this.dilarang.filter(row => row.doNotUse && this.cocokKonteks(row)) },
        get jumlahCocok() {
            return this.dilarangTampil.length + this.singkatanBolehTampil.length + this.simbolBolehTampil.length
        },
    }">

        {{-- HEADER — komponen baku modul dokumen; tutup lewat Alpine karena modal ini di luar Livewire --}}
        <x-modul-dokumen.header judul="Panduan Penulisan Rekam Medis" namaModal="panduan-penulisan-rm"
            ikon="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253">
            Singkatan & simbol · SPO {{ $singkatanRm::SUMBER_SPO }} · SK {{ $singkatanRm::SUMBER_SK }}
        </x-modul-dokumen.header>

        {{-- TOOLBAR — cari + tab --}}
        <div class="px-6 pt-4 border-b shrink-0 border-hairline dark:border-gray-700">
            <x-text-input type="search" x-model="cari" class="text-sm"
                placeholder="Cari singkatan / simbol / arti, mis. Px, qd, cc, Hb..." />

            {{-- Konteks penulisan — pengelompokan bantu, bukan bagian SK --}}
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <span class="text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Konteks penulisan</span>
                <x-tabs variant="chip">
                    <x-tab active-expr="konteksAktif === ''" x-on:click="konteksAktif = ''" class="!px-3 !py-1 !text-xs">Semua</x-tab>
                    <template x-for="item in konteks" :key="item.id">
                        <x-tab active-expr="konteksAktif === item.id" x-on:click="konteksAktif = item.id"
                            class="!px-3 !py-1 !text-xs" x-text="item.label"></x-tab>
                    </template>
                </x-tabs>
            </div>
            <p x-show="konteksTerpilih" x-cloak class="mt-1.5 text-xs text-muted dark:text-gray-400">
                Dipakai di: <span x-text="konteksTerpilih?.dipakaiDi"></span>.
                Pengelompokan ini bantuan pencarian — status boleh/dilarang tetap mengikuti Lampiran SK.
            </p>

            <x-tabs variant="underline" class="mt-3" x-show="!cari">
                <x-tab active-expr="tab === 'wajib'" x-on:click="tab = 'wajib'">Wajib Diingat</x-tab>
                <x-tab active-expr="tab === 'dilarang'" color="rose" x-on:click="tab = 'dilarang'">
                    Dilarang (<span x-text="dilarangTampil.length"></span>)
                </x-tab>
                <x-tab active-expr="tab === 'boleh'" color="emerald" x-on:click="tab = 'boleh'">
                    Boleh (<span x-text="singkatanBolehTampil.length + simbolBolehTampil.length"></span>)
                </x-tab>
            </x-tabs>
            <p x-show="cari" x-cloak class="py-3 text-sm text-muted dark:text-gray-400">
                <span x-text="jumlahCocok"></span> hasil. Huruf besar/kecil bisa beda arti (mis. Hb boleh, HB dilarang).
                <span x-show="jumlahCocok === 0" class="font-semibold text-rose-700 dark:text-rose-300">
                    Tidak ada di daftar BOLEH berarti tidak boleh dipakai. Tulis lengkap.
                </span>
            </p>
        </div>

        {{-- BODY --}}
        <div class="flex flex-col flex-1 min-h-0 gap-6 px-6 py-5 overflow-y-auto">

            {{-- WAJIB DIINGAT --}}
            <section x-show="!cari && tab === 'wajib'" class="space-y-5">
                {{-- Panel panduan biru-info standar (lihat gaji-dokter). Beda dari standar: default TERBUKA,
                     karena aturan ini isi utama tab Wajib Diingat, bukan panduan pelengkap form. --}}
                <div x-data="{ buka: true }"
                    class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                    <button type="button" x-on:click="buka = !buka"
                        class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                        <span class="flex items-center min-w-0 gap-2">
                            <svg class="w-4 h-4 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="truncate">Panduan: aturan penulisan rekam medis (SPO {{ $singkatanRm::SUMBER_SPO }})</span>
                        </span>
                        <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="buka" class="px-4 pb-4 text-sm text-blue-900 dark:text-blue-100">
                        <ol class="ml-4 space-y-1 list-decimal">
                            @foreach ($singkatanRm::aturan() as $aturan)
                                <li>{{ $aturan }}</li>
                            @endforeach
                        </ol>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-300">
                        Paling berbahaya — daftar "Do Not Use"
                    </h3>
                    <x-modul-dokumen.tabel-daftar :kolom="['Jangan tulis', 'Bisa terbaca / tertukar', 'Tulis']">
                        <tbody>
                            <template x-for="row in doNotUse" :key="row.kode">
                                <tr class="border-t border-hairline dark:border-gray-800">
                                    <td class="px-4 py-3 font-mono font-semibold align-middle whitespace-nowrap text-rose-700 dark:text-rose-300" x-text="row.kode"></td>
                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300" x-text="row.bisaTerbaca ?? row.arti"></td>
                                    <td class="px-4 py-3 font-medium align-middle text-emerald-700 dark:text-emerald-300" x-text="row.tulis ?? ''"></td>
                                </tr>
                            </template>
                        </tbody>
                    </x-modul-dokumen.tabel-daftar>
                </div>
            </section>

            {{-- DILARANG --}}
            <section x-show="(cari && dilarangTampil.length) || (!cari && tab === 'dilarang')" x-cloak class="flex flex-col gap-3">
                <div x-show="cari" class="text-sm font-semibold tracking-wide uppercase text-rose-700 dark:text-rose-300">Dilarang</div>
                <x-modul-dokumen.tabel-daftar :kolom="['Singkatan / simbol', 'Arti / alasan dilarang', 'Bisa terbaca / tertukar', 'Tulis']">
                    <tbody>
                        <template x-for="row in dilarangTampil" :key="row.kode">
                            <tr class="border-t border-hairline dark:border-gray-800">
                                <td class="px-4 py-3 font-mono font-semibold align-middle text-rose-700 dark:text-rose-300">
                                    <span x-text="row.kode"></span>
                                    <x-badge variant="danger" class="ml-1 font-sans whitespace-nowrap" x-show="row.doNotUse">Do Not Use</x-badge>
                                </td>
                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300" x-text="row.arti || '—'"></td>
                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300" x-text="row.bisaTerbaca ?? '—'"></td>
                                <td class="px-4 py-3 font-medium align-middle text-emerald-700 dark:text-emerald-300" x-text="row.tulis ?? 'tulis lengkap'"></td>
                            </tr>
                        </template>
                    </tbody>
                </x-modul-dokumen.tabel-daftar>
            </section>

            {{-- BOLEH --}}
            <section x-show="(cari && (singkatanBolehTampil.length || simbolBolehTampil.length)) || (!cari && tab === 'boleh')"
                x-cloak class="flex flex-col gap-5">
                <div x-show="cari" class="text-sm font-semibold tracking-wide uppercase text-emerald-700 dark:text-emerald-300">Boleh</div>

                <div x-show="singkatanBolehTampil.length">
                    <h3 x-show="!cari" class="mb-2 text-sm font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-300">
                        Singkatan (<span x-text="singkatanBolehTampil.length"></span>)
                    </h3>
                    <div class="grid grid-cols-1 gap-x-6 sm:grid-cols-2 lg:grid-cols-3">
                        <template x-for="row in singkatanBolehTampil" :key="row.kode">
                            <div class="flex gap-3 py-1.5 text-sm border-b border-hairline dark:border-gray-800">
                                <span class="w-20 font-mono font-semibold shrink-0 text-emerald-700 dark:text-emerald-300" x-text="row.kode"></span>
                                <span class="text-body dark:text-gray-300" x-text="row.arti"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div x-show="simbolBolehTampil.length">
                    <h3 x-show="!cari" class="mb-2 text-sm font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-300">Simbol</h3>
                    <div class="grid grid-cols-1 gap-x-6 sm:grid-cols-2 lg:grid-cols-3">
                        <template x-for="row in simbolBolehTampil" :key="row.kode">
                            <div class="flex gap-3 py-1.5 text-sm border-b border-hairline dark:border-gray-800">
                                <span class="w-20 font-semibold text-center shrink-0 text-emerald-700 dark:text-emerald-300" x-text="row.kode"></span>
                                <span class="text-body dark:text-gray-300" x-text="row.arti"></span>
                            </div>
                        </template>
                    </div>
                    <div x-show="!cari" class="mt-3 text-sm text-body dark:text-gray-300">
                        <span class="font-semibold">Penyakit menular:</span>
                        @foreach ($singkatanRm::warnaPenyakitMenular() as $row)
                            <span class="mr-3">{{ $row['kode'] }} = {{ $row['arti'] }}</span>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>

        {{-- FOOTER — pola footer modul dokumen (layar daftar): petunjuk kiri, Tutup kanan --}}
        <div class="sticky bottom-0 z-10 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-end gap-2">
                <p class="flex items-center gap-1.5 mr-auto text-sm text-muted dark:text-gray-400">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Ragu? <strong>Tulis lengkap.</strong> Singkatan baru diusulkan ke Komite/Tim Rekam Medis melalui kepala unit.</span>
                </p>
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'panduan-penulisan-rm' })">Tutup</x-secondary-button>
            </div>
        </div>
    </div>
</x-modal>
