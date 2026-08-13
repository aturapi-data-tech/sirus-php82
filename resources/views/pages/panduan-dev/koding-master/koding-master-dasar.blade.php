                    {{-- ====== 01 PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Standarisasi Koding Modul Master</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Tutorial ini adalah versi web dari <span class="ds-code" style="color:var(--primary)">docs/standar-master-module.md</span> —
                            standar resmi cara menulis modul master (CRUD list + form) di SIRUS:
                            <strong>Laravel 12 + Livewire/Volt 4 + Oracle</strong>. Tujuannya satu:
                            semua modul master memakai <strong>SATU pola yang sama</strong>, sehingga kode
                            ringkas, mudah di-review, dan programmer baru cukup hafal satu bentuk.
                        </p>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Acuan kanonik adalah modul <strong>Master Agama</strong>
                            (<span class="ds-code">resources/views/pages/master/master-agama/</span>) —
                            implementasi terbersih generasi design-system <span class="ds-code">ds-*</span>:
                            hanya 154 baris untuk list dan 229 baris untuk form, tapi lengkap dengan
                            pencarian, pagination, modal dirty-guard, validasi, dan guard FK Oracle.
                        </p>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Prinsip inti:</strong> satu modul = dua file (LIST + FORM).
                                LIST hanya menampilkan &amp; menyuruh lewat event; semua tulis-DB dan
                                validasi hidup di FORM. Kalau kamu menulis <span class="ds-code">validate()</span>
                                atau <span class="ds-code">insert()</span> di file LIST, berhenti — itu salah tempat.
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">2 File / Modul</div>
                                <div class="ds-body-sm">List + Actions, Volt SFC anonymous class, tanpa Controller.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Event Bernamespace</div>
                                <div class="ds-body-sm">Komunikasi list ↔ form via <span class="ds-code">master.&lt;folder&gt;.*</span> — tidak ada pemanggilan method lintas komponen.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Aman Oracle</div>
                                <div class="ds-body-sm">Pencarian UPPER LIKE, delete dengan guard ORA-02292, kolom string kosong = NULL.</div>
                            </div>
                        </div>

                        <p class="ds-body-md mt-8" style="max-width:62ch">
                            <strong>Baru pertama kali membuat master?</strong> Mulai dari bab
                            <button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                x-on:click="go('alur')">Alur: Buat Master Baru</button>
                            — peta jalan 9 langkah dari salin baseline sampai checklist merge;
                            tiap langkah menunjuk bab referensi detailnya. Bab-bab lain di menu kiri
                            adalah referensi yang bisa dibaca lepas.
                        </p>

                        <div class="ds-card-outline mt-4" style="padding:20px">
                            <div class="ds-title-sm mb-2">Peta belajar programmer baru</div>
                            <ol class="ds-body-sm space-y-1.5" style="list-style:decimal; padding-left:18px">
                                <li><strong>Jalankan proyek di lokal</strong> — <span class="ds-code">composer install</span> · <span class="ds-code">npm install &amp;&amp; npm run build</span> · salin <span class="ds-code">.env</span> (kredensial Oracle dev: minta ke lead) · <span class="ds-code">php artisan serve</span> · login akun dev.</li>
                                <li><strong>Khatam tutorial ini berurutan</strong> (bab 01–14), lalu praktik <strong>satu master Level 1</strong> sampai lolos checklist bab 14.</li>
                                <li>Lanjut <a href="{{ route('panduan-dev.koding-transaksi') }}" wire:navigate class="hover:underline font-semibold" style="color:var(--primary)">Tutorial Koding Transaksi</a> — mulai dari bab Alur Pasien &amp; Data Inti (JSON CLOB), baru bab tahapan.</li>
                                <li>Praktik transaksi pertama lewat bab <em>Alur: Tambah Fitur</em> di tutorial transaksi — dan hafalkan bab <em>Ranjau Umum</em> + <em>Glosarium Istilah</em> di sana sebelum menyentuh kode.</li>
                            </ol>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Bukan developer?</strong> Langsung ke bab
                                <button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                    x-on:click="go('anatomi')">Anatomi Visual (UI/UX)</button>
                                — mockup halaman list, modal form, LOV, dan alur event dengan zona bernomor,
                                tanpa perlu membaca kode.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 02 ALUR ====== --}}
                    <section x-show="section === 'alur'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Alur: Buat Master Baru</h1>
                        <p class="ds-body-md mb-8" style="max-width:62ch">
                            Peta jalan dari nol sampai siap merge — kerjakan <strong>berurutan</strong>.
                            Prinsipnya: tidak pernah menulis dari kosong; selalu salin baseline
                            <span class="ds-code">master-agama</span> lalu sesuaikan. Tiap langkah
                            menunjuk bab referensi yang membahas detailnya.
                        </p>

                        @php
                            $alurCircle = 'display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:9999px;background:var(--primary);color:#fff;font-weight:700;font-size:14px;flex:none';
                            $alurSteps = [
                                [
                                    't' => 'Kenali tabel & tentukan level',
                                    'd' => 'Sebelum menulis kode: pastikan tabel Oracle-nya — nama tabel (biasanya <span class="ds-code">rsmst_*</span>), primary key, kolom wajib, dan ada/tidaknya kolom <span class="ds-code">active_status</span> (\'1\'/\'0\'). Lalu tentukan level: CRUD satu tabel = <strong>Level 1</strong> · ada FK / LOV / toggle status = <strong>Level 2</strong> · induk-anak seperti bangsal→kamar→bed = <strong>Level 3</strong>. Kalau ragu, mulai Level 1.',
                                    'go' => 'varian', 'label' => 'Bab 13 · Varian & Level Kompleksitas',
                                    'snip' => null, 'sniptitle' => null,
                                ],
                                [
                                    't' => 'Salin baseline master-agama',
                                    'd' => 'Salin folder acuan kanonik, ganti nama kedua file ⚡, lalu cari-ganti identitas domain di dalamnya. Selesai langkah ini, modulmu sudah jalan — tinggal disesuaikan.',
                                    'go' => 'struktur', 'label' => 'Bab 03 · Struktur File & Routing',
                                    'snip' => 'alur-salin', 'sniptitle' => 'Terminal — salin & ganti nama',
                                ],
                                [
                                    't' => 'Daftarkan route & menu',
                                    'd' => 'Route eksplisit di <span class="ds-code">routes/web.php</span> (repo ini tidak memakai auto-discovery), lalu tambahkan entri di <span class="ds-code">app/Services/AppMenu.php</span> supaya modul muncul di menu dashboard sesuai role penggunanya.',
                                    'go' => null, 'label' => null,
                                    'snip' => 'alur-route-menu', 'sniptitle' => 'routes/web.php + AppMenu.php',
                                ],
                                [
                                    't' => 'Kerjakan file LIST',
                                    'd' => 'Sesuaikan kolom tabel &amp; query <span class="ds-code">rows()</span>. Pertahankan kontrak penamaan: <span class="ds-code">searchKeyword</span>, <span class="ds-code">itemsPerPage</span>, <span class="ds-code">resetFilters()</span>, event <span class="ds-code">master.&lt;folder&gt;.*</span> (Bab 04). Ingat prinsip inti: LIST tidak pernah validasi / menulis DB — tombolnya hanya mengirim event.',
                                    'go' => 'list', 'label' => 'Bab 05 · Halaman List',
                                    'snip' => null, 'sniptitle' => null,
                                ],
                                [
                                    't' => 'Kerjakan file FORM (actions)',
                                    'd' => 'Key <span class="ds-code">$form</span> = nama kolom DB. Sesuaikan <span class="ds-code">openCreate</span> / <span class="ds-code">openEdit</span> / <span class="ds-code">save()</span> / <span class="ds-code">closeModal()</span> dan nama modal yang unik. PK di-<span class="ds-code">:disabled</span> saat edit; rule <span class="ds-code">unique</span> hanya saat create.',
                                    'go' => 'form', 'label' => 'Bab 06 · Form Modal (Actions)',
                                    'snip' => null, 'sniptitle' => null,
                                ],
                                [
                                    't' => 'Rapikan validasi & delete guard',
                                    'd' => 'Pesan validasi <strong>selalu Bahasa Indonesia</strong> dan <span class="ds-code">validate()</span> paling atas di save() (Bab 10). Handler delete menangkap <span class="ds-code">ORA-02292</span> menjadi toast yang manusiawi (Bab 11) — tanpa ini user melihat error 500.',
                                    'go' => 'validasi', 'label' => 'Bab 10 · Validasi',
                                    'snip' => null, 'sniptitle' => null,
                                ],
                                [
                                    't' => 'Cocokkan tampilan',
                                    'd' => 'Bandingkan halamanmu dengan mockup zona bernomor di Anatomi Visual. Pakai komponen standar — <span class="ds-code">x-text-input</span>, <span class="ds-code">x-primary-button</span>, <span class="ds-code">x-action-edit/delete</span>, <span class="ds-code">x-modal</span> (Bab 07). Ada field yang merujuk master lain? Pakai LOV (Bab 08), jangan dropdown ribuan baris.',
                                    'go' => 'anatomi', 'label' => 'Bab 09 · Anatomi Visual (UI/UX)',
                                    'snip' => null, 'sniptitle' => null,
                                ],
                                [
                                    't' => 'Uji CRUD ujung-ke-ujung',
                                    'd' => 'Jalankan semuanya di browser: tambah (Enter di field terakhir = simpan), edit, cari + reset, pagination, tutup modal saat ada perubahan belum disimpan (dirty-guard harus konfirmasi), dan hapus baris yang sudah dipakai transaksi — harus muncul toast merah, bukan error 500.',
                                    'go' => null, 'label' => null,
                                    'snip' => null, 'sniptitle' => null,
                                ],
                                [
                                    't' => 'Checklist sebelum PR',
                                    'd' => 'Pastikan semua butir checklist hijau; LIST ≤ ±300 baris, FORM ≤ ±400 baris (lebih dari itu → pecah partial, Bab 12). Kerjakan di branch <span class="ds-code">develop</span> / feature branch, lalu ajukan PR.',
                                    'go' => 'checklist', 'label' => 'Bab 14 · Checklist & Referensi',
                                    'snip' => null, 'sniptitle' => null,
                                ],
                            ];
                        @endphp

                        <div>
                            @foreach ($alurSteps as $st)
                                <div class="flex gap-4">
                                    <div class="flex flex-col items-center">
                                        <span style="{{ $alurCircle }}">{{ $loop->iteration }}</span>
                                        @if (! $loop->last)
                                            <span class="flex-1" style="width:2px; background:var(--hairline); margin-top:4px"></span>
                                        @endif
                                    </div>
                                    <div class="flex-1 {{ $loop->last ? '' : 'pb-8' }}" style="min-width:0">
                                        <div class="ds-title-sm mb-1" style="padding-top:4px">{{ $st['t'] }}</div>
                                        <p class="ds-body-sm" style="max-width:62ch">{!! $st['d'] !!}</p>
                                        @if ($st['go'])
                                            <button type="button" class="mt-2 text-sm font-semibold hover:underline" style="color:var(--primary)"
                                                x-on:click="go('{{ $st['go'] }}')">→ {{ $st['label'] }}</button>
                                        @endif
                                        @if ($st['snip'])
                                            <div class="ds-card-dark mt-3" style="padding:0; overflow:hidden">
                                                <div class="px-4 py-2" style="background:var(--surface-dark-soft)">
                                                    <span class="ds-caption-up" style="color:var(--on-dark-soft)">{{ $st['sniptitle'] }}</span>
                                                </div>
                                                <pre class="ds-code" style="margin:0; padding:16px 20px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip[$st['snip']] }}</pre>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Contoh hidup per level:</strong>
                                Level 1 → <span class="ds-code">master-agama</span> ·
                                Level 2 → <span class="ds-code">master-obat</span> / <span class="ds-code">master-dokter</span> ·
                                Level 3 → <span class="ds-code">master-kamar</span> (bangsal→kamar→bed).
                                Buka kodenya berdampingan dengan tutorial ini.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 03 STRUKTUR ====== --}}
                    <section x-show="section === 'struktur'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Struktur File &amp; Routing</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Satu modul master = <strong>satu folder, dua file Volt SFC</strong>.
                            Nama folder dan nama file selalu memakai prefix <span class="ds-code">master-</span>
                            dan kebab-case.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Struktur folder</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['tree'] }}</pre>
                        </div>

                        <p class="ds-body-md mt-8 mb-2" style="max-width:62ch">
                            Routing <strong>eksplisit</strong> di <span class="ds-code">routes/web.php</span>
                            (repo ini tidak memakai Folio auto-discovery). Selalu beri nama route
                            <span class="ds-code">master.&lt;nama&gt;</span>:
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">routes/web.php</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['route'] }}</pre>
                        </div>

                        <p class="ds-body-md mt-8 mb-2" style="max-width:62ch">
                            LIST me-mount FORM sebagai <strong>child component</strong> di akhir markup —
                            modal form selalu siap menerima event tanpa perlu route sendiri:
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">⚡master-&lt;nama&gt;.blade.php (paling bawah)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['mount'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Boleh menyimpang dari struktur 2-file hanya untuk <strong>3 varian resmi</strong>
                                (master-detail hierarkis, form bertab, single-file integrasi) —
                                lihat bab <em>Varian Resmi</em>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 04 PENAMAAN ====== --}}
                    <section x-show="section === 'penamaan'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Kontrak Penamaan</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Nama state, method, dan event <strong>tidak bebas</strong> — semuanya kontrak.
                            Reviewer harus bisa menebak isi file tanpa membukanya.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Hal</th><th>Standar</th><th>Catatan</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">State pencarian</td><td class="ds-td-class">searchKeyword</td><td class="ds-body-sm">+ updatedSearchKeyword() → resetPage()</td></tr>
                                    <tr><td class="ds-td-strong">Per halaman</td><td class="ds-td-class">itemsPerPage</td><td class="ds-body-sm">default 10</td></tr>
                                    <tr><td class="ds-td-strong">Reset filter</td><td class="ds-td-class">resetFilters()</td><td class="ds-body-sm">dipanggil x-toolbar-refresh-reset</td></tr>
                                    <tr><td class="ds-td-strong">Data list</td><td class="ds-td-class">#[Computed] rows()</td><td class="ds-body-sm">akses $this->rows di markup</td></tr>
                                    <tr><td class="ds-td-strong">Namespace event</td><td class="ds-td-class">master.&lt;namafolder&gt;.*</td><td class="ds-body-sm">HARUS sama dgn nama folder</td></tr>
                                    <tr><td class="ds-td-strong">Verb event</td><td class="ds-td-class">openCreate · openEdit · requestDelete · saved</td><td class="ds-body-sm">hanya 4 ini utk CRUD standar</td></tr>
                                    <tr><td class="ds-td-strong">Mode form</td><td class="ds-td-class">$formMode + $originalId</td><td class="ds-body-sm">'create' | 'edit'</td></tr>
                                    <tr><td class="ds-td-strong">State form</td><td class="ds-td-class">array $form</td><td class="ds-body-sm">key = nama kolom DB</td></tr>
                                    <tr><td class="ds-td-strong">wire:key baris</td><td class="ds-td-class">&lt;slug&gt;-{pk}</td><td class="ds-body-sm">contoh: agama-12</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <p class="ds-body-md mt-8 mb-2" style="max-width:62ch">
                            Alur komunikasi list ↔ form <strong>selalu lewat event</strong>, tidak pernah
                            memanggil method komponen lain secara langsung:
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Alur event</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['event-flow'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Jangan tiru deviasi lama:</strong> <span class="ds-code">master.class.*</span>
                                (kelas-rawat) dan <span class="ds-code">master.diagkep.*</span> (diag-keperawatan)
                                tidak mengikuti nama folder — keduanya masuk backlog penyeragaman, bukan contoh.
                            </span>
                        </div>
                    </section>