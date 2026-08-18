                    {{-- ====== 01 PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Pengiriman Data ke SATUSEHAT</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            <strong>SATUSEHAT</strong> adalah platform interoperabilitas data kesehatan
                            Kemenkes berbasis <strong>FHIR R4</strong>. Setiap kunjungan pasien yang kita
                            layani harus dikirim ulang ke SATUSEHAT sebagai sekumpulan
                            <em>resource</em> FHIR (Encounter, Condition, Observation, Procedure, dst.).
                            Tutorial ini merangkum <strong>cara sistem mengirim</strong> dan
                            <strong>standarisasi data</strong> tiap resource — berbasis implementasi nyata
                            di repo, bukan teori.
                        </p>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Lapisan trait: <span class="ds-code">app/Http/Traits/SATUSEHAT/*.php</span>
                            (20 file, ±3.200 baris). Lapisan UI aktif:
                            <span class="ds-code">transaksi/rj/satu-sehat/*.blade.php</span> +
                            <span class="ds-code">daftar-rj/satu-sehat-rj-actions.blade.php</span>.
                        </p>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">FHIR R4, per-resource</div>
                                <div class="ds-body-sm">Tiap resource = satu HTTP call terpisah (<span class="ds-code">POST Encounter</span>, <span class="ds-code">POST Condition</span>…), <strong>bukan</strong> FHIR Bundle.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Encounter = akar</div>
                                <div class="ds-body-sm">Semua resource mereferensikan <span class="ds-code">Encounter/{id}</span>. Encounter wajib sukses dulu; kalau gagal semua berhenti.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Kode terstandar</div>
                                <div class="ds-body-sm">ICD-10 (diagnosa), ICD-9-CM (tindakan), LOINC (observasi), SNOMED (keluhan/alergi), KFA (obat).</div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Ruang lingkup aktif = Rawat Jalan (RJ).</strong>
                                UGD &amp; RI <em>belum</em> punya alur kirim SATUSEHAT — pola di bab-bab
                                ini adalah cetak-biru saat kita memperluasnya ke jalur lain.
                            </span>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Dapat tugas mengaktifkan resource?</strong> Langsung ke bab
                                <button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                    x-on:click="go('tambah')">Menambah Resource Baru</button> —
                                bedakan dulu apakah trait-nya sudah ada
                                (<button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                    x-on:click="go('dashboard')">lihat peta dashboard</button>) atau harus dibuat dari nol.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 02 ARSITEKTUR ====== --}}
                    <section x-show="section === 'arsitektur'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Arsitektur &amp; 2 Jalur Kirim</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Satu trait inti (<span class="ds-code">SatuSehatTrait</span>) memegang
                            <em>transport</em> &amp; autentikasi; di atasnya sederet
                            <strong>resource trait</strong> membangun payload FHIR dan menembak endpoint
                            masing-masing.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Lapisan trait</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">SatuSehatTrait  (core/transport)
  · initializeSatuSehat()  · getAccessToken()  · makeRequest()  · logSatuSehat()
        ▲ di-`use` oleh SEMUA resource trait di bawah

Resource traits (bangun payload FHIR + POST/PUT):
  Encounter · Condition · Observation · Procedure · AllergyIntolerance ·
  MedicationRequest · MedicationDispense · ServiceRequest · Specimen ·
  DiagnosticReport · Patient · Practitioner · Organization · Location
  (Loinc / Snomed = lookup terminologi)

UI RJ (Livewire, satu tombol per-resource):
  satu-sehat-rj-actions ──buka modal──▶ kirim-encounter │ kirim-condition │
  kirim-observation │ kirim-procedure │ kirim-medication-request</pre>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Dua jalur kirim yang WAJIB dibedakan</h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px; border-color:var(--primary)">
                                <div class="ds-title-sm mb-2">1 · Jalur UI aktif (produksi)</div>
                                <div class="ds-body-sm">
                                    5 komponen Livewire per-langkah, masing-masing tombol
                                    <strong>Kirim</strong> sendiri. Menyimpan hasil ke node JSON
                                    <span class="ds-code">satusehat</span> pada record RJ.
                                    <strong>Ini yang benar-benar dipakai.</strong>
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">2 · Orkestrator batch (blueprint)</div>
                                <div class="ds-body-sm">
                                    <span class="ds-code">KirimRawatJalanTrait::kirimRawatJalan()</span> —
                                    11 langkah sekali jalan, lengkap (alergi, dispense, lab). Tapi
                                    <strong>belum di-<span class="ds-code">use</span> komponen/route manapun</strong>
                                    — anggap cadangan, bukan jalur produksi.
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 03 AUTENTIKASI ====== --}}
                    <section x-show="section === 'autentikasi'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Autentikasi &amp; Environment</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            OAuth2 <strong>client_credentials</strong> — token di-cache lalu dipasang
                            sebagai <span class="ds-code">Bearer</span> di tiap panggilan FHIR bersama
                            header <span class="ds-code">Organization-Id</span>.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">getAccessToken() + header</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['auth'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Hal</th><th>Nilai / Cara</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Token endpoint</td><td class="ds-body-sm"><span class="ds-code">SATUSEHAT_AUTH_URL . "accesstoken?grant_type=client_credentials"</span> (POST <span class="ds-code">asForm</span>)</td></tr>
                                    <tr><td class="ds-td-strong">Kredensial</td><td class="ds-body-sm">env <span class="ds-code">SATUSEHAT_CLIENT_ID</span>, <strong><span class="ds-code">SATUSEHAT_SECRET_ID</span></strong> (catat: <span class="ds-code">_SECRET_ID</span>, bukan <span class="ds-code">_CLIENT_SECRET</span>)</td></tr>
                                    <tr><td class="ds-td-strong">Cache token</td><td class="ds-body-sm"><span class="ds-code">Cache::remember('satusehat_access_token', 3500, …)</span> — TTL hardcoded ~58 mnt, <span class="ds-code">expires_in</span> diabaikan</td></tr>
                                    <tr><td class="ds-td-strong">Header API</td><td class="ds-body-sm"><span class="ds-code">Authorization: Bearer {token}</span> + <span class="ds-code">Organization-Id: {SATUSEHAT_ORGANIZATION_ID}</span></td></tr>
                                    <tr><td class="ds-td-strong">Base URL FHIR</td><td class="ds-body-sm"><span class="ds-code">SATUSEHAT_BASE_URL</span> → <span class="ds-code">.../fhir-r4/v1/</span> (<strong>PRODUCTION</strong>)</td></tr>
                                    <tr><td class="ds-td-strong">Versi</td><td class="ds-body-sm">FHIR <strong>R4</strong>; profil <span class="ds-code">https://fhir.kemkes.go.id/r4/StructureDefinition/*</span></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Switch environment = ganti nilai env</strong> (tak ada toggle di kode).
                                Sandbox Kemkes umumnya <span class="ds-code">api-satusehat-stg.kemkes.go.id</span>.
                                <br><strong>Bahaya:</strong> semua kredensial dibaca <span class="ds-code">env()</span> langsung
                                tanpa wrapper <span class="ds-code">config/*.php</span> — kalau
                                <span class="ds-code">php artisan config:cache</span> dijalankan di production,
                                <span class="ds-code">env()</span> runtime → <span class="ds-code">null</span> →
                                integrasi mati senyap.
                            </span>
                        </div>
                    </section>