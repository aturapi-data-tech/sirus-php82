                    {{-- ====== 04 AUTENTIKASI ====== --}}
                    <section x-show="section === 'autentikasi'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Cara Kerja</div>
                        <h1 class="ds-display-md mb-4">Autentikasi</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            BPJS memakai <strong>satu user SIRUS khusus</strong> (tabel <span class="ds-code">users</span>).
                            Pertama BPJS menukar username + password dengan token, lalu token itu dikirim di setiap
                            panggilan berikutnya. Token berlaku <strong>12 jam</strong>.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">token lalu panggilan</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['token'] }}</pre>
                        </div>

                        <div class="mb-3 ds-caption-up">Dari mana username &amp; password BPJS</div>
                        @foreach ([
                            ['K1', 'Bukan dari .env — dari tabel users SIRUS.',
                             'token() menjalankan Auth::attempt dengan kolom name = x-username dan password = x-password (bcrypt). Akun BPJS adalah satu user SIRUS biasa; username & password inilah yang didaftarkan RS di portal BPJS.'],
                            ['K2', 'Membuat / mengganti password: menu Sistem → User Control.',
                             'Ganti password di SIRUS = BPJS langsung gagal minta token sampai password baru juga diperbarui di portal BPJS. Lakukan berbarengan.'],
                            ['K3', 'Beri akun itu tanpa role.',
                             'Akun yang sama juga bisa login ke layar SIRUS. Tanpa role, menu SIRUS kosong untuknya. Status nonaktif (active_status 0) TIDAK menghentikan token API — Auth::attempt hanya mencocokkan nama & password.'],
                            ['K4', 'Kunci tanda tangan token tertulis di kode.',
                             'TraitJWTRsiMadinah::privateKey() — bawaan project lama, bukan dari .env. Mengubahnya membuat semua token yang beredar tidak sah; BPJS cukup minta token baru lewat /auth.'],
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

                        <div class="grid grid-cols-1 gap-4 mt-6 md:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--primary)">Kode jawaban</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    <li>• <strong>200</strong> — berhasil</li>
                                    <li>• <strong>201</strong> — ditolak / gagal validasi (konvensi BPJS, bukan "created")</li>
                                    <li>• Pesan untuk pasien ada di <span class="ds-code">metadata.message</span></li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--muted-soft)">Setiap panggilan dicatat</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    <li>• Request (method, URL, IP, header, body) + response masuk tabel <span class="ds-code">api_log_status</span> — termasuk header <span class="ds-code">x-password</span> panggilan /auth apa adanya, jadi akses ke tabel ini harus dibatasi</li>
                                    <li>• Panggilan keluar ke server BPJS tercatat di <span class="ds-code">web_log_status</span> — lihat menu Log BPJS</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 05 AMBIL ANTREAN ====== --}}
                    <section x-show="section === 'ambil-antrean'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Cara Kerja</div>
                        <h1 class="ds-display-md mb-4">Ambil Antrean</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Endpoint terpenting: membuat booking. Belum ada pendaftaran RJ di tahap ini — yang dibuat
                            hanya baris <span class="ds-code">referensi_mobilejkn_bpjs</span> berstatus
                            <span class="ds-code">Belum</span>.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">POST /ambilantrean</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['ambil'] }}</pre>
                        </div>

                        <div class="mb-3 ds-caption-up">Urutan pemeriksaan — berhenti di cek pertama yang gagal</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Cek</th>
                                            <th>Pesan bila gagal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['1', 'Token & user valid', 'Unauthorized: …'],
                                            ['2', 'Nomor kartu ada di Master Pasien', 'termasuk Pasien Baru … daftar melalui pendaftaran offline'],
                                            ['3', 'NIK sama dengan nik_bpjs di Master Pasien', 'NIK anda … berbeda'],
                                            ['4', 'Format field (kartu 13 digit, NIK 16 digit, tanggal Y-m-d, jenis kunjungan 1–4)', 'pesan validasi field'],
                                            ['5', 'Tanggal belum lewat, bukan hari ini (bila H+0 dilarang), maks. 35 hari', 'Tanggal periksa sudah terlewat / … minimal H+1 / … hari ke depan'],
                                            ['6', 'Dokter & poli dikenal (kode BPJS di master)', 'Dokter tidak ditemukan / Poli tidak ditemukan'],
                                            ['7', 'Ada jadwal dokter di poli itu pada hari tsb', 'Pendaftaran ke Poli … tidak tersedia'],
                                            ['8', 'Sisa kuota > 0 (dicek dua kali: sebelum & di dalam lock)', 'Quota Poli … tidak tersedia'],
                                            ['9', 'NIK belum punya booking aktif di tanggal yang sama', 'Terdapat Antrian (…) dengan nomor NIK yang sama …'],
                                        ] as [$nomor, $cek, $pesan])
                                            <tr>
                                                <td>{{ $nomor }}</td>
                                                <td>{{ $cek }}</td>
                                                <td><span class="ds-body-sm" style="color:var(--muted)">{{ $pesan }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-card-outline mb-3" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">kuota &amp; nomor antrean</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['kuota'] }}</pre>
                        </div>
                        <p class="ds-body-sm mb-2" style="max-width:62ch; color:var(--muted)">
                            Lock per dokter + tanggal mencegah dua pasien yang menekan tombol bersamaan mendapat nomor
                            yang sama atau melampaui kuota. Kode booking = waktu sekarang + <span class="ds-code">JKN</span>
                            (mis. <span class="ds-code">20261022143000JKN</span>); estimasi dilayani = jam mulai praktik +
                            10 menit per nomor antrean.
                        </p>
                    </section>

                    {{-- ====== 06 CHECKIN ====== --}}
                    <section x-show="section === 'checkin'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Cara Kerja</div>
                        <h1 class="ds-display-md mb-4">Checkin</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Checkin mengubah booking menjadi <strong>pendaftaran rawat jalan sungguhan</strong>.
                            Sejak titik ini pasien tampil di Daftar Rawat Jalan dan Pelayanan RJ seperti pasien loket,
                            dengan klaim BPJS.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">POST /checkinantrean</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['checkin'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--primary)">Kenapa kuota tidak dicek ulang</div>
                                <div class="ds-body-sm">
                                    Kursi pasien sudah "dipesan" saat ambil antrean — booking berstatus Belum ikut dihitung
                                    sebagai terisi. Nomor antrean dari booking dipakai apa adanya agar sama dengan yang
                                    tampil di HP pasien.
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--muted-soft)">Checkin lewat loket</div>
                                <div class="ds-body-sm">
                                    Pasien yang gagal checkin di HP bisa dicheckin petugas lewat menu
                                    <strong>Booking Rawat Jalan</strong> (tombol proses checkin). Hasil akhirnya sama:
                                    pendaftaran RJ + status Checkin.
                                </div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:20px">
                            <div class="ds-caption-up mb-2" style="color:var(--primary)">Logika checkin ada di DUA tempat — ubah keduanya</div>
                            <div class="ds-body-sm mb-3">
                                Checkin dari HP dan checkin di loket adalah kode terpisah, bukan satu fungsi yang dipakai bersama.
                                Mengubah aturan checkin (batas jam, shift, kolom pendaftaran RJ, tarif admin, isi yang
                                dilaporkan ke BPJS) di satu tempat saja membuat hasil checkin HP dan loket berbeda.
                            </div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th>Checkin dari HP</th>
                                            <th>Checkin di loket</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['Berkas', 'Api/MobileJkn/AntrolBPJSController.php → checkinantrean()', 'pages/transaksi/rj/booking-rj/⚡booking-rj.blade.php → prosesCheckin()'],
                                            ['Dipicu oleh', 'BPJS (pasien menekan checkin di Mobile JKN)', 'petugas di menu Booking Rawat Jalan'],
                                            ['Trait BPJS', 'Traits\MobileJkn\AntrianTrait', 'Traits\BPJS\AntrianTrait'],
                                            ['Cek kuota', 'tidak (sudah dipesan saat booking)', 'ya, dicek ulang dari pendaftaran RJ'],
                                            ['Tarif admin', 'langsung dari master dokter', 'fetchAdminPricesBpjsBooking()'],
                                        ] as [$aspek, $dariHp, $diLoket])
                                            <tr>
                                                <td><strong>{{ $aspek }}</strong></td>
                                                <td><span class="ds-body-sm">{{ $dariHp }}</span></td>
                                                <td><span class="ds-body-sm">{{ $diLoket }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 07 ENDPOINT LAIN ====== --}}
                    <section x-show="section === 'endpoint-lain'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Cara Kerja</div>
                        <h1 class="ds-display-md mb-4">Batal, Status, Sisa, Operasi</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Semua wajib header <span class="ds-code">x-username</span> + <span class="ds-code">x-token</span>.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Endpoint</th>
                                            <th>Input</th>
                                            <th>Yang dilakukan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['POST /batalantrean', 'kodebooking, keterangan', 'Status → Batal + waktu batal. Ditolak bila sudah Batal atau sudah Checkin.'],
                                            ['POST /statusantrean', 'kodepoli, kodedokter, tanggalperiksa, jampraktek', 'Info kuota & antrean poli-dokter: total, sisa, nomor yang sedang dipanggil. Hanya baca.'],
                                            ['POST /sisaantrean', 'kodebooking', 'Posisi antrean pasien sendiri — hanya untuk booking yang sudah Checkin. Hanya baca.'],
                                            ['POST /pasienbaru', 'data pasien', 'Selalu menolak: RS belum menerima pasien baru dari Mobile JKN.'],
                                            ['POST /jadwaloperasirs', 'tanggalawal, tanggalakhir', 'Daftar jadwal operasi RS (booking_operasi) dalam rentang tanggal. Hanya baca.'],
                                            ['POST /jadwaloperasipasien', 'nopeserta', 'Jadwal operasi milik satu peserta BPJS. Hanya baca.'],
                                            ['POST /x', 'kodebooking, waktu', 'Endpoint uji dari project lama, TANPA autentikasi, tidak dipakai BPJS. Kandidat dihapus.'],
                                        ] as [$endpoint, $input, $guna])
                                            <tr>
                                                <td><span class="ds-code">{{ $endpoint }}</span></td>
                                                <td>{{ $input }}</td>
                                                <td>{{ $guna }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p class="ds-body-sm" style="max-width:62ch; color:var(--muted)">
                            "Hanya baca" artinya tidak mengubah data pelayanan — tetapi setiap panggilan tetap menulis
                            satu baris log di <span class="ds-code">api_log_status</span>.
                        </p>
                    </section>
