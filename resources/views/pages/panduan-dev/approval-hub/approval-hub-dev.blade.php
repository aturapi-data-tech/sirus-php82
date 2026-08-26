                    {{-- ====== TAMBAH MODUL BARU ====== --}}
                    <section x-show="section === 'new-module'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Pengembangan</div>
                        <h1 class="ds-display-md mb-4">Tambah Modul Approval Baru</h1>
                        <p class="ds-body-sm mb-4" style="color:var(--body-strong); max-width:62ch">
                            Checklist dan template untuk menambahkan modul baru ke Approval Hub.
                            Semua modul mengikuti pattern yang sama —
                            cukup ganti nama module dan logika scan/execute.
                        </p>

                        {{-- Checklist --}}
                        <div class="ds-card-outline mb-6" style="padding:24px">
                            <div class="ds-caption-up mb-4" style="color:var(--muted)">Checklist</div>
                            <div class="space-y-4">
                                @foreach ([
                                    ['1', 'Buat komponen Blade', 'approval-hub/&lt;module&gt;/⚡&lt;module&gt;.blade.php'],
                                    ['2', 'Tambah tab di parent', 'Edit ⚡approval-hub.blade.php — update TABS const + &lt;x-tab&gt; + @@if content'],
                                    ['3', 'Scan transaksi', 'Query sumber data → insert ke rstxn_approval_queue dengan module unik'],
                                    ['4', 'Review modal', 'Baca dari queue, tampilkan di &lt;x-modal&gt; full-screen'],
                                    ['5', 'Execute on approve', 'Jalankan API/bridging saat approve, update status accordingly'],
                                    ['6', 'Row selection', 'Pakai x-toggle pattern (lihat section "Row Selection")'],
                                    ['7', 'Session isolation', 'Prefix session keys dengan nama module'],
                                    ['8', 'AppMenu entry', 'Tambah di app/Services/AppMenu.php → group Approval Hub'],
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

                        {{-- Template --}}
                        <h2 class="ds-title-md mb-3" style="color:var(--ink)">Template Minimal</h2>
                        <div class="ds-card-outline" style="padding:20px">
                            <pre class="ds-body-sm" style="color:var(--body-strong); line-height:1.5; font-family:'JetBrains Mono',monospace">&lt;?php
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
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
        $pageIds = $this->rows
            ->pluck('approval_id')->toArray();
        $allSelected = !array_diff(
            $pageIds, $this->selectedIds);
        if ($allSelected) {
            $this->selectedIds = array_values(
                array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(
                array_unique(array_merge(
                    $this->selectedIds, $pageIds)));
        }
    }

    public function scanTransaksi(): void
    {
        $this->selectedIds = [];
        DB::table('rstxn_approval_queue')
            ->where('module', 'nama-module')
            ->delete();
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
                    </section>
