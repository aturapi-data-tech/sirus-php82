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
                                    <tr><td class="ds-td-strong">DiagnosticReport (radiologi)</td><td class="ds-td-class">DiagnosticReportTrait</td><td class="ds-body-sm">LOINC (kategori RAD)</td><td class="ds-body-sm">order radiologi + dr_uuid; ImagingStudy seharusnya dari DICOM Router (<button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('pacs')">§PACS</button>) — <strong>wired (kartu 10)</strong></td></tr>
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
                                = <strong>UUID balikan SATUSEHAT</strong> dari respons POST tiap resource — bukan dari DB. Dipakai untuk badge hijau.
                                <br><strong>Penanda "sudah pernah dikirim" BUKAN array datar itu</strong>, melainkan indeks per-paket <span class="ds-code">satusehat.labKirim</span> — array datar tak menyimpan keterangan paket mana punya id yang mana, jadi tak bisa dipakai menentukan yang bolong. Lihat bab <strong>Indeks per-order</strong>.
                                <br><strong>⚠️ Asumsi MVP (perlu validasi sandbox):</strong> panel SR/DR pakai LOINC generik <span class="ds-code">26436-6</span> &amp; Specimen default <strong>darah/venipuncture</strong> untuk semua paket — belum tepat untuk lab non-darah (urin/feses).
                            </span>
                        </div>

                        {{-- ===== DETAIL PENGIRIMAN RADIOLOGI (kartu 10) ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-3">Detail — Pengiriman Penunjang Radiologi (kartu 10)</h2>
                        <p class="ds-body-md mb-3" style="max-width:64ch">
                            Tiga sender (<span class="ds-code">⚡kirim-radiologi.blade.php</span> RJ/UGD/RI) berlogika identik,
                            beda tabel order &amp; kunci (<span class="ds-code">rad-</span> / <span class="ds-code">ugd-rad-</span> / <span class="ds-code">ri-rad-</span>).
                            <strong>Alur ini belum sesuai arsitektur DICOM Router SATUSEHAT</strong> &mdash; konsep yang benar &amp;
                            daftar selisihnya ada di <button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('pacs')">§PACS, DICOM Router &amp; ImagingStudy</button>.
                        </p>

                        <div class="ds-card-outline mb-4" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Resource</th><th>Yang dikirim SEKARANG</th><th>Target</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">ServiceRequest</td><td class="ds-body-sm">identifier <span class="ds-code">servicerequest/{org}</span> = <span class="ds-code">rad-{rjNo}-{rad_dtl}</span>; LOINC dari master, fallback <span class="ds-code">18748-4</span>; dikirim saat tombol Kirim</td><td class="ds-body-sm">+ identifier <strong>ACSN</strong>; dikirim saat order; kode nasional X bila tak ada LOINC</td></tr>
                                    <tr><td class="ds-td-strong">Observation</td><td class="ds-body-sm"><span class="ds-code">valueString</span> penunjuk lampiran</td><td class="ds-body-sm">bacaan radiolog, <span class="ds-code">basedOn</span> SR, <span class="ds-code">derivedFrom</span> ImagingStudy</td></tr>
                                    <tr><td class="ds-td-strong">DiagnosticReport</td><td class="ds-body-sm"><span class="ds-code">diagnostic/{org}/rad</span>, basedOn SR, result Obs; terkirim walau bacaan belum ada</td><td class="ds-body-sm">hanya bila bacaan ada; <span class="ds-code">conclusion</span>, <span class="ds-code">imagingStudy</span>, performer radiolog</td></tr>
                                    <tr><td class="ds-td-strong">ImagingStudy</td><td class="ds-body-sm">dikirim SIRUS bila foto ada (UID Orthanc / turunan 2.25)</td><td class="ds-body-sm"><strong>dibuat DICOM Router</strong> &mdash; SIRUS berhenti mengirim</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>ID disimpan</strong> (<span class="ds-code">satusehat.radServiceRequestIds / radObservationIds / radDiagnosticReportIds / radImagingStudyIds</span>) = UUID balikan SATUSEHAT.
                                <br><strong>Penanda "sudah pernah dikirim" BUKAN array datar itu</strong>, melainkan indeks per-order <span class="ds-code">satusehat.radKirim</span>. Lihat bab <strong>Indeks per-order</strong>.
                                <br><strong>RI:</strong> waktu SR/Obs/DR saat ini memakai tanggal MASUK rawat inap &mdash; seharusnya <span class="ds-code">rirad_date</span> per order.
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