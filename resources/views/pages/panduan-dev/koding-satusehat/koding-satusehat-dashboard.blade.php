                    {{-- ====== 09 DASHBOARD ====== --}}
                    <section x-show="section === 'dashboard'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Peta Dashboard SATUSEHAT → Status</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Kolom di dashboard platform SATUSEHAT (jumlah resource per bulan) vs kondisi
                            implementasi di sistem ini. Pakai peta ini untuk tahu apa yang tinggal
                            di-wire dan apa yang harus dibuat dari nol.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Kolom Dashboard (Resource)</th><th>Trait ada?</th><th>Ter-wire di UI RJ?</th><th>Sistem kode</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Kunjungan (Encounter)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol</td><td class="ds-body-sm">class AMB</td></tr>
                                    <tr><td class="ds-td-strong">Diagnosis (Condition)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol</td><td class="ds-body-sm">ICD-10 (+SNOMED keluhan)</td></tr>
                                    <tr><td class="ds-td-strong">Observasi (Observation)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol</td><td class="ds-body-sm">LOINC</td></tr>
                                    <tr><td class="ds-td-strong">Tindakan (Procedure)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol</td><td class="ds-body-sm">ICD-9-CM</td></tr>
                                    <tr><td class="ds-td-strong">Peresepan Obat (MedicationRequest)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol</td><td class="ds-body-sm">KFA</td></tr>
                                    <tr><td class="ds-td-strong">Obat Dibawa Pulang (MedicationDispense)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol (kartu 8)</td><td class="ds-body-sm">KFA</td></tr>
                                    <tr><td class="ds-td-strong">Layanan Penunjang (ServiceRequest)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol (kartu 9 Lab)</td><td class="ds-body-sm">LOINC</td></tr>
                                    <tr><td class="ds-td-strong">Laboratorium (Specimen)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol (kartu 9 Lab)</td><td class="ds-body-sm">SNOMED</td></tr>
                                    <tr><td class="ds-td-strong">Pelaporan Diagnostik (DiagnosticReport)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol (Lab kartu 9 + Radiologi kartu 10)</td><td class="ds-body-sm">LOINC</td></tr>
                                    <tr><td class="ds-td-strong">Intoleransi Alergi (AllergyIntolerance)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol (kartu 7)</td><td class="ds-body-sm">SNOMED</td></tr>
                                    <tr><td class="ds-td-strong">Impresi Klinik (ClinicalImpression)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol (kartu 11)</td><td class="ds-body-sm">—</td></tr>
                                    <tr><td class="ds-td-strong">Diet (Composition)</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">—</td></tr>
                                    <tr><td class="ds-td-strong">Radiologi (ImagingStudy)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm">⚠️ trait siap + lolos staging; belum di-wire ke UI — <button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('pacs')">lihat §PACS</button></td><td class="ds-body-sm">LOINC + DICOM UID</td></tr>
                                    <tr><td class="ds-td-strong">Imunisasi (Immunization)</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">—</td></tr>
                                    <tr><td class="ds-td-strong">Episode Perawatan (EpisodeOfCare)</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">—</td></tr>
                                    <tr><td class="ds-td-strong">Instruksi Gizi (NutritionOrder)</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">—</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-display-sm mb-1" style="color:var(--primary)">11</div>
                                <div class="ds-body-sm">kartu <strong>ter-wire tombol Kirim</strong> di RJ (Encounter, Condition, Observation, Procedure, MedicationRequest, Chief Complaint, Allergy, Medication Dispense, Lab, Radiologi, Clinical Impression). <strong>UGD = 9</strong> (tanpa Chief Complaint &amp; Allergy).</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-display-sm mb-1">4</div>
                                <div class="ds-body-sm"><strong>belum dibuat</strong> (Composition/Diet, Immunization, EpisodeOfCare, NutritionOrder)</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-display-sm mb-1">1</div>
                                <div class="ds-body-sm"><strong>trait siap, belum di-wire</strong>: ImagingStudy — <button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('pacs')">PACS Orthanc &amp; ImagingStudy</button></div>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 10 RESOURCE BELUM ADA ====== --}}
                    <section x-show="section === 'belum-ada'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Resource Belum Ada — Cara &amp; Metode Kirim</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Enam kolom dashboard ini <strong>belum punya trait sama sekali</strong>.
                            Bab ini merangkum <em>cara kirim</em> (endpoint + payload FHIR R4) dan
                            <em>metode</em> (<span class="ds-code">createX()</span> mengikuti idiom repo:
                            <span class="ds-code">resourceType</span> → <span class="ds-code">subject/encounter</span>
                            reference → <span class="ds-code">makeRequest('post', '/X', $payload)</span>).
                            Semua di bawah = <strong>cetak-biru, belum diuji sandbox</strong>.
                        </p>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Resource</th><th>Endpoint</th><th>Kode system</th><th>Kesiapan data SIRUS</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">EpisodeOfCare</td><td class="ds-td-class">POST /EpisodeOfCare</td><td class="ds-body-sm">episodeofcare-type</td><td class="ds-body-sm" style="color:var(--primary)">✅ ada — rstxn_rihdrs (RI)</td></tr>
                                    <tr><td class="ds-td-strong">ClinicalImpression</td><td class="ds-td-class">POST /ClinicalImpression</td><td class="ds-body-sm">SNOMED (finding)</td><td class="ds-body-sm" style="color:var(--primary)">✅ ada — asesmen "A" EMR</td></tr>
                                    <tr><td class="ds-td-strong">NutritionOrder</td><td class="ds-td-class">POST /NutritionOrder</td><td class="ds-body-sm">SNOMED (diet)</td><td class="ds-body-sm">◑ sebagian — order diet EMR (role Gizi)</td></tr>
                                    <tr><td class="ds-td-strong">Composition</td><td class="ds-td-class">POST /Composition</td><td class="ds-body-sm">LOINC (doc type)</td><td class="ds-body-sm">◑ sebagian — narasi EMR jadi section</td></tr>
                                    <tr><td class="ds-td-strong">ImagingStudy</td><td class="ds-td-class">POST /ImagingStudy</td><td class="ds-body-sm">LOINC + DICOM UID</td><td class="ds-body-sm" style="color:var(--primary)">✅ trait siap + Orthanc — <button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('pacs')">§PACS</button></td></tr>
                                    <tr><td class="ds-td-strong">QuestionnaireResponse</td><td class="ds-td-class">POST /QuestionnaireResponse</td><td class="ds-body-sm">Q0007 + clinical-term</td><td class="ds-body-sm" style="color:var(--primary)">✅ ada — telaahResep RJ &amp; UGD (15 butir), pengirim di kedua jalur</td></tr>
                                    <tr><td class="ds-td-strong">Immunization</td><td class="ds-td-class">POST /Immunization</td><td class="ds-body-sm">KFA (vaksin)</td><td class="ds-body-sm">⚠️ gap — belum ada modul imunisasi</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mb-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Prasyarat sama utk semuanya:</strong> Encounter pasien harus sudah
                                terkirim (semua resource ini mereferensikan <span class="ds-code">Encounter/{id}</span>
                                &amp; <span class="ds-code">Patient/{id}</span>). Urutan implementasi disarankan
                                dari yang <strong>datanya sudah ada</strong> (EpisodeOfCare, ClinicalImpression)
                                ke yang butuh modul baru (Immunization). ImagingStudy sudah ada trait — lihat <button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('pacs')">§PACS</button>.
                            </span>
                        </div>

                        {{-- Grup 1: data sudah ada --}}
                        <div class="ds-caption-up mb-3">Data sudah ada — tinggal buat trait &amp; wire</div>

                        <div class="ds-title-md mb-1">EpisodeOfCare — Episode Perawatan</div>
                        <p class="ds-body-sm mb-3" style="max-width:62ch; color:var(--muted)">
                            Mengelompokkan seluruh kunjungan satu rawat inap jadi satu episode. Sumber:
                            <span class="ds-code">rstxn_rihdrs</span> (mulai = tgl masuk, selesai = tgl pulang, DPJP = careManager).
                        </p>
                        <div class="ds-card-dark mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">EpisodeOfCareTrait::createEpisodeOfCare()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ss-episode'] }}</pre>
                        </div>

                        <div class="ds-title-md mb-1">ClinicalImpression — Impresi Klinik</div>
                        <p class="ds-body-sm mb-3" style="max-width:62ch; color:var(--muted)">
                            Asesmen dokter (huruf "A" di SOAP). Sumber: section Penilaian/Assessment EMR;
                            <span class="ds-code">finding</span> bisa dipetakan ke SNOMED bila tersedia.
                        </p>
                        <div class="ds-card-dark mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">ClinicalImpressionTrait::createClinicalImpression()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ss-clinical'] }}</pre>
                        </div>

                        {{-- Grup 2: data sebagian --}}
                        <div class="ds-caption-up mb-3">Data sebagian — perlu pemetaan kode</div>

                        <div class="ds-title-md mb-1">NutritionOrder — Instruksi Gizi</div>
                        <p class="ds-body-sm mb-3" style="max-width:62ch; color:var(--muted)">
                            Order diet dari EMR (role Gizi punya akses Daftar RI/EMR). PR: petakan teks diet
                            ke kode SNOMED diet (<span class="ds-code">oralDiet.type</span>).
                        </p>
                        <div class="ds-card-dark mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">NutritionOrderTrait::createNutritionOrder()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ss-nutrition'] }}</pre>
                        </div>

                        <div class="ds-title-md mb-1">Composition — Dokumen Klinis (label dashboard "Diet")</div>
                        <p class="ds-body-sm mb-3" style="max-width:62ch; color:var(--muted)">
                            Dokumen terstruktur ber-section (mis. ringkasan/rencana). PR: tentukan
                            <span class="ds-code">type</span> LOINC dokumen &amp; bungkus narasi jadi
                            <span class="ds-code">section[].text.div</span> (XHTML valid).
                        </p>
                        <div class="ds-card-dark mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">CompositionTrait::createComposition()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ss-composition'] }}</pre>
                        </div>

                        {{-- Grup 3: gap data --}}
                        <div class="ds-caption-up mb-3">Gap data — butuh modul / field baru dulu</div>

                        <div class="ds-title-md mb-1">ImagingStudy — Radiologi</div>
                        <div class="ds-card-outline mb-8" style="padding:16px 20px; border-color:var(--success)">
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Sudah ada trait + Orthanc terpasang.</strong>
                                <span class="ds-code">ImagingStudyTrait</span> lolos uji staging,
                                <span class="ds-code">OrthancTrait</span> menyambungkan SIRUS ke PACS.
                                Tinggal wire ke UI kirim radiologi.
                                <br>Detail lengkap &rarr; <button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('pacs')">PACS Orthanc &amp; ImagingStudy</button>
                            </span>
                        </div>

                        <div class="ds-title-md mb-1">Immunization — Imunisasi</div>
                        <p class="ds-body-sm mb-3" style="max-width:62ch; color:var(--muted)">
                            Belum ada modul imunisasi. Perlu form capture (jenis vaksin ber-KFA, lot, rute,
                            dosis, petugas) sebelum resource ini bisa dikirim.
                        </p>
                        <div class="ds-card-dark mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">ImmunizationTrait::createImmunization()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ss-immunization'] }}</pre>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Langkah adopsi (tiap resource):</strong> (1) buat
                                <span class="ds-code">App\Http\Traits\SATUSEHAT\&lt;Resource&gt;Trait</span> berisi
                                <span class="ds-code">createX()</span> di atas; (2) buat komponen
                                <span class="ds-code">kirim-&lt;resource&gt;-rj-actions</span> meniru
                                <button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                    x-on:click="go('tambah')">bab Menambah Resource</button>; (3) gate
                                <span class="ds-code">:disabled="!$hasEncounter"</span>; (4) simpan id ke node JSON
                                <span class="ds-code">satusehat</span>; (5) uji sandbox, verifikasi
                                <span class="ds-code">web_log_status</span>.
                            </span>
                        </div>
                    </section>