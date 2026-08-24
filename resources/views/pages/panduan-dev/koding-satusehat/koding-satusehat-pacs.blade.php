                    {{-- ====== PACS / IMAGING STUDY ====== --}}
                    <section x-show="section === 'pacs'" x-cloak>
                        <div class="ds-eyebrow mb-3">PACS — Adopsi</div>
                        <h1 class="ds-display-md mb-4">PACS Orthanc &amp; ImagingStudy</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Koneksi SIRUS ke PACS Orthanc untuk mendapatkan <strong>UID DICOM asli</strong>,
                            lalu mengirim <span class="ds-code">ImagingStudy</span> ke SATUSEHAT dengan identitas
                            yang bisa ditelusuri ke gambar di PACS.
                        </p>

                        {{-- Status ringkas --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-8">
                            <div class="ds-card-outline" style="padding:20px; border-color:var(--success)">
                                <div class="ds-title-sm mb-2" style="color:var(--success)">Orthanc</div>
                                <ul class="ds-body-sm space-y-1" style="list-style:disc; padding-left:18px">
                                    <li>Docker, port 4242 (DICOM) + 8042 (HTTP)</li>
                                    <li>AET <span class="ds-code">ORTHANC_RSIM</span></li>
                                    <li>SQLite (cukup ~400 pemeriksaan/bulan)</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px; border-color:var(--success)">
                                <div class="ds-title-sm mb-2" style="color:var(--success)">Trait</div>
                                <ul class="ds-body-sm space-y-1" style="list-style:disc; padding-left:18px">
                                    <li><span class="ds-code">OrthancTrait</span> — koneksi SIRUS &rarr; Orthanc</li>
                                    <li><span class="ds-code">ImagingStudyTrait</span> — kirim ke SATUSEHAT</li>
                                    <li>Lolos uji staging</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px; border-color:var(--warning)">
                                <div class="ds-title-sm mb-2" style="color:var(--warning)">Belum</div>
                                <ul class="ds-body-sm space-y-1" style="list-style:disc; padding-left:18px">
                                    <li>Konfirmasi alat DICOM</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Alur End-to-End --}}
                        <h2 class="ds-title-lg mb-3">Alur Lengkap: Order &rarr; Upload &rarr; PACS &rarr; SATUSEHAT</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Lima langkah dari permintaan dokter sampai data terkirim ke SATUSEHAT.
                            Langkah 3&ndash;4 hanya aktif kalau alat radiologi support DICOM.
                        </p>

                        <div class="space-y-4 mb-8">
                            {{-- Step 1 --}}
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400" style="flex-shrink:0">
                                        <span class="text-xs font-bold">1</span>
                                    </div>
                                    <div class="ds-title-sm">Dokter Order Radiologi (EMR)</div>
                                    <span class="ds-caption px-2 py-0.5 rounded" style="background:var(--success-soft); color:var(--success)">aktif</span>
                                </div>
                                <div class="ds-body-sm" style="padding-left:40px">
                                    EMR &rarr; tab Penunjang &rarr; Radiologi &rarr; pilih pemeriksaan &rarr; Kirim.<br>
                                    <strong>Insert</strong> ke <span class="ds-code">rstxn_rjrads</span> / <span class="ds-code">ugdrads</span> / <span class="ds-code">riradiologs</span>
                                    dengan <span class="ds-code">RADNUM_NO</span> auto-generate
                                    (<span class="ds-code">NomorRadiologi::generate()</span> &rarr; format <span class="ds-code">R-YYMMDD-NNNNN</span>, 14 char).<br>
                                    <strong>File:</strong> <span class="ds-code">⚡rm-radiologi-rj-actions.blade.php</span> (RJ),
                                    <span class="ds-code">⚡rm-radiologi-ugd-actions.blade.php</span> (UGD),
                                    <span class="ds-code">⚡rm-radiologi-ri-actions.blade.php</span> (RI),
                                    <span class="ds-code">⚡upload-radiologi-tambah-actions.blade.php</span> (penunjang).
                                </div>
                            </div>

                            {{-- Step 2 --}}
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400" style="flex-shrink:0">
                                        <span class="text-xs font-bold">2</span>
                                    </div>
                                    <div class="ds-title-sm">Petugas Radiologi Upload Foto &amp; Bacaan</div>
                                    <span class="ds-caption px-2 py-0.5 rounded" style="background:var(--success-soft); color:var(--success)">aktif</span>
                                </div>
                                <div class="ds-body-sm" style="padding-left:40px">
                                    Modul Penunjang Radiologi (<span class="ds-code">/transaksi/penunjang/radiologi</span>):<br>
                                    &bull; Upload <strong>foto</strong> (JPG/PDF) &rarr; <span class="ds-code">rad_upload_pdf_foto</span><br>
                                    &bull; Upload <strong>hasil bacaan</strong> (PDF) &rarr; <span class="ds-code">rad_upload_pdf</span><br>
                                    &bull; Tulis bacaan di editor &rarr; <span class="ds-code">hasil_bacaan</span> (CLOB)<br>
                                    <strong>File:</strong> <span class="ds-code">⚡upload-radiologi-foto-actions.blade.php</span>,
                                    <span class="ds-code">⚡upload-radiologi-bacaan-actions.blade.php</span>
                                </div>
                            </div>

                            {{-- Step 3 --}}
                            <div class="ds-card-outline" style="padding:20px; border-style:dashed">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full" style="flex-shrink:0; background:var(--surface-soft); color:var(--muted-soft)">
                                        <span class="text-xs font-bold">3</span>
                                    </div>
                                    <div class="ds-title-sm">Alat Radiologi Kirim Gambar ke Orthanc</div>
                                    <span class="ds-caption px-2 py-0.5 rounded" style="background:var(--warning-soft); color:var(--warning)">menunggu alat DICOM</span>
                                </div>
                                <div class="ds-body-sm" style="padding-left:40px">
                                    Alat X-ray/USG mengirim gambar via <strong>C-STORE</strong> ke Orthanc (port 4242).<br>
                                    Orthanc menerbitkan <span class="ds-code">StudyInstanceUID</span> asli. <span class="ds-code">AccessionNumber</span> di gambar
                                    = <span class="ds-code">RADNUM_NO</span> dari worklist SIMRS.<br>
                                    <strong>Prasyarat:</strong> alat harus punya DICOM Store SCU + Modality Worklist SCU.
                                    Kalau alat hanya cetak film / simpan JPEG ke USB, langkah ini <strong>dilewati</strong>.
                                </div>
                            </div>

                            {{-- Step 4 --}}
                            <div class="ds-card-outline" style="padding:20px; border-style:dashed">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full" style="flex-shrink:0; background:var(--surface-soft); color:var(--muted-soft)">
                                        <span class="text-xs font-bold">4</span>
                                    </div>
                                    <div class="ds-title-sm">SIRUS Sinkron UID dari Orthanc</div>
                                    <span class="ds-caption px-2 py-0.5 rounded" style="background:var(--success-soft); color:var(--success)">infra siap</span>
                                </div>
                                <div class="ds-body-sm" style="padding-left:40px">
                                    <span class="ds-code">OrthancTrait::cariStudyUid($radnumNo)</span> &rarr; query
                                    <span class="ds-code">POST /tools/find</span> by AccessionNumber &rarr; dapat <span class="ds-code">StudyInstanceUID</span>.<br>
                                    Simpan ke kolom <span class="ds-code">STUDY_UID</span> di tabel order.<br>
                                    <strong>Tanpa PACS (fallback):</strong> <span class="ds-code">STUDY_UID</span> tetap kosong &rarr;
                                    <span class="ds-code">uidStudi()</span> generate UID turunan arc <span class="ds-code">2.25</span> (sah bentuk, tidak bisa ditelusuri).
                                </div>
                            </div>

                            {{-- Step 5 --}}
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400" style="flex-shrink:0">
                                        <span class="text-xs font-bold">5</span>
                                    </div>
                                    <div class="ds-title-sm">Kirim ke SATUSEHAT (Kartu 10)</div>
                                    <span class="ds-caption px-2 py-0.5 rounded" style="background:var(--success-soft); color:var(--success)">SR+DR aktif</span>
                                    <span class="ds-caption px-2 py-0.5 rounded" style="background:var(--success-soft); color:var(--success)">ImagingStudy RJ+UGD+RI aktif</span>
                                </div>
                                <div class="ds-body-sm" style="padding-left:40px">
                                    Petugas buka halaman SATUSEHAT pasien &rarr; klik Kirim di kartu Radiologi.<br>
                                    <strong>File:</strong> <span class="ds-code">⚡kirim-radiologi.blade.php</span> (per modul RJ/UGD/RI).<br><br>
                                    Loop tiap order radiologi:<br>
                                    <span class="ds-code" style="display:inline-block; margin:4px 0">&nbsp;① ServiceRequest</span> — order radiologi (LOINC 18748-4 atau spesifik dari master)<br>
                                    <span class="ds-code" style="display:inline-block; margin:4px 0">&nbsp;② DiagnosticReport</span> — laporan (basedOn SR, kategori RAD)<br>
                                    <span class="ds-code" style="display:inline-block; margin:4px 0">&nbsp;③ ImagingStudy</span> — metadata studi pencitraan (UID DICOM + modality) &mdash; <strong>aktif RJ + UGD + RI, auto upload foto ke Orthanc</strong><br><br>
                                    ID hasil kirim disimpan ke JSON <span class="ds-code">satusehat</span> di record kunjungan
                                    (<span class="ds-code">radServiceRequestIds</span>, <span class="ds-code">radDiagnosticReportIds</span>, <span class="ds-code">radImagingStudyIds</span>),
                                    dan yang menentukan sebuah order sudah lengkap atau belum adalah indeks per-order
                                    <span class="ds-code">radKirim</span> — termasuk yang membuat foto yang diupload
                                    <strong>sesudah</strong> kiriman pertama masih bisa disusulkan ImagingStudy-nya.
                                </div>
                            </div>
                        </div>

                        {{-- Diagram sequence --}}
                        <h2 class="ds-title-lg mb-3">Diagram Alur Antar Aktor</h2>
                        <div class="ds-card-dark mb-8" style="padding:20px 24px; overflow-x:auto">
<pre class="ds-code" style="margin:0; color:var(--on-dark-soft); line-height:1.9">DOKTER                    PETUGAS RAD              ORTHANC              SATUSEHAT
  │                           │                      │                     │
  ├─ Order Radiologi ────────▶│                      │                     │
  │  (RADNUM_NO generated)    │                      │                     │
  │                           ├─ Upload foto/PDF     │                     │
  │                           │                      │                     │
  │                   [kalau PACS aktif]              │                     │
  │                           │  Alat ──C-STORE────▶ │                     │
  │                           │                      ├─ Study + UID asli   │
  │                           ├─ Sinkron UID ◀───────┤                     │
  │                           │  (OrthancTrait)      │                     │
  │                           │  STUDY_UID tersimpan  │                     │
  │                           │                      │                     │
  ├─ Kirim SATUSEHAT ────────▶│                      │                     │
  │  (kartu 10)               │                      │                     │
  │                           ├──── ServiceRequest ──────────────────────▶ │
  │                           ├──── DiagnosticReport ────────────────────▶ │
  │                           ├──── ImagingStudy (soon) ─────────────────▶ │
  │                           │                      │                     │</pre>
                        </div>

                        {{-- Arsitektur --}}
                        <h2 class="ds-title-lg mb-3">Arsitektur Koneksi</h2>
                        <div class="ds-card-dark mb-8" style="padding:20px 24px; overflow-x:auto">
<pre class="ds-code" style="margin:0; color:var(--on-dark-soft); line-height:1.9">┌─────────────────┐     ┌──────────────┐     ┌──────────────────┐     ┌───────────────┐
│  Order Radiologi│────▶│   Orthanc    │────▶│     SIRUS        │────▶│   SATUSEHAT   │
│  (RADNUM_NO)    │     │  /tools/find │     │ ImagingStudyTrait│     │ POST          │
│  AccessionNumber│     │  StudyUID    │     │ UID asli/turunan │     │ /ImagingStudy │
└─────────────────┘     └──────────────┘     └──────────────────┘     └───────────────┘

Alat radiologi ──C-STORE──▶ Orthanc (port 4242)
                            │
SIRUS ──REST /tools/find───▶│ AccessionNumber = RADNUM_NO
      ◀── StudyInstanceUID──┘
      │
      ├── simpan ke STUDY_UID (kolom baru di tabel order)
      └── POST /ImagingStudy ke SATUSEHAT (pakai UID asli)</pre>
                        </div>

                        {{-- Tabel & kolom --}}
                        <h2 class="ds-title-lg mb-3">Tabel &amp; Kolom Terkait</h2>
                        <div class="ds-card-outline mb-8" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Tabel</th><th>PK</th><th>Pengikat DICOM</th><th>UID DICOM</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">RSTXN_RJRADS</td><td class="ds-body-sm"><span class="ds-code">RJ_NO, RAD_DTL</span></td><td class="ds-body-sm"><span class="ds-code">RADNUM_NO</span> VARCHAR2(15)</td><td class="ds-body-sm"><span class="ds-code">STUDY_UID</span> VARCHAR2(64) <strong class="text-emerald-600">&check;</strong></td></tr>
                                    <tr><td class="ds-td-strong">RSTXN_UGDRADS</td><td class="ds-body-sm"><span class="ds-code">RJ_NO, RAD_DTL</span></td><td class="ds-body-sm"><span class="ds-code">RADNUM_NO</span> VARCHAR2(15)</td><td class="ds-body-sm"><span class="ds-code">STUDY_UID</span> VARCHAR2(64) <strong class="text-emerald-600">&check;</strong></td></tr>
                                    <tr><td class="ds-td-strong">RSTXN_RIRADIOLOGS</td><td class="ds-body-sm"><span class="ds-code">RIHDR_NO, RIRAD_NO</span></td><td class="ds-body-sm"><span class="ds-code">RADNUM_NO</span> VARCHAR2(15)</td><td class="ds-body-sm"><span class="ds-code">STUDY_UID</span> VARCHAR2(64) <strong class="text-emerald-600">&check;</strong></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="ds-card-outline mb-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Penamaan tabel RI:</strong> <span class="ds-code">RSTXN_RIRADIOLOGS</span>, <strong>bukan</strong>
                                <span class="ds-code">rstxn_rirads</span>. RJ/UGD memakai pola <span class="ds-code">*rads</span> —
                                RI berbeda. Sudah pernah menyesatkan.
                            </span>
                        </div>

                        {{-- OrthancTrait --}}
                        <h2 class="ds-title-lg mb-3">OrthancTrait — Koneksi SIRUS &rarr; Orthanc</h2>
                        <p class="ds-body-md mb-3" style="max-width:62ch">
                            <span class="ds-code">App\Http\Traits\SATUSEHAT\OrthancTrait</span> — REST client
                            ke Orthanc via Basic Auth. Konfigurasi di <span class="ds-code">.env</span>:
                        </p>

                        <div class="ds-card-dark mb-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">.env — konfigurasi Orthanc</span>
                            </div>
<pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">ORTHANC_URL=http://localhost:8042
ORTHANC_USER=sirus
ORTHANC_PASSWORD=&lt;password&gt;</pre>
                        </div>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Method</th><th>Fungsi</th><th>Return</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong"><span class="ds-code">cariStudyUid($accNo)</span></td><td class="ds-body-sm">Query <span class="ds-code">/tools/find</span> by AccessionNumber &rarr; ambil StudyInstanceUID</td><td class="ds-body-sm"><span class="ds-code">string|null</span></td></tr>
                                    <tr><td class="ds-td-strong"><span class="ds-code">sinkronStudyUid($tabel, $where, $radnumNo)</span></td><td class="ds-body-sm">Cari UID + simpan ke kolom <span class="ds-code">STUDY_UID</span> per row</td><td class="ds-body-sm"><span class="ds-code">string|null</span></td></tr>
                                    <tr><td class="ds-td-strong"><span class="ds-code">sinkronStudyUidBatch($tabel, $pkRef, $pkDtl, $limit)</span></td><td class="ds-body-sm">Batch: semua row dengan <span class="ds-code">RADNUM_NO</span> &ne; null, <span class="ds-code">STUDY_UID</span> kosong</td><td class="ds-body-sm"><span class="ds-code">int</span> (jumlah synced)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-dark mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Contoh pemakaian OrthancTrait</span>
                            </div>
<pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">use App\Http\Traits\SATUSEHAT\OrthancTrait;

// Cari UID satu order:
$uid = $this->cariStudyUid('R00123');   // → '2.25.1041823759...' atau null

// Sinkron satu row RJ (cari + simpan ke STUDY_UID):
$this->sinkronStudyUid('rstxn_rjrads', ['rj_no' => $rjNo, 'rad_dtl' => $dtl], $radnumNo);

// Batch sinkron semua RJ yang belum punya STUDY_UID:
$count = $this->sinkronStudyUidBatch('rstxn_rjrads', 'rj_no', 'rad_dtl');
// → return jumlah row yang berhasil disinkronkan</pre>
                        </div>

                        {{-- ImagingStudyTrait --}}
                        <h2 class="ds-title-lg mb-3">ImagingStudyTrait — Kirim ke SATUSEHAT</h2>
                        <p class="ds-body-md mb-3" style="max-width:62ch">
                            <span class="ds-code">App\Http\Traits\SATUSEHAT\ImagingStudyTrait</span> — rakit payload
                            FHIR R4 &amp; <span class="ds-code">POST /ImagingStudy</span>. Sudah lolos uji staging.
                        </p>

                        <div class="ds-card-dark mb-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">ImagingStudyTrait::postImagingStudy()</span>
                            </div>
<pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ss-imaging'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-8">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Sumber UID</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><strong>STUDY_UID terisi</strong> &rarr; UID asli dari Orthanc (bisa ditelusuri ke gambar DICOM)</li>
                                    <li><strong>STUDY_UID kosong</strong> &rarr; <span class="ds-code">uidStudi()</span> — UID turunan arc <span class="ds-code">2.25</span> (sah bentuknya, tidak bisa ditelusuri)</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Hasil uji staging</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>ID: <span class="ds-code">ImagingStudy/16744a38-...</span></li>
                                    <li>Encounter UGD 203859, THORAX PA/AP</li>
                                    <li>SATUSEHAT tidak menuntut UID asli</li>
                                    <li><span class="ds-code">basedOn</span> (ServiceRequest) tidak wajib</li>
                                </ul>
                            </div>
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

                        {{-- NomorRadiologi --}}
                        <h2 class="ds-title-lg mb-3">RADNUM_NO &mdash; <span class="ds-code">NomorRadiologi</span></h2>
                        <div class="ds-card-outline mb-4" style="padding:16px 20px">
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Class:</strong> <span class="ds-code">App\Support\NomorRadiologi</span><br>
                                <strong>Method:</strong> <span class="ds-code">NomorRadiologi::generate()</span><br>
                                <strong>Format:</strong> <span class="ds-code">R-YYMMDD-NNNNN</span> (14 char, fit VARCHAR2(15))<br>
                                <strong>Contoh:</strong> <span class="ds-code">R-260824-00001</span>, <span class="ds-code">R-260824-00002</span>, &hellip;<br>
                                <strong>Sequence:</strong> per hari, UNION ALL dari 3 tabel radiologi (RJ + UGD + RI).<br>
                                <strong>Kapasitas:</strong> 99.999 nomor per hari.
                            </span>
                        </div>
                        <div class="ds-card-dark mb-4" style="padding:16px 20px; overflow-x:auto">
<pre class="ds-code" style="margin:0; color:var(--on-dark-soft); line-height:1.7">// Di setiap insert order radiologi:
use App\Support\NomorRadiologi;

DB::table('rstxn_rjrads')->insert([
    ...
    'radnum_no' => NomorRadiologi::generate(),
]);</pre>
                        </div>
                        <p class="ds-body-sm mb-6" style="color:var(--muted)">
                            Sudah di-wire di 4 file insert: <span class="ds-code">⚡rm-radiologi-rj-actions</span>,
                            <span class="ds-code">⚡rm-radiologi-ugd-actions</span>,
                            <span class="ds-code">⚡rm-radiologi-ri-actions</span>,
                            <span class="ds-code">⚡upload-radiologi-tambah-actions</span>.
                            Data historis (11.404 row RJ) belum terisi &mdash; akan terisi untuk order baru ke depan.
                        </p>

                        {{-- Langkah selanjutnya --}}
                        <h2 class="ds-title-lg mb-3">Langkah Selanjutnya</h2>
                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>1. <s>Generate RADNUM_NO</s></strong> <strong class="text-emerald-600">&check; Done</strong> &mdash; otomatis via <span class="ds-code">NomorRadiologi::generate()</span> di semua insert order.
                                <br><strong>2. <s>Wire ImagingStudy ke UI</s></strong> <strong class="text-emerald-600">&check; RJ + UGD + RI Done</strong> &mdash; auto upload foto ke Orthanc + kirim ImagingStudy.
                                <br><strong>3. Konfirmasi alat DICOM</strong> — tanya vendor apakah X-ray &amp; USG punya DICOM Store SCU + Modality Worklist SCU.
                                <br><strong>4. Production</strong> — pindah Orthanc ke VM Proxmox dedicated, disk terpisah &ge; 500 GB.
                            </span>
                        </div>

                        <div class="ds-card-outline mt-4 mb-4" style="padding:16px 20px">
                            <span class="ds-body-sm" style="color:var(--muted)">
                                Panduan lengkap instalasi Orthanc, konfigurasi DICOM, dan detail sambungan
                                &rarr; <span class="ds-code">docs/pacs-orthanc.md</span>
                            </span>
                        </div>
                    </section>
