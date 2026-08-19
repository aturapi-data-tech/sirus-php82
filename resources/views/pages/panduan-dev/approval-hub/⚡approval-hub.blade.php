<?php

use Livewire\Component;

// Dokumentasi Approval Hub — panduan arsitektur, alur kerja, dan referensi teknis
// untuk modul Casemix AI / E-Klaim Bridging / SATUSEHAT.
new class extends Component {
    public string $activeSection = 'overview';

    private const SECTIONS = [
        'overview', 'architecture', 'scan', 'ai-suggest',
        'approve-bridging', 'emr', 'selection', 'berkas', 'new-module',
    ];

    public function setSection(string $s): void
    {
        if (in_array($s, self::SECTIONS, true)) {
            $this->activeSection = $s;
        }
    }
};
?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />

    <div class="ds">
        <div class="ds-section">

            {{-- HERO --}}
            <header class="ds-band">
                <div class="flex items-center justify-between gap-2 mb-5">
                    <div class="flex items-center gap-2">
                        <span class="ds-spike"></span>
                        <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                        <span class="ds-body-sm" style="color:var(--muted-soft)">/ Panduan Dev / Approval Hub</span>
                    </div>
                    <x-theme-toggle />
                </div>

                <a href="{{ route('panduan-dev') }}" wire:navigate class="ds-btn ds-btn-secondary mb-6" style="display:inline-flex;align-items:center;gap:6px">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    Kembali ke Standarisasi UI
                </a>

                <div class="ds-eyebrow mb-4">Design System Internal &middot; Approval Hub</div>
                <h1 class="ds-display-xl">Approval Hub.</h1>
                <p class="ds-body-md mt-6" style="max-width:60ch; color:var(--body-strong)">
                    Dokumentasi lengkap arsitektur, alur kerja, dan referensi teknis untuk modul
                    <strong>Approval Hub</strong> &mdash; workflow universal
                    <strong>Scan &rarr; AI Suggest &rarr; Human Review &rarr; Execute</strong>.
                </p>

                <div class="ds-card-outline mt-6" style="padding:14px 18px">
                    <span class="ds-spike" style="vertical-align:middle"></span>
                    <span class="ds-body-sm" style="color:var(--body-strong)">
                        Halaman ini adalah referensi teknis &mdash; untuk panduan end-user,
                        lihat panel <strong>&ldquo;Panduan&rdquo;</strong> di halaman Approval Hub.
                    </span>
                </div>
            </header>

            {{-- NAV --}}
            <nav class="ds-band mt-8">
                <div class="flex flex-wrap gap-2">
                    @foreach ([
                        'overview' => 'Overview',
                        'architecture' => 'Arsitektur',
                        'scan' => 'Scan Transaksi',
                        'ai-suggest' => 'AI Suggest ICD',
                        'approve-bridging' => 'Approve & Bridging',
                        'emr' => 'EMR & SOAP',
                        'selection' => 'Row Selection',
                        'berkas' => 'Berkas BPJS',
                        'new-module' => 'Tambah Modul Baru',
                    ] as $key => $label)
                        <button type="button" wire:click="setSection('{{ $key }}')"
                            class="ds-btn {{ $activeSection === $key ? 'ds-btn-primary' : 'ds-btn-secondary' }}"
                            style="font-size:13px">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </nav>

            {{-- CONTENT --}}
            <div class="ds-band mt-8">

                {{-- ═══ OVERVIEW ═══ --}}
                @if ($activeSection === 'overview')
                <div class="space-y-8">
                    <div>
                        <h2 class="ds-title-lg">Overview</h2>
                        <p class="ds-body-md mt-4" style="color:var(--body-strong)">
                            Approval Hub adalah modul terpusat untuk semua workflow yang butuh
                            persetujuan manusia sebelum eksekusi. Pattern dasarnya sama untuk semua modul:
                        </p>
                    </div>

                    {{-- Flow diagram --}}
                    <div class="ds-card-outline" style="padding:24px">
                        <div class="ds-eyebrow mb-4">Alur Universal</div>
                        <div class="flex flex-wrap items-center gap-3 text-sm font-semibold" style="color:var(--body-strong)">
                            <span class="px-3 py-1.5 rounded-lg" style="background:var(--surface-raised)">1. Scan Transaksi</span>
                            <span style="color:var(--muted-soft)">&rarr;</span>
                            <span class="px-3 py-1.5 rounded-lg" style="background:var(--surface-raised)">2. AI Suggest</span>
                            <span style="color:var(--muted-soft)">&rarr;</span>
                            <span class="px-3 py-1.5 rounded-lg" style="background:var(--surface-raised)">3. Human Review</span>
                            <span style="color:var(--muted-soft)">&rarr;</span>
                            <span class="px-3 py-1.5 rounded-lg" style="background:var(--surface-raised)">4. Execute</span>
                        </div>
                    </div>

                    {{-- Tab list --}}
                    <div>
                        <h3 class="ds-title-sm mb-3">Tab Aktif (5 modul)</h3>
                        <div class="overflow-x-auto">
                            <table class="ds-table">
                                <thead>
                                    <tr>
                                        <th>Tab</th>
                                        <th>Modul DB</th>
                                        <th>Deskripsi</th>
                                        <th>Auto Bridging</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td><code class="ds-code">Casemix RJ</code></td><td>casemix / RJ</td><td>AI suggest ICD-10/9 + auto bridging E-Klaim 14 step</td><td>Ya</td></tr>
                                    <tr><td><code class="ds-code">Casemix UGD</code></td><td>casemix / UGD</td><td>Sama dengan RJ, tabel <code class="ds-code">rstxn_ugdhdrs</code></td><td>Ya</td></tr>
                                    <tr><td><code class="ds-code">Casemix RI</code></td><td>casemix / RI</td><td>AI suggest ICD, bridging manual via modal iDRG</td><td>Tidak (manual)</td></tr>
                                    <tr><td><code class="ds-code">Bundling Klaim</code></td><td>bundling</td><td>Bundling klaim BPJS</td><td>&mdash;</td></tr>
                                    <tr><td><code class="ds-code">SATUSEHAT RJ</code></td><td>satusehat</td><td>Integrasi FHIR SATUSEHAT</td><td>&mdash;</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Status flow --}}
                    <div>
                        <h3 class="ds-title-sm mb-3">Status Flow</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <div class="space-y-2 text-sm font-mono" style="color:var(--body-strong)">
                                <div>pending &rarr; <span style="color:var(--blue)">AI suggest (ai_model set)</span> &rarr; pending</div>
                                <div>pending &rarr; <span style="color:var(--green)">approved</span> &rarr; executing &rarr; <span style="color:var(--green)">executed</span> &#10003;</div>
                                <div>pending &rarr; approved &rarr; <span style="color:var(--red)">failed</span> (bridging error)</div>
                                <div>pending &rarr; <span style="color:var(--muted)">rejected</span></div>
                            </div>
                        </div>
                    </div>

                    {{-- Security --}}
                    <div class="ds-card-outline" style="padding:20px; border-color:var(--red)">
                        <div class="ds-eyebrow mb-2" style="color:var(--red)">Keamanan</div>
                        <ul class="ds-body-sm space-y-1" style="color:var(--body-strong)">
                            <li>&bull; Fitur AI: <strong>admin role only</strong></li>
                            <li>&bull; Kredensial RS: <strong>tidak boleh</strong> disimpan di memory/log</li>
                            <li>&bull; Coder NIK: dari <code class="ds-code">auth()->user()->emp_id</code></li>
                            <li>&bull; E-Klaim API: semua call terenkripsi (via iDrgTrait)</li>
                        </ul>
                    </div>
                </div>
                @endif

                {{-- ═══ ARCHITECTURE ═══ --}}
                @if ($activeSection === 'architecture')
                <div class="space-y-8">
                    <h2 class="ds-title-lg">Arsitektur & File Structure</h2>

                    {{-- File tree --}}
                    <div>
                        <h3 class="ds-title-sm mb-3">Struktur File</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="text-sm font-mono" style="color:var(--body-strong); line-height:1.8">resources/views/pages/transaksi/approval-hub/
├── ⚡approval-hub.blade.php          {{-- Parent wrapper — tab nav --}}
├── casemix-queue/
│   └── ⚡casemix-queue.blade.php     {{-- Parameterized: queue-type="RJ|UGD|RI" --}}
├── satusehat-queue/
│   └── ⚡satusehat-queue.blade.php   {{-- SATUSEHAT FHIR queue --}}
└── bundling-klaim/
    └── ⚡bundling-klaim.blade.php    {{-- Bundling klaim BPJS --}}</pre>
                        </div>
                    </div>

                    {{-- Parameterized pattern --}}
                    <div>
                        <h3 class="ds-title-sm mb-3">Parameterized Component Pattern</h3>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong)">
                            Casemix queue menggunakan <strong>1 komponen</strong> untuk 3 tipe (RJ/UGD/RI) via prop
                            <code class="ds-code">queueType</code>. Ini menghindari duplikasi ~2700 baris &times; 3.
                        </p>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="text-sm font-mono" style="color:var(--body-strong); line-height:1.8">{{-- Parent memanggil: --}}
&lt;livewire:pages::transaksi.approval-hub.casemix-queue.casemix-queue
    queue-type="RJ" wire:key="casemix-queue-rj" /&gt;

{{-- Component mount: --}}
public function mount(string $queueType = 'RJ'): void
{
    $this->queueType = strtoupper($queueType);
    $prefix = 'approval-casemix-' . strtolower($this->queueType) . '-';
    $this->filterStatus = session($prefix . 'filterStatus', 'pending');
}</pre>
                        </div>
                    </div>

                    {{-- Isolation rules --}}
                    <div>
                        <h3 class="ds-title-sm mb-3">Isolation Rules</h3>
                        <div class="overflow-x-auto">
                            <table class="ds-table">
                                <thead><tr><th>Aspek</th><th>Strategi</th><th>Contoh</th></tr></thead>
                                <tbody>
                                    <tr><td>Session keys</td><td>Prefix per-type</td><td><code class="ds-code">approval-casemix-rj-filterStatus</code></td></tr>
                                    <tr><td>Modal names</td><td>Append queueType</td><td><code class="ds-code">casemix-review-RJ</code></td></tr>
                                    <tr><td>DB queries</td><td>Filter ref_type</td><td><code class="ds-code">->where('ref_type', $this->queueType)</code></td></tr>
                                    <tr><td>wire:key</td><td>Unique per instance</td><td><code class="ds-code">wire:key="casemix-queue-rj"</code></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Traits --}}
                    <div>
                        <h3 class="ds-title-sm mb-3">Traits Yang Dipakai</h3>
                        <div class="overflow-x-auto">
                            <table class="ds-table">
                                <thead><tr><th>Trait</th><th>Methods</th></tr></thead>
                                <tbody>
                                    <tr><td><code class="ds-code">EmrRJTrait</code></td><td>findDataRJ(), lockRJRow(), updateJsonRJ(), calculateRJCosts()</td></tr>
                                    <tr><td><code class="ds-code">EmrUGDTrait</code></td><td>findDataUGD(), lockUGDRow(), updateJsonUGD(), calculateUGDCosts()</td></tr>
                                    <tr><td><code class="ds-code">EmrRITrait</code></td><td>findDataRI()</td></tr>
                                    <tr><td><code class="ds-code">MasterPasienTrait</code></td><td>findDataMasterPasien()</td></tr>
                                    <tr><td><code class="ds-code">iDrgTrait</code></td><td>14 static methods E-Klaim API</td></tr>
                                    <tr><td><code class="ds-code">EmrCompleteness*Trait</code></td><td>calculateEmrPercentRJ/RI/UGD()</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- DB table --}}
                    <div>
                        <h3 class="ds-title-sm mb-3">Tabel: rstxn_approval_queue</h3>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong)">
                            Tabel sentral &mdash; semua modul (casemix, satusehat, bundling) pakai tabel yang sama.
                        </p>
                        <div class="overflow-x-auto">
                            <table class="ds-table">
                                <thead><tr><th>Column</th><th>Type</th><th>Keterangan</th></tr></thead>
                                <tbody>
                                    <tr><td><code class="ds-code">approval_id</code></td><td>NUMBER (PK)</td><td>Auto-increment sequence</td></tr>
                                    <tr><td><code class="ds-code">module</code></td><td>VARCHAR2</td><td>'casemix', 'satusehat', 'bundling'</td></tr>
                                    <tr><td><code class="ds-code">ref_no</code></td><td>VARCHAR2</td><td>rj_no / rihdr_no</td></tr>
                                    <tr><td><code class="ds-code">ref_type</code></td><td>VARCHAR2</td><td>'RJ', 'UGD', 'RI'</td></tr>
                                    <tr><td><code class="ds-code">reg_no</code></td><td>VARCHAR2</td><td>Nomor RM</td></tr>
                                    <tr><td><code class="ds-code">reg_name</code></td><td>VARCHAR2(100)</td><td>Nama pasien</td></tr>
                                    <tr><td><code class="ds-code">vno_sep</code></td><td>VARCHAR2(50)</td><td>Nomor SEP</td></tr>
                                    <tr><td><code class="ds-code">ai_payload</code></td><td>CLOB (JSON)</td><td>diagnosa, prosedur, soap_text, emr_percent, emr_sections</td></tr>
                                    <tr><td><code class="ds-code">ai_confidence</code></td><td>NUMBER</td><td>0&ndash;100</td></tr>
                                    <tr><td><code class="ds-code">status</code></td><td>VARCHAR2</td><td>pending / approved / executed / failed / rejected</td></tr>
                                    <tr><td><code class="ds-code">human_payload</code></td><td>CLOB (JSON)</td><td>Hasil review manusia: {diagnosa, prosedur}</td></tr>
                                    <tr><td><code class="ds-code">reviewer</code></td><td>VARCHAR2</td><td>Username reviewer</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                {{-- ═══ SCAN ═══ --}}
                @if ($activeSection === 'scan')
                <div class="space-y-8">
                    <h2 class="ds-title-lg">Scan Transaksi</h2>

                    <div>
                        <h3 class="ds-title-sm mb-3">Alur Scan</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <ol class="ds-body-sm space-y-2" style="color:var(--body-strong)">
                                <li><strong>1.</strong> Delete existing queue rows untuk module + ref_type ini</li>
                                <li><strong>2.</strong> Query transaksi BPJS yang punya SEP: <code class="ds-code">REGEXP_LIKE(h.vno_sep, '^\d{4}')</code></li>
                                <li><strong>3.</strong> Per row: read EMR &rarr; extract SOAP text &rarr; hitung EMR completeness &rarr; extract existing diagnosa &amp; bridging &rarr; insert ke queue</li>
                            </ol>
                        </div>
                    </div>

                    <div>
                        <h3 class="ds-title-sm mb-3">Tabel Source per Type</h3>
                        <div class="overflow-x-auto">
                            <table class="ds-table">
                                <thead><tr><th>Type</th><th>Tabel</th><th>Column No</th><th>Column Tanggal</th><th>JSON Column</th></tr></thead>
                                <tbody>
                                    <tr><td>RJ</td><td><code class="ds-code">rstxn_rjhdrs</code></td><td>rj_no</td><td>rj_date</td><td>datadaftarpolirj_json</td></tr>
                                    <tr><td>UGD</td><td><code class="ds-code">rstxn_ugdhdrs</code></td><td>rj_no</td><td>rj_date</td><td>datadaftarugd_json</td></tr>
                                    <tr><td>RI</td><td><code class="ds-code">rstxn_rihdrs</code></td><td>rihdr_no</td><td>entry_date</td><td>&mdash; (via trait)</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h3 class="ds-title-sm mb-3">insertScanRow() &mdash; Shared Helper</h3>
                        <p class="ds-body-sm mb-3" style="color:var(--body-strong)">
                            Satu method shared untuk semua tipe. Menerima EMR array dan metadata transaksi:
                        </p>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="text-sm font-mono" style="color:var(--body-strong); line-height:1.8">$soapText    = extractSoapText($emr, $refType);  // S: O: A: P:
$existingDx  = extractExistingDiagnosis($emr);    // dari EMR diagnosis[]
$bridging    = extractIdrgBridging($emr);          // dari idrg.coderDiagnosa
$emrPct      = calculateEmrPercent($emr, $refType); // per-section %

ai_payload = {
  diagnosa, prosedur, soap_text, has_bridging,
  emr_percent, emr_sections, tgl_kunjungan
}</pre>
                        </div>
                    </div>

                    <div>
                        <h3 class="ds-title-sm mb-3">Mode Scan</h3>
                        <div class="overflow-x-auto">
                            <table class="ds-table">
                                <thead><tr><th>Mode</th><th>Input</th><th>Range</th></tr></thead>
                                <tbody>
                                    <tr><td>Bulanan</td><td><code class="ds-code">mm/yyyy</code></td><td>Awal &ndash; akhir bulan</td></tr>
                                    <tr><td>Harian</td><td><code class="ds-code">dd/mm/yyyy</code></td><td>Satu hari</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                {{-- ═══ AI SUGGEST ═══ --}}
                @if ($activeSection === 'ai-suggest')
                <div class="space-y-8">
                    <h2 class="ds-title-lg">AI Suggest ICD</h2>

                    <div>
                        <h3 class="ds-title-sm mb-3">Alur AI Suggest</h3>
                        <div class="ds-card-outline" style="padding:24px">
                            <div class="flex flex-wrap items-center gap-3 text-sm font-semibold" style="color:var(--body-strong)">
                                <span class="px-3 py-1.5 rounded-lg" style="background:var(--surface-raised)">SOAP Text</span>
                                <span style="color:var(--muted-soft)">&rarr;</span>
                                <span class="px-3 py-1.5 rounded-lg" style="background:var(--surface-raised)">AI API Call</span>
                                <span style="color:var(--muted-soft)">&rarr;</span>
                                <span class="px-3 py-1.5 rounded-lg" style="background:var(--surface-raised)">Parse JSON</span>
                                <span style="color:var(--muted-soft)">&rarr;</span>
                                <span class="px-3 py-1.5 rounded-lg" style="background:var(--surface-raised)">Validate vs DB</span>
                                <span style="color:var(--muted-soft)">&rarr;</span>
                                <span class="px-3 py-1.5 rounded-lg" style="background:var(--surface-raised)">Save</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="ds-title-sm mb-3">AI Prompt Rules</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <ul class="ds-body-sm space-y-1.5" style="color:var(--body-strong)">
                                <li>&bull; Diagnosa pertama = <strong>Primary</strong> (paling resource-intensive)</li>
                                <li>&bull; Kode harus spesifik (subkategori, bukan kode induk)</li>
                                <li>&bull; Prosedur hanya jika ada tindakan medis (operasi, invasif)</li>
                                <li>&bull; Konsultasi rutin tanpa tindakan: prosedur <strong>kosong</strong></li>
                                <li>&bull; Prioritaskan kode dari <strong>bridging sebelumnya</strong> jika klinis sesuai</li>
                                <li>&bull; Setiap entry wajib punya <code class="ds-code">"reason"</code></li>
                                <li>&bull; Confidence 0&ndash;100</li>
                            </ul>
                        </div>
                    </div>

                    <div>
                        <h3 class="ds-title-sm mb-3">Validasi Post-AI</h3>
                        <div class="overflow-x-auto">
                            <table class="ds-table">
                                <thead><tr><th>Jenis</th><th>Tabel Master</th><th>Fallback</th></tr></thead>
                                <tbody>
                                    <tr><td>Diagnosa (ICD-10)</td><td><code class="ds-code">rsmst_mstdiags</code></td><td>Parent code jika subkategori tidak ada</td></tr>
                                    <tr><td>Prosedur (ICD-9-CM)</td><td><code class="ds-code">rsmst_mstprocedures</code></td><td>Reject jika tidak ditemukan</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="ds-body-sm mt-3" style="color:var(--muted)">
                            Kode yang ditolak masuk ke <code class="ds-code">ai_notes</code> sebagai warning.
                        </p>
                    </div>

                    <div>
                        <h3 class="ds-title-sm mb-3">Batch vs Selection</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <ul class="ds-body-sm space-y-1.5" style="color:var(--body-strong)">
                                <li>&bull; <strong>Ada selectedIds</strong> &rarr; proses semua yang dipilih (tanpa limit)</li>
                                <li>&bull; <strong>Tidak ada seleksi</strong> &rarr; batch 5 item pending per klik</li>
                                <li>&bull; Filter: <code class="ds-code">status = 'pending' AND ai_model IS NULL</code></li>
                            </ul>
                        </div>
                    </div>

                    <div>
                        <h3 class="ds-title-sm mb-3">AI Response Format</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="text-sm font-mono" style="color:var(--body-strong); line-height:1.6">{
  "diagnosa": [
    {"code": "E11.9", "desc": "...", "kategori": "Primary",
     "confidence": 90, "reason": "GDA 186 + terapi Glimepirid"}
  ],
  "prosedur": [
    {"code": "88.72", "desc": "...", "confidence": 80,
     "reason": "Echo dilakukan"}
  ],
  "confidence": 85,
  "notes": "DM tipe 2 Primary karena resource-intensive..."
}</pre>
                        </div>
                    </div>
                </div>
                @endif

                {{-- ═══ APPROVE & BRIDGING ═══ --}}
                @if ($activeSection === 'approve-bridging')
                <div class="space-y-8">
                    <h2 class="ds-title-lg">Approve &amp; E-Klaim Bridging</h2>

                    <div>
                        <h3 class="ds-title-sm mb-3">Alur Approve</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <ol class="ds-body-sm space-y-2" style="color:var(--body-strong)">
                                <li><strong>1.</strong> Update status &rarr; <code class="ds-code">approved</code>, save <code class="ds-code">human_payload</code></li>
                                <li><strong>2.</strong> <strong>RJ/UGD:</strong> syncDiagnosaToEmr() &mdash; insert diagnosa baru ke <code class="ds-code">rstxn_rjdtls</code> + update EMR JSON</li>
                                <li><strong>3.</strong> <strong>RJ/UGD:</strong> executeBridging() &mdash; 14-step E-Klaim pipeline otomatis</li>
                                <li><strong>4.</strong> <strong>RI:</strong> dispatch event <code class="ds-code">daftar-ri.idrg.open</code> (bridging manual)</li>
                            </ol>
                        </div>
                    </div>

                    {{-- 14 Steps --}}
                    <div>
                        <h3 class="ds-title-sm mb-3">14-Step E-Klaim Bridging Pipeline</h3>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong)">
                            <code class="ds-code">executeBridging($rjNo, $refType)</code> &mdash; otomatis untuk RJ dan UGD.
                            Setiap step: panggil API &rarr; cek <code class="ds-code">metadata.code == 200</code> &rarr;
                            simpan progress via <code class="ds-code">saveBridgingResult()</code>.
                        </p>
                        <div class="overflow-x-auto">
                            <table class="ds-table">
                                <thead><tr><th>#</th><th>Method</th><th>Catatan</th></tr></thead>
                                <tbody>
                                    <tr><td>1</td><td><code class="ds-code">newClaim()</code></td><td>Skip jika idrg.nomorSep sudah ada</td></tr>
                                    <tr><td>2</td><td><code class="ds-code">setDiagnosaIdrg()</code></td><td>Kode diagnosa dipisah <code class="ds-code">#</code>: I10#J18.9</td></tr>
                                    <tr><td>3</td><td><code class="ds-code">setProsedurIdrg()</code></td><td><code class="ds-code">#</code> jika kosong</td></tr>
                                    <tr><td>4</td><td><code class="ds-code">setClaimData()</code></td><td>Auto tarif dari kasir, cara_masuk: gp (RJ) / er (UGD)</td></tr>
                                    <tr><td>5</td><td><code class="ds-code">grouperIdrgStage1()</code></td><td>MDC 36 = ungroupable &rarr; stop</td></tr>
                                    <tr><td>6</td><td><code class="ds-code">grouperIdrgStage2()</code></td><td>Hanya jika ada topup_options</td></tr>
                                    <tr><td>7</td><td><code class="ds-code">finalIdrg()</code></td><td>&mdash;</td></tr>
                                    <tr><td>8</td><td><code class="ds-code">importIdrgToInacbg()</code></td><td>&mdash;</td></tr>
                                    <tr><td>9</td><td><code class="ds-code">setDiagnosaInacbg()</code></td><td>Sama string dengan step 2</td></tr>
                                    <tr><td>10</td><td><code class="ds-code">setProsedurInacbg()</code></td><td>Sama string dengan step 3</td></tr>
                                    <tr><td>11</td><td><code class="ds-code">grouperInacbgStage1()</code></td><td>&mdash;</td></tr>
                                    <tr><td>12</td><td><code class="ds-code">grouperInacbgStage2()</code></td><td>Hanya jika ada special_cmg</td></tr>
                                    <tr><td>13</td><td><code class="ds-code">finalInacbg()</code></td><td>Skip jika INACBG ungroupable</td></tr>
                                    <tr><td>14</td><td><code class="ds-code">finalClaim()</code></td><td>Coder NIK dari auth()->user()->emp_id</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Claim Data Payload --}}
                    <div>
                        <h3 class="ds-title-sm mb-3">buildClaimDataPayload() &mdash; Tarif Mapping</h3>
                        <div class="overflow-x-auto">
                            <table class="ds-table">
                                <thead><tr><th>Field E-Klaim</th><th>Source (kasir)</th></tr></thead>
                                <tbody>
                                    <tr><td>prosedur_non_bedah</td><td><code class="ds-code">$cost['actePrice']</code></td></tr>
                                    <tr><td>konsultasi</td><td>poliPrice + rsAdmin + rjAdmin</td></tr>
                                    <tr><td>tenaga_ahli</td><td><code class="ds-code">$cost['actdPrice']</code></td></tr>
                                    <tr><td>penunjang</td><td>actpPrice + other</td></tr>
                                    <tr><td>radiologi</td><td><code class="ds-code">$cost['rad']</code></td></tr>
                                    <tr><td>laboratorium</td><td><code class="ds-code">$cost['lab']</code></td></tr>
                                    <tr><td>obat</td><td><code class="ds-code">$cost['obat']</code></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="ds-card-outline mt-4" style="padding:16px">
                            <div class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Perbedaan RJ vs UGD:</strong>
                                <code class="ds-code">cara_masuk</code>: gp (RJ) / er (UGD) &bull;
                                <code class="ds-code">jenis_rawat</code>: 2 (RJ) / 1 (UGD)
                            </div>
                        </div>
                    </div>

                    {{-- Diagnosa string --}}
                    <div>
                        <h3 class="ds-title-sm mb-3">Format String Diagnosa/Prosedur</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="text-sm font-mono" style="color:var(--body-strong); line-height:1.8">// Kode dipisah '#', Primary didahulukan
buildDiagnosaString() → "I10#J18.9#E11.9"  (Primary#Sec#Sec)
buildProsedurString() → "88.72#93.39"      (semua prosedur)
// Kosong → '#' (bukan empty string)</pre>
                        </div>
                    </div>

                    {{-- saveBridgingResult --}}
                    <div>
                        <h3 class="ds-title-sm mb-3">saveBridgingResult() &mdash; Progress Save</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="text-sm font-mono" style="color:var(--body-strong); line-height:1.8">DB::transaction(function () {
    $this->lockRJRow($rjNo);     // atau lockUGDRow
    $fresh = $this->findDataRJ($rjNo);
    $fresh['idrg'] = $idrg;      // semua progress tersimpan
    $this->updateJsonRJ($rjNo, $fresh);
});
// Data idrg TIDAK pernah menimpa EMR asli
// — hanya menulis ke namespace idrg.* dalam JSON</pre>
                        </div>
                    </div>
                </div>
                @endif

                {{-- ═══ EMR & SOAP ═══ --}}
                @if ($activeSection === 'emr')
                <div class="space-y-8">
                    <h2 class="ds-title-lg">EMR &amp; SOAP Extraction</h2>

                    <div>
                        <h3 class="ds-title-sm mb-3">SOAP Text Extraction per Type</h3>
                        <div class="overflow-x-auto">
                            <table class="ds-table">
                                <thead><tr><th>Section</th><th>RJ / UGD</th><th>RI</th></tr></thead>
                                <tbody>
                                    <tr><td><strong>S</strong></td><td>anamnesa.keluhanUtama + riwayatPenyakitSekarangUmum</td><td>CPPT latest → soap.subjective<br>Fallback: pengkajianDokter.anamnesa</td></tr>
                                    <tr><td><strong>O</strong></td><td>pemeriksaan.tandaVital (TD,N,RR,S,SpO2,GDA) + fisik + penunjang</td><td>CPPT → soap.objective<br>Fallback: pengkajianDokter.fisik</td></tr>
                                    <tr><td><strong>A</strong></td><td>diagnosisFreeText + diagnosis[].icdX</td><td>CPPT → soap.assessment<br>+ pengkajianDokter.diagnosaAwal</td></tr>
                                    <tr><td><strong>P</strong></td><td>perencanaan.terapi + tindakLanjut</td><td>CPPT → soap.plan<br>Fallback: pengkajianDokter.rencana</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h3 class="ds-title-sm mb-3">EMR Completeness</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <ul class="ds-body-sm space-y-1.5" style="color:var(--body-strong)">
                                <li>&bull; <strong>&ge; 80%</strong> dianggap lengkap (threshold)</li>
                                <li>&bull; Dihitung per-section: S, O, A, P, N, T (UGD triase), K (SNOMED), C (CPPT RI)</li>
                                <li>&bull; Return: <code class="ds-code">['emr' => int, 'sections' => ['s' => int, 'o' => int, ...]]</code></li>
                            </ul>
                        </div>
                    </div>

                    <div>
                        <h3 class="ds-title-sm mb-3">Section Labels per Type</h3>
                        <div class="overflow-x-auto">
                            <table class="ds-table">
                                <thead><tr><th>Key</th><th>RJ</th><th>UGD</th><th>RI</th></tr></thead>
                                <tbody>
                                    <tr><td>s</td><td>Anamnesa</td><td>Anamnesa</td><td>Pengkajian</td></tr>
                                    <tr><td>o</td><td>Pemeriksaan</td><td>Pemeriksaan</td><td>TTV</td></tr>
                                    <tr><td>a</td><td>Diagnosa</td><td>Diagnosa</td><td>Diagnosa</td></tr>
                                    <tr><td>p</td><td>Perencanaan</td><td>Perencanaan</td><td>Rencana</td></tr>
                                    <tr><td>n</td><td>Penilaian</td><td>Penilaian</td><td>Penilaian</td></tr>
                                    <tr><td>t</td><td>&mdash;</td><td>Triase</td><td>&mdash;</td></tr>
                                    <tr><td>k</td><td>SNOMED</td><td>SNOMED</td><td>Askep</td></tr>
                                    <tr><td>c</td><td>&mdash;</td><td>&mdash;</td><td>CPPT</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h3 class="ds-title-sm mb-3">syncDiagnosaToEmr()</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="text-sm font-mono" style="color:var(--body-strong); line-height:1.8">// Dipanggil saat approve (RJ/UGD only)
1. Baca JSON dari datadaftarpolirj_json / datadaftarugd_json
2. Filter diagnosa baru (belum ada di diagnosis[].diagId)
3. Validasi kode vs rsmst_mstdiags
4. Insert ke rstxn_rjdtls (detail transaksi)
5. Append ke diagnosis[] array di JSON
6. Update JSON + set rj_diagnosa = 'D' (RJ only)</pre>
                        </div>
                    </div>
                </div>
                @endif

                {{-- ═══ SELECTION ═══ --}}
                @if ($activeSection === 'selection')
                <div class="space-y-8">
                    <h2 class="ds-title-lg">Row Selection (x-toggle)</h2>

                    <div>
                        <h3 class="ds-title-sm mb-3">Pattern</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="text-sm font-mono" style="color:var(--body-strong); line-height:1.8">// PHP property + methods
public array $selectedIds = [];

public function toggleSelect(int $id): void
{
    if (in_array($id, $this->selectedIds)) {
        $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));
    } else {
        $this->selectedIds[] = $id;
    }
}

public function toggleSelectAll(): void
{
    $pageIds = $this->rows->pluck('approval_id')->toArray();
    $allSelected = !array_diff($pageIds, $this->selectedIds);
    if ($allSelected) {
        $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
    } else {
        $this->selectedIds = array_values(
            array_unique(array_merge($this->selectedIds, $pageIds))
        );
    }
}</pre>
                        </div>
                    </div>

                    <div>
                        <h3 class="ds-title-sm mb-3">Blade Template</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="text-sm font-mono" style="color:var(--body-strong); line-height:1.6">{{-- Header toggle (select all) --}}
&lt;x-toggle
    :current="count($selectedIds) > 0 &&
        !array_diff($this->rows->pluck('approval_id')->toArray(),
            $selectedIds) ? '1' : '0'"
    trueValue="1" falseValue="0"
    wireClick="toggleSelectAll" /&gt;

{{-- Per-row toggle --}}
&lt;x-toggle
    :current="in_array($row->approval_id, $selectedIds) ? '1' : '0'"
    trueValue="1" falseValue="0"
    wireClick="toggleSelect(&#123;&#123; $row->approval_id &#125;&#125;)" /&gt;

{{-- Badge di toolbar --}}
@@if (!empty($selectedIds))
    &lt;span class="text-xs font-semibold ..."&gt;
        &#123;&#123; count($selectedIds) &#125;&#125; dipilih
    &lt;/span&gt;
@@endif</pre>
                        </div>
                    </div>

                    <div>
                        <h3 class="ds-title-sm mb-3">Reset Timing</h3>
                        <div class="ds-card-outline" style="padding:16px">
                            <ul class="ds-body-sm space-y-1" style="color:var(--body-strong)">
                                <li>&bull; Saat <strong>scan</strong> transaksi</li>
                                <li>&bull; Saat <strong>clear queue</strong></li>
                                <li>&bull; <em>Opsional:</em> saat pindah halaman (pagination)</li>
                            </ul>
                        </div>
                    </div>
                </div>
                @endif

                {{-- ═══ BERKAS ═══ --}}
                @if ($activeSection === 'berkas')
                <div class="space-y-8">
                    <h2 class="ds-title-lg">Upload Berkas BPJS</h2>

                    <div>
                        <h3 class="ds-title-sm mb-3">Slot Numbering</h3>
                        <div class="overflow-x-auto">
                            <table class="ds-table">
                                <thead><tr><th>Slot</th><th>Berkas</th><th>Catatan</th></tr></thead>
                                <tbody>
                                    <tr><td>1</td><td>SEP</td><td>Generated PDF</td></tr>
                                    <tr><td>2</td><td>Grouping</td><td>Hanya setelah klaim final</td></tr>
                                    <tr><td>3</td><td>Rekam Medis</td><td>Dari EMR</td></tr>
                                    <tr><td>4</td><td>SKDP</td><td>Surat kontrol</td></tr>
                                    <tr><td>5</td><td>Lain-lain</td><td>&mdash;</td></tr>
                                    <tr><td>100+</td><td>Lab results</td><td>SLOT_LAB_OFFSET = 100</td></tr>
                                    <tr><td>200+</td><td>Radiologi results</td><td>SLOT_RAD_OFFSET = 200</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h3 class="ds-title-sm mb-3">Alur Upload</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="text-sm font-mono" style="color:var(--body-strong); line-height:1.8">autoUploadBerkas()
  → generateAllBerkas()
    → generateBerkasSep()       // slot 1
    → generateBerkasGrouping()  // slot 2
    → generateBerkasSkdp()      // slot 4
    → generateBerkasLab()       // slot 100+
    → generateBerkasRadiologi() // slot 200+
  → saveBerkasBpjs()            // insert/update DB
  → syncBerkasToMount()         // copy ke mount dir</pre>
                        </div>
                    </div>
                </div>
                @endif

                {{-- ═══ NEW MODULE ═══ --}}
                @if ($activeSection === 'new-module')
                <div class="space-y-8">
                    <h2 class="ds-title-lg">Menambah Modul Approval Baru</h2>

                    <div>
                        <h3 class="ds-title-sm mb-3">Checklist</h3>
                        <div class="ds-card-outline" style="padding:24px">
                            <ol class="ds-body-sm space-y-3" style="color:var(--body-strong)">
                                <li>
                                    <strong>1. Buat komponen Blade</strong><br>
                                    <code class="ds-code">approval-hub/&lt;module&gt;/⚡&lt;module&gt;.blade.php</code>
                                </li>
                                <li>
                                    <strong>2. Tambah tab di parent</strong><br>
                                    Edit <code class="ds-code">⚡approval-hub.blade.php</code>: tambah ke <code class="ds-code">TABS</code> const + <code class="ds-code">&lt;x-tab&gt;</code> + <code class="ds-code">@@if</code> content
                                </li>
                                <li>
                                    <strong>3. Scan transaksi</strong><br>
                                    Query sumber data &rarr; insert ke <code class="ds-code">rstxn_approval_queue</code> dengan <code class="ds-code">module</code> unik
                                </li>
                                <li>
                                    <strong>4. Review modal</strong><br>
                                    Baca dari queue, tampilkan di <code class="ds-code">&lt;x-modal&gt;</code> full-screen
                                </li>
                                <li>
                                    <strong>5. Execute on approve</strong><br>
                                    Jalankan API/bridging saat approve, update status accordingly
                                </li>
                                <li>
                                    <strong>6. Row selection</strong><br>
                                    Pakai <code class="ds-code">x-toggle</code> pattern (lihat section &ldquo;Row Selection&rdquo;)
                                </li>
                                <li>
                                    <strong>7. Session isolation</strong><br>
                                    Prefix session keys dengan nama module
                                </li>
                            </ol>
                        </div>
                    </div>

                    <div>
                        <h3 class="ds-title-sm mb-3">Template Minimal Komponen Baru</h3>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="text-sm font-mono" style="color:var(--body-strong); line-height:1.6">&lt;?php
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public string $filterStatus = 'pending';
    public array $selectedIds = [];

    #[Computed]
    public function rows()
    {
        return DB::table('rstxn_approval_queue')
            ->where('module', 'nama-module')
            ->when($this->filterStatus, fn($q) =>
                $q->where('status', $this->filterStatus))
            ->orderByDesc('created_at')
            ->paginate(25);
    }

    public function toggleSelect(int $id): void { /* ... */ }
    public function toggleSelectAll(): void { /* ... */ }

    public function scanTransaksi(): void
    {
        $this->selectedIds = [];
        DB::table('rstxn_approval_queue')
            ->where('module', 'nama-module')->delete();
        // ... query sumber + insert rows
    }

    public function approve(): void
    {
        // 1. Update status
        // 2. Execute (API/bridging)
        // 3. Update result status
    }
};
?&gt;</pre>
                        </div>
                    </div>
                </div>
                @endif

            </div>

        </div>
    </div>
</div>
