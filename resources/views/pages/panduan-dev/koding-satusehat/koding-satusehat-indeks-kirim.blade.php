                    {{-- ====== INDEKS KIRIMAN PENUNJANG PER-ORDER ====== --}}
                    <section x-show="section === 'indeks-kirim'" x-cloak>
                        <div class="ds-eyebrow mb-3">Pengiriman — Penunjang</div>
                        <h1 class="ds-display-md mb-4">Indeks Per-Order (<span class="ds-code">radKirim</span> / <span class="ds-code">labKirim</span>)</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Yang menentukan sebuah order penunjang sudah lengkap terkirim atau belum
                            <strong>bukan</strong> array datar <span class="ds-code">radServiceRequestIds</span> dan kawan-kawannya,
                            melainkan indeks berkunci per-order. Berlaku untuk enam sender: lab &amp; radiologi &times; RJ/UGD/RI.
                            Kode bersamanya di <span class="ds-code">app/Http/Traits/SATUSEHAT/PenunjangKirimTrait.php</span>.
                        </p>

                        <h2 class="ds-title-lg mb-3">Kenapa array datar tidak cukup</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Array datar itu daftar UUID <strong>tanpa keterangan order mana punya siapa</strong>.
                            Begitu satu order gagal di tengah &mdash; ServiceRequest terbentuk, DiagnosticReport belum &mdash;
                            tak ada cara tahu order mana yang bolong. Guard cuma bisa dua sikap, dua-duanya salah:
                        </p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-8">
                            <div class="ds-card-outline" style="padding:20px; border-color:var(--danger)">
                                <div class="ds-title-sm mb-2" style="color:var(--danger)">Kalau diloloskan</div>
                                <p class="ds-body-sm">
                                    ServiceRequest di-POST ulang dengan identifier yang sama &rarr; ditolak duplikat
                                    (<span class="ds-code">RuleNumber 20002</span>) dan <strong>macet selamanya</strong>.
                                </p>
                            </div>
                            <div class="ds-card-outline" style="padding:20px; border-color:var(--danger)">
                                <div class="ds-title-sm mb-2" style="color:var(--danger)">Kalau ditolak semua</div>
                                <p class="ds-body-sm">
                                    DiagnosticReport <strong>tak pernah tersusul</strong> &mdash; data di SATUSEHAT
                                    tinggal separuh, dan tak ada yang memberitahu.
                                </p>
                            </div>
                        </div>

                        <h2 class="ds-title-lg mb-3">Bentuk node</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Tiap order sudah punya identifier stabil yang dipakai saat POST. Identifier itu jadi kuncinya.
                        </p>
                        <pre class="ds-code" style="padding:20px 24px; overflow-x:auto; line-height:1.7">{{ $snip['indeks-kirim'] }}</pre>

                        <div class="ds-card-outline mt-4 mb-8" style="padding:16px 20px; border-color:var(--warning)">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Array datar wajib dipertahankan.</strong>
                                <span class="ds-code">App\Support\SatuSehatMonitor</span> mencocokkan <strong>string mentah</strong>
                                <span class="ds-code">"radServiceRequestIds":["</span> ke CLOB, dan indikator status di
                                <span class="ds-code">daftar-rj</span>/<span class="ds-code">daftar-ugd</span> serta
                                <span class="ds-code">kirim-resume-medis</span> juga membacanya. Mengubah bentuknya
                                memutus mereka <strong>diam-diam</strong>.
                            </span>
                        </div>

                        <h2 class="ds-title-lg mb-3">Lima aturan</h2>
                        <ol class="ds-body-md mb-8 space-y-3" style="list-style:decimal; padding-left:22px; max-width:70ch">
                            <li>
                                Guard bukan lagi &ldquo;ada isinya &rarr; tolak&rdquo;, melainkan
                                <strong>kumpulkan bagian yang belum ada, kirim itu saja</strong>. Order yang semua bagian
                                wajibnya sudah punya id dilewati tanpa memanggil API sama sekali.
                            </li>
                            <li>
                                <strong>Record lama</strong> belum punya indeks; id-nya dipulihkan sekali lewat pencarian
                                identifier (<span class="ds-code">cariIdLewatIdentifier()</span>), lalu disimpan.
                                <strong>Gagal atau tak ketemu = order dilewati, bukan dikirim ulang</strong> &mdash; array datar
                                sudah membuktikan ia pernah terkirim, jadi POST ulang hanya akan kena 20002 lagi.
                                Sikap ini sama dengan guard lama, jadi record lama tak pernah lebih buruk.
                            </li>
                            <li>
                                <strong>DiagnosticReport punya dua bentuk <span class="ds-code">identifier.system</span></strong>
                                sejak <span class="ds-code">RuleNumber 10432</span>. Pemulihan wajib mencoba yang baru dulu
                                (<span class="ds-code">&hellip;/diagnostic/{org}/rad</span> atau <span class="ds-code">/lab</span>)
                                lalu jatuh ke yang lama (tanpa akhiran). Kalau tidak, DR lama tak ketemu dan dibuatkan
                                <strong>DR kedua</strong> untuk order yang sama &mdash; SATUSEHAT tak menolaknya karena
                                system-nya memang beda.
                            </li>
                            <li>
                                <strong>Observation bukan penanda kelengkapan.</strong> Ia tak punya identifier: tak bisa
                                dipulihkan, dan tak akan ditolak duplikat. Di radiologi, Observation dibuat
                                <strong>di dalam</strong> cabang pembuatan DR &mdash; kalau di luar, laporan yang sudah ada
                                ditinggali observasi yatim tiap tombol kirim ditekan.
                            </li>
                            <li>
                                <strong>Batas di lab:</strong> paket lama yang DR-nya bolong sengaja <strong>dilewati</strong>.
                                Daftar Observation-nya tak bisa dipulihkan, dan mengirim ulang berarti menggandakan hasil lab
                                di SATUSEHAT. Jumlah yang dilewati disebut di toast, tidak hilang diam-diam.
                            </li>
                        </ol>

                        <h2 class="ds-title-lg mb-3">Efek sampingan yang menguntungkan</h2>
                        <div class="ds-card-outline mb-8" style="padding:16px 20px; border-color:var(--success)">
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Foto radiologi yang diupload <strong>sesudah</strong> SATUSEHAT dikirim kini bisa disusulkan
                                <span class="ds-code">ImagingStudy</span>-nya &mdash; order itu jadi &ldquo;belum lengkap&rdquo;
                                di sisi <span class="ds-code">is</span>. Sebelumnya tak pernah bisa, karena guard lama
                                memblokir seluruh kiriman begitu DR-nya ada.
                            </span>
                        </div>

                        <h2 class="ds-title-lg mb-3">Menambah resource baru ke sender penunjang</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Tambahkan bagiannya ke daftar bagian wajib dan ke <span class="ds-code">catatKirim()</span>.
                            <strong>Jangan</strong> membuat array datar baru sebagai penanda &ldquo;sudah dikirim&rdquo; &mdash;
                            itu justru masalah yang bab ini perbaiki. Kalau resource-nya tak punya identifier
                            (seperti Observation), perlakukan seperti aturan 4.
                        </p>

                        <div class="ds-card-outline" style="padding:16px 20px; border-color:var(--warning)">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Belum diuji kirim live.</strong> Yang khususnya belum terbukti: apakah SATUSEHAT
                                menerima <span class="ds-code">?identifier=</span> yang di-<span class="ds-code">rawurlencode</span>
                                penuh (seluruh <span class="ds-code">system|value</span>). Kalau ditolak, efeknya hanya
                                pemulihan record lama gagal &rarr; order dilewati (aman), <strong>bukan</strong> kiriman ganda.
                                <br>Rujukan lengkap &rarr; <span class="ds-code">docs/satusehat-api.md</span> &sect;12.
                            </span>
                        </div>
                    </section>
