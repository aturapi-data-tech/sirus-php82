<?php
// resources/views/pages/transaksi/approval-hub/casemix-queue/casemix-queue.blade.php
// Daftar antrian approval Casemix — AI suggest ICD-10/9, tim casemix review.

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait, EmrRJTrait, EmrRITrait, MasterPasienTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['casemix-queue-toolbar'];

    #[Session(key: 'approval-casemix-searchKeyword')]
    public string $searchKeyword = '';
    #[Session(key: 'approval-casemix-filterStatus')]
    public string $filterStatus = 'pending';
    #[Session(key: 'approval-casemix-filterRefType')]
    public string $filterRefType = '';
    #[Session(key: 'approval-casemix-itemsPerPage')]
    public int $itemsPerPage = 25;

    // Review modal state
    public ?int $reviewId = null;
    public array $reviewData = [];
    public array $editedDiagnosa = [];
    public array $editedProsedur = [];
    public string $reviewNotes = '';

    // EMR data transaksi spesifik
    public array $emrData = [];

    // Berkas BPJS status
    public array $berkasStatus = [];

    private array $berkasLabels = [
        1 => 'SEP',
        2 => 'GROUPING',
        3 => 'REKAM MEDIS',
        4 => 'SKDP',
        5 => 'LAIN-LAIN',
    ];
    private const SLOT_LAB_OFFSET = 100;
    private const SLOT_RAD_OFFSET = 200;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    public function updatedFilterStatus(): void { $this->resetPage(); $this->incrementVersion('casemix-queue-toolbar'); }
    public function updatedFilterRefType(): void { $this->resetPage(); $this->incrementVersion('casemix-queue-toolbar'); }
    public function updatedItemsPerPage(): void { $this->resetPage(); $this->incrementVersion('casemix-queue-toolbar'); }
    public function updatedSearchKeyword(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterRefType']);
        $this->filterStatus = 'pending';
        $this->incrementVersion('casemix-queue-toolbar');
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $query = DB::table('rstxn_approval_queue')
            ->where('module', 'casemix')
            ->select([
                'approval_id', 'ref_no', 'ref_type', 'reg_no', 'reg_name',
                'vno_sep', 'ai_payload', 'ai_confidence', 'ai_notes',
                'status', 'human_payload', 'reviewer', 'review_notes',
                'exec_status', 'exec_error',
                DB::raw("to_char(created_at, 'DD/MM/YYYY HH24:MI') as created_display"),
                DB::raw("to_char(reviewed_at, 'DD/MM/YYYY HH24:MI') as reviewed_display"),
                DB::raw("to_char(executed_at, 'DD/MM/YYYY HH24:MI') as executed_display"),
            ]);

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }
        if ($this->filterRefType !== '') {
            $query->where('ref_type', $this->filterRefType);
        }
        if ($this->searchKeyword !== '') {
            $kw = '%' . strtoupper(trim($this->searchKeyword)) . '%';
            $query->where(function ($q) use ($kw) {
                $q->where(DB::raw('UPPER(reg_name)'), 'LIKE', $kw)
                  ->orWhere(DB::raw('UPPER(reg_no)'), 'LIKE', $kw)
                  ->orWhere(DB::raw('UPPER(vno_sep)'), 'LIKE', $kw);
            });
        }

        $query->orderByRaw("CASE status
            WHEN 'pending' THEN 1
            WHEN 'edited' THEN 2
            WHEN 'approved' THEN 3
            WHEN 'executing' THEN 4
            WHEN 'executed' THEN 5
            WHEN 'failed' THEN 6
            WHEN 'rejected' THEN 7
            ELSE 8 END");
        $query->orderByDesc('created_at');

        return $query->paginate($this->itemsPerPage);
    }

    #[Computed]
    public function stats(): array
    {
        $counts = DB::table('rstxn_approval_queue')
            ->where('module', 'casemix')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status IN ('approved','edited') THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'executed' THEN 1 ELSE 0 END) as executed,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            ")
            ->first();

        return (array) $counts;
    }

    public function openReview(int $approvalId): void
    {
        $row = DB::table('rstxn_approval_queue')->where('approval_id', $approvalId)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }

        $this->reviewId = $approvalId;
        $this->reviewData = (array) $row;

        $payload = json_decode($row->ai_payload ?? '{}', true) ?: [];
        $humanPayload = json_decode($row->human_payload ?? '{}', true) ?: [];

        $this->editedDiagnosa = $humanPayload['diagnosa'] ?? $payload['diagnosa'] ?? [];
        $this->editedProsedur = $humanPayload['prosedur'] ?? $payload['prosedur'] ?? [];
        $this->reviewNotes = '';

        $this->loadEmrData();
        $this->loadBerkasStatus();
        $this->dispatch('open-modal', name: 'casemix-review');
    }

    private function loadEmrData(): void
    {
        $refNo = (int) ($this->reviewData['ref_no'] ?? 0);
        $refType = strtoupper($this->reviewData['ref_type'] ?? '');
        if (!$refNo || !$refType) {
            $this->emrData = [];
            return;
        }

        try {
            $this->emrData = match ($refType) {
                'RI' => $this->findDataRI($refNo),
                'RJ', 'UGD' => $this->findDataRJ($refNo),
                default => [],
            };
        } catch (\Throwable) {
            $this->emrData = [];
        }
    }

    private function loadBerkasStatus(): void
    {
        $refNo = $this->reviewData['ref_no'] ?? null;
        $refType = strtoupper($this->reviewData['ref_type'] ?? '');
        if (!$refNo || !$refType) {
            $this->berkasStatus = [];
            return;
        }

        $table = match ($refType) {
            'RI' => 'rstxn_riuploadbpjses',
            'RJ', 'UGD' => 'rstxn_rjuploadbpjses',
            default => null,
        };
        $fkCol = match ($refType) {
            'RI' => 'rihdr_no',
            'RJ', 'UGD' => 'rj_no',
            default => null,
        };
        if (!$table || !$fkCol) {
            $this->berkasStatus = [];
            return;
        }

        $rows = DB::table($table)
            ->select('seq_file', 'uploadbpjs', 'jenis_file')
            ->where($fkCol, $refNo)
            ->orderBy('seq_file')
            ->get();

        $bySlot = [];
        foreach ([1, 2, 3, 4, 5] as $slot) {
            $bySlot[$slot] = ['label' => $this->berkasLabels[$slot], 'file' => null, 'meta' => null];
        }

        $statusRjRi = $refType === 'RI' ? 'RI' : 'RJ';
        $labs = DB::table('lbtxn_checkuphdrs')
            ->where('ref_no', $refNo)
            ->where('status_rjri', $statusRjRi)
            ->where('checkup_status', '!=', 'B')
            ->orderBy('checkup_no')
            ->select('checkup_no', DB::raw("to_char(checkup_date,'dd/mm/yyyy hh24:mi') as cdate"))
            ->get();
        foreach ($labs as $idx => $lab) {
            $slot = self::SLOT_LAB_OFFSET + $idx;
            $bySlot[$slot] = [
                'label' => 'LAB #' . ($idx + 1) . ' — ' . $lab->checkup_no,
                'file' => null,
                'meta' => ['type' => 'lab', 'checkup_no' => $lab->checkup_no],
            ];
        }

        if ($refType === 'RI') {
            $rads = DB::table('rstxn_riradiologs as r')
                ->leftJoin('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
                ->where('r.rihdr_no', $refNo)
                ->orderBy('r.rirad_no')
                ->select('r.rirad_no', 'm.rad_desc', 'r.rad_upload_pdf')
                ->get();
        } else {
            $rads = DB::table('rstxn_rjrads as r')
                ->leftJoin('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
                ->where('r.rj_no', $refNo)
                ->orderBy('r.rad_dtl')
                ->select('r.rad_dtl as rirad_no', 'm.rad_desc', 'r.rad_upload_pdf')
                ->get();
        }
        foreach ($rads as $idx => $rad) {
            $slot = self::SLOT_RAD_OFFSET + $idx;
            $bySlot[$slot] = [
                'label' => 'RAD #' . ($idx + 1) . ' — ' . ($rad->rad_desc ?? '-'),
                'file' => null,
                'meta' => ['type' => 'rad', 'rirad_no' => (int) $rad->rirad_no, 'radUploadPdf' => $rad->rad_upload_pdf ?: null],
            ];
        }

        foreach ($rows as $r) {
            if (isset($bySlot[$r->seq_file])) {
                $bySlot[$r->seq_file]['file'] = $r->uploadbpjs;
            }
        }
        ksort($bySlot);
        $this->berkasStatus = $bySlot;
    }

    private function saveBerkasBpjs(int $refNo, string $refType, int $seqFile, string $pdfContent): void
    {
        $namespace = 'upload/bpjs';
        Storage::disk('local')->makeDirectory($namespace);
        $filename = Carbon::now(config('app.timezone'))->format('dmYHis') . '_' . $seqFile . '.pdf';
        $filePath = $namespace . '/' . $filename;

        $table = $refType === 'RI' ? 'rstxn_riuploadbpjses' : 'rstxn_rjuploadbpjses';
        $fkCol = $refType === 'RI' ? 'rihdr_no' : 'rj_no';

        $cekFile = DB::table($table)->where($fkCol, $refNo)->where('seq_file', $seqFile)->first();

        Storage::disk('local')->put($filePath, $pdfContent);

        DB::transaction(function () use ($cekFile, $table, $fkCol, $refNo, $seqFile, $filename) {
            if ($cekFile) {
                if (!empty($cekFile->uploadbpjs)) {
                    foreach (['mount/bpjs/', 'upload/bpjs/'] as $prefix) {
                        if (Storage::disk('local')->exists($prefix . $cekFile->uploadbpjs)) {
                            Storage::disk('local')->delete($prefix . $cekFile->uploadbpjs);
                        }
                    }
                }
                DB::table($table)->where($fkCol, $refNo)->where('seq_file', $seqFile)
                    ->update(['uploadbpjs' => $filename, 'jenis_file' => 'pdf']);
            } else {
                DB::table($table)->insert([
                    $fkCol => $refNo,
                    'seq_file' => $seqFile,
                    'uploadbpjs' => $filename,
                    'jenis_file' => 'pdf',
                ]);
            }
        });
    }

    private function syncBerkasToMount(int $refNo): void
    {
        $mountBase = Storage::disk('local')->path('mount/bpjs');
        $uploadBase = Storage::disk('local')->path('upload/bpjs');

        if (!is_dir($mountBase)) return;

        $files = glob($uploadBase . '/*.pdf');
        foreach ($files as $file) {
            $basename = basename($file);
            $dest = $mountBase . '/' . $basename;
            if (@copy($file, $dest)) {
                @unlink($file);
            }
        }
    }

    private function generateAllBerkas(int $refNo, string $refType): array
    {
        $generated = [];
        $errors = [];
        $this->loadBerkasStatus();

        foreach ($this->berkasStatus as $slot => $info) {
            if (!empty($info['file'])) continue;

            try {
                if ($slot === 1) {
                    $this->generateBerkasSep($refNo, $refType);
                    $generated[] = 'SEP';
                } elseif ($slot === 4) {
                    $this->generateBerkasSkdp($refNo, $refType);
                    $generated[] = 'SKDP';
                } elseif ($slot >= self::SLOT_LAB_OFFSET && $slot < self::SLOT_RAD_OFFSET) {
                    $checkupNo = $info['meta']['checkup_no'] ?? null;
                    if ($checkupNo) {
                        $this->generateBerkasLab($refNo, $refType, $checkupNo, $slot);
                        $generated[] = $info['label'];
                    }
                } elseif ($slot >= self::SLOT_RAD_OFFSET) {
                    $riradNo = $info['meta']['rirad_no'] ?? null;
                    $radFile = $info['meta']['radUploadPdf'] ?? null;
                    if ($riradNo && $radFile) {
                        $this->generateBerkasRadiologi($refNo, $refType, $radFile, $slot);
                        $generated[] = $info['label'];
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = ($info['label'] ?? "slot $slot") . ': ' . $e->getMessage();
            }
        }

        return ['generated' => $generated, 'errors' => $errors];
    }

    private function generateBerkasSep(int $refNo, string $refType): void
    {
        $dataTxn = $refType === 'RI' ? $this->findDataRI($refNo) : $this->findDataRJ($refNo);
        if (empty($dataTxn) || empty($dataTxn['sep']['noSep'])) {
            throw new \RuntimeException('Data SEP tidak ditemukan.');
        }

        $sep = $dataTxn['sep'];
        $reqSep = $sep['reqSep']['request']['t_sep'] ?? [];
        $resSep = $sep['resSep'] ?? [];

        $regNo = $dataTxn['regNo'] ?? '';
        $pasienData = !empty($regNo) ? $this->findDataMasterPasien($regNo) : [];
        $pasien = $pasienData['pasien'] ?? [];

        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_address', 'int_city')->first();

        $dokterDpjp = $resSep['dpjp']['nmDPJP'] ?? null;
        $kodeDpjpReq = $reqSep['dpjpLayan'] ?? $reqSep['skdp']['kodeDPJP'] ?? '';
        if (empty($dokterDpjp) && !empty($kodeDpjpReq)) {
            $dokterDpjp = DB::table('rsmst_doctors')->where('kd_dr_bpjs', $kodeDpjpReq)->value('dr_name');
        }
        if (empty($dokterDpjp)) {
            $dokterDpjp = $dataTxn['drDesc'] ?? '-';
        }

        $data = [
            'sep' => $sep, 'reqSep' => $reqSep, 'resSep' => $resSep,
            'dataTxn' => $dataTxn, 'pasien' => $pasien,
            'jenis' => strtolower($refType),
            'identitasRs' => $identitasRs,
            'namaRs' => $identitasRs->int_name ?? 'RSI MADINAH',
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d-m-Y H:i:s'),
            'dokterDpjp' => $dokterDpjp,
        ];

        set_time_limit(300);
        $pdf = Pdf::loadView('pages.components.modul-dokumen.bpjs.cetak-sep.cetak-sep-print', ['data' => $data])
            ->setPaper('A5', 'landscape');
        $this->saveBerkasBpjs($refNo, $refType, 1, $pdf->output());
    }

    private function generateBerkasSkdp(int $refNo, string $refType): void
    {
        $dataTxn = $refType === 'RI' ? $this->findDataRI($refNo) : $this->findDataRJ($refNo);
        if (empty($dataTxn) || empty($dataTxn['kontrol']['tglKontrol'])) {
            throw new \RuntimeException('Data SKDP belum tersedia.');
        }

        $kontrol = $dataTxn['kontrol'];
        $sep = $dataTxn['sep'] ?? [];
        $regNo = $dataTxn['regNo'] ?? '';
        $pasienData = !empty($regNo) ? $this->findDataMasterPasien($regNo) : [];
        $pasien = $pasienData['pasien'] ?? [];

        if (!empty($pasien['tglLahir'])) {
            try { $pasien['tglLahirFormatted'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])->translatedFormat('j F Y'); }
            catch (\Throwable) { $pasien['tglLahirFormatted'] = $pasien['tglLahir']; }
        }
        if (!empty($kontrol['tglKontrol'])) {
            try { $kontrol['tglKontrolFormatted'] = Carbon::createFromFormat('d/m/Y', $kontrol['tglKontrol'])->translatedFormat('j F Y'); }
            catch (\Throwable) { $kontrol['tglKontrolFormatted'] = $kontrol['tglKontrol']; }
        }

        $resSep = $sep['resSep'] ?? [];
        $reqSep = $sep['reqSep']['request']['t_sep'] ?? [];
        $diagnosa = $resSep['diagnosa'] ?? ($reqSep['diagAwal'] ?? '-');
        $identitasRs = DB::table('rsmst_identitases')->select('int_name')->first();

        $data = [
            'kontrol' => $kontrol, 'pasien' => $pasien, 'dataTxn' => $dataTxn,
            'diagnosa' => $diagnosa, 'jenis' => strtolower($refType),
            'namaRs' => $identitasRs->int_name ?? 'RSI MADINAH',
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d-m-Y H:i:s'),
        ];

        set_time_limit(300);
        $pdf = Pdf::loadView('pages.components.modul-dokumen.bpjs.cetak-skdp.cetak-skdp-print', ['data' => $data])
            ->setPaper('A5', 'landscape');
        $this->saveBerkasBpjs($refNo, $refType, 4, $pdf->output());
    }

    private function generateBerkasLab(int $refNo, string $refType, string $checkupNo, int $slot): void
    {
        $statusRjRi = $refType === 'RI' ? 'RI' : 'RJ';
        $valid = DB::table('lbtxn_checkuphdrs')
            ->where('ref_no', $refNo)->where('status_rjri', $statusRjRi)
            ->where('checkup_no', $checkupNo)->where('checkup_status', '!=', 'B')
            ->exists();
        if (!$valid) throw new \RuntimeException('Checkup tidak valid.');

        set_time_limit(300);
        $header = collect(DB::select(
            "SELECT DISTINCT a.emp_id, a.checkup_no,
                    to_char(checkup_date,'dd/mm/yyyy hh24:mi:ss') AS checkup_date,
                    a.reg_no, reg_name, a.dr_id, dr_name,
                    sex, birth_date, c.address, emp_name,
                    waktu_selesai_pelayanan, checkup_kesimpulan
             FROM lbtxn_checkuphdrs a
             JOIN rsmst_pasiens c ON a.reg_no = c.reg_no
             JOIN rsmst_doctors f ON a.dr_id = f.dr_id
             JOIN immst_employers g ON a.emp_id = g.emp_id
             WHERE a.checkup_no = :cno",
            ['cno' => $checkupNo],
        ))->first();
        if (!$header) throw new \RuntimeException('Header lab tidak ditemukan.');

        $txn = DB::select(
            "SELECT b.clabitem_id, clabitem_desc, clab_desc, app_seq, item_seq,
                    lab_result, unit_desc, unit_convert, item_code,
                    normal_f, normal_m, high_limit_m, high_limit_f,
                    low_limit_m, low_limit_f, lowhigh_status, lab_result_status, d.nilai_kritis,
                    sex, a.dr_id, dr_name, a.emp_id, emp_name
             FROM lbtxn_checkuphdrs a
             JOIN lbtxn_checkupdtls b ON a.checkup_no = b.checkup_no
             JOIN rsmst_pasiens c ON a.reg_no = c.reg_no
             JOIN lbmst_clabitems d ON b.clabitem_id = d.clabitem_id
             JOIN lbmst_clabs e ON d.clab_id = e.clab_id
             JOIN rsmst_doctors f ON a.dr_id = f.dr_id
             JOIN immst_employers g ON a.emp_id = g.emp_id
             WHERE a.checkup_no = :cno AND nvl(hidden_status,'N') = 'N'
             ORDER BY app_seq, item_seq, clabitem_desc",
            ['cno' => $checkupNo],
        );

        $txnLuar = DB::select(
            "SELECT ('  ' || labout_desc) AS labout_desc, labout_result, labout_normal
             FROM lbtxn_checkuphdrs a
             JOIN lbtxn_checkupoutdtls b ON a.checkup_no = b.checkup_no
             WHERE a.checkup_no = :cno ORDER BY labout_dtl, labout_desc",
            ['cno' => $checkupNo],
        );

        $pdf = Pdf::loadView(
            'pages.components.rekam-medis.penunjang.laboratorium-display.laboratorium-display-print',
            compact('header', 'txn', 'txnLuar'),
        )->setPaper('a4', 'portrait');
        $this->saveBerkasBpjs($refNo, $refType, $slot, $pdf->output());
    }

    private function generateBerkasRadiologi(int $refNo, string $refType, string $radFile, int $slot): void
    {
        $content = null;
        if (str_contains($radFile, '/')) {
            $legacy = public_path('storage/' . $radFile);
            $content = is_file($legacy) ? file_get_contents($legacy) : null;
        } else {
            foreach (['mount/penunjang/radiologi/', 'upload/penunjang/radiologi/'] as $prefix) {
                if (Storage::disk('local')->exists($prefix . $radFile)) {
                    $content = Storage::disk('local')->get($prefix . $radFile);
                    break;
                }
            }
        }
        if ($content === null) throw new \RuntimeException('File radiologi tidak ditemukan di server.');
        $this->saveBerkasBpjs($refNo, $refType, $slot, $content);
    }

    public function autoUploadBerkas(): void
    {
        if (!$this->reviewId) return;

        $refNo = (int) ($this->reviewData['ref_no'] ?? 0);
        $refType = strtoupper($this->reviewData['ref_type'] ?? '');
        if (!$refNo || !in_array($refType, ['RJ', 'RI', 'UGD'])) {
            $this->dispatch('toast', type: 'error', message: 'Data ref tidak valid.');
            return;
        }

        try {
            $result = $this->generateAllBerkas($refNo, $refType);
            $this->syncBerkasToMount($refNo);
            $this->loadBerkasStatus();

            if (empty($result['generated']) && empty($result['errors'])) {
                $this->dispatch('toast', type: 'info', message: 'Semua berkas sudah lengkap.');
                return;
            }

            $msg = '';
            if (!empty($result['generated'])) {
                $msg .= 'Berkas di-generate: ' . implode(', ', $result['generated']) . '.';
            }
            if (!empty($result['errors'])) {
                $msg .= ' Error: ' . implode('; ', $result['errors']);
                $this->dispatch('toast', type: 'warning', message: $msg);
            } else {
                $this->dispatch('toast', type: 'success', message: $msg);
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal generate berkas: ' . $e->getMessage());
        }
    }

    public function approve(): void
    {
        if (!$this->reviewId) return;

        $humanPayload = json_encode([
            'diagnosa' => $this->editedDiagnosa,
            'prosedur' => $this->editedProsedur,
        ]);

        DB::table('rstxn_approval_queue')
            ->where('approval_id', $this->reviewId)
            ->whereIn('status', ['pending', 'edited', 'failed'])
            ->update([
                'status' => 'approved',
                'human_payload' => $humanPayload,
                'reviewer' => Auth::user()->myuser_name ?? Auth::user()->name ?? 'unknown',
                'review_notes' => $this->reviewNotes ?: null,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        $refNo = (string) ($this->reviewData['ref_no'] ?? '');
        $refType = strtoupper(trim((string) ($this->reviewData['ref_type'] ?? '')));
        $isRI = in_array($refType, ['RI', 'RANAP']);

        $this->dispatch('close-modal', name: 'casemix-review');
        $this->dispatch('toast', type: 'success', message: 'Klaim di-approve. Membuka bridging iDRG...');

        if ($isRI) {
            $this->dispatch('daftar-ri.idrg.open', riHdrNo: $refNo);
        } elseif ($refType === 'UGD') {
            $this->dispatch('daftar-ugd.idrg.open', rjNo: $refNo);
        } else {
            $this->dispatch('daftar-rj.idrg.open', rjNo: $refNo);
        }

        $this->reviewId = null;
        $this->reviewData = [];
        $this->emrData = [];
        $this->berkasStatus = [];
    }

    public function reject(): void
    {
        if (!$this->reviewId) return;

        if (trim($this->reviewNotes) === '') {
            $this->dispatch('toast', type: 'error', message: 'Catatan wajib diisi saat reject.');
            return;
        }

        DB::table('rstxn_approval_queue')
            ->where('approval_id', $this->reviewId)
            ->whereIn('status', ['pending', 'edited', 'failed'])
            ->update([
                'status' => 'rejected',
                'reviewer' => Auth::user()->myuser_name ?? Auth::user()->name ?? 'unknown',
                'review_notes' => $this->reviewNotes,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        $this->dispatch('close-modal', name: 'casemix-review');
        $this->dispatch('toast', type: 'warning', message: 'Klaim di-reject.');
        $this->reviewId = null;
        $this->reviewData = [];
        $this->emrData = [];
        $this->berkasStatus = [];
    }

    public function removeDiagnosa(int $index): void
    {
        unset($this->editedDiagnosa[$index]);
        $this->editedDiagnosa = array_values($this->editedDiagnosa);
    }

    public function removeProsedur(int $index): void
    {
        unset($this->editedProsedur[$index]);
        $this->editedProsedur = array_values($this->editedProsedur);
    }

    public function toggleKategori(int $index): void
    {
        if (!isset($this->editedDiagnosa[$index])) return;
        $current = $this->editedDiagnosa[$index]['kategori'] ?? 'Secondary';
        $this->editedDiagnosa[$index]['kategori'] = $current === 'Primary' ? 'Secondary' : 'Primary';
    }

    private function statusBadge(string $status): array
    {
        return match ($status) {
            'pending'   => ['bg' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300', 'label' => 'Pending'],
            'approved'  => ['bg' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300', 'label' => 'Approved'],
            'edited'    => ['bg' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300', 'label' => 'Edited'],
            'executing' => ['bg' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300', 'label' => 'Executing'],
            'executed'  => ['bg' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300', 'label' => 'Executed'],
            'failed'    => ['bg' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300', 'label' => 'Failed'],
            'rejected'  => ['bg' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300', 'label' => 'Rejected'],
            default     => ['bg' => 'bg-gray-100 text-gray-600', 'label' => $status],
        };
    }

    private function confidenceBadge(int $confidence): array
    {
        if ($confidence >= 90) return ['bg' => 'text-emerald-600 dark:text-emerald-400', 'icon' => '●'];
        if ($confidence >= 70) return ['bg' => 'text-yellow-600 dark:text-yellow-400', 'icon' => '●'];
        return ['bg' => 'text-red-600 dark:text-red-400', 'icon' => '●'];
    }
};
?>

<div>
    <x-page-title
        title="Approval Hub — Casemix / E-Klaim"
        subtitle="Auto upload berkas BPJS, review ICD coding AI, bridging iDRG/INA-CBG" />

    {{-- STATS CARDS --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-2 gap-3 mb-4 sm:grid-cols-3 lg:grid-cols-6">
        <div class="px-4 py-3 rounded-xl bg-canvas dark:bg-gray-800 border border-hairline dark:border-gray-700">
            <div class="text-2xl font-bold text-ink dark:text-white">{{ $s['pending'] ?? 0 }}</div>
            <div class="text-sm text-muted">Pending Review</div>
        </div>
        <div class="px-4 py-3 rounded-xl bg-canvas dark:bg-gray-800 border border-hairline dark:border-gray-700">
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $s['approved'] ?? 0 }}</div>
            <div class="text-sm text-muted">Approved</div>
        </div>
        <div class="px-4 py-3 rounded-xl bg-canvas dark:bg-gray-800 border border-hairline dark:border-gray-700">
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $s['executed'] ?? 0 }}</div>
            <div class="text-sm text-muted">Executed</div>
        </div>
        <div class="px-4 py-3 rounded-xl bg-canvas dark:bg-gray-800 border border-hairline dark:border-gray-700">
            <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $s['failed'] ?? 0 }}</div>
            <div class="text-sm text-muted">Failed</div>
        </div>
        <div class="px-4 py-3 rounded-xl bg-canvas dark:bg-gray-800 border border-hairline dark:border-gray-700">
            <div class="text-2xl font-bold text-gray-500">{{ $s['rejected'] ?? 0 }}</div>
            <div class="text-sm text-muted">Rejected</div>
        </div>
        <div class="px-4 py-3 rounded-xl bg-canvas dark:bg-gray-800 border border-hairline dark:border-gray-700">
            <div class="text-2xl font-bold text-ink dark:text-white">{{ $s['total'] ?? 0 }}</div>
            <div class="text-sm text-muted">Total</div>
        </div>
    </div>

    {{-- TOOLBAR --}}
    <div class="p-4 mb-4 bg-canvas dark:bg-gray-800 rounded-2xl border border-hairline dark:border-gray-700"
         wire:key="casemix-queue-toolbar-{{ $renderVersions['casemix-queue-toolbar'] ?? 0 }}">
        <div class="flex flex-wrap items-end gap-3">
            {{-- Search --}}
            <div class="flex-1 min-w-[200px]">
                <label class="block mb-1 text-xs font-medium text-muted">Cari</label>
                <input type="text" wire:model.live.debounce.400ms="searchKeyword"
                    placeholder="Nama pasien, No RM, SEP..."
                    class="w-full px-3 py-2 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-600 dark:text-white focus:ring-brand focus:border-brand" />
            </div>

            {{-- Status --}}
            <div>
                <label class="block mb-1 text-xs font-medium text-muted">Status</label>
                <select wire:model.live="filterStatus"
                    class="px-3 py-2 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                    <option value="">Semua</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="executed">Executed</option>
                    <option value="failed">Failed</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            {{-- Ref Type --}}
            <div>
                <label class="block mb-1 text-xs font-medium text-muted">Jenis Rawat</label>
                <select wire:model.live="filterRefType"
                    class="px-3 py-2 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                    <option value="">Semua</option>
                    <option value="RJ">Rawat Jalan</option>
                    <option value="RI">Rawat Inap</option>
                    <option value="UGD">UGD</option>
                </select>
            </div>

            {{-- Per Page --}}
            <div>
                <label class="block mb-1 text-xs font-medium text-muted">Per halaman</label>
                <select wire:model.live="itemsPerPage"
                    class="px-3 py-2 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>

            {{-- Reset --}}
            <div>
                <button type="button" wire:click="resetFilters"
                    class="px-3 py-2 text-sm font-medium text-muted hover:text-ink dark:hover:text-white">
                    Reset
                </button>
            </div>
        </div>
    </div>

    {{-- TABLE --}}
    <div class="overflow-hidden bg-canvas dark:bg-gray-800 rounded-2xl border border-hairline dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold uppercase bg-surface-soft dark:bg-gray-900 text-muted">
                    <tr>
                        <th class="px-4 py-3">Pasien</th>
                        <th class="px-4 py-3">Diagnosa (AI Suggest)</th>
                        <th class="px-4 py-3 text-center">Confidence</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Reviewer</th>
                        <th class="px-4 py-3 text-center">Tanggal</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline dark:divide-gray-700">
                    @forelse ($this->rows as $row)
                        @php
                            $payload = json_decode($row->ai_payload ?? '{}', true) ?: [];
                            $diagnosa = $payload['diagnosa'] ?? [];
                            $badge = $this->statusBadge($row->status);
                            $conf = $this->confidenceBadge($row->ai_confidence ?? 0);
                        @endphp
                        <tr class="transition hover:bg-surface-soft dark:hover:bg-gray-800/50"
                            wire:key="aq-{{ $row->approval_id }}">
                            {{-- Pasien --}}
                            <td class="px-4 py-3 align-top">
                                <div class="font-semibold text-ink dark:text-white">{{ $row->reg_name }}</div>
                                <div class="text-xs text-muted">RM <span class="font-mono">{{ $row->reg_no }}</span></div>
                                <div class="text-xs text-muted">SEP {{ $row->vno_sep ?? '-' }}</div>
                                <div class="inline-block px-1.5 py-0.5 mt-1 text-xs font-medium rounded bg-surface-soft dark:bg-gray-700 text-muted">
                                    {{ $row->ref_type }}
                                </div>
                            </td>

                            {{-- Diagnosa --}}
                            <td class="px-4 py-3 align-top">
                                @foreach (array_slice($diagnosa, 0, 3) as $d)
                                    <div class="flex items-center gap-1.5 {{ !$loop->first ? 'mt-1' : '' }}">
                                        <span class="font-mono text-xs px-1.5 py-0.5 rounded {{ ($d['kategori'] ?? '') === 'Primary' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
                                            {{ $d['code'] ?? '-' }}
                                        </span>
                                        <span class="text-xs text-body dark:text-gray-300 line-clamp-1">{{ $d['desc'] ?? '' }}</span>
                                    </div>
                                @endforeach
                                @if (count($diagnosa) > 3)
                                    <div class="mt-1 text-xs text-muted">+{{ count($diagnosa) - 3 }} lainnya</div>
                                @endif
                            </td>

                            {{-- Confidence --}}
                            <td class="px-4 py-3 text-center align-top">
                                <span class="{{ $conf['bg'] }} font-semibold">
                                    {{ $conf['icon'] }} {{ $row->ai_confidence ?? 0 }}%
                                </span>
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

                            {{-- Reviewer --}}
                            <td class="px-4 py-3 text-center align-top text-xs text-muted">
                                {{ $row->reviewer ?? '-' }}
                            </td>

                            {{-- Tanggal --}}
                            <td class="px-4 py-3 text-center align-top whitespace-nowrap">
                                <div class="text-xs text-muted">{{ $row->created_display }}</div>
                                @if ($row->reviewed_display)
                                    <div class="text-xs text-muted">R: {{ $row->reviewed_display }}</div>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="px-4 py-3 text-center align-top">
                                @if (in_array($row->status, ['pending', 'failed']))
                                    <button type="button" wire:click="openReview({{ $row->approval_id }})"
                                        class="px-3 py-1.5 text-xs font-medium text-white rounded-lg bg-brand hover:bg-brand-dark transition">
                                        Review
                                    </button>
                                @elseif ($row->status === 'executed')
                                    <button type="button" wire:click="openReview({{ $row->approval_id }})"
                                        class="px-3 py-1.5 text-xs font-medium text-muted hover:text-ink transition">
                                        Detail
                                    </button>
                                @else
                                    <button type="button" wire:click="openReview({{ $row->approval_id }})"
                                        class="px-3 py-1.5 text-xs font-medium text-muted hover:text-ink transition">
                                        Lihat
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16">
                                <div class="flex flex-col items-center justify-center gap-3">
                                    <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <p class="text-base font-medium text-muted dark:text-gray-400">
                                        {{ $filterStatus === 'pending' ? 'Tidak ada antrian pending' : 'Tidak ada data' }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINATION --}}
        <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
            {{ $this->rows->links() }}
        </div>
    </div>

    {{-- REVIEW MODAL --}}
    <x-modal name="casemix-review" size="full" height="full" focusable>
        <div class="flex flex-col h-full">
            {{-- Header --}}
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-ink dark:text-white">Review Klaim Casemix</h2>
                        @if (!empty($reviewData))
                            <p class="text-sm text-muted">
                                {{ $reviewData['reg_name'] ?? '' }} &middot;
                                RM {{ $reviewData['reg_no'] ?? '' }} &middot;
                                SEP {{ $reviewData['vno_sep'] ?? '-' }} &middot;
                                {{ $reviewData['ref_type'] ?? '' }}
                            </p>
                        @endif
                    </div>
                    <x-icon-button color="gray" type="button" x-on:click="$dispatch('close-modal', { name: 'casemix-review' })" class="shrink-0">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- Body: 2 kolom — kiri fixed, kanan scroll --}}
            <div class="flex-1 overflow-hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 h-full">

                    {{-- KIRI: EMR Transaksi (read-only, fixed) --}}
                    <div class="px-4 py-4 border-r border-hairline dark:border-gray-700 overflow-y-auto">
                        @if (!empty($emrData))
                            @php
                                $refType = strtoupper($reviewData['ref_type'] ?? '');
                                $isRI = $refType === 'RI';
                            @endphp

                            {{-- Tombol Rekam Medis Detail --}}
                            <div class="mb-3">
                                <button type="button"
                                    x-on:click="
                                        @if ($isRI)
                                            $dispatch('cetak-rekam-medis-ri.open', { riHdrNo: {{ (int) ($reviewData['ref_no'] ?? 0) }} })
                                        @elseif ($refType === 'UGD')
                                            $dispatch('cetak-rekam-medis-ugd.open', { rjNo: {{ (int) ($reviewData['ref_no'] ?? 0) }} })
                                        @else
                                            $dispatch('cetak-rekam-medis.open', { rjNo: {{ (int) ($reviewData['ref_no'] ?? 0) }} })
                                        @endif
                                    "
                                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-purple-700 bg-purple-100 rounded-xl hover:bg-purple-200 dark:text-purple-300 dark:bg-purple-900/30 dark:hover:bg-purple-900/50 transition border border-purple-200 dark:border-purple-800">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                    </svg>
                                    Rekam Medis Lengkap
                                </button>
                            </div>

                            {{-- Header info --}}
                            <div class="mb-4 p-3 rounded-xl bg-surface-soft dark:bg-gray-800 border border-hairline dark:border-gray-700">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-bold uppercase tracking-wider text-muted">{{ $refType }}</span>
                                    <span class="text-xs text-muted">{{ $emrData['rjDate'] ?? $emrData['riDate'] ?? '' }}</span>
                                </div>
                                <div class="text-sm font-semibold text-ink dark:text-white">{{ $emrData['regName'] ?? '-' }}</div>
                                <div class="text-xs text-muted">
                                    RM {{ $emrData['regNo'] ?? '-' }} &middot;
                                    {{ $emrData['poliDesc'] ?? '' }} &middot;
                                    dr. {{ $emrData['drDesc'] ?? '-' }}
                                </div>
                                @if (!empty($emrData['sep']['noSep']))
                                    <div class="mt-1 text-xs text-muted">SEP {{ $emrData['sep']['noSep'] }}</div>
                                @endif
                            </div>

                            {{-- Diagnosa ICD-10 --}}
                            <div class="mb-4">
                                <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-muted">Diagnosa ICD-10</h4>
                                @if (!empty($emrData['diagnosis']))
                                    <div class="space-y-1">
                                        @foreach ($emrData['diagnosis'] as $diag)
                                            <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-surface-soft dark:bg-gray-800">
                                                <span class="font-mono text-xs font-semibold text-blue-700 dark:text-blue-300">{{ $diag['diagId'] ?? '' }}</span>
                                                <span class="text-xs text-body dark:text-gray-300">{{ $diag['diagDesc'] ?? '' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-xs text-muted italic">-</p>
                                @endif
                            </div>

                            {{-- Diagnosis free text --}}
                            @if (!empty($emrData['diagnosisFreeText']))
                                <div class="mb-4">
                                    <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-muted">Diagnosis Dokter</h4>
                                    <div class="px-3 py-2 text-sm rounded-lg bg-surface-soft dark:bg-gray-800 whitespace-pre-line text-body dark:text-gray-300">{{ $emrData['diagnosisFreeText'] }}</div>
                                </div>
                            @endif

                            @if ($isRI)
                                {{-- DPJP Leveling (RI) --}}
                                @php $leveling = $emrData['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? []; @endphp
                                @if (!empty($leveling))
                                    <div class="mb-4">
                                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-muted">DPJP</h4>
                                        <div class="space-y-1">
                                            @foreach ($leveling as $lv)
                                                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-surface-soft dark:bg-gray-800">
                                                    <span class="text-xs font-semibold {{ ($lv['levelDokter'] ?? '') === 'Utama' ? 'text-blue-700 dark:text-blue-300' : 'text-muted' }}">{{ $lv['levelDokter'] ?? '-' }}</span>
                                                    <span class="text-xs text-body dark:text-gray-300">{{ $lv['drName'] ?? '-' }} — {{ $lv['poliDesc'] ?? '' }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Tindak Lanjut (RI) --}}
                                @php
                                    $tlRI = data_get($emrData, 'perencanaan.tindakLanjut.tindakLanjut', '');
                                    $tglPulang = data_get($emrData, 'perencanaan.tindakLanjut.tglPulang', '');
                                @endphp
                                @if ($tlRI)
                                    <div class="mb-4">
                                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-muted">Tindak Lanjut</h4>
                                        <div class="px-3 py-2 text-sm rounded-lg bg-surface-soft dark:bg-gray-800 text-body dark:text-gray-300">
                                            {{ $tlRI }}
                                            @if ($tglPulang)
                                                <span class="text-muted"> — {{ $tglPulang }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                {{-- Resume Medis (RI) --}}
                                @php
                                    $rmRI = (string) data_get($emrData, 'resumeMedis', '');
                                    $rmRIHasContent = trim(strip_tags($rmRI)) !== '';
                                @endphp
                                @if ($rmRIHasContent)
                                    <div class="mb-4">
                                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-muted">Resume Medis</h4>
                                        <div class="px-3 py-2 text-sm rounded-lg bg-surface-soft dark:bg-gray-800 text-body dark:text-gray-300 overflow-auto max-h-64">{!! $rmRI !!}</div>
                                    </div>
                                @endif
                            @else
                                {{-- Terapi (RJ/UGD) --}}
                                @php $terapi = data_get($emrData, 'perencanaan.terapi.terapi', ''); @endphp
                                @if ($terapi)
                                    <div class="mb-4">
                                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-muted">Terapi</h4>
                                        <div class="px-3 py-2 text-sm rounded-lg bg-surface-soft dark:bg-gray-800 whitespace-pre-line text-body dark:text-gray-300">{{ $terapi }}</div>
                                    </div>
                                @endif
                            @endif

                            {{-- SEP Info --}}
                            @if (!empty($emrData['sep']['resSep']))
                                @php $resSep = $emrData['sep']['resSep']; @endphp
                                <div class="mb-4">
                                    <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-muted">Info SEP</h4>
                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        @foreach ([
                                            'Diagnosa' => $resSep['diagnosa'] ?? '-',
                                            'Jns Pelayanan' => $resSep['jnsPelayanan'] ?? '-',
                                            'Kelas Rawat' => $resSep['klsRawat']['klsRawatHak'] ?? '-',
                                            'Penjamin' => $resSep['penjamin'] ?? '-',
                                        ] as $label => $val)
                                            <div class="px-2 py-1.5 rounded bg-surface-soft dark:bg-gray-800">
                                                <div class="text-muted">{{ $label }}</div>
                                                <div class="font-medium text-body dark:text-gray-300">{{ $val }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            {{-- Berkas BPJS Status --}}
                            @if (!empty($berkasStatus))
                                <div class="mb-4">
                                    <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-muted">Berkas BPJS</h4>
                                    <div class="space-y-1.5">
                                        @foreach ($berkasStatus as $slot => $info)
                                            <div class="flex items-center gap-2 px-3 py-2 rounded-lg border {{ !empty($info['file']) ? 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800' : 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800' }}">
                                                @if (!empty($info['file']))
                                                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                @else
                                                    <svg class="w-4 h-4 text-red-500 dark:text-red-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                    </svg>
                                                @endif
                                                <span class="flex-1 text-xs font-medium {{ !empty($info['file']) ? 'text-emerald-800 dark:text-emerald-300' : 'text-red-800 dark:text-red-300' }} truncate">
                                                    {{ $info['label'] }}
                                                </span>
                                                @if (!empty($info['file']))
                                                    <a href="{{ route('files.show', ['path' => 'mount/bpjs/' . $info['file']]) }}"
                                                        target="_blank" rel="noopener"
                                                        class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium rounded bg-emerald-200/60 text-emerald-700 hover:bg-emerald-300/60 dark:bg-emerald-800/40 dark:text-emerald-300 dark:hover:bg-emerald-700/40 transition shrink-0">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                        Lihat
                                                    </a>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    @php
                                        $missing = collect($berkasStatus)->filter(fn($i) => empty($i['file']))->count();
                                    @endphp
                                    @if ($missing > 0 && in_array($reviewData['status'] ?? '', ['pending', 'failed']))
                                        <p class="mt-2 text-xs text-muted dark:text-gray-400">
                                            {{ $missing }} berkas belum upload — klik "Auto Upload Berkas" untuk generate otomatis.
                                        </p>
                                    @endif
                                </div>
                            @endif

                        @else
                            <p class="text-sm text-muted italic">Data EMR tidak tersedia.</p>
                        @endif
                    </div>

                    {{-- KANAN: Form Review --}}
                    <div class="px-6 py-4 space-y-6 overflow-y-auto">

                {{-- AI Notes --}}
                @if (!empty($reviewData['ai_notes']))
                    <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                        <div class="text-xs font-semibold text-blue-800 dark:text-blue-300 mb-1">Catatan AI</div>
                        <div class="text-sm text-blue-900 dark:text-blue-200">{{ $reviewData['ai_notes'] }}</div>
                        <div class="mt-2 text-xs text-blue-600 dark:text-blue-400">
                            Confidence: <span class="font-bold">{{ $reviewData['ai_confidence'] ?? 0 }}%</span>
                        </div>
                    </div>
                @endif

                {{-- Diagnosa --}}
                <div>
                    <h3 class="mb-3 text-sm font-bold text-ink dark:text-white uppercase tracking-wider">Diagnosa (ICD-10)</h3>
                    <div class="space-y-2">
                        @forelse ($editedDiagnosa as $idx => $d)
                            <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-soft dark:bg-gray-800 border border-hairline dark:border-gray-700">
                                <button type="button" wire:click="toggleKategori({{ $idx }})"
                                    class="px-2 py-1 text-xs font-bold rounded-lg transition {{ ($d['kategori'] ?? '') === 'Primary' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300' }}">
                                    {{ ($d['kategori'] ?? '') === 'Primary' ? 'P' : 'S' }}
                                </button>
                                <span class="font-mono text-sm font-semibold text-ink dark:text-white min-w-[70px]">{{ $d['code'] ?? '' }}</span>
                                <span class="flex-1 text-sm text-body dark:text-gray-300">{{ $d['desc'] ?? '' }}</span>
                                @if (isset($d['confidence']))
                                    <span class="text-xs text-muted">{{ $d['confidence'] }}%</span>
                                @endif
                                @if (in_array($reviewData['status'] ?? '', ['pending', 'failed']))
                                    <button type="button" wire:click="removeDiagnosa({{ $idx }})"
                                        class="text-red-500 hover:text-red-700 transition">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm italic text-muted">Tidak ada diagnosa</p>
                        @endforelse
                    </div>
                </div>

                {{-- Prosedur --}}
                <div>
                    <h3 class="mb-3 text-sm font-bold text-ink dark:text-white uppercase tracking-wider">Prosedur (ICD-9-CM)</h3>
                    <div class="space-y-2">
                        @forelse ($editedProsedur as $idx => $p)
                            <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-soft dark:bg-gray-800 border border-hairline dark:border-gray-700">
                                <span class="font-mono text-sm font-semibold text-ink dark:text-white min-w-[70px]">{{ $p['code'] ?? '' }}</span>
                                <span class="flex-1 text-sm text-body dark:text-gray-300">{{ $p['desc'] ?? '' }}</span>
                                @if (in_array($reviewData['status'] ?? '', ['pending', 'failed']))
                                    <button type="button" wire:click="removeProsedur({{ $idx }})"
                                        class="text-red-500 hover:text-red-700 transition">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm italic text-muted">Tidak ada prosedur</p>
                        @endforelse
                    </div>
                </div>

                {{-- Exec result (kalau sudah dieksekusi) --}}
                @if (!empty($reviewData['exec_status']))
                    <div class="p-4 rounded-xl {{ $reviewData['exec_status'] === 'success' ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800' }} border">
                        <div class="text-xs font-semibold {{ $reviewData['exec_status'] === 'success' ? 'text-emerald-800 dark:text-emerald-300' : 'text-red-800 dark:text-red-300' }} mb-1">
                            Hasil Eksekusi: {{ strtoupper($reviewData['exec_status']) }}
                        </div>
                        @if (!empty($reviewData['exec_error']))
                            <div class="text-sm text-red-900 dark:text-red-200">{{ $reviewData['exec_error'] }}</div>
                        @endif
                    </div>
                @endif

                {{-- Review Notes --}}
                @if (in_array($reviewData['status'] ?? '', ['pending', 'failed']))
                    <div>
                        <label class="block mb-1 text-sm font-medium text-ink dark:text-white">Catatan Review</label>
                        <textarea wire:model="reviewNotes" rows="3" placeholder="Opsional. Wajib diisi kalau reject."
                            class="w-full px-3 py-2 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-600 dark:text-white focus:ring-brand focus:border-brand"></textarea>
                    </div>
                @elseif (!empty($reviewData['review_notes']))
                    <div>
                        <div class="text-xs font-semibold text-muted mb-1">Catatan Reviewer</div>
                        <div class="text-sm text-body dark:text-gray-300">{{ $reviewData['review_notes'] }}</div>
                    </div>
                @endif

                    </div>{{-- /KANAN --}}
                </div>{{-- /grid --}}
            </div>

            {{-- Footer --}}
            <div class="sticky bottom-0 z-10 flex items-center justify-between gap-2 px-6 py-3 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                {{-- Kiri: Auto Upload Berkas --}}
                <div class="flex items-center gap-2">
                    @if (in_array($reviewData['status'] ?? '', ['pending', 'failed']))
                        <button type="button" wire:click="autoUploadBerkas" wire:loading.attr="disabled"
                            wire:target="autoUploadBerkas"
                            class="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-100 rounded-lg hover:bg-blue-200 dark:text-blue-300 dark:bg-blue-900/30 dark:hover:bg-blue-900/50 transition disabled:opacity-50">
                            <span wire:loading.remove wire:target="autoUploadBerkas" class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                Auto Upload Berkas
                            </span>
                            <span wire:loading wire:target="autoUploadBerkas" class="flex items-center gap-1.5">
                                <x-loading /> Generating...
                            </span>
                        </button>
                    @endif
                </div>

                {{-- Kanan: Reject / Approve --}}
                <div class="flex items-center gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'casemix-review' })">
                        Tutup
                    </x-secondary-button>

                    @if (in_array($reviewData['status'] ?? '', ['pending', 'failed']))
                        <button type="button" wire:click="reject" wire:loading.attr="disabled"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition disabled:opacity-50">
                            Reject
                        </button>
                        <button type="button" wire:click="approve" wire:loading.attr="disabled"
                            wire:target="approve"
                            class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition disabled:opacity-50">
                            <span wire:loading.remove wire:target="approve">Approve</span>
                            <span wire:loading wire:target="approve">Menyimpan...</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </x-modal>

    {{-- Cetak Rekam Medis — komponen existing (RJ, UGD, RI) --}}
    <livewire:pages::components.rekam-medis.rj.cetak-rekam-medis.cetak-rekam-medis-open
        wire:key="approval-hub-cetak-rm-rj" />
    <livewire:pages::components.rekam-medis.ugd.cetak-rekam-medis.cetak-rekam-medis-open
        wire:key="approval-hub-cetak-rm-ugd" />
    <livewire:pages::components.rekam-medis.ri.cetak-rekam-medis.cetak-rekam-medis-open
        wire:key="approval-hub-cetak-rm-ri" />

    {{-- Bridging iDRG / INACBG — orchestrator existing (RJ, UGD, RI) --}}
    <livewire:pages::transaksi.rj.daftar-rj.idrg-rj-actions
        wire:key="approval-hub-idrg-rj" />
    <livewire:pages::transaksi.ugd.daftar-ugd.idrg-ugd-actions
        wire:key="approval-hub-idrg-ugd" />
    <livewire:pages::transaksi.ri.daftar-ri.idrg-ri-actions
        wire:key="approval-hub-idrg-ri" />
</div>
