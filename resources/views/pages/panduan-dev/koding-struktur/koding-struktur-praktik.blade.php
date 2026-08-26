                    {{-- ====== 10 TUJUH PEMERIKSA ====== --}}
                    <section x-show="section === 'pemeriksa'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Praktik</div>
                        <h1 class="ds-display-md mb-4">7 Pemeriksa</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Rename &amp; pindah berkas <strong>tidak</strong> cukup diverifikasi satu cara.
                            Tiap mekanisme resolusi Laravel punya titik buta sendiri — dan tiap pemeriksa di
                            bawah lahir karena satu titik buta itu pernah kena.
                        </p>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>#</th><th>Pemeriksa</th><th>Titik buta yang ia tutup</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">1</td><td>Kompilasi Blade standalone</td><td>struktur directive, tag komponen, blok Volt tak sah</td></tr>
                                    <tr><td class="ds-td-strong">2</td><td>Resolusi komponen Livewire</td><td><span class="ds-code">&lt;livewire:…&gt;</span> &amp; <span class="ds-code">Route::livewire</span></td></tr>
                                    <tr><td class="ds-td-strong">3</td><td>Nama view literal</td><td><strong>resolver Livewire tidak menjangkau ini</strong> — <span class="ds-code">loadView</span> cetak PDF lewat view finder</td></tr>
                                    <tr><td class="ds-td-strong">4</td><td>Resolusi kelas (<span class="ds-code">Foo::</span>)</td><td>FQCN yang putus setelah pindah namespace</td></tr>
                                    <tr><td class="ds-td-strong">5</td><td>Impor trait</td><td><strong>pemeriksa kelas tidak menjangkau ini</strong> — trait dipakai lewat <span class="ds-code">use</span>, bukan <span class="ds-code">Foo::</span></td></tr>
                                    <tr><td class="ds-td-strong">6</td><td>Nama route</td><td><span class="ds-code">route('…')</span> &amp; entri AppMenu</td></tr>
                                    <tr><td class="ds-td-strong">7</td><td>Request nyata (HTTP kernel)</td><td>redirect URL lama benar-benar menjawab 302</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">inti tiap pemeriksa</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['pemeriksa'] }}</pre>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Tiga jebakan perkakas yang sudah pernah menipu</h2>
                        @foreach ([
                            ['app(Finder::class) memberi instance BARU yang kosong',
                             'Resolver yang benar app(\'livewire.finder\'). Dengan yang salah, 690 referensi tampak GAGAL SEMUA — seperti kerusakan masif, padahal pemeriksanya yang salah. Selalu sanity-check dengan satu nama yang pasti ada sebelum mempercayai hasilnya.'],
                            ['Tinker meng-alias kelas ke global namespace',
                             'Rujukan yang benar-benar putus tetap tampak resolve (DischargeDisposition, NyeriOptions, EresepJson pernah begitu). Jalankan pemeriksa kelas di luar Tinker, pakai bootstrap sendiri.'],
                            ['<x-…> di komentar // blok kelas SFC jadi parse error PALSU',
                             'Compiler SFC Livewire memisahkan blok kelas SEBELUM Blade jalan. Pemeriksa harus meniru itu: pisahkan blok kelas sampai penutup PHP dulu, baru kompilasi templatnya. Tanpa ini, 5 berkas sehat dilaporkan rusak.'],
                        ] as [$judul, $isi])
                            <div class="ds-card-outline mb-3" style="padding:18px 20px">
                                <div class="ds-body-md" style="font-weight:600; color:var(--ink)">{{ $judul }}</div>
                                <div class="ds-body-sm mt-1" style="color:var(--body)">{{ $isi }}</div>
                            </div>
                        @endforeach

                        <div class="ds-card-outline mt-6" style="padding:20px; border-color:var(--primary)">
                            <div class="ds-caption-up mb-2" style="color:var(--primary)">Sesudah rename massal</div>
                            <p class="ds-body-sm" style="margin:0">
                                Kosongkan <span class="ds-code">storage/framework/views/</span> secara manual.
                                <span class="ds-code">php artisan view:clear</span> butuh boot aplikasi — yang
                                justru tidak tersedia saat Oracle mati.
                            </p>
                        </div>
                    </section>

                    {{-- ====== 11 CHECKLIST & REFERENSI ====== --}}
                    <section x-show="section === 'checklist'" x-cloak>
                        <div class="ds-eyebrow mb-3">11 — Praktik</div>
                        <h1 class="ds-display-md mb-4">Checklist &amp; Referensi</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Sebelum modul baru di-merge, semua butir ini harus terpenuhi:
                        </p>

                        <div class="ds-card-outline mb-10" style="padding:24px">
                            <ul class="ds-body-sm space-y-2.5">
                                @foreach ([
                                    'Satu folder baru di pages/<area>/, nama kebab-case Indonesia, sama dengan nama berkas utama',
                                    'Berkas utama + -actions ber-⚡; partial tanpa ⚡',
                                    'Ada suffix jalur bila modul hidup di lebih dari satu jalur',
                                    'Route: segmen URL pertama = folder area = prefix nama route',
                                    'Namespace event = nama folder (master.<folder>.*)',
                                    'Cetakan/viewer yang dipakai >1 layar naik ke pages/components/<domain>/<jalur>/',
                                    'Logika stateless → app/Support/<SubNamespace>/; mixin komponen → app/Http/Traits/<Grup>/',
                                    'Tidak ada berkas melebihi batas ukuran sejak lahir',
                                    'Tidak menambah model Eloquent untuk tabel Oracle warisan',
                                    'Dua pemeriksa ⚡ kembali kosong',
                                ] as $item)
                                    <li class="flex items-start gap-2.5">
                                        <svg class="w-4 h-4 mt-0.5 shrink-0" style="color:var(--primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Referensi</h2>
                        <div class="ds-card-outline mb-10" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Apa</th><th>Di mana</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Dokumen sumber halaman ini</td><td class="ds-td-class">docs/standar-struktur-folder.md</td></tr>
                                    <tr><td class="ds-td-strong">Anatomi ISI modul master</td><td class="ds-td-class">docs/standar-master-module.md — /panduan-dev/koding-master</td></tr>
                                    <tr><td class="ds-td-strong">Anatomi modul dokumen bertanda tangan</td><td class="ds-td-class">docs/modul-dokumen-ri-pattern.md</td></tr>
                                    <tr><td class="ds-td-strong">Frame halaman &amp; tabel full-height</td><td class="ds-td-class">docs/page-frame-pattern.md</td></tr>
                                    <tr><td class="ds-td-strong">Komponen UI &amp; tombol</td><td class="ds-td-class">docs/standar-ui-komponen.md, docs/standar-komponen-tombol.md</td></tr>
                                    <tr><td class="ds-td-strong">Penamaan variable/method &amp; aturan use</td><td class="ds-td-class">skill naming-conventions</td></tr>
                                    <tr><td class="ds-td-strong">Keselamatan edit/rename massal Blade</td><td class="ds-td-class">skill blade-safe-edit</td></tr>
                                    <tr><td class="ds-td-strong">Generator LOV</td><td class="ds-td-class">app/Console/Commands/MakeLov.php</td></tr>
                                    <tr><td class="ds-td-strong">Konfigurasi lokasi komponen &amp; ⚡</td><td class="ds-td-class">config/livewire.php</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Sisa pekerjaan</h2>
                        <div class="ds-card-outline" style="padding:20px">
                            <p class="ds-body-sm" style="margin:0 0 10px 0">
                                Backlog penyeragaman di dokumen sumber sudah selesai <strong>kecuali satu</strong>:
                                11 berkas masih di atas 1.500 baris.
                            </p>
                            <p class="ds-body-sm" style="margin:0; color:var(--body)">
                                Dan itu <strong>bukan</strong> pekerjaan pemecahan markup lagi — bulk-nya blok kelas
                                Volt. Memecah markup tidak akan menurunkannya ke bawah ambang; yang perlu dikurangi
                                kelasnya (pisah ke trait/Support), dan itu keputusan desain per modul yang layak
                                dikerjakan saat modul itu disentuh.
                            </p>
                        </div>
                    </section>
