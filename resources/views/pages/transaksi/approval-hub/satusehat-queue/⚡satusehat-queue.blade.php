<?php
// resources/views/pages/transaksi/approval-hub/satusehat-queue/satusehat-queue.blade.php
// Antrian kirim SATUSEHAT Rawat Jalan — scan, cek readiness, AI suggest ICD, kirim batch.

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Rj\EmrCompletenessRJTrait;
use App\Http\Traits\SATUSEHAT\SatuSehatTrait;
use App\Http\Traits\SATUSEHAT\KirimRawatJalanTrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait, EmrRJTrait, EmrCompletenessRJTrait;
    use SatuSehatTrait, KirimRawatJalanTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['ss-queue-toolbar'];

    #[Session(key: 'approval-ss-filterStatus')]
    public string $filterStatus = 'pending';
    #[Session(key: 'approval-ss-itemsPerPage')]
    public int $itemsPerPage = 25;

    #[Session(key: 'approval-ss-scanMode')]
    public string $scanMode = 'harian';
    #[Session(key: 'approval-ss-scanBulan')]
    public string $scanBulan = '';
    #[Session(key: 'approval-ss-scanTanggal')]
    public string $scanTanggal = '';
    public bool $scanning = false;
    public bool $sending = false;
    public int $sendProgress = 0;
    public int $sendTotal = 0;

    // AI batch (continuous)
    public bool $aiRunning = false;
    public bool $aiStopRequested = false;
    public int $aiProgress = 0;
    public int $aiTotal = 0;

    // Selection
    public array $selectedIds = [];

    // Batch kirim all (continuous)
    public array $batchKirimQueue = [];
    public int $batchKirimProgress = 0;
    public int $batchKirimTotal = 0;
    public bool $kirimStopRequested = false;

    // Review modal
    public ?int $reviewId = null;
    public array $reviewData = [];
    public string $reviewNotes = '';

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        if (empty($this->scanBulan)) {
            $this->scanBulan = now()->format('m/Y');
        }
        if (empty($this->scanTanggal)) {
            $this->scanTanggal = now()->format('d/m/Y');
        }
        $this->ensureSsSentCountColumn();
    }

    private function ensureSsSentCountColumn(): void
    {
        $exists = DB::selectOne("SELECT 1 FROM user_tab_columns WHERE table_name = 'RSTXN_APPROVAL_QUEUE' AND column_name = 'SS_SENT_COUNT'");
        if (!$exists) {
            DB::statement("ALTER TABLE rstxn_approval_queue ADD ss_sent_count NUMBER DEFAULT 0");
        }
    }

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
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    // ── Query rows ──

    #[Computed]
    public function rows()
    {
        $query = DB::table('rstxn_approval_queue')
            ->where('module', 'satusehat')
            ->select(
                'approval_id', 'ref_no', 'ref_type', 'reg_no', 'reg_name',
                'vno_sep', 'ai_payload', 'ai_confidence', 'ai_notes', 'ai_model',
                'status', 'human_payload', 'reviewer', 'review_notes',
                'exec_status', 'exec_error', 'ss_sent_count',
                DB::raw("to_char(created_at, 'DD/MM/YYYY HH24:MI:SS') as created_display"),
                DB::raw("to_char(reviewed_at, 'DD/MM/YYYY HH24:MI') as reviewed_display"),
                DB::raw("to_char(ref_date, 'DD/MM/YYYY HH24:MI') as ref_date_display"),
            );

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        $query->orderByRaw("CASE WHEN ai_model IS NOT NULL THEN 0 ELSE 1 END")
            ->orderByRaw("CASE status
            WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'edited' THEN 2
            WHEN 'executed' THEN 3 WHEN 'failed' THEN 4 WHEN 'rejected' THEN 5
            ELSE 6 END")
            ->orderByRaw('NVL(ss_sent_count, 0) ASC')
            ->orderByRaw('ref_date ASC NULLS LAST');

        return $query->paginate($this->itemsPerPage);
    }

    #[Computed]
    public function satusehatStates(): array
    {
        $rjNos = $this->rows->pluck('ref_no')->filter()->unique()->toArray();
        if (empty($rjNos)) return [];

        $rows = DB::table('rstxn_rjhdrs')
            ->whereIn('rj_no', $rjNos)
            ->select('rj_no', 'datadaftarpolirj_json')
            ->get();

        $states = [];
        foreach ($rows as $r) {
            $jsonRaw = is_object($r->datadaftarpolirj_json) && method_exists($r->datadaftarpolirj_json, 'read')
                ? $r->datadaftarpolirj_json->read($r->datadaftarpolirj_json->size())
                : (string) ($r->datadaftarpolirj_json ?? '');
            $emr = json_decode($jsonRaw ?: '{}', true) ?? [];
            $states[$r->rj_no] = $emr['satusehat'] ?? [];
        }
        return $states;
    }

    #[Computed]
    public function statusCounts(): object
    {
        $counts = DB::table('rstxn_approval_queue')
            ->where('module', 'satusehat')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status IN ('approved','edited') THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'executed' THEN 1 ELSE 0 END) as executed,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            ")->first();
        return $counts;
    }

    // ── Review modal ──

    public function openReview(int $approvalId): void
    {
        $row = DB::table('rstxn_approval_queue')->where('approval_id', $approvalId)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }

        // Load fresh state from EMR JSON
        $rjNo = (int) $row->ref_no;
        if ($rjNo > 0) {
            $payload = json_decode($row->ai_payload ?? '{}', true) ?: [];
            $emr = $this->findDataRJ($rjNo);
            if (!empty($emr)) {
                $payload['satusehat'] = $emr['satusehat'] ?? [];
                $payload['emr_checklist'] = $this->collectChecklistRJ($emr);
                $emrPct = $this->calculateEmrPercentRJ($emr);
                $payload['emr_percent'] = $emrPct['emr'];
                $payload['emr_sections'] = $emrPct['sections'];
            }
            $row->ai_payload = json_encode($payload);
        }

        $this->reviewId = $approvalId;
        $this->reviewData = (array) $row;
        $this->reviewNotes = '';
        $this->dispatch('open-modal', name: 'ss-review');
    }

    public function approve(): void
    {
        if (!$this->reviewId) return;

        $row = DB::table('rstxn_approval_queue')->where('approval_id', $this->reviewId)->first();
        if (!$row || !in_array($row->status, ['pending', 'edited', 'failed'])) return;

        $payload = json_decode($row->ai_payload ?? '{}', true) ?: [];

        // Recalculate readiness from current payload state
        $readiness = [];
        if (empty($payload['patient_uuid'] ?? '')) $readiness[] = 'Patient ID (IHS) kosong';
        if (empty($payload['dr_uuid'] ?? '')) $readiness[] = 'Dokter IHS kosong';
        if (empty($payload['poli_uuid'] ?? '')) $readiness[] = 'Poli UUID kosong';
        if (empty($payload['diagnosa'] ?? [])) $readiness[] = 'Belum ada diagnosa ICD';

        $payload['readiness'] = $readiness;
        $payload['is_ready'] = empty($readiness);
        $payload['needs_manual'] = !empty($readiness);

        // Auto-fill alergi "tidak ada alergi" if empty
        $alergiPayload = $payload['alergi'] ?? [];
        $alergiText = is_array($alergiPayload) ? trim($alergiPayload['text'] ?? '') : '';
        $alergiSnomed = is_array($alergiPayload) ? trim($alergiPayload['snomedCode'] ?? '') : '';
        if ($alergiText === '' && $alergiSnomed === '') {
            $payload['alergi'] = [
                'text' => \App\Support\Terminologi\AlergiSnomed::TIDAK_ADA['alergi'],
                'snomedCode' => \App\Support\Terminologi\AlergiSnomed::TIDAK_ADA['snomedCode'],
                'snomedDisplayEn' => \App\Support\Terminologi\AlergiSnomed::TIDAK_ADA['snomedDisplayEn'],
                'snomedDisplayId' => \App\Support\Terminologi\AlergiSnomed::TIDAK_ADA['snomedDisplayId'],
            ];
        }

        // Sync approved diagnosa + alergi into EMR JSON
        $rjNo = (int) $row->ref_no;
        if ($rjNo > 0) {
            try {
                $lob = DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->value('datadaftarpolirj_json');
                $jsonRaw = is_object($lob) && method_exists($lob, 'read') ? $lob->read($lob->size()) : (string) $lob;
                $emr = json_decode($jsonRaw ?: '{}', true) ?? [];

                $emrChanged = false;

                // Write diagnosa to EMR + rstxn_rjdtls
                $existingDx = $emr['diagnosis'] ?? [];
                $existingCodes = collect($existingDx)->pluck('diagId')->toArray();
                $newDxFromPayload = collect($payload['diagnosa'] ?? [])->filter(fn($d) => !in_array($d['code'], $existingCodes));

                if ($newDxFromPayload->isNotEmpty()) {
                    foreach ($newDxFromPayload as $d) {
                        $diagCode = $d['code'] ?? '';
                        $existsInMaster = DB::table('rsmst_mstdiags')->where('diag_id', $diagCode)->exists();
                        if (!$existsInMaster) continue;

                        $nextDtl = DB::table('rstxn_rjdtls')->select(DB::raw('NVL(MAX(rjdtl_dtl)+1, 1) as nxt'))->first();
                        $dtlId = $nextDtl->nxt;

                        DB::table('rstxn_rjdtls')->insert([
                            'rjdtl_dtl' => $dtlId,
                            'rj_no' => $rjNo,
                            'diag_id' => $diagCode,
                        ]);

                        $hasPrimary = collect($existingDx)->where('kategoriDiagnosa', 'Primary')->isNotEmpty();
                        $kategori = (!$hasPrimary && ($d['kategori'] ?? '') === 'Primary') ? 'Primary' : ($d['kategori'] ?? 'Secondary');

                        $existingDx[] = [
                            'diagId' => $diagCode,
                            'diagDesc' => $d['desc'],
                            'icdX' => $diagCode,
                            'ketdiagnosa' => 'Keterangan Diagnosa',
                            'kategoriDiagnosa' => $kategori,
                            'rjDtlDtl' => $dtlId,
                            'rjNo' => $rjNo,
                        ];
                    }

                    $emr['diagnosis'] = $existingDx;
                    DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->update(['rj_diagnosa' => 'D']);
                    $emrChanged = true;
                }

                // Write alergi to EMR
                $alergiApproved = $payload['alergi'] ?? [];
                if (!empty($alergiApproved['snomedCode'])) {
                    $emr['anamnesa'] = $emr['anamnesa'] ?? [];
                    $emr['anamnesa']['alergi'] = [
                        'alergi' => $alergiApproved['text'] ?? '',
                        'snomedCode' => $alergiApproved['snomedCode'] ?? '',
                        'snomedDisplayEn' => $alergiApproved['snomedDisplayEn'] ?? '',
                        'snomedDisplayId' => $alergiApproved['snomedDisplayId'] ?? '',
                    ];
                    $emrChanged = true;
                }

                if ($emrChanged) {
                    DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->update([
                        'datadaftarpolirj_json' => json_encode($emr),
                    ]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Approve: failed to sync EMR', [
                    'rj_no' => $rjNo, 'error' => $e->getMessage(),
                ]);
            }
        }

        DB::table('rstxn_approval_queue')
            ->where('approval_id', $this->reviewId)
            ->update([
                'status' => 'approved',
                'ai_payload' => json_encode($payload),
                'reviewer' => Auth::user()->myuser_name ?? Auth::user()->name ?? 'unknown',
                'review_notes' => $this->reviewNotes ?: null,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        $this->dispatch('toast', type: 'success', message: 'Item disetujui — diagnosa & alergi disinkron ke EMR.');
        $this->dispatch('close-modal', name: 'ss-review');
        $this->reviewId = null;
        $this->incrementVersion('ss-queue-toolbar');
    }

    public function quickApprove(int $approvalId): void
    {
        $this->reviewId = $approvalId;
        $this->reviewNotes = '';
        $this->approve();
    }

    public function approveAll(): void
    {
        $query = DB::table('rstxn_approval_queue')
            ->where('module', 'satusehat')
            ->where('status', 'pending')
            ->select('approval_id', 'ai_payload')
            ->orderBy('approval_id');

        if (!empty($this->selectedIds)) {
            $query->whereIn('approval_id', $this->selectedIds);
        }

        $items = $query->get();
        $approved = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $payload = json_decode($item->ai_payload ?? '{}', true) ?: [];

            $ready = !empty($payload['patient_uuid'] ?? '')
                && !empty($payload['dr_uuid'] ?? '')
                && !empty($payload['poli_uuid'] ?? '')
                && !empty($payload['diagnosa'] ?? []);

            if (!$ready) {
                $skipped++;
                continue;
            }

            $this->reviewId = $item->approval_id;
            $this->reviewNotes = '';
            $this->approve();
            $approved++;
        }

        $msg = $approved . ' item di-approve.';
        if ($skipped > 0) $msg .= ' ' . $skipped . ' di-skip (setup belum lengkap).';
        $this->dispatch('toast', type: $approved > 0 ? 'success' : 'info', message: $msg);
        $this->incrementVersion('ss-queue-toolbar');
    }

    public function reject(): void
    {
        if (!$this->reviewId) return;

        DB::table('rstxn_approval_queue')
            ->where('approval_id', $this->reviewId)
            ->update([
                'status' => 'rejected',
                'reviewer' => Auth::user()->myuser_name ?? Auth::user()->name ?? 'unknown',
                'review_notes' => $this->reviewNotes ?: null,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        $this->dispatch('toast', type: 'info', message: 'Item ditolak.');
        $this->dispatch('close-modal', name: 'ss-review');
        $this->reviewId = null;
        $this->incrementVersion('ss-queue-toolbar');
    }

    public function skip(int $approvalId): void
    {
        DB::table('rstxn_approval_queue')
            ->where('approval_id', $approvalId)
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'review_notes' => 'Skipped — setup master belum lengkap',
                'reviewer' => Auth::user()->myuser_name ?? Auth::user()->name ?? 'system',
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        $this->dispatch('toast', type: 'info', message: 'Item di-skip.');
        $this->incrementVersion('ss-queue-toolbar');
    }

    // ── Date range ──

    private function scanDateRange(): array
    {
        if ($this->scanMode === 'bulanan') {
            $parts = explode('/', $this->scanBulan);
            $month = (int) ($parts[0] ?? now()->month);
            $year = (int) ($parts[1] ?? now()->year);
            $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
            $end = $start->copy()->endOfMonth()->endOfDay();
        } else {
            $end = Carbon::createFromFormat('d/m/Y', $this->scanTanggal)->endOfDay();
            $start = $end->copy()->startOfDay();
        }
        return [$start, $end];
    }

    // ── Scan transaksi RJ ──

    public function scanTransaksi(): void
    {
        [$start, $end] = $this->scanDateRange();
        $this->selectedIds = [];
        $this->scanning = true;

        try {
            DB::table('rstxn_approval_queue')->where('module', 'satusehat')->delete();

            $rjRows = DB::table('rstxn_rjhdrs as h')
                ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->leftJoin('rsmst_doctors as d', 'h.dr_id', '=', 'd.dr_id')
                ->leftJoin('rsmst_polis as pol', 'h.poli_id', '=', 'pol.poli_id')
                ->whereRaw("trunc(h.rj_date) >= to_date(?, 'YYYY-MM-DD')", [$start->format('Y-m-d')])
                ->whereRaw("trunc(h.rj_date) <= to_date(?, 'YYYY-MM-DD')", [$end->format('Y-m-d')])
                ->whereRaw("NVL(h.rj_status, 'A') = 'L'")
                ->where('h.klaim_id', '!=', 'KR')
                ->whereRaw("p.patient_uuid IS NOT NULL AND p.patient_uuid != ' '")
                ->whereRaw("d.dr_uuid IS NOT NULL AND d.dr_uuid != ' '")
                ->whereRaw("pol.poli_uuid IS NOT NULL AND pol.poli_uuid != ' '")
                ->select(
                    'h.rj_no', 'h.reg_no', 'p.reg_name', 'h.rj_date',
                    DB::raw("to_char(h.rj_date, 'DD/MM/YYYY HH24:MI:SS') as rj_date_display"),
                    'h.dr_id', 'd.dr_name', 'h.poli_id', 'pol.poli_desc',
                    'p.patient_uuid', 'd.dr_uuid', 'pol.poli_uuid',
                )
                ->get();

            $inserted = 0;

            foreach ($rjRows as $row) {
                $emr = $this->findDataRJ($row->rj_no);
                $ss = $emr['satusehat'] ?? [];

                $patientUuid = $row->patient_uuid ?? '';
                $drUuid = $row->dr_uuid ?? '';
                $poliUuid = $row->poli_uuid ?? '';

                $hasDiagnosis = !empty($emr['diagnosis']) && is_array($emr['diagnosis']);
                $alreadySent = !empty($ss['encounterId']);

                $readiness = [];
                if (empty($patientUuid)) $readiness[] = 'Patient ID (IHS) kosong';
                if (empty($drUuid)) $readiness[] = 'Dokter IHS kosong';
                if (empty($poliUuid)) $readiness[] = 'Poli UUID kosong';
                if (!$hasDiagnosis) $readiness[] = 'Belum ada diagnosa ICD';
                if ($alreadySent) $readiness[] = 'Encounter sudah terkirim';

                $isReady = empty($readiness) && !$alreadySent;
                $needsManual = !empty($readiness) && !$alreadySent;

                $diagnosaList = collect($emr['diagnosis'] ?? [])->map(fn($d) => [
                    'code' => $d['icdX'] ?? ($d['diagId'] ?? ''),
                    'desc' => $d['diagDesc'] ?? '',
                    'kategori' => $d['kategoriDiagnosa'] ?? 'Primary',
                ])->values()->toArray();

                $prosedurList = collect($emr['procedure'] ?? [])->map(fn($p) => [
                    'code' => $p['procedureId'] ?? '',
                    'desc' => $p['procedureDesc'] ?? '',
                ])->values()->toArray();

                $anamnesa = $emr['anamnesa'] ?? [];
                $keluhanNode = $anamnesa['keluhanUtama'] ?? [];
                $alergiNode = $anamnesa['alergi'] ?? [];

                $keluhanUtama = [
                    'text' => $keluhanNode['keluhanUtama'] ?? '',
                    'snomedCode' => $keluhanNode['snomedCode'] ?? '',
                    'snomedDisplayEn' => $keluhanNode['snomedDisplayEn'] ?? '',
                    'snomedDisplayId' => $keluhanNode['snomedDisplayId'] ?? '',
                ];

                $alergiText = trim($alergiNode['alergi'] ?? '');
                $alergiSnomedCode = trim($alergiNode['snomedCode'] ?? '');

                if ($alergiText === '' && $alergiSnomedCode === '') {
                    $alergi = [
                        'text' => \App\Support\Terminologi\AlergiSnomed::TIDAK_ADA['alergi'],
                        'snomedCode' => \App\Support\Terminologi\AlergiSnomed::TIDAK_ADA['snomedCode'],
                        'snomedDisplayEn' => \App\Support\Terminologi\AlergiSnomed::TIDAK_ADA['snomedDisplayEn'],
                        'snomedDisplayId' => \App\Support\Terminologi\AlergiSnomed::TIDAK_ADA['snomedDisplayId'],
                    ];
                } else {
                    $alergi = [
                        'text' => $alergiText,
                        'snomedCode' => $alergiSnomedCode,
                        'snomedDisplayEn' => $alergiNode['snomedDisplayEn'] ?? '',
                        'snomedDisplayId' => $alergiNode['snomedDisplayId'] ?? '',
                    ];
                }

                $emrChecklist = $this->collectChecklistRJ($emr);
                $emrPct = $this->calculateEmrPercentRJ($emr);

                $ssFilled = fn($v) => is_array($v) ? count($v) > 0 : !empty($v);
                $satusehatItems = [
                    ['label' => 'Encounter',           'full' => 'Encounter (kunjungan)',              'sent' => $ssFilled($ss['encounterId'] ?? null)],
                    ['label' => 'Condition',           'full' => 'Condition (diagnosa ICD-10)',        'sent' => $ssFilled($ss['conditionIds'] ?? null)],
                    ['label' => 'Observation',         'full' => 'Observation (tanda vital)',          'sent' => $ssFilled($ss['observationIds'] ?? null)],
                    ['label' => 'Procedure',           'full' => 'Procedure (tindakan ICD-9)',         'sent' => $ssFilled($ss['procedureIds'] ?? null)],
                    ['label' => 'Med Request',         'full' => 'Medication Request (resep)',         'sent' => $ssFilled($ss['medicationRequestIds'] ?? null)],
                    ['label' => 'Chief Complaint',     'full' => 'Chief Complaint (keluhan utama)',    'sent' => $ssFilled($ss['chiefComplaintId'] ?? null)],
                    ['label' => 'Allergy',             'full' => 'Allergy Intolerance',               'sent' => $ssFilled($ss['allergyId'] ?? null)],
                    ['label' => 'Med Dispense',        'full' => 'Medication Dispense (obat pulang)',  'sent' => $ssFilled($ss['medicationDispenseIds'] ?? null)],
                    ['label' => 'Lab',                 'full' => 'Penunjang Lab',                      'sent' => $ssFilled($ss['labServiceRequestIds'] ?? null) || $ssFilled($ss['labDiagnosticReportIds'] ?? null)],
                    ['label' => 'Radiologi',           'full' => 'Penunjang Radiologi',               'sent' => $ssFilled($ss['radServiceRequestIds'] ?? null) || $ssFilled($ss['radDiagnosticReportIds'] ?? null)],
                    ['label' => 'Clinical Impression', 'full' => 'Clinical Impression',               'sent' => $ssFilled($ss['clinicalImpressionId'] ?? null)],
                    ['label' => 'Penilaian',           'full' => 'Penilaian (nyeri/risiko jatuh)',     'sent' => $ssFilled($ss['penilaianObservationIds'] ?? null)],
                    ['label' => 'Encounter Selesai',   'full' => 'Encounter status finished',         'sent' => !empty($ss['encounterFinished'])],
                    ['label' => 'Resume Medis',        'full' => 'Resume Medis (Composition)',         'sent' => $ssFilled($ss['compositionId'] ?? null)],
                ];

                DB::table('rstxn_approval_queue')->insert([
                    'module' => 'satusehat',
                    'ref_no' => $row->rj_no,
                    'ref_type' => 'RJ',
                    'reg_no' => $row->reg_no,
                    'reg_name' => mb_substr($row->reg_name ?? '', 0, 100),
                    'vno_sep' => null,
                    'ref_date' => $row->rj_date,
                    'ai_payload' => json_encode([
                        'diagnosa' => $diagnosaList,
                        'prosedur' => $prosedurList,
                        'keluhan_utama' => $keluhanUtama,
                        'alergi' => $alergi,
                        'patient_uuid' => $patientUuid,
                        'dr_uuid' => $drUuid,
                        'dr_id' => $row->dr_id ?? '',
                        'dr_name' => $row->dr_name ?? '',
                        'poli_uuid' => $poliUuid,
                        'poli_id' => $row->poli_id ?? '',
                        'poli_desc' => $row->poli_desc ?? '',
                        'rj_date' => $row->rj_date_display ?? '',
                        'readiness' => $readiness,
                        'is_ready' => $isReady,
                        'needs_manual' => $needsManual,
                        'already_sent' => $alreadySent,
                        'satusehat' => $ss,
                        'emr_percent' => $emrPct['emr'],
                        'emr_sections' => $emrPct['sections'],
                        'emr_checklist' => $emrChecklist,
                        'satusehat_items' => $satusehatItems,
                    ]),
                    'ai_confidence' => 0,
                    'ai_notes' => '',
                    'status' => $alreadySent ? 'executed' : ($needsManual ? 'pending' : 'pending'),
                    'ss_sent_count' => collect($satusehatItems)->where('sent', true)->count(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $inserted++;
            }

            $this->dispatch('toast', type: 'success', message: $inserted . ' transaksi RJ dimasukkan ke antrian SATUSEHAT.');
            $this->incrementVersion('ss-queue-toolbar');
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Scan gagal: ' . $e->getMessage());
        }

        $this->scanning = false;
    }

    public function clearQueue(): void
    {
        $deleted = DB::table('rstxn_approval_queue')->where('module', 'satusehat')->delete();
        $this->selectedIds = [];
        $this->dispatch('toast', type: 'info', message: $deleted . ' item dihapus dari antrian.');
        $this->incrementVersion('ss-queue-toolbar');
        $this->resetPage();
    }

    // ── Kirim All (batch 5 via modal) ──

    public function kirimAllBatch(): void
    {
        if (!empty($this->batchKirimQueue) || $this->batchKirimTotal > 0) {
            $this->dispatch('toast', type: 'info', message: 'Batch kirim sedang berjalan.');
            return;
        }

        $totalApproved = DB::table('rstxn_approval_queue')
            ->where('module', 'satusehat')
            ->where('status', 'approved')
            ->when(!empty($this->selectedIds), fn($q) => $q->whereIn('approval_id', $this->selectedIds))
            ->count();

        if ($totalApproved === 0) {
            $this->dispatch('toast', type: 'info', message: 'Tidak ada item approved untuk dikirim.');
            return;
        }

        $this->batchKirimTotal = $totalApproved;
        $this->batchKirimProgress = 0;
        $this->kirimStopRequested = false;

        $this->kirimLoadNextBatch();
        if (empty($this->batchKirimQueue)) {
            $this->kirimFinish();
            return;
        }

        $this->kirimNextInBatch();
    }

    private function kirimNextInBatch(): void
    {
        if (empty($this->batchKirimQueue)) {
            if ($this->kirimStopRequested) {
                $this->kirimFinish();
                return;
            }

            $remaining = DB::table('rstxn_approval_queue')
                ->where('module', 'satusehat')
                ->where('status', 'approved')
                ->count();

            if ($remaining === 0) {
                $this->kirimFinish();
                return;
            }

            $this->kirimLoadNextBatch();
            if (empty($this->batchKirimQueue)) {
                $this->kirimFinish();
                return;
            }
        }

        $rjNo = array_shift($this->batchKirimQueue);
        $this->batchKirimProgress++;
        $this->dispatch('daftar-rj.satu-sehat.open', rjNo: $rjNo, autoKirim: true);
    }

    private function kirimLoadNextBatch(): void
    {
        $query = DB::table('rstxn_approval_queue')
            ->where('module', 'satusehat')
            ->where('status', 'approved')
            ->select('approval_id', 'ref_no', 'ref_date');

        if (!empty($this->selectedIds)) {
            $query->whereIn('approval_id', $this->selectedIds);
        }

        $items = $query->get();
        if ($items->isEmpty()) return;

        $rjNos = $items->pluck('ref_no')->filter()->unique()->toArray();
        $emrRows = DB::table('rstxn_rjhdrs')
            ->whereIn('rj_no', $rjNos)
            ->select('rj_no', 'datadaftarpolirj_json')
            ->get();

        $sentCounts = [];
        $ssFilled = fn($v) => is_array($v) ? count($v) > 0 : !empty($v);
        foreach ($emrRows as $r) {
            $jsonRaw = is_object($r->datadaftarpolirj_json) && method_exists($r->datadaftarpolirj_json, 'read')
                ? $r->datadaftarpolirj_json->read($r->datadaftarpolirj_json->size())
                : (string) ($r->datadaftarpolirj_json ?? '');
            $emr = json_decode($jsonRaw ?: '{}', true) ?? [];
            $ss = $emr['satusehat'] ?? [];
            $sentCounts[$r->rj_no] = collect([
                $ssFilled($ss['encounterId'] ?? null),
                $ssFilled($ss['conditionIds'] ?? null),
                $ssFilled($ss['observationIds'] ?? null),
                $ssFilled($ss['procedureIds'] ?? null),
                $ssFilled($ss['medicationRequestIds'] ?? null),
                $ssFilled($ss['chiefComplaintId'] ?? null),
                $ssFilled($ss['allergyId'] ?? null),
                $ssFilled($ss['medicationDispenseIds'] ?? null),
                $ssFilled($ss['labServiceRequestIds'] ?? null) || $ssFilled($ss['labDiagnosticReportIds'] ?? null),
                $ssFilled($ss['radServiceRequestIds'] ?? null) || $ssFilled($ss['radDiagnosticReportIds'] ?? null),
                $ssFilled($ss['clinicalImpressionId'] ?? null),
                $ssFilled($ss['penilaianObservationIds'] ?? null),
                !empty($ss['encounterFinished']),
                $ssFilled($ss['compositionId'] ?? null),
            ])->filter()->count();
        }

        $sorted = $items->sortBy([
            fn($a, $b) => ($sentCounts[$a->ref_no] ?? 0) <=> ($sentCounts[$b->ref_no] ?? 0),
            fn($a, $b) => ($a->ref_date ?? '') <=> ($b->ref_date ?? ''),
        ])->values();
        $batch = $sorted->take(5);

        $this->batchKirimQueue = $batch->pluck('ref_no')->map(fn($v) => (string) $v)->toArray();
    }

    private function kirimFinish(): void
    {
        $msg = 'Kirim selesai: ' . $this->batchKirimProgress . '/' . $this->batchKirimTotal . ' record terkirim.';
        $this->dispatch('toast', type: 'success', message: $msg);
        $this->batchKirimQueue = [];
        $this->batchKirimProgress = 0;
        $this->batchKirimTotal = 0;
        $this->kirimStopRequested = false;
        $this->incrementVersion('ss-queue-toolbar');
    }

    #[On('rj-satu-sehat.kirim-semua-selesai')]
    public function onKirimSemuaSelesai(string $rjNo): void
    {
        DB::table('rstxn_approval_queue')
            ->where('module', 'satusehat')
            ->where('ref_no', $rjNo)
            ->where('status', 'approved')
            ->update([
                'status' => 'executed',
                'exec_status' => 'success',
                'ss_sent_count' => 14,
                'executed_at' => now(),
                'updated_at' => now(),
            ]);

        if (empty($this->batchKirimQueue) && $this->batchKirimTotal === 0) {
            return;
        }

        $this->dispatch('close-modal', name: 'rj-satu-sehat');
        $this->kirimNextInBatch();
    }

    public function batalKirimAll(): void
    {
        $this->kirimStopRequested = true;
        $this->batchKirimQueue = [];
        $sent = $this->batchKirimProgress;
        $this->batchKirimProgress = 0;
        $this->batchKirimTotal = 0;
        $this->dispatch('toast', type: 'info', message: 'Batch kirim dihentikan. ' . $sent . ' record sudah terkirim.');
        $this->incrementVersion('ss-queue-toolbar');
    }

    // ── AI Suggest ICD (batch 5) ──

    public function aiSuggestBatch(): void
    {
        $apiKey = config('ai.api_key');
        if (empty($apiKey)) {
            $this->dispatch('toast', type: 'error', message: 'AI API key belum dikonfigurasi.');
            return;
        }

        $this->aiRunning = true;
        $this->aiStopRequested = false;
        $this->aiProgress = 0;
        $this->aiTotal = 0;
        $this->aiProcessNextBatch();
    }

    public function stopAi(): void
    {
        $this->aiStopRequested = true;
        $sent = $this->aiProgress;
        $this->aiRunning = false;
        $this->aiProgress = 0;
        $this->aiTotal = 0;
        $this->dispatch('toast', type: 'info', message: 'AI dihentikan. ' . $sent . ' item sudah diproses.');
        $this->incrementVersion('ss-queue-toolbar');
    }

    public function aiProcessNextBatch(): void
    {
        if ($this->aiStopRequested || !$this->aiRunning) {
            $this->aiRunning = false;
            $this->aiStopRequested = false;
            $msg = $this->aiProgress . '/' . $this->aiTotal . ' item diproses AI.';
            $this->dispatch('toast', type: 'success', message: $msg);
            $this->incrementVersion('ss-queue-toolbar');
            return;
        }

        $batchSize = 5;

        $query = DB::table('rstxn_approval_queue')
            ->where('module', 'satusehat')
            ->where('status', 'pending')
            ->whereNull('ai_model')
            ->select('approval_id', 'ai_payload', 'reg_name', 'ref_type')
            ->orderBy('approval_id');

        if (!empty($this->selectedIds)) {
            $query->whereIn('approval_id', $this->selectedIds);
        }

        $pending = $query->get();

        $needsAi = $pending->filter(function ($item) {
            $payload = json_decode($item->ai_payload ?? '{}', true) ?: [];
            $noDiagnosa = empty($payload['diagnosa']);
            $noKeluhanSnomed = empty($payload['keluhan_utama']['snomedCode'] ?? '');
            return $noDiagnosa || $noKeluhanSnomed;
        });

        if ($needsAi->isEmpty()) {
            $this->aiRunning = false;
            $msg = $this->aiProgress . ' item diproses AI. Semua sudah lengkap.';
            $this->dispatch('toast', type: 'success', message: $msg);
            $this->incrementVersion('ss-queue-toolbar');
            return;
        }

        $this->aiTotal = $this->aiProgress + $needsAi->count();
        $batch = $needsAi->take($batchSize);

        foreach ($batch as $item) {
            $this->aiProgress++;

            try {
                $payload = json_decode($item->ai_payload ?? '{}', true) ?: [];

                $refNo = (int) DB::table('rstxn_approval_queue')->where('approval_id', $item->approval_id)->value('ref_no');
                $emr = $this->findDataRJ($refNo);

                $soapText = $this->extractSoapTextForSS($emr);
                if (mb_strlen(trim($soapText)) < 10) {
                    DB::table('rstxn_approval_queue')
                        ->where('approval_id', $item->approval_id)
                        ->update([
                            'ai_model' => 'skipped',
                            'ai_notes' => 'SOAP terlalu pendek untuk AI',
                            'updated_at' => now(),
                        ]);
                    continue;
                }

                $result = $this->callAiIcdSuggestSS($soapText, $payload);

                if ($result) {
                    if (!empty($result['diagnosa'])) $payload['diagnosa'] = $result['diagnosa'];
                    if (!empty($result['prosedur'])) $payload['prosedur'] = $result['prosedur'];
                    if (!empty($result['keluhan_utama'])) $payload['keluhan_utama'] = $result['keluhan_utama'];
                    if (!empty($result['alergi'])) $payload['alergi'] = $result['alergi'];
                    $payload['rejected_diagnosa'] = $result['rejected_diagnosa'] ?? [];
                    $payload['rejected_prosedur'] = $result['rejected_prosedur'] ?? [];

                    DB::table('rstxn_approval_queue')
                        ->where('approval_id', $item->approval_id)
                        ->update([
                            'ai_payload' => json_encode($payload),
                            'ai_confidence' => $result['confidence'] ?? 0,
                            'ai_notes' => $result['notes'] ?? '',
                            'ai_model' => config('ai.model'),
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('rstxn_approval_queue')
                        ->where('approval_id', $item->approval_id)
                        ->update([
                            'ai_model' => 'skipped',
                            'ai_notes' => 'AI tidak menghasilkan output',
                            'updated_at' => now(),
                        ]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AI suggest SATUSEHAT error', [
                    'approval_id' => $item->approval_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->js('setTimeout(() => $wire.aiProcessNextBatch(), 300)');
    }

    private function extractSoapTextForSS(array $emr): string
    {
        $parts = [];
        $a = $emr['anamnesa'] ?? [];
        $p = $emr['pemeriksaan'] ?? [];
        $r = $emr['perencanaan'] ?? [];

        $keluhan = $a['keluhanUtama']['keluhanUtama'] ?? '';
        $rps = $a['riwayatPenyakitSekarangUmum']['riwayatPenyakitSekarangUmum'] ?? '';
        if ($keluhan || $rps) $parts[] = 'S: ' . trim($keluhan . '. ' . $rps);

        $tv = $p['tandaVital'] ?? [];
        $fisikParts = array_filter([
            !empty($tv['sistolik']) ? 'TD ' . $tv['sistolik'] . '/' . ($tv['distolik'] ?? '') : '',
            !empty($tv['frekuensiNadi']) ? 'Nadi ' . $tv['frekuensiNadi'] : '',
            !empty($tv['suhu']) ? 'Suhu ' . $tv['suhu'] : '',
            !empty($tv['frekuensiNafas']) ? 'RR ' . $tv['frekuensiNafas'] : '',
        ]);
        $fisik = $p['fisik'] ?? [];
        $fisikText = is_string($fisik) ? $fisik : '';
        if ($fisikParts || $fisikText) $parts[] = 'O: ' . implode(', ', $fisikParts) . ($fisikText ? '. ' . $fisikText : '');

        $dxFree = $emr['diagnosisFreeText'] ?? '';
        $dxList = collect($emr['diagnosis'] ?? [])->pluck('diagDesc')->filter()->implode('; ');
        if ($dxFree || $dxList) $parts[] = 'A: ' . trim($dxFree . '. ' . $dxList);

        $terapi = $r['terapi']['terapi'] ?? '';
        $tindakLanjut = $r['tindakLanjut']['tindakLanjut'] ?? '';
        if ($terapi || $tindakLanjut) $parts[] = 'P: ' . trim($terapi . '. ' . $tindakLanjut);

        return implode("\n", $parts);
    }

    private function callAiIcdSuggestSS(string $soapText, array $existingPayload = []): ?array
    {
        $systemPrompt = <<<'PROMPT'
Kamu adalah coder medis berpengalaman di rumah sakit Indonesia untuk pengiriman data SATUSEHAT.
Tugas: dari catatan SOAP rawat jalan, tentukan:
1. Kode ICD-10 (diagnosa) dan ICD-9-CM (prosedur)
2. Kode SNOMED CT untuk keluhan utama (chief complaint) — dari value_set "condition-code"
3. Kode SNOMED CT untuk alergi (jika ada) — dari value_set "substance-code"

Aturan:
- Diagnosa pertama harus Primary (diagnosa utama).
- Kode harus spesifik (gunakan subkategori).
- Untuk konsultasi rawat jalan rutin TANPA tindakan: kosongkan prosedur.
- keluhan_utama: SNOMED CT code yang paling cocok dengan keluhan utama pasien. Wajib diisi.
- alergi: isi jika ada informasi alergi di catatan SOAP. Kosongkan jika tidak ada.
- Setiap item WAJIB punya "reason".
- Confidence 0-100 berdasarkan keyakinan coding.

Jawab HANYA dalam format JSON (tanpa markdown fence):
{
  "diagnosa": [
    {"code": "E11.9", "desc": "Type 2 diabetes mellitus without complications", "kategori": "Primary", "confidence": 90, "reason": "GDA 186 + keluhan sering BAK"}
  ],
  "prosedur": [],
  "keluhan_utama": {"snomedCode": "29857009", "snomedDisplayEn": "Chest pain", "reason": "Keluhan nyeri dada"},
  "alergi": [
    {"snomedCode": "387207008", "snomedDisplayEn": "Ibuprofen", "category": "medication", "reason": "Pasien menyatakan alergi ibuprofen"}
  ],
  "confidence": 85,
  "notes": "Alasan confidence."
}
PROMPT;

        $response = Http::timeout(config('ai.timeout', 60))
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('ai.api_key'),
                'Content-Type' => 'application/json',
            ])
            ->post(config('ai.api_url'), [
                'model' => config('ai.model'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => "Rawat Jalan:\n\n" . $soapText],
                ],
                'max_tokens' => config('ai.max_tokens', 2000),
                'temperature' => 0.2,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('AI API error: ' . $response->status());
        }

        $content = $response->json('choices.0.message.content', '');
        $content = preg_replace('/^```json?\s*/m', '', $content);
        $content = preg_replace('/```\s*$/m', '', $content);
        $parsed = json_decode(trim($content), true);

        if (!$parsed || !isset($parsed['diagnosa'])) {
            throw new \RuntimeException('AI response tidak valid');
        }

        // Validate against master diagnosa (rsmst_mstdiags)
        $validatedDx = [];
        $rejectedDx = [];
        foreach ($parsed['diagnosa'] ?? [] as $d) {
            $origCode = trim($d['code'] ?? '');
            if (!$origCode) continue;
            $code = $origCode;

            $master = DB::table('rsmst_mstdiags')->where('diag_id', $code)->first();

            // Fallback: H81.10 → H81.1, A09.0 → A09
            if (!$master && preg_match('/^(.+)\.\d$/', $code, $m)) {
                $master = DB::table('rsmst_mstdiags')->where('diag_id', $m[1])->first();
                if ($master) $code = $m[1];
            }
            if (!$master && str_contains($code, '.')) {
                $parent = substr($code, 0, strrpos($code, '.'));
                $master = DB::table('rsmst_mstdiags')->where('diag_id', $parent)->first();
                if ($master) $code = $parent;
            }

            if (!$master) {
                $rejectedDx[] = $origCode . ' — ' . ($d['desc'] ?? '?');
                continue;
            }

            $validatedDx[] = [
                'code' => $code,
                'desc' => $master->diag_desc ?: ($d['desc'] ?? ''),
                'kategori' => $d['kategori'] ?? 'Secondary',
                'confidence' => $d['confidence'] ?? 0,
                'reason' => $d['reason'] ?? '',
                'validated' => true,
                'original_code' => $origCode !== $code ? $origCode : null,
            ];
        }

        $validatedProc = [];
        $rejectedProc = [];
        foreach ($parsed['prosedur'] ?? [] as $p) {
            $code = trim($p['code'] ?? '');
            if (!$code) continue;
            $dbDesc = DB::table('rsmst_mstprocedures')->where('proc_id', $code)->value('proc_desc');
            if (!$dbDesc) {
                $rejectedProc[] = $code . ' — ' . ($p['desc'] ?? '?');
                continue;
            }
            $validatedProc[] = [
                'code' => $code,
                'desc' => $dbDesc,
                'confidence' => $p['confidence'] ?? 0,
                'reason' => $p['reason'] ?? '',
                'validated' => true,
            ];
        }

        // Validate keluhan utama SNOMED
        $keluhanUtama = null;
        $existingKeluhan = $existingPayload['keluhan_utama'] ?? [];
        $aiKeluhan = $parsed['keluhan_utama'] ?? null;
        if ($aiKeluhan && !empty($aiKeluhan['snomedCode'])) {
            $sCode = trim($aiKeluhan['snomedCode']);
            $dbDisplay = DB::table('rsmst_snomed_codes')
                ->where('snomed_code', $sCode)
                ->where('value_set', 'condition-code')
                ->value('display_en');
            $keluhanUtama = [
                'text' => $existingKeluhan['text'] ?? '',
                'snomedCode' => $sCode,
                'snomedDisplayEn' => $dbDisplay ?: ($aiKeluhan['snomedDisplayEn'] ?? ''),
                'snomedDisplayId' => $aiKeluhan['snomedDisplayId'] ?? '',
                'reason' => $aiKeluhan['reason'] ?? '',
                'validated' => $dbDisplay !== null,
            ];
        }

        // Validate alergi SNOMED
        $validatedAlergi = [];
        foreach ($parsed['alergi'] ?? [] as $al) {
            $sCode = trim($al['snomedCode'] ?? '');
            if (!$sCode) continue;
            $dbDisplay = DB::table('rsmst_snomed_codes')
                ->where('snomed_code', $sCode)
                ->where('value_set', 'substance-code')
                ->value('display_en');
            $validatedAlergi[] = [
                'snomedCode' => $sCode,
                'snomedDisplayEn' => $dbDisplay ?: ($al['snomedDisplayEn'] ?? ''),
                'category' => $al['category'] ?? 'medication',
                'reason' => $al['reason'] ?? '',
                'validated' => $dbDisplay !== null,
            ];
        }

        // Build warning notes for rejected codes
        $warnings = [];
        if (!empty($rejectedDx)) {
            $warnings[] = 'Diagnosa tidak ada di master: ' . implode('; ', $rejectedDx);
        }
        if (!empty($rejectedProc)) {
            $warnings[] = 'Prosedur tidak ada di master: ' . implode('; ', $rejectedProc);
        }

        $aiNotes = trim($parsed['notes'] ?? '');
        if (!empty($warnings)) {
            $warningText = implode('. ', $warnings);
            $aiNotes = $aiNotes ? $aiNotes . "\n⚠ " . $warningText : '⚠ ' . $warningText;
        }

        return [
            'diagnosa' => $validatedDx,
            'prosedur' => $validatedProc,
            'keluhan_utama' => $keluhanUtama,
            'alergi' => $validatedAlergi,
            'confidence' => (int) ($parsed['confidence'] ?? 0),
            'notes' => $aiNotes,
            'rejected_diagnosa' => $rejectedDx,
            'rejected_prosedur' => $rejectedProc,
        ];
    }

    // ── Kirim ke SATUSEHAT (batch 5) ──

    public function kirimBatch(): void
    {
        $batchSize = 5;

        $queryKirim = DB::table('rstxn_approval_queue')
            ->where('module', 'satusehat')
            ->where('status', 'approved')
            ->orderBy('approval_id');

        if (!empty($this->selectedIds)) {
            $queryKirim->whereIn('approval_id', $this->selectedIds);
        }

        $approved = $queryKirim->get();

        if ($approved->isEmpty()) {
            $this->dispatch('toast', type: 'info', message: 'Tidak ada item yang sudah di-approve untuk dikirim.');
            return;
        }

        $batch = $approved->take($batchSize);
        $remaining = $approved->count() - $batch->count();

        $this->sending = true;
        $this->sendTotal = $batch->count();
        $this->sendProgress = 0;
        $sent = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($batch as $idx => $item) {
            if ($idx > 0) {
                usleep(2000000);
            }
            $this->sendProgress++;
            $payload = json_decode($item->ai_payload ?? '{}', true) ?: [];

            // Recalculate readiness from current payload
            $kirimReadiness = [];
            if (empty($payload['patient_uuid'] ?? '')) $kirimReadiness[] = 'Patient ID (IHS) kosong';
            if (empty($payload['dr_uuid'] ?? '')) $kirimReadiness[] = 'Dokter IHS kosong';
            if (empty($payload['poli_uuid'] ?? '')) $kirimReadiness[] = 'Poli UUID kosong';
            if (empty($payload['diagnosa'] ?? [])) $kirimReadiness[] = 'Belum ada diagnosa ICD';
            if (!empty($kirimReadiness)) {
                DB::table('rstxn_approval_queue')
                    ->where('approval_id', $item->approval_id)
                    ->update([
                        'status' => 'failed',
                        'exec_status' => 'failed',
                        'exec_error' => 'Setup belum lengkap: ' . implode(', ', $kirimReadiness),
                        'updated_at' => now(),
                    ]);
                $skipped++;
                continue;
            }

            try {
                $emr = $this->findDataRJ((int) $item->ref_no);
                if (empty($emr)) {
                    throw new \RuntimeException('EMR tidak ditemukan');
                }

                // Inject AI diagnosa into EMR for sending
                if (!empty($payload['diagnosa'])) {
                    $emr['diagnosis'] = collect($payload['diagnosa'])->map(fn($d) => [
                        'icdX' => $d['code'],
                        'diagId' => $d['code'],
                        'diagDesc' => $d['desc'],
                        'kategoriDiagnosa' => $d['kategori'] ?? 'Secondary',
                    ])->toArray();
                }

                if (!empty($payload['prosedur'])) {
                    $emr['procedure'] = collect($payload['prosedur'])->map(fn($p) => [
                        'procedureId' => $p['code'],
                        'procedureDesc' => $p['desc'],
                    ])->toArray();
                }

                $pasien = [
                    'satusehatId' => $payload['patient_uuid'] ?? '',
                    'regName' => $item->reg_name ?? '',
                ];

                $result = $this->kirimRawatJalan($emr, $pasien);

                if ($result['success']) {
                    // Save satusehat state back to EMR JSON
                    $emr['satusehat'] = $result['satusehat'];
                    $this->updateSatuSehatState($item->ref_no, $result['satusehat']);

                    $payload['satusehat'] = $result['satusehat'];
                    $payload['send_messages'] = $result['messages'];

                    DB::table('rstxn_approval_queue')
                        ->where('approval_id', $item->approval_id)
                        ->update([
                            'status' => 'executed',
                            'exec_status' => 'success',
                            'ai_payload' => json_encode($payload),
                            'updated_at' => now(),
                        ]);
                    $sent++;
                } else {
                    DB::table('rstxn_approval_queue')
                        ->where('approval_id', $item->approval_id)
                        ->update([
                            'status' => 'failed',
                            'exec_status' => 'failed',
                            'exec_error' => mb_substr(implode('; ', $result['messages']), 0, 500),
                            'updated_at' => now(),
                        ]);
                    $errors++;
                }
            } catch (\Throwable $e) {
                DB::table('rstxn_approval_queue')
                    ->where('approval_id', $item->approval_id)
                    ->update([
                        'status' => 'failed',
                        'exec_status' => 'failed',
                        'exec_error' => mb_substr($e->getMessage(), 0, 500),
                        'updated_at' => now(),
                    ]);
                $errors++;
                \Illuminate\Support\Facades\Log::error('SATUSEHAT kirim error', [
                    'approval_id' => $item->approval_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->sending = false;
        $msg = $sent . '/' . $this->sendTotal . ' berhasil dikirim.';
        if ($skipped > 0) $msg .= ' ' . $skipped . ' di-skip (setup belum lengkap).';
        if ($errors > 0) $msg .= ' ' . $errors . ' gagal.';
        if ($remaining > 0) $msg .= ' Sisa ' . $remaining . ' — klik Kirim lagi.';
        $this->dispatch('toast', type: $errors > 0 ? 'warning' : 'success', message: $msg);
        $this->incrementVersion('ss-queue-toolbar');
    }

    private function updateSatuSehatState(int $rjNo, array $ssState): void
    {
        try {
            $lob = DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->value('datadaftarpolirj_json');
            $jsonRaw = is_object($lob) && method_exists($lob, 'read') ? $lob->read($lob->size()) : (string) $lob;
            $json = json_decode($jsonRaw ?: '{}', true) ?? [];
            $json['satusehat'] = $ssState;
            DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->update([
                'datadaftarpolirj_json' => json_encode($json),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to save SATUSEHAT state to EMR', [
                'rj_no' => $rjNo,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ── UI helpers ──

    private function statusBadge(string $status): array
    {
        return match ($status) {
            'pending'  => ['label' => 'Pending', 'bg' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'],
            'approved' => ['label' => 'Approved', 'bg' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'],
            'edited'   => ['label' => 'Edited', 'bg' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300'],
            'executed'  => ['label' => 'Terkirim', 'bg' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'],
            'failed'   => ['label' => 'Gagal', 'bg' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'],
            'rejected' => ['label' => 'Ditolak', 'bg' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'],
            default    => ['label' => $status, 'bg' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'],
        };
    }
};
?>

<div>
    <x-page-title
        title="Approval Hub — SATUSEHAT Rawat Jalan"
        subtitle="Scan RJ → AI suggest ICD → Approve → Kirim ke SATUSEHAT (batch 5)" />

    {{-- TOOLBAR --}}
    <div class="sticky z-30 px-4 py-3 mb-4 bg-canvas border-b border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700"
         wire:key="ss-queue-toolbar-{{ $renderVersions['ss-queue-toolbar'] ?? 0 }}">
        <div class="flex items-end gap-3 overflow-x-auto">

            {{-- Scan --}}
            <div class="mt-auto">
                <x-primary-button wire:click="scanTransaksi" wire:loading.attr="disabled" wire:target="scanTransaksi">
                    <span wire:loading.remove wire:target="scanTransaksi" class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Scan
                    </span>
                    <span wire:loading wire:target="scanTransaksi"><x-loading /> Scanning ...</span>
                </x-primary-button>
            </div>

            {{-- Mode --}}
            <div>
                <x-input-label value="Mode" />
                <div class="inline-flex mt-1 rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600">
                    <button type="button" wire:click="$set('scanMode', 'bulanan')"
                        class="px-3 py-1.5 text-xs font-medium transition-colors
                            {{ $scanMode === 'bulanan' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                        Bulanan
                    </button>
                    <button type="button" wire:click="$set('scanMode', 'harian')"
                        class="px-3 py-1.5 text-xs font-medium transition-colors border-l border-gray-300 dark:border-gray-600
                            {{ $scanMode === 'harian' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                        Harian
                    </button>
                </div>
            </div>

            {{-- Date input --}}
            <div>
                <x-input-label value="{{ $scanMode === 'bulanan' ? 'Bulan' : 'Tanggal' }}" />
                <div class="relative mt-1">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    @if ($scanMode === 'bulanan')
                        <x-text-input type="text" wire:model="scanBulan"
                            class="block w-full pl-10 sm:w-28" placeholder="mm/yyyy" maxlength="7" />
                    @else
                        <x-text-input type="text" wire:model="scanTanggal"
                            class="block w-full pl-10 sm:w-36" placeholder="dd/mm/yyyy" maxlength="10" />
                    @endif
                </div>
            </div>

            {{-- Status filter buttons (grid 3x2) --}}
            @php $sc = $this->statusCounts; @endphp
            <div class="grid grid-cols-3 gap-1 mt-auto shrink-0">
                <button type="button" wire:click="$set('filterStatus', 'pending')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg border transition-all
                        {{ $filterStatus === 'pending' ? 'bg-amber-100 border-amber-400 text-amber-700 font-semibold dark:bg-amber-900/30 dark:border-amber-500 dark:text-amber-300' : 'bg-canvas border-gray-300 text-muted hover:bg-amber-50 dark:border-gray-600 dark:hover:bg-amber-900/20' }}">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>{{ $sc->pending ?? 0 }} Pending
                </button>
                <button type="button" wire:click="$set('filterStatus', 'approved')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg border transition-all
                        {{ $filterStatus === 'approved' ? 'bg-blue-100 border-blue-400 text-blue-700 font-semibold dark:bg-blue-900/30 dark:border-blue-500 dark:text-blue-300' : 'bg-canvas border-gray-300 text-muted hover:bg-blue-50 dark:border-gray-600 dark:hover:bg-blue-900/20' }}">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>{{ $sc->approved ?? 0 }} Approved
                </button>
                <button type="button" wire:click="$set('filterStatus', 'executed')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg border transition-all
                        {{ $filterStatus === 'executed' ? 'bg-emerald-100 border-emerald-400 text-emerald-700 font-semibold dark:bg-emerald-900/30 dark:border-emerald-500 dark:text-emerald-300' : 'bg-canvas border-gray-300 text-muted hover:bg-emerald-50 dark:border-gray-600 dark:hover:bg-emerald-900/20' }}">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>{{ $sc->executed ?? 0 }} Terkirim
                </button>
                <button type="button" wire:click="$set('filterStatus', 'failed')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg border transition-all
                        {{ $filterStatus === 'failed' ? 'bg-red-100 border-red-400 text-red-700 font-semibold dark:bg-red-900/30 dark:border-red-500 dark:text-red-300' : 'bg-canvas border-gray-300 text-muted hover:bg-red-50 dark:border-gray-600 dark:hover:bg-red-900/20' }}">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>{{ $sc->failed ?? 0 }} Failed
                </button>
                <button type="button" wire:click="$set('filterStatus', 'rejected')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg border transition-all
                        {{ $filterStatus === 'rejected' ? 'bg-gray-200 border-gray-400 text-gray-700 font-semibold dark:bg-gray-700 dark:border-gray-500 dark:text-gray-300' : 'bg-canvas border-gray-300 text-muted hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-800' }}">
                    <span class="w-2 h-2 rounded-full bg-gray-400"></span>{{ $sc->rejected ?? 0 }} Ditolak
                </button>
                <button type="button" wire:click="$set('filterStatus', '')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg border transition-all
                        {{ $filterStatus === '' ? 'bg-gray-100 border-gray-400 text-ink font-semibold dark:bg-gray-700 dark:border-gray-500 dark:text-white' : 'bg-canvas border-gray-300 text-muted hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800' }}">
                    {{ $sc->total ?? 0 }} Semua
                </button>
            </div>

            {{-- Run AI / Stop AI --}}
            <div class="mt-auto">
                @if ($aiRunning)
                    <span class="inline-flex items-center gap-1.5 text-xs text-muted">
                        <x-loading class="text-blue-600 dark:text-blue-400" />
                        AI {{ $aiProgress }}/{{ $aiTotal }}
                    </span>
                    <x-danger-button wire:click="stopAi" title="Stop AI">
                        Stop AI
                    </x-danger-button>
                @else
                    <x-info-button wire:click="aiSuggestBatch" wire:loading.attr="disabled"
                        wire:target="aiSuggestBatch"
                        title="AI suggest ICD — jalan terus sampai selesai atau di-stop">
                        <span wire:loading.remove wire:target="aiSuggestBatch" class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z" />
                            </svg>
                            Run AI
                        </span>
                        <span wire:loading wire:target="aiSuggestBatch" class="flex items-center gap-1">
                            <x-loading /> Starting...
                        </span>
                    </x-info-button>
                @endif
            </div>

            {{-- Approve All --}}
            @if ($filterStatus === 'pending' || $filterStatus === '')
                <div class="mt-auto">
                    <x-confirm-button variant="success" action="approveAll()"
                        title="Approve All" message="{{ !empty($selectedIds) ? count($selectedIds) . ' item terpilih' : 'Semua pending yang siap kirim' }} akan di-approve. Lanjutkan?"
                        confirmText="Ya, approve" cancelText="Batal">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Approve {{ !empty($selectedIds) ? '(' . count($selectedIds) . ')' : 'All' }}
                        </span>
                    </x-confirm-button>
                </div>
            @endif

            {{-- Kirim All --}}
            @if ($filterStatus === 'approved' || $filterStatus === '')
                <div class="mt-auto">
                    @if (!empty($batchKirimQueue) || $batchKirimTotal > 0)
                        <span class="inline-flex items-center gap-1.5 text-xs text-muted">
                            <x-loading class="text-teal-600 dark:text-teal-400" />
                            Kirim {{ $batchKirimProgress }}/{{ $batchKirimTotal }}
                        </span>
                        <x-danger-button wire:click="batalKirimAll">
                            Stop Kirim
                        </x-danger-button>
                    @else
                        <x-confirm-button variant="success" action="kirimAllBatch()"
                            title="Kirim ke SATUSEHAT"
                            message="{{ !empty($selectedIds) ? count($selectedIds) . ' item terpilih' : 'Semua approved' }} akan dikirim ke SATUSEHAT secara otomatis sampai selesai. Klik Stop untuk menghentikan. Lanjutkan?"
                            confirmText="Ya, Mulai Kirim" cancelText="Batal">
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                Kirim {{ !empty($selectedIds) ? '(' . count($selectedIds) . ')' : 'All' }}
                            </span>
                        </x-confirm-button>
                    @endif
                </div>
            @endif

            @if (!empty($selectedIds))
                <span class="text-xs font-semibold text-primary dark:text-primary-light px-2 py-0.5 rounded-full bg-primary/10 mt-auto">{{ count($selectedIds) }} dipilih</span>
            @endif

            {{-- RIGHT: per page + Clear --}}
            <div class="flex items-center gap-3 ml-auto shrink-0">
                <x-toolbar-refresh-reset :label="null" />
                <div class="w-20">
                    <x-select-input wire:model.live="itemsPerPage">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </x-select-input>
                </div>
                <x-confirm-button variant="danger" action="clearQueue()"
                    title="Hapus Antrian" message="Semua data antrian SATUSEHAT akan dihapus. Lanjutkan?"
                    confirmText="Ya, hapus semua" cancelText="Batal">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Clear
                </x-confirm-button>
            </div>

        </div>
    </div>

    {{-- PANDUAN --}}
    <div x-data="{ buka: false }" class="mb-4 overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
        <button type="button" x-on:click="buka = !buka"
            class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
            <span class="flex items-center min-w-0 gap-2">
                <svg class="w-4 h-4 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="truncate">Panduan: cara pakai SATUSEHAT Rawat Jalan</span>
            </span>
            <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="buka" x-cloak class="px-4 pb-4 space-y-4 text-sm text-blue-900 dark:text-blue-100">

            {{-- PRASYARAT --}}
            <div>
                <div class="font-semibold">Prasyarat</div>
                <ul class="mt-1 ml-4 space-y-0.5 list-disc">
                    <li>Role user harus <span class="font-semibold">Admin</span> &mdash; fitur AI SATUSEHAT sementara hanya untuk admin.</li>
                    <li>Data master <span class="font-semibold">Patient IHS ID</span> (<span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">rsmst_pasiens.patient_uuid</span>) harus terisi.</li>
                    <li>Data master <span class="font-semibold">Practitioner IHS ID</span> (<span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">rsmst_doctors.dr_uuid</span>) harus terisi.</li>
                    <li>Data master <span class="font-semibold">Location/Poli UUID</span> (<span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">rsmst_polis.poli_uuid</span>) harus terisi.</li>
                    <li>Data SOAP / assessment EMR harus sudah diisi oleh dokter.</li>
                    <li>Koneksi ke server <span class="font-semibold">SATUSEHAT</span> harus aktif (OAuth2 credentials via <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">.env</span>).</li>
                </ul>
            </div>

            {{-- ALUR LENGKAP --}}
            <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                <div class="font-semibold">Alur lengkap</div>
                <ol class="mt-1 ml-4 space-y-1 list-decimal">
                    <li><span class="font-semibold">Scan RJ</span> &mdash; pilih mode
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Bulanan</span> atau
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Harian</span>,
                        isi tanggal/bulan, lalu klik
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Scan</span>.
                        Sistem mengambil semua transaksi RJ beserta cek readiness (Patient IHS, Dokter IHS, Poli UUID).
                        <br><span class="text-xs italic">Item yang belum lengkap setup-nya ditandai &ldquo;Perlu setup&rdquo; dan bisa di-skip.</span></li>
                    <li><span class="font-semibold">Run AI (5)</span> &mdash; klik
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Run AI (5)</span>
                        untuk memproses 5 item pending yang belum punya diagnosa.
                        AI membaca data SOAP dari EMR dan menyarankan kode ICD-10 (diagnosa) dan ICD-9-CM (prosedur).
                        Klik berulang sampai semua terproses.</li>
                    <li><span class="font-semibold">Review &amp; Approve</span> &mdash; klik
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Review</span>
                        untuk buka detail. Periksa diagnosa hasil AI &amp; readiness. Jika sesuai, klik
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Approve</span>.
                        Jika tidak,
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Reject</span>.</li>
                    <li><span class="font-semibold">Kirim (5)</span> &mdash; klik
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Kirim (5)</span>
                        untuk mengirim 5 item approved ke SATUSEHAT.
                        Proses: Encounter &rarr; Condition &rarr; Observation &rarr; Procedure &rarr; dll (14 langkah otomatis).
                        Yang sudah terkirim tidak diulang.</li>
                </ol>
            </div>

            {{-- CLEAR --}}
            <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                <div class="font-semibold">Menghapus antrian</div>
                <ul class="mt-1 ml-4 space-y-0.5 list-disc">
                    <li>Klik tombol <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Clear</span>
                        di toolbar untuk menghapus semua data antrian (ada konfirmasi).</li>
                    <li>Atau cukup klik <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Scan</span>
                        lagi &mdash; antrian lama otomatis diganti dengan hasil scan baru.</li>
                </ul>
            </div>

            {{-- CATATAN --}}
            <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                <div class="font-semibold">Catatan</div>
                <ul class="mt-1 ml-4 space-y-0.5 list-disc">
                    <li>Item tanpa Patient IHS / Dokter IHS / Poli UUID ditandai <span class="font-semibold">&ldquo;Perlu setup manual&rdquo;</span> &mdash; lengkapi dulu di master data, lalu scan ulang.</li>
                    <li>Data EMR asli <span class="font-semibold">tidak pernah ditimpa</span> &mdash; diagnosa AI disimpan terpisah di antrian, diinjeksi saat kirim.</li>
                    <li>Status pengiriman SATUSEHAT disimpan di JSON transaksi (<span class="font-mono text-xs">satuSehat.*</span>).</li>
                </ul>
            </div>

        </div>
    </div>

    {{-- TABLE --}}
    <div class="overflow-hidden bg-canvas dark:bg-gray-800 rounded-2xl border border-hairline dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold uppercase bg-surface-soft dark:bg-gray-900 text-muted">
                    <tr>
                        <th class="px-3 py-3 text-center w-10">
                            <x-toggle :current="count($selectedIds) > 0 && !array_diff($this->rows->pluck('approval_id')->toArray(), $selectedIds) ? '1' : '0'" trueValue="1" falseValue="0" wireClick="toggleSelectAll" />
                        </th>
                        <th class="px-4 py-3">Pasien</th>
                        <th class="px-4 py-3">Poli / Dokter</th>
                        <th class="px-4 py-3">Data Klinis</th>
                        <th class="px-4 py-3 text-center">Readiness</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3">Status Kirim</th>
                        <th class="px-4 py-3 text-center">Tgl RJ</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
            </thead>
            <tbody class="divide-y divide-hairline dark:divide-gray-700">
                @forelse ($this->rows as $row)
                    @php
                        $payload = json_decode($row->ai_payload ?? '{}', true) ?: [];
                        $diagnosa = $payload['diagnosa'] ?? [];
                        $keluhan = $payload['keluhan_utama'] ?? [];
                        $alergiData = $payload['alergi'] ?? [];
                        $alreadySent = $payload['already_sent'] ?? false;

                        $readiness = [];
                        if (empty($payload['patient_uuid'] ?? '')) $readiness[] = 'Patient ID (IHS) kosong';
                        if (empty($payload['dr_uuid'] ?? '')) $readiness[] = 'Dokter IHS kosong';
                        if (empty($payload['poli_uuid'] ?? '')) $readiness[] = 'Poli UUID kosong';
                        if (empty($diagnosa)) $readiness[] = 'Belum ada diagnosa ICD';
                        $isReady = empty($readiness) && !$alreadySent;
                        $needsManual = !empty($readiness) && !$alreadySent;
                        $badge = $this->statusBadge($row->status);

                        $hasKeluhan = !empty($keluhan['text'] ?? '');
                        $hasKeluhanSnomed = !empty($keluhan['snomedCode'] ?? '');
                        $alergiIsList = is_array($alergiData) && (empty($alergiData) || array_is_list($alergiData));
                        $alergiItems = $alergiIsList ? $alergiData : ($alergiData ? [$alergiData] : []);
                        $hasAlergi = !empty($alergiItems);
                        $hasAlergiSnomed = collect($alergiItems)->contains(fn($a) => !empty($a['snomedCode'] ?? ''));
                    @endphp
                    <tr class="transition hover:bg-surface-soft dark:hover:bg-gray-800/50"
                        wire:key="ss-{{ $row->approval_id }}">
                        {{-- Toggle select --}}
                        <td class="px-3 py-3 text-center align-top">
                            <x-toggle :current="in_array($row->approval_id, $selectedIds) ? '1' : '0'" trueValue="1" falseValue="0" wireClick="toggleSelect({{ $row->approval_id }})" />
                        </td>
                        {{-- Pasien --}}
                        <td class="px-4 py-3 align-top">
                            <div class="font-semibold text-ink dark:text-white">{{ $row->reg_name }}</div>
                            <div class="text-xs text-muted">RM <span class="font-mono">{{ $row->reg_no }}</span></div>
                        </td>

                        {{-- Poli / Dokter --}}
                        <td class="px-4 py-3 align-top">
                            <div class="text-xs text-body dark:text-gray-300">{{ $payload['poli_desc'] ?? '-' }}</div>
                            <div class="text-xs text-muted">{{ $payload['dr_name'] ?? '-' }}</div>
                        </td>

                        {{-- Data Klinis --}}
                        <td class="px-4 py-3 align-top">
                            {{-- Diagnosa ICD --}}
                            @if (!empty($diagnosa))
                                @foreach (array_slice($diagnosa, 0, 2) as $d)
                                    <div class="flex items-center gap-1.5 {{ !$loop->first ? 'mt-0.5' : '' }}">
                                        <span class="font-mono text-xs px-1.5 py-0.5 rounded {{ ($d['kategori'] ?? '') === 'Primary' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
                                            {{ $d['code'] ?? '-' }}
                                        </span>
                                        <span class="text-xs text-body dark:text-gray-300 line-clamp-1">{{ $d['desc'] ?? '' }}</span>
                                    </div>
                                @endforeach
                                @if (count($diagnosa) > 2)
                                    <div class="text-[10px] text-muted mt-0.5">+{{ count($diagnosa) - 2 }} lainnya</div>
                                @endif
                            @else
                                <div class="flex items-center gap-1 text-[10px] text-rose-500">✗ Diagnosa ICD kosong</div>
                            @endif
                            {{-- Keluhan Utama SNOMED --}}
                            <div class="flex items-center gap-1 mt-1 text-[10px] {{ $hasKeluhanSnomed ? 'text-emerald-600 dark:text-emerald-400' : ($hasKeluhan ? 'text-amber-600 dark:text-amber-400' : 'text-rose-500') }}">
                                <span>{{ $hasKeluhanSnomed ? '✓' : ($hasKeluhan ? '◐' : '✗') }}</span>
                                <span>Keluhan{{ $hasKeluhanSnomed ? ': ' . \Illuminate\Support\Str::limit($keluhan['snomedDisplayEn'] ?? '', 25) : ($hasKeluhan ? ' (perlu SNOMED)' : ' kosong') }}</span>
                            </div>
                            {{-- Alergi --}}
                            @php
                                $alergiTidakAda = collect($alergiItems)->contains(fn($a) => \App\Support\Terminologi\AlergiSnomed::adalahTidakAdaAlergi($a['snomedCode'] ?? ''));
                            @endphp
                            <div class="flex items-center gap-1 text-[10px] {{ $hasAlergiSnomed ? ($alergiTidakAda ? 'text-gray-500 dark:text-gray-400' : 'text-emerald-600 dark:text-emerald-400') : 'text-gray-400 dark:text-gray-500' }}">
                                <span>{{ $hasAlergiSnomed ? ($alergiTidakAda ? '○' : '✓') : '—' }}</span>
                                <span>{{ $hasAlergiSnomed ? ($alergiTidakAda ? 'Tidak ada alergi' : 'Alergi: ' . collect($alergiItems)->pluck('snomedDisplayEn')->filter()->implode(', ')) : 'Alergi belum diisi' }}</span>
                            </div>
                        </td>

                        {{-- Readiness --}}
                        <td class="px-4 py-3 text-center align-top">
                            @if ($alreadySent)
                                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">Sudah terkirim</span>
                            @elseif ($isReady)
                                <span class="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                    Siap kirim
                                </span>
                            @elseif ($needsManual)
                                <div class="space-y-0.5" x-data="{ show: false }">
                                    <span class="text-xs text-amber-600 dark:text-amber-400 font-semibold cursor-pointer" x-on:click="show = !show">
                                        Perlu setup ({{ count($readiness) }})
                                    </span>
                                    <div x-show="show" x-cloak class="text-left space-y-0.5">
                                        @foreach ($readiness as $issue)
                                            <div class="text-[10px] text-rose-600 dark:text-rose-400">✗ {{ $issue }}</div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3 text-center align-top">
                            <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full {{ $badge['bg'] }}">
                                {{ $badge['label'] }}
                            </span>
                            @if ($row->exec_status === 'failed')
                                <div class="mt-1 text-xs text-error line-clamp-1" title="{{ $row->exec_error }}">
                                    {{ \Illuminate\Support\Str::limit($row->exec_error ?? '', 40) }}
                                </div>
                            @endif
                        </td>

                        {{-- Status Kirim SATUSEHAT --}}
                        <td class="px-4 py-3 align-top">
                            @php
                                $ssFilled = fn($v) => is_array($v) ? count($v) > 0 : !empty($v);
                                $ss = $this->satusehatStates[$row->ref_no] ?? [];
                                $ssItems = [
                                    ['l' => 'Encounter',           's' => $ssFilled($ss['encounterId'] ?? null)],
                                    ['l' => 'Condition',           's' => $ssFilled($ss['conditionIds'] ?? null)],
                                    ['l' => 'Observation',         's' => $ssFilled($ss['observationIds'] ?? null)],
                                    ['l' => 'Procedure',           's' => $ssFilled($ss['procedureIds'] ?? null)],
                                    ['l' => 'Med Request',         's' => $ssFilled($ss['medicationRequestIds'] ?? null)],
                                    ['l' => 'Chief Complaint',     's' => $ssFilled($ss['chiefComplaintId'] ?? null)],
                                    ['l' => 'Allergy',             's' => $ssFilled($ss['allergyId'] ?? null)],
                                    ['l' => 'Med Dispense',        's' => $ssFilled($ss['medicationDispenseIds'] ?? null)],
                                    ['l' => 'Lab',                 's' => $ssFilled($ss['labServiceRequestIds'] ?? null) || $ssFilled($ss['labDiagnosticReportIds'] ?? null)],
                                    ['l' => 'Radiologi',           's' => $ssFilled($ss['radServiceRequestIds'] ?? null) || $ssFilled($ss['radDiagnosticReportIds'] ?? null)],
                                    ['l' => 'Clin Impression',     's' => $ssFilled($ss['clinicalImpressionId'] ?? null)],
                                    ['l' => 'Penilaian',           's' => $ssFilled($ss['penilaianObservationIds'] ?? null)],
                                    ['l' => 'Enc Selesai',         's' => !empty($ss['encounterFinished'])],
                                    ['l' => 'Resume Medis',        's' => $ssFilled($ss['compositionId'] ?? null)],
                                ];
                                $ssSent = collect($ssItems)->where('s', true)->count();
                                $ssTotal = count($ssItems);
                            @endphp
                            <div class="flex flex-wrap gap-0.5 max-w-[220px]">
                                @foreach ($ssItems as $si)
                                    <span title="{{ $si['l'] }}" class="inline-block px-1 py-0.5 rounded text-[9px] leading-none font-medium {{ $si['s'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-600' }}">{{ $si['l'] }}</span>
                                @endforeach
                            </div>
                            <div class="mt-1 text-[10px] font-semibold {{ $ssSent === $ssTotal ? 'text-emerald-600 dark:text-emerald-400' : ($ssSent > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted') }}">
                                {{ $ssSent }}/{{ $ssTotal }} resource terkirim
                            </div>
                        </td>

                        {{-- Tgl RJ --}}
                        <td class="px-4 py-3 text-center align-top whitespace-nowrap">
                            <div class="text-xs font-semibold text-ink dark:text-white">{{ $row->ref_date_display ?? ($payload['rj_date'] ?? '-') }}</div>
                        </td>

                        {{-- Aksi --}}
                        <td class="px-4 py-3 text-center align-top">
                            <div class="flex flex-col items-center gap-1">
                                @if ($row->status === 'pending')
                                    @if ($isReady)
                                        <x-success-button wire:click="quickApprove({{ $row->approval_id }})"
                                            wire:loading.attr="disabled" wire:target="quickApprove({{ $row->approval_id }})"
                                            class="!px-3 !py-1.5 !text-xs !min-w-0 w-full">
                                            <span wire:loading.remove wire:target="quickApprove({{ $row->approval_id }})">Approve</span>
                                            <span wire:loading wire:target="quickApprove({{ $row->approval_id }})"><x-loading /></span>
                                        </x-success-button>
                                    @endif
                                    <x-primary-button wire:click="openReview({{ $row->approval_id }})"
                                        class="!px-3 !py-1.5 !text-xs !min-w-0 w-full">
                                        Review
                                    </x-primary-button>
                                    @if ($needsManual)
                                        <x-secondary-button wire:click="skip({{ $row->approval_id }})"
                                            class="!px-2 !py-1.5 !text-xs !min-w-0 w-full"
                                            title="Skip — perlu setup manual dulu">
                                            Skip
                                        </x-secondary-button>
                                    @endif
                                @elseif ($row->status === 'failed')
                                    <x-warning-button wire:click="openReview({{ $row->approval_id }})"
                                        class="!px-3 !py-1.5 !text-xs !min-w-0 w-full">
                                        Retry
                                    </x-warning-button>
                                    <x-success-button type="button"
                                        x-on:click="$dispatch('daftar-rj.satu-sehat.open', { rjNo: '{{ $row->ref_no }}' })"
                                        class="!px-3 !py-1.5 !text-xs !min-w-0 w-full" title="Buka kirim per-resource">
                                        Kirim
                                    </x-success-button>
                                @elseif (in_array($row->status, ['approved', 'executed']))
                                    <x-secondary-button wire:click="openReview({{ $row->approval_id }})"
                                        class="!px-3 !py-1.5 !text-xs !min-w-0 w-full">
                                        Detail
                                    </x-secondary-button>
                                    <x-success-button type="button"
                                        x-on:click="$dispatch('daftar-rj.satu-sehat.open', { rjNo: '{{ $row->ref_no }}' })"
                                        class="!px-3 !py-1.5 !text-xs !min-w-0 w-full" title="Buka kirim per-resource">
                                        Kirim
                                    </x-success-button>
                                @else
                                    <x-secondary-button wire:click="openReview({{ $row->approval_id }})"
                                        class="!px-3 !py-1.5 !text-xs !min-w-0 w-full">
                                        Detail
                                    </x-secondary-button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-muted italic">
                            Belum ada data. Klik <strong>Scan RJ</strong> untuk memulai.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">{{ $this->rows->links() }}</div>

    {{-- REVIEW MODAL --}}
    <x-modal name="ss-review" size="full" height="full" focusable>
        @if ($reviewId)
            @php
                $reviewPayload = json_decode($reviewData['ai_payload'] ?? '{}', true) ?: [];
                $reviewDx = $reviewPayload['diagnosa'] ?? [];
                $reviewProc = $reviewPayload['prosedur'] ?? [];
                $reviewKeluhan = $reviewPayload['keluhan_utama'] ?? [];
                $reviewAlergi = $reviewPayload['alergi'] ?? [];

                $reviewReadiness = [];
                if (empty($reviewPayload['patient_uuid'] ?? '')) $reviewReadiness[] = 'Patient ID (IHS) kosong';
                if (empty($reviewPayload['dr_uuid'] ?? '')) $reviewReadiness[] = 'Dokter IHS kosong';
                if (empty($reviewPayload['poli_uuid'] ?? '')) $reviewReadiness[] = 'Poli UUID kosong';
                if (empty($reviewPayload['diagnosa'] ?? [])) $reviewReadiness[] = 'Belum ada diagnosa ICD';
                $reviewBadge = $this->statusBadge($reviewData['status'] ?? 'pending');
            @endphp

            {{-- HEADER --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-hairline dark:border-gray-700 shrink-0">
                <div class="min-w-0">
                    <h2 class="text-lg font-bold text-ink dark:text-white truncate">
                        Review SATUSEHAT — {{ $reviewData['reg_name'] ?? '-' }}
                    </h2>
                    <p class="text-sm text-muted truncate">
                        RM {{ $reviewData['reg_no'] ?? '-' }}
                        — {{ $reviewPayload['poli_desc'] ?? '-' }}
                        — {{ $reviewPayload['dr_name'] ?? '-' }}
                        <span class="inline-block ml-2 px-2 py-0.5 text-xs font-semibold rounded-full {{ $reviewBadge['bg'] }}">{{ $reviewBadge['label'] }}</span>
                    </p>
                </div>
                <x-icon-button color="gray" x-on:click="$dispatch('close-modal', {name: 'ss-review'})" class="shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </x-icon-button>
            </div>

            {{-- BODY --}}
            <div class="flex-1 overflow-y-auto px-6 py-5">
                @php
                    $reviewEmrPct = $reviewPayload['emr_percent'] ?? 0;
                    $reviewEmrSections = $reviewPayload['emr_sections'] ?? [];
                    $reviewEmrChecklist = $reviewPayload['emr_checklist'] ?? [];
                    $ssFilled = fn($v) => is_array($v) ? count($v) > 0 : !empty($v);
                    $ss = $reviewPayload['satusehat'] ?? [];
                    $reviewSsItems = [
                        ['label' => 'Encounter',           'sent' => $ssFilled($ss['encounterId'] ?? null)],
                        ['label' => 'Condition',           'sent' => $ssFilled($ss['conditionIds'] ?? null)],
                        ['label' => 'Observation',         'sent' => $ssFilled($ss['observationIds'] ?? null)],
                        ['label' => 'Procedure',           'sent' => $ssFilled($ss['procedureIds'] ?? null)],
                        ['label' => 'Med Request',         'sent' => $ssFilled($ss['medicationRequestIds'] ?? null)],
                        ['label' => 'Chief Complaint',     'sent' => $ssFilled($ss['chiefComplaintId'] ?? null)],
                        ['label' => 'Allergy',             'sent' => $ssFilled($ss['allergyId'] ?? null)],
                        ['label' => 'Med Dispense',        'sent' => $ssFilled($ss['medicationDispenseIds'] ?? null)],
                        ['label' => 'Lab',                 'sent' => $ssFilled($ss['labServiceRequestIds'] ?? null) || $ssFilled($ss['labDiagnosticReportIds'] ?? null)],
                        ['label' => 'Radiologi',           'sent' => $ssFilled($ss['radServiceRequestIds'] ?? null) || $ssFilled($ss['radDiagnosticReportIds'] ?? null)],
                        ['label' => 'Clinical Impression', 'sent' => $ssFilled($ss['clinicalImpressionId'] ?? null)],
                        ['label' => 'Penilaian',           'sent' => $ssFilled($ss['penilaianObservationIds'] ?? null)],
                        ['label' => 'Encounter Selesai',   'sent' => !empty($ss['encounterFinished'])],
                        ['label' => 'Resume Medis',        'sent' => $ssFilled($ss['compositionId'] ?? null)],
                    ];
                    $sectionLabelsReview = ['s' => 'S — Anamnesa', 'o' => 'O — Pemeriksaan', 'a' => 'A — Diagnosa', 'p' => 'P — Perencanaan', 'n' => 'N — Penilaian', 'k' => 'K — SNOMED'];
                @endphp
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    {{-- KOLOM KIRI: Info, Readiness, EMR Rekam Medis --}}
                    <div class="space-y-5">

                        {{-- Tombol Rekam Medis Lengkap --}}
                        <div>
                            <button type="button"
                                x-on:click="$dispatch('cetak-rekam-medis.open', { rjNo: {{ (int) ($reviewData['ref_no'] ?? 0) }} })"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-purple-700 bg-purple-100 rounded-xl hover:bg-purple-200 dark:text-purple-300 dark:bg-purple-900/30 dark:hover:bg-purple-900/50 transition border border-purple-200 dark:border-purple-800">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                                Rekam Medis Lengkap
                            </button>
                        </div>

                        {{-- Readiness --}}
                        @if (!empty($reviewReadiness))
                            <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                                <div class="text-xs font-semibold text-amber-800 dark:text-amber-300 mb-2">Setup belum lengkap</div>
                                @foreach ($reviewReadiness as $issue)
                                    <div class="text-xs text-amber-700 dark:text-amber-400">✗ {{ $issue }}</div>
                                @endforeach
                                <div class="mt-2 text-xs text-muted">Setup dulu di master, lalu scan ulang.</div>
                            </div>
                        @endif

                        {{-- AI Notes --}}
                        @if (!empty($reviewData['ai_notes']))
                            <div class="p-4 rounded-xl bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-800">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-semibold text-violet-800 dark:text-violet-300">AI Coding Suggest</span>
                                    <span class="text-xs font-bold {{ ($reviewData['ai_confidence'] ?? 0) >= 80 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $reviewData['ai_confidence'] ?? 0 }}%</span>
                                </div>
                                <div class="text-sm text-violet-900 dark:text-violet-200 whitespace-pre-line">{{ $reviewData['ai_notes'] }}</div>
                            </div>
                        @endif

                        {{-- Rejected codes (tidak ada di master) --}}
                        @php
                            $rejDx = $reviewPayload['rejected_diagnosa'] ?? [];
                            $rejProc = $reviewPayload['rejected_prosedur'] ?? [];
                        @endphp
                        @if (!empty($rejDx) || !empty($rejProc))
                            <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800">
                                <div class="text-xs font-semibold text-rose-800 dark:text-rose-300 mb-2">Kode tidak ada di master RS</div>
                                @foreach ($rejDx as $rej)
                                    <div class="flex items-center gap-1.5 text-xs text-rose-700 dark:text-rose-400">
                                        <span class="font-mono px-1 py-0.5 rounded bg-rose-100 dark:bg-rose-900/30">ICD</span>
                                        <span>{{ $rej }}</span>
                                    </div>
                                @endforeach
                                @foreach ($rejProc as $rej)
                                    <div class="flex items-center gap-1.5 text-xs text-rose-700 dark:text-rose-400 {{ !$loop->first || !empty($rejDx) ? 'mt-1' : '' }}">
                                        <span class="font-mono px-1 py-0.5 rounded bg-rose-100 dark:bg-rose-900/30">ICD-9</span>
                                        <span>{{ $rej }}</span>
                                    </div>
                                @endforeach
                                <div class="mt-2 text-[10px] text-rose-500 dark:text-rose-500">Kode di atas direkomendasikan AI tapi tidak ditemukan di master diagnosa/prosedur RS. Tambahkan ke master jika diperlukan.</div>
                            </div>
                        @endif

                        {{-- Info Pasien --}}
                        <div class="p-4 rounded-xl bg-surface-soft dark:bg-gray-800 border border-hairline dark:border-gray-700">
                            <div class="text-xs font-bold uppercase tracking-wider text-muted mb-2">Info Pasien</div>
                            <div class="grid grid-cols-1 gap-2 text-sm">
                                <div><span class="text-muted">No. RM:</span> <span class="font-mono text-ink dark:text-white">{{ $reviewData['reg_no'] ?? '-' }}</span></div>
                                <div><span class="text-muted">Poli:</span> <span class="text-ink dark:text-white">{{ $reviewPayload['poli_desc'] ?? '-' }}</span></div>
                                <div><span class="text-muted">Dokter:</span> <span class="text-ink dark:text-white">{{ $reviewPayload['dr_name'] ?? '-' }}</span></div>
                                <div><span class="text-muted">Patient IHS:</span> <span class="font-mono text-xs {{ !empty($reviewPayload['patient_uuid']) ? 'text-emerald-600' : 'text-rose-500' }}">{{ !empty($reviewPayload['patient_uuid']) ? \Illuminate\Support\Str::limit($reviewPayload['patient_uuid'], 20) : 'Kosong' }}</span></div>
                                <div><span class="text-muted">Dokter IHS:</span> <span class="font-mono text-xs {{ !empty($reviewPayload['dr_uuid']) ? 'text-emerald-600' : 'text-rose-500' }}">{{ !empty($reviewPayload['dr_uuid']) ? \Illuminate\Support\Str::limit($reviewPayload['dr_uuid'], 20) : 'Kosong' }}</span></div>
                                <div><span class="text-muted">Poli UUID:</span> <span class="font-mono text-xs {{ !empty($reviewPayload['poli_uuid']) ? 'text-emerald-600' : 'text-rose-500' }}">{{ !empty($reviewPayload['poli_uuid']) ? \Illuminate\Support\Str::limit($reviewPayload['poli_uuid'], 20) : 'Kosong' }}</span></div>
                            </div>
                        </div>

                        {{-- Rekam Medis Lengkap --}}
                        <div class="p-4 rounded-xl bg-surface-soft dark:bg-gray-800 border border-hairline dark:border-gray-700">
                            <div class="flex items-center justify-between mb-3">
                                <div class="text-xs font-bold uppercase tracking-wider text-muted">Rekam Medis</div>
                                @php
                                    $emrBarColor = $reviewEmrPct >= 80 ? 'bg-emerald-500' : ($reviewEmrPct >= 50 ? 'bg-amber-400' : 'bg-rose-400');
                                    $emrTextColor = $reviewEmrPct >= 80 ? 'text-emerald-700 dark:text-emerald-400' : ($reviewEmrPct >= 50 ? 'text-amber-700 dark:text-amber-400' : 'text-rose-700 dark:text-rose-400');
                                @endphp
                                <div class="flex items-center gap-2">
                                    <div class="w-20 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                        <div class="h-full rounded-full {{ $emrBarColor }}" style="width: {{ $reviewEmrPct }}%"></div>
                                    </div>
                                    <span class="text-xs font-bold {{ $emrTextColor }}">{{ $reviewEmrPct }}%</span>
                                </div>
                            </div>
                            @if (!empty($reviewEmrChecklist))
                                <div class="space-y-3">
                                    @foreach ($reviewEmrChecklist as $secKey => $section)
                                        @php $secPct = $reviewEmrSections[$secKey] ?? 0; @endphp
                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-xs font-semibold {{ $secPct >= 80 ? 'text-emerald-700 dark:text-emerald-400' : ($secPct > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400') }}">
                                                    {{ $secPct >= 80 ? '✓' : ($secPct > 0 ? '◐' : '✗') }}
                                                    {{ $section['label'] ?? ($sectionLabelsReview[$secKey] ?? $secKey) }}
                                                    <span class="font-normal text-muted">({{ $section['weight'] ?? 0 }}%)</span>
                                                </span>
                                                <span class="text-[10px] font-mono {{ $secPct >= 80 ? 'text-emerald-600' : ($secPct > 0 ? 'text-amber-600' : 'text-rose-500') }}">{{ $secPct }}%</span>
                                            </div>
                                            @foreach ($section['items'] ?? [] as $item)
                                                <div class="flex items-center gap-1.5 pl-4 text-[11px] {{ $item['filled'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500 dark:text-rose-400' }}">
                                                    <span>{{ $item['filled'] ? '✓' : '✗' }}</span>
                                                    <span>{{ $item['label'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            @elseif (!empty($reviewEmrSections))
                                <div class="space-y-1">
                                    @foreach ($sectionLabelsReview as $key => $label)
                                        @if (isset($reviewEmrSections[$key]))
                                            @php $secPct = $reviewEmrSections[$key]; @endphp
                                            <div class="flex items-center gap-1 text-xs {{ $secPct >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($secPct > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-500 dark:text-rose-400') }}">
                                                <span>{{ $secPct >= 80 ? '✓' : ($secPct > 0 ? '◐' : '✗') }}</span>
                                                <span>{{ $label }}</span>
                                                <span class="ml-auto font-mono">{{ $secPct }}%</span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs italic text-muted">Scan ulang untuk melihat data EMR.</p>
                            @endif
                        </div>

                        {{-- SATUSEHAT Status Kirim --}}
                        <div class="p-4 rounded-xl bg-surface-soft dark:bg-gray-800 border border-hairline dark:border-gray-700">
                            <div class="text-xs font-bold uppercase tracking-wider text-muted mb-3">Status Kirim SATUSEHAT</div>
                            @if (!empty($reviewSsItems))
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($reviewSsItems as $ssItem)
                                        <span title="{{ $ssItem['label'] }} — {{ $ssItem['sent'] ? 'sudah dikirim' : 'belum dikirim' }}"
                                            class="inline-flex items-center gap-0.5 px-2 py-1 rounded-md border text-[10px] font-semibold leading-none {{ $ssItem['sent'] ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800' : 'bg-surface-soft text-muted-soft border-hairline dark:bg-gray-800 dark:text-gray-500 dark:border-gray-700' }}">
                                            @if ($ssItem['sent'])
                                                <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                            @endif
                                            {{ $ssItem['label'] }}
                                        </span>
                                    @endforeach
                                </div>
                                @php $sentCount = collect($reviewSsItems)->where('sent', true)->count(); @endphp
                                <div class="mt-2 text-[10px] text-muted">{{ $sentCount }}/{{ count($reviewSsItems) }} resource terkirim</div>
                            @else
                                <p class="text-xs italic text-muted">Belum ada data — scan ulang.</p>
                            @endif
                        </div>

                        {{-- Catatan Review --}}
                        <div>
                            <x-input-label value="Catatan review" />
                            <textarea wire:model="reviewNotes" rows="3" placeholder="Catatan review (opsional)..."
                                class="w-full mt-1 px-3 py-2 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-600 dark:text-white focus:ring-brand focus:border-brand"></textarea>
                        </div>
                    </div>

                    {{-- KOLOM TENGAH + KANAN: Diagnosa, Prosedur, SNOMED --}}
                    <div class="space-y-5">

                        {{-- Diagnosa ICD-10 --}}
                        <div>
                            <h3 class="text-sm font-bold text-ink dark:text-white uppercase tracking-wider mb-3">Diagnosa (ICD-10)</h3>
                            <div class="space-y-2">
                                @forelse ($reviewDx as $d)
                                    <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-soft dark:bg-gray-800 border border-hairline dark:border-gray-700">
                                        <span class="font-mono text-xs px-1.5 py-0.5 rounded font-semibold {{ ($d['kategori'] ?? '') === 'Primary' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
                                            {{ ($d['kategori'] ?? '') === 'Primary' ? 'P' : 'S' }}
                                        </span>
                                        <span class="font-mono text-sm font-semibold text-ink dark:text-white min-w-[70px]">{{ $d['code'] ?? '' }}</span>
                                        <span class="flex-1 text-sm text-body dark:text-gray-300">{{ $d['desc'] ?? '' }}</span>
                                        @if (!empty($d['reason']))
                                            <span class="text-xs text-muted max-w-[200px] text-right">{{ $d['reason'] }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm italic text-muted">Belum ada diagnosa — klik Run AI di toolbar.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {{-- Prosedur ICD-9-CM --}}
                            <div>
                                <h3 class="text-sm font-bold text-ink dark:text-white uppercase tracking-wider mb-3">Prosedur (ICD-9-CM)</h3>
                                <div class="space-y-2">
                                    @forelse ($reviewProc as $p)
                                        <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-soft dark:bg-gray-800 border border-hairline dark:border-gray-700">
                                            <span class="font-mono text-sm font-semibold text-ink dark:text-white min-w-[70px]">{{ $p['code'] ?? '' }}</span>
                                            <span class="flex-1 text-sm text-body dark:text-gray-300">{{ $p['desc'] ?? '' }}</span>
                                            @if (!empty($p['reason']))
                                                <span class="text-xs text-muted max-w-[150px] text-right">{{ $p['reason'] }}</span>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-sm italic text-muted">Tidak ada prosedur.</p>
                                    @endforelse
                                </div>
                            </div>

                            {{-- Keluhan Utama (SNOMED) --}}
                            <div>
                                <h3 class="text-sm font-bold text-ink dark:text-white uppercase tracking-wider mb-3">Keluhan Utama (SNOMED)</h3>
                                <div class="p-3 rounded-xl bg-surface-soft dark:bg-gray-800 border border-hairline dark:border-gray-700">
                                    @if (!empty($reviewKeluhan['text'] ?? ''))
                                        <div class="text-sm text-body dark:text-gray-300 mb-2">{{ $reviewKeluhan['text'] }}</div>
                                    @endif
                                    @if (!empty($reviewKeluhan['snomedCode'] ?? ''))
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300">
                                                {{ $reviewKeluhan['snomedCode'] }}
                                            </span>
                                            <span class="text-sm text-body dark:text-gray-300">{{ $reviewKeluhan['snomedDisplayEn'] ?? '' }}</span>
                                            @if (isset($reviewKeluhan['validated']))
                                                <span class="w-2 h-2 rounded-full shrink-0 {{ $reviewKeluhan['validated'] ? 'bg-emerald-500' : 'bg-amber-500' }}"
                                                    title="{{ $reviewKeluhan['validated'] ? 'Kode valid di database' : 'Kode tidak ditemukan di database' }}"></span>
                                            @endif
                                        </div>
                                        @if (!empty($reviewKeluhan['reason']))
                                            <div class="mt-1 text-xs text-muted">{{ $reviewKeluhan['reason'] }}</div>
                                        @endif
                                    @else
                                        <p class="text-sm italic text-muted">Belum ada kode SNOMED — klik Run AI di toolbar.</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Alergi (SNOMED) --}}
                        <div>
                            <h3 class="text-sm font-bold text-ink dark:text-white uppercase tracking-wider mb-3">Alergi (SNOMED)</h3>
                            <div class="space-y-2">
                                @if (is_array($reviewAlergi) && !empty($reviewAlergi))
                                    @foreach ($reviewAlergi as $al)
                                        <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-soft dark:bg-gray-800 border border-hairline dark:border-gray-700">
                                            <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300">
                                                {{ $al['snomedCode'] ?? '' }}
                                            </span>
                                            <span class="flex-1 text-sm text-body dark:text-gray-300">{{ $al['snomedDisplayEn'] ?? '' }}</span>
                                            <span class="text-xs text-muted">{{ $al['category'] ?? '' }}</span>
                                            @if (isset($al['validated']))
                                                <span class="w-2 h-2 rounded-full shrink-0 {{ $al['validated'] ? 'bg-emerald-500' : 'bg-amber-500' }}"
                                                    title="{{ $al['validated'] ? 'Kode valid di database' : 'Kode tidak ditemukan di database' }}"></span>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    <p class="text-sm italic text-muted">Tidak ada data alergi.</p>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="flex items-center justify-between px-6 py-4 border-t border-hairline dark:border-gray-700 shrink-0 bg-surface-soft/50 dark:bg-gray-900/50">
                <div class="flex items-center gap-2">
                    @if (in_array($reviewData['status'] ?? '', ['pending', 'failed']))
                        <x-primary-button wire:click="approve" wire:loading.attr="disabled" wire:target="approve">
                            <span wire:loading.remove wire:target="approve">Approve</span>
                            <span wire:loading wire:target="approve"><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                        <x-danger-button wire:click="reject" wire:loading.attr="disabled">Reject</x-danger-button>
                    @endif
                    @if (in_array($reviewData['status'] ?? '', ['approved', 'executed', 'failed']))
                        <x-success-button type="button"
                            x-on:click="$dispatch('close-modal', {name: 'ss-review'}); $dispatch('daftar-rj.satu-sehat.open', { rjNo: '{{ $reviewData['ref_no'] ?? '' }}' })">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                Buka Kirim SATUSEHAT
                            </span>
                        </x-success-button>
                    @endif
                </div>
                <x-secondary-button x-on:click="$dispatch('close-modal', {name: 'ss-review'})">Tutup</x-secondary-button>
            </div>
        @endif
    </x-modal>

    {{-- Cetak Rekam Medis RJ — komponen existing --}}
    <livewire:pages::components.rekam-medis.rj.cetak-rekam-medis.cetak-rekam-medis-open
        wire:key="ss-queue-cetak-rm-rj" />

    {{-- Modal Kirim SATUSEHAT per-resource (14 kartu) --}}
    <livewire:pages::transaksi.rj.daftar-rj.satu-sehat-rj-actions
        wire:key="ss-queue-kirim-rj" />

</div>
