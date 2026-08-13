                    {{-- ====== 07 PEMAKAIAN KOMPONEN ====== --}}
                    <section x-show="section === 'komponen'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Komponen</div>
                        <h1 class="ds-display-md mb-4">Pemakaian Komponen</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Katalog komponen Blade yang dipakai modul master — semuanya di
                            <span class="ds-code">resources/views/components/</span>. Aturan utamanya sederhana:
                            <strong>kalau komponennya ada, pakai — jangan tulis markup manual</strong>.
                        </p>

                        {{-- peta komponen --}}
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Komponen</th><th>Dipakai di</th><th>Props kunci</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-class">x-page-title</td><td class="ds-body-sm">atas semua halaman list</td><td class="ds-td-meta">title · subtitle</td></tr>
                                    <tr><td class="ds-td-class">x-toolbar-refresh-reset</td><td class="ds-body-sm">kanan toolbar list</td><td class="ds-td-meta">label · resetAction · iconOnly</td></tr>
                                    <tr><td class="ds-td-class">x-action-edit / x-action-delete</td><td class="ds-body-sm">kolom Aksi tabel</td><td class="ds-td-meta">action · title · message</td></tr>
                                    <tr><td class="ds-td-class">x-input-label / x-input-error</td><td class="ds-body-sm">pasangan tiap field</td><td class="ds-td-meta">value · required / messages</td></tr>
                                    <tr><td class="ds-td-class">x-text-input / x-select-input</td><td class="ds-body-sm">field form</td><td class="ds-td-meta">error · disabled</td></tr>
                                    <tr><td class="ds-td-class">x-text-input-number</td><td class="ds-body-sm">SEMUA field nominal uang</td><td class="ds-td-meta">error · disabled · extraBlur</td></tr>
                                    <tr><td class="ds-td-class">x-border-form</td><td class="ds-body-sm">section field di body modal</td><td class="ds-td-meta">title · align · bgcolor · padding</td></tr>
                                    <tr><td class="ds-td-class">x-modal</td><td class="ds-body-sm">wrapper form actions</td><td class="ds-td-meta">name · size (md–full) · height · focusable</td></tr>
                                    <tr><td class="ds-td-class">x-dirty-modal-content</td><td class="ds-body-sm">isi modal (dirty-guard)</td><td class="ds-td-meta">name · event · label · wireKey</td></tr>
                                    <tr><td class="ds-td-class">x-badge</td><td class="ds-body-sm">badge Mode header modal</td><td class="ds-td-meta">variant (success|warning|info|...)</td></tr>
                                    <tr><td class="ds-td-class">x-primary/secondary/icon-button</td><td class="ds-body-sm">Simpan · Batal/Edit · close X</td><td class="ds-td-meta">type · disabled · color</td></tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- A. halaman & list --}}
                        <h2 class="ds-title-lg mt-10 mb-2">Halaman &amp; List</h2>

                        {{-- live preview: komponen aksi asli, no-op --}}
                        <div class="ds-frame mt-2">
                            <div class="ds-frame-label">Tampilan — silakan diklik (aksi demo, tidak mengubah data)</div>
                            <div class="flex flex-wrap items-center gap-3 mt-3">
                                <x-action-edit wire:click="demoAksi" />
                                <x-action-delete :action="'demoAksi'"
                                    title="Hapus Demo"
                                    message="Ini dialog konfirmasi asli milik x-action-delete — aman diklik." />
                                <x-toolbar-refresh-reset :label="null" />
                            </div>
                            <p class="ds-caption mt-3" style="color:var(--muted)">
                                Tombol Hapus memunculkan dialog konfirmasi bawaan; ⟳ memuat ulang komponen; ↩ mereset filter demo.
                            </p>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-page-title</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-page-title'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-action-edit · x-action-delete</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-actions'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-toolbar-refresh-reset</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-refresh'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <span class="ds-code">x-action-delete</span> adalah pembungkus
                                <span class="ds-code">x-confirm-button variant="danger"</span> + ikon sampah —
                                dialog konfirmasi sudah termasuk. JANGAN pakai <span class="ds-code">wire:confirm</span>
                                (dialog browser native) atau tombol hapus manual.
                            </span>
                        </div>

                        {{-- B. form & input --}}
                        <h2 class="ds-title-lg mt-10 mb-2">Form &amp; Input</h2>

                        {{-- live preview: trio field + number + error state, dibungkus x-border-form asli --}}
                        <div class="ds-frame mt-2">
                            <div class="ds-frame-label">Tampilan — silakan diketik</div>
                            <div class="mt-3">
                                <x-border-form title="Data Contoh (x-border-form)">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <x-input-label value="Nama (x-text-input)" :required="true" />
                                            <x-text-input wire:model.live.debounce.300ms="demoText"
                                                placeholder="Ketik sesuatu..." class="w-full mt-1" />
                                            <p class="mt-1 text-xs" style="color:var(--muted)">
                                                Nilai tersinkron: <span class="ds-code" style="color:var(--primary)">{{ $demoText !== '' ? $demoText : '—' }}</span>
                                            </p>
                                        </div>
                                        <div>
                                            <x-input-label value="Kategori (x-select-input)" />
                                            <x-select-input wire:model.live="demoSelect" class="w-full mt-1">
                                                <option value="">— pilih —</option>
                                                <option value="A">Kategori A</option>
                                                <option value="B">Kategori B</option>
                                            </x-select-input>
                                        </div>
                                        <div>
                                            <x-input-label value="Harga (x-text-input-number)" />
                                            <x-text-input-number wire:model="demoNumber" class="w-full mt-1" />
                                            <p class="mt-1 text-xs" style="color:var(--muted)">
                                                Ketik angka lalu klik di luar → otomatis berformat ribuan.
                                            </p>
                                        </div>
                                        <div>
                                            <x-input-label value="Kondisi error (x-input-error)" />
                                            <x-text-input :error="true" placeholder="Border merah saat gagal validasi"
                                                class="w-full mt-1" />
                                            <x-input-error :messages="['Contoh pesan validasi Bahasa Indonesia.']" class="mt-1" />
                                        </div>
                                    </div>
                                </x-border-form>
                            </div>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-input-label · x-text-input · x-input-error · x-select-input</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-input'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-text-input-number (nominal uang)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-number'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-border-form</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-border-form'] }}</pre>
                        </div>

                        {{-- C. modal --}}
                        <h2 class="ds-title-lg mt-10 mb-2">Modal</h2>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-modal + x-dirty-modal-content</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-modal'] }}</pre>
                        </div>

                        {{-- live preview: semua varian badge --}}
                        <div class="ds-frame mt-4">
                            <div class="ds-frame-label">Tampilan — 8 varian x-badge</div>
                            <div class="flex flex-wrap items-center gap-2 mt-3">
                                @foreach (['brand', 'alternative', 'gray', 'danger', 'success', 'warning', 'info', 'purple'] as $variantName)
                                    <x-badge :variant="$variantName">{{ $variantName }}</x-badge>
                                @endforeach
                            </div>
                            <p class="ds-caption mt-3" style="color:var(--muted)">
                                Di modal master hanya dua yang dipakai: <strong>success</strong> (Mode: Tambah) dan <strong>warning</strong> (Mode: Edit).
                            </p>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-badge (Mode: Tambah / Edit)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-badge'] }}</pre>
                        </div>

                        {{-- D. tombol --}}
                        <h2 class="ds-title-lg mt-10 mb-2">Tombol</h2>

                        {{-- live preview: tombol asli, aksi no-op --}}
                        <div class="ds-frame mt-2 mb-4">
                            <div class="ds-frame-label">Tampilan — silakan diklik (aksi demo)</div>
                            <div class="flex flex-wrap items-center gap-3 mt-3">
                                <x-primary-button type="button" wire:click="demoAksi" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="demoAksi">Simpan</span>
                                    <span wire:loading wire:target="demoAksi">Saving...</span>
                                </x-primary-button>
                                <x-secondary-button type="button">Batal</x-secondary-button>
                                <x-icon-button color="gray" type="button" title="Close X standar header modal">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </x-icon-button>
                                <x-confirm-button variant="danger" :action="'demoAksi'"
                                    title="Hapus Data"
                                    message="Ini dialog konfirmasi asli x-confirm-button — aman diklik."
                                    confirmText="Ya, hapus" cancelText="Batal">
                                    Hapus
                                </x-confirm-button>
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Komponen</th><th>Kegunaan di modul master</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-class">x-primary-button</td><td class="ds-body-sm">Simpan (SATU per modal) + tombol Tambah di toolbar — selalu dgn <span class="ds-code">wire:loading.attr="disabled"</span></td></tr>
                                    <tr><td class="ds-td-class">x-secondary-button</td><td class="ds-body-sm">Batal (via <span class="ds-code">tryClose()</span>)</td></tr>
                                    <tr><td class="ds-td-class">x-icon-button color="gray"</td><td class="ds-body-sm">close X di pojok header modal</td></tr>
                                    <tr><td class="ds-td-class">x-confirm-button</td><td class="ds-body-sm">aksi berbahaya non-hapus-baris (hapus baris tabel pakai x-action-delete)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Varian, ukuran, dan warna lengkap semua tombol:
                                <span class="ds-code">docs/standar-komponen-tombol.md</span>.
                                Katalog visual seluruh komponen (dgn demo interaktif): halaman
                                <a href="{{ route('panduan-dev') }}" wire:navigate class="hover:underline" style="color:var(--primary)">Standarisasi UI</a>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 08 LOV ====== --}}
                    <section x-show="section === 'lov'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Komponen</div>
                        <h1 class="ds-display-md mb-4">LOV (List of Values)</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            LOV adalah <strong>child Livewire component siap-pakai</strong> untuk field
                            yang merujuk ke master lain (FK): cari sambil ketik → pilih → parent menerima
                            payload. Tersedia <strong>34 LOV</strong> di
                            <span class="ds-code">resources/views/livewire/lov/&lt;entitas&gt;/lov-&lt;entitas&gt;.blade.php</span> —
                            obat, dokter, poli, pasien, diagnosa, kamar, akun, supplier, dan lainnya.
                            <strong>Jangan membangun dropdown pencarian manual</strong> — pakai LOV yang ada,
                            atau salin LOV yang paling mirip lalu ganti query &amp; payload-nya.
                        </p>

                        <div class="ds-card-outline mt-2" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Kontrak 3 langkah:</strong>
                                (1) mount LOV dgn <span class="ds-code">target</span> unik +
                                <span class="ds-code">wire:key</span> ber-renderVersions,
                                (2) LOV dispatch <span class="ds-code">lov.selected.&lt;target&gt;</span> saat user memilih,
                                (3) parent menangkap via <span class="ds-code">#[On]</span> lalu mengisi
                                <span class="ds-code">$form</span>.
                            </span>
                        </div>

                        {{-- live preview: LOV product asli + payload yang tertangkap listener demo --}}
                        <div class="ds-frame mt-6">
                            <div class="ds-frame-label">Tampilan — LOV asli (lov-product), mencari data obat sungguhan</div>
                            <div class="grid grid-cols-1 gap-4 mt-3 lg:grid-cols-2">
                                <div>
                                    <livewire:lov.product.lov-product
                                        target="demo-koding-master"
                                        label="Obat (cari dari master obat)"
                                        placeholder="Ketik nama/kode/kandungan obat..."
                                        wire:key="lov-demo-koding-master" />
                                    <p class="ds-caption mt-2" style="color:var(--muted)">
                                        Ketik ≥ 2 huruf (mis. "para") · ↓ ↑ navigasi · Enter ambil · Esc tutup.
                                    </p>
                                </div>
                                <div class="ds-card-outline" style="padding:16px">
                                    <div class="ds-caption-up mb-2">Payload yang diterima parent (langkah 3)</div>
                                    @if ($demoLovId !== '')
                                        <p class="ds-body-sm">
                                            <span class="ds-code" style="color:var(--primary)">lov.selected.demo-koding-master</span> tertangkap:<br>
                                            <span class="ds-td-token">product_id&nbsp;&nbsp;= {{ $demoLovId }}</span><br>
                                            <span class="ds-td-token">product_name = {{ $demoLovName }}</span>
                                        </p>
                                    @else
                                        <p class="ds-body-sm" style="color:var(--muted)">
                                            Belum ada pilihan — pilih obat di kiri, payload event akan tampil di sini
                                            (persis yang diterima <span class="ds-code">#[On]</span> di form parent).
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Langkah 1 — mount di markup parent</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['lov-mount'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Langkah 2 &amp; 3 — listener di kelas parent</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['lov-listener'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Props &amp; perilaku bawaan</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['lov-anatomy'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Wajib diingat</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><span class="ds-code">target</span> unik per form — kalau dua form memakai LOV sama dgn target sama, keduanya menangkap event yang sama</li>
                                    <li><span class="ds-code">wire:key</span> WAJIB menyertakan <span class="ds-code">renderVersions</span> — tanpa itu state LOV lama nyangkut saat modal dibuka ulang</li>
                                    <li>Simpan <strong>id + nama</strong> ke <span class="ds-code">$form</span> dari payload; validasi <span class="ds-code">Rule::exists</span> tetap di parent</li>
                                    <li>Error validasi ditampilkan parent (<span class="ds-code">x-input-error</span> di bawah LOV), bukan di dalam LOV</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Mode edit &amp; terkunci</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>Nilai FK <strong>tidak boleh diubah</strong> saat edit → tampilkan field readonly, LOV hanya di mode create (contoh: master-obat-kronis)</li>
                                    <li>Nilai FK <strong>boleh diubah</strong> saat edit → kirim <span class="ds-code">initial*Id</span>, LOV mount langsung dalam keadaan terpilih</li>
                                    <li>Form terkunci / mode lihat → <span class="ds-code">:readonly="true"</span> (tombol "Ubah" hilang)</li>
                                </ul>
                            </div>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-3">LOV yang tersedia</h2>
                        <div class="ds-card-outline" style="padding:20px">
                            <div class="flex flex-wrap gap-2">
                                @foreach ([
                                    'akun', 'akun-ci', 'akun-co', 'asuhan-keperawatan', 'cat-product', 'clabitem-group',
                                    'desa', 'diag-kep', 'diagnosa', 'dokter', 'group-akun', 'group-product',
                                    'jasa-dokter', 'jasa-karyawan', 'jasa-medis', 'kabupaten', 'kas', 'kasir',
                                    'kelas-kamar', 'lain-lain', 'loinc', 'outs', 'pasien', 'poli',
                                    'procedure', 'product', 'product-non', 'propinsi', 'radiologi', 'room',
                                    'snomed', 'stocklocation', 'supplier', 'uom',
                                ] as $lovName)
                                    <span class="ds-badge-pill">lov-{{ $lovName }}</span>
                                @endforeach
                            </div>
                            <p class="ds-body-sm mt-4" style="color:var(--muted)">
                                Butuh LOV entitas baru? Salin folder LOV yang paling mirip
                                (acuan bersih: <span class="ds-code">lov/product</span>), ganti query +
                                bentuk payload, pertahankan seluruh kontrak <span class="ds-code">target</span>/event
                                dan navigasi keyboard-nya.
                            </p>
                        </div>
                    </section>