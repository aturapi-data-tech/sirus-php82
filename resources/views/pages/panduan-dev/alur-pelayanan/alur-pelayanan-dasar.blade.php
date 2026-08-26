                    {{-- ====== PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Alur Pelayanan Pasien di SIMRS</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Tutorial ini memetakan <strong>perjalanan satu pasien</strong> di dalam sistem —
                            dari didaftarkan, dilayani dokter (EMR), mengambil obat, sampai membayar di kasir —
                            beserta <strong>menu yang dipakai, role yang bertanggung jawab, dan status yang
                            berubah</strong> di tiap tahap. Alur diklasifikasikan per jalur:
                            <strong>Rawat Jalan (RJ)</strong>, <strong>UGD</strong>, dan <strong>Rawat Inap (RI)</strong>.
                        </p>

                        {{-- Diagram tahap RJ --}}
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-4" style="color:var(--muted)">Empat tahap Rawat Jalan</div>
                            <div class="flex flex-wrap items-stretch gap-2">
                                @foreach ([
                                    ['1', 'Daftar RJ', 'Pendaftaran & SEP', 'rj-daftar'],
                                    ['2', 'Pelayanan RJ', 'EMR dokter/perawat', 'rj-pelayanan'],
                                    ['3', 'Apotek RJ', 'Telaah & serah obat', 'rj-apotek'],
                                    ['4', 'Kasir RJ', 'Administrasi & bayar', 'rj-kasir'],
                                ] as [$no, $judul, $sub, $target])
                                    <button type="button" x-on:click="go('{{ $target }}')"
                                        class="flex-1 min-w-[150px] text-left rounded-xl p-4 transition hover:shadow-md"
                                        style="background:var(--surface-card)">
                                        <div class="ds-caption-up" style="color:var(--primary)">Tahap {{ $no }}</div>
                                        <div class="ds-title-md" style="color:var(--ink)">{{ $judul }}</div>
                                        <div class="ds-body-sm" style="color:var(--muted)">{{ $sub }}</div>
                                    </button>
                                    @if (!$loop->last)
                                        <div class="self-center ds-title-md" style="color:var(--muted-soft)">→</div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        {{-- Peta besar: alur pasien (garis penuh) + aliran biaya (putus-putus) --}}
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-2" style="color:var(--muted)">Peta besar satu kunjungan RJ</div>
                            <div class="overflow-x-auto">
                                <svg viewBox="0 0 860 396" style="min-width:700px; width:100%; font-family:inherit">
                                    <defs>
                                        <marker id="panah" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7"
                                            markerHeight="7" orient="auto-start-reverse">
                                            <path d="M 0 0 L 10 5 L 0 10 z" fill="var(--muted)" />
                                        </marker>
                                        <marker id="panah-biaya" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7"
                                            markerHeight="7" orient="auto-start-reverse">
                                            <path d="M 0 0 L 10 5 L 0 10 z" fill="var(--primary)" />
                                        </marker>
                                    </defs>

                                    {{-- ── Baris atas: alur pasien ── --}}
                                    <rect x="20" y="40" width="150" height="64" rx="12" fill="var(--surface-card)" />
                                    <text x="95" y="66" text-anchor="middle" font-size="12" fill="var(--primary)"
                                        font-weight="700">TAHAP 1</text>
                                    <text x="95" y="86" text-anchor="middle" font-size="14" font-weight="600"
                                        fill="var(--ink)">Daftar RJ</text>

                                    <rect x="250" y="40" width="190" height="64" rx="12" fill="var(--surface-card)" />
                                    <text x="345" y="66" text-anchor="middle" font-size="12" fill="var(--primary)"
                                        font-weight="700">TAHAP 2</text>
                                    <text x="345" y="86" text-anchor="middle" font-size="14" font-weight="600"
                                        fill="var(--ink)">Pelayanan RJ (EMR)</text>

                                    <rect x="690" y="40" width="150" height="64" rx="12" fill="var(--surface-card)" />
                                    <text x="765" y="66" text-anchor="middle" font-size="12" fill="var(--primary)"
                                        font-weight="700">TAHAP 4</text>
                                    <text x="765" y="86" text-anchor="middle" font-size="14" font-weight="600"
                                        fill="var(--ink)">Kasir RJ</text>

                                    <line x1="170" y1="72" x2="244" y2="72" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <line x1="440" y1="72" x2="684" y2="72" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <text x="562" y="62" text-anchor="middle" font-size="11" fill="var(--muted)">selesai
                                        dilayani</text>

                                    {{-- ── Cabang dari EMR (4 cabang) ── --}}
                                    <rect x="45" y="220" width="145" height="56" rx="12" fill="var(--surface-card)" />
                                    <text x="117" y="244" text-anchor="middle" font-size="13" font-weight="600"
                                        fill="var(--ink)">Modul Laborat</text>
                                    <text x="117" y="262" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">P → C → H</text>

                                    <rect x="205" y="220" width="155" height="56" rx="12" fill="var(--surface-card)" />
                                    <text x="282" y="244" text-anchor="middle" font-size="13" font-weight="600"
                                        fill="var(--ink)">Modul Radiologi</text>
                                    <text x="282" y="262" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">foto + bacaan</text>

                                    <rect x="375" y="220" width="150" height="56" rx="12" fill="var(--surface-card)" />
                                    <text x="450" y="244" text-anchor="middle" font-size="13" font-weight="600"
                                        fill="var(--ink)">Kamar Operasi</text>
                                    <text x="450" y="262" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">OK — A → L</text>

                                    <rect x="540" y="220" width="170" height="56" rx="12" fill="var(--surface-card)" />
                                    <text x="625" y="238" text-anchor="middle" font-size="12" fill="var(--primary)"
                                        font-weight="700">TAHAP 3</text>
                                    <text x="625" y="256" text-anchor="middle" font-size="13" font-weight="600"
                                        fill="var(--ink)">Apotek RJ</text>
                                    <text x="625" y="270" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">e-resep → serah obat</text>

                                    <line x1="285" y1="104" x2="135" y2="214" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <text x="180" y="150" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">order lab</text>
                                    <line x1="330" y1="104" x2="285" y2="214" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <text x="290" y="165" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">order rad</text>
                                    <line x1="380" y1="104" x2="445" y2="214" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <text x="432" y="150" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">kirim OK</text>
                                    <line x1="430" y1="104" x2="605" y2="214" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <text x="555" y="140" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">e-resep</text>

                                    {{-- ── Aliran biaya (putus-putus) → pos biaya → kasir ── --}}
                                    <rect x="690" y="320" width="150" height="48" rx="24" fill="none"
                                        stroke="var(--primary)" stroke-width="1.5" />
                                    <text x="765" y="340" text-anchor="middle" font-size="12" font-weight="600"
                                        fill="var(--primary)">Pos Biaya</text>
                                    <text x="765" y="356" text-anchor="middle" font-size="11"
                                        fill="var(--primary)">kunjungan</text>

                                    <line x1="117" y1="276" x2="117" y2="344" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" />
                                    <line x1="117" y1="344" x2="684" y2="344" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" marker-end="url(#panah-biaya)" />
                                    <line x1="282" y1="276" x2="282" y2="344" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" />
                                    <line x1="450" y1="276" x2="450" y2="344" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" />
                                    <text x="462" y="316" font-size="10" fill="var(--primary)">Trf Biaya</text>
                                    <line x1="625" y1="276" x2="625" y2="344" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" />
                                    <line x1="765" y1="320" x2="765" y2="110" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" marker-end="url(#panah-biaya)" />
                                    <text x="782" y="215" font-size="11" fill="var(--primary)"
                                        transform="rotate(90 782 215)" text-anchor="middle">dibayar di kasir</text>
                                </svg>
                            </div>
                            <div class="flex flex-wrap gap-4 mt-2">
                                <span class="ds-body-sm inline-flex items-center gap-2" style="color:var(--muted)">
                                    <span style="display:inline-block;width:26px;border-top:2px solid var(--muted)"></span>
                                    alur pasien / dokumen
                                </span>
                                <span class="ds-body-sm inline-flex items-center gap-2" style="color:var(--muted)">
                                    <span style="display:inline-block;width:26px;border-top:2px dashed var(--primary)"></span>
                                    aliran biaya (otomatis)
                                </span>
                            </div>
                        </div>

                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Dua "rel" berjalan sejajar di sepanjang alur:
                        </p>
                        <ul class="ds-body-md mb-6 space-y-2" style="max-width:62ch; list-style:disc; padding-left:1.2em">
                            <li><strong>Rel klinis</strong> — EMR (screening, anamnesis, pemeriksaan, diagnosa,
                                e-resep, order penunjang). Hasilnya tersimpan sebagai JSON kunjungan
                                (<span class="ds-code">datadaftarpolirj_json</span>).</li>
                            <li><strong>Rel administrasi</strong> — pos biaya (poli, jasa, obat, lab, radiologi, …)
                                yang terkumpul otomatis dari tindakan klinis, lalu dibayar di kasir.</li>
                        </ul>
                        <p class="ds-body-md" style="max-width:62ch">
                            Untuk pasien BPJS ada rel ketiga: <strong>stempel antrean BPJS (taskId 1–7)</strong> —
                            waktu tiap tahap dilaporkan ke BPJS untuk menghitung waktu tunggu. Ringkasannya ada di
                            seksi <button type="button" class="hover:underline" style="color:var(--primary)"
                                x-on:click="go('rj-status')">Siklus Status &amp; taskId</button>.
                        </p>
                    </section>

                    {{-- ====== PETA MODUL ====== --}}
                    <section x-show="section === 'peta-modul'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Peta Modul &amp; Role (Rawat Jalan)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Satu tahap = satu menu utama. Role menentukan menu mana yang tampil di APP
                            (diatur di <span class="ds-code">app/Services/AppMenu.php</span>).
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Tahap</th>
                                            <th>Menu (URL)</th>
                                            <th>Role</th>
                                            <th>Fungsi utama</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['1. Pendaftaran', 'Daftar Rawat Jalan — /rawat-jalan/daftar', 'Mr, Admin, Supervisor Tu, Manager Umum', 'Daftarkan pasien ke poli, klaim & SEP, antrean'],
                                            ['1b. Booking', 'Booking Rawat Jalan — /rawat-jalan/booking', 'Mr, Admin, Supervisor Tu, Manager Umum', 'Aktivasi booking Mobile JKN jadi pendaftaran'],
                                            ['2. Pelayanan', 'Pelayanan Rawat Jalan — /rawat-jalan/pelayanan', 'Dokter, Perawat, Admin, Manager Medis', 'EMR poli: pengkajian s/d resep'],
                                            ['3. Apotek', 'Antrian Apotek RJ — /transaksi/rj/antrian-apotek-rj (atau tab RJ di /transaksi/apotek)', 'Apoteker, Admin, Manager Medis', 'Telaah resep/obat, serah obat'],
                                            ['4. Kasir', 'Antrian Kasir RJ — /transaksi/rj/antrian-kasir-rj (atau tab RJ di /transaksi/kasir)', 'Tu, Admin, Supervisor Tu, Manager Umum', 'Administrasi biaya & pembayaran'],
                                            ['Pendukung', 'Jadwal Kontrol Pasien — /jadwal-kontrol', 'Mr, Admin, Tu, Supervisor Tu, Manager Umum', 'Geser tanggal SKDP + update BPJS'],
                                            ['Pendukung', 'Daftar Pasien Bulanan RJ — /rawat-jalan/daftar-bulanan', 'Casemix dkk.', 'Rekap bulanan, berkas klaim'],
                                        ] as [$tahap, $menu, $role, $fungsi])
                                            <tr>
                                                <td class="ds-td-token">{{ $tahap }}</td>
                                                <td class="ds-td-strong">{{ $menu }}</td>
                                                <td class="ds-td-meta">{{ $role }}</td>
                                                <td>{{ $fungsi }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Satu kunjungan RJ = satu baris <span class="ds-code">rstxn_rjhdrs</span>
                                (kunci <span class="ds-code">rj_no</span>). Semua isi EMR + SEP + taskId +
                                e-resep hidup di satu kolom JSON <span class="ds-code">datadaftarpolirj_json</span> —
                                setiap tahap membaca &amp; menambal JSON yang sama.
                            </span>
                        </div>
                    </section>

                    {{-- ====== MASTER PASIEN ====== --}}
                    <section x-show="section === 'master-pasien'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Master Pasien — Fondasi Identitas</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Sebelum alur mana pun berjalan, pasien harus punya <strong>satu No. RM</strong> di
                            Master Pasien (<span class="ds-code">/master/pasien</span>) — sumber identitas
                            tunggal yang dipakai pendaftaran RJ, UGD, dan RI. Dari sini pula tombol pintas ke
                            tiga pendaftaran tersedia, dan LOV "Cari Pasien" di semua form membaca data ini.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => null, 'judul' => 'Pasien baru?', 'sub' => 'cari dulu — cegah RM ganda'],
                                ['chip' => 'RM', 'judul' => 'Tambah Data Pasien', 'sub' => 'identitas + NIK + BPJS', 'chipWarna' => 'sky'],
                                ['chip' => 'IHS', 'judul' => 'Patient UUID', 'sub' => 'ID SATUSEHAT per pasien', 'chipWarna' => 'green'],
                                ['chip' => null, 'judul' => 'Siap didaftarkan', 'sub' => 'RJ · UGD · RI'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Tampilan program</div>
                        @include('pages.panduan-dev.alur-pelayanan.partial-galeri', ['gambarList' => [
                            ['src' => 'images/panduan-dev/alur/master-pasien/01-list.png', 'caption' => 'Master Pasien — ±133 ribu pasien; cari nama/NRM/NIK, tombol pintas Pendaftaran Rawat Jalan / UGD / Rawat Inap, Tambah Data Pasien Baru, dan Edit/Hapus per baris.'],
                            ['src' => 'images/panduan-dev/alur/master-pasien/02-data-pasien.png', 'caption' => 'Tab Data Pasien — data dasar (nama + gelar, tempat/tanggal lahir dengan umur auto), data sosial (JK, agama, pendidikan, pekerjaan), dan data budaya; toggle "Pasien Tidak Dikenal" untuk pasien tak sadar.'],
                            ['src' => 'images/panduan-dev/alur/master-pasien/03-identitas-alamat.png', 'caption' => 'Tab Identitas & Alamat — Patient UUID (IHS SATUSEHAT, bisa di-generate ulang), NIK, ID BPJS, paspor; alamat KTP vs domisili dengan LOV desa berkode wilayah (desa → kecamatan → kab → provinsi).'],
                            ['src' => 'images/panduan-dev/alur/master-pasien/04-kontak-keluarga.png', 'caption' => 'Tab Kontak & Keluarga — No. HP pasien + penanggung jawab (nama, HP, hubungan) + data ayah/ibu; penting untuk consent & penjamin.'],
                            ['src' => 'images/panduan-dev/alur/master-pasien/05-rekam-medis.png', 'caption' => 'Tab Rekam Medis — riwayat kunjungan pasien lintas jalur langsung dari master (poli/dokter, diagnosis, terapi, Copy Resep / Resume Medis).'],
                            ['src' => 'images/panduan-dev/alur/master-pasien/06-status-kunci.png', 'caption' => 'Tab Status Kunci — lockstatus menandai pasien sedang aktif di satu jalur (cegah daftar ganda UGD/RJ/RI); otomatis lepas saat pulang, tombol Reset hanya untuk yang nyangkut.'],
                        ]])
                    </section>