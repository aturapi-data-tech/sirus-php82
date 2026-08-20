                    {{-- ====== OVERVIEW ====== --}}
                    <section x-show="section === 'overview'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Dasar</div>
                        <h1 class="ds-display-md mb-4">Approval Hub</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Modul terpusat untuk semua workflow yang butuh persetujuan manusia sebelum eksekusi.
                            Pattern universal: <strong>Scan → AI Suggest → Human Review → Execute</strong>.
                            Dipakai untuk casemix (ICD coding + E-Klaim), SATUSEHAT (FHIR), dan bundling klaim BPJS.
                        </p>

                        {{-- Diagram alur --}}
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-4" style="color:var(--muted)">Empat tahap universal</div>
                            <div class="flex flex-wrap items-stretch gap-2">
                                @foreach ([
                                    ['1', 'Scan Transaksi', 'Query BPJS + EMR', 'scan'],
                                    ['2', 'AI Suggest', 'ICD-10/9 dari SOAP', 'ai-suggest'],
                                    ['3', 'Human Review', 'Approve / Reject', 'approve'],
                                    ['4', 'Execute', 'Bridging / Kirim', 'bridging'],
                                ] as [$no, $judul, $sub, $target])
                                    <button type="button" x-on:click="go('{{ $target }}')"
                                        class="flex-1 min-w-[150px] text-left rounded-xl p-4 transition hover:shadow-md"
                                        style="background:var(--surface-card)">
                                        <div class="ds-caption-up" style="color:var(--primary)">Tahap {{ $no }}</div>
                                        <div class="ds-title-md" style="color:var(--ink)">{{ $judul }}</div>
                                        <div class="ds-body-sm" style="color:var(--muted)">{{ $sub }}</div>
                                    </button>
                                    @if (!$loop->last)
                                        <div class="self-center ds-title-md" style="color:var(--muted-soft)">→</div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        {{-- Tab list --}}
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Tab aktif (5 modul)</div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr><th>Tab</th><th>Modul DB</th><th>Deskripsi</th><th>Auto Bridging</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr><td><span class="ds-code">Casemix RJ</span></td><td>casemix / RJ</td><td>AI suggest ICD + auto bridging E-Klaim 14 step</td><td style="color:var(--green)">Ya</td></tr>
                                        <tr><td><span class="ds-code">Casemix UGD</span></td><td>casemix / UGD</td><td>Sama dengan RJ, tabel <span class="ds-code">rstxn_ugdhdrs</span></td><td style="color:var(--green)">Ya</td></tr>
                                        <tr><td><span class="ds-code">Casemix RI</span></td><td>casemix / RI</td><td>AI suggest ICD, bridging manual via modal iDRG</td><td style="color:var(--muted)">Manual</td></tr>
                                        <tr><td><span class="ds-code">Bundling Klaim</span></td><td>bundling</td><td>Bundling klaim BPJS</td><td>—</td></tr>
                                        <tr><td><span class="ds-code">SATUSEHAT RJ</span></td><td>satusehat</td><td>Integrasi FHIR SATUSEHAT</td><td>—</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Status flow --}}
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Status flow</div>
                            <div class="space-y-2">
                                <div class="ds-body-sm" style="color:var(--body-strong)">
                                    <span class="ds-code">pending</span> →
                                    <span style="color:var(--blue)">AI suggest</span> →
                                    <span class="ds-code">pending</span> (ai_model set)
                                </div>
                                <div class="ds-body-sm" style="color:var(--body-strong)">
                                    <span class="ds-code">pending</span> →
                                    <span class="ds-code">approved</span> →
                                    <span class="ds-code">executing</span> →
                                    <span style="color:var(--green); font-weight:600">executed ✓</span>
                                </div>
                                <div class="ds-body-sm" style="color:var(--body-strong)">
                                    <span class="ds-code">pending</span> →
                                    <span class="ds-code">approved</span> →
                                    <span style="color:var(--red); font-weight:600">failed</span>
                                    (bridging error)
                                </div>
                                <div class="ds-body-sm" style="color:var(--body-strong)">
                                    <span class="ds-code">pending</span> →
                                    <span style="color:var(--muted)">rejected</span>
                                </div>
                            </div>
                        </div>

                        {{-- Security --}}
                        <div class="ds-card-outline" style="padding:20px; border-left:3px solid var(--red)">
                            <div class="ds-caption-up mb-2" style="color:var(--red)">Keamanan</div>
                            <ul class="ds-body-sm space-y-1" style="color:var(--body-strong)">
                                <li>• Fitur AI: <strong>admin role only</strong></li>
                                <li>• Kredensial RS: <strong>tidak boleh</strong> disimpan di memory/log</li>
                                <li>• Coder NIK: dari <span class="ds-code">auth()->user()->emp_id</span></li>
                                <li>• E-Klaim API: semua call terenkripsi (via iDrgTrait)</li>
                            </ul>
                        </div>
                    </section>


                    {{-- ====== ARCHITECTURE ====== --}}
                    <section x-show="section === 'architecture'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Dasar</div>
                        <h1 class="ds-display-md mb-4">Arsitektur & File Structure</h1>

                        {{-- File tree --}}
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Struktur file</div>
                            <pre class="ds-body-sm" style="color:var(--body-strong); line-height:1.8; font-family:'JetBrains Mono',monospace">resources/views/pages/transaksi/approval-hub/
├── ⚡approval-hub.blade.php          ← Parent wrapper — tab nav
├── casemix-queue/
│   └── ⚡casemix-queue.blade.php     ← Parameterized: queue-type="RJ|UGD|RI"
├── satusehat-queue/
│   └── ⚡satusehat-queue.blade.php   ← SATUSEHAT FHIR queue
└── bundling-klaim/
    └── ⚡bundling-klaim.blade.php    ← Bundling klaim BPJS</pre>
                        </div>

                        {{-- Parameterized pattern --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Parameterized Component Pattern</h2>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong); max-width:62ch">
                            Casemix queue menggunakan <strong>1 komponen</strong> untuk 3 tipe (RJ/UGD/RI) via prop
                            <span class="ds-code">queueType</span>. Menghindari duplikasi ~2700 baris × 3.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <pre class="ds-body-sm" style="color:var(--body-strong); line-height:1.6; font-family:'JetBrains Mono',monospace">@verbatim// Parent memanggil:
&lt;livewire:...casemix-queue queue-type="RJ" wire:key="casemix-queue-rj" /&gt;

// Component mount:
public function mount(string $queueType = 'RJ'): void {
    $this->queueType = strtoupper($queueType);
    $prefix = 'approval-casemix-' . strtolower($this->queueType) . '-';
    $this->filterStatus = session($prefix . 'filterStatus', 'pending');
}@endverbatim</pre>
                        </div>

                        {{-- Isolation rules --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Isolation Rules</h2>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Aspek</th><th>Strategi</th><th>Contoh</th></tr></thead>
                                    <tbody>
                                        <tr><td>Session keys</td><td>Prefix per-type</td><td><span class="ds-code">approval-casemix-rj-filterStatus</span></td></tr>
                                        <tr><td>Modal names</td><td>Append queueType</td><td><span class="ds-code">casemix-review-RJ</span></td></tr>
                                        <tr><td>DB queries</td><td>Filter ref_type</td><td><span class="ds-code">->where('ref_type', $this->queueType)</span></td></tr>
                                        <tr><td>wire:key</td><td>Unique per instance</td><td><span class="ds-code">wire:key="casemix-queue-rj"</span></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Traits --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Traits Yang Dipakai</h2>
                        <div class="ds-card-outline" style="padding:20px">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Trait</th><th>Methods</th></tr></thead>
                                    <tbody>
                                        <tr><td><span class="ds-code">EmrRJTrait</span></td><td>findDataRJ(), lockRJRow(), updateJsonRJ(), calculateRJCosts()</td></tr>
                                        <tr><td><span class="ds-code">EmrUGDTrait</span></td><td>findDataUGD(), lockUGDRow(), updateJsonUGD(), calculateUGDCosts()</td></tr>
                                        <tr><td><span class="ds-code">EmrRITrait</span></td><td>findDataRI()</td></tr>
                                        <tr><td><span class="ds-code">MasterPasienTrait</span></td><td>findDataMasterPasien()</td></tr>
                                        <tr><td><span class="ds-code">iDrgTrait</span></td><td>14 static methods E-Klaim API</td></tr>
                                        <tr><td><span class="ds-code">EmrCompleteness*Trait</span></td><td>calculateEmrPercentRJ/RI/UGD()</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>


                    {{-- ====== DATABASE ====== --}}
                    <section x-show="section === 'database'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Dasar</div>
                        <h1 class="ds-display-md mb-4">Database (Queue Table)</h1>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong); max-width:62ch">
                            Tabel <span class="ds-code">rstxn_approval_queue</span> adalah tabel sentral — semua modul
                            (casemix, satusehat, bundling) pakai tabel yang sama, dibedakan oleh kolom
                            <span class="ds-code">module</span> dan <span class="ds-code">ref_type</span>.
                        </p>

                        <div class="ds-card-outline" style="padding:20px">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Column</th><th>Type</th><th>Keterangan</th></tr></thead>
                                    <tbody>
                                        <tr><td><span class="ds-code">approval_id</span></td><td>NUMBER (PK)</td><td>Auto-increment sequence</td></tr>
                                        <tr><td><span class="ds-code">module</span></td><td>VARCHAR2</td><td>'casemix', 'satusehat', 'bundling'</td></tr>
                                        <tr><td><span class="ds-code">ref_no</span></td><td>VARCHAR2</td><td>rj_no / rihdr_no</td></tr>
                                        <tr><td><span class="ds-code">ref_type</span></td><td>VARCHAR2</td><td>'RJ', 'UGD', 'RI'</td></tr>
                                        <tr><td><span class="ds-code">reg_no</span></td><td>VARCHAR2</td><td>Nomor RM pasien</td></tr>
                                        <tr><td><span class="ds-code">reg_name</span></td><td>VARCHAR2(100)</td><td>Nama pasien</td></tr>
                                        <tr><td><span class="ds-code">vno_sep</span></td><td>VARCHAR2(50)</td><td>Nomor SEP</td></tr>
                                        <tr><td><span class="ds-code">ref_date</span></td><td>DATE</td><td>Tanggal kunjungan</td></tr>
                                        <tr><td><span class="ds-code">ai_payload</span></td><td>CLOB (JSON)</td><td>diagnosa, prosedur, soap_text, emr_percent, emr_sections</td></tr>
                                        <tr><td><span class="ds-code">ai_confidence</span></td><td>NUMBER</td><td>0–100</td></tr>
                                        <tr><td><span class="ds-code">ai_notes</span></td><td>VARCHAR2(4000)</td><td>Catatan AI + warning kode ditolak</td></tr>
                                        <tr><td><span class="ds-code">ai_model</span></td><td>VARCHAR2</td><td>Model AI yang dipakai</td></tr>
                                        <tr><td><span class="ds-code">status</span></td><td>VARCHAR2</td><td>pending / approved / executed / failed / rejected</td></tr>
                                        <tr><td><span class="ds-code">human_payload</span></td><td>CLOB (JSON)</td><td>Hasil review manusia: {diagnosa, prosedur}</td></tr>
                                        <tr><td><span class="ds-code">reviewer</span></td><td>VARCHAR2</td><td>Username reviewer</td></tr>
                                        <tr><td><span class="ds-code">review_notes</span></td><td>VARCHAR2</td><td>Catatan reviewer</td></tr>
                                        <tr><td><span class="ds-code">reviewed_at</span></td><td>TIMESTAMP</td><td>Waktu review</td></tr>
                                        <tr><td><span class="ds-code">exec_status</span></td><td>VARCHAR2</td><td>Status eksekusi</td></tr>
                                        <tr><td><span class="ds-code">exec_error</span></td><td>VARCHAR2</td><td>Pesan error eksekusi</td></tr>
                                        <tr><td><span class="ds-code">executed_at</span></td><td>TIMESTAMP</td><td>Waktu eksekusi</td></tr>
                                        <tr><td><span class="ds-code">created_at</span></td><td>TIMESTAMP</td><td>Waktu dibuat</td></tr>
                                        <tr><td><span class="ds-code">updated_at</span></td><td>TIMESTAMP</td><td>Waktu diperbarui</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
