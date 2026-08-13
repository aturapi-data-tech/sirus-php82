                    {{-- ====== 01 PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Standarisasi Koding Modul Transaksi</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Domain transaksi adalah jantung SIRUS: perjalanan pasien dari
                            <strong>pendaftaran → pelayanan → kasir</strong> pada tiga jalur
                            (<strong>RJ</strong> rawat jalan, <strong>UGD</strong>, <strong>RI</strong> rawat inap),
                            ditambah tiga lintas-modul yang menempel di setiap jalur:
                            <strong>EMR</strong>, <strong>Modul Dokumen</strong>, dan <strong>Administrasi</strong>.
                            Tutorial ini merangkum pola yang WAJIB ditiru bila kita mengadopsi /
                            membangun modul transaksi baru.
                        </p>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Skala-nya jauh di atas master: EMR RJ saja 51 file (±13.700 baris) —
                            maka disiplin pola menjadi lebih penting, bukan lebih longgar.
                            Semua aturan dari <em>Tutorial Koding Master</em> (2-file, kontrak event,
                            komponen, LOV) tetap berlaku; bab-bab di sini menambahkan pola
                            khas transaksi di atasnya.
                        </p>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">1 Header + JSON CLOB</div>
                                <div class="ds-body-sm">Detail kunjungan hidup di satu kolom JSON (CLOB) pada tabel header — dibaca via <span class="ds-code">OracleLob</span>, ditulis dgn row-lock.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Modal-first</div>
                                <div class="ds-body-sm">EMR, administrasi, dokumen = modal full-screen yang dibuka via event dari list — bukan halaman ber-route sendiri.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Event-driven save</div>
                                <div class="ds-body-sm">Section EMR menyimpan lewat broadcast <span class="ds-code">save-*</span> — satu tombol menyimpan banyak section (silent toast).</div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Peringatan dual-system:</strong> DB Oracle yang sama masih dipakai
                                Oracle Dev 6i (SIMRS lama). Entry dari sistem lama <em>tidak mengisi JSON cache</em> —
                                selalu pertimbangkan data yang JSON-nya kosong/parsial saat menulis fitur.
                            </span>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Dapat tugas menambah fitur?</strong> Langsung ke bab
                                <button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                    x-on:click="go('tambah-fitur')">Alur: Tambah Fitur</button>
                                — step-by-step tiga skenario paling umum (section EMR baru, form modul
                                dokumen baru, pos administrasi baru); bab lain jadi referensi detailnya.
                                Menemukan singkatan asing (SEP, CPPT, PRB...)? Buka bab
                                <button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                    x-on:click="go('glosarium')">Glosarium Istilah</button>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 02 ALUR ====== --}}
                    <section x-show="section === 'alur'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Alur Pasien &amp; Routing</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Tiga jalur, pola tahapan mirip tapi <strong>tidak identik</strong> —
                            jangan blind-copy antar jalur (UGD punya triase &amp; transfer; RI tanpa
                            halaman pelayanan dan billing-nya per-item).
                        </p>

                        {{-- flow RJ --}}
                        <div class="ds-caption-up mb-2">Rawat Jalan (RJ)</div>
                        <div class="flex flex-wrap items-center gap-2 mb-6">
                            @foreach ([['Daftar RJ', '/rj/daftar'], ['Pelayanan', '/rj/pelayanan'], ['Antrian Kasir', 'poll 30s'], ['Administrasi', 'modal'], ['Apotek', 'antrian-apotek-rj']] as $i => [$tahap, $ket])
                                @if ($i > 0)<span class="ds-code" style="color:var(--primary)">▶</span>@endif
                                <span class="ds-card-outline" style="padding:8px 14px">
                                    <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $tahap }}</span>
                                    <span class="block text-xs" style="color:var(--muted)">{{ $ket }}</span>
                                </span>
                            @endforeach
                        </div>

                        {{-- flow UGD --}}
                        <div class="ds-caption-up mb-2">UGD</div>
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            @foreach ([['Daftar UGD', 'triase P0–P3'], ['Pelayanan', '/ugd/pelayanan'], ['Antrian Kasir', 'poll 30s'], ['Administrasi', 'modal'], ['Apotek', 'antrian-apotek-ugd']] as $i => [$tahap, $ket])
                                @if ($i > 0)<span class="ds-code" style="color:var(--primary)">▶</span>@endif
                                <span class="ds-card-outline" style="padding:8px 14px">
                                    <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $tahap }}</span>
                                    <span class="block text-xs" style="color:var(--muted)">{{ $ket }}</span>
                                </span>
                            @endforeach
                        </div>
                        <p class="ds-caption mb-6" style="color:var(--muted)">+ cabang: Transfer ke RI (modal terpisah, default cara masuk "MELALUI IGD").</p>

                        {{-- flow RI --}}
                        <div class="ds-caption-up mb-2">Rawat Inap (RI)</div>
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            @foreach ([['Daftar RI', 'EMR/Adm/Pindah Kamar dari list'], ['Billing per-item', 'imtxn_slshdrs/dtls'], ['Antrian Kasir RI', 'per resep/sls'], ['Daftar Kasir RI', 'per rihdr'], ['Posting Bayar', 'bon/kembalian']] as $i => [$tahap, $ket])
                                @if ($i > 0)<span class="ds-code" style="color:var(--primary)">▶</span>@endif
                                <span class="ds-card-outline" style="padding:8px 14px; {{ $i === 0 ? 'border-color:var(--primary)' : '' }}">
                                    <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $tahap }}</span>
                                    <span class="block text-xs" style="color:var(--muted)">{{ $ket }}</span>
                                </span>
                            @endforeach
                        </div>
                        <p class="ds-caption mb-8" style="color:var(--muted)">RI TIDAK punya halaman pelayanan — EMR dibuka langsung dari daftar-ri.</p>

                        {{-- matrix --}}
                        <h2 class="ds-title-lg mb-3">Matrix jalur × tahap (route)</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Tahap</th><th>RJ</th><th>UGD</th><th>RI</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Pendaftaran</td><td class="ds-td-class">/rawat-jalan/daftar</td><td class="ds-td-class">/ugd/daftar</td><td class="ds-td-class">/ri/daftar</td></tr>
                                    <tr><td class="ds-td-strong">Pelayanan</td><td class="ds-td-class">/rawat-jalan/pelayanan</td><td class="ds-td-class">/ugd/pelayanan</td><td class="ds-body-sm">— (dari daftar-ri)</td></tr>
                                    <tr><td class="ds-td-strong">EMR (host)</td><td class="ds-td-class">emr-rj/emr-rj (modal)</td><td class="ds-td-class">emr-ugd (modal)</td><td class="ds-td-class">emr-ri (modal)</td></tr>
                                    <tr><td class="ds-td-strong">Modul Dokumen</td><td class="ds-td-class">emr-rj/modul-dokumen (4 form)</td><td class="ds-td-class">emr-ugd/modul-dokumen</td><td class="ds-td-class">emr-ri/modul-dokumen (±28 form)</td></tr>
                                    <tr><td class="ds-td-strong">Administrasi</td><td class="ds-td-class">administrasi-rj (modal)</td><td class="ds-td-class">administrasi-ugd</td><td class="ds-td-class">administrasi-ri</td></tr>
                                    <tr><td class="ds-td-strong">Antrian Kasir</td><td class="ds-td-class">/transaksi/rj/antrian-kasir-rj</td><td class="ds-td-class">/transaksi/ugd/antrian-kasir-ugd</td><td class="ds-td-class">/transaksi/kasir/antrian-kasir-ri + daftar-kasir-ri</td></tr>
                                    <tr><td class="ds-td-strong">Apotek</td><td class="ds-td-class">antrian-apotek-rj</td><td class="ds-td-class">antrian-apotek-ugd</td><td class="ds-td-class">ri-resep/antrian-ri-resep + PTO</td></tr>
                                    <tr><td class="ds-td-strong">Rekap bulanan</td><td class="ds-td-class">/rawat-jalan/daftar-bulanan</td><td class="ds-td-class">/ugd/daftar-bulanan</td><td class="ds-td-class">/ri/daftar-bulanan</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="ds-caption mt-3" style="color:var(--muted)">
                            + halaman tab gabungan lintas jalur: /transaksi/kasir · /transaksi/apotek · /transaksi/casemix (wrapper tab RJ+UGD+RI).
                        </p>
                    </section>

                    {{-- ====== 03 DATA ====== --}}
                    <section x-show="section === 'data'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Data Inti &amp; JSON CLOB</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Satu kunjungan = satu baris di tabel <strong>header</strong> per jalur —
                            <span class="ds-code">rstxn_rjhdrs</span> (PK <span class="ds-code">rj_no</span>),
                            <span class="ds-code">rstxn_ugdhdrs</span>, <span class="ds-code">rstxn_rihdrs</span>
                            (PK <span class="ds-code">rihdr_no</span>, dibaca via view <span class="ds-code">rsview_rihdrs</span>).
                            Seluruh detail klinis &amp; administrasi (anamnesa, diagnosa, pos biaya, log)
                            hidup di <strong>satu kolom JSON CLOB</strong> di baris itu.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Membaca — findData* + OracleLob</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['clob-read'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Menulis — read-modify-write + row lock (WAJIB)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['rmw'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Kenapa JSON, bukan tabel normalized?</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>EMR = ratusan field lintas section — satu dokumen JSON per kunjungan jauh lebih sederhana dari puluhan tabel</li>
                                    <li>Satu <span class="ds-code">findData()</span> = seluruh konteks kunjungan</li>
                                    <li>Konsekuensi: WAJIB disiplin lock + merge (jangan replace state yang belum tersimpan — pakai <span class="ds-code">array_replace</span>)</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Jebakan Oracle yang sering kena</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><span class="ds-code">''</span> = NULL di Oracle — jangan <span class="ds-code">&lt;&gt; ''</span>, pakai <span class="ds-code">IS NOT NULL</span></li>
                                    <li>Kolom mixed-case → <span class="ds-code">DB::raw('"namaKolom" as alias')</span></li>
                                    <li>JSON_VALUE tidak didukung — filter via INSTR atau decode di PHP</li>
                                    <li>Detail lengkap: skill <span class="ds-code">oracle-quirks</span></li>
                                </ul>
                            </div>
                        </div>
                    </section>