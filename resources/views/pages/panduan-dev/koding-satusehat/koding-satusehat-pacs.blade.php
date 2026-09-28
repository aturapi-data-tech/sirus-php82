                    {{-- ====== PACS / DICOM ROUTER / IMAGING STUDY ====== --}}
                    <section x-show="section === 'pacs'" x-cloak>
                        <div class="ds-eyebrow mb-3">Radiologi — DICOM System SATUSEHAT</div>
                        <h1 class="ds-display-md mb-4">PACS, DICOM Router &amp; ImagingStudy</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            SATUSEHAT kini punya <strong>DICOM System</strong>: gambar radiologi dikirim lewat
                            <strong>DICOM Router</strong> yang dipasang di RS, dan <strong>router itulah yang membuat
                            <span class="ds-code">ImagingStudy</span></strong> &mdash; bukan SIMRS. Tugas SIRUS bergeser:
                            mengirim <span class="ds-code">ServiceRequest</span> ber-<strong>Accession Number</strong>
                            <em>saat order</em>, lalu bacaan dokter radiologi (<span class="ds-code">Observation</span> +
                            <span class="ds-code">DiagnosticReport</span>).
                        </p>

                        <div class="ds-card-outline mb-8" style="padding:16px 20px; border-color:var(--warning)">
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Status: KONSEP.</strong> Halaman ini menjelaskan alur yang BENAR menurut dokumentasi
                                SATUSEHAT (audit 28/09/2026). Kode kirim radiologi RJ/UGD/RI <strong>belum</strong> diubah
                                ke alur ini &mdash; selisihnya dirinci di bab <strong>Kondisi Kode Sekarang</strong>.
                            </span>
                        </div>

                        {{-- Status ringkas --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-8">
                            <div class="ds-card-outline" style="padding:20px; border-color:var(--success)">
                                <div class="ds-title-sm mb-2" style="color:var(--success)">Sudah ada</div>
                                <ul class="ds-body-sm space-y-1" style="list-style:disc; padding-left:18px">
                                    <li>Orthanc (PACS lokal, port 4242/8042)</li>
                                    <li><span class="ds-code">RADNUM_NO</span> + <span class="ds-code">STUDY_UID</span> di tabel order</li>
                                    <li>SR + Observation + DR terkirim (versi lama)</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px; border-color:var(--warning)">
                                <div class="ds-title-sm mb-2" style="color:var(--warning)">Perlu keputusan</div>
                                <ul class="ds-body-sm space-y-1" style="list-style:disc; padding-left:18px">
                                    <li>Sumber Accession Number</li>
                                    <li>Order &ldquo;kirim ke rad luar&rdquo; dikirim atau tidak</li>
                                    <li>Alat X-ray/USG: DICOM Store + Worklist?</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px; border-color:var(--error)">
                                <div class="ds-title-sm mb-2" style="color:var(--error)">Belum</div>
                                <ul class="ds-body-sm space-y-1" style="list-style:disc; padding-left:18px">
                                    <li>DICOM Router terpasang</li>
                                    <li>SR ber-identifier ACSN, dikirim saat order</li>
                                    <li>Bacaan radiolog terstruktur</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Pembagian tugas --}}
                        <h2 class="ds-title-lg mb-3">Siapa Mengirim Apa</h2>
                        <div class="ds-card-outline mb-8" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Resource</th><th>Pengirim</th><th>Kapan</th><th>Isi kunci</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">ServiceRequest</td><td class="ds-body-sm"><strong>SIRUS</strong></td><td class="ds-body-sm"><strong>Saat order dibuat</strong> &mdash; sebelum pasien difoto</td><td class="ds-body-sm">identifier <span class="ds-code">servicerequest/{org}</span> <strong>+ <span class="ds-code">acsn/{org}</span></strong>, category Imaging, code LOINC</td></tr>
                                    <tr><td class="ds-td-strong">ImagingStudy</td><td class="ds-body-sm"><strong>DICOM Router</strong></td><td class="ds-body-sm">Otomatis saat gambar tiba (C-STORE)</td><td class="ds-body-sm">UID DICOM asli, <span class="ds-code">basedOn</span> SR, file diunggah ke NIDR</td></tr>
                                    <tr><td class="ds-td-strong">Observation</td><td class="ds-body-sm"><strong>SIRUS</strong> (bacaan radiolog)</td><td class="ds-body-sm">Sesudah dibaca</td><td class="ds-body-sm"><span class="ds-code">basedOn</span> SR, <span class="ds-code">derivedFrom</span> ImagingStudy, performer = radiolog</td></tr>
                                    <tr><td class="ds-td-strong">DiagnosticReport</td><td class="ds-body-sm"><strong>SIRUS</strong> (kesimpulan)</td><td class="ds-body-sm">Sesudah dibaca</td><td class="ds-body-sm">identifier <span class="ds-code">diagnostic/{org}/rad</span>, <span class="ds-code">result</span> Observation, <span class="ds-code">conclusion</span>, <span class="ds-code">imagingStudy</span></td></tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- Alur End-to-End --}}
                        <h2 class="ds-title-lg mb-3">Alur Lengkap (target)</h2>
                        <div class="space-y-4 mb-8">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400" style="flex-shrink:0"><span class="text-xs font-bold">1</span></div>
                                    <div class="ds-title-sm">Dokter order radiologi &rarr; Accession Number terbit</div>
                                </div>
                                <div class="ds-body-sm" style="padding-left:40px">
                                    Insert ke <span class="ds-code">rstxn_rjrads</span> / <span class="ds-code">rstxn_ugdrads</span> / <span class="ds-code">rstxn_riradiologs</span>.
                                    Accession Number wajib <strong>unik per faskes</strong> dan <strong>persis sama</strong> dengan tag
                                    <span class="ds-code">AccessionNumber</span> di file DICOM. Bila ada Modality Worklist, order ditulis ke worklist;
                                    bila tidak, radiografer mengetik nomornya di alat.
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400" style="flex-shrink:0"><span class="text-xs font-bold">2</span></div>
                                    <div class="ds-title-sm">SIRUS langsung POST ServiceRequest</div>
                                </div>
                                <div class="ds-body-sm" style="padding-left:40px">
                                    Dikirim <strong>saat order</strong>, bukan di akhir kunjungan. Alasannya: router memeriksa SR
                                    <em>pada detik gambar tiba</em>. SR yang belum ada = gambar tak bisa dicocokkan.
                                    ID balikan disimpan di indeks per-order (<span class="ds-code">radKirim</span>).
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px; border-style:dashed">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full" style="flex-shrink:0; background:var(--surface-soft); color:var(--muted-soft)"><span class="text-xs font-bold">3</span></div>
                                    <div class="ds-title-sm">Alat kirim gambar (C-STORE) &rarr; Orthanc &rarr; DICOM Router</div>
                                </div>
                                <div class="ds-body-sm" style="padding-left:40px">
                                    Orthanc tetap berguna sebagai PACS lokal (arsip &amp; viewer RS) dan meneruskan gambar ke router
                                    sebagai <em>DICOM peer</em>. Tanpa Orthanc, alat boleh langsung ke router.
                                    <strong>Prasyarat:</strong> alat punya DICOM Store SCU (+ Worklist SCU bila ingin nomor otomatis).
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px; border-style:dashed">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full" style="flex-shrink:0; background:var(--surface-soft); color:var(--muted-soft)"><span class="text-xs font-bold">4</span></div>
                                    <div class="ds-title-sm">Router: cocokkan ACSN &rarr; unggah DICOM &rarr; POST ImagingStudy</div>
                                </div>
                                <div class="ds-body-sm" style="padding-left:40px">
                                    Router mengekstrak <span class="ds-code">AccessionNumber</span>, mencari
                                    <span class="ds-code">ServiceRequest?identifier=http://sys-ids.kemkes.go.id/acsn/{org}|{ACSN}</span>,
                                    lalu mengunggah file ke NIDR dan membuat ImagingStudy (<span class="ds-code">basedOn</span> SR).
                                    SIRUS <strong>tidak</strong> membuat ImagingStudy sendiri.
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400" style="flex-shrink:0"><span class="text-xs font-bold">5</span></div>
                                    <div class="ds-title-sm">Radiolog membaca &rarr; SIRUS kirim Observation + DiagnosticReport</div>
                                </div>
                                <div class="ds-body-sm" style="padding-left:40px">
                                    Hanya bila bacaan <strong>sudah ada</strong>. ImagingStudy milik router dicari lewat identifier ACSN yang sama,
                                    lalu dirujuk di <span class="ds-code">Observation.derivedFrom</span> dan
                                    <span class="ds-code">DiagnosticReport.imagingStudy</span>. Performer = dokter radiologi, waktu = waktu bacaan.
                                </div>
                            </div>
                        </div>

                        {{-- Diagram --}}
                        <h2 class="ds-title-lg mb-3">Diagram Alur Antar Aktor</h2>
                        <div class="ds-card-dark mb-8" style="padding:20px 24px; overflow-x:auto">
<pre class="ds-code" style="margin:0; color:var(--on-dark-soft); line-height:1.9">SIRUS                ALAT / ORTHANC        DICOM ROUTER            SATUSEHAT
  │                        │                     │                      │
  ├─ Order + ACSN ─────────┼─ (worklist)         │                      │
  ├─ POST ServiceRequest ──┼─────────────────────┼────────────────────▶ │ SR (identifier acsn)
  │                        ├─ C-STORE ─────────▶ │                      │
  │                        │                     ├─ GET SR?identifier ▶ │ cocokkan ACSN
  │                        │                     ├─ unggah DICOM ─────▶ │ NIDR
  │                        │                     ├─ POST ImagingStudy ▶ │ basedOn SR
  ├─ (radiolog membaca)    │                     │                      │
  ├─ GET ImagingStudy?identifier=acsn ───────────┼────────────────────▶ │
  ├─ POST Observation ─────┼─────────────────────┼────────────────────▶ │ derivedFrom IS
  └─ POST DiagnosticReport ┼─────────────────────┼────────────────────▶ │ result + conclusion</pre>
                        </div>

                        {{-- Identifier ACSN --}}
                        <h2 class="ds-title-lg mb-3">Identifier Accession Number pada ServiceRequest</h2>
                        <p class="ds-body-md mb-3" style="max-width:62ch">
                            SR memuat <strong>dua</strong> identifier: milik kita (untuk pemulihan/anti-duplikat) dan ACSN
                            (untuk router). <span class="ds-code">ServiceRequestTrait</span> saat ini hanya menerima satu &mdash;
                            perlu diperluas.
                        </p>
                        <div class="ds-card-dark mb-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">ServiceRequest.identifier (target)</span>
                            </div>
<pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">"identifier": [
  { "system": "http://sys-ids.kemkes.go.id/servicerequest/{org}",
    "value":  "rad-{rjNo}-{rad_dtl}" },
  { "use": "usual",
    "type": { "coding": [{ "system": "http://terminology.hl7.org/CodeSystem/v2-0203",
                           "code": "ACSN" }] },
    "system": "http://sys-ids.kemkes.go.id/acsn/{org}",
    "value":  "&lt;Accession Number = tag DICOM&gt;" }
]</pre>
                        </div>
                        <div class="ds-card-outline mb-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Belum dipastikan resmi.</strong> System <span class="ds-code">acsn/{org}</span> diambil dari kode
                                DICOM Router (<span class="ds-code">interface/satusehat.py</span>). Dokumen lain menyebut varian
                                <span class="ds-code">accessionno/{org}</span> (halaman ImagingStudy) dan
                                <span class="ds-code">img-accession-no/&lt;subject&gt;</span> (API catalogue). Konfirmasi ke koleksi Postman
                                &ldquo;ServiceRequest - Create - For MWL di dalam DICOM Router&rdquo; sebelum dikodekan.
                            </span>
                        </div>

                        {{-- Kondisi kode sekarang --}}
                        <h2 class="ds-title-lg mb-3">Kondisi Kode Sekarang (RJ, UGD, RI)</h2>
                        <p class="ds-body-md mb-3" style="max-width:62ch">
                            Ketiga <span class="ds-code">⚡kirim-radiologi.blade.php</span> berlogika identik
                            (beda tabel &amp; kunci <span class="ds-code">rad-</span> / <span class="ds-code">ugd-rad-</span> /
                            <span class="ds-code">ri-rad-</span>), jadi selisih di bawah berlaku ketiganya.
                        </p>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Bagian</th><th>Sekarang</th><th>Seharusnya</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">SR identifier</td><td class="ds-body-sm">hanya <span class="ds-code">servicerequest/{org}</span></td><td class="ds-body-sm">+ identifier ACSN &mdash; tanpa ini router tak menemukan order</td></tr>
                                    <tr><td class="ds-td-strong">Waktu kirim SR</td><td class="ds-body-sm">saat tombol Kirim (sesudah hasil)</td><td class="ds-body-sm">saat order dibuat</td></tr>
                                    <tr><td class="ds-td-strong">ImagingStudy</td><td class="ds-body-sm">SIRUS POST sendiri bila ada foto; UID turunan <span class="ds-code">uidStudi()</span> bila Orthanc tak ketemu</td><td class="ds-body-sm">dibuat router &mdash; SIRUS berhenti mengirim (dipagari saklar selama transisi)</td></tr>
                                    <tr><td class="ds-td-strong">Observation</td><td class="ds-body-sm"><span class="ds-code">valueString</span> &ldquo;Lihat hasil pada lampiran radiologi&rdquo;, tanpa basedOn/derivedFrom</td><td class="ds-body-sm">bacaan radiolog, <span class="ds-code">basedOn</span> SR, <span class="ds-code">derivedFrom</span> ImagingStudy</td></tr>
                                    <tr><td class="ds-td-strong">Performer Obs/DR</td><td class="ds-body-sm">dokter pengirim (DPJP)</td><td class="ds-body-sm">dokter radiologi</td></tr>
                                    <tr><td class="ds-td-strong">DiagnosticReport</td><td class="ds-body-sm"><span class="ds-code">final</span> walau belum ada bacaan; tanpa <span class="ds-code">conclusion</span> &amp; <span class="ds-code">imagingStudy</span></td><td class="ds-body-sm">dikirim hanya bila bacaan ada; conclusion + ref ImagingStudy</td></tr>
                                    <tr><td class="ds-td-strong">Waktu</td><td class="ds-body-sm">RJ/UGD: tgl kunjungan · <strong>RI: tgl MASUK rawat inap</strong></td><td class="ds-body-sm">waktu order (<span class="ds-code">rirad_date</span> / <span class="ds-code">waktu_entry</span>) &amp; waktu bacaan</td></tr>
                                    <tr><td class="ds-td-strong">Kode tanpa LOINC</td><td class="ds-body-sm">fallback senyap <span class="ds-code">18748-4</span> (RI 1.896 order, UGD 12, RJ 0)</td><td class="ds-body-sm">kode nasional <span class="ds-code">X</span>+6 digit, system <span class="ds-code">http://terminology.kemkes.go.id/CodeSystem/examination</span></td></tr>
                                    <tr><td class="ds-td-strong">Prioritas &amp; indikasi</td><td class="ds-body-sm">selalu <span class="ds-code">routine</span>; <span class="ds-code">reasonCode</span> tak dikirim</td><td class="ds-body-sm"><span class="ds-code">cito_status</span> &rarr; <span class="ds-code">stat</span>; <span class="ds-code">klinis_desc</span> &rarr; <span class="ds-code">reasonCode</span></td></tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- Fakta data --}}
                        <h2 class="ds-title-lg mb-3">Fakta Data yang Membatasi</h2>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Kolom</th><th>RJ</th><th>UGD</th><th>RI</th><th>Arti</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">total order</td><td class="ds-body-sm">11.125</td><td class="ds-body-sm">5.885</td><td class="ds-body-sm">15.069</td><td class="ds-body-sm">&mdash;</td></tr>
                                    <tr><td class="ds-td-strong"><span class="ds-code">rad_upload_pdf</span></td><td class="ds-body-sm">7.061</td><td class="ds-body-sm">4.262</td><td class="ds-body-sm">7.472</td><td class="ds-body-sm">bacaan mayoritas <strong>PDF</strong>, bukan teks</td></tr>
                                    <tr><td class="ds-td-strong"><span class="ds-code">rad_result</span> + <span class="ds-code">hasil_bacaan</span></td><td class="ds-body-sm">32</td><td class="ds-body-sm">3</td><td class="ds-body-sm">53</td><td class="ds-body-sm">teks untuk <span class="ds-code">conclusion</span> nyaris tak ada</td></tr>
                                    <tr><td class="ds-td-strong"><span class="ds-code">dr_radiologi</span></td><td class="ds-body-sm">~70% (NAMA)</td><td class="ds-body-sm">19</td><td class="ds-body-sm">52</td><td class="ds-body-sm">berisi nama, bukan ID &mdash; perlu peta ke <span class="ds-code">dr_uuid</span></td></tr>
                                    <tr><td class="ds-td-strong"><span class="ds-code">tgl_bacaan</span> / CITO</td><td class="ds-body-sm">0 / 0</td><td class="ds-body-sm">0 / 0</td><td class="ds-body-sm">0 / 0</td><td class="ds-body-sm">waktu bacaan belum pernah dicatat</td></tr>
                                    <tr><td class="ds-td-strong"><span class="ds-code">radnum_no</span></td><td class="ds-body-sm">7.086</td><td class="ds-body-sm">4.273</td><td class="ds-body-sm">7.506</td><td class="ds-body-sm">kandidat ACSN, tapi masih dua skema (lihat bawah)</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="ds-card-outline mb-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Order &ldquo;kirim ke luar&rdquo;.</strong> Di RI, fallback 18748-4 terbanyak berasal dari item yang
                                bukan pemeriksaan kita: <span class="ds-code">2G KIRIM KE RAD LUAR</span> (1.197),
                                <span class="ds-code">3A USG KE LUAR</span> (52), juga <span class="ds-code">2D BACAAN MADINAH</span> dan item generik
                                <span class="ds-code">2E USG DI MADINAH</span> / <span class="ds-code">2F CT SCAN</span>. Perlu diputuskan apakah item
                                ini dikirim sebagai SR kita sama sekali.
                            </span>
                        </div>

                        {{-- Tabel & kolom --}}
                        <h2 class="ds-title-lg mb-3">Tabel &amp; Kolom Terkait</h2>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Tabel</th><th>PK</th><th>Accession (kandidat)</th><th>UID DICOM</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">RSTXN_RJRADS</td><td class="ds-body-sm"><span class="ds-code">RJ_NO, RAD_DTL</span></td><td class="ds-body-sm"><span class="ds-code">RADNUM_NO</span> VARCHAR2(15)</td><td class="ds-body-sm"><span class="ds-code">STUDY_UID</span> VARCHAR2(64)</td></tr>
                                    <tr><td class="ds-td-strong">RSTXN_UGDRADS</td><td class="ds-body-sm"><span class="ds-code">RJ_NO, RAD_DTL</span></td><td class="ds-body-sm"><span class="ds-code">RADNUM_NO</span> VARCHAR2(15)</td><td class="ds-body-sm"><span class="ds-code">STUDY_UID</span> VARCHAR2(64)</td></tr>
                                    <tr><td class="ds-td-strong">RSTXN_RIRADIOLOGS</td><td class="ds-body-sm"><span class="ds-code">RIHDR_NO, RIRAD_NO</span></td><td class="ds-body-sm"><span class="ds-code">RADNUM_NO</span> VARCHAR2(15)</td><td class="ds-body-sm"><span class="ds-code">STUDY_UID</span> VARCHAR2(64)</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="ds-card-outline mb-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Penamaan tabel RI:</strong> <span class="ds-code">RSTXN_RIRADIOLOGS</span>, <strong>bukan</strong>
                                <span class="ds-code">rstxn_rirads</span>. <span class="ds-code">STUDY_UID</span> tetap berguna untuk arsip &amp;
                                viewer lokal walau ImagingStudy dibuat router.
                            </span>
                        </div>

                        {{-- RADNUM_NO --}}
                        <h2 class="ds-title-lg mb-3">RADNUM_NO sebagai Accession Number &mdash; belum beres</h2>
                        <div class="ds-card-outline mb-4" style="padding:16px 20px">
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <span class="ds-code">App\Support\NomorRadiologi::generate()</span> menulis format
                                <span class="ds-code">R-YYMMDD-NNNNN</span> di 4 titik insert order. Masalahnya kolom yang sama
                                <strong>masih dinomori sistem Oracle Dev 6i</strong> dengan sekuens numerik per bulan
                                (master <span class="ds-code">RSMST_RADNUMBERS</span>), yang berulang tiap bulan. Dua skema di satu kolom
                                tidak memenuhi syarat &ldquo;unik per faskes&rdquo;. Pilihannya: ikut sekuens legacy dan
                                mendaftarkannya, atau kolom Accession tersendiri.
                            </span>
                        </div>

                        {{-- OrthancTrait --}}
                        <h2 class="ds-title-lg mb-3">OrthancTrait &mdash; PACS lokal</h2>
                        <p class="ds-body-md mb-3" style="max-width:62ch">
                            <span class="ds-code">App\Http\Traits\SATUSEHAT\OrthancTrait</span> tetap dipakai untuk arsip &amp; viewer RS:
                            mencari <span class="ds-code">StudyInstanceUID</span> per AccessionNumber dan menyimpannya ke
                            <span class="ds-code">STUDY_UID</span>. Konfigurasi <span class="ds-code">.env</span>:
                        </p>
                        <div class="ds-card-dark mb-4" style="padding:0; overflow:hidden">
<pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">ORTHANC_URL=http://localhost:8042
ORTHANC_USER=sirus
ORTHANC_PASSWORD=&lt;password&gt;</pre>
                        </div>
                        <div class="ds-card-outline mb-8" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Method</th><th>Fungsi</th><th>Return</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong"><span class="ds-code">cariStudyUid($accNo)</span></td><td class="ds-body-sm">Query <span class="ds-code">/tools/find</span> by AccessionNumber &rarr; StudyInstanceUID</td><td class="ds-body-sm"><span class="ds-code">string|null</span></td></tr>
                                    <tr><td class="ds-td-strong"><span class="ds-code">sinkronStudyUid($tabel, $where, $radnumNo)</span></td><td class="ds-body-sm">Cari UID + simpan ke <span class="ds-code">STUDY_UID</span> per row</td><td class="ds-body-sm"><span class="ds-code">string|null</span></td></tr>
                                    <tr><td class="ds-td-strong"><span class="ds-code">sinkronStudyUidBatch(...)</span></td><td class="ds-body-sm">Batch row ber-<span class="ds-code">RADNUM_NO</span> yang <span class="ds-code">STUDY_UID</span>-nya kosong</td><td class="ds-body-sm"><span class="ds-code">int</span></td></tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- ImagingStudyTrait --}}
                        <h2 class="ds-title-lg mb-3">ImagingStudyTrait &mdash; hanya masa transisi</h2>
                        <p class="ds-body-md mb-3" style="max-width:62ch">
                            <span class="ds-code">App\Http\Traits\SATUSEHAT\ImagingStudyTrait</span> lolos uji staging
                            (<span class="ds-code">ImagingStudy/16744a38-...</span>, validator tak menuntut UID asli). Namun dalam
                            arsitektur baru ImagingStudy adalah <strong>milik router</strong>. Begitu router terpasang, pengiriman dari
                            SIRUS wajib dimatikan &mdash; kalau tidak, satu studi tercatat dua kali, salah satunya dengan UID turunan
                            <span class="ds-code">2.25</span> yang tidak menunjuk gambar apa pun.
                        </p>
                        <div class="ds-card-dark mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">ImagingStudyTrait::postImagingStudy()</span>
                            </div>
<pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ss-imaging'] }}</pre>
                        </div>

                        {{-- Helper modalitas --}}
                        <h2 class="ds-title-lg mb-3">Helper Modalitas DICOM</h2>
                        <p class="ds-body-sm mb-3" style="max-width:62ch">
                            <span class="ds-code">modalitasDariDeskripsi()</span> menebak kode DICOM dari nama pemeriksaan.
                            Konservatif: yang tak dikenali jadi <span class="ds-code">OT</span> (Other).
                        </p>
                        <div class="ds-card-outline mb-8" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Kode</th><th>Nama</th><th>Kata kunci (case-insensitive)</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">DX</td><td class="ds-body-sm">Digital Radiography</td><td class="ds-body-sm">XR, X-RAY, RONTGEN, THORAX, FOTO</td></tr>
                                    <tr><td class="ds-td-strong">CT</td><td class="ds-body-sm">Computed Tomography</td><td class="ds-body-sm">CT SCAN, CT-SCAN</td></tr>
                                    <tr><td class="ds-td-strong">MR</td><td class="ds-body-sm">Magnetic Resonance</td><td class="ds-body-sm">MRI</td></tr>
                                    <tr><td class="ds-td-strong">US</td><td class="ds-body-sm">Ultrasound</td><td class="ds-body-sm">USG, ULTRASOUND, ULTRASONO</td></tr>
                                    <tr><td class="ds-td-strong">MG</td><td class="ds-body-sm">Mammography</td><td class="ds-body-sm">MAMMO</td></tr>
                                    <tr><td class="ds-td-strong">XA</td><td class="ds-body-sm">X-Ray Angiography</td><td class="ds-body-sm">ANGIO</td></tr>
                                    <tr><td class="ds-td-strong">OT</td><td class="ds-body-sm">Other</td><td class="ds-body-sm"><em>(default)</em></td></tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- DICOM Router --}}
                        <h2 class="ds-title-lg mb-3">Memasang DICOM Router</h2>
                        <div class="ds-card-outline mb-8" style="padding:16px 20px">
                            <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                <li>Dua cara: <strong>Docker</strong> (disarankan, port bisa diatur) atau <strong>Installer</strong> (lebih ringan, port tetap).</li>
                                <li>Installer memakai port <span class="ds-code">8080</span> dan <span class="ds-code">11112</span> &mdash; pastikan tak dipakai layanan lain.</li>
                                <li>Konfigurasi memuat Organization ID, client key &amp; secret SATUSEHAT, serta daftar alat/PACS yang boleh mengirim.</li>
                                <li>Orthanc diarahkan meneruskan gambar ke router sebagai <em>DICOM modality/peer</em>.</li>
                            </ul>
                        </div>

                        {{-- Langkah selanjutnya --}}
                        <h2 class="ds-title-lg mb-3">Urutan Pekerjaan</h2>
                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>1. Putuskan sumber Accession Number</strong> &mdash; <span class="ds-code">RADNUM_NO</span> dibereskan atau kolom sendiri.
                                <br><strong>2. Pastikan system identifier ACSN</strong> dari Postman resmi.
                                <br><strong>3. <span class="ds-code">ServiceRequestTrait</span> multi-identifier</strong> + tambah identifier ACSN di SR radiologi RJ/UGD/RI.
                                <br><strong>4. SR dikirim saat order</strong>, bukan di tombol Kirim.
                                <br><strong>5. Saklar matikan ImagingStudy SIRUS</strong> begitu router aktif.
                                <br><strong>6. Obs/DR hanya bila bacaan ada</strong>; performer radiolog; <span class="ds-code">basedOn</span>, <span class="ds-code">derivedFrom</span>, <span class="ds-code">imagingStudy</span>, <span class="ds-code">conclusion</span>.
                                <br><strong>7. Ganti fallback 18748-4</strong> ke kode nasional X, dan putuskan order &ldquo;kirim ke luar&rdquo;.
                                <br><strong>8. RI: waktu dari <span class="ds-code">rirad_date</span></strong>, bukan tanggal masuk.
                                <br><strong>9. Konfirmasi alat</strong> &mdash; DICOM Store SCU + Worklist SCU pada X-ray &amp; USG.
                            </span>
                        </div>

                        <div class="ds-card-outline mt-4 mb-4" style="padding:16px 20px">
                            <span class="ds-body-sm" style="color:var(--muted)">
                                Sumber: dokumentasi SATUSEHAT <em>DICOM System</em> (Arsitektur, DICOM Router, Instalasi),
                                <em>ImagingStudy</em>, <em>LOINC Radiologi</em>, dan panduan interoperabilitas Rawat Jalan &mdash;
                                dibaca 28/09/2026. Instalasi Orthanc &rarr; <span class="ds-code">docs/pacs-orthanc.md</span>.
                            </span>
                        </div>
                    </section>
