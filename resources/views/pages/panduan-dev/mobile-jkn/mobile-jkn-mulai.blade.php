                    {{-- ====== 01 PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Apa itu Mobile JKN</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            <strong>Mobile JKN</strong> adalah aplikasi BPJS Kesehatan di HP peserta. Lewat aplikasi itu
                            pasien bisa <strong>ambil antrean poli</strong> rumah sakit dari rumah, lalu
                            <strong>checkin</strong> saat tiba di hari periksa — tanpa antre di loket pendaftaran.
                        </p>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Aplikasi pasien tidak memanggil SIRUS langsung. Server BPJS yang memanggil
                            <strong>URL API milik RS</strong> (sudah didaftarkan RS ke BPJS). API itu sekarang
                            menjadi bagian SIRUS — dulu project terpisah <span class="ds-code">api-rsimadinah-laravel</span>.
                        </p>

                        <div class="grid grid-cols-1 gap-4 mb-8 md:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--primary)">RS sebagai server</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    <li>• BPJS memanggil endpoint RS: ambil antrean, checkin, batal, status, sisa antrean, jadwal operasi</li>
                                    <li>• RS menjawab dengan data kuota, nomor antrean, kode booking</li>
                                    <li>• Kontrak (nama field, kode jawaban) ditentukan BPJS — <strong>tidak boleh diubah sepihak</strong></li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--muted-soft)">RS sebagai client</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    <li>• Setelah checkin, RS melapor balik ke server Antrean RS BPJS (<span class="ds-code">antrean/add</span>)</li>
                                    <li>• Waktu tiap tahap layanan dilaporkan sebagai <strong>taskId 1–7</strong></li>
                                    <li>• Data ini yang dipakai BPJS untuk menilai waktu tunggu RS</li>
                                </ul>
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:20px">
                            <div class="ds-caption-up mb-2" style="color:var(--muted-soft)">Kebijakan RS yang tertanam di kode</div>
                            <ul class="ds-body-sm space-y-1.5">
                                <li>• <strong>Pasien baru tidak bisa booking</strong> — nomor kartu harus sudah ada di Master Pasien; pasien baru daftar offline dulu</li>
                                <li>• <strong>Booking minimal H+1</strong>, paling jauh 35 hari ke depan (H+0 bisa dibuka lewat <span class="ds-code">ANTRIAN_DISALLOW_SAMEDAY=false</span>)</li>
                                <li>• Kuota dibagi bersama pendaftaran loket — booking JKN yang belum datang tetap memakan kuota</li>
                            </ul>
                        </div>
                    </section>

                    {{-- ====== 02 ARSITEKTUR & HOST ====== --}}
                    <section x-show="section === 'arsitektur'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Arsitektur &amp; Host</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Satu aplikasi Laravel melayani dua <strong>host</strong> berbeda. Laravel memilah
                            berdasarkan nama host yang diminta, jadi path API tetap di root
                            (<span class="ds-code">/auth</span>, <span class="ds-code">/ambilantrean</span>) persis
                            seperti URL yang terdaftar di BPJS.
                        </p>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">dua host, satu aplikasi</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['arsitektur'] }}</pre>
                        </div>

                        @foreach ([
                            ['H1', 'API hanya aktif di host MOBILE_JKN_HOST.',
                             'Route di routes/mobile-jkn.php dimuat dari bootstrap/app.php dengan Route::domain(). Env kosong = route API tidak dimuat sama sekali — aman untuk instalasi SIRUS yang tidak melayani Mobile JKN.'],
                            ['H2', 'Halaman SIRUS tertutup di host API.',
                             'Host API terbuka ke internet karena dipanggil BPJS. Middleware BatasiHostMobileJkn menjawab 404 untuk semua halaman grup web (login, EMR, Livewire) bila diminta lewat host API.'],
                            ['H3', 'API tidak memakai sesi login SIRUS.',
                             'Grup api: tanpa cookie, tanpa CSRF. Pengaman diganti token JWT di header (lihat Autentikasi) plus batas 60 request per menit per IP.'],
                            ['H4', 'Database sama dengan SIRUS.',
                             'Booking, pendaftaran RJ, dan log ditulis ke Oracle yang sama, jadi petugas langsung melihat booking di menu Booking Rawat Jalan.'],
                            ['H5', 'Kode API sengaja dipisah dari trait SIRUS.',
                             'Trait di App\Http\Traits\MobileJkn adalah salinan apa adanya dari project lama, supaya perilaku API tidak berubah saat dipindah. Menyatukannya dengan Traits\BPJS\AntrianTrait milik SIRUS = pekerjaan terpisah, wajib uji ulang.'],
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

                        <div class="ds-card-outline mt-6" style="padding:20px">
                            <div class="ds-caption-up mb-2" style="color:var(--primary)">Kenapa ada BatasiHostMobileJkn</div>
                            <div class="ds-body-sm mb-3">
                                Apache melayani dev-sirus.rsimadinah.com dan apijkn.rsimadinah.com dengan folder yang sama.
                                Tanpa pengaman, Laravel menyajikan <strong>semua</strong> halaman SIRUS di host mana pun —
                                termasuk apijkn.rsimadinah.com/login. Host API wajib terbuka ke internet (dipanggil BPJS),
                                jadi login, EMR, dan data pasien ikut terbuka lewat pintu itu: sasaran tebak-password dan
                                celah keamanan PHP/Laravel.
                            </div>
                            <div class="ds-body-sm mb-3">
                                Middleware ini dipasang paling depan di grup <span class="ds-code">web</span> (semua halaman SIRUS)
                                dengan satu aturan: host = <span class="ds-code">MOBILE_JKN_HOST</span> → 404. Endpoint API memakai
                                grup <span class="ds-code">api</span>, jadi tidak melewatinya. Arah sebaliknya (API tak muncul di host
                                SIRUS) sudah dijaga <span class="ds-code">Route::domain()</span>. Env kosong = middleware diam.
                            </div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Alamat</th>
                                            <th>Hasil</th>
                                            <th>Dijaga oleh</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['apijkn…/ambilantrean, /auth, …', 'API jalan', '—'],
                                            ['apijkn…/ , /login, /livewire/update', '404', 'BatasiHostMobileJkn'],
                                            ['dev-sirus…/login', 'SIRUS normal', '—'],
                                            ['dev-sirus…/auth, /ambilantrean', '404', 'Route::domain()'],
                                        ] as [$alamat, $hasil, $penjaga])
                                            <tr>
                                                <td><span class="ds-code">{{ $alamat }}</span></td>
                                                <td>{{ $hasil }}</td>
                                                <td>{{ $penjaga }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="ds-body-sm mt-3" style="color:var(--muted)">
                                Di project lama hal ini tak pernah jadi masalah karena host API hanya berisi aplikasi API.
                                Jangan hapus middleware ini selama API dan SIRUS satu aplikasi.
                            </div>
                        </div>
                    </section>

                    {{-- ====== 03 ALUR PASIEN ====== --}}
                    <section x-show="section === 'alur-pasien'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Alur Pasien</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Dari sisi pasien hanya ada dua tindakan di HP: <strong>ambil antrean</strong> lalu
                            <strong>checkin</strong>. Setelah checkin, pasien masuk alur rawat jalan biasa di SIRUS.
                        </p>

                        <div class="mb-3 ds-caption-up">Sebelum hari periksa (H-35 s/d H-1)</div>
                        <div class="mb-6">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'HP pasien', 'chipWarna' => 'sky', 'judul' => 'Pilih poli, dokter, tanggal', 'sub' => 'di aplikasi Mobile JKN'],
                                ['chip' => '/ambilantrean', 'judul' => 'Cek pasien & kuota', 'sub' => 'rsmst_pasiens, scview_scpolis'],
                                ['chip' => 'status Belum', 'chipWarna' => 'amber', 'judul' => 'Booking tersimpan', 'sub' => 'referensi_mobilejkn_bpjs'],
                                ['chip' => null, 'judul' => 'Pasien dapat nomor', 'sub' => 'kode booking + nomor antrean + estimasi'],
                            ]])
                        </div>

                        <div class="mb-3 ds-caption-up">Hari periksa</div>
                        <div class="mb-6">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => '/checkinantrean', 'judul' => 'Checkin', 'sub' => 'di HP atau di loket (menu Booking RJ)'],
                                ['chip' => 'status Checkin', 'chipWarna' => 'green', 'judul' => 'Jadi pendaftaran RJ', 'sub' => 'baris rstxn_rjhdrs, klaim BPJS'],
                                ['chip' => 'taskId 3', 'judul' => 'Lapor ke BPJS', 'sub' => 'antrean/add + updatewaktu'],
                                ['chip' => 'taskId 4–7', 'judul' => 'Poli → apotek', 'sub' => 'dicatat modul SIRUS biasa'],
                            ]])
                        </div>

                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Status booking</th>
                                            <th>Arti</th>
                                            <th>Bisa berubah ke</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['Belum', 'Booking sudah dibuat, pasien belum checkin. Memakan kuota.', 'Checkin, Batal'],
                                            ['Checkin', 'Pasien sudah checkin; pendaftaran RJ sudah terbentuk.', '— (final)'],
                                            ['Batal', 'Dibatalkan pasien (via HP) atau petugas (menu Booking RJ). Kuota kembali.', '— (final)'],
                                        ] as [$status, $arti, $berikutnya])
                                            <tr>
                                                <td><span class="ds-code">{{ $status }}</span></td>
                                                <td>{{ $arti }}</td>
                                                <td>{{ $berikutnya }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <p class="ds-body-sm" style="max-width:62ch; color:var(--muted)">
                            taskId 1–7 dan 99 dijelaskan lengkap di skill <span class="ds-code">bpjs-antrean-task-id</span>
                            dan di Tutorial Alur Pelayanan seksi Rawat Jalan.
                        </p>
                    </section>

                    {{-- ====== 04 PETA FILE ====== --}}
                    <section x-show="section === 'peta-file'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Peta File</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Semua berkas yang membentuk API Mobile JKN di SIRUS, dan urutan yang dilewati satu
                            request dari BPJS.
                        </p>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
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
                                            ['.env → MOBILE_JKN_HOST', 'Nama host API. Kosong = API mati.', 'host API pindah / baru diaktifkan'],
                                            ['config/services.php → mobile_jkn.host', 'Membaca MOBILE_JKN_HOST supaya aman dengan config:cache.', 'hampir tidak pernah'],
                                            ['bootstrap/app.php', 'Memuat routes/mobile-jkn.php khusus host API (bagian then), membatasi 60 request/menit, memasang BatasiHostMobileJkn di grup web, dan bentuk JSON error API (withExceptions).', 'batas request diubah, bentuk error diubah'],
                                            ['routes/mobile-jkn.php', 'Daftar endpoint: path di root host API → method controller.', 'BPJS menambah endpoint baru'],
                                            ['app/Http/Controllers/Api/MobileJkn/AntrolBPJSController.php', 'Seluruh logika saat BPJS memanggil kita: autentikasi, validasi, kuota, booking, checkin, batal, status, operasi.', 'aturan bisnis berubah (kuota, H+1, jam checkin) — aturan checkin ikut ubah di booking-rj'],
                                            ['resources/views/pages/transaksi/rj/booking-rj/⚡booking-rj.blade.php', 'BUKAN bagian API: layar petugas Booking RJ. Punya logika checkin loket sendiri (prosesCheckin) yang meniru checkinantrean().', 'aturan checkin berubah — samakan dengan controller'],
                                            ['app/Http/Traits/MobileJkn/TraitJWTRsiMadinah.php', 'Membuat & memeriksa token, bentuk jawaban {response, metadata}, menulis log api_log_status.', 'masa berlaku token / format jawaban'],
                                            ['app/Http/Traits/MobileJkn/AntrianTrait.php', 'Panggilan KELUAR ke Antrean RS BPJS (antrean/add, updatewaktu), tanda tangan HMAC, dekripsi jawaban.', 'BPJS mengubah endpoint / enkripsi'],
                                            ['app/Http/Traits/MobileJkn/EmrRJTrait.php', 'Baca & kunci baris rstxn_rjhdrs, tulis datadaftarpolirj_json saat checkin.', 'struktur JSON pendaftaran RJ berubah'],
                                            ['app/Http/Middleware/BatasiHostMobileJkn.php', 'Halaman SIRUS → 404 bila dibuka lewat host API.', 'hampir tidak pernah'],
                                            ['docs/mobile-jkn-api.md', 'Referensi teknis (versi teks halaman ini).', 'ikut setiap perubahan di atas'],
                                        ] as [$berkas, $peran, $ubah])
                                            <tr>
                                                <td><span class="ds-code">{{ $berkas }}</span></td>
                                                <td>{{ $peran }}</td>
                                                <td>{{ $ubah }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:20px">
                            <div class="ds-caption-up mb-2" style="color:var(--primary)">Dua AntrianTrait — jangan tertukar</div>
                            <ul class="ds-body-sm space-y-1.5">
                                <li>• <span class="ds-code">Traits\MobileJkn\AntrianTrait</span> — dipakai API Mobile JKN (salinan project lama)</li>
                                <li>• <span class="ds-code">Traits\BPJS\AntrianTrait</span> — dipakai layar SIRUS (Booking RJ, Daftar RJ, taskId 4–7)</li>
                                <li>• Keduanya memakai kredensial <span class="ds-code">ANTRIAN_*</span> yang sama dari .env. Perbaikan bug di satu trait belum tentu berlaku di trait lainnya</li>
                            </ul>
                        </div>
                    </section>
