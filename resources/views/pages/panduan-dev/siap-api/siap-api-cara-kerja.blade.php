                    {{-- ====== 04 — CARA KERJA ====== --}}
                    <section x-show="section === 'autentikasi'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Cara Kerja</div>
                        <h1 class="ds-display-md mb-4">Autentikasi &amp; Akun</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            SIAP memakai <strong>satu akun user SIRUS</strong> (tabel <span class="ds-code">users</span>) ber-role <strong>Api Siap</strong>. Pertama SIAP menukar username + password dengan token, lalu token dikirim sebagai <span class="ds-code">Authorization: Bearer</span> di tiap panggilan. Token berlaku <strong>1 jam</strong>; SIAP menyimpannya 45 menit dan otomatis minta baru.
                        </p>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">token lalu panggilan</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['token'] }}</pre>
                        </div>
                        <div class="mb-3 ds-caption-up">Akun API</div>
                        @foreach ([
                            ['K1', 'Dibuat dengan perintah, bukan dari .env.',
                             'php artisan siap-api:akun siap-gaji membuat role Api Siap (bila belum ada) dan user siap-gaji dengan password acak yang dicetak sekali. Jalankan lagi untuk menyetel ulang password. Perintah menolak mengubah user yang sudah punya role lain.'],
                            ['K2', 'Admin mengelolanya dari User Control.',
                             'Ganti password atau nonaktifkan (active_status 0) seperti user biasa. Middleware mengecek ulang akun pada SETIAP request, jadi akun yang dinonaktifkan langsung kehilangan akses walau tokennya belum kedaluwarsa — beda dengan Mobile JKN yang hanya memeriksa token.'],
                            ['K3', 'Tidak bisa dipakai masuk ke web SIRUS.',
                             'LoginRequest menolak akun ber-role Api Siap dengan pesan "Akun API tidak bisa dipakai untuk masuk ke SIRUS". Akun ini murni untuk mesin.'],
                            ['K4', 'Rahasia tanda tangan dari .env, terpisah dari Mobile JKN.',
                             'SIAP_API_JWT_SECRET (≥ 32 karakter). Mengganti rahasia = semua token SIAP yang beredar tidak sah; SIAP akan minta token baru sendiri (401 dicoba ulang sekali). Rahasia Mobile JKN (MOBILE_JKN_JWT_SECRET) tidak terpengaruh.'],
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
                        <div class="grid grid-cols-1 gap-4 mb-8 md:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--primary)">Kode jawaban</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    <li>• <strong>200</strong> — berhasil</li>
                                    <li>• <strong>401</strong> — kredensial / token / akun ditolak</li>
                                    <li>• <strong>403</strong> — IP di luar whitelist</li>
                                    <li>• <strong>422</strong> — periode tidak valid</li>
                                    <li>• <strong>429</strong> — melebihi batas request</li>
                                    <li>• <strong>503</strong> — rahasia belum diatur (API mati)</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--muted-soft)">Perbedaan dengan Mobile JKN</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    <li>• Header standar (<span class="ds-code">Authorization: Bearer</span>), bukan <span class="ds-code">x-username</span> / <span class="ds-code">x-token</span></li>
                                    <li>• Galat memakai kode HTTP biasa, bukan 201</li>
                                    <li>• Tidak ada log per request ke tabel — tidak ada data pasien yang ditulis</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 05 — CARA KERJA ====== --}}
                    <section x-show="section === 'endpoint'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Cara Kerja</div>
                        <h1 class="ds-display-md mb-4">Endpoint &amp; Bentuk Data</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Enam endpoint: empat baca (token, dokter + struktur gaji, slip per bulan jasa, lampiran), satu tulis (struktur gaji dokter — tahap 1 pemindahan ke SIAP), satu transaksi mentah (tahap 3). Endpoint data mengembalikan JSON yang bentuknya <strong>kontrak dengan SIAP</strong> (<span class="ds-code">App\Support\GajiDokterSirus::ambil()</span> membacanya kolom per kolom) — menambah field aman, mengganti nama / arti field harus disesuaikan di dua sisi.
                        </p>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">GET /api/siap/dokter</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['dokter'] }}</pre>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">GET /api/siap/gaji-dokter/{tahun}/{bulan}</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['periode'] }}</pre>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">GET /api/siap/gaji-dokter/{tahun}/{bulan}/lampiran</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['lampiran'] }}</pre>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">PUT /api/siap/dokter/{dr_id}/struktur-gaji — tahap 1 (hanya bila mode SIAP = struktur disetel di SIAP, selain itu 409)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ '{"basic_salary":6000000,"npwp_status":"Y","npwp":null,"skema_gaji_pokok":"A","potongan_rs_basis":"J","potongan_rs_persen":10,
 "potongan_rs_aturan":null,"pph21_persen":2.5,"tarif_per_kapita_ri":0,"tarif_per_kapita_rj":0,
 "tunjangan_struktural":0,"tunjangan_fungsional":0,"tunjangan_hadir":0,
 "potongan_idi":100000,"potongan_arisan":0,"potongan_koperasi":0,"potongan_angsuran":0,"potongan_bpjs":127600,"potongan_zariyah":0}
→ {"ok":true,"dr_id":"085","struktur":{…},"ditulis_oleh":"siap-gaji","ditulis_pada":"2026-11-06 22:27:40"}
Validasi = aturan form Struktur Gaji Master Dokter (skema A/G/N, basis T/J/N/B, aturan berjenjang lewat GajiDokter::validasiAturan).' }}</pre>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">GET /api/siap/gaji-dokter/{tahun}/{bulan}/transaksi — tahap 3 (opsional ?dr_id=)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ '{"tahun_jasa":"2026","bulan_jasa":"07","mode":{"struktur_gaji_dari_siap":true,"gaji_dokter_nonaktif":false},"jumlah_dokter":36,
 "dokter":[{"dr_id":"085","dr_name":"dr. Nanda Agus Prasetya, SpOG","aktif":true,"struktur":{…sama dengan PUT…},"grup_kapita":[],
   "baris":[{"komponen":"UP RJ","group":"RJ","label":"Uang Periksa Rawat Jalan","seq":1,"tgl":"02/07/2026","reg_no":"097289Z","nama":"BUDI ENDA APRIYANI",
             "keterangan":"","klaim":"JKN MANDIRI","klaim_status":"BPJS","no_sep":"0184R0060726V000223","txn_no":"670903","nominal":50000,"pasien":1}, …]}]}
Langsung dari RSVIEW_NEWDOCSALARIES (+ dokter bertarif kapita) — TIDAK bergantung pada slip SIRUS, jadi tetap bekerja setelah
Slip Gaji Dokter SIRUS dimatikan. SIAP menghitung slipnya sendiri (App\Support\GajiDokterHitung, salinan rumus GajiDokter) dari baris ini.' }}</pre>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-2" style="color:var(--primary)">Pemindahan ke SIAP — mode disetel di SIAP, SIRUS mengikuti</div>
                            <ul class="ds-body-sm space-y-1.5">
                                <li>• Tidak ada saklar .env di SIRUS. Mode dibaca langsung dari tabel <span class="ds-code">SIAP.SIAPMST_PENGATURAN_GDOKS</span> (satu Oracle, beda skema) lewat <span class="ds-code">App\Support\GajiDokter\ModePindahSiap</span>; disetel di SIAP → Gaji Dokter → Struktur Gaji → Mode.</li>
                                <li>• <b>Struktur disetel di SIAP</b> (tahap 1): panel Struktur Gaji di Master Dokter SIRUS jadi baca-saja dan PUT struktur-gaji diterima.</li>
                                <li>• <b>Slip dihitung SIAP</b> (tahap 3): SIAP menghitung dari …/transaksi; di Slip Gaji Dokter SIRUS Proses/Final/Buka Kunci/Hapus ditutup (arsip tetap terbaca & tercetak).</li>
                                <li>• Butuh hak baca sekali (SYSTEM): <span class="ds-code">GRANT SELECT ON siap.siapmst_pengaturan_gdoks TO sirus</span>. Tabel tak terbaca = tahap 0 (SIRUS sumber semuanya). Mode ikut dilaporkan di field <span class="ds-code">mode</span> pada GET /dokter dan …/transaksi.</li>
                            </ul>
                        </div>
                        @foreach ([
                            ['D1', 'Satu bulan jasa = satu panggilan slip + satu panggilan lampiran.',
                             'SIAP menarik keduanya lalu menghitung checksum per dokter (header + komponen + lampiran). Lampiran semua dokter ±1 MB / ±3 detik untuk 36 slip — masih jauh di bawah timeout 120 detik di sisi SIAP.'],
                            ['D2', 'Draft ikut dikirim.',
                             'gaji_status D / F apa adanya. SIAP boleh menarik Draft sebagai pembanding awal; angka pasti adalah tarikan setelah Final. Tidak ada filter status di server supaya SIAP yang memutuskan.'],
                            ['D3', 'Nama dokter kosong tetap dikirim.',
                             'Ada dr_id di master tanpa nama; SIAP mengisinya "(tanpa nama) <dr_id>". Server tidak membersihkan data master.'],
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

                    {{-- ====== 06 — CARA KERJA ====== --}}
                    <section x-show="section === 'keamanan'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Cara Kerja</div>
                        <h1 class="ds-display-md mb-4">Pengaman</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Berlapis, dari luar ke dalam. Tiap lapis berdiri sendiri — mematikan satu lapis tidak membuka yang lain.
                        </p>
                        @foreach ([
                            ['P1', 'Pintu tertutup bila rahasia kosong.',
                             'Route tidak dimuat sama sekali tanpa SIAP_API_JWT_SECRET ≥ 32 karakter → 404, bukan 401. Instalasi SIRUS yang tidak terhubung SIAP tidak punya endpoint ini.'],
                            ['P1b', 'Host: tidak pernah lewat host Mobile JKN; opsional host sendiri.',
                             'SIAP_API_HOST kosong → /api/siap hanya di host SIRUS biasa (404 bila lewat MOBILE_JKN_HOST). Diisi → Route::domain, host lain 404, dan halaman web SIRUS ditutup di host itu.'],
                            ['P2', 'Whitelist IP (opsional, disarankan di produksi).',
                             'SIAP_API_IP_WHITELIST=10.10.1.5 → request dari IP lain dijawab 403 sebelum token diperiksa. Di balik Cloudflare / proxy, trustProxies di bootstrap/app.php membuat request()->ip() memakai X-Forwarded-For.'],
                            ['P3', 'Rate limit.',
                             'siap-api 120 request/menit/IP, siap-token 10/menit/IP — tebak password dibatasi 10 percobaan per menit.'],
                            ['P4', 'Token HS256 ber-exp, diverifikasi hash_equals.',
                             'Klaim iss sirus / aud siap / exp dicek; tanda tangan dibandingkan tahan timing attack. Token tidak disimpan di server (stateless) — pencabutan dilakukan lewat akun (P5).'],
                            ['P5', 'Akun diperiksa tiap request.',
                             'sub di token harus user yang masih ada, aktif, dan ber-role Api Siap. Nonaktifkan dari User Control = akses putus seketika.'],
                            ['P6', 'Satu endpoint tulis yang bersaklar + tanpa sesi.',
                             'Hanya PUT struktur gaji dokter yang menulis, dan itu pun ditolak 409 selama mode di SIAP belum "struktur disetel di SIAP". Tidak memakai cookie / CSRF, tidak menyentuh Auth::login. Password dibandingkan lewat Auth::validate (bcrypt) tanpa membuat sesi.'],
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
                        <div class="ds-card-outline" style="padding:20px">
                            <div class="ds-caption-up mb-2" style="color:var(--primary)">Yang masih bisa diperketat</div>
                            <ul class="ds-body-sm space-y-1.5">
                                    <li>• Isi <span class="ds-code">SIAP_API_HOST</span> bila ingin /api/siap benar-benar terpisah dari host SIRUS (DNS / hosts di server SIAP saja, tidak perlu publik)</li>
                                    <li>• HTTPS wajib bila SIAP dan SIRUS tidak satu LAN — token dan password lewat di body / header</li>
                                    <li>• Log akses per request bila audit memintanya (sekarang hanya galat ke laravel.log)</li>
                            </ul>
                        </div>
                    </section>

