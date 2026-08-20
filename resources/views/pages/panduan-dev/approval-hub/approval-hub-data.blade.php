                    {{-- ====== SOAP EXTRACTION ====== --}}
                    <section x-show="section === 'emr-soap'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Data & EMR</div>
                        <h1 class="ds-display-md mb-4">SOAP Extraction</h1>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong); max-width:62ch">
                            Teks SOAP diextract dari EMR JSON untuk dikirim ke AI.
                            Tiap tipe punya path berbeda di JSON.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">SOAP path per type</div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Section</th><th>RJ / UGD</th><th>RI</th></tr></thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>S</strong></td>
                                            <td>anamnesa.keluhanUtama + riwayatPenyakitSekarangUmum</td>
                                            <td>CPPT latest → soap.subjective<br><span class="ds-caption" style="color:var(--muted)">Fallback: pengkajianDokter.anamnesa</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>O</strong></td>
                                            <td>pemeriksaan.tandaVital (TD, N, RR, S, SpO2, GDA) + fisik + penunjang</td>
                                            <td>CPPT → soap.objective<br><span class="ds-caption" style="color:var(--muted)">Fallback: pengkajianDokter.fisik</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>A</strong></td>
                                            <td>diagnosisFreeText + diagnosis[].icdX</td>
                                            <td>CPPT → soap.assessment<br><span class="ds-caption" style="color:var(--muted)">+ pengkajianDokter.diagnosaAwal</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>P</strong></td>
                                            <td>perencanaan.terapi + tindakLanjut</td>
                                            <td>CPPT → soap.plan<br><span class="ds-caption" style="color:var(--muted)">Fallback: pengkajianDokter.rencana</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:20px; border-left:3px solid var(--blue)">
                            <div class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>RI priority:</strong> CPPT entries (SOAP terstruktur) dipakai utama.
                                pengkajianDokter hanya sebagai fallback jika CPPT kosong.
                                Diagnosa awal dari pengkajianDokter ditambahkan ke section A jika belum ada.
                            </div>
                        </div>
                    </section>


                    {{-- ====== EMR COMPLETENESS ====== --}}
                    <section x-show="section === 'emr-completeness'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Data & EMR</div>
                        <h1 class="ds-display-md mb-4">EMR Completeness</h1>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong); max-width:62ch">
                            Kelengkapan EMR dihitung per-section. Threshold:
                            <strong>≥ 80% dianggap lengkap</strong>. Ditampilkan sebagai progress bar di kolom Confidence
                            saat item belum diproses AI.
                        </p>

                        {{-- Section labels --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Section Labels per Type</h2>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Key</th><th>RJ</th><th>UGD</th><th>RI</th></tr></thead>
                                    <tbody>
                                        <tr><td><span class="ds-code">s</span></td><td>Anamnesa</td><td>Anamnesa</td><td>Pengkajian</td></tr>
                                        <tr><td><span class="ds-code">o</span></td><td>Pemeriksaan</td><td>Pemeriksaan</td><td>TTV</td></tr>
                                        <tr><td><span class="ds-code">a</span></td><td>Diagnosa</td><td>Diagnosa</td><td>Diagnosa</td></tr>
                                        <tr><td><span class="ds-code">p</span></td><td>Perencanaan</td><td>Perencanaan</td><td>Rencana</td></tr>
                                        <tr><td><span class="ds-code">n</span></td><td>Penilaian</td><td>Penilaian</td><td>Penilaian</td></tr>
                                        <tr><td><span class="ds-code">t</span></td><td>—</td><td>Triase</td><td>—</td></tr>
                                        <tr><td><span class="ds-code">k</span></td><td>SNOMED</td><td>SNOMED</td><td>Askep</td></tr>
                                        <tr><td><span class="ds-code">c</span></td><td>—</td><td>—</td><td>CPPT</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Return format --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Return Format</h2>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <pre class="ds-body-sm" style="color:var(--body-strong); line-height:1.6; font-family:'JetBrains Mono',monospace">calculateEmrPercent($emr, $refType)
→ ['emr' => 85, 'sections' => ['s' => 100, 'o' => 90, 'a' => 70, ...]]</pre>
                        </div>

                        {{-- Indicator --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Visual Indicator</h2>
                        <div class="ds-card-outline" style="padding:20px">
                            <div class="space-y-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-16 h-2 rounded-full overflow-hidden" style="background:var(--surface-card)">
                                        <div class="h-full rounded-full" style="background:var(--green); width:85%"></div>
                                    </div>
                                    <span class="ds-body-sm font-semibold" style="color:var(--green)">≥ 80% — Lengkap</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-16 h-2 rounded-full overflow-hidden" style="background:var(--surface-card)">
                                        <div class="h-full rounded-full" style="background:var(--amber); width:60%"></div>
                                    </div>
                                    <span class="ds-body-sm font-semibold" style="color:var(--amber)">50–79% — Parsial</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-16 h-2 rounded-full overflow-hidden" style="background:var(--surface-card)">
                                        <div class="h-full rounded-full" style="background:var(--red); width:30%"></div>
                                    </div>
                                    <span class="ds-body-sm font-semibold" style="color:var(--red)">&lt; 50% — Minim</span>
                                </div>
                            </div>
                        </div>
                    </section>


                    {{-- ====== FORMAT DIAGNOSA/PROSEDUR ====== --}}
                    <section x-show="section === 'diagnosa-format'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Data & EMR</div>
                        <h1 class="ds-display-md mb-4">Format Diagnosa & Prosedur</h1>

                        {{-- String format --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">E-Klaim String Format</h2>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong)">
                            E-Klaim API menerima kode yang disambung dengan <span class="ds-code">#</span>.
                            Primary diagnosa selalu didahulukan.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <pre class="ds-body-sm" style="color:var(--body-strong); line-height:1.8; font-family:'JetBrains Mono',monospace">// buildDiagnosaString()
// Primary first, lalu Secondary
"I10#J18.9#E11.9"  →  I10 = Primary, J18.9 & E11.9 = Secondary

// buildProsedurString()
"88.72#93.39"      →  semua prosedur digabung

// Kosong → '#' (bukan empty string — E-Klaim butuh ini)</pre>
                        </div>

                        {{-- Array format --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Array Format (JSON)</h2>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="ds-body-sm" style="color:var(--body-strong); line-height:1.6; font-family:'JetBrains Mono',monospace">// Diagnosa entry
{
    "code": "I10",
    "desc": "Essential (primary) hypertension",
    "kategori": "Primary",       // atau "Secondary"
    "confidence": 90,
    "reason": "TD 150/90 + terapi Amlodipine",
    "validated": true,           // ada di rsmst_mstdiags
    "original_code": null        // set jika AI code di-downgrade ke parent
}

// Prosedur entry
{
    "code": "88.72",
    "desc": "Diagnostic ultrasound of heart",
    "confidence": 80,
    "reason": "Echo dilakukan",
    "validated": true             // ada di rsmst_mstprocedures
}</pre>
                        </div>
                    </section>
