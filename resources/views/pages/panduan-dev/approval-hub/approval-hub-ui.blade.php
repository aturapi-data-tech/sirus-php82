                    {{-- ====== ROW SELECTION ====== --}}
                    <section x-show="section === 'selection'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — UI Pattern</div>
                        <h1 class="ds-display-md mb-4">Row Selection (x-toggle)</h1>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong); max-width:62ch">
                            Pattern standar untuk memilih item di tabel antrian.
                            Dipakai di casemix-queue, satusehat-queue, dan bundling-klaim.
                        </p>

                        {{-- PHP --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">PHP Property & Methods</h2>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <pre class="ds-body-sm" style="color:var(--body-strong); line-height:1.6; font-family:'JetBrains Mono',monospace">public array $selectedIds = [];

public function toggleSelect(int $id): void
{
    if (in_array($id, $this->selectedIds)) {
        $this->selectedIds = array_values(
            array_diff($this->selectedIds, [$id]));
    } else {
        $this->selectedIds[] = $id;
    }
}

public function toggleSelectAll(): void
{
    $pageIds = $this->rows->pluck('approval_id')->toArray();
    $allSelected = !array_diff($pageIds, $this->selectedIds);
    if ($allSelected) {
        $this->selectedIds = array_values(
            array_diff($this->selectedIds, $pageIds));
    } else {
        $this->selectedIds = array_values(
            array_unique(
                array_merge($this->selectedIds, $pageIds)));
    }
}</pre>
                        </div>

                        {{-- Blade --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Blade Template</h2>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <pre class="ds-body-sm" style="color:var(--body-strong); line-height:1.6; font-family:'JetBrains Mono',monospace">@verbatim{{-- Header toggle (select all) --}}
&lt;x-toggle
    :current="count($selectedIds) > 0 &&
        !array_diff($this->rows->pluck('approval_id')
            ->toArray(), $selectedIds) ? '1' : '0'"
    trueValue="1" falseValue="0"
    wireClick="toggleSelectAll" /&gt;

{{-- Per-row toggle --}}
&lt;x-toggle
    :current="in_array($row->approval_id,
        $selectedIds) ? '1' : '0'"
    trueValue="1" falseValue="0"
    wireClick="toggleSelect({{ $row->approval_id }})" /&gt;

{{-- Badge di toolbar --}}
@if (!empty($selectedIds))
    &lt;span class="..."&gt;
        {{ count($selectedIds) }} dipilih
    &lt;/span&gt;
@endif@endverbatim</pre>
                        </div>

                        {{-- Reset timing --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Kapan Reset selectedIds</h2>
                        <div class="ds-card-outline" style="padding:20px">
                            <ul class="ds-body-sm space-y-1.5" style="color:var(--body-strong)">
                                <li>• Saat <strong>scan</strong> transaksi</li>
                                <li>• Saat <strong>clear queue</strong></li>
                                <li>• <em>Opsional:</em> saat pindah halaman (pagination)</li>
                            </ul>
                        </div>
                    </section>


                    {{-- ====== BERKAS BPJS ====== --}}
                    <section x-show="section === 'berkas'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — UI Pattern</div>
                        <h1 class="ds-display-md mb-4">Upload Berkas BPJS</h1>

                        {{-- Slot numbering --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Slot Numbering</h2>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Slot</th><th>Berkas</th><th>Catatan</th></tr></thead>
                                    <tbody>
                                        <tr><td><span class="ds-code">1</span></td><td>SEP</td><td>Generated PDF</td></tr>
                                        <tr><td><span class="ds-code">2</span></td><td>Grouping</td><td>Hanya setelah klaim final</td></tr>
                                        <tr><td><span class="ds-code">3</span></td><td>Rekam Medis</td><td>Dari EMR</td></tr>
                                        <tr><td><span class="ds-code">4</span></td><td>SKDP</td><td>Surat kontrol</td></tr>
                                        <tr><td><span class="ds-code">5</span></td><td>Lain-lain</td><td>—</td></tr>
                                        <tr><td><span class="ds-code">100+</span></td><td>Lab results</td><td>SLOT_LAB_OFFSET = 100</td></tr>
                                        <tr><td><span class="ds-code">200+</span></td><td>Radiologi results</td><td>SLOT_RAD_OFFSET = 200</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Alur upload --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Alur Upload</h2>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="ds-body-sm" style="color:var(--body-strong); line-height:1.8; font-family:'JetBrains Mono',monospace">autoUploadBerkas()
  → generateAllBerkas()
    → generateBerkasSep()         // slot 1
    → generateBerkasGrouping()    // slot 2
    → generateBerkasSkdp()        // slot 4
    → generateBerkasLab()         // slot 100+
    → generateBerkasRadiologi()   // slot 200+
  → saveBerkasBpjs()              // insert/update DB
  → syncBerkasToMount()           // copy ke mount dir</pre>
                        </div>
                    </section>
