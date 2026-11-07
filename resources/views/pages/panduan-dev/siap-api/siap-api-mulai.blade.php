                    {{-- ====== 01 — MULAI ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Apa itu API SIAP</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            <strong>SIAP</strong> (Sistem Informasi Administrasi Pegawai) menghitung gaji karyawan, termasuk slip <strong>dokter spesialis</strong> (gaji pokok, tunjangan, potongan RS, PPh 21 &mdash; strukturnya disetel di SIAP). Yang hanya diketahui <strong>SIRUS</strong> adalah transaksi jasanya: uang periksa, jasa tindakan, visite, konsul, operasi, radiologi. Supaya Keuangan tidak menyalin angka dengan tangan, SIRUS membuka <strong>API baca-saja</strong> yang ditarik SIAP tiap bulan jasa; di layar, data yang sama dipantau lewat Manajemen &rarr; Monitoring Keuangan &rarr; <strong>Monitoring Jasa Dokter</strong>.
                        </p>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Di sini SIRUS adalah <strong>server</strong>: menjawab permintaan, tidak pernah memanggil SIAP. SIAP menyimpan hasilnya sebagai <em>snapshot berversi</em> — bila angka di SIRUS berubah sesudah ditarik, SIAP membuat versi baru dan membandingkannya; SIRUS tidak perlu tahu.
                        </p>
                        <div class="grid grid-cols-1 gap-4 mb-8 md:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--primary)">SIRUS sebagai server</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    <li>• Menerbitkan token JWT untuk akun API (tabel users, role <strong>Api Siap</strong>)</li>
                                    <li>• Mengirim master dokter, slip gaji dokter per bulan jasa (header + komponen), dan lampiran rincian pasien</li>
                                    <li>• Semua endpoint <span class="ds-code">GET</span> kecuali <span class="ds-code">POST /token</span> — <strong>tidak ada yang menulis</strong></li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--muted-soft)">Yang BUKAN urusan API ini</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    <li>• Menghitung gaji — tetap di modul Gaji Dokter SIRUS (Draft → Final)</li>
                                    <li>• Memetakan dokter ke karyawan — dilakukan di SIAP</li>
                                    <li>• Mengirim data ke BPJS / SATUSEHAT — jalur lain</li>
                                </ul>
                            </div>
                        </div>
                        <div class="ds-card-outline" style="padding:20px">
                            <div class="ds-caption-up mb-2" style="color:var(--muted-soft)">Kebijakan yang tertanam di kode</div>
                            <ul class="ds-body-sm space-y-1.5">
                                    <li>• API hanya dimuat bila <span class="ds-code">SIAP_API_JWT_SECRET</span> terisi ≥ 32 karakter — instalasi tanpa SIAP tidak membuka pintu apa pun</li>
                                    <li>• Periode selalu <strong>bulan jasa</strong> (bulan pelayanan); bulan gaji SIRUS = bulan jasa + 1 dan ikut dikirim</li>
                                    <li>• Angka dikirim mentah seperti di tabel: potongan bernilai negatif, <span class="ds-code">gaji_status</span> D / F apa adanya</li>
                            </ul>
                        </div>
                    </section>

                    {{-- ====== 02 — MULAI ====== --}}
                    <section x-show="section === 'arsitektur'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Arsitektur &amp; URL</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Mobile JKN dipisah di tingkat <strong>host</strong> (URL terdaftar di BPJS, path di root). API SIAP dipisah di tingkat <strong>path</strong>: prefix <span class="ds-code">/api/siap</span> — bawaan di host SIRUS biasa karena pemanggilnya satu server di LAN, dan bisa dipindah ke host sendiri dengan <span class="ds-code">SIAP_API_HOST</span> (pola yang sama dengan Mobile JKN).
                        </p>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">siap → sirus</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['arsitektur'] }}</pre>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">dibandingkan dengan mobile jkn</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['banding'] }}</pre>
                        </div>
                        @foreach ([
                            ['U1', 'Prefix /api/siap dimuat dari bootstrap/app.php.',
                             'Bagian then: Route::prefix(\'api/siap\') dengan middleware SubstituteBindings + throttle:siap-api, hanya bila SIAP_API_JWT_SECRET ≥ 32 karakter. Tidak memakai grup web (tanpa sesi, tanpa CSRF) dan tidak memakai grup api bawaan.'],
                            ['U2', 'SIAP_API_HOST kosong: host SIRUS biasa, ditolak di host Mobile JKN.',
                             'Tanpa Route::domain, /api/siap ikut terjawab di host apa pun yang mengarah ke SIRUS — maka AuthSiapApi dan endpoint token menjawab 404 bila dipanggil lewat MOBILE_JKN_HOST (host publik). Di host SIRUS biasa pintu tetap terkunci token + role + rate limit + SIAP_API_IP_WHITELIST.'],
                            ['U2b', 'SIAP_API_HOST diisi: hanya host itu, seperti Mobile JKN.',
                             'Route dimuat dengan Route::domain(SIAP_API_HOST) → host lain 404. BatasiHostMobileJkn juga menutup halaman web SIRUS di host itu (kecuali nilainya sama dengan host APP_URL, supaya salah isi tidak mematikan SIRUS). SIAP_API_URL di SIAP harus memakai host ini.'],
                            ['U3', 'Satu mesin JWT untuk dua pintu, dua rahasia.',
                             'App\Support\Api\Jwt dipakai Mobile JKN (TraitJWTRsiMadinah) dan SIAP (JwtSiap). Kuncinya berbeda (MOBILE_JKN_JWT_SECRET vs SIAP_API_JWT_SECRET) sehingga token satu pintu tidak pernah membuka pintu lain.'],
                            ['U4', 'Database sama dengan SIRUS, dibaca langsung.',
                             'Controller membaca rsmst_doctors dan RSVIEW_NEWDOCSALARIES lewat GajiDokterLampiran::barisMassal — kelas yang sama dengan modal Rincian di Monitoring Jasa Dokter, jadi angka API = angka layar.'],
                        ] as [$nomor, $judul, $keterangan])
                            <div class="ds-card-outline mb-3" style="padding:18px 20px">
                                <div class="flex items-start gap-3">
                                    <span class="ds-code shrink-0" style="color:var(--primary); font-weight:700">{{ $nomor }}</span>
                                    <div>
                                        <div class="ds-body-md" style="font-weight:600; color:var(--ink)">{{ $judul }}</div>
                                        <div class="ds-body-sm mt-1" style="color:var(--body)">{{ $keterangan }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </section>

                    {{-- ====== 03 — MULAI ====== --}}
                    <section x-show="section === 'peta-file'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Peta File</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Semua berkas yang membentuk API SIAP di SIRUS, dan urutan yang dilewati satu request dari SIAP.
                        </p>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">perjalanan satu request</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['perjalanan'] }}</pre>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Berkas</th>
                                            <th>Peran</th>
                                            <th>Ubah bila…</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['.env → SIAP_API_HOST / JWT_SECRET / TOKEN_TTL / IP_WHITELIST / ROLE', 'Host khusus (kosong = host SIRUS biasa), rahasia JWT (pintu aktif bila ≥ 32 karakter), umur token, whitelist IP, nama role akun API.', 'host API dipisah, rotasi rahasia, server SIAP pindah IP'],
                                            ['config/services.php → siap.*, mobile_jkn.jwt_secret', 'Membaca .env supaya aman dengan config:cache.', 'hampir tidak pernah'],
                                            ['bootstrap/app.php', 'Memuat routes/api-siap.php dengan prefix /api/siap (+ Route::domain bila SIAP_API_HOST diisi) + throttle:siap-api bila rahasia terisi.', 'prefix / host API diubah'],
                                            ['app/Providers/AppServiceProvider.php', 'Rate limiter siap-api (120/menit/IP) dan siap-token (10/menit/IP).', 'batas request diubah'],
                                            ['routes/api-siap.php', 'Daftar endpoint: /token, /dokter, /gaji-dokter/{tahun}/{bulan}/transaksi.', 'SIAP butuh data baru'],
                                            ['app/Http/Controllers/Api/Siap/GajiDokterApiController.php', 'Terbitkan token; susun JSON master dokter dan transaksi mentah per bulan jasa.', 'bentuk JSON berubah — WAJIB sesuaikan App\\Support\\GajiDokterSirus di SIAP'],
                                            ['app/Http/Middleware/AuthSiapApi.php', 'Penjaga: secret ada → bukan host Mobile JKN → whitelist IP → Bearer JWT sah → akun sub ada, aktif, ber-role Api Siap.', 'aturan akun / whitelist'],
                                            ['app/Http/Middleware/BatasiHostMobileJkn.php', 'Halaman web SIRUS → 404 di host Mobile JKN dan di SIAP_API_HOST (bila diisi dan bukan host APP_URL).', 'hampir tidak pernah'],
                                            ['app/Support/Api/Jwt.php', 'Mesin HS256 bersama (encode/decode, base64url & base64 lama).', 'hampir tidak pernah — dipakai Mobile JKN juga'],
                                            ['app/Support/Api/JwtSiap.php', 'Klaim token SIAP (iss sirus, aud siap, sub, exp), TTL, rahasia.', 'umur token / klaim'],
                                            ['app/Console/Commands/BuatAkunApiSiap.php', 'php artisan siap-api:akun — buat / setel ulang akun API + role.', 'nama role / kebijakan akun'],
                                            ['app/Http/Requests/Auth/LoginRequest.php', 'Menolak akun ber-role Api Siap masuk ke web SIRUS.', 'hampir tidak pernah'],
                                            ['app/Support/GajiDokter/GajiDokterLampiran.php', 'BUKAN bagian API: penyusun baris per pasien yang dipakai Monitoring Jasa Dokter DAN endpoint transaksi.', 'rumus baris berubah — angka API ikut'],
                                            ['docs/siap-api.md', 'Referensi teknis (versi teks halaman ini).', 'ikut setiap perubahan di atas'],
                                        ] as [$berkas, $peran, $ubah])
                                            <tr>
                                                <td><span class="ds-code">{{ $berkas }}</span></td>
                                                <td class="ds-body-sm">{{ $peran }}</td>
                                                <td class="ds-body-sm" style="color:var(--muted-soft)">{{ $ubah }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

