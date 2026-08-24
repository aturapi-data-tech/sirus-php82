                    {{-- ====== UJI KIRIM ====== --}}
                    <section x-show="section === 'uji-kirim'" x-cloak>
                        <div class="ds-eyebrow mb-3">11 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Pelajaran Uji Kirim Pertama</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Sampai 03/08/2026 semua sender "benar secara konstruksi tapi belum pernah divalidasi server".
                            Begitu benar-benar dikirim, server menolak lima hal yang <strong>tak terlihat dari kode</strong>.
                            Semua di bawah ini berasal dari respons <span class="ds-code">OperationOutcome</span>, bukan dari membaca spek.
                        </p>

                        <h2 class="ds-title-lg mb-3">Aturan validator yang menolak</h2>
                        <div class="space-y-3">
                            @foreach ([
                                ['MedicationRequest.dispenseRequest', 'invalid value (expected a DispenseRequest object): []', 'Elemen berkardinalitas 0..1 (objek) dikirim sebagai array kosong.', 'Field opsional hanya disertakan bila ada isinya — berlaku juga untuk dosageInstruction & reasonReference.'],
                                ['MedicationDispense.quantity.system', 'Invalid coding system: …/CodeSystem/kfa-satuan (RuleNumber 10050)', 'CodeSystem kfa-satuan tidak dikenal server.', 'Pakai http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm kode TAB — pola yang sudah dipakai KirimRawatJalanTrait.'],
                                ['AllergyIntolerance.category', 'Element not found: AllergyIntolerance.category (RuleNumber 10075)', 'category sengaja dihilangkan untuk pernyataan "tidak ada alergi" (alasannya benar secara klinis).', 'category WAJIB selalu ada → AlergiSnomed::kategoriFhir(). type & criticality tetap boleh dihilangkan di situ.'],
                                ['Encounter.statusHistory', 'every statusHistory period start and end must be filled (Rule 10122)', 'Entri dari createNewEncounter()/startRoomEncounter() hanya punya start.', 'EncounterTrait::siapkanFinishEncounter() mengisi end tiap entri dari start entri BERIKUTNYA.'],
                                ['Encounter.diagnosis', 'Element not found: Encounter.diagnosis (RuleNumber 10457)', 'Finish dikirim tanpa diagnosis.', 'Diisi dari conditionIds (use = DD, rank berurutan). Tombol Finish menolak lebih dulu bila diagnosa belum dikirim.'],
                            ] as [$elemen, $pesan, $sebab, $perbaikan])
                                <div class="ds-card-outline" style="padding:16px 20px">
                                    <div class="ds-title-sm mb-1">{{ $elemen }}</div>
                                    <div class="ds-body-sm" style="opacity:.85"><em>{{ $pesan }}</em></div>
                                    <div class="ds-body-sm mt-2"><strong>Sebab:</strong> {{ $sebab }}</div>
                                    <div class="ds-body-sm"><strong>Perbaikan:</strong> {{ $perbaikan }}</div>
                                </div>
                            @endforeach
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Uji payload tanpa mengirim</h2>
                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">dry-run: tangkap payload, jangan kirim</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['dryrun'] }}</pre>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Sumber data yang ternyata salah alamat</h2>
                        <p class="ds-body-md mb-3" style="max-width:62ch">
                            Dua sender membaca key yang <strong>tak pernah ada</strong> di JSON EMR. Keduanya gagal
                            <strong>senyap</strong> — toast bilang "berhasil dikirim (0 item)". Pola inilah yang wajib
                            dicurigai saat menambah sender baru.
                        </p>
                        <div class="ds-card-outline" style="padding:16px 20px">
                            <div class="ds-body-sm">
                                <strong>Observation RJ</strong> — dibaca: <span class="ds-code">pemeriksaanFisik</span>/<span class="ds-code">tandaVital</span> di akar, key <span class="ds-code">sistole/diastole/nadi/rr</span>.
                                <br>Yang benar: <span class="ds-code">pemeriksaan.tandaVital</span>, key <span class="ds-code">sistolik/distolik/frekuensiNadi/frekuensiNafas/spo2</span>.
                                <br><br><strong>MedicationRequest &amp; Dispense RJ/UGD</strong> — dibaca: <span class="ds-code">kfaCode</span>/<span class="ds-code">product_id_satusehat</span> di item e-resep.
                                <br>Yang benar: lookup <span class="ds-code">immst_products.product_id_satusehat</span> lewat <span class="ds-code">productId</span> (<span class="ds-code">App\Support\Terminologi\ObatKfa</span>). RI sudah benar sejak awal.
                                <br><br><strong>Aturannya:</strong> verifikasi key ke data nyata (<span class="ds-code">findDataRJ()</span> lalu <span class="ds-code">array_keys()</span>) — jangan percaya nama field di kode lama.
                            </div>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Waktu "selesai" beda tiap modul</h2>
                        <p class="ds-body-md mb-3" style="max-width:62ch">
                            <span class="ds-code">Encounter.period.end</span> harus jam layanan berakhir, bukan
                            <span class="ds-code">now()</span> (= jam petugas mengklik tombol).
                        </p>
                        <div class="space-y-3">
                            @foreach ([
                                ['RJ', 'taskId7 → taskId5 → now()', 'Probe 150 kunjungan: task5 (keluar poli) terisi 125×.'],
                                ['UGD', 'taskId7 → perencanaan.pengkajianMedis.selesaiPemeriksaan → now()', 'UGD TIDAK memakai task 5 — terisi 0×, sedangkan "Selesai Pemeriksaan" terisi 91×.'],
                                ['RI', 'exitDate (tgl pulang) → now()', 'Rawat inap tak memakai task antrean.'],
                            ] as [$modul, $urutan, $alasan])
                                <div class="ds-card-outline" style="padding:16px 20px">
                                    <div class="ds-title-sm mb-1">{{ $modul }}</div>
                                    <div class="ds-body-sm"><span class="ds-code">{{ $urutan }}</span></div>
                                    <div class="ds-body-sm" style="opacity:.85">{{ $alasan }}</div>
                                </div>
                            @endforeach
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Pasangan resep → penyerahan</h2>
                        <div class="ds-card-outline" style="padding:16px 20px">
                            <div class="ds-body-sm">
                                <span class="ds-code">MedicationDispense.authorizingPrescription</span> dulu ditebak dari
                                <strong>urutan</strong> daftar <span class="ds-code">medicationRequestIds</span> — geser satu item saja,
                                obat tertaut ke resep yang salah tanpa ada yang tahu. Sejak racikan ikut dikirim, urutan itu makin rawan.
                                <br><br>Sekarang resep mencatat peta eksplisit <span class="ds-code">satusehat.medicationRequestItems</span>
                                (<span class="ds-code">id, jenis, kunci, kode, display, qty</span>), dan
                                <span class="ds-code">App\Support\Terminologi\MedicationRequestItem::ambil()</span> menyusun ulang peta itu untuk kunjungan lama —
                                <strong>ditolak bila jumlahnya tak cocok</strong>, bukan menebak.
                            </div>
                        </div>
                    </section>

                    {{-- ====== 11 BACKLOG ====== --}}
                    <section x-show="section === 'backlog'" x-cloak>
                        <div class="ds-eyebrow mb-3">11 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Backlog &amp; Gotcha</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Temuan verifikasi lapangan — perbaiki saat menyentuh area terkait.
                        </p>

                        <div class="space-y-3">
                            @foreach ([
                                ['env() tanpa config wrapper', 'Mati senyap bila config:cache. Rekomendasi: buat config/satusehat.php dan baca via config(\'satusehat.*\').'],
                                ['5 resource belum di-wire', 'Dispense/ServiceRequest/Specimen/DiagnosticReport/Allergy → kolom dashboard itu akan 0 walau trait tersedia.'],
                                ['Timeout 10s tanpa retry/connectTimeout', 'Samakan pola dgn BPJS timeout(8)->connectTimeout(3) supaya tak membekukan worker.'],
                                ['KFA/kode di-skip diam-diam', 'Bila master belum diisi → tambahkan peringatan "N item tanpa kode dilewati".'],
                                ['registrationId == medicationCode == kfaCode', 'Di kirim-medication-request:89-90 — perlu ditinjau apakah field registrasi obat harus beda dari KFA.'],
                                ['DiagnosticReport default kategori MB (Microbiology)', 'Set eksplisit LAB/RAD saat mengaktifkan lab/radiologi.'],
                                ['Diagnosa tak tandai primer/sekunder', 'Encounter.diagnosis.rank tidak diisi → semua Condition setara.'],
                                ['Token TTL hardcoded 3500', 'Mengabaikan expires_in, tak ada invalidasi cache saat 401.'],
                            ] as $i => [$judul, $isi])
                                <div class="ds-card-outline" style="padding:16px 20px">
                                    <div class="flex items-start gap-3">
                                        <span style="display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;border-radius:9999px;background:var(--primary);color:#fff;font-size:12px;font-weight:700;flex:none">{{ $i + 1 }}</span>
                                        <div>
                                            <div class="ds-title-sm mb-1">{{ $judul }}</div>
                                            <div class="ds-body-sm">{{ $isi }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- ====== 12 TAMBAH ====== --}}
                    <section x-show="section === 'tambah'" x-cloak>
                        <div class="ds-eyebrow mb-3">12 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Menambah / Mengaktifkan Resource Baru</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Dua skenario. Cek dulu di <button type="button" class="hover:underline font-semibold"
                                style="color:var(--primary)" x-on:click="go('dashboard')">Peta Dashboard</button>
                            apakah trait-nya sudah ada.
                        </p>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
                            <div class="ds-card-outline" style="padding:20px; border-color:var(--primary)">
                                <div class="ds-title-sm mb-2">A · Trait sudah ada</div>
                                <div class="ds-body-sm">Dispense · ServiceRequest · Specimen · DiagnosticReport · Allergy → tinggal <strong>wire ke UI</strong>.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">B · Trait belum ada</div>
                                <div class="ds-body-sm">Composition · Immunization → <strong>buat trait dulu</strong>. ImagingStudy → trait siap, <button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('pacs')">wire ke UI (§PACS)</button>.</div>
                            </div>
                        </div>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Kerangka komponen kirim per-resource (meniru kirim-procedure)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['kirim-component'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Langkah adopsi — A (wire) vs B (trait baru)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['add-resource'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Selalu uji di sandbox dulu</strong> (ganti env AUTH/BASE URL ke <span class="ds-code">-stg</span>),
                                lalu verifikasi payload &amp; balasan lewat tabel <span class="ds-code">web_log_status</span>
                                sebelum diarahkan ke production.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 13 GLOSARIUM ====== --}}
                    <section x-show="section === 'glosarium'" x-cloak>
                        <div class="ds-eyebrow mb-3">13 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Glosarium FHIR &amp; SATUSEHAT</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Istilah yang sering muncul saat menyentuh integrasi ini.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Istilah</th><th>Arti</th></tr></thead>
                                <tbody>
                                    @foreach ([
                                        ['SATUSEHAT', 'Platform interoperabilitas data kesehatan Kemenkes (standar FHIR)'],
                                        ['FHIR R4', 'Fast Healthcare Interoperability Resources versi R4 — standar pertukaran data kesehatan'],
                                        ['Resource', 'Satuan data FHIR (Encounter, Condition, Observation, dst.) — dikirim per HTTP call'],
                                        ['Encounter', 'Resource kunjungan pasien — AKAR yang direferensikan semua resource lain'],
                                        ['Condition', 'Resource diagnosa (encounter-diagnosis) atau keluhan (problem-list-item)'],
                                        ['Observation', 'Resource pengukuran/observasi klinis (mis. tanda vital)'],
                                        ['Procedure', 'Resource tindakan medis'],
                                        ['MedicationRequest', 'Resource peresepan obat'],
                                        ['IHS Code', 'Identitas unik resource/pasien/dokter di SATUSEHAT (patient_uuid, dr_uuid, poli_uuid)'],
                                        ['Organization-Id', 'Identitas fasilitas kesehatan pengirim (env SATUSEHAT_ORGANIZATION_ID)'],
                                        ['ICD-10', 'Sistem kode diagnosa penyakit internasional'],
                                        ['ICD-9-CM', 'Sistem kode tindakan/prosedur medis'],
                                        ['LOINC', 'Sistem kode observasi & pemeriksaan laboratorium'],
                                        ['SNOMED CT', 'Terminologi klinis (keluhan, alergi, kategori tindakan)'],
                                        ['KFA', 'Kamus Farmasi & Alat kesehatan Kemenkes — kode standar obat'],
                                        ['UCUM', 'Unified Code for Units of Measure — satuan pengukuran standar'],
                                        ['client_credentials', 'Alur OAuth2 mesin-ke-mesin (client_id + secret → access_token)'],
                                        ['fail-soft', 'Kegagalan langkah non-akar tidak menghentikan langkah lain'],
                                        ['Idempotensi', 'Jaminan kirim ulang tak menggandakan resource (guard state + natural key)'],
                                        ['Bundle', 'Kumpulan resource FHIR dalam satu transaksi — TIDAK dipakai di sini (per-resource)'],
                                        ['web_log_status', 'Tabel audit tiap panggilan API SATUSEHAT (payload & response)'],
                                    ] as [$istilah, $arti])
                                        <tr>
                                            <td class="ds-td-strong" style="white-space:nowrap">{{ $istilah }}</td>
                                            <td class="ds-body-sm">{{ $arti }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Detail lengkap &amp; nomor baris kode: <span class="ds-code">docs/satusehat-api.md</span>.
                                Lihat juga <span class="ds-code">docs/trait-template-api-eksternal.md</span> &amp;
                                <span class="ds-code">docs/diagnosa-architecture.md</span>.
                            </span>
                        </div>
                    </section>