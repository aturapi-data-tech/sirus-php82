                    {{-- ====== 07 — OPERASIONAL ====== --}}
                    <section x-show="section === 'setup'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Operasional</div>
                        <h1 class="ds-display-md mb-4">Setup &amp; Deploy</h1>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">.env dan akun</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['env'] }}</pre>
                        </div>
                        @foreach ([
                            ['S1', 'Urutan di server SIRUS.',
                             'Isi SIAP_API_JWT_SECRET (dan MOBILE_JKN_JWT_SECRET), php artisan config:clear, lalu php artisan siap-api:akun siap-gaji. Salin password yang dicetak — tidak bisa dibaca ulang, hanya disetel ulang.'],
                            ['S2', 'Urutan di server SIAP.',
                             'Isi SIAP_API_URL (host SIRUS biasa, atau SIAP_API_HOST bila diisi — dengan https://), SIAP_API_USER=siap-gaji, SIAP_API_PASSWORD, php artisan config:clear, lalu Panduan → Bridging SIRUS → Uji sambungan.'],
                            ['S3', 'Whitelist IP setelah sambungan terbukti.',
                             'Isi SIAP_API_IP_WHITELIST dengan IP server SIAP (yang terlihat SIRUS — cek lewat log Apache bila di balik proxy), config:clear, uji lagi.'],
                            ['S4', 'Rotasi rahasia / password.',
                             'Rahasia: ganti SIAP_API_JWT_SECRET → SIAP minta token baru sendiri. Password: jalankan siap-api:akun lagi, perbarui .env SIAP; sampai diperbarui, Ambil di SIAP gagal 401 (snapshot lama aman).'],
                            ['S5', 'Peralihan Mobile JKN ke rahasia .env.',
                             'MOBILE_JKN_JWT_SECRET kosong = kunci lama di kode masih dipakai. Setelah diisi, token JKN lama ditolak — BPJS cukup /auth lagi (token JKN berumur 12 jam, jadi paling lama 12 jam ada token lama beredar).'],
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

                    {{-- ====== 08 — OPERASIONAL ====== --}}
                    <section x-show="section === 'uji'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Operasional</div>
                        <h1 class="ds-display-md mb-4">Uji Sambungan</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Semua endpoint baca saja — aman dijalankan ke SIRUS produksi. Dari sisi SIAP cukup tombol <strong>Uji sambungan</strong> di Panduan → Bridging SIRUS; di bawah ini versi manual dari terminal.
                        </p>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">curl</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.6">{{ $snippet['uji'] }}</pre>
                        </div>
                        <div class="grid grid-cols-1 gap-4 mb-8 md:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--primary)">Hasil yang benar</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    <li>• token: 3 bagian dipisah titik, expires_in 3600</li>
                                    <li>• dokter: <span class="ds-code">{"dokter":[…]}</span> — jumlah = baris rsmst_doctors</li>
                                    <li>• transaksi: <span class="ds-code">jumlah_dokter</span> = dokter yang punya transaksi jasa pada bulan itu (0 bila belum ada)</li>
                                    <li>• tanpa token 401; akun web biasa 401; bulan 13 → 422</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--muted-soft)">Jangan</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    <li>• Menguji dengan akun Admin — ditolak, dan itu memang sengaja</li>
                                    <li>• Membagikan password akun API lewat chat — setel ulang bila terlanjur</li>
                                    <li>• Menjalankan siap-api:akun di laptop yang .env-nya ke DB produksi tanpa sadar — ia mengubah password akun produksi</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 09 — OPERASIONAL ====== --}}
                    <section x-show="section === 'masalah'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Operasional</div>
                        <h1 class="ds-display-md mb-4">Masalah &amp; Penanganan</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Pesan di kolom kiri adalah teks yang tampil di SIAP (toast saat Ambil / Uji sambungan) — isi <span class="ds-code">error</span> dari JSON SIRUS diteruskan apa adanya.
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
                                    <li>• Bentuk JSON adalah kontrak dengan <span class="ds-code">App\Support\GajiDokterSirus</span> di SIAP — menambah field aman, mengubah nama / arti field harus bersamaan di dua repo</li>
                                    <li>• Baris transaksi memakai <span class="ds-code">GajiDokterLampiran::barisMassal</span> yang sama dengan Monitoring Jasa Dokter — ubah di satu tempat, keduanya ikut</li>
                                    <li>• Uji regresi minimal: token akun API 200, akun biasa 401, akun nonaktif 401, /dokter 200, periode 2026/07 jumlah &gt; 0, bulan 13 → 422</li>
                                    <li>• Referensi teknis lengkap: <span class="ds-code">docs/siap-api.md</span>; sisi client: SIAP → Panduan → Bridging SIRUS</li>
                            </ul>
                        </div>
                    </section>

