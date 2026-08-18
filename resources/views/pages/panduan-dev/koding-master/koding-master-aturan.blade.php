                    {{-- ====== 10 VALIDASI ====== --}}
                    <section x-show="section === 'validasi'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Aturan</div>
                        <h1 class="ds-display-md mb-4">Validasi</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Dua aturan mati: pesan <strong>selalu Bahasa Indonesia</strong>, dan
                            <span class="ds-code">validate()</span> dipanggil <strong>sebelum logika lain</strong>
                            di save() — early-return sebelum validate membuat border merah field wajib
                            tidak pernah muncul.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Form kecil — array inline</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['validasi-inline'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Form besar — method terpisah</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['validasi-method'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Rule <span class="ds-code">unique</span> hanya saat create:
                                <span class="ds-code">$this->formMode === 'create' ? 'required|...|unique:tabel,kolom' : 'required|...'</span>
                                — dan field PK di-<span class="ds-code">:disabled</span> saat edit.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 11 DELETE ====== --}}
                    <section x-show="section === 'delete'" x-cloak>
                        <div class="ds-eyebrow mb-3">11 — Aturan</div>
                        <h1 class="ds-display-md mb-4">Delete &amp; ORA-02292</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Delete selalu <strong>dua lapis pengaman</strong>. Lapis 1 di UI:
                            konfirmasi lewat <span class="ds-code">x-action-delete</span> (dialog konfirmasi,
                            bukan <span class="ds-code">wire:confirm</span> browser-native). Lapis 2 di server:
                            tangkap <span class="ds-code">ORA-02292</span> (child record found) dan ubah jadi
                            toast yang manusiawi — tanpa ini user melihat error 500.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Handler delete standar</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['delete'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Opsional (dianjurkan utk master bervolume tinggi):</strong> cek eksplisit
                                tabel pemakai sebelum delete supaya pesannya lebih spesifik — contoh
                                <span class="ds-code">master-pasien-actions</span> mengecek
                                rstxn_rjhdrs / ugdhdrs / rihdrs lebih dulu.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 12 PARTIAL ====== --}}
                    <section x-show="section === 'partial'" x-cloak>
                        <div class="ds-eyebrow mb-3">12 — Aturan</div>
                        <h1 class="ds-display-md mb-4">Ukuran File &amp; Partial</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Batas kewajaran: LIST ≤ ±300 baris, FORM ≤ ±400 baris. Lewat dari itu —
                            atau form punya lebih dari satu section logis — pecah markup jadi
                            <strong>partial per section</strong>. Partial adalah markup murni
                            (tanpa kelas Volt); seluruh state tetap di file induk.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Contoh nyata — master-pasien (10 partial)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['pasien-tree'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Penamaan partial: <span class="ds-code">master-&lt;x&gt;-actions-&lt;section&gt;.blade.php</span>,
                                tanpa prefix ⚡ (bukan komponen Livewire). Jangan memindahkan state atau
                                method ke partial — hanya markup.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 13 VARIAN ====== --}}
                    <section x-show="section === 'varian'" x-cloak>
                        <div class="ds-eyebrow mb-3">13 — Lanjutan</div>
                        <h1 class="ds-display-md mb-4">Varian &amp; Level Kompleksitas</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Tidak semua master sama beratnya: ada yang <strong>biasa</strong> (poli, agama),
                            ada yang <strong>expert</strong> (kamar hierarkis, jasa medis dengan paket &amp; tarif).
                            Prinsipnya: <strong>selalu mulai dari Level 1</strong> — naikkan level hanya kalau
                            domainnya memang menuntut, dan teknik tambahannya pun tetap terstandar.
                        </p>

                        {{-- tabel level --}}
                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Level</th><th>Ciri</th><th>Teknik tambahan yang dipakai</th><th>Contoh modul</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="ds-td-strong">1 · Dasar</td>
                                        <td class="ds-body-sm">Satu tabel, CRUD murni</td>
                                        <td class="ds-body-sm">Pola bab 01–11 persis, tanpa tambahan</td>
                                        <td class="ds-td-class">agama · poli · kelas-rawat · stocklocations · signa-catatan</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">2 · Menengah</td>
                                        <td class="ds-body-sm">CRUD + FK / status / query berat</td>
                                        <td class="ds-body-sm">LOV (bab 08) · <span class="ds-code">baseQuery()</span> terpisah · <span class="ds-code">toggleActive</span> · rules/messages sbg method · form bertab + partial</td>
                                        <td class="ds-td-class">obat · obat-kronis · dokter · karyawan · diagnosa · pasien</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">3 · Expert</td>
                                        <td class="ds-body-sm">Hierarki induk-anak / sub-list di dalam form</td>
                                        <td class="ds-body-sm">Verb event spesifik + payload konteks · panel detail · sub-form dgn validasi bertahap · tarif per kelas</td>
                                        <td class="ds-td-class">kamar (bangsal→kamar→bed) · laborat (clab→clabitem) · interaksi-obat · jasa-medis / jasa-dokter</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h2 class="ds-title-lg mb-3">Tiga varian struktur resmi</h2>
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Varian</th><th>Kapan dipakai</th><th>Contoh acuan</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="ds-td-strong">Master-detail hierarkis</td>
                                        <td class="ds-body-sm">Data induk-anak dikelola satu layar. Namespace event bersama + verb spesifik (mis. <span class="ds-code">master.kamar.openCreateBangsal</span>); child list embedded tanpa page-title/frame penuh.</td>
                                        <td class="ds-td-class">master-kamar (bangsal→kamar→bed)<br>master-laborat (clab→clabitem)<br>master-interaksi-obat (hdr→dtl)</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">Form bertab</td>
                                        <td class="ds-body-sm">Field sangat banyak / multi-section. Tab via Alpine <span class="ds-code">activeTab</span> + partial per tab.</td>
                                        <td class="ds-td-class">master-pasien</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">Single-file integrasi</td>
                                        <td class="ds-body-sm">Bukan CRUD murni — sinkronisasi API eksternal. Trait API ikut pola <span class="ds-code">docs/trait-template-api-eksternal.md</span>.</td>
                                        <td class="ds-td-class">setup-jadwal-bpjs<br>registrasi-aplicares-sirs</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Varian ≠ bebas aturan: kontrak penamaan state, validasi Indonesia,
                                guard ORA-02292, dan komponen UI standar tetap berlaku di ketiganya.
                            </span>
                        </div>

                        {{-- deep-dive expert A: hierarki --}}
                        <h2 class="ds-title-lg mt-10 mb-2">Expert A — Hierarki induk-anak (master-kamar)</h2>
                        <p class="ds-body-md mb-2" style="max-width:62ch">
                            Saat satu layar mengelola beberapa entitas bertingkat, verb event generik
                            (<span class="ds-code">openCreate</span>) jadi ambigu — buka form apa?
                            Solusinya: <strong>verb spesifik per entitas</strong> dalam satu namespace,
                            dan event <span class="ds-code">saved</span> membawa payload konteks
                            supaya list tahu bagian mana yang perlu di-refresh.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pola hierarki — ⚡master-kamar.blade.php</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['level-kamar'] }}</pre>
                        </div>

                        <p class="ds-body-md mt-6 mb-2" style="max-width:62ch">
                            CRUD per entitasnya sendiri <strong>tidak berubah</strong> dari bab 05–11:
                            tiap entitas (bangsal, kamar, bed) punya file actions + modal sendiri.
                            Yang benar-benar baru di Level 3 hanya <strong>tiga hal</strong> berikut —
                            ditunjukkan lewat kode asli modul kamar:
                        </p>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">1 · CRUD entitas anak — konteks induk ikut ke mana-mana</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['level-kamar-bed-actions'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">2 · Delete induk — cek anak dulu, baru jaring ORA-02292</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['level-kamar-delete-guard'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">3 · Refresh presisi — satu listener saved, payload yang menentukan</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['level-kamar-refresh'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Rantai lengkapnya: klik "Tambah Bed" di panel detail →
                                <span class="ds-code">openCreateBed(roomId)</span> → modal bed simpan →
                                <span class="ds-code">saved(entity: 'bed', roomId)</span> →
                                list me-refresh <em>hanya</em> panel bed kamar itu. Kode acuan utuh:
                                <span class="ds-code">pages/master/master-kamar/</span> — header file
                                utamanya memuat diagram struktur folder &amp; alur event selengkapnya.
                            </span>
                        </div>

                        {{-- deep-dive expert B: sub-list dalam form --}}
                        <h2 class="ds-title-lg mt-10 mb-2">Expert B — Sub-list di dalam form (master-jasa-medis)</h2>
                        <p class="ds-body-md mb-2" style="max-width:62ch">
                            Form yang menyimpan header + baris detail (paket obat, komponen tarif)
                            memakai <strong>validasi bertahap</strong>: tiap tombol "Tambah" pada sub-form
                            memvalidasi field sub-form itu saja, barisnya masuk array di
                            <span class="ds-code">$form</span>, dan <span class="ds-code">save()</span>
                            memvalidasi form utama lalu menyimpan header + loop detail.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pola sub-list — ⚡master-jasa-medis-actions.blade.php</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['level-jm'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Teknik expert lain yang sudah terstandar</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><strong>Tarif per kelas</strong>: baris tarif per kelas kamar (ACTP/ACTD-CLASSES) dikelola lewat <strong>modal tersendiri</strong> — acuan: modal Tarif V&amp;K di master-dokter, LOV jasa per kelas</li>
                                    <li><strong>toggleActive</strong>: aktif/nonaktif baris tanpa hapus (kolom <span class="ds-code">active_status '1'/'0'</span>) — dokter, kamar, jasa-medis</li>
                                    <li><strong>Panel detail</strong> di list (computed <span class="ds-code">selectedRoom()</span>) utk data anak yang sering dilihat</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Batas yang tidak boleh dilewati</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>Level 3 <strong>bukan izin</strong> menaruh <span class="ds-code">validate()</span>/simpan di komponen LIST — panel tarif inline di list master-dokter adalah <strong>backlog perbaikan</strong>, bukan contoh</li>
                                    <li>Verb spesifik tetap berpola <span class="ds-code">openCreate&lt;Entitas&gt;</span> / <span class="ds-code">requestDelete&lt;Entitas&gt;</span> — jangan mengarang verb baru</li>
                                    <li>Kalau ragu modulmu Level berapa: mulai Level 1; kompleksitas ditambah belakangan jauh lebih murah daripada dibongkar</li>
                                </ul>
                            </div>
                        </div>
                    </section>