                    {{-- ====== 08 STANDAR ====== --}}
                    <section x-show="section === 'standar'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Pengiriman</div>
                        <h1 class="ds-display-md mb-4">Standarisasi Data per Resource</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Tiap resource punya <span class="ds-code">resourceType</span>/status,
                            sistem kode (system URI), dan sumber data (JSON EMR / master) sendiri.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Resource</th><th>Trait</th><th>Sistem kode</th><th>Sumber data</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Encounter</td><td class="ds-td-class">EncounterTrait</td><td class="ds-body-sm">class AMB (v3-ActCode)</td><td class="ds-body-sm">rjNo, dr_uuid, poli_uuid, rjDate, regName</td></tr>
                                    <tr><td class="ds-td-strong">Condition (diagnosa)</td><td class="ds-td-class">createFinalDiagnosis</td><td class="ds-body-sm"><strong>ICD-10</strong> hl7 sid/icd-10</td><td class="ds-body-sm">diagnpinaList[], kodeIcdx/icdx</td></tr>
                                    <tr><td class="ds-td-strong">Condition (keluhan)</td><td class="ds-td-class">createChiefComplaint</td><td class="ds-body-sm"><strong>SNOMED</strong> snomed.info/sct</td><td class="ds-body-sm">keluhanUtama + keluhanUtamaSnomedCode</td></tr>
                                    <tr><td class="ds-td-strong">Observation (vital)</td><td class="ds-td-class">ObservationTrait</td><td class="ds-body-sm"><strong>LOINC</strong> + UCUM</td><td class="ds-body-sm">tandaVital: sistole/diastole/nadi/suhu/rr</td></tr>
                                    <tr><td class="ds-td-strong">Observation (nyeri)</td><td class="ds-td-class">NyeriKesadaranObservationMap</td><td class="ds-body-sm">NRS <strong>SNOMED 1172399009</strong> · WBS <strong>LOINC 38221-8</strong> · NIPS <strong>LOINC 98012-8</strong></td><td class="ds-body-sm">penilaian.nyeri[] via <span class="ds-code">NyeriOptions::daftarEntri()</span> — VAS/FLACC/BPS/CPOT/PAINAD <strong>belum ada kode resmi</strong>, dilewati &amp; dilaporkan di kartu</td></tr>
                                    <tr><td class="ds-td-strong">Observation (kesadaran)</td><td class="ds-td-class">NyeriKesadaranObservationMap</td><td class="ds-body-sm"><strong>LOINC 67775-7</strong> Level of responsiveness</td><td class="ds-body-sm">screening.kesadaran — RJ 3 pilihan (hanya "Mengantuk / Gelisah" berkode SNOMED 300202002), UGD 5 pilihan dan <strong>belum satu pun berkode</strong>; sisanya dikirim <span class="ds-code">text</span> saja</td></tr>
                                    <tr><td class="ds-td-strong">QuestionnaireResponse</td><td class="ds-td-class">QuestionnaireResponseTrait + TelaahResepQ0007</td><td class="ds-body-sm">Q0007 · clinical-term <span class="ds-code">OV000052</span></td><td class="ds-body-sm">telaahResep (15 butir) — kode <strong>"Tidak Sesuai" belum ada</strong>, telaah ber-jawaban Tidak DITOLAK kirim</td></tr>
                                    <tr><td class="ds-td-strong">Procedure</td><td class="ds-td-class">ProcedureTrait</td><td class="ds-body-sm"><strong>ICD-9-CM</strong></td><td class="ds-body-sm">tindakanList, kodeIcd9/icd9</td></tr>
                                    <tr><td class="ds-td-strong">AllergyIntolerance</td><td class="ds-td-class">AllergyIntoleranceTrait</td><td class="ds-body-sm"><strong>SNOMED</strong></td><td class="ds-body-sm">riwayat alergi (anamnesa) + SNOMED; dr_uuid — <strong>wired (kartu 7)</strong></td></tr>
                                    <tr><td class="ds-td-strong">MedicationRequest</td><td class="ds-td-class">MedicationRequestTrait</td><td class="ds-body-sm"><strong>KFA</strong> sys-ids.kemkes/kfa</td><td class="ds-body-sm">eresep; KFA dari master product_id_satusehat</td></tr>
                                    <tr><td class="ds-td-strong">MedicationDispense</td><td class="ds-td-class">MedicationDispenseTrait</td><td class="ds-body-sm"><strong>KFA</strong></td><td class="ds-body-sm">eresep + KFA; butuh Resep terkirim dulu — <strong>wired (kartu 8)</strong></td></tr>
                                    <tr><td class="ds-td-strong">ServiceRequest</td><td class="ds-td-class">ServiceRequestTrait</td><td class="ds-body-sm"><strong>LOINC</strong> 26436-6 (panel)</td><td class="ds-body-sm"><span class="ds-code">lbtxn_checkuphdrs/dtls</span> + <span class="ds-code">lbmst_clabitems.loinc_code</span> — <strong>wired (kartu 9 Lab)</strong></td></tr>
                                    <tr><td class="ds-td-strong">Specimen</td><td class="ds-td-class">SpecimenTrait</td><td class="ds-body-sm">SNOMED (darah/venipuncture)</td><td class="ds-body-sm">1 per paket checkup — <strong>wired (kartu 9 Lab)</strong></td></tr>
                                    <tr><td class="ds-td-strong">DiagnosticReport</td><td class="ds-td-class">DiagnosticReportTrait</td><td class="ds-body-sm"><strong>LOINC</strong> (kategori LAB)</td><td class="ds-body-sm">merangkum paket lab (<span class="ds-code">lbtxn_checkup*</span>) — <strong>wired (kartu 9 Lab)</strong></td></tr>
                                    <tr><td class="ds-td-strong">DiagnosticReport (radiologi)</td><td class="ds-td-class">DiagnosticReportTrait</td><td class="ds-body-sm">LOINC (kategori RAD)</td><td class="ds-body-sm">order radiologi + dr_uuid; ImagingStudy trait siap (<button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('pacs')">§PACS</button>) — <strong>wired (kartu 10)</strong></td></tr>
                                    <tr><td class="ds-td-strong">ClinicalImpression</td><td class="ds-td-class">ClinicalImpressionTrait</td><td class="ds-body-sm">— (ringkasan diagnosa)</td><td class="ds-body-sm">diagnosa (Condition) + dr_uuid — <strong>wired (kartu 11)</strong></td></tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- ===== DETAIL PENGIRIMAN LAB (kartu 9) ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-3">Detail — Pengiriman Penunjang Lab (kartu 9)</h2>
                        <p class="ds-body-md mb-3" style="max-width:64ch">
                            Sumber <strong>dari DB lab internal</strong> (bukan JSON EMR). Tiap <strong>paket checkup</strong> yang
                            sudah selesai menghasilkan rantai 4 resource. Sama untuk RJ &amp; UGD — beda hanya
                            <span class="ds-code">status_rjri</span> ('RJ' vs 'UGD').
                        </p>

                        <div class="ds-card-outline mb-4" style="padding:16px 20px">
                            <div class="ds-title-sm mb-2">Rantai per paket checkup</div>
                            <div class="ds-body-sm" style="line-height:1.9">
                                <span class="ds-code">ServiceRequest</span> (order, LOINC panel 26436-6)
                                → <span class="ds-code">Specimen</span> (darah/venipuncture)
                                → <span class="ds-code">Observation</span> <strong>× per item ber-LOINC</strong> (kategori laboratory)
                                → <span class="ds-code">DiagnosticReport</span> (merangkum paket).
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Langkah</th><th>Sumber (tabel · kolom)</th><th>Aturan</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Pilih paket</td><td class="ds-body-sm"><span class="ds-code">lbtxn_checkuphdrs</span>: <span class="ds-code">ref_no</span>=rj_no · <span class="ds-code">status_rjri</span>='RJ'/'UGD' · <span class="ds-code">checkup_status &lt;&gt; 'P'</span></td><td class="ds-body-sm">Hanya paket <strong>selesai</strong> (bukan Pending). Tak ada → gagal (toast).</td></tr>
                                    <tr><td class="ds-td-strong">Ambil item</td><td class="ds-body-sm"><span class="ds-code">lbtxn_checkupdtls</span> ⋈ <span class="ds-code">lbmst_clabitems</span>: <span class="ds-code">loinc_code</span>, <span class="ds-code">loinc_display</span>, <span class="ds-code">unit_desc</span>, <span class="ds-code">lab_result</span></td><td class="ds-body-sm">Buang <span class="ds-code">hidden_status≠'N'</span> &amp; header grup (<span class="ds-code">is_group='Y'</span>).</td></tr>
                                    <tr><td class="ds-td-strong">Observation</td><td class="ds-body-sm"><span class="ds-code">loinc_code</span> → code · <span class="ds-code">lab_result</span> → nilai · <span class="ds-code">unit_desc</span> → UCUM</td><td class="ds-body-sm">Item <strong>tanpa LOINC di-skip</strong>; hasil numerik → <span class="ds-code">valueQuantity</span>, selain itu <span class="ds-code">valueString</span>; hasil kosong dilewati.</td></tr>
                                    <tr><td class="ds-td-strong">DiagnosticReport</td><td class="ds-body-sm">identifier <span class="ds-code">{rjNo}-{checkup_no}</span>, category LAB, code 26436-6</td><td class="ds-body-sm">result = semua Observation paket; basedOn = ServiceRequest; performer = <span class="ds-code">dr_uuid</span> DPJP.</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>ID yang disimpan</strong> (<span class="ds-code">satusehat.labServiceRequestIds / labSpecimenIds / labObservationIds / labDiagnosticReportIds</span>)
                                = <strong>UUID balikan SATUSEHAT</strong> dari respons POST tiap resource — bukan dari DB. Dipakai untuk badge hijau &amp; guard "sudah pernah dikirim".
                                <br><strong>⚠️ Asumsi MVP (perlu validasi sandbox):</strong> panel SR/DR pakai LOINC generik <span class="ds-code">26436-6</span> &amp; Specimen default <strong>darah/venipuncture</strong> untuk semua paket — belum tepat untuk lab non-darah (urin/feses).
                            </span>
                        </div>

                        {{-- ===== DETAIL PENGIRIMAN RADIOLOGI (kartu 10) ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-3">Detail — Pengiriman Penunjang Radiologi (kartu 10)</h2>
                        <p class="ds-body-md mb-3" style="max-width:64ch">
                            Lebih ringkas dari lab: tiap <strong>order radiologi</strong> hanya menghasilkan 2 resource.
                            <strong>ImagingStudy</strong>: trait siap + Orthanc terpasang, belum di-wire ke alur ini — <button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('pacs')">detail §PACS</button>.
                            Sama untuk RJ &amp; UGD — beda hanya tabel order.
                        </p>

                        <div class="ds-card-outline mb-4" style="padding:16px 20px">
                            <div class="ds-title-sm mb-2">Rantai per order radiologi</div>
                            <div class="ds-body-sm" style="line-height:1.9">
                                <span class="ds-code">ServiceRequest</span> (order, LOINC generik 18748-4 · SNOMED 363679005 Imaging)
                                → <span class="ds-code">DiagnosticReport</span> (laporan minimal, kategori RAD, <strong>tanpa Observation/ImagingStudy</strong>).
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Langkah</th><th>Sumber (tabel · kolom)</th><th>Aturan</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Ambil order</td><td class="ds-body-sm"><span class="ds-code">rstxn_rjrads</span>/<span class="ds-code">rstxn_ugdrads</span> ⋈ <span class="ds-code">rsmst_radiologis</span>: <span class="ds-code">rad_dtl</span>, <span class="ds-code">rad_id</span>, <span class="ds-code">rad_desc</span> · where <span class="ds-code">rj_no</span></td><td class="ds-body-sm"><strong>Semua order</strong> dikirim (tak difilter status/hasil — beda dari lab). Tak ada order → gagal.</td></tr>
                                    <tr><td class="ds-td-strong">ServiceRequest</td><td class="ds-body-sm">identifier <span class="ds-code">rad-{rjNo}-{rad_dtl}</span> · display = <span class="ds-code">rad_desc</span></td><td class="ds-body-sm">code generik LOINC 18748-4; requester = <span class="ds-code">dr_uuid</span> DPJP.</td></tr>
                                    <tr><td class="ds-td-strong">DiagnosticReport</td><td class="ds-body-sm">category RAD, code 18748-4, basedOn = SR</td><td class="ds-body-sm"><strong>Minimal</strong>: tanpa Observation &amp; tanpa lampiran PDF; ImagingStudy dilewati (no DICOM).</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>ID disimpan</strong> (<span class="ds-code">satusehat.radServiceRequestIds / radDiagnosticReportIds</span>) = UUID balikan SATUSEHAT (guard "sudah pernah dikirim").
                                <br><strong>⚠️ GAP (perlu perbaikan):</strong> alur kirim masih pakai kode generik <span class="ds-code">18748-4</span> untuk semua order. Master <span class="ds-code">rsmst_radiologis</span> kini sudah punya kolom <span class="ds-code">loinc_code</span>/<span class="ds-code">loinc_display</span> (isi via <span class="ds-code">/master/radiologis</span>) — tinggal dipakai menggantikan kode generik. Hasil bacaan/PDF (<span class="ds-code">rsview_rads.rad_upload_pdf</span>) &amp; nilai terstruktur <strong>belum dikirim</strong>; DR masih laporan kosong.
                            </span>
                        </div>

                        {{-- ===== DETAIL JALUR DICOM / ImagingStudy ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-3">Detail — Jalur DICOM / ImagingStudy</h2>
                        <p class="ds-body-md mb-3" style="max-width:64ch">
                            Jalur <strong>lengkap versi SATUSEHAT</strong> dengan PACS Orthanc.
                            <span class="ds-code">ImagingStudyTrait</span> + <span class="ds-code">OrthancTrait</span> sudah siap
                            dan lolos uji staging. Tinggal wire ke UI kirim radiologi. Detail lengkap
                            &rarr; <button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('pacs')">PACS Orthanc &amp; ImagingStudy</button>.
                        </p>

                        <div class="ds-card-outline mb-4" style="padding:16px 20px">
                            <div class="ds-title-sm mb-2">Rantai ideal per order radiologi (DICOM)</div>
                            <div class="ds-body-sm" style="line-height:1.9">
                                <span class="ds-code">ServiceRequest</span> (order, LOINC/ICD spesifik)
                                → <span class="ds-code">ImagingStudy</span> (UID DICOM + modality DCM: CR/CT/MR/US)
                                → <span class="ds-code">Observation</span> <em>(opsional — temuan terstruktur)</em>
                                → <span class="ds-code">DiagnosticReport</span> (basedOn SR, <span class="ds-code">imagingStudy</span> ref, conclusion bacaan + <span class="ds-code">presentedForm</span> PDF).
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Langkah</th><th>Butuh (sumber · field)</th><th>Aturan</th><th>Status</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Ambil order + kode</td><td class="ds-body-sm"><span class="ds-code">rstxn_rjrads/ugdrads</span> ⋈ <span class="ds-code">rsmst_radiologis</span>: <span class="ds-code">loinc_code</span>/<span class="ds-code">loinc_display</span>, ICD-9</td><td class="ds-body-sm">Pakai kode spesifik per tindakan (bukan generik 18748-4).</td><td class="ds-body-sm">🟡 kolom LOINC <strong>ada</strong>, alur kirim belum pakai</td></tr>
                                    <tr><td class="ds-td-strong">Dapatkan UID DICOM</td><td class="ds-body-sm"><span class="ds-code">studyUid</span> · <span class="ds-code">seriesUid</span> · <span class="ds-code">sopUid</span> dari PACS / modality worklist</td><td class="ds-body-sm">Format <span class="ds-code">urn:oid:{OID}</span>. Tanpa PACS → <span class="ds-code">uidStudi()</span> arc 2.25.</td><td class="ds-body-sm">✅ <span class="ds-code">STUDY_UID</span> kolom + OrthancTrait</td></tr>
                                    <tr><td class="ds-td-strong">ImagingStudy</td><td class="ds-body-sm"><span class="ds-code">POST /ImagingStudy</span>: identifier <span class="ds-code">urn:dicom:uid</span>, modality DCM, numberOfSeries/Instances, procedureCode</td><td class="ds-body-sm">Referensi ke <span class="ds-code">Encounter</span> + <span class="ds-code">Patient</span>; started = tgl periksa.</td><td class="ds-body-sm">✅ <span class="ds-code">postImagingStudy()</span> lolos staging, belum di-wire UI</td></tr>
                                    <tr><td class="ds-td-strong">Observation <em>(opsional)</em></td><td class="ds-body-sm">temuan terstruktur ber-LOINC</td><td class="ds-body-sm">Boleh dilewati — banyak radiologi cuma narasi.</td><td class="ds-body-sm">🔴 belum ada capture terstruktur</td></tr>
                                    <tr><td class="ds-td-strong">DiagnosticReport</td><td class="ds-body-sm">basedOn = SR, <span class="ds-code">imagingStudy</span> = [ref], conclusion = bacaan, <span class="ds-code">presentedForm</span> = PDF base64 (<span class="ds-code">rsview_rads.rad_upload_pdf</span>)</td><td class="ds-body-sm">Lengkap (beda dari DR minimal sekarang).</td><td class="ds-body-sm">🔴 sekarang DR tanpa bacaan/PDF</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Jalur SEKARANG (aktif)</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><span class="ds-code">ServiceRequest</span> + <span class="ds-code">DiagnosticReport</span> minimal</li>
                                    <li>Kode generik LOINC <span class="ds-code">18748-4</span></li>
                                    <li>Tanpa ImagingStudy · tanpa PDF · tanpa Observation</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Jalur IDEAL (DICOM)</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>SR + <strong>ImagingStudy</strong> + (Observation) + DR lengkap</li>
                                    <li>Kode LOINC/ICD spesifik per modalitas</li>
                                    <li>Bacaan (conclusion) + PDF (<span class="ds-code">presentedForm</span>)</li>
                                </ul>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Status integrasi PACS:</strong>
                                <br><strong>1)</strong> Isi <span class="ds-code">loinc_code</span> tindakan di <span class="ds-code">/master/radiologis</span> → ganti kode generik 18748-4.
                                <br><strong>2)</strong> <strong>Tanpa PACS (fallback):</strong> <span class="ds-code">uidStudi()</span> generate UID arc <span class="ds-code">2.25</span> — sah, tidak bisa ditelusuri.
                                <br><strong>3)</strong> <strong>Dengan PACS (✅ Orthanc terpasang):</strong> <span class="ds-code">OrthancTrait::cariStudyUid()</span> ambil UID asli → simpan ke <span class="ds-code">STUDY_UID</span> → ImagingStudy penuh.
                                <br>Detail &rarr; <button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('pacs')">§PACS Orthanc &amp; ImagingStudy</button>
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">LOINC vital di-hardcode di blade</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>TD panel <span class="ds-code">85354-9</span> (sistole <span class="ds-code">8480-6</span> / diastole <span class="ds-code">8462-4</span>)</li>
                                    <li>Nadi <span class="ds-code">8867-4</span> · Suhu <span class="ds-code">8310-5</span> · RR <span class="ds-code">9279-1</span></li>
                                    <li><span class="ds-code">LoincTrait</span>/<span class="ds-code">SnomedTrait</span> (lookup live tx.fhir.org) <strong>tidak dipakai</strong> di alur RJ</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">KFA obat</div>
                                <div class="ds-body-sm">
                                    Diambil dari master obat kolom
                                    <span class="ds-code">product_id_satusehat</span> /
                                    <span class="ds-code">product_name_satusehat</span> (di-set manual di
                                    <span class="ds-code">/master/master-obat</span>). Kalau kosong → item
                                    resep di-<strong>skip</strong>.
                                </div>
                            </div>
                        </div>
                    </section>