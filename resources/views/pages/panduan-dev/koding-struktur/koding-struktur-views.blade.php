                    {{-- ====== 03 PETA DIREKTORI ====== --}}
                    <section x-show="section === 'peta-views'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — resources/views</div>
                        <h1 class="ds-display-md mb-4">Peta Direktori</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Ada <strong>tiga tempat</strong> komponen bisa tinggal, dan ketiganya punya tujuan
                            berbeda. Salah tempat tidak langsung error — ia baru terasa berat setahun kemudian.
                        </p>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">resources/views/</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['peta-views'] }}</pre>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Tiga tempat, tiga tujuan</h2>
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Folder</th><th>Isinya</th><th>Cara dipanggil</th><th>Kelas Volt?</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="ds-td-class">components/</td>
                                        <td>komponen presentasi anonim</td>
                                        <td class="ds-td-class">&lt;x-nama&gt;</td>
                                        <td>tidak — kalau butuh state, ia bukan penghuni sini</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-class">livewire/</td>
                                        <td>komponen ber-state <strong>lintas modul</strong> (LOV, dialog global)</td>
                                        <td class="ds-td-class">&lt;livewire:lov.dokter…&gt;</td>
                                        <td>wajib</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-class">pages/</td>
                                        <td>halaman + komponen ber-state <strong>milik halaman</strong></td>
                                        <td class="ds-td-class">Route::livewire / &lt;livewire:pages::…&gt;</td>
                                        <td>ya, kecuali partial</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:20px; border-color:var(--primary)">
                            <div class="ds-caption-up mb-2" style="color:var(--primary)">Uji cepat sebelum menaruh</div>
                            <ol class="ds-body-sm space-y-1.5">
                                <li>1. Punya state / <span class="ds-code">wire:model</span>? <strong>Tidak</strong> → <span class="ds-code">components/</span></li>
                                <li>2. Dipakai lebih dari satu area? <strong>Ya</strong> → <span class="ds-code">livewire/</span></li>
                                <li>3. Selain itu → <span class="ds-code">pages/&lt;area&gt;/&lt;modul&gt;/</span></li>
                            </ol>
                        </div>
                    </section>

                    {{-- ====== 04 PREFIX BOLT ====== --}}
                    <section x-show="section === 'bolt'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — resources/views</div>
                        <h1 class="ds-display-md mb-4">Prefix <span style="color:var(--primary)">⚡</span></h1>

                        <div class="ds-card-outline mb-6" style="padding:20px; border-color:var(--primary)">
                            <p class="ds-body-md" style="margin:0">
                                <span class="ds-code" style="color:var(--primary)">⚡</span>
                                = berkas ini <strong>SFC Volt berkelas</strong>
                                (<span class="ds-code">new [#[Layout]] class extends Component</span>).<br>
                                <strong>Tanpa <span class="ds-code">⚡</span></strong>
                                = partial markup murni yang di-<span class="ds-code">@@include</span>.
                            </p>
                        </div>

                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Ini konvensi native Livewire 4 dan sudah jadi default repo
                            (<span class="ds-code">config/livewire.php</span> →
                            <span class="ds-code">make_command.emoji = true</span>), jadi tiap
                            <span class="ds-code">php artisan make:livewire</span> otomatis patuh.
                        </p>

                        <h2 class="ds-title-lg mt-10 mb-4">Kenapa aturan ini, bukan “⚡ hanya untuk halaman ber-route”</h2>
                        <ul class="ds-body-sm space-y-2 mb-6" style="max-width:70ch">
                            <li>• Ia menjawab pertanyaan tersering saat maintenance: <em>ini komponen atau include?</em> — terjawab dari <span class="ds-code">ls</span>, tanpa membuka berkas.</li>
                            <li>• Prefix <span class="ds-code">⚡</span> <strong>tidak</strong> ikut dalam string resolusi Livewire: <span class="ds-code">pages::master.master-obat.master-obat</span> cocok dengan <span class="ds-code">⚡master-obat.blade.php</span>. Jadi menambah/melepasnya tidak memutus referensi.</li>
                            <li>• Saat audit, invariannya sudah benar satu arah: dari 142 berkas ber-<span class="ds-code">⚡</span>, <strong>0</strong> yang bukan SFC. Tidak ada yang perlu dibatalkan — hanya perlu dilengkapi.</li>
                        </ul>

                        <div class="ds-card-outline mb-6" style="padding:20px; border-color:var(--warning, var(--hairline))">
                            <div class="ds-caption-up mb-2" style="color:var(--warning, var(--primary))">Satu batas penting</div>
                            <p class="ds-body-sm" style="margin:0">
                                Kebal-referensi itu <strong>hanya</strong> berlaku untuk jalur resolver Livewire.
                                Kalau sebuah SFC memanggil <span class="ds-code">view('&lt;nama-dirinya&gt;')</span>,
                                ia mengikat nama berkas ke <strong>view finder</strong> — dan
                                <span class="ds-code">⚡</span> akan memutusnya. Karena itu:
                                <strong>jangan tulis <span class="ds-code">render()</span> di SFC</strong>
                                kecuali ia mengembalikan view yang <em>berbeda</em> dari dirinya.
                                (Tiga LOV pernah begitu — <span class="ds-code">render()</span> mubazir sisa
                                porting dari komponen berkelas.)
                            </p>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Cek kepatuhan — dua-duanya harus kosong</h2>
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">bash</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['cek-bolt'] }}</pre>
                        </div>
                    </section>

                    {{-- ====== 05 SUFFIX PERAN & JALUR ====== --}}
                    <section x-show="section === 'suffix'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — resources/views</div>
                        <h1 class="ds-display-md mb-4">Suffix Peran &amp; Jalur</h1>

                        <h2 class="ds-title-lg mb-4">Suffix peran</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Kolom “sebaran nyata” bukan hiasan — itu yang membuktikan aturannya bukan karangan.
                        </p>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Suffix</th><th>Peran</th><th>SFC atau partial?</th><th>Sebaran nyata</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-class">(tanpa suffix)</td><td>LIST / layar utama modul</td><td class="ds-td-strong">SFC</td><td>—</td></tr>
                                    <tr><td class="ds-td-class">-actions</td><td>modal create/edit + semua validate/insert/update/delete</td><td class="ds-td-strong">SFC</td><td>200 SFC, 0 partial</td></tr>
                                    <tr><td class="ds-td-class">-tab</td><td>isi satu tab dari layar bertab</td><td>partial</td><td>0 SFC, 56 partial</td></tr>
                                    <tr><td class="ds-td-class">-view</td><td>penampil read-only (viewer rekam medis)</td><td>partial</td><td>0 SFC, 8 partial</td></tr>
                                    <tr><td class="ds-td-class">-print</td><td>badan cetak dompdf</td><td>partial</td><td>0 SFC, 88 partial</td></tr>
                                    <tr><td class="ds-td-class">-&lt;bagian&gt;</td><td>pecahan section dari berkas yang kebesaran</td><td>partial</td><td>—</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mb-8" style="padding:20px; border-color:var(--primary)">
                            <p class="ds-body-sm" style="margin:0">
                                <strong>Hanya layar utama dan <span class="ds-code">-actions</span> yang jadi komponen.</strong>
                                Sisanya partial — konsisten dengan bab Batas Ukuran: memecah berkas dilakukan dengan
                                <span class="ds-code">@@include</span>, bukan dengan menambah komponen Livewire anak
                                (tiap anak = satu round-trip + satu titik race Alpine/morph). Jadi
                                <span class="ds-code">-tab</span> <em>bukan</em> komponen per tab.
                            </p>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Suffix jalur — untuk SEMUA modul-dokumen</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Setiap folder &amp; berkas di bawah <span class="ds-code">emr-&lt;jalur&gt;/modul-dokumen/</span>
                            menyandang jalurnya — <strong>bukan hanya</strong> yang kebetulan ada di lebih dari satu jalur.
                            Alasannya: RI sudah begitu di 38/38 folder, dan patokan “hanya kalau multi-jalur” akan memaksa
                            23 folder RI <em>dilepas</em> suffix-nya — arah yang salah. Suffix juga berfungsi sebagai
                            penanda “ini EMR yang mana” saat berkas dibuka sendirian di editor.
                        </p>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['suffix-jalur'] }}</pre>
                        </div>
                        <p class="ds-body-sm mb-8" style="max-width:62ch; color:var(--body)">
                            Berlaku juga untuk partial di dalamnya: <span class="ds-code">suket-rj/tabs/suket-sehat-rj-tab.blade.php</span>
                            — jalur ditulis <strong>sebelum</strong> suffix peran (<span class="ds-code">-rj-tab</span>,
                            bukan <span class="ds-code">-tab-rj</span>).
                        </p>

                        <h2 class="ds-title-lg mt-10 mb-4">Akronim: utuh &amp; huruf kecil</h2>
                        <div class="grid grid-cols-1 gap-4 mb-8 md:grid-cols-2">
                            <div class="ds-card-outline" style="padding:18px">
                                <div class="ds-caption-up mb-2" style="color:var(--primary)">Benar</div>
                                <div class="ds-code ds-body-sm">ri/ &nbsp; rj/ &nbsp; ugd/ &nbsp; bpjs/ &nbsp; ok/ &nbsp; emr/</div>
                            </div>
                            <div class="ds-card-outline" style="padding:18px">
                                <div class="ds-caption-up mb-2" style="color:var(--muted-soft)">Salah</div>
                                <div class="ds-code ds-body-sm">r-i/ &nbsp; u-g-d/ &nbsp; b-p-j-s/ &nbsp; o-k-ri.blade.php</div>
                                <div class="ds-caption mt-2" style="color:var(--muted-soft)">artefak konversi PascalCase (RI → r-i), bukan keputusan desain</div>
                            </div>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Nama berkas ≠ kunci data</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Aturan kebab-case berlaku untuk <strong>nama berkas</strong>, bukan untuk nilai yang
                            sudah tersimpan di DB. Sebelum me-rename apa pun, tanyakan satu hal:
                            <strong>apakah nama ini pernah ditulis ke DB?</strong>
                        </p>
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">components/site-marking-diagram.blade.php</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['kunci-data'] }}</pre>
                        </div>
                    </section>
