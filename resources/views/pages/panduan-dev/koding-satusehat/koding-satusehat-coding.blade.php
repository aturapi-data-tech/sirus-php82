                    {{-- ====== 08c CODING SATUSEHAT & ALUR APIKESEHATAN ====== --}}
                    <section x-show="section === 'coding'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Pengiriman</div>
                        <h1 class="ds-display-md mb-4">Coding SATUSEHAT &amp; Alur apikesehatan.com</h1>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Sejak 28/10/2026 pengiriman RJ, UGD, dan RI disamakan dengan contoh alur
                            <span class="ds-code">apikesehatan.com</span> (modul rawat-jalan, igd, rawat-inap). Prinsip utamanya:
                            <strong>EMR hanya berisi teks klinis</strong> untuk pelayanan; kode SNOMED/KFA diisi
                            <strong>sesudah pelayanan</strong> di layar tersendiri <strong>Coding SATUSEHAT</strong>, supaya petugas
                            yang mengisi EMR tidak bingung oleh LOV kode. Rincian teknis per bab: <span class="ds-code">docs/satusehat-api.md</span> §9.4.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>apikesehatan.com BUKAN simulator.</strong> Formulirnya mem-proxy client_id/secret ke SATUSEHAT
                                asli lewat servernya — jangan pernah memasukkan kredensial produksi. Dipakai sebagai <em>acuan label &amp;
                                struktur</em> saja; setiap kodenya wajib dicek ulang di <span class="ds-code">tx.fhir.org</span>
                                (lihat tabel kode salah di bawah).
                            </span>
                        </div>

                        <h2 class="ds-title-lg mt-6 mb-3">Layar Coding SATUSEHAT</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Jalur</th><th>Lokasi &amp; pintu masuk</th><th>Tempat kode disimpan</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">RJ</td><td class="ds-body-sm"><span class="ds-code">transaksi/rj/coding-fhir-rj/</span> — Pelayanan RJ: tab Coding di modal <span class="ds-code">emr-coding-rj</span>; Daftar RJ: modal sendiri</td><td class="ds-body-sm">Key yang <strong>sama</strong> dengan teksnya di <span class="ds-code">datadaftarpolirj_json</span> (mis. <span class="ds-code">anamnesa.keluhanPenyerta.snomedList</span>). Form EMR wajib <span class="ds-code">pertahankanKodeCoding()</span> saat simpan</td></tr>
                                    <tr><td class="ds-td-strong">UGD</td><td class="ds-body-sm"><span class="ds-code">transaksi/ugd/coding-fhir-ugd/</span> — Pelayanan UGD: tab Coding di modal <span class="ds-code">emr-coding-ugd</span>; Daftar UGD: modal sendiri</td><td class="ds-body-sm">Sama dengan RJ (key menumpang node teks) + <span class="ds-code">pertahankanKodeCoding()</span> di anamnesa UGD. LOV SNOMED lama di EMR UGD dicabut</td></tr>
                                    <tr><td class="ds-td-strong">RI</td><td class="ds-body-sm"><span class="ds-code">transaksi/ri/coding-fhir-ri/</span> — Daftar RI: tab Coding di modal <span class="ds-code">emr-coding-ri</span></td><td class="ds-body-sm">Node akar khusus <span class="ds-code">kodingSatuSehat</span> (<span class="ds-code">App\Support\AnamnesaRi::NODE_KODING</span>) — form EMR tak pernah menyentuhnya, jadi <strong>tidak perlu</strong> pertahankanKodeCoding</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <ul class="ds-body-sm mt-3 space-y-1" style="max-width:72ch; list-style:disc; padding-left:20px">
                            <li>Hak akses: Gate <span class="ds-code">emr.codingFhir</span> (<span class="ds-code">AksiRole::EMR_CODING_FHIR</span>) — Dokter, Perawat, MR, Manager Medis/Umum, Admin.</li>
                            <li>Tampilan grid SOAP seperti EMR, teks klinis <strong>hanya-baca</strong>; dipecah per kelompok (anamnesa / pemeriksaan / diagnosa / perencanaan) seperti folder emr-*.</li>
                            <li>Simpan = event <span class="ds-code">save-coding-fhir-{jalur}</span>; tiap kelompok menulis bagiannya sendiri di bawah row lock. Parameter nomor kunjungan opsional (dari "Tutup dan Simpan"); <span class="ds-code">senyap: true</span> = simpan otomatis saat pindah tab, dilewati bila kode tak berubah. Sukses → <span class="ds-code">refresh-after-{jalur}.saved</span> + <span class="ds-code">coding-fhir-{jalur}.tersimpan</span> (berhasil).</li>
                            <li><strong>Satu modal dengan EMR</strong> (2026-10-28): <span class="ds-code">emr-coding-{jalur}</span> = x-modal + header + identitas + tab <em>Rekam Medis | Coding SATUSEHAT</em> di slot <span class="ds-code">tab</span> header. Komponen asli dipasang <span class="ds-code">:embedded="true"</span> (isi di partial <span class="ds-code">*-isi.blade.php</span>, dipakai juga mode modal sendiri). Pindah tab = simpan otomatis tab yang ditinggalkan; gagal validasi → tetap di tab itu. Dirty guard: RJ/UGD satu <span class="ds-code">x-dirty-modal-content</span> di pembungkus (saveEvents ikut tab aktif); RI memakai guard per-tab EMR RI sendiri + guard Coding di pembungkus. Detail: <span class="ds-code">docs/dirty-modal-pattern.md</span> §3.</li>
                            <li>Data yang dikirim <strong>otomatis</strong> (TTV per waktu ukur, pemberian obat, oksigen/urin) ditampilkan sebagai tabel hanya-baca berstatus <em>terkirim / akan dikirim / dilewati + sebabnya</em> lewat <span class="ds-code">TampilanObservasiSatuSehat</span> — peta yang sama dengan sender, jadi tampilan = kiriman.</li>
                            <li>Master pasien (pola alergi): kode riwayat dahulu &amp; keluarga + daftar alergen dipakai ulang kunjungan berikutnya <strong>hanya bila teksnya sama</strong>; riwayat pengobatan sengaja tidak (obat beda antar poli).</li>
                        </ul>

                        <h2 class="ds-title-lg mt-8 mb-3">Kenapa RI memakai node terpisah</h2>
                        <p class="ds-body-sm mb-3" style="max-width:72ch">
                            Teks anamnesa RI tersebar di <strong>dua formulir</strong>: Pengkajian Dokter (terisi ±1 dari 4 rawat inap) dan
                            Pengkajian Awal perawat (±9 dari 10). <span class="ds-code">App\Support\AnamnesaRi</span> adalah sumber tunggal teks
                            untuk sender &amp; layar Coding — teks dokter lebih dulu, cadangannya teks perawat. Alergi RI <strong>dilewati</strong>
                            bila Pengkajian Dokter belum mengisinya (jangan disimpulkan "tidak ada alergi" dari teks kosong).
                        </p>

                        <h2 class="ds-title-lg mt-8 mb-3">Kode apikesehatan yang SALAH arti — jangan disalin</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Langkah</th><th>Kode di apikesehatan</th><th>Arti sebenarnya (tx.fhir.org)</th><th>Yang dipakai SIRUS</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Kondisi pulang "Membaik"</td><td class="ds-body-sm"><span class="ds-code">271299001</span></td><td class="ds-body-sm"><em>Patient's condition worsened</em></td><td class="ds-body-sm"><span class="ds-code">KondisiPulang</span> — Membaik <span class="ds-code">268910001</span></td></tr>
                                    <tr><td class="ds-td-strong">Kondisi pulang "Memburuk" / "Tidak Berubah"</td><td class="ds-body-sm"><span class="ds-code">271593001</span> / <span class="ds-code">162467007</span></td><td class="ds-body-sm"><em>Moribund</em> / <em>Free of symptoms</em></td><td class="ds-body-sm"><span class="ds-code">271299001</span> / <span class="ds-code">359748005</span></td></tr>
                                    <tr><td class="ds-td-strong">Prognosis "Cukup" / "Buruk"</td><td class="ds-body-sm"><span class="ds-code">170969009</span> / <span class="ds-code">170970005</span></td><td class="ds-body-sm"><em>bad</em> / <em>uncertain</em></td><td class="ds-body-sm"><span class="ds-code">Prognosis</span> (helper)</td></tr>
                                    <tr><td class="ds-td-strong">Triase CTAS level 5</td><td class="ds-body-sm"><span class="ds-code">LA6116-3</span></td><td class="ds-body-sm">tidak ada di LOINC</td><td class="ds-body-sm"><span class="ds-code">LA10137-0</span> (answer list LL3059-4; level 2 = <span class="ds-code">LA6113-0</span> display resmi "2 or more")</td></tr>
                                    <tr><td class="ds-td-strong">Psikologis "Emotionally stable"</td><td class="ds-body-sm"><span class="ds-code">276746005</span></td><td class="ds-body-sm"><em>Environmental event</em></td><td class="ds-body-sm">Kooperatif <span class="ds-code">134417007</span> Normal mood; Depresi <span class="ds-code">366979004</span> (temuan, bukan diagnosis)</td></tr>
                                    <tr><td class="ds-td-strong">Kesadaran</td><td class="ds-body-sm"><span class="ds-code">300202002</span> diberi display lain</td><td class="ds-body-sm"><em>Responds to voice</em></td><td class="ds-body-sm"><span class="ds-code">NyeriKesadaranObservationMap</span> (AVPU RJ, skala klasik RI)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Yang ditambahkan per jalur</h2>
                        <div class="grid grid-cols-1 gap-4 mb-4 sm:grid-cols-3">
                            <div class="ds-card-outline" style="padding:16px 20px">
                                <div class="ds-title-sm mb-1">RJ — 25 kartu</div>
                                <div class="ds-body-sm">Keluhan penyerta, riwayat penyakit/keluarga/pengobatan/perjalanan, status psikologis, prognosis, rencana tindak lanjut (SKDP), kondisi pulang; keluhan utama kategori kemkes <span class="ds-code">chief-complaint</span>; alergi per alergen (Obat KFA / Makanan-Lingkungan SNOMED); BB/TB &amp; kesadaran AVPU; cara keluar HL7 <span class="ds-code">rawatJalan()</span>.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:16px 20px">
                                <div class="ds-title-sm mb-1">UGD — 24 kartu</div>
                                <div class="ds-body-sm">Kedatangan &amp; triase (transportasi, surat rujukan, CTAS — dari <span class="ds-code">tingkatKegawatan</span>, cadangan saran Screening; pemetaan P1→1/P2→3/P3→4 <strong>usulan sementara</strong>), pemberian obat (MedicationAdministration), Observasi Lanjutan per entri, kondisi saat keluar; tanpa keluhan penyerta &amp; prognosis (tak ada di alur IGD). Cara keluar <span class="ds-code">gawatDarurat()</span>.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:16px 20px">
                                <div class="ds-title-sm mb-1">RI — 27 kartu</div>
                                <div class="ds-body-sm">Keluhan utama &amp; alergi dihidupkan, pemeriksaan awal Pengkajian Awal + Observasi Lanjutan per entri, edukasi (Procedure + KPTL 10913), perencanaan pemulangan (CarePlan 736372004), prognosis, kondisi saat keluar, Resume Medis RI (18842-5). Cara keluar kini <strong>HL7</strong> <span class="ds-code">rawatInap()</span> (dulu SNOMED).</div>
                            </div>
                        </div>
                        <p class="ds-body-sm" style="max-width:72ch">
                            Sengaja <strong>tidak</strong> dipasang: Goal, status puasa, kehamilan &amp; alergi kontras, RiskAssessment,
                            pindah ruangan/ganti DPJP (Encounter PUT &amp; Location), ImagingStudy dari SIRUS, skrining berbasis
                            QuestionnaireResponse, dan SBAR (<span class="ds-code">Communication</span> bukan resource SATUSEHAT).
                        </p>

                        <h2 class="ds-title-lg mt-8 mb-3">Jebakan yang sudah dibayar</h2>
                        <ul class="ds-body-sm space-y-2" style="max-width:72ch; list-style:disc; padding-left:20px">
                            <li><strong>Key bentrok:</strong> <span class="ds-code">satusehat.observasiLanjutanIds</span> di RI milik kartu Observasi Lanjutan (MA/oksigen/urin). Peta TTV per entri RI memakai <span class="ds-code">tandaVitalLanjutanIds</span>. Selalu cek key hasil yang sudah dipakai sebelum menamai yang baru.</li>
                            <li><strong>Identifier unik lintas jalur:</strong> nomor RJ, UGD, dan RI berasal dari urutan berbeda dan bisa kembar. Beri awalan jalur (<span class="ds-code">RI-…</span>, <span class="ds-code">UGD-…-KONTROL</span>). Catatan: identifier Composition RJ &amp; UGD lama masih <span class="ds-code">rj_no</span> polos.</li>
                            <li><strong>Trait bersama hanya aditif:</strong> opsi baru dengan default lama (ObservationTrait valueBoolean/note, ProcedureTrait kategori/codingTambahan/note, MedicationAdministrationTrait identifier/category/request) — payload jalur lain wajib tetap sama, buktikan dengan dry-run tanpa opsi.</li>
                            <li><strong>Uji di lokal = DB produksi:</strong> jalankan skrip uji lewat bootstrap Laravel (<span class="ds-code">tinker</span> sering menggantung), bungkus <span class="ds-code">DB::beginTransaction()</span>/rollBack, dan ingat <strong>setiap</strong> panggilan <span class="ds-code">makeRequest</span>/VClaim meng-insert <span class="ds-code">web_log_status</span> — walau HTTP di-fake. <span class="ds-code">Livewire::test()->set()</span> memicu hook <span class="ds-code">updated*</span> yang bisa langsung menyimpan.</li>
                            <li><strong>Komentar PHP yang kehilangan penutup <span class="ds-code">*/</span></strong> menelan method di bawahnya tanpa galat lint — hanya render yang menangkapnya. Setelah edit massal, render komponennya.</li>
                        </ul>
                    </section>
