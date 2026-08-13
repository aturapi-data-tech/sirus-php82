                    {{-- ====== 01 PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Struktur Folder &amp; Penamaan Berkas</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Standar ini menjawab satu pertanyaan yang muncul tiap kali ada modul baru:
                            <strong>berkas ini ditaruh di mana, dan dinamai apa?</strong> Tujuannya supaya
                            programmer baru bisa <em>menebak</em> lokasi berkas tanpa <span class="ds-code">grep</span>,
                            dan programmer lama tidak perlu memutuskan ulang tiap kali.
                        </p>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Halaman ini adalah versi web dari
                            <span class="ds-code" style="color:var(--primary)">docs/standar-struktur-folder.md</span>.
                        </p>

                        <div class="grid grid-cols-1 gap-4 mb-8 md:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--primary)">Yang diatur di sini</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    <li>• Pohon direktori <span class="ds-code">app/</span> &amp; <span class="ds-code">resources/views/</span></li>
                                    <li>• Nama folder &amp; nama berkas</li>
                                    <li>• Arti prefix <span class="ds-code">⚡</span> dan suffix peran</li>
                                    <li>• Batas Trait vs Support</li>
                                    <li>• Keselarasan URL ↔ folder ↔ nama route</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--muted-soft)">Yang TIDAK diatur di sini</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    <li>• Isi &amp; anatomi modul master → <span class="ds-code">koding-master</span></li>
                                    <li>• Anatomi modul dokumen bertanda tangan</li>
                                    <li>• Markup di dalam berkas (frame, tabel, modal)</li>
                                    <li>• Komponen UI &amp; tombol</li>
                                </ul>
                            </div>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Angka repo saat standar ini dibuat</h2>
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Apa</th><th>Jumlah</th><th>Catatan</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Berkas Blade</td><td>1.095</td><td>691 SFC Volt + 404 partial</td></tr>
                                    <tr><td class="ds-td-strong">SFC ber-<span class="ds-code">⚡</span></td><td>691</td><td>100% patuh, tanpa pengecualian</td></tr>
                                    <tr><td class="ds-td-strong">Partial ber-<span class="ds-code">⚡</span></td><td>0</td><td>harus selalu 0</td></tr>
                                    <tr><td class="ds-td-strong">Berkas PHP <span class="ds-code">app/</span></td><td>128</td><td>68 trait, 38 support</td></tr>
                                    <tr><td class="ds-td-strong"><span class="ds-code">Route::livewire</span></td><td>137</td><td>+ 20 redirect URL lama</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {{-- ====== 02 PRINSIP ====== --}}
                    <section x-show="section === 'prinsip'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">6 Prinsip</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Semua aturan di bab-bab berikutnya turun dari enam prinsip ini. Kalau suatu
                            kasus tidak tercakup aturan mana pun, kembalikan ke prinsip.
                        </p>

                        @foreach ([
                            ['P1', 'Satu modul = satu folder.',
                             'Semua berkas milik satu layar hidup berdampingan. Tidak ada berkas modul yang nyempil di folder induk.'],
                            ['P2', 'Nama folder = nama modul = nama berkas utama = namespace event.',
                             'master-agama/ → ⚡master-agama.blade.php → event master.agama.*. Empat-empatnya harus sinkron; kalau satu berubah, semuanya ikut.'],
                            ['P3', 'Lokasi mengikuti PEMILIK, bukan pemakai.',
                             'Cetakan dokumen RI dipakai banyak layar → naik ke pages/components/. Kalau cuma dipakai satu layar, ia tinggal di folder modulnya.'],
                            ['P4', 'Prefix ⚡ menandai komponen; ketiadaannya menandai partial.',
                             'Terlihat langsung dari ls mana yang punya state dan mana yang cuma markup — tanpa membuka berkas.'],
                            ['P5', 'Kebab-case, Bahasa Indonesia, tanpa singkatan baru.',
                             'Akronim domain yang sudah baku (ri, rj, ugd, emr, lov, sep, idrg) dipakai apa adanya — dan ditulis utuh huruf kecil, bukan dipecah per huruf.'],
                            ['P6', 'Kedalaman maksimum 4 level di bawah pages/.',
                             'Lebih dalam dari itu hanya untuk pengecualian resmi (laporan SIRS/RL yang formatnya ditentukan Kemkes).'],
                        ] as [$kode, $judul, $isi])
                            <div class="ds-card-outline mb-3" style="padding:18px 20px">
                                <div class="flex items-start gap-3">
                                    <span class="ds-code shrink-0" style="color:var(--primary); font-weight:700">{{ $kode }}</span>
                                    <div>
                                        <div class="ds-body-md" style="font-weight:600; color:var(--ink)">{{ $judul }}</div>
                                        <div class="ds-body-sm mt-1" style="color:var(--body)">{{ $isi }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </section>
