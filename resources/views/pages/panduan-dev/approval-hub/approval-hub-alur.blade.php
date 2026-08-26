                    {{-- ====== SCAN TRANSAKSI ====== --}}
                    <section x-show="section === 'scan'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Alur Kerja</div>
                        <h1 class="ds-display-md mb-4">Scan Transaksi</h1>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong); max-width:62ch">
                            Langkah pertama: ambil semua transaksi BPJS (yang punya SEP) dari periode tertentu,
                            baca EMR-nya, lalu masukkan ke antrian approval.
                        </p>

                        {{-- Alur scan --}}
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Alur scan</div>
                            <div class="space-y-3">
                                @foreach ([
                                    ['1', 'Delete existing queue', 'Hapus antrian lama untuk module + ref_type ini'],
                                    ['2', 'Query transaksi BPJS', 'Filter yang punya SEP: REGEXP_LIKE(h.vno_sep, \'^\d{4}\')'],
                                    ['3', 'Per row: baca EMR', 'extractSoapText() → calculateEmrPercent() → extractExistingDiagnosis() → extractIdrgBridging()'],
                                    ['4', 'Insert ke queue', 'insertScanRow() → rstxn_approval_queue dengan status \'pending\''],
                                ] as [$no, $judul, $desc])
                                    <div class="flex items-start gap-3">
                                        <div class="flex items-center justify-center w-7 h-7 rounded-full shrink-0"
                                            style="background:var(--primary); color:white; font-size:12px; font-weight:700">{{ $no }}</div>
                                        <div>
                                            <div class="ds-title-sm" style="color:var(--ink)">{{ $judul }}</div>
                                            <div class="ds-body-sm" style="color:var(--muted)">{{ $desc }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Source tables --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Tabel Source per Type</h2>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Type</th><th>Tabel</th><th>Column No</th><th>Column Tanggal</th><th>JSON Column</th></tr></thead>
                                    <tbody>
                                        <tr><td>RJ</td><td><span class="ds-code">rstxn_rjhdrs</span></td><td>rj_no</td><td>rj_date</td><td>datadaftarpolirj_json</td></tr>
                                        <tr><td>UGD</td><td><span class="ds-code">rstxn_ugdhdrs</span></td><td>rj_no</td><td>rj_date</td><td>datadaftarugd_json</td></tr>
                                        <tr><td>RI</td><td><span class="ds-code">rstxn_rihdrs</span></td><td>rihdr_no</td><td>entry_date</td><td>— (via trait)</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- insertScanRow --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">insertScanRow() — Shared Helper</h2>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <pre class="ds-body-sm" style="color:var(--body-strong); line-height:1.8; font-family:'JetBrains Mono',monospace">ai_payload = {
    diagnosa,        // dari bridging sebelumnya atau EMR
    prosedur,        // dari bridging sebelumnya
    soap_text,       // S: O: A: P: (max 4000 char)
    has_bridging,    // bool — ada kode dari E-Klaim sebelumnya?
    emr_percent,     // 0–100 overall
    emr_sections,    // {s: 85, o: 100, a: 70, ...}
    tgl_kunjungan    // display format dd/mm/yyyy hh24:mi
}</pre>
                        </div>

                        {{-- Mode scan --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Mode Scan</h2>
                        <div class="ds-card-outline" style="padding:20px">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Mode</th><th>Input</th><th>Range</th></tr></thead>
                                    <tbody>
                                        <tr><td>Bulanan</td><td><span class="ds-code">mm/yyyy</span></td><td>Awal – akhir bulan</td></tr>
                                        <tr><td>Harian</td><td><span class="ds-code">dd/mm/yyyy</span></td><td>Satu hari</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>


                    {{-- ====== AI SUGGEST ICD ====== --}}
                    <section x-show="section === 'ai-suggest'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Alur Kerja</div>
                        <h1 class="ds-display-md mb-4">AI Suggest ICD</h1>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong); max-width:62ch">
                            AI membaca teks SOAP dari EMR dan menyarankan kode ICD-10 (diagnosa)
                            dan ICD-9-CM (prosedur) untuk klaim BPJS/INA-CBG.
                        </p>

                        {{-- Alur --}}
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-4" style="color:var(--muted)">Pipeline AI suggest</div>
                            <div class="flex flex-wrap items-stretch gap-2">
                                @foreach ([
                                    ['SOAP Text', 'Dari EMR'],
                                    ['AI API Call', 'OpenAI-compatible'],
                                    ['Parse JSON', 'diagnosa + prosedur'],
                                    ['Validate vs DB', 'rsmst_mstdiags'],
                                    ['Save', 'ke ai_payload'],
                                ] as [$judul, $sub])
                                    <div class="flex-1 min-w-[120px] text-center rounded-xl p-3"
                                        style="background:var(--surface-card)">
                                        <div class="ds-title-sm" style="color:var(--ink)">{{ $judul }}</div>
                                        <div class="ds-caption" style="color:var(--muted)">{{ $sub }}</div>
                                    </div>
                                    @if (!$loop->last)
                                        <div class="self-center" style="color:var(--muted-soft)">→</div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        {{-- Prompt rules --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">AI Prompt Rules</h2>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ul class="ds-body-sm space-y-1.5" style="color:var(--body-strong)">
                                <li>• Diagnosa pertama = <strong>Primary</strong> (paling resource-intensive)</li>
                                <li>• Kode harus spesifik (subkategori, bukan kode induk)</li>
                                <li>• Prosedur hanya jika ada tindakan medis (operasi, invasif)</li>
                                <li>• Konsultasi rutin tanpa tindakan: prosedur <strong>kosong</strong></li>
                                <li>• Prioritaskan kode dari <strong>bridging sebelumnya</strong> jika klinis sesuai</li>
                                <li>• Setiap entry wajib punya <span class="ds-code">"reason"</span></li>
                                <li>• Confidence 0–100</li>
                            </ul>
                        </div>

                        {{-- Validation --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Validasi Post-AI</h2>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Jenis</th><th>Tabel Master</th><th>Fallback</th></tr></thead>
                                    <tbody>
                                        <tr><td>Diagnosa (ICD-10)</td><td><span class="ds-code">rsmst_mstdiags</span></td><td>Parent code jika subkategori tidak ada</td></tr>
                                        <tr><td>Prosedur (ICD-9-CM)</td><td><span class="ds-code">rsmst_mstprocedures</span></td><td>Reject jika tidak ditemukan</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="ds-caption mt-3" style="color:var(--muted)">
                                Kode yang ditolak masuk ke <span class="ds-code">ai_notes</span> sebagai warning.
                            </p>
                        </div>

                        {{-- Continuous mode --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Continuous Mode</h2>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ul class="ds-body-sm space-y-1.5" style="color:var(--body-strong)">
                                <li>• Klik <strong>Run AI</strong> → proses otomatis per 5 item, lanjut terus sampai semua selesai</li>
                                <li>• Klik <strong>Stop AI</strong> untuk menghentikan kapan saja</li>
                                <li>• Async per batch via <span class="ds-code">$this->js('setTimeout(() => $wire.aiProcessNextBatch(), 300)')</span></li>
                                <li>• <strong>Ada selectedIds</strong> → hanya proses yang dipilih</li>
                                <li>• Filter: <span class="ds-code">status = 'pending' AND ai_model IS NULL</span></li>
                                <li>• Item yang SOAP-nya terlalu pendek atau AI gagal ditandai <span class="ds-code">ai_model = 'skipped'</span> agar tidak loop</li>
                            </ul>
                        </div>

                        {{-- AI response --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">AI Response Format</h2>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="ds-body-sm" style="color:var(--body-strong); line-height:1.6; font-family:'JetBrains Mono',monospace">{
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
                    </section>


                    {{-- ====== APPROVE & SYNC EMR ====== --}}
                    <section x-show="section === 'approve'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Alur Kerja</div>
                        <h1 class="ds-display-md mb-4">Approve & Sync EMR</h1>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Alur approve</div>
                            <div class="space-y-3">
                                @foreach ([
                                    ['1', 'Update status', 'Set status → approved, simpan human_payload (diagnosa/prosedur yang sudah diedit)'],
                                    ['2', 'Sync diagnosa ke EMR', 'RJ/UGD only — insert diagnosa baru ke rstxn_rjdtls + update EMR JSON'],
                                    ['3', 'Execute bridging', 'RJ/UGD: auto 14-step E-Klaim pipeline'],
                                    ['4', 'RI: manual bridging', 'Dispatch event daftar-ri.idrg.open — buka modal iDRG'],
                                ] as [$no, $judul, $desc])
                                    <div class="flex items-start gap-3">
                                        <div class="flex items-center justify-center w-7 h-7 rounded-full shrink-0"
                                            style="background:var(--primary); color:white; font-size:12px; font-weight:700">{{ $no }}</div>
                                        <div>
                                            <div class="ds-title-sm" style="color:var(--ink)">{{ $judul }}</div>
                                            <div class="ds-body-sm" style="color:var(--muted)">{{ $desc }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- syncDiagnosaToEmr --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">syncDiagnosaToEmr()</h2>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong); max-width:62ch">
                            Dipanggil saat approve (RJ/UGD only). Sinkronisasi kode diagnosa yang disetujui
                            ke dalam EMR JSON dan tabel detail transaksi.
                        </p>
                        <div class="ds-card-outline" style="padding:20px">
                            <div class="space-y-3">
                                @foreach ([
                                    ['1', 'Baca JSON EMR', 'Dari datadaftarpolirj_json (RJ) atau datadaftarugd_json (UGD)'],
                                    ['2', 'Filter diagnosa baru', 'Yang belum ada di diagnosis[].diagId'],
                                    ['3', 'Validasi kode', 'Cek terhadap rsmst_mstdiags — skip jika tidak ada'],
                                    ['4', 'Insert ke rstxn_rjdtls', 'Tabel detail transaksi (FK ke rj_no)'],
                                    ['5', 'Append ke diagnosis[]', 'Tambahkan ke array diagnosis di JSON EMR'],
                                    ['6', 'Update JSON + flag', 'Set rj_diagnosa = \'D\' (RJ only)'],
                                ] as [$no, $judul, $desc])
                                    <div class="flex items-start gap-3">
                                        <div class="flex items-center justify-center w-6 h-6 rounded shrink-0"
                                            style="background:var(--surface-card); color:var(--body-strong); font-size:11px; font-weight:600">{{ $no }}</div>
                                        <div>
                                            <div class="ds-body-sm font-semibold" style="color:var(--ink)">{{ $judul }}</div>
                                            <div class="ds-caption" style="color:var(--muted)">{{ $desc }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>


                    {{-- ====== BRIDGING E-KLAIM ====== --}}
                    <section x-show="section === 'bridging'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Alur Kerja</div>
                        <h1 class="ds-display-md mb-4">Bridging E-Klaim (14 Step)</h1>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong); max-width:62ch">
                            <span class="ds-code">executeBridging($rjNo, $refType)</span> — pipeline otomatis untuk
                            RJ dan UGD. Setiap step: panggil API → cek
                            <span class="ds-code">metadata.code == 200</span> → simpan progress.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-4" style="color:var(--muted)">14 step pipeline</div>
                            <div class="space-y-2">
                                @foreach ([
                                    ['1', 'newClaim()', 'Skip jika idrg.nomorSep sudah ada', false],
                                    ['2', 'setDiagnosaIdrg()', 'Kode diagnosa dipisah #: I10#J18.9', false],
                                    ['3', 'setProsedurIdrg()', '# jika kosong', false],
                                    ['4', 'setClaimData()', 'Auto tarif dari kasir, cara_masuk: gp/er', false],
                                    ['5', 'grouperIdrgStage1()', 'MDC 36 = ungroupable → stop', true],
                                    ['6', 'grouperIdrgStage2()', 'Hanya jika ada topup_options', false],
                                    ['7', 'finalIdrg()', '—', false],
                                    ['8', 'importIdrgToInacbg()', 'Import hasil iDRG ke INACBG', false],
                                    ['9', 'setDiagnosaInacbg()', 'Sama string dengan step 2', false],
                                    ['10', 'setProsedurInacbg()', 'Sama string dengan step 3', false],
                                    ['11', 'grouperInacbgStage1()', '—', false],
                                    ['12', 'grouperInacbgStage2()', 'Hanya jika ada special_cmg', false],
                                    ['13', 'finalInacbg()', 'Skip jika INACBG ungroupable', false],
                                    ['14', 'finalClaim()', 'Coder NIK dari auth()->user()->emp_id', false],
                                ] as [$no, $method, $note, $warn])
                                    <div class="flex items-start gap-3 {{ $warn ? 'rounded-lg p-2' : '' }}"
                                        style="{{ $warn ? 'background:rgba(250,188,46,0.08)' : '' }}">
                                        <div class="flex items-center justify-center w-7 h-7 rounded-full shrink-0"
                                            style="background:{{ $warn ? 'var(--amber)' : 'var(--primary)' }}; color:white; font-size:11px; font-weight:700">{{ $no }}</div>
                                        <div class="flex-1 flex items-center gap-2 flex-wrap">
                                            <span class="ds-code">{{ $method }}</span>
                                            <span class="ds-caption" style="color:var(--muted)">{{ $note }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Error handling --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Error Handling</h2>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ul class="ds-body-sm space-y-1.5" style="color:var(--body-strong)">
                                <li>• Setiap step: cek <span class="ds-code">$res['metadata']['code'] == 200</span></li>
                                <li>• Gagal: <span class="ds-code">saveBridgingResult()</span> — partial progress tersimpan ke EMR JSON</li>
                                <li>• Return: <span class="ds-code">{success, failedStep, error, summary}</span></li>
                                <li>• <span class="ds-code">describeEklaimError()</span> untuk pesan error human-readable</li>
                            </ul>
                        </div>

                        {{-- saveBridgingResult --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">saveBridgingResult()</h2>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="ds-body-sm" style="color:var(--body-strong); line-height:1.6; font-family:'JetBrains Mono',monospace">DB::transaction(function () {
    $this->lockRJRow($rjNo);     // atau lockUGDRow
    $fresh = $this->findDataRJ($rjNo);
    $fresh['idrg'] = $idrg;      // progress tersimpan
    $this->updateJsonRJ($rjNo, $fresh);
});
// idrg.* TIDAK menimpa EMR asli — namespace terpisah</pre>
                        </div>
                    </section>


                    {{-- ====== CLAIM DATA & TARIF ====== --}}
                    <section x-show="section === 'claim-data'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Alur Kerja</div>
                        <h1 class="ds-display-md mb-4">Claim Data & Tarif</h1>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong); max-width:62ch">
                            <span class="ds-code">buildClaimDataPayload()</span> — tarif otomatis dari kasir,
                            dikirim ke E-Klaim di step 4 (setClaimData).
                        </p>

                        {{-- Mapping --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Tarif Mapping</h2>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Field E-Klaim</th><th>Source (kasir)</th></tr></thead>
                                    <tbody>
                                        <tr><td>prosedur_non_bedah</td><td><span class="ds-code">$cost['actePrice']</span></td></tr>
                                        <tr><td>konsultasi</td><td>poliPrice + rsAdmin + rjAdmin</td></tr>
                                        <tr><td>tenaga_ahli</td><td><span class="ds-code">$cost['actdPrice']</span></td></tr>
                                        <tr><td>penunjang</td><td>actpPrice + other</td></tr>
                                        <tr><td>radiologi</td><td><span class="ds-code">$cost['rad']</span></td></tr>
                                        <tr><td>laboratorium</td><td><span class="ds-code">$cost['lab']</span></td></tr>
                                        <tr><td>obat</td><td><span class="ds-code">$cost['obat']</span></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Perbedaan RJ vs UGD --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Perbedaan RJ vs UGD</h2>
                        <div class="ds-card-outline" style="padding:20px">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Field</th><th>RJ</th><th>UGD</th></tr></thead>
                                    <tbody>
                                        <tr><td><span class="ds-code">cara_masuk</span></td><td>gp</td><td>er</td></tr>
                                        <tr><td><span class="ds-code">jenis_rawat</span></td><td>2</td><td>1</td></tr>
                                        <tr><td>Cost method</td><td><span class="ds-code">calculateRJCosts()</span></td><td><span class="ds-code">calculateUGDCosts()</span></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
