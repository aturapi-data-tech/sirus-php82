                    {{-- ====== 08 PETA app/ — TRAIT vs SUPPORT ====== --}}
                    <section x-show="section === 'peta-app'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — app/ &amp; Routing</div>
                        <h1 class="ds-display-md mb-4">Peta <span class="ds-code">app/</span> — Trait vs Support</h1>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">app/</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['peta-app'] }}</pre>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Batas Trait vs Support — pertanyaan yang paling sering salah dijawab</h2>
                        <div class="ds-card-outline mb-6" style="padding:20px; border-color:var(--primary)">
                            <p class="ds-body-md" style="margin:0">
                                Butuh <span class="ds-code">$this</span> / <span class="ds-code">dispatch()</span> /
                                properti komponen → <strong>Trait</strong>.<br>
                                Murni input → output → <strong>Support</strong>.<br>
                                <span class="ds-body-sm" style="color:var(--body)">
                                    Kalau sebuah trait tidak pernah menyentuh <span class="ds-code">$this</span>,
                                    ia salah tempat.
                                </span>
                            </p>
                        </div>
                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['trait-support'] }}</pre>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Grup di <span class="ds-code">Traits/</span></h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Daftarnya <strong>terbuka</strong> — grup baru boleh lahir untuk area domain baru.
                            Yang menentukan penghuni <span class="ds-code">Traits/</span> bukan nama grupnya,
                            melainkan uji <span class="ds-code">$this</span> di atas. Nama grup ditulis PascalCase
                            atau akronim huruf besar utuh (<span class="ds-code">IDRG</span>, bukan
                            <span class="ds-code">iDRG</span>).
                        </p>

                        <h2 class="ds-title-lg mt-10 mb-4">Sub-namespace di <span class="ds-code">Support/</span></h2>
                        <div class="ds-card-outline mb-6" style="padding:20px; border-color:var(--primary)">
                            <p class="ds-body-sm" style="margin:0">
                                <strong>Aturan pembentukan: sub-namespace dibuat HANYA bila anggotanya ≥ 2.</strong>
                                Folder berisi satu berkas menambah kedalaman tanpa memberi informasi — biarkan ia
                                di akar <span class="ds-code">App\Support</span>. Karena itu yang dibentuk cuma
                                4 kelompok + <span class="ds-code">Downtime/</span> yang sudah ada, bukan satu
                                folder per domain.
                            </p>
                        </div>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Sub-namespace</th><th>Isi</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-class">Clause/ (6)</td><td>teks legal berversi</td></tr>
                                    <tr><td class="ds-td-class">Options/ (9)</td><td>daftar opsi &amp; skala formulir EMR</td></tr>
                                    <tr><td class="ds-td-class">Terminologi/ (8)</td><td>pemetaan kode standar SNOMED/KFA/LOINC/ICD/FHIR</td></tr>
                                    <tr><td class="ds-td-class">GajiDokter/ (2)</td><td>modul slip gaji dokter</td></tr>
                                    <tr><td class="ds-td-class">Downtime/ (2)</td><td>formulir &amp; tarif waktu henti</td></tr>
                                    <tr><td class="ds-td-class">(akar) (11)</td><td>pembantu tunggal per domain — nama sudah menjelaskan dirinya</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Jebakan: resolusi satu-namespace</h2>
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['jebakan-satu-ns'] }}</pre>
                        </div>
                    </section>

                    {{-- ====== 09 ROUTING & URL ====== --}}
                    <section x-show="section === 'routing'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — app/ &amp; Routing</div>
                        <h1 class="ds-display-md mb-4">Routing &amp; URL</h1>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">routes/web.php</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['route-pola'] }}</pre>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Prefix resmi</h2>
                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Area view</th><th>Prefix URL</th><th>Prefix nama route</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-class">pages/master/</td><td class="ds-td-class">/master/</td><td class="ds-td-class">master.</td></tr>
                                    <tr><td class="ds-td-class">pages/transaksi/&lt;jalur&gt;/</td><td class="ds-td-class">/rj/ /ugd/ /ri/ /ri-resep/</td><td class="ds-td-class">&lt;jalur&gt;.</td></tr>
                                    <tr><td class="ds-td-class">pages/transaksi/&lt;fungsi&gt;/</td><td class="ds-td-class">/keuangan/ /gudang/ /apotek/ /kasir/ /casemix/ /penunjang/</td><td class="ds-td-class">&lt;fungsi&gt;.</td></tr>
                                    <tr><td class="ds-td-class">pages/manajemen/</td><td class="ds-td-class">/manajemen/</td><td class="ds-td-class">manajemen.</td></tr>
                                    <tr><td class="ds-td-class">pages/database-monitor/</td><td class="ds-td-class">/database-monitor/</td><td class="ds-td-class">database-monitor.</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline" style="padding:20px; border-color:var(--primary)">
                            <div class="ds-caption-up mb-2" style="color:var(--primary)">Kalau mengubah URL yang sudah live</div>
                            <p class="ds-body-sm" style="margin:0">
                                Petugas menyimpan URL lama di bookmark &amp; pintasan browser. Sertakan
                                <span class="ds-code">Route::redirect()</span> — dan pakai <strong>302</strong>
                                (default), <strong>bukan 301</strong>. 301 di-cache permanen oleh browser, jadi
                                kalau nanti ada penyesuaian lagi, pengguna yang pernah membuka URL lama sulit
                                dilepas dari cache-nya.
                            </p>
                        </div>
                    </section>
