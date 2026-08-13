                    {{-- ====== 09 ANATOMI VISUAL ====== --}}
                    <section x-show="section === 'anatomi'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Komponen</div>
                        <h1 class="ds-display-md mb-4">Anatomi Visual (UI/UX)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Bab ini untuk yang bekerja di sisi <strong>UI/UX</strong> — tanpa perlu membaca kode.
                            Setiap mockup di bawah dirender dengan token design-system asli
                            (warna &amp; kelas <span class="ds-code">ds-*</span> yang sama dengan aplikasi),
                            dan tiap zona bernomor dipetakan ke nama komponennya di legenda.
                        </p>

                        {{-- ===== A. ANATOMI HALAMAN LIST ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-3">A · Halaman List</h2>
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">

                            {{-- topbar --}}
                            <div class="flex items-center gap-3 px-4 py-2.5" style="position:relative; background:var(--surface-dark)">
                                <span style="width:22px;height:22px;border-radius:6px;background:var(--accent-lime);display:inline-block"></span>
                                <span class="ds-title-sm" style="color:var(--on-dark)">RSI&nbsp;Madinah</span>
                                <span class="px-2.5 py-1 text-xs rounded-full" style="background:var(--surface-dark-elevated); color:var(--on-dark-soft)">
                                    <strong style="color:var(--on-dark)">Master Agama</strong> — Kelola data agama pasien
                                </span>
                                <span style="{{ $badge }};position:absolute;top:8px;right:8px">1</span>
                            </div>

                            {{-- toolbar --}}
                            <div class="px-4 py-3" style="position:relative; background:var(--surface-soft); border-bottom:1px solid var(--hairline)">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div style="{{ $mockInput }}; width:220px">Cari agama...</div>
                                    <div style="{{ $mockInput }}; width:70px; justify-content:space-between">10 <span>▾</span></div>
                                    <span class="ds-btn ds-btn-primary" style="height:36px; padding:8px 14px; font-size:13px">+ Tambah Data</span>
                                    <span class="inline-flex overflow-hidden rounded-lg" style="border:1px solid var(--hairline); background:var(--canvas)">
                                        <span class="px-2.5 py-2 text-sm" style="color:var(--info)">⟳</span>
                                        <span class="px-2.5 py-2 text-sm" style="border-left:1px solid var(--hairline); color:var(--muted)">↩</span>
                                    </span>
                                </div>
                                <span style="{{ $badge }};position:absolute;top:8px;right:8px">2</span>
                            </div>

                            {{-- tabel --}}
                            <div style="position:relative">
                                <table class="ds-table">
                                    <thead>
                                        <tr><th>ID</th><th>Agama</th><th class="ds-c">Aksi</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([[1, 'ISLAM'], [2, 'KRISTEN'], [3, 'KATOLIK']] as [$mockId, $mockNama])
                                            <tr>
                                                <td class="ds-td-token">{{ $mockId }}</td>
                                                <td class="ds-td-strong">{{ $mockNama }}</td>
                                                <td class="ds-c">
                                                    <span class="inline-flex items-center gap-2">
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg"
                                                            style="border:1px solid var(--hairline); background:var(--canvas); color:var(--body)">✎ Edit</span>
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg"
                                                            style="background:var(--error); color:#fff">🗑 Hapus</span>
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <span style="{{ $badge }};position:absolute;top:8px;right:8px">3</span>
                                <span style="{{ $badge }};position:absolute;top:64px;right:8px;background:var(--info)">4</span>
                            </div>

                            {{-- pagination --}}
                            <div class="flex items-center justify-between px-4 py-2.5" style="position:relative; border-top:1px solid var(--hairline); background:var(--canvas)">
                                <span class="ds-caption" style="color:var(--muted)">Menampilkan 1–3 dari 6 data</span>
                                <span class="inline-flex items-center gap-1.5">
                                    <button type="button" class="ds-page-btn" disabled>‹</button>
                                    <button type="button" class="ds-page-btn ds-page-btn-active">1</button>
                                    <button type="button" class="ds-page-btn">2</button>
                                    <button type="button" class="ds-page-btn">›</button>
                                </span>
                                <span style="{{ $badge }};position:absolute;top:8px;right:8px">5</span>
                            </div>
                        </div>

                        {{-- legenda list --}}
                        <div class="grid grid-cols-1 gap-2 mt-4 sm:grid-cols-2">
                            @foreach ([
                                ['1', 'x-page-title — judul halaman jadi chip di topbar global (bukan header lokal)'],
                                ['2', 'Toolbar sticky — x-text-input pencarian (debounce 300ms) · x-select-input per-halaman · x-primary-button Tambah · x-toolbar-refresh-reset'],
                                ['3', 'Card tabel ds-table — thead sticky, card flex-fill sampai bawah viewport'],
                                ['4', 'Kolom Aksi — x-action-edit + x-action-delete (Hapus selalu lewat dialog konfirmasi)'],
                                ['5', 'Pagination sticky bottom — nempel di dasar card, bukan ikut scroll'],
                            ] as [$num, $ket])
                                <div class="flex items-start gap-2.5">
                                    <span style="{{ $badge }}; margin-top:2px; {{ $num === '4' ? 'background:var(--info)' : '' }}">{{ $num }}</span>
                                    <span class="ds-body-sm">{{ $ket }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- ===== B. ANATOMI MODAL FORM ===== --}}
                        <h2 class="ds-title-lg mt-12 mb-3">B · Modal Form (Tambah / Edit)</h2>
                        <div class="ds-card-outline" style="padding:24px; background:var(--surface-soft)">
                            <div class="mx-auto" style="max-width:560px; border-radius:14px; overflow:hidden; border:1px solid var(--hairline); box-shadow:0 18px 40px rgba(0,0,0,.14)">

                                {{-- header modal --}}
                                <div class="px-5 py-4" style="position:relative; background:var(--surface-soft)">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <span class="flex items-center justify-center" style="width:38px;height:38px;border-radius:12px;background:var(--primary-disabled)">
                                                <span style="width:16px;height:16px;border-radius:4px;background:var(--primary);display:inline-block"></span>
                                            </span>
                                            <span>
                                                <span class="block ds-title-md">Tambah Data Agama</span>
                                                <span class="block ds-caption" style="color:var(--muted)">Lengkapi informasi agama pasien.</span>
                                            </span>
                                        </div>
                                        <span class="flex items-center justify-center" style="width:28px;height:28px;border-radius:8px;border:1px solid var(--hairline);color:var(--muted)">✕</span>
                                    </div>
                                    <span class="inline-flex px-2 py-0.5 mt-2 text-xs font-medium rounded-full" style="background:var(--success-tint); color:var(--success-deep)">Mode: Tambah</span>
                                    <span style="{{ $badge }};position:absolute;top:8px;right:44px">1</span>
                                    <span style="{{ $badge }};position:absolute;bottom:8px;left:8px;background:var(--info)">2</span>
                                    <span style="{{ $badge }};position:absolute;top:8px;right:8px;background:var(--muted)">3</span>
                                </div>

                                {{-- body modal --}}
                                <div class="px-4 py-4" style="position:relative; background:var(--canvas); border-top:1px solid var(--hairline)">
                                    <div style="border:1px solid var(--hairline); border-radius:14px; overflow:hidden">
                                        <div class="px-4 py-2 ds-caption-up" style="background:var(--surface-soft); border-bottom:1px solid var(--hairline)">Data Agama</div>
                                        <div class="grid grid-cols-3 gap-3 p-4">
                                            <div>
                                                <span class="block mb-1 text-xs font-medium" style="color:var(--body)">ID Agama</span>
                                                <div style="{{ $mockInput }}">7</div>
                                            </div>
                                            <div class="col-span-2">
                                                <span class="block mb-1 text-xs font-medium" style="color:var(--body)">Nama Agama</span>
                                                <div style="{{ $mockInput }}; border-color:var(--error); color:var(--ink)"></div>
                                                <span class="block mt-1 text-xs" style="color:var(--error)">Nama Agama wajib diisi.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <span style="{{ $badge }};position:absolute;top:8px;right:8px">4</span>
                                    <span style="{{ $badge }};position:absolute;bottom:8px;right:8px;background:var(--error)">5</span>
                                </div>

                                {{-- footer modal --}}
                                <div class="flex items-center justify-between px-5 py-3.5" style="position:relative; background:var(--surface-soft); border-top:1px solid var(--hairline)">
                                    <span class="ds-caption" style="color:var(--muted)">
                                        <kbd class="px-1.5 py-0.5 text-xs font-semibold rounded" style="background:var(--canvas); border:1px solid var(--hairline)">Enter</kbd>
                                        di field terakhir untuk simpan
                                    </span>
                                    <span class="inline-flex gap-2">
                                        <span class="ds-btn ds-btn-secondary" style="height:34px; padding:6px 14px; font-size:13px">Batal</span>
                                        <span class="ds-btn ds-btn-primary" style="height:34px; padding:6px 14px; font-size:13px">Simpan</span>
                                    </span>
                                    <span style="{{ $badge }};position:absolute;top:8px;right:8px">6</span>
                                </div>
                            </div>
                        </div>

                        {{-- legenda modal --}}
                        <div class="grid grid-cols-1 gap-2 mt-4 sm:grid-cols-2">
                            @foreach ([
                                ['1', 'Header — ikon modul + judul "Tambah/Ubah Data ..." + deskripsi singkat', ''],
                                ['2', 'x-badge Mode — hijau (success) saat Tambah, kuning (warning) saat Edit', 'background:var(--info)'],
                                ['3', 'Close X — x-icon-button gray; menutup lewat tryClose() (konfirmasi bila ada perubahan belum disimpan)', 'background:var(--muted)'],
                                ['4', 'Body — x-border-form mengelompokkan field; latar surface-soft', ''],
                                ['5', 'Error state — border merah + pesan Indonesia di bawah field (x-input-error)', 'background:var(--error)'],
                                ['6', 'Footer sticky — hint keyboard · Batal · SATU tombol Simpan hijau', ''],
                            ] as [$num, $ket, $extra])
                                <div class="flex items-start gap-2.5">
                                    <span style="{{ $badge }}; margin-top:2px; {{ $extra }}">{{ $num }}</span>
                                    <span class="ds-body-sm">{{ $ket }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- ===== C. ALUR EVENT ===== --}}
                        <h2 class="ds-title-lg mt-12 mb-3">C · Alur List ↔ Form (event)</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            List dan Form adalah dua komponen terpisah yang <strong>hanya berbicara lewat event</strong>.
                            Bagi UI, artinya: klik apa pun di list tidak pernah menulis data — modal form-lah
                            satu-satunya pintu ke database.
                        </p>
                        <div class="grid items-center grid-cols-1 gap-4 lg:grid-cols-[1fr_auto_1fr]">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2">LIST — tampilan</div>
                                <ul class="ds-body-sm space-y-1" style="list-style:disc; padding-left:18px">
                                    <li>Menampilkan tabel + pencarian</li>
                                    <li>Tombol Tambah / Edit / Hapus → <em>hanya mengirim event</em></li>
                                    <li>Refresh otomatis saat menerima <span class="ds-code">saved</span></li>
                                </ul>
                            </div>
                            <div class="text-center">
                                <div class="ds-code mb-2" style="color:var(--primary); white-space:nowrap">── openCreate / openEdit / requestDelete ──▶</div>
                                <div class="ds-code" style="color:var(--info); white-space:nowrap">◀── master.&lt;x&gt;.saved ──</div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2">FORM — modal</div>
                                <ul class="ds-body-sm space-y-1" style="list-style:disc; padding-left:18px">
                                    <li>Terbuka saat menerima event open*</li>
                                    <li>Validasi → simpan/hapus ke <strong>database</strong></li>
                                    <li>Toast sukses → tutup → kirim <span class="ds-code">saved</span></li>
                                </ul>
                            </div>
                        </div>

                        {{-- ===== D. LOV DUA KEADAAN ===== --}}
                        <h2 class="ds-title-lg mt-12 mb-3">D · LOV — dua keadaan</h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-3">Keadaan 1 — mode cari</div>
                                <span class="block mb-1 text-xs font-medium" style="color:var(--body)">Obat (cari dari master obat)</span>
                                <div style="{{ $mockInput }}; color:var(--ink)">amox<span style="opacity:.4">|</span></div>
                                <div class="mt-2 overflow-hidden" style="border:1px solid var(--hairline); border-radius:12px; box-shadow:0 8px 20px rgba(0,0,0,.08)">
                                    <div class="px-3 py-2" style="background:var(--surface-card); border-left:3px solid var(--primary)">
                                        <span class="block text-sm font-semibold" style="color:var(--ink)">AMOXICILLIN 500 MG TABLET</span>
                                        <span class="block text-xs" style="color:var(--muted)">ID: 12345 • Harga: Rp 1.500</span>
                                    </div>
                                    <div class="px-3 py-2" style="border-top:1px solid var(--hairline-soft)">
                                        <span class="block text-sm font-semibold" style="color:var(--ink)">AMOXSAN SIRUP 60 ML</span>
                                        <span class="block text-xs" style="color:var(--muted)">ID: 12377 • Harga: Rp 28.000</span>
                                    </div>
                                </div>
                                <p class="ds-caption mt-3" style="color:var(--muted)">Ketik ≥ 2 huruf · navigasi ↓ ↑ · Enter ambil · Esc tutup</p>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-3">Keadaan 2 — mode terpilih</div>
                                <span class="block mb-1 text-xs font-medium" style="color:var(--body)">Obat (cari dari master obat)</span>
                                <div class="flex items-center gap-2">
                                    <div style="{{ $mockInput }}; flex:1; color:var(--ink); background:var(--surface-soft)">AMOXICILLIN 500 MG TABLET</div>
                                    <span class="ds-btn ds-btn-secondary" style="height:36px; padding:8px 14px; font-size:13px; white-space:nowrap">Ubah</span>
                                </div>
                                <p class="ds-caption mt-3" style="color:var(--muted)">
                                    Pilihan terkirim ke form induk (id + nama). Tombol "Ubah" mengosongkan pilihan;
                                    hilang bila form terkunci (readonly).
                                </p>
                            </div>
                        </div>

                        {{-- ===== E. HIERARKI LEVEL 3 ===== --}}
                        <h2 class="ds-title-lg mt-12 mb-3">E · Master hierarkis (Level 3 — contoh: kamar)</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Satu halaman, dua panel: <strong>induk</strong> (bangsal) di kiri dan
                            <strong>anak</strong> (kamar + bed) di kanan. Panel kanan kosong sampai
                            satu baris bangsal diklik — dari situ kamar terfilter, dan tiap kamar
                            membuka panel detail berisi tarif &amp; daftar bed.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow:hidden">

                            {{-- topbar --}}
                            <div class="flex items-center gap-3 px-4 py-2.5" style="background:var(--surface-dark)">
                                <span style="width:22px;height:22px;border-radius:6px;background:var(--accent-lime);display:inline-block"></span>
                                <span class="ds-title-sm" style="color:var(--on-dark)">RSI&nbsp;Madinah</span>
                                <span class="px-2.5 py-1 text-xs rounded-full" style="background:var(--surface-dark-elevated); color:var(--on-dark-soft)">
                                    <strong style="color:var(--on-dark)">Master Kamar</strong> — Bangsal, kamar &amp; bed rawat inap
                                </span>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-[2fr_3fr]" style="background:var(--canvas)">

                                {{-- KIRI: LIST BANGSAL (induk) --}}
                                <div class="lg:border-r" style="position:relative; border-color:var(--hairline)">
                                    <table class="ds-table">
                                        <thead>
                                            <tr><th>Bangsal</th><th class="ds-c">Aksi</th></tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ds-td-strong">AN-NISA</td>
                                                <td class="ds-c"><span class="text-xs" style="color:var(--muted)">✎ &nbsp;🗑</span></td>
                                            </tr>
                                            <tr style="background:var(--success-tint)">
                                                <td>
                                                    <span class="inline-flex items-center gap-2">
                                                        <span style="width:5px;height:20px;border-radius:9999px;background:var(--primary);display:inline-block"></span>
                                                        <span class="text-sm font-semibold" style="color:var(--ink)">SHOFA</span>
                                                    </span>
                                                </td>
                                                <td class="ds-c"><span class="text-xs" style="color:var(--muted)">✎ &nbsp;🗑</span></td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">MARWAH</td>
                                                <td class="ds-c"><span class="text-xs" style="color:var(--muted)">✎ &nbsp;🗑</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <span style="{{ $badge }};position:absolute;top:8px;right:8px">1</span>
                                    <span style="{{ $badge }};position:absolute;top:64px;right:8px;background:var(--info)">2</span>
                                </div>

                                {{-- KANAN: KAMAR + DETAIL (anak) --}}
                                <div style="position:relative">

                                    {{-- toolbar kamar --}}
                                    <div class="px-4 py-3" style="position:relative; background:var(--surface-soft); border-bottom:1px solid var(--hairline)">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div style="{{ $mockInput }}; width:160px">Cari kamar...</div>
                                            <span class="ds-btn ds-btn-primary" style="height:36px; padding:8px 14px; font-size:13px">+ Tambah Data Kamar Baru</span>
                                        </div>
                                        <span style="{{ $badge }};position:absolute;top:8px;right:8px">3</span>
                                    </div>

                                    {{-- rekap --}}
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 px-4 py-2 ds-caption" style="background:var(--surface-soft); border-bottom:1px solid var(--hairline); color:var(--muted)">
                                        <span><strong style="color:var(--body)">KAMAR</strong> Total 4 ·
                                            <span style="color:var(--success-deep)">● Aktif 3</span> ·
                                            <span style="color:var(--error)">● Non-Aktif 1</span></span>
                                        <span><strong style="color:var(--body)">TEMPAT TIDUR</strong>
                                            <span style="color:var(--success-deep)">● Aktif 8</span> ·
                                            <span style="color:var(--error)">● Non-Aktif 2</span></span>
                                    </div>

                                    <div class="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2">

                                        {{-- tabel kamar terfilter --}}
                                        <div style="position:relative; border:1px solid var(--hairline); border-radius:12px; overflow:hidden">
                                            <table class="ds-table">
                                                <thead>
                                                    <tr><th>Kamar — <span style="color:var(--primary); text-transform:none">SHOFA</span></th><th class="ds-c">Status</th></tr>
                                                </thead>
                                                <tbody>
                                                    <tr style="background:var(--success-tint)">
                                                        <td>
                                                            <span class="flex items-center gap-2">
                                                                <span style="width:5px;height:28px;border-radius:9999px;background:var(--primary);display:inline-block;flex:none"></span>
                                                                <span>
                                                                    <span class="block text-sm font-semibold" style="color:var(--ink)">SHOFA 101</span>
                                                                    <span class="block text-xs" style="color:var(--muted)"><span style="font-family:var(--mono)">S101</span> · KELAS 1</span>
                                                                </span>
                                                            </span>
                                                        </td>
                                                        <td class="ds-c">
                                                            <span class="block px-2 py-0.5 mx-auto text-xs font-medium rounded-full w-max" style="background:var(--success-tint); color:var(--success-deep)">Aktif</span>
                                                            <span class="block px-2 py-0.5 mx-auto mt-1 text-xs font-medium rounded-full w-max" style="background:var(--info-tint); color:var(--info-deep)">2 Bed</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <span class="block text-sm font-semibold" style="color:var(--ink)">SHOFA 102</span>
                                                            <span class="block text-xs" style="color:var(--muted)"><span style="font-family:var(--mono)">S102</span> · KELAS 2</span>
                                                        </td>
                                                        <td class="ds-c">
                                                            <span class="block px-2 py-0.5 mx-auto text-xs font-medium rounded-full w-max" style="background:var(--success-tint); color:var(--success-deep)">Aktif</span>
                                                            <span class="block px-2 py-0.5 mx-auto mt-1 text-xs font-medium rounded-full w-max" style="background:var(--info-tint); color:var(--info-deep)">3 Bed</span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <span style="{{ $badge }};position:absolute;top:8px;right:8px">4</span>
                                        </div>

                                        {{-- panel detail kamar --}}
                                        <div style="position:relative; border:1px solid var(--hairline); border-radius:12px; overflow:hidden">
                                            <div class="flex items-start justify-between gap-2 px-4 py-3" style="border-bottom:1px solid var(--hairline)">
                                                <span>
                                                    <span class="block ds-title-sm">SHOFA 101</span>
                                                    <span class="block text-xs" style="color:var(--muted)"><span style="font-family:var(--mono)">S101</span> · KELAS 1</span>
                                                </span>
                                                <span class="text-xs" style="color:var(--muted)">✎ &nbsp;🗑</span>
                                            </div>
                                            <div class="px-4 py-3" style="border-bottom:1px solid var(--hairline)">
                                                <div class="ds-caption-up mb-2">Tarif Kamar</div>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div>
                                                        <span class="block mb-0.5 text-xs" style="color:var(--muted)">Kamar</span>
                                                        <div style="{{ $mockInput }}; height:30px; font-size:12px; color:var(--ink)">250.000</div>
                                                    </div>
                                                    <div>
                                                        <span class="block mb-0.5 text-xs" style="color:var(--muted)">Askep</span>
                                                        <div style="{{ $mockInput }}; height:30px; font-size:12px; color:var(--ink)">50.000</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="px-4 py-3">
                                                <div class="flex items-center justify-between gap-2 mb-2">
                                                    <span class="ds-caption-up">Tempat Tidur (2)</span>
                                                    <span class="ds-btn ds-btn-secondary" style="height:28px; padding:4px 10px; font-size:12px">+ Tambah Bed</span>
                                                </div>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach (['S101-A', 'S101-B'] as $mockBed)
                                                        <span class="inline-flex items-center gap-2 px-2.5 py-1.5 text-xs rounded-lg" style="border:1px solid var(--hairline); background:var(--surface-soft)">
                                                            <span class="font-bold" style="font-family:var(--mono); color:var(--ink)">{{ $mockBed }}</span>
                                                            <span style="color:var(--muted)">✎ ✕</span>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <span style="{{ $badge }};position:absolute;top:8px;right:8px">5</span>
                                            <span style="{{ $badge }};position:absolute;bottom:8px;right:8px;background:var(--info)">6</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- legenda hierarki --}}
                        <div class="grid grid-cols-1 gap-2 mt-4 sm:grid-cols-2">
                            @foreach ([
                                ['1', 'Panel INDUK — list bangsal (komponen master-bangsal); klik baris = memilih konteks', ''],
                                ['2', 'Baris terpilih — highlight hijau + bar kiri; mengirim event bangsal.selected ke panel kanan', 'background:var(--info)'],
                                ['3', 'Toolbar kamar — baru muncul setelah bangsal dipilih; pencarian & Tambah hanya berlaku utk bangsal aktif', ''],
                                ['4', 'List ANAK — kamar terfilter bangsal aktif; klik baris membuka panel detail di sebelahnya', ''],
                                ['5', 'Panel detail kamar — tarif + daftar bed; Edit/Hapus kamar dari sini', ''],
                                ['6', 'Tambah/Edit Bed — kirim openCreateBed(roomId); event saved membawa entity + roomId → refresh presisi', 'background:var(--info)'],
                            ] as [$num, $ket, $extra])
                                <div class="flex items-start gap-2.5">
                                    <span style="{{ $badge }}; margin-top:2px; {{ $extra }}">{{ $num }}</span>
                                    <span class="ds-body-sm">{{ $ket }}</span>
                                </div>
                            @endforeach
                        </div>

                        <p class="ds-body-md mt-8 mb-3" style="max-width:62ch">
                            Di balik layar, ketiganya tetap memakai pola event yang sama dengan bab C —
                            hanya rantainya lebih panjang:
                        </p>
                        <div class="grid items-center grid-cols-1 gap-3 lg:grid-cols-[1fr_auto_1fr_auto_1fr]">
                            <div class="ds-card-outline" style="padding:16px">
                                <div class="ds-caption-up mb-1">Induk</div>
                                <div class="ds-title-sm">List Bangsal</div>
                                <p class="ds-caption mt-1" style="color:var(--muted)">/master/kamar — pilih bangsal</p>
                            </div>
                            <div class="ds-code text-center" style="color:var(--primary); white-space:nowrap">── bangsal.selected ──▶</div>
                            <div class="ds-card-outline" style="padding:16px; border-color:var(--primary)">
                                <div class="ds-caption-up mb-1">Anak</div>
                                <div class="ds-title-sm">List Kamar + panel detail</div>
                                <p class="ds-caption mt-1" style="color:var(--muted)">kamar terfilter bangsal aktif; panel bed per kamar</p>
                            </div>
                            <div class="ds-code text-center" style="color:var(--primary); white-space:nowrap">── openCreateBed(roomId) ──▶</div>
                            <div class="ds-card-outline" style="padding:16px">
                                <div class="ds-caption-up mb-1">Modal</div>
                                <div class="ds-title-sm">Form Kamar / Bed</div>
                                <p class="ds-caption mt-1" style="color:var(--muted)">saved membawa entity + roomId → refresh presisi</p>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Ingin melihat komponen aslinya hidup (bisa diklik &amp; diketik)? Buka
                                <a href="{{ route('panduan-dev') }}" wire:navigate class="hover:underline" style="color:var(--primary)">halaman Standarisasi UI</a>
                                — katalog interaktif seluruh komponen; halaman ini fokus ke <em>peta</em> penempatannya.
                            </span>
                        </div>
                    </section>