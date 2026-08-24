                    {{-- ====== 08b STATUS PER MODUL (RJ/UGD/RI) ====== --}}
                    <section x-show="section === 'ri-ugd'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Pengiriman</div>
                        <h1 class="ds-display-md mb-4">Status Pengiriman per Modul — RJ / UGD / RI</h1>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Alur kirim SATUSEHAT sudah tersedia di <strong>tiga modul</strong>. Tiap resource = 1 komponen
                            <span class="ds-code">kirim-*.blade.php</span> di <span class="ds-code">transaksi/{modul}/satu-sehat/</span>,
                            digabung di 1 modal <span class="ds-code">satu-sehat-{modul}-actions</span>, dipanggil dari Daftar {modul}.
                            ID balikan SATUSEHAT disimpan di JSON record (<span class="ds-code">satusehat.*</span>) sebagai guard "sudah pernah dikirim" — khusus lab &amp; radiologi guard-nya <strong>per-order</strong> (<span class="ds-code">radKirim</span>/<span class="ds-code">labKirim</span>), bukan array datar.
                        </p>

                        <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-3">
                            <div class="ds-card-outline" style="padding:16px 20px">
                                <div class="ds-title-sm mb-1">RJ — Rawat Jalan</div>
                                <div class="ds-body-sm">Lengkap (11 resource inti) + <strong>Nyeri &amp; Kesadaran</strong> (Observation) dan <strong>Telaah Resep</strong> (QuestionnaireResponse Q0007) — 15 kartu. Ruang lingkup awal &amp; acuan pola; dua tambahan terakhir kini <strong>sudah ada juga di UGD</strong>, belum di RI.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:16px 20px">
                                <div class="ds-title-sm mb-1">UGD</div>
                                <div class="ds-body-sm">Lengkap + <strong>ChiefComplaint &amp; Allergy</strong> (LOV SNOMED di anamnesa keluhan utama &amp; alergi), serta <strong>Nyeri &amp; Kesadaran</strong> dan <strong>Telaah Resep</strong> — 15 kartu, sejajar RJ. Pilihan kesadaran UGD ada <strong>lima</strong> dan seluruhnya berbeda dari RJ; belum satu pun punya padanan SNOMED, jadi dikirim sebagai teks.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:16px 20px">
                                <div class="ds-title-sm mb-1">RI — Rawat Inap</div>
                                <div class="ds-body-sm"><strong>13 resource aktif</strong> + 2 digating (SNOMED). Encounter class <span class="ds-code">IMP</span>.</div>
                            </div>
                        </div>

                        <h2 class="ds-title-lg mt-6 mb-3">Detail Resource RI (Rawat Inap)</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Resource</th><th>Sumber data RI</th><th>Status</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Encounter (IMP)</td><td class="ds-body-sm"><span class="ds-code">rstxn_rihdrs</span> + lokasi <span class="ds-code">rsmst_rooms.room_uuid</span></td><td class="ds-body-sm">✅ aktif — dukung <strong>pindah kamar</strong> (location[] bertambah)</td></tr>
                                    <tr><td class="ds-td-strong">EpisodeOfCare</td><td class="ds-body-sm"><span class="ds-code">rstxn_rihdrs</span> (episodeNo=rihdr_no, careManager=DPJP)</td><td class="ds-body-sm">✅ aktif — 1 RI = 1 episode; link Encounter; Finish saat pulang</td></tr>
                                    <tr><td class="ds-td-strong">Condition</td><td class="ds-body-sm"><span class="ds-code">rstxn_ridtls</span> ⋈ <span class="ds-code">rsmst_mstdiags</span> <strong>by diag_id</strong></td><td class="ds-body-sm">✅ aktif — aman 288 icdx kembar</td></tr>
                                    <tr><td class="ds-td-strong">Procedure</td><td class="ds-body-sm">JSON <span class="ds-code">procedure[]</span> (procedureId=ICD-9)</td><td class="ds-body-sm">✅ aktif</td></tr>
                                    <tr><td class="ds-td-strong">Observation (vital)</td><td class="ds-body-sm">JSON <span class="ds-code">observasi.observasiLanjutan.tandaVital[]</span></td><td class="ds-body-sm">✅ aktif — <strong>multi-entri</strong> (per waktu ukur)</td></tr>
                                    <tr><td class="ds-td-strong">MedicationRequest</td><td class="ds-body-sm">JSON <span class="ds-code">eresepHdr[].eresep[]</span> → join <span class="ds-code">immst_products</span> (KFA)</td><td class="ds-body-sm">✅ aktif — <strong>racikan belum</strong>; item tanpa KFA di-skip</td></tr>
                                    <tr><td class="ds-td-strong">MedicationDispense</td><td class="ds-body-sm">sda (obatList identik) — pairing 1:1 dgn medicationRequestIds</td><td class="ds-body-sm">✅ aktif — butuh MedicationRequest dulu; authorizingPrescription</td></tr>
                                    <tr><td class="ds-td-strong">ClinicalImpression</td><td class="ds-body-sm">JSON <span class="ds-code">cppt[]</span> (SOAP)</td><td class="ds-body-sm">✅ aktif — 1 entri = 1 CI; <strong>assessor = DPJP</strong> (fallback MVP); guard per <span class="ds-code">cpptId</span></td></tr>
                                    <tr><td class="ds-td-strong">Penunjang Lab</td><td class="ds-body-sm"><span class="ds-code">lbtxn_checkuphdrs</span> <span class="ds-code">status_rjri='RI'</span> ⋈ <span class="ds-code">lbmst_clabitems.loinc_code</span></td><td class="ds-body-sm">✅ aktif — chain SR→Specimen→Obs→DR</td></tr>
                                    <tr><td class="ds-td-strong">Penunjang Radiologi</td><td class="ds-body-sm"><span class="ds-code">rstxn_riradiologs</span> ⋈ <span class="ds-code">rsmst_radiologis.loinc_code</span></td><td class="ds-body-sm">✅ aktif — <strong>LOINC spesifik</strong> bila master terisi, else generik 18748-4; ImagingStudy trait siap (<button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('pacs')">§PACS</button>)</td></tr>
                                    <tr><td class="ds-td-strong">NutritionOrder (Diet)</td><td class="ds-body-sm">JSON <span class="ds-code">pengkajianDokter.rencana.diet</span> (free-text)</td><td class="ds-body-sm">✅ aktif — <strong>text-only</strong> (tanpa coding SNOMED); trait baru <span class="ds-code">NutritionOrderTrait</span></td></tr>
                                    <tr><td class="ds-td-strong">Penilaian (Observation)</td><td class="ds-body-sm">JSON <span class="ds-code">penilaian.resikoJatuh[]</span> &amp; <span class="ds-code">penilaian.gizi[]</span> (bersarang ganda) → <span class="ds-code">App\Support\Terminologi\PenilaianObservationMap</span></td><td class="ds-body-sm">✅ aktif — Morse <span class="ds-code">59460-6</span> skor + <span class="ds-code">59461-4</span> level (<span class="ds-code">survey</span>); BB/TB/IMT <span class="ds-code">29463-7</span>/<span class="ds-code">8302-2</span>/<span class="ds-code">39156-5</span> (<span class="ds-code">vital-signs</span>). Humpty <strong>tanpa LOINC</strong> → generik <span class="ds-code">73830-2</span></td></tr>
                                    <tr><td class="ds-td-strong">Observasi Lanjutan</td><td class="ds-body-sm">JSON <span class="ds-code">observasi.obatDanCairan.pemberianObatDanCairan[]</span>, <span class="ds-code">pemakaianOksigen.pemakaianOksigenData[]</span>, <span class="ds-code">pengeluaranCairan.pengeluaranCairan[]</span> &rarr; <span class="ds-code">App\Support\Terminologi\ObservasiLanjutanMap</span></td><td class="ds-body-sm">&#9989; aktif &mdash; <strong>MedicationAdministration</strong> (KFA, route SNOMED) + Observation oksigen <span class="ds-code">107117-4</span>/<span class="ds-code">3151-8</span> (valueRange) + urine <span class="ds-code">9187-6</span>. Hanya ~31% baris obat ber-productId &rarr; sisanya dilewati &amp; <strong>dilaporkan</strong></td></tr>
                                    <tr><td class="ds-td-strong">ChiefComplaint</td><td class="ds-body-sm">JSON <span class="ds-code">pengkajianDokter.anamnesa.keluhanUtama</span> + SNOMED</td><td class="ds-body-sm">⏸️ <strong>digating</strong> <span class="ds-code">@@if(false)</span> — aktifkan bareng LOV SNOMED</td></tr>
                                    <tr><td class="ds-td-strong">AllergyIntolerance</td><td class="ds-body-sm">JSON <span class="ds-code">pengkajianDokter.anamnesa.jenisAlergi</span> + SNOMED</td><td class="ds-body-sm">⏸️ <strong>digating</strong> <span class="ds-code">@@if(false)</span></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Keputusan desain RI:</strong>
                                <br>• <strong>Location kamar</strong>: kolom <span class="ds-code">rsmst_rooms.room_uuid</span> baru + tombol "Daftarkan Location" di <span class="ds-code">/master/kamar</span> (pola <span class="ds-code">poli_uuid</span>). Pindah kamar → entri <span class="ds-code">location[]</span> baru.
                                <br>• <strong>SNOMED RI digating</strong> (<span class="ds-code">@@if(false)</span>) di <span class="ds-code">rm-pengkajian-dokter-ri-actions</span> (LOV) &amp; <span class="ds-code">satu-sehat-ri-actions</span> (sender). Backend utuh — aktifkan = ubah <span class="ds-code">false→true</span> di 3 titik.
                                <br>• <strong>CPPT → ClinicalImpression</strong>: assessor = DPJP karena PPA non-dokter belum punya IHS.
                                <br>• <strong>NutritionOrder</strong>: text-only — cek sandbox apakah profile wajib coding; kalau ya, perlu master diet ber-SNOMED.
                                <br>• <strong>Penilaian</strong>: kode LOINC diverifikasi lewat <span class="ds-code">tx.fhir.org</span> (<span class="ds-code">LoincTrait</span>), <strong>bukan hafalan</strong> — tebakan awal <span class="ds-code">59460-2</span>/<span class="ds-code">59461-0</span> ternyata SALAH. Pemetaan dipakai bareng RJ/UGD lewat helper statis (bukan trait, hindari tabrakan nama method EMR).
                                <br>• <strong>Guard skor 0</strong>: entri tanpa metode DAN tanpa kategori tidak memancarkan apa-apa. Default form (<span class="ds-code">resikoJatuh='Tidak'</span>, metode='', <strong>skor=0</strong>) = tak ada skala dipakai. <strong>Jangan pakai skor sebagai guard</strong> — <span class="ds-code">0 !== null</span> lolos, dan skor 0 juga nilai Morse yang sah; tanpa guard ini ~1000 record RJ terkirim sebagai "Fall risk = Tidak diketahui".
                                <br>• <strong>Skor/kategori gizi tak dikirim</strong>: skrining custom 3-item (bukan MST/MUST/Strong-Kids) → tanpa padanan LOINC. Hanya antropometri. Nilai di luar batas wajar (BB 0.3–500, TB 20–260, IMT 5–200) dilewati — data nyata sempat berisi <span class="ds-code">bb=1 tb=1 imt=10000</span>.
                            </span>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <div class="ds-title-sm mb-1">Belum dikerjakan (pengayaan, non-wajib)</div>
                            <div class="ds-body-sm">
                                <strong>RI</strong>: SBAR (butuh Communication trait), Composition (ringkasan pulang), ImagingStudy (trait siap, belum di-wire). •
                                <strong>UGD</strong>: MedicationAdministration cairan.
                                <br><br><strong>Racikan obat (RJ/UGD/RI) — SUDAH TERKIRIM sejak 03/08/2026</strong> (sebelumnya buntu spek + data):
                                <br>• <em>Spek</em>: dikirim sebagai <strong>compound</strong> — <span class="ds-code">Medication.contained</span> dengan <span class="ds-code">ingredient[]</span> ber-KFA per bahan, <span class="ds-code">medicationType</span> = <strong>SD/Compound</strong>, dan <span class="ds-code">Medication.code</span> cukup <span class="ds-code">code.text</span> karena campurannya memang tak punya KFA tunggal.
                                <br>• <em>Data</em>: kuncinya bukan <span class="ds-code">productId</span> melainkan <strong>nama</strong> — probe 200 kunjungan RJ: 38 nama bahan tanpa productId (393 baris) <strong>semuanya cocok persis satu</strong> produk ber-KFA di master. <span class="ds-code">App\Support\Terminologi\RacikanKfa</span> memetakan productId → KFA, dan bila kosong mencocokkan nama <strong>hanya bila kandidatnya tepat satu</strong> (nama kembar ditolak — menebak berarti salah obat).
                                <br>• <em>Hasil</em>: RJ <strong>203/205 grup (99%)</strong>, UGD <strong>198/214 (93%)</strong>, RI 2/2 — dari sebelumnya 13%/11%. Grup yang gagal dilaporkan <strong>beserta nama bahannya</strong> (mis. VITAMIN B KOMPLEKS), tinggal dilengkapi KFA-nya di Master Obat.
                                <br>• <em>Sisa</em>: <span class="ds-code">ingredient.strength</span> tak diisi (dosis di JSON teks bebas: "1/2", "sesuai bb"), bentuk sediaan racikan masih default Tablet, dan dispense RI belum ikut racikan.
                            </div>
                        </div>
                    </section>