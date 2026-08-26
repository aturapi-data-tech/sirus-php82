                    {{-- ====== 04 TRANSPORT ====== --}}
                    <section x-show="section === 'transport'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Pengiriman</div>
                        <h1 class="ds-display-md mb-4">Transport &amp; Logging</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Semua kiriman lewat satu pintu: <span class="ds-code">makeRequest($method, $endpoint, $data)</span>.
                            <strong>Bukan FHIR Bundle</strong> — tiap resource satu HTTP call terpisah.
                            Setiap call di-audit ke tabel <span class="ds-code">web_log_status</span>.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">makeRequest() + logSatuSehat()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['transport'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Sukses vs gagal</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>2xx → <span class="ds-code">$response->json()</span> (array)</li>
                                    <li>Gagal → <span class="ds-code">throw \Exception('API request failed: '.body)</span></li>
                                    <li>Caller (blade) tangkap <span class="ds-code">\Throwable</span> → toast error</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Audit — <span class="ds-code">web_log_status</span></div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>Kolom: <span class="ds-code">code, date_ref, response, http_req, http_payload, requestTransferTime</span></li>
                                    <li>Sumber verifikasi tiap kiriman (payload &amp; balasan server)</li>
                                    <li><span class="ds-code">Http::timeout(10)</span> tanpa <span class="ds-code">connectTimeout/retry</span> → rawan freeze (backlog)</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 05 IHS ====== --}}
                    <section x-show="section === 'ihs'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Pengiriman</div>
                        <h1 class="ds-display-md mb-4">Resolusi IHS Code</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            IHS = identitas resource di SATUSEHAT. Sumbernya kolom master
                            (di-set sekali), <strong>bukan</strong> dilookup tiap kirim.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Entitas</th><th>IHS disimpan di</th><th>Cara isi</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Pasien</td><td class="ds-td-class">rsmst_pasiens.patient_uuid</td><td class="ds-body-sm"><span class="ds-code">searchPatient(['nik'=>…])</span> → kalau kosong <span class="ds-code">createPatient()</span></td></tr>
                                    <tr><td class="ds-td-strong">Dokter</td><td class="ds-td-class">rsmst_doctors.dr_uuid</td><td class="ds-body-sm">manual (<span class="ds-code">searchPractitioner</span> tersedia, tak dipakai runtime)</td></tr>
                                    <tr><td class="ds-td-strong">Poli / Location</td><td class="ds-td-class">rsmst_polis.poli_uuid</td><td class="ds-body-sm">manual (<span class="ds-code">searchLocation/createLocation</span> tersedia)</td></tr>
                                    <tr><td class="ds-td-strong">Organization</td><td class="ds-td-class">env SATUSEHAT_ORGANIZATION_ID</td><td class="ds-body-sm">tetap (<span class="ds-code">100027469</span>)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Resolusi IHS pasien</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ihs'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Kalau <span class="ds-code">dr_uuid</span> / <span class="ds-code">poli_uuid</span>
                                kosong → kirim Encounter berhenti dengan toast error. NIK harus 16 digit;
                                kalau tidak, identifier di-skip <strong>diam-diam</strong>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 06 URUTAN ====== --}}
                    <section x-show="section === 'urutan'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Pengiriman</div>
                        <h1 class="ds-display-md mb-4">Model &amp; Urutan Kirim</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Urutan kanonik (dari orkestrator <span class="ds-code">KirimRawatJalanTrait</span>).
                            Di UI aktif langkah 1–4 + 7 tersedia sebagai tombol; sisanya baru ada di trait.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>#</th><th>Langkah</th><th>Resource FHIR</th><th>Sistem kode</th><th>Gate</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">1</td><td class="ds-body-sm">Kunjungan</td><td class="ds-td-class">Encounter</td><td class="ds-body-sm">class AMB (v3-ActCode)</td><td class="ds-body-sm" style="color:var(--primary)"><strong>ROOT — wajib sukses</strong></td></tr>
                                    <tr><td class="ds-td-strong">2</td><td class="ds-body-sm">Diagnosa</td><td class="ds-td-class">Condition (encounter-diagnosis)</td><td class="ds-body-sm">ICD-10</td><td class="ds-body-sm">fail-soft</td></tr>
                                    <tr><td class="ds-td-strong">3</td><td class="ds-body-sm">Tanda vital</td><td class="ds-td-class">Observation (vital-signs)</td><td class="ds-body-sm">LOINC</td><td class="ds-body-sm">fail-soft</td></tr>
                                    <tr><td class="ds-td-strong">4</td><td class="ds-body-sm">Tindakan</td><td class="ds-td-class">Procedure</td><td class="ds-body-sm">ICD-9-CM</td><td class="ds-body-sm">fail-soft</td></tr>
                                    <tr><td class="ds-td-strong">5</td><td class="ds-body-sm">Keluhan utama</td><td class="ds-td-class">Condition (problem-list-item)</td><td class="ds-body-sm">SNOMED</td><td class="ds-body-sm">fail-soft</td></tr>
                                    <tr><td class="ds-td-strong">6</td><td class="ds-body-sm">Alergi</td><td class="ds-td-class">AllergyIntolerance</td><td class="ds-body-sm">SNOMED</td><td class="ds-body-sm">fail-soft</td></tr>
                                    <tr><td class="ds-td-strong">7</td><td class="ds-body-sm">Peresepan obat</td><td class="ds-td-class">MedicationRequest</td><td class="ds-body-sm">KFA</td><td class="ds-body-sm">fail-soft</td></tr>
                                    <tr><td class="ds-td-strong">8</td><td class="ds-body-sm">Obat dibawa pulang</td><td class="ds-td-class">MedicationDispense</td><td class="ds-body-sm">KFA</td><td class="ds-body-sm">fail-soft</td></tr>
                                    <tr><td class="ds-td-strong">9–11</td><td class="ds-body-sm">Penunjang lab</td><td class="ds-td-class">ServiceRequest → Observation → DiagnosticReport</td><td class="ds-body-sm">LOINC</td><td class="ds-body-sm">fail-soft</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Siklus status Encounter (akar)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['encounter-lifecycle'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Encounter = akar</div>
                                <div class="ds-body-sm">Semua resource lain mereferensikan <span class="ds-code">Encounter/{id}</span>, <span class="ds-code">Patient/{id}</span>, <span class="ds-code">Practitioner/{id}</span>.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Idempotensi rapuh</div>
                                <div class="ds-body-sm">Guard in-memory <span class="ds-code">$ss</span> + node JSON <span class="ds-code">satusehat</span>. Hanya Encounter &amp; ServiceRequest punya natural key di server — sisanya <strong>hati-hati kirim dobel</strong> bila state JSON hilang.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Skip diam-diam</div>
                                <div class="ds-body-sm">Item tanpa kode kunci di-<span class="ds-code">continue</span>: diagnosa tanpa <span class="ds-code">kodeIcdx</span>, tindakan tanpa <span class="ds-code">kodeIcd9</span>, obat tanpa <span class="ds-code">kfaCode</span>. Bisa "berhasil (0 item)".</div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Penyimpanan hasil:</strong> node <span class="ds-code">satusehat</span> di JSON RJ →
                                <span class="ds-code">encounterId</span>, <span class="ds-code">conditionIds[]</span>,
                                <span class="ds-code">observationIds[]</span>, <span class="ds-code">procedureIds[]</span>,
                                <span class="ds-code">medicationRequestIds[]</span>, flag
                                <span class="ds-code">encounterInProgress</span>/<span class="ds-code">encounterFinished</span>.
                                Ditulis via <span class="ds-code">DB::transaction</span> + <span class="ds-code">lockRJRow</span> + <span class="ds-code">updateJsonRJ</span>.
                            </span>
                        </div>
                    </section>