                    {{-- ====== P1 ATURAN MAIN → IMPLEMENTASI (PDF Petirs, 29/10/2026) ====== --}}
                    <section x-show="section === 'aturan-main'" x-cloak>
                        <div class="ds-eyebrow mb-3">P1 — Pembaruan 29/10</div>
                        <h1 class="ds-display-md mb-4">Aturan Main SATUSEHAT → Implementasi di SIRUS</h1>
                        <p class="ds-body-md mb-4" style="max-width:66ch">
                            Bagian ini lahir dari bedah PDF pelatihan <strong>"Interoperabilitas SATUSEHAT — Pelayanan Rawat Jalan,
                            Gawat Darurat, dan Rawat Inap"</strong> (Dody Naftali, 41 hal.), dicocokkan dengan dokumentasi resmi
                            Kemkes (<span class="ds-code">satusehat.kemkes.go.id/platform/docs</span>) dan SATUSEHAT FHIR R4 IG (Simplifier).
                            Tiap aturan di bawah ditulis berpasangan: <strong>apa kata aturannya</strong> dan <strong>di mana kode kita
                            memenuhinya</strong>. Rujukan aturan teknis yang lebih tua tetap di
                            <button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('standar')">§Standarisasi per Resource</button>.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>#</th><th>Aturan</th><th>Implementasi SIRUS</th><th>Status</th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td class="ds-td-strong">1</td>
                                        <td class="ds-body-sm"><strong>Waktu selalu UTC+00</strong>. 17.35 WIB → <span class="ds-code">10:35:00+00:00</span>. Konversi di <em>satu tempat terpusat</em>.</td>
                                        <td class="ds-body-sm"><span class="ds-code">SatuSehatTrait::waktuKeUtc()</span> dipanggil <span class="ds-code">makeRequest()</span> untuk semua POST/PUT. Sender tetap boleh <span class="ds-code">toIso8601String()</span> lokal.</td>
                                        <td class="ds-body-sm">✅ <span class="ds-code">fc252dcfa</span></td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">2</td>
                                        <td class="ds-body-sm"><strong>Tanda * = wajib</strong>. Baca "Pemetaan Nilai" tiap bab sebagai kontrak.</td>
                                        <td class="ds-body-sm">Diterapkan per temuan: <span class="ds-code">classHistory</span> (*), <span class="ds-code">serviceClass</span> IGD (*), <span class="ds-code">Observation.issued</span> (Rule 10296). Lihat tabel kewajiban di §Siklus Encounter.</td>
                                        <td class="ds-body-sm">✅</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">3</td>
                                        <td class="ds-body-sm"><strong>identifier lokal</strong> <span class="ds-code">http://sys-ids.kemkes.go.id/encounter/{org}</span> + nomor SIMRS.</td>
                                        <td class="ds-body-sm"><span class="ds-code">buildBaseEncounterPayload()</span>: value <span class="ds-code">RJ-/UGD-/RI-{no}</span>.</td>
                                        <td class="ds-body-sm">✅ sudah lama</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">4</td>
                                        <td class="ds-body-sm"><strong>Setiap ID balikan disimpan</strong> — untuk relasi, cegah dobel saat retry, PUT, Composition.</td>
                                        <td class="ds-body-sm">Node JSON <span class="ds-code">satusehat.*</span> per kunjungan (<span class="ds-code">encounterId</span>, <span class="ds-code">chiefComplaintId</span>, <span class="ds-code">perencanaanPulangId</span>, flag <span class="ds-code">encounterTriaged</span>/<span class="ds-code">InProgress</span>/<span class="ds-code">Finished</span>, …).</td>
                                        <td class="ds-body-sm">✅</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">5</td>
                                        <td class="ds-body-sm"><strong>Terminologi bukan teks bebas</strong>; SNOMED dibatasi ECL per bab (riwayat ≠ penyakit).</td>
                                        <td class="ds-body-sm">Layar Coding SATUSEHAT (§Coding) + master opsi berkode terverifikasi (<span class="ds-code">DischargePlanningOptions</span>, <span class="ds-code">KedatanganIgd</span>, <span class="ds-code">DischargeDisposition</span>).</td>
                                        <td class="ds-body-sm">✅</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">6</td>
                                        <td class="ds-body-sm"><strong>PUT = kirim ulang seluruh resource</strong> (tak ada partial update).</td>
                                        <td class="ds-body-sm">Semua PUT Encounter memakai pola <span class="ds-code">getEncounter()</span> → ubah → PUT utuh (<span class="ds-code">startRoomEncounter</span>, <span class="ds-code">tambahStatusEncounter</span>, finish).</td>
                                        <td class="ds-body-sm">✅</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">7</td>
                                        <td class="ds-body-sm"><strong>Kunjungan harus tuntas</strong>: finished + diagnosis + resume. Dinilai berkala, berisiko sanksi.</td>
                                        <td class="ds-body-sm">Finish Encounter + Resume Medis di tiap modul; prasyarat ditolak di depan dengan toast (bukan dikirim lalu gagal).</td>
                                        <td class="ds-body-sm">✅ — tapi lihat celah RJ→UGD di §Tautan</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-dark" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Aturan #1 — waktuKeUtc() di makeRequest()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['utc'] }}</pre>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Penolakan baru yang ditemukan sesi ini</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Pesan SATUSEHAT</th><th>Sebab</th><th>Perbaikan</th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td class="ds-body-sm"><span class="ds-code">Element not found: Observation.issued (RuleNumber: 10296)</span></td>
                                        <td class="ds-body-sm">Mulai ditegakkan 29/10/2026. Tanda vital RJ/UGD/RI tak pernah mengirim <span class="ds-code">issued</span> (hanya lab/radiologi/kedatangan).</td>
                                        <td class="ds-body-sm"><span class="ds-code">ObservationTrait::createObservation()</span> — satu-satunya pembangun Observation — kini selalu mengisi <span class="ds-code">issued</span> = nilai pemanggil, atau <span class="ds-code">effectiveDateTime</span>. Commit <span class="ds-code">593843eca</span>.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Pelajaran:</strong> elemen yang di dokumentasi tidak bertanda * atau belum pernah ditolak bisa
                                <strong>tiba-tiba ditegakkan</strong>. Karena itu elemen yang di Pemetaan Nilai bertanda * (mis.
                                <span class="ds-code">classHistory</span>) diisi sekarang walau server masih menerima tanpanya.
                            </span>
                        </div>
                    </section>

                    {{-- ====== P2 SIKLUS ENCOUNTER RJ/UGD/RI ====== --}}
                    <section x-show="section === 'siklus-encounter'" x-cloak>
                        <div class="ds-eyebrow mb-3">P2 — Pembaruan 29/10</div>
                        <h1 class="ds-display-md mb-4">Siklus Encounter RJ / UGD / RI</h1>
                        <p class="ds-body-md mb-4" style="max-width:66ch">
                            Encounter adalah akar: dibuat (POST) saat kirim pertama, berpindah status lewat PUT, dan ditutup
                            (PUT <span class="ds-code">finished</span>) dengan diagnosis lengkap. Jenis kunjungan beda = <strong>Encounter beda</strong>
                            (IGD → RI menghasilkan dua Encounter: <span class="ds-code">EMER</span> lalu <span class="ds-code">IMP</span>).
                        </p>

                        <h2 class="ds-title-lg mt-6 mb-3">Status & sumber waktunya</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Status</th><th>RJ (<span class="ds-code">AMB</span>)</th><th>UGD (<span class="ds-code">EMER</span>)</th><th>RI (<span class="ds-code">IMP</span>)</th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td class="ds-td-strong">arrived</td>
                                        <td class="ds-body-sm">kolom <span class="ds-code">rj_date</span> (<span class="ds-code">rstxn_rjhdrs</span>)</td>
                                        <td class="ds-body-sm">kolom <span class="ds-code">rj_date</span> (<span class="ds-code">rstxn_ugdhdrs</span>)</td>
                                        <td class="ds-body-sm">kolom <span class="ds-code">entry_date</span> (<span class="ds-code">rstxn_rihdrs</span>)</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">triaged</td>
                                        <td class="ds-body-sm">—</td>
                                        <td class="ds-body-sm"><strong>baru</strong> · JSON <span class="ds-code">screening.tanggalPelayanan</span> (TTD Screening) → cadangan <span class="ds-code">anamnesa.pengkajianPerawatan.jamDatang</span>. Syarat: tingkat triase ada (<span class="ds-code">pengkajianPerawatan.tingkatKegawatan</span> / <span class="ds-code">screening.triaseSaran</span>)</td>
                                        <td class="ds-body-sm">—</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">in-progress</td>
                                        <td class="ds-body-sm">= arrived (perilaku lama)</td>
                                        <td class="ds-body-sm"><strong>berubah</strong> · JSON <span class="ds-code">perencanaan.pengkajianMedis.waktuPemeriksaan</span> (dulu = jam masuk); kosong → jam triase/datang</td>
                                        <td class="ds-body-sm">= entry_date</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">finished</td>
                                        <td class="ds-body-sm"><span class="ds-code">taskIdPelayanan.taskId7</span> → <span class="ds-code">taskId5</span></td>
                                        <td class="ds-body-sm"><span class="ds-code">taskId7</span> → <span class="ds-code">pengkajianMedis.selesaiPemeriksaan</span></td>
                                        <td class="ds-body-sm"><span class="ds-code">exitDate</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="ds-body-sm mt-2" style="color:var(--muted)">
                            Semua JSON = kolom <span class="ds-code">datadaftar*_json</span> di tabel header yang sama. Tidak ada tabel log status baru:
                            riwayat dibangun dari jam yang sudah tercatat di EMR. Urutan dijaga <span class="ds-code">waktuTerakhir()</span> —
                            status berikutnya tak boleh mendahului status sebelumnya (jam triase 21.50 &lt; datang 22.00 → dijepit 22.00).
                            Tanggal selalu dari data, <strong>tidak pernah <span class="ds-code">now()</span></strong>.
                        </p>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">UGD — triaged &amp; in-progress</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['triaged-ugd'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Finish — siapkanFinishEncounter() (bersama RJ/UGD/RI)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['finish-encounter'] }}</pre>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Kewajiban elemen Encounter per modul</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Elemen</th><th>RJ</th><th>IGD</th><th>RI</th><th>SIRUS</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">class / classHistory[i].class &amp; .period</td><td class="ds-body-sm">*</td><td class="ds-body-sm">*</td><td class="ds-body-sm">*</td><td class="ds-body-sm">✅ 1 entri saat finish (<span class="ds-code">9f5191e78</span>)</td></tr>
                                    <tr><td class="ds-td-strong">location[i]</td><td class="ds-body-sm">*</td><td class="ds-body-sm">*</td><td class="ds-body-sm">*</td><td class="ds-body-sm">✅ tak dobel, ditutup <span class="ds-code">completed</span> (<span class="ds-code">2080df2a2</span>, <span class="ds-code">5a4af118f</span>)</td></tr>
                                    <tr><td class="ds-td-strong">location.extension:serviceClass (value + upgradeClassIndicator)</td><td class="ds-body-sm">—</td><td class="ds-body-sm"><strong>*</strong></td><td class="ds-body-sm">—</td><td class="ds-body-sm">✅ IGD saja: reguler / kelas-tetap</td></tr>
                                    <tr><td class="ds-td-strong">hospitalization.dischargeDisposition</td><td class="ds-body-sm">*</td><td class="ds-body-sm">*</td><td class="ds-body-sm">—</td><td class="ds-body-sm">✅ dari Tindak Lanjut (<span class="ds-code">DischargeDisposition</span>)</td></tr>
                                    <tr><td class="ds-td-strong">diagnosis (DD + CC)</td><td class="ds-body-sm">wajib saat finish</td><td class="ds-body-sm">wajib</td><td class="ds-body-sm">wajib</td><td class="ds-body-sm">✅ CC sejak <span class="ds-code">a6b5c425f</span></td></tr>
                                    <tr><td class="ds-td-strong">episodeOfCare, basedOn, partOf, admitSource, origin</td><td class="ds-body-sm">—</td><td class="ds-body-sm">—</td><td class="ds-body-sm">—</td><td class="ds-body-sm">opsional — lihat §Tautan</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Kelas layanan — pasangKelasLayanan()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['service-class'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-6 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">serviceClass ≠ classHistory</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><span class="ds-code">serviceClass</span> = <strong>kelas layanan/perawatan</strong> (reguler, eksekutif, 1/2/3/VIP) — extension Kemkes di <span class="ds-code">location[]</span>.</li>
                                    <li><span class="ds-code">classHistory</span> = riwayat <strong>jenis kunjungan</strong> (AMB/EMER/IMP) — elemen bawaan FHIR.</li>
                                    <li>Pindah kelas 3 → VIP = <span class="ds-code">serviceClass</span> + <span class="ds-code">upgradeClassIndicator</span> di entri lokasi baru, <strong>bukan</strong> classHistory. PDF menulis keduanya berdampingan — mudah tertukar.</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Lokasi (location[])</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><span class="ds-code">startRoomEncounter()</span> hanya menambah lokasi bila <strong>berbeda</strong> dari lokasi terakhir (dulu poli/IGD tercatat 2x).</li>
                                    <li>RI pindah kamar (<span class="ds-code">appendEncounterLocation</span>): kamar lama → <span class="ds-code">completed</span>, end = jam masuk kamar baru (<span class="ds-code">rsmst_trfrooms.start_date</span>).</li>
                                    <li>Finish menutup lokasi yang masih terbuka.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Uji kering siklus — tanpa kirim, tanpa DB</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['uji-siklus'] }}</pre>
                        </div>
                    </section>

                    {{-- ====== P3 DISCHARGE PLANNING & CAREPLAN ====== --}}
                    <section x-show="section === 'rencana-pemulangan'" x-cloak>
                        <div class="ds-eyebrow mb-3">P3 — Pembaruan 29/10</div>
                        <h1 class="ds-display-md mb-4">Discharge Planning &amp; CarePlan Rencana Pemulangan</h1>
                        <p class="ds-body-md mb-4" style="max-width:66ch">
                            Playbook IGD bab 22 dan RI bab 22b: Rencana Pemulangan Pasien dikirim sebagai <strong>CarePlan</strong>
                            kategori <span class="ds-code">736372004</span> (Discharge care plan). RI sudah punya; UGD baru dibuat 29/10.
                            RJ tidak punya bab ini di playbook (discharge planning RJ memang <em>tidak dipakai</em>).
                        </p>

                        <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-3">
                            <div class="ds-card-outline" style="padding:16px 20px">
                                <div class="ds-title-sm mb-1">1 · Form EMR</div>
                                <div class="ds-body-sm">Perencanaan UGD → <strong>Discharge Planning</strong> (atas ke bawah, di bawah Tindak Lanjut). Partial <span class="ds-code">emr-ugd/perencanaan/tabs/discharge-planning-tab</span>; method di <span class="ds-code">⚡rm-perencanaan-ugd-actions</span>.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:16px 20px">
                                <div class="ds-title-sm mb-1">2 · Kartu SATUSEHAT</div>
                                <div class="ds-body-sm"><span class="ds-code">ugd/satu-sehat/⚡kirim-perencanaan-pulang</span> — badge <strong>23</strong>, rantai Kirim Semua sesudah <span class="ds-code">rencana-tindak-lanjut</span>. Kondisi Saat Keluar 24, Resume 25.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:16px 20px">
                                <div class="ds-title-sm mb-1">3 · Resume Medis</div>
                                <div class="ds-body-sm"><span class="ds-code">perencanaanPulangId</span> → section <strong>Perencanaan Perawatan</strong> (LOINC 18776-5) di Composition UGD — slot ini dulu selalu kosong.</div>
                            </div>
                        </div>

                        <div class="ds-card-dark" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Bentuk data → payload CarePlan</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['careplan-ugd'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Keputusan desain:</strong>
                                <br>• <strong>Bentuk data disamakan dengan RI</strong> supaya logika kartu UGD = port kartu RI (fungsi <span class="ds-code">rencanaPulang()</span> identik).
                                <br>• <strong>Form toggle lama dibuang.</strong> File tab UGD dulu berisi toggle DM/PPOK/HIV/CKD/TB/Stroke/Kemoterapi — tak pernah dipasang di modal, tak punya default, dan isinya <em>diagnosis</em>, bukan jenis layanan. Tak ada kode SNOMED terverifikasi untuknya.
                                <br>• <strong>snomedCode disimpan per entri</strong>, bukan dicari saat kirim — record lama tetap memakai kode saat dicatat walau daftar opsi berubah.
                                <br>• Tiap tambah/hapus entri langsung tersimpan (<span class="ds-code">save(false, $logKeterangan)</span>) dan tercatat di log admin MR, mis. "Perencanaan UGD — Tambah alat bantu — Kursi roda".
                                <br>• <strong>Observation OC000055</strong> ("Kriteria Pasien yang dilakukan Rencana Pemulangan", Tabel 26 dok IGD) <strong>belum</strong> dikirim — RI pun belum; EMR tak punya isian kriterianya.
                                <br>• Opsional: kedua bagian "Tidak Ada" / kosong → kartu dilewati dengan toast info, rantai tetap lanjut.
                            </span>
                        </div>
                    </section>

                    {{-- ====== P4 EPISODEOFCARE & TAUTAN KUNJUNGAN ====== --}}
                    <section x-show="section === 'episode-tautan'" x-cloak>
                        <div class="ds-eyebrow mb-3">P4 — Pembaruan 29/10</div>
                        <h1 class="ds-display-md mb-4">EpisodeOfCare &amp; Tautan Antarkunjungan</h1>
                        <p class="ds-body-md mb-4" style="max-width:66ch">
                            Pertanyaan yang sering muncul: "pasien kontrol / transfer RJ→UGD→RI — dirangkai pakai apa?"
                            Jawaban singkat: <strong>bukan EpisodeOfCare</strong> untuk pelayanan biasa. Ringkasan riset dan rancangan
                            di bawah — <strong>rancangan tautan belum dikerjakan</strong> (PR Tahap 3).
                        </p>

                        <h2 class="ds-title-lg mt-6 mb-3">EpisodeOfCare di SATUSEHAT</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Kode <span class="ds-code">episodeofcare-type</span></th><th>Modul</th><th>Kapan dibuat</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-body-sm"><span class="ds-code">TB-SO</span>, <span class="ds-code">TB-RO</span></td><td class="ds-body-sm">Tuberkulosis</td><td class="ds-body-sm">Saat terduga TB (<span class="ds-code">waitlist</span> → <span class="ds-code">active</span>); balasan memuat nomor surveilans</td></tr>
                                    <tr><td class="ds-body-sm"><span class="ds-code">PNC</span></td><td class="ds-body-sm">Nifas</td><td class="ds-body-sm">Kunjungan nifas pertama; penutupan via PATCH</td></tr>
                                    <tr><td class="ds-body-sm"><span class="ds-code">cancer</span> (rev. v3.1 → <span class="ds-code">CNC</span>)</td><td class="ds-body-sm">Registrasi Kanker</td><td class="ds-body-sm">Saat terduga kanker; ditutup saat meninggal</td></tr>
                                    <tr><td class="ds-body-sm">(ANC)</td><td class="ds-body-sm">Kehamilan</td><td class="ds-body-sm">Kunjungan ANC pertama, dipakai selama hamil</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <ul class="ds-body-sm mt-3 space-y-1.5" style="list-style:disc; padding-left:18px; max-width:70ch">
                            <li>Di modul tematik <span class="ds-code">EpisodeOfCare.type</span> bertanda * — <strong>tak ada kode</strong> untuk kontrol umum, penyakit kronis, atau IGD→RI.</li>
                            <li>Di RJ/IGD/RI <span class="ds-code">Encounter.episodeOfCare</span> tanpa * (opsional). Rangkaian kunjungan pasien sudah tertaut lewat IHS pasien.</li>
                            <li>Dok Kemkes tak konsisten: sistem ditulis <span class="ds-code">https://</span> di satu halaman, <span class="ds-code">http://</span> di halaman lain — uji dulu bila kelak dipakai.</li>
                            <li>Pola bila dipakai (program tematik): POST <strong>sekali</strong>, UUID disimpan di tingkat <strong>pasien</strong>, kunjungan berikutnya <span class="ds-code">GET /EpisodeOfCare?patient=&amp;type=&amp;status=active</span>.</li>
                            <li><strong>Pasien kontrol RJ</strong> → penandanya <em>Status Pengunjung</em> <span class="ds-code">Encounter.extension.EncounterToFacility</span> = <span class="ds-code">new | returning | unknown</span> (belum dikirim).</li>
                        </ul>
                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Kondisi kode sekarang:</strong> RI masih mengirim EpisodeOfCare <em>1 RI = 1 episode</em> dengan kode HL7
                                <span class="ds-code">hacc</span> (Home and Community Care) — salah makna dan tak menautkan Encounter lain.
                                Penghentiannya sempat dikerjakan lalu <strong>di-revert atas permintaan user</strong>; keputusan masih terbuka.
                                Jangan diubah tanpa persetujuan.
                            </span>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Tautan transfer yang sudah tersimpan di kasir</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Transfer</th><th>Tabel</th><th>Kolom</th><th>Dibuat oleh</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">RJ → UGD</td><td class="ds-body-sm"><span class="ds-code">rstxn_ugdbiayaselamadirjs</span></td><td class="ds-body-sm"><span class="ds-code">rj_no</span> (RJ) → <span class="ds-code">rj_no_rsugd</span> (UGD)</td><td class="ds-body-sm">Kasir RJ — <span class="ds-code">⚡transfer-rj-ke-ugd-actions</span></td></tr>
                                    <tr><td class="ds-td-strong">UGD → RI</td><td class="ds-body-sm"><span class="ds-code">rstxn_ribiayaselamadugds</span></td><td class="ds-body-sm"><span class="ds-code">rj_no</span> (UGD) → <span class="ds-code">ugd_no_rsri</span> (RI)</td><td class="ds-body-sm">Kasir UGD — <span class="ds-code">⚡transfer-ugd-ke-ri-actions</span></td></tr>
                                    <tr><td class="ds-td-strong">rincian biaya</td><td class="ds-body-sm"><span class="ds-code">rstxn_ugdtempadmins</span> / <span class="ds-code">rstxn_ritempadmins</span></td><td class="ds-body-sm"><span class="ds-code">tempadm_flag</span> (RJ/UGD) + <span class="ds-code">tempadm_ref</span></td><td class="ds-body-sm">sda, berantai sampai RI</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="ds-body-sm mt-2" style="color:var(--muted)">RJ langsung → RI (lewat SPRI/pendaftaran RI) tidak punya tautan di kasir.</p>

                        <h2 class="ds-title-lg mt-8 mb-3">Rancangan (PR Tahap 3 — belum dikerjakan)</h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">A · UGD → RI (prioritas)</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>Kartu Rencana Tindak Lanjut UGD mengirim ServiceRequest <strong>rujukan internal Rawat Inap</strong> (Tabel 27 dok IGD): category <span class="ds-code">3457005</span> Patient referral, code <span class="ds-code">737481003</span> Inpatient care management — <strong>verifikasi tx.fhir.org dulu</strong>. Pemicu: baris transfer / Tindak Lanjut MRS. ID → <span class="ds-code">satusehat.rujukanRawatInapId</span>.</li>
                                    <li>Encounter RI → <span class="ds-code">basedOn</span> SR itu (cari via <span class="ds-code">ugd_no_rsri</span>); bila SR belum ada saat create, pasang saat finish.</li>
                                    <li>Cara keluar UGD tetap <span class="ds-code">oth</span> + teks "Dirawat inap" + no. RI.</li>
                                    <li>Rujukan <em>luar</em> (ServiceRequest SISRUTE) hanya ditautkan ke Encounter pertama (UGD).</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">B · RJ → UGD</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>Cara keluar RJ dari <strong>data transfer</strong>: <span class="ds-code">oth</span> "Dipindahkan ke IGD (UGD #…)" — Tindak Lanjut RJ tak punya opsi IGD.</li>
                                    <li><strong>Celah nyata:</strong> RJ yang ditransfer sering tanpa task 5/7 → Finish Encounter RJ <em>selalu ditolak</em>. Kasir tak menyimpan jam transfer (<span class="ds-code">rj_date</span> UGD disalin dari RJ) → usul: jam selesai = <span class="ds-code">jamDatang</span> Pengkajian Perawat UGD tujuan.</li>
                                    <li>Tanpa ServiceRequest/basedOn — tak ada kode Kemkes "ke IGD".</li>
                                </ul>
                            </div>
                        </div>
                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <div class="ds-title-sm mb-1">Tidak dipakai</div>
                            <div class="ds-body-sm"><span class="ds-code">admitSource</span>, <span class="ds-code">hospitalization.origin</span>, <span class="ds-code">partOf</span> — tercantum di Pemetaan Nilai RJ/IGD/RI tanpa * dan <strong>tanpa kode maupun panduan</strong>. <span class="ds-code">partOf</span> secara FHIR untuk sub-kunjungan di dalam satu kunjungan, bukan rangkaian berurutan.</div>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Keputusan terbuka</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Topik</th><th>Kondisi</th><th>Usulan</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Cara keluar meninggal</td><td class="ds-body-sm">kita <span class="ds-code">exp</span> (HL7)</td><td class="ds-body-sm">dok IGD: <span class="ds-code">exp-lt48h</span> / <span class="ds-code">exp-gt48h</span> (sistem Kemkes) — UGD + RI, verifikasi bagian RI dulu</td></tr>
                                    <tr><td class="ds-td-strong">Kelas kamar RI</td><td class="ds-body-sm">belum</td><td class="ds-body-sm">opsional; data ada (kelas kamar + <span class="ds-code">rsmst_trfrooms</span>)</td></tr>
                                    <tr><td class="ds-td-strong">Triase P0 (DOA)</td><td class="ds-body-sm">tak dikirim</td><td class="ds-body-sm">PDF: SNOMED <span class="ds-code">63238001</span>; peta P1→1, P2→3, P3→4 menunggu komite medis</td></tr>
                                    <tr><td class="ds-td-strong">EpisodeOfCare RI</td><td class="ds-body-sm"><span class="ds-code">hacc</span></td><td class="ds-body-sm">menunggu keputusan user</td></tr>
                                    <tr><td class="ds-td-strong">Status Pengunjung RJ</td><td class="ds-body-sm">belum</td><td class="ds-body-sm"><span class="ds-code">new/returning</span> dari riwayat kunjungan</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Jejak commit 29/10/2026</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Commit</th><th>Isi</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-body-sm"><span class="ds-code">a6b5c425f</span></td><td class="ds-body-sm">Keluhan utama ikut <span class="ds-code">Encounter.diagnosis</span> <span class="ds-code">use=CC</span> (RJ/UGD/RI)</td></tr>
                                    <tr><td class="ds-body-sm"><span class="ds-code">fc252dcfa</span></td><td class="ds-body-sm">dateTime → UTC terpusat di <span class="ds-code">makeRequest()</span></td></tr>
                                    <tr><td class="ds-body-sm"><span class="ds-code">593843eca</span></td><td class="ds-body-sm"><span class="ds-code">Observation.issued</span> selalu dikirim (Rule 10296)</td></tr>
                                    <tr><td class="ds-body-sm"><span class="ds-code">9f5191e78</span></td><td class="ds-body-sm"><span class="ds-code">Encounter.classHistory</span> saat finish</td></tr>
                                    <tr><td class="ds-body-sm"><span class="ds-code">ca746ddf0</span></td><td class="ds-body-sm">Status <span class="ds-code">triaged</span> Encounter IGD</td></tr>
                                    <tr><td class="ds-body-sm"><span class="ds-code">2080df2a2</span></td><td class="ds-body-sm">Kelas layanan IGD (ServiceClass) + lokasi tak dobel</td></tr>
                                    <tr><td class="ds-body-sm"><span class="ds-code">17466d182</span></td><td class="ds-body-sm">Discharge Planning UGD + kartu CarePlan Rencana Pemulangan</td></tr>
                                    <tr><td class="ds-body-sm"><span class="ds-code">5a4af118f</span></td><td class="ds-body-sm">Lokasi ditutup — pindah kamar RI &amp; saat finish</td></tr>
                                    <tr><td class="ds-body-sm"><span class="ds-code">4a5366d1f</span></td><td class="ds-body-sm">Discharge Planning UGD disusun atas ke bawah</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="ds-body-sm mt-2" style="color:var(--muted)">
                            Semua di atas lolos uji kering (payload &amp; siklus tanpa kirim, render Livewire tanpa DB) tetapi
                            <strong>belum diuji kirim sungguhan</strong> ke SATUSEHAT. Kiriman pertama di prod = uji live-nya; periksa
                            <span class="ds-code">web_log_status</span>.
                        </p>
                    </section>
