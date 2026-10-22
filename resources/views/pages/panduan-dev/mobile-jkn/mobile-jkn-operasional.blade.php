                    {{-- ====== 08 DI LAYAR SIRUS ====== --}}
                    <section x-show="section === 'di-sirus'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Operasional</div>
                        <h1 class="ds-display-md mb-4">Di Layar SIRUS</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Petugas tidak berinteraksi dengan API. Hasilnya terlihat di menu-menu berikut.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Menu</th>
                                            <th>Role</th>
                                            <th>Gunanya untuk Mobile JKN</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['Booking Rawat Jalan — /rj/booking', 'Admin, Mr, Manager Umum, Supervisor Tu', 'Lihat booking per tanggal/dokter/status, checkin pasien di loket, batalkan booking yang tidak datang, cek status di server BPJS'],
                                            ['Daftar Rawat Jalan', 'Mr, Admin, Supervisor Tu, Manager Umum', 'Pasien yang sudah checkin tampil sebagai pendaftaran BPJS. Peringatan kuota di loket ikut menghitung booking JKN berstatus Belum'],
                                            ['Pelayanan RJ, Apotek RJ', 'sesuai modul', 'Mencatat taskId 4–7 seperti pasien loket'],
                                            ['Log BPJS / E-Klaim API — /database-monitor/log-bpjs', 'Admin', 'Riwayat panggilan keluar ke Antrean RS BPJS (antrean/add, updatewaktu) beserta hasilnya'],
                                        ] as [$menu, $role, $guna])
                                            <tr>
                                                <td>{{ $menu }}</td>
                                                <td>{{ $role }}</td>
                                                <td>{{ $guna }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:20px">
                            <div class="ds-caption-up mb-2" style="color:var(--primary)">Rutin harian yang disarankan</div>
                            <ul class="ds-body-sm space-y-1.5">
                                <li>• Pagi: buka Booking Rawat Jalan tanggal hari ini — siapkan berkas pasien yang booking</li>
                                <li>• Selama praktik: booking Belum tetap memakan kuota — pasien yang pasti tidak datang dibatalkan agar kursinya bisa dipakai pasien loket</li>
                                <li>• Bila pasien protes nomor tidak muncul di dashboard BPJS → cek Log BPJS untuk kode booking itu</li>
                            </ul>
                        </div>
                    </section>

                    {{-- ====== 09 SETUP & DEPLOY ====== --}}
                    <section x-show="section === 'setup'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Operasional</div>
                        <h1 class="ds-display-md mb-4">Setup &amp; Deploy</h1>

                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">.env</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['env'] }}</pre>
                        </div>

                        @foreach ([
                            ['S1', 'Host API harus sama persis dengan yang terdaftar di BPJS.',
                             'Isi MOBILE_JKN_HOST tanpa http:// dan tanpa port. Setelah mengubah .env jalankan php artisan config:clear (atau config:cache bila server memakai cache konfigurasi).'],
                            ['S2', 'Apache cukup satu VirtualHost untuk dua host.',
                             'ServerName dev-sirus.rsimadinah.com + ServerAlias apijkn.rsimadinah.com, keduanya ke folder public SIRUS yang sama. Laravel yang memilah. Contoh lengkap di bawah.'],
                            ['S3', 'HTTPS dipasang di Cloudflare.',
                             'Kedua host lewat Cloudflare, jadi sertifikat publik diurus Cloudflare. Apache cukup HTTP :80, kecuali Cloudflare memakai mode Full (strict) — baru perlu VirtualHost :443 dengan Origin Certificate.'],
                            ['S4', 'Firewall: host API boleh publik, SIRUS jangan.',
                             'Port yang menerima panggilan BPJS harus terbuka dari internet. Middleware BatasiHostMobileJkn menjaga halaman SIRUS tidak terbuka lewat host itu, tetapi akses SIRUS lewat IP/host LAN tetap sebaiknya dibatasi di firewall.'],
                            ['S5', 'Peralihan dari project lama.',
                             'Arahkan host API ke SIRUS (DNS atau VirtualHost), uji dengan langkah di bawah, baru matikan project api-rsimadinah-laravel. Jangan jalankan keduanya melayani host yang sama.'],
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

                        <div class="ds-card-outline mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">apache — dev-sirus + apijkn</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['apache'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">cloudflare &amp; .env</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['cloudflare'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">uji setelah deploy</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['uji'] }}</pre>
                        </div>
                    </section>

                    {{-- ====== 10 MASALAH & PENANGANAN ====== --}}
                    <section x-show="section === 'masalah'" x-cloak>
                        <div class="ds-eyebrow mb-3">11 — Operasional</div>
                        <h1 class="ds-display-md mb-4">Masalah &amp; Penanganan</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Pesan di kolom kiri adalah teks yang dikirim ke aplikasi pasien
                            (<span class="ds-code">metadata.message</span>), jadi pasien biasanya membawa pesan ini saat komplain.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">gejala → periksa</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['masalah'] }}</pre>
                        </div>

                        <div class="ds-card-outline" style="padding:20px">
                            <div class="ds-caption-up mb-2" style="color:var(--primary)">Mengubah kode API</div>
                            <ul class="ds-body-sm space-y-1.5">
                                <li>• Nama field dan format jawaban adalah kontrak BPJS — cocokkan dengan Manual API Antrean RS sebelum mengubah</li>
                                <li>• Jangan menguji endpoint dari laptop yang <span class="ds-code">.env</span>-nya mengarah ke database produksi: setiap panggilan menulis log, ambil antrean dan checkin menulis data pasien</li>
                                <li>• Referensi teknis lengkap: <span class="ds-code">docs/mobile-jkn-api.md</span></li>
                            </ul>
                        </div>
                    </section>
