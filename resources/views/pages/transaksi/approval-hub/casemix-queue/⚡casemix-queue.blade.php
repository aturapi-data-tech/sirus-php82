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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\IDRG\iDrgTrait;
use App\Http\Traits\Txn\Rj\EmrCompletenessRJTrait;
use App\Http\Traits\Txn\Ri\EmrCompletenessRITrait;
use App\Http\Traits\Txn\Ugd\EmrCompletenessUGDTrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait, EmrRJTrait, EmrRITrait, EmrUGDTrait, MasterPasienTrait, iDrgTrait;
    use EmrCompletenessRJTrait, EmrCompletenessRITrait, EmrCompletenessUGDTrait;

    public string $queueType = 'RJ';

    public array $renderVersions = [];
    protected array $renderAreas = ['casemix-queue-toolbar'];

    public string $filterStatus = 'pending';
    public int $itemsPerPage = 25;

    public string $scanMode = 'bulanan';
    public string $scanBulan = '';
    public string $scanTanggal = '';
    public bool $scanning = false;
    public bool $aiRunning = false;
    public int $aiProgress = 0;
    public int $aiTotal = 0;

    // Review modal state
    public ?int $reviewId = null;
    public array $reviewData = [];
    public array $editedDiagnosa = [];
    public array $editedProsedur = [];
    public string $reviewNotes = '';

    // Row selection
    public array $selectedIds = [];

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

    public function mount(string $queueType = 'RJ'): void
    {
        $this->queueType = strtoupper($queueType);

        $this->registerAreas($this->renderAreas);

        $prefix = 'approval-casemix-' . strtolower($this->queueType) . '-';
        $this->filterStatus = session($prefix . 'filterStatus', 'pending');
        $this->itemsPerPage = (int) session($prefix . 'itemsPerPage', 25);
        $this->scanMode = session($prefix . 'scanMode', 'bulanan');
        $this->scanBulan = session($prefix . 'scanBulan', now()->format('m/Y'));
        $this->scanTanggal = session($prefix . 'scanTanggal', now()->format('d/m/Y'));
    }

    public function updatedFilterStatus(): void
    {
        session()->put('approval-casemix-' . strtolower($this->queueType) . '-filterStatus', $this->filterStatus);
        $this->resetPage();
        $this->incrementVersion('casemix-queue-toolbar');
    }
    public function updatedItemsPerPage(): void
    {
        session()->put('approval-casemix-' . strtolower($this->queueType) . '-itemsPerPage', $this->itemsPerPage);
        $this->resetPage();
    }
    public function updatedScanMode(): void
    {
        session()->put('approval-casemix-' . strtolower($this->queueType) . '-scanMode', $this->scanMode);
    }
    public function updatedScanBulan(): void
    {
        session()->put('approval-casemix-' . strtolower($this->queueType) . '-scanBulan', $this->scanBulan);
    }
    public function updatedScanTanggal(): void
    {
        session()->put('approval-casemix-' . strtolower($this->queueType) . '-scanTanggal', $this->scanTanggal);
    }

    public function resetFilters(): void
    {
        $this->filterStatus = 'pending';
        $this->updatedFilterStatus();
        $this->incrementVersion('casemix-queue-toolbar');
        $this->resetPage();
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

    private function scanDateRange(): array
    {
        if ($this->scanMode === 'bulanan') {
            if (!preg_match('/^(\d{1,2})\/(\d{4})$/', $this->scanBulan, $m)) {
                return [now()->startOfMonth(), now()->endOfMonth()];
            }
            $start = Carbon::createFromDate((int) $m[2], (int) $m[1], 1)->startOfMonth();
            return [$start, $start->copy()->endOfMonth()];
        }

        if (!preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $this->scanTanggal, $m)) {
            return [now()->startOfDay(), now()->endOfDay()];
        }
        $date = Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1]);
        return [$date->startOfDay(), $date->endOfDay()];
    }

    #[Computed]
    public function rows()
    {
        $query = DB::table('rstxn_approval_queue')
            ->where('module', 'casemix')
            ->select([
                'approval_id', 'ref_no', 'ref_type', 'reg_no', 'reg_name',
                'vno_sep', 'ai_payload', 'ai_confidence', 'ai_notes', 'ai_model',
                'status', 'human_payload', 'reviewer', 'review_notes',
                'exec_status', 'exec_error',
                DB::raw("to_char(created_at, 'DD/MM/YYYY HH24:MI') as created_display"),
                DB::raw("to_char(reviewed_at, 'DD/MM/YYYY HH24:MI') as reviewed_display"),
                DB::raw("to_char(executed_at, 'DD/MM/YYYY HH24:MI') as executed_display"),
                DB::raw("to_char(ref_date, 'DD/MM/YYYY HH24:MI') as ref_date_display"),
            ]);

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }
        if ($this->queueType !== '') {
            $query->where('ref_type', $this->queueType);
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
        $query->orderByDesc('ref_date');

        return $query->paginate($this->itemsPerPage);
    }

    #[Computed]
    public function stats(): array
    {
        $query = DB::table('rstxn_approval_queue')->where('module', 'casemix');
        if ($this->queueType !== '') {
            $query->where('ref_type', $this->queueType);
        }
        $counts = $query->selectRaw("
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
        $this->dispatch('open-modal', name: 'casemix-review-' . $this->queueType);
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
                'UGD' => $this->findDataUGD($refNo),
                'RJ' => $this->findDataRJ($refNo),
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
                } elseif ($slot === 2) {
                    $this->generateBerkasGrouping($refNo, $refType);
                    $generated[] = 'GROUPING';
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
        $dataTxn = match ($refType) {
            'RI' => $this->findDataRI($refNo),
            'UGD' => $this->findDataUGD($refNo),
            default => $this->findDataRJ($refNo),
        };
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

    private function generateBerkasGrouping(int $refNo, string $refType): void
    {
        $dataTxn = match ($refType) {
            'RI', 'RANAP' => $this->findDataRI($refNo),
            'UGD' => $this->findDataUGD($refNo),
            default => $this->findDataRJ($refNo),
        };

        $idrg = $dataTxn['idrg'] ?? [];
        if (empty($idrg['klaimFinal'])) {
            throw new \RuntimeException('Klaim belum final — grouping belum bisa dicetak.');
        }

        $nomorSep = $idrg['nomorSep'] ?? null;
        if (empty($nomorSep)) {
            throw new \RuntimeException('Nomor SEP klaim tidak ditemukan.');
        }

        $res = $this->printClaim($nomorSep)->getOriginalContent();
        if (($res['metadata']['code'] ?? 0) != 200) {
            throw new \RuntimeException(self::describeEklaimError($res['metadata'] ?? [], 'Cetak Grouping'));
        }

        $pdfBase64 = $res['response']['data'] ?? ($res['response'] ?? null);
        if (empty($pdfBase64) || !is_string($pdfBase64)) {
            throw new \RuntimeException('Response cetak tidak berisi PDF.');
        }

        $this->saveBerkasBpjs($refNo, $refType, 2, base64_decode($pdfBase64));
    }

    private function generateBerkasSkdp(int $refNo, string $refType): void
    {
        $dataTxn = match ($refType) {
            'RI' => $this->findDataRI($refNo),
            'UGD' => $this->findDataUGD($refNo),
            default => $this->findDataRJ($refNo),
        };
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

    private function syncDiagnosaToEmr(int $rjNo, string $refType): void
    {
        $isUGD = strtoupper($refType) === 'UGD';
        $table = $isUGD ? 'rstxn_ugdhdrs' : 'rstxn_rjhdrs';
        $jsonCol = $isUGD ? 'datadaftarugd_json' : 'datadaftarpolirj_json';

        try {
            $lob = DB::table($table)->where('rj_no', $rjNo)->value($jsonCol);
            $jsonRaw = is_object($lob) && method_exists($lob, 'read') ? $lob->read($lob->size()) : (string) $lob;
            $emr = json_decode($jsonRaw ?: '{}', true) ?? [];

            $existingDx = $emr['diagnosis'] ?? [];
            $existingCodes = collect($existingDx)->pluck('diagId')->toArray();

            $newDx = collect($this->editedDiagnosa)->filter(fn($d) => !in_array($d['code'] ?? '', $existingCodes));

            foreach ($newDx as $d) {
                $diagCode = $d['code'] ?? '';
                if (!DB::table('rsmst_mstdiags')->where('diag_id', $diagCode)->exists()) continue;

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
                    'diagDesc' => $d['desc'] ?? '',
                    'icdX' => $diagCode,
                    'ketdiagnosa' => 'Keterangan Diagnosa',
                    'kategoriDiagnosa' => $kategori,
                    'rjDtlDtl' => $dtlId,
                    'rjNo' => $rjNo,
                ];
            }

            $emr['diagnosis'] = $existingDx;
            $updateData = [$jsonCol => json_encode($emr)];
            if (!$isUGD) $updateData['rj_diagnosa'] = 'D';

            DB::table($table)->where('rj_no', $rjNo)->update($updateData);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Casemix approve: failed to sync EMR', [
                'rj_no' => $rjNo, 'ref_type' => $refType, 'error' => $e->getMessage(),
            ]);
        }
    }

    private function extractSoapText(array $emr, string $refType = 'RJ'): string
    {
        return match (strtoupper($refType)) {
            'RI' => $this->extractSoapTextRI($emr),
            default => $this->extractSoapTextRJ($emr),
        };
    }

    private function extractSoapTextRJ(array $emr): string
    {
        $parts = [];

        $keluhan = data_get($emr, 'anamnesa.keluhanUtama.keluhanUtama', '');
        $riwayat = data_get($emr, 'anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum', '');
        if ($keluhan || $riwayat) {
            $parts[] = 'S: ' . trim($keluhan . '. ' . $riwayat);
        }

        $tv = data_get($emr, 'pemeriksaan.tandaVital', []);
        $vitals = collect([
            'TD' => ($tv['sistolik'] ?? '') . '/' . ($tv['distolik'] ?? ''),
            'N' => $tv['frekuensiNadi'] ?? '',
            'RR' => $tv['frekuensiNafas'] ?? '',
            'S' => $tv['suhu'] ?? '',
            'SpO2' => $tv['spo2'] ?? '',
            'GDA' => $tv['gda'] ?? '',
        ])->filter(fn($v) => $v && $v !== '/' && $v !== '0')->map(fn($v, $k) => "$k:$v")->implode(', ');
        $fisik = data_get($emr, 'pemeriksaan.fisik', '');
        $penunjang = data_get($emr, 'pemeriksaan.penunjang', '');
        $obj = trim(implode('. ', array_filter([$vitals, $fisik, $penunjang])));
        if ($obj) $parts[] = 'O: ' . $obj;

        $dxFreeText = $emr['diagnosisFreeText'] ?? '';
        $dxCoded = collect($emr['diagnosis'] ?? [])->map(fn($d) =>
            ($d['icdX'] ?? $d['diagId'] ?? '') . ' ' . ($d['diagDesc'] ?? '')
        )->implode('; ');
        $assessment = trim(implode('. ', array_filter([$dxFreeText, $dxCoded])));
        if ($assessment) $parts[] = 'A: ' . $assessment;

        $terapi = data_get($emr, 'perencanaan.terapi.terapi', '');
        $tindakLanjut = data_get($emr, 'perencanaan.tindakLanjut.tindakLanjut', '');
        $plan = trim(implode('. ', array_filter([$tindakLanjut, $terapi])));
        if ($plan) $parts[] = 'P: ' . $plan;

        return implode("\n", $parts);
    }

    private function extractSoapTextRI(array $emr): string
    {
        $parts = [];
        $dok = $emr['pengkajianDokter'] ?? [];

        // CPPT entries (SOAP terstruktur)
        $cppts = $emr['cppt'] ?? [];
        if (!empty($cppts)) {
            $latest = end($cppts);
            $soap = $latest['soap'] ?? [];
            if (!empty($soap['subjective'])) $parts[] = 'S: ' . $soap['subjective'];
            if (!empty($soap['objective'])) $parts[] = 'O: ' . $soap['objective'];
            if (!empty($soap['assessment'])) $parts[] = 'A: ' . $soap['assessment'];
            if (!empty($soap['plan'])) $parts[] = 'P: ' . $soap['plan'];
        }

        // Fallback dari pengkajianDokter jika CPPT kosong
        if (empty($parts)) {
            $keluhan = data_get($dok, 'anamnesa.keluhanUtama', '');
            $riwayat = data_get($dok, 'anamnesa.riwayatPenyakit.sekarang', '');
            if ($keluhan || $riwayat) {
                $parts[] = 'S: ' . trim($keluhan . '. ' . $riwayat);
            }

            $fisik = is_string($dok['fisik'] ?? null) ? $dok['fisik'] : '';
            if ($fisik) $parts[] = 'O: ' . $fisik;

            $dxAwal = data_get($dok, 'diagnosaAssesment.diagnosaAwal', '');
            if ($dxAwal) $parts[] = 'A: ' . $dxAwal;

            $terapi = data_get($dok, 'rencana.terapi', '');
            if ($terapi) $parts[] = 'P: ' . $terapi;
        }

        // Tambahkan diagnosa awal dari pengkajianDokter jika belum ada di A:
        $dxAwal = data_get($dok, 'diagnosaAssesment.diagnosaAwal', '');
        if ($dxAwal && !collect($parts)->contains(fn($p) => str_contains($p, $dxAwal))) {
            $existingA = collect($parts)->first(fn($p) => str_starts_with($p, 'A:'));
            if ($existingA) {
                $parts = collect($parts)->map(fn($p) =>
                    str_starts_with($p, 'A:') ? $p . '. Dx awal: ' . $dxAwal : $p
                )->toArray();
            } else {
                $parts[] = 'A: ' . $dxAwal;
            }
        }

        return implode("\n", $parts);
    }

    private function calculateEmrPercent(array $emr, string $refType): array
    {
        return match (strtoupper($refType)) {
            'RI' => $this->calculateEmrPercentRI($emr),
            'UGD' => $this->calculateEmrPercentUGD($emr),
            default => $this->calculateEmrPercentRJ($emr),
        };
    }

    private function extractExistingDiagnosis(array $emr, string $refType = 'RJ'): array
    {
        if (in_array(strtoupper($refType), ['RI', 'RANAP'])) {
            return [];
        }
        return collect($emr['diagnosis'] ?? [])->map(fn($d) => [
            'code' => $d['icdX'] ?? $d['diagId'] ?? '',
            'desc' => $d['diagDesc'] ?? '',
            'kategori' => $d['kategoriDiagnosa'] ?? 'Secondary',
        ])->values()->toArray();
    }

    private function extractIdrgBridging(array $emr): array
    {
        $idrg = $emr['idrg'] ?? [];
        if (empty($idrg)) return ['diagnosa' => [], 'prosedur' => [], 'text' => ''];

        $dx = collect($idrg['coderDiagnosa'] ?? $idrg['coderInacbgDiagnosa'] ?? [])
            ->map(fn($d) => [
                'code' => $d['code'] ?? '',
                'desc' => $d['desc'] ?? $d['display'] ?? '',
                'kategori' => $d['kategori'] ?? 'Secondary',
            ])->values()->toArray();

        $proc = [];
        $coderProc = $idrg['coderProsedur'] ?? $idrg['coderInacbgProsedur'] ?? [];
        if (!empty($coderProc)) {
            $proc = collect($coderProc)->map(fn($p) => [
                'code' => $p['code'] ?? '',
                'desc' => $p['desc'] ?? $p['display'] ?? '',
            ])->values()->toArray();
        }

        $textParts = [];
        if (!empty($dx)) {
            $textParts[] = 'Diagnosa bridging: ' . collect($dx)->map(fn($d) =>
                $d['code'] . ' ' . $d['desc'] . ' (' . $d['kategori'] . ')'
            )->implode('; ');
        }
        if (!empty($proc)) {
            $textParts[] = 'Prosedur bridging: ' . collect($proc)->map(fn($p) =>
                $p['code'] . ' ' . $p['desc']
            )->implode('; ');
        }

        $group = $idrg['idrgGroup'] ?? $idrg['inacbgGroup'] ?? [];
        if (!empty($group['drg_code'])) {
            $textParts[] = 'DRG: ' . $group['drg_code'] . ' — ' . ($group['drg_description'] ?? '');
        }

        return [
            'diagnosa' => $dx,
            'prosedur' => $proc,
            'text' => implode('. ', $textParts),
        ];
    }

    public function scanTransaksi(): void
    {
        $type = $this->queueType;
        $this->selectedIds = [];

        [$start, $end] = $this->scanDateRange();
        $this->scanning = true;

        try {
            DB::table('rstxn_approval_queue')
                ->where('module', 'casemix')
                ->where('ref_type', $type)
                ->delete();

            $inserted = 0;

            if ($type === 'RJ') {
                $inserted = $this->scanRJ($start, $end);
            } elseif ($type === 'RI') {
                $inserted = $this->scanRI($start, $end);
            } elseif ($type === 'UGD') {
                $inserted = $this->scanUGD($start, $end);
            }

            $this->dispatch('toast', type: 'success', message: $inserted . ' transaksi ' . $type . ' BPJS dimasukkan ke antrian.');
            $this->incrementVersion('casemix-queue-toolbar');
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Scan ' . $type . ' gagal: ' . $e->getMessage());
        }

        $this->scanning = false;
    }

    private function scanRJ(Carbon $start, Carbon $end): int
    {
        $rows = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
            ->whereRaw("REGEXP_LIKE(h.vno_sep, '^\d{4}')")
            ->whereRaw("trunc(h.rj_date) >= to_date(?, 'YYYY-MM-DD')", [$start->format('Y-m-d')])
            ->whereRaw("trunc(h.rj_date) <= to_date(?, 'YYYY-MM-DD')", [$end->format('Y-m-d')])
            ->select('h.rj_no', 'h.reg_no', 'p.reg_name', 'h.vno_sep', 'h.rj_date',
                DB::raw("to_char(h.rj_date, 'DD/MM/YYYY HH24:MI') as tgl_kunjungan"))
            ->get();

        $inserted = 0;
        foreach ($rows as $row) {
            $emr = $this->findDataRJ($row->rj_no);
            $this->insertScanRow($emr, 'RJ', $row->rj_no, $row->reg_no, $row->reg_name, $row->vno_sep, $row->rj_date, $row->tgl_kunjungan);
            $inserted++;
        }
        return $inserted;
    }

    private function scanRI(Carbon $start, Carbon $end): int
    {
        $rows = DB::table('rstxn_rihdrs as h')
            ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
            ->whereRaw("REGEXP_LIKE(h.vno_sep, '^\d{4}')")
            ->whereRaw("trunc(h.entry_date) >= to_date(?, 'YYYY-MM-DD')", [$start->format('Y-m-d')])
            ->whereRaw("trunc(h.entry_date) <= to_date(?, 'YYYY-MM-DD')", [$end->format('Y-m-d')])
            ->select('h.rihdr_no', 'h.reg_no', 'p.reg_name', 'h.vno_sep', 'h.entry_date',
                DB::raw("to_char(h.entry_date, 'DD/MM/YYYY HH24:MI') as tgl_kunjungan"))
            ->get();

        $inserted = 0;
        foreach ($rows as $row) {
            $emr = $this->findDataRI($row->rihdr_no);
            $this->insertScanRow($emr, 'RI', $row->rihdr_no, $row->reg_no, $row->reg_name, $row->vno_sep, $row->entry_date, $row->tgl_kunjungan);
            $inserted++;
        }
        return $inserted;
    }

    private function scanUGD(Carbon $start, Carbon $end): int
    {
        $rows = DB::table('rstxn_ugdhdrs as h')
            ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
            ->whereRaw("REGEXP_LIKE(h.vno_sep, '^\d{4}')")
            ->whereRaw("trunc(h.rj_date) >= to_date(?, 'YYYY-MM-DD')", [$start->format('Y-m-d')])
            ->whereRaw("trunc(h.rj_date) <= to_date(?, 'YYYY-MM-DD')", [$end->format('Y-m-d')])
            ->select('h.rj_no', 'h.reg_no', 'p.reg_name', 'h.vno_sep', 'h.rj_date',
                DB::raw("to_char(h.rj_date, 'DD/MM/YYYY HH24:MI') as tgl_kunjungan"))
            ->get();

        $inserted = 0;
        foreach ($rows as $row) {
            $emr = $this->findDataUGD($row->rj_no);
            $this->insertScanRow($emr, 'UGD', $row->rj_no, $row->reg_no, $row->reg_name, $row->vno_sep, $row->rj_date, $row->tgl_kunjungan);
            $inserted++;
        }
        return $inserted;
    }

    private function insertScanRow(array $emr, string $refType, $refNo, $regNo, $regName, $vnoSep, $refDate, $tglKunjungan): void
    {
        $soapText = $this->extractSoapText($emr, $refType);
        $existingDx = $this->extractExistingDiagnosis($emr, $refType);
        $bridging = $this->extractIdrgBridging($emr);
        $emrPct = $this->calculateEmrPercent($emr, $refType);

        $fullSoap = $soapText;
        if ($bridging['text']) $fullSoap .= "\n\n[Bridging sebelumnya]\n" . $bridging['text'];

        $initialDx = !empty($bridging['diagnosa']) ? $bridging['diagnosa'] : $existingDx;

        DB::table('rstxn_approval_queue')->insert([
            'module' => 'casemix',
            'ref_no' => $refNo,
            'ref_type' => $refType,
            'reg_no' => $regNo,
            'reg_name' => mb_substr($regName ?? '', 0, 100),
            'vno_sep' => mb_substr($vnoSep ?? '', 0, 30),
            'ref_date' => $refDate,
            'ai_payload' => json_encode([
                'diagnosa' => $initialDx,
                'prosedur' => $bridging['prosedur'],
                'soap_text' => mb_substr($fullSoap, 0, 4000),
                'has_bridging' => !empty($bridging['diagnosa']),
                'emr_percent' => $emrPct['emr'],
                'emr_sections' => $emrPct['sections'],
                'tgl_kunjungan' => $tglKunjungan ?? '',
            ]),
            'ai_confidence' => 0,
            'ai_notes' => '',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function clearQueue(): void
    {
        $deleted = DB::table('rstxn_approval_queue')
            ->where('module', 'casemix')
            ->where('ref_type', $this->queueType)
            ->delete();
        $this->selectedIds = [];
        $this->dispatch('toast', type: 'info', message: $deleted . ' item ' . $this->queueType . ' dihapus dari antrian.');
        $this->incrementVersion('casemix-queue-toolbar');
        $this->resetPage();
    }

    public function aiSuggestBatch(): void
    {
        $apiKey = config('ai.api_key');
        if (empty($apiKey)) {
            $this->dispatch('toast', type: 'error', message: 'AI API key belum dikonfigurasi (AI_API_KEY di .env).');
            return;
        }

        $batchSize = 5;

        $query = DB::table('rstxn_approval_queue')
            ->where('module', 'casemix')
            ->where('ref_type', $this->queueType)
            ->where('status', 'pending')
            ->whereNull('ai_model')
            ->select('approval_id', 'ai_payload', 'reg_name', 'ref_type')
            ->orderBy('approval_id');

        if (!empty($this->selectedIds)) {
            $query->whereIn('approval_id', $this->selectedIds);
        }

        $pending = $query->get();

        if ($pending->isEmpty()) {
            $this->dispatch('toast', type: 'info', message: 'Tidak ada item pending yang perlu AI suggest.');
            return;
        }

        $batch = empty($this->selectedIds) ? $pending->take($batchSize) : $pending;
        $remaining = $pending->count() - $batch->count();

        $this->aiRunning = true;
        $this->aiTotal = $batch->count();
        $this->aiProgress = 0;
        $processed = 0;
        $errors = 0;

        foreach ($batch as $item) {
            $this->aiProgress++;

            try {
                $payload = json_decode($item->ai_payload ?? '{}', true) ?: [];
                $soapText = $payload['soap_text'] ?? '';

                if (mb_strlen(trim($soapText)) < 10) {
                    continue;
                }

                $result = $this->callAiIcdSuggest($soapText, $item->ref_type);

                if ($result) {
                    $payload['diagnosa'] = $result['diagnosa'] ?? $payload['diagnosa'];
                    $payload['prosedur'] = $result['prosedur'] ?? [];
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
                    $processed++;
                }
            } catch (\Throwable $e) {
                $errors++;
                \Illuminate\Support\Facades\Log::warning('AI suggest ICD error', [
                    'approval_id' => $item->approval_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->aiRunning = false;
        $msg = $processed . '/' . $this->aiTotal . ' item berhasil diproses AI.';
        if ($errors > 0) $msg .= ' ' . $errors . ' gagal.';
        if ($remaining > 0) $msg .= ' Sisa ' . $remaining . ' — klik Run AI lagi.';
        $this->dispatch('toast', type: $errors > 0 ? 'warning' : 'success', message: $msg);
        $this->incrementVersion('casemix-queue-toolbar');
    }

    public function aiSuggestSingle(int $approvalId): void
    {
        $apiKey = config('ai.api_key');
        if (empty($apiKey)) {
            $this->dispatch('toast', type: 'error', message: 'AI API key belum dikonfigurasi (AI_API_KEY di .env).');
            return;
        }

        $item = DB::table('rstxn_approval_queue')->where('approval_id', $approvalId)->first();
        if (!$item) return;

        $payload = json_decode($item->ai_payload ?? '{}', true) ?: [];
        $soapText = $payload['soap_text'] ?? '';

        if (mb_strlen(trim($soapText)) < 10) {
            $this->dispatch('toast', type: 'warning', message: 'SOAP text terlalu pendek untuk AI suggest.');
            return;
        }

        try {
            $result = $this->callAiIcdSuggest($soapText, $item->ref_type);

            if ($result) {
                $payload['diagnosa'] = $result['diagnosa'] ?? $payload['diagnosa'];
                $payload['prosedur'] = $result['prosedur'] ?? [];
                $payload['rejected_diagnosa'] = $result['rejected_diagnosa'] ?? [];
                $payload['rejected_prosedur'] = $result['rejected_prosedur'] ?? [];

                DB::table('rstxn_approval_queue')
                    ->where('approval_id', $approvalId)
                    ->update([
                        'ai_payload' => json_encode($payload),
                        'ai_confidence' => $result['confidence'] ?? 0,
                        'ai_notes' => $result['notes'] ?? '',
                        'ai_model' => config('ai.model'),
                        'updated_at' => now(),
                    ]);

                if ($this->reviewId === $approvalId) {
                    $this->editedDiagnosa = $result['diagnosa'] ?? [];
                    $this->editedProsedur = $result['prosedur'] ?? [];
                    $this->reviewData['ai_confidence'] = $result['confidence'] ?? 0;
                    $this->reviewData['ai_notes'] = $result['notes'] ?? '';
                    $this->reviewData['ai_payload'] = json_encode($payload);
                }

                $this->dispatch('toast', type: 'success', message: 'AI suggest selesai — confidence ' . ($result['confidence'] ?? 0) . '%');
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'AI suggest gagal: ' . $e->getMessage());
        }
    }

    private function callAiIcdSuggest(string $soapText, string $refType): ?array
    {
        $systemPrompt = <<<'PROMPT'
Kamu adalah coder ICD medis berpengalaman di rumah sakit Indonesia.
Tugas: dari catatan SOAP, tentukan kode ICD-10 (diagnosa) dan ICD-9-CM (prosedur) yang sesuai untuk klaim BPJS/INA-CBG.

Aturan:
- Diagnosa pertama harus Primary (diagnosa utama yang paling resource-intensive).
- Diagnosa tambahan sebagai Secondary.
- Kode harus spesifik (gunakan subkategori, bukan kode induk).
- Untuk prosedur, hanya jika ada tindakan medis yang dilakukan (operasi, prosedur invasif, dll).
- Konsultasi rawat jalan rutin TANPA tindakan: kosongkan prosedur.
- Jika ada [Bridging sebelumnya] di input, itu adalah kode ICD yang sudah pernah dipakai untuk klaim BPJS pasien ini. PRIORITASKAN kode tersebut jika klinis masih sesuai dengan SOAP saat ini. Tambahkan kode baru jika diperlukan.
- Confidence 0-100 berdasarkan keyakinan coding.
- Setiap diagnosa/prosedur WAJIB punya "reason" — alasan singkat kenapa kode itu dipilih berdasarkan data SOAP. Jika kode dari bridging sebelumnya, sebutkan "sesuai bridging sebelumnya + [alasan klinis]".
- "notes" berisi ringkasan keseluruhan: diagnosa utama karena apa, kenapa confidence segitu.

Jawab HANYA dalam format JSON (tanpa markdown fence):
{
  "diagnosa": [
    {"code": "E11.9", "desc": "Type 2 diabetes mellitus without complications", "kategori": "Primary", "confidence": 90, "reason": "GDA 186 + keluhan sering BAK + terapi Glimepirid & Metformin"},
    {"code": "I10", "desc": "Essential (primary) hypertension", "kategori": "Secondary", "confidence": 85, "reason": "TD 150/90 + terapi Amlodipine"}
  ],
  "prosedur": [
    {"code": "88.72", "desc": "Diagnostic ultrasound of heart", "confidence": 80, "reason": "Echo dilakukan sesuai catatan penunjang"}
  ],
  "confidence": 85,
  "notes": "DM tipe 2 sebagai Primary karena resource-intensive (terapi multipel). Confidence 85% karena SOAP lengkap tapi tidak ada hasil lab HbA1c."
}
PROMPT;

        $userPrompt = "Jenis layanan: " . ($refType === 'RI' ? 'Rawat Inap' : 'Rawat Jalan') . "\n\n" . $soapText;

        $response = Http::timeout(config('ai.timeout', 30))
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('ai.api_key'),
                'Content-Type' => 'application/json',
            ])
            ->post(config('ai.api_url'), [
                'model' => config('ai.model'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => config('ai.max_tokens', 2000),
                'temperature' => 0.2,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('AI API error: ' . $response->status() . ' — ' . $response->body());
        }

        $content = $response->json('choices.0.message.content', '');
        $content = preg_replace('/^```json?\s*/m', '', $content);
        $content = preg_replace('/```\s*$/m', '', $content);
        $parsed = json_decode(trim($content), true);

        if (!$parsed || !isset($parsed['diagnosa'])) {
            throw new \RuntimeException('AI response tidak valid: ' . mb_substr($content, 0, 200));
        }

        $validatedDx = [];
        $rejectedDx = [];
        foreach ($parsed['diagnosa'] ?? [] as $d) {
            $origCode = trim($d['code'] ?? '');
            if (!$origCode) continue;
            $code = $origCode;

            $master = DB::table('rsmst_mstdiags')->where('diag_id', $code)->first();

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
            'confidence' => (int) ($parsed['confidence'] ?? 0),
            'notes' => $aiNotes,
            'rejected_diagnosa' => $rejectedDx,
            'rejected_prosedur' => $rejectedProc,
        ];
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

        if (!$isRI && !empty($this->editedDiagnosa)) {
            $rjNo = (int) $refNo;
            if ($rjNo > 0) {
                $this->syncDiagnosaToEmr($rjNo, $refType);
            }
        }

        $this->dispatch('close-modal', name: 'casemix-review-' . $this->queueType);

        $bridgingResult = null;
        if (!$isRI) {
            $rjNo = (int) $refNo;
            if ($rjNo > 0) {
                $bridgingResult = $this->executeBridging($rjNo, $refType);
            }
        }

        if ($bridgingResult && $bridgingResult['success']) {
            DB::table('rstxn_approval_queue')
                ->where('approval_id', $this->reviewId)
                ->update(['status' => 'executed', 'updated_at' => now()]);
            $this->dispatch('toast', type: 'success', message: 'Approve + bridging selesai — klaim final. ' . ($bridgingResult['summary'] ?? ''));
        } elseif ($bridgingResult) {
            DB::table('rstxn_approval_queue')
                ->where('approval_id', $this->reviewId)
                ->update(['status' => 'failed', 'ai_notes' => DB::raw("ai_notes || ' | Bridging: " . addslashes($bridgingResult['error'] ?? 'unknown') . "'"), 'updated_at' => now()]);
            $this->dispatch('toast', type: 'warning', message: 'Approve OK tapi bridging gagal di step ' . ($bridgingResult['failedStep'] ?? '?') . ': ' . ($bridgingResult['error'] ?? ''));
        } else {
            $this->dispatch('toast', type: 'success', message: 'Klaim di-approve — diagnosa disinkron ke EMR.');
            if ($isRI) {
                $this->dispatch('daftar-ri.idrg.open', riHdrNo: $refNo);
            }
        }

        $this->reviewId = null;
        $this->reviewData = [];
        $this->emrData = [];
        $this->berkasStatus = [];
    }

    private function executeBridging(int $rjNo, string $refType): array
    {
        $isUGD = strtoupper($refType) === 'UGD';

        try {
            $data = $isUGD ? $this->findDataUGD($rjNo) : $this->findDataRJ($rjNo);
            if (empty($data)) {
                return ['success' => false, 'failedStep' => 'load', 'error' => 'Data transaksi tidak ditemukan'];
            }

            $pasienData = $this->findDataMasterPasien($data['regNo'] ?? '');
            $pasien = $pasienData['pasien'] ?? [];
            $idrg = $data['idrg'] ?? [];

            $nomorSep = $idrg['nomorSep'] ?? $idrg['claimNumber'] ?? ($data['sep']['noSep'] ?? '');
            if (empty($nomorSep)) {
                return ['success' => false, 'failedStep' => 'sep', 'error' => 'Nomor SEP kosong'];
            }

            $coderNik = (string) (auth()->user()->emp_id ?? '');
            if (empty($coderNik)) {
                return ['success' => false, 'failedStep' => 'coder', 'error' => 'User tidak punya emp_id (NIK coder)'];
            }

            $nomorKartu = data_get($pasien, 'identitas.idbpjs')
                ?: data_get($data, 'sep.resSep.peserta.noKartu')
                ?: data_get($data, 'sep.reqSep.t_sep.noKartu') ?: '';
            $nomorRm = $data['regNo'] ?? '';
            $namaPasien = $pasien['regName'] ?? ($data['regName'] ?? '');
            $tglLahir = $this->parseBirthForBridging($pasien['tglLahir'] ?? '');
            $gender = (int) data_get($pasien, 'jenisKelamin.jenisKelaminId', 1);

            $dxString = $this->buildDiagnosaString($this->editedDiagnosa);
            $procString = $this->buildProsedurString($this->editedProsedur);

            // Step 1: new_claim
            if (empty($idrg['nomorSep'])) {
                $res = $this->newClaim($nomorKartu, $nomorSep, $nomorRm, $namaPasien, $tglLahir, $gender)->getOriginalContent();
                if (($res['metadata']['code'] ?? 0) != 200) {
                    return ['success' => false, 'failedStep' => 'new_claim', 'error' => self::describeEklaimError($res['metadata'] ?? [], 'new_claim')];
                }
                $idrg['nomorSep'] = $nomorSep;
                $idrg['patientId'] = $res['response']['patient_id'] ?? null;
                $idrg['createdAt'] = now()->toIso8601String();
            }

            // Step 2: set diagnosa iDRG
            if (!empty($dxString)) {
                $res = $this->setDiagnosaIdrg($nomorSep, $dxString)->getOriginalContent();
                if (($res['metadata']['code'] ?? 0) != 200) {
                    $this->saveBridgingResult($rjNo, $refType, $data, $idrg);
                    return ['success' => false, 'failedStep' => 'set_diagnosa_idrg', 'error' => self::describeEklaimError($res['metadata'] ?? [], 'Set Diagnosa iDRG')];
                }
                $idrg['idrgDiagnosa'] = $res['response'] ?? [];
                $idrg['idrgDiagnosaString'] = $dxString;
                $idrg['idrgDiagnosaExpanded'] = $res['response']['expanded'] ?? [];
                $idrg['coderDiagnosa'] = $this->editedDiagnosa;
                $idrg['coderDiagnosaSyncedAt'] = now()->toIso8601String();
            }

            // Step 3: set prosedur iDRG
            $procForIdrg = !empty($procString) ? $procString : '#';
            $res = $this->setProsedurIdrg($nomorSep, $procForIdrg)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->saveBridgingResult($rjNo, $refType, $data, $idrg);
                return ['success' => false, 'failedStep' => 'set_prosedur_idrg', 'error' => self::describeEklaimError($res['metadata'] ?? [], 'Set Prosedur iDRG')];
            }
            $idrg['idrgProsedur'] = $res['response'] ?? [];
            $idrg['idrgProsedurString'] = $procForIdrg;

            // Step 4: set claim data (tarif + tanggal)
            $claimPayload = $this->buildClaimDataPayload($data, $pasien, $nomorSep, $coderNik, $rjNo, $isUGD);
            $res = $this->setClaimData($nomorSep, $claimPayload)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->saveBridgingResult($rjNo, $refType, $data, $idrg);
                return ['success' => false, 'failedStep' => 'set_claim_data', 'error' => self::describeEklaimError($res['metadata'] ?? [], 'Set Claim Data')];
            }
            $idrg['claimData'] = $claimPayload;
            $idrg['claimDataSavedAt'] = now()->toIso8601String();

            // Step 5: grouper iDRG stage 1
            $res = $this->grouperIdrgStage1($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->saveBridgingResult($rjNo, $refType, $data, $idrg);
                return ['success' => false, 'failedStep' => 'grouper_idrg_1', 'error' => self::describeEklaimError($res['metadata'] ?? [], 'Grouper iDRG')];
            }
            $groupResult = $res['response'] ?? [];
            $idrg['idrgGroup'] = $groupResult;
            $idrg['idrgUngroupable'] = ((string) ($groupResult['mdc_number'] ?? '') === '36');

            if ($idrg['idrgUngroupable']) {
                $this->saveBridgingResult($rjNo, $refType, $data, $idrg);
                return ['success' => false, 'failedStep' => 'grouper_idrg_1', 'error' => 'Ungroupable (MDC 36)'];
            }

            // Step 6: grouper iDRG stage 2 (if topup_options)
            if (!empty($groupResult['topup_options'])) {
                $topupCodes = is_array($groupResult['topup_options'])
                    ? implode('#', array_column($groupResult['topup_options'], 'code'))
                    : null;
                $res = $this->grouperIdrgStage2($nomorSep, $topupCodes)->getOriginalContent();
                if (($res['metadata']['code'] ?? 0) != 200) {
                    $this->saveBridgingResult($rjNo, $refType, $data, $idrg);
                    return ['success' => false, 'failedStep' => 'grouper_idrg_2', 'error' => self::describeEklaimError($res['metadata'] ?? [], 'Grouper iDRG Stage 2')];
                }
                $idrg['idrgStage2'] = $res['response'] ?? [];
            }

            // Step 7: final iDRG
            $res = $this->finalIdrg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->saveBridgingResult($rjNo, $refType, $data, $idrg);
                return ['success' => false, 'failedStep' => 'final_idrg', 'error' => self::describeEklaimError($res['metadata'] ?? [], 'Final iDRG')];
            }
            $idrg['idrgFinal'] = true;
            $idrg['idrgFinalAt'] = now()->toIso8601String();

            // Step 8: import iDRG to INACBG
            $res = $this->importIdrgToInacbg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->saveBridgingResult($rjNo, $refType, $data, $idrg);
                return ['success' => false, 'failedStep' => 'import_inacbg', 'error' => self::describeEklaimError($res['metadata'] ?? [], 'Import iDRG ke INACBG')];
            }

            // Step 9: set diagnosa INACBG
            if (!empty($dxString)) {
                $res = $this->setDiagnosaInacbg($nomorSep, $dxString)->getOriginalContent();
                if (($res['metadata']['code'] ?? 0) != 200) {
                    $this->saveBridgingResult($rjNo, $refType, $data, $idrg);
                    return ['success' => false, 'failedStep' => 'set_diagnosa_inacbg', 'error' => self::describeEklaimError($res['metadata'] ?? [], 'Set Diagnosa INACBG')];
                }
                $idrg['inacbgDiagnosa'] = $res['response'] ?? [];
                $idrg['coderInacbgDiagnosa'] = $this->editedDiagnosa;
            }

            // Step 10: set prosedur INACBG
            $res = $this->setProsedurInacbg($nomorSep, $procForIdrg)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->saveBridgingResult($rjNo, $refType, $data, $idrg);
                return ['success' => false, 'failedStep' => 'set_prosedur_inacbg', 'error' => self::describeEklaimError($res['metadata'] ?? [], 'Set Prosedur INACBG')];
            }
            $idrg['inacbgProsedur'] = $res['response'] ?? [];

            // Step 11: grouper INACBG stage 1
            $res = $this->grouperInacbgStage1($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->saveBridgingResult($rjNo, $refType, $data, $idrg);
                return ['success' => false, 'failedStep' => 'grouper_inacbg_1', 'error' => self::describeEklaimError($res['metadata'] ?? [], 'Grouper INACBG')];
            }
            $inacbgGroup = $res['response'] ?? [];
            $idrg['inacbgGroup'] = $inacbgGroup;
            $idrg['inacbgUngroupable'] = ((string) ($inacbgGroup['mdc_number'] ?? '') === '36');

            // Step 12: grouper INACBG stage 2 (if special_cmg)
            if (!empty($inacbgGroup['special_cmg'])) {
                $res = $this->grouperInacbgStage2($nomorSep, $inacbgGroup['special_cmg'])->getOriginalContent();
                if (($res['metadata']['code'] ?? 0) != 200) {
                    $this->saveBridgingResult($rjNo, $refType, $data, $idrg);
                    return ['success' => false, 'failedStep' => 'grouper_inacbg_2', 'error' => self::describeEklaimError($res['metadata'] ?? [], 'Grouper INACBG Stage 2')];
                }
                $idrg['inacbgStage2'] = $res['response'] ?? [];
            }

            // Step 13: final INACBG
            if (!$idrg['inacbgUngroupable']) {
                $res = $this->finalInacbg($nomorSep)->getOriginalContent();
                if (($res['metadata']['code'] ?? 0) != 200) {
                    $this->saveBridgingResult($rjNo, $refType, $data, $idrg);
                    return ['success' => false, 'failedStep' => 'final_inacbg', 'error' => self::describeEklaimError($res['metadata'] ?? [], 'Final INACBG')];
                }
                $idrg['inacbgFinal'] = true;
                $idrg['inacbgFinalAt'] = now()->toIso8601String();
            }

            // Step 14: final claim
            $res = $this->finalClaim($nomorSep, $coderNik)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->saveBridgingResult($rjNo, $refType, $data, $idrg);
                return ['success' => false, 'failedStep' => 'final_claim', 'error' => self::describeEklaimError($res['metadata'] ?? [], 'Final Klaim')];
            }
            $idrg['klaimFinal'] = true;
            $idrg['klaimFinalAt'] = now()->toIso8601String();
            $idrg['coderNik'] = $coderNik;

            $this->saveBridgingResult($rjNo, $refType, $data, $idrg);

            $drgCode = $groupResult['drg_code'] ?? ($inacbgGroup['drg_code'] ?? '-');
            return ['success' => true, 'summary' => 'DRG: ' . $drgCode];

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Casemix bridging error', [
                'rj_no' => $rjNo, 'ref_type' => $refType, 'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'failedStep' => 'exception', 'error' => $e->getMessage()];
        }
    }

    private function saveBridgingResult(int $rjNo, string $refType, array $data, array $idrg): void
    {
        $isUGD = strtoupper($refType) === 'UGD';
        DB::transaction(function () use ($rjNo, $data, $idrg, $isUGD) {
            if ($isUGD) {
                $this->lockUGDRow($rjNo);
                $fresh = $this->findDataUGD($rjNo);
            } else {
                $this->lockRJRow($rjNo);
                $fresh = $this->findDataRJ($rjNo);
            }
            $fresh['idrg'] = $idrg;
            if ($isUGD) {
                $this->updateJsonUGD($rjNo, $fresh);
            } else {
                $this->updateJsonRJ($rjNo, $fresh);
            }
        });
    }

    private function buildDiagnosaString(array $diagnosa): string
    {
        if (empty($diagnosa)) return '';
        $primary = [];
        $secondary = [];
        foreach ($diagnosa as $d) {
            $code = trim($d['code'] ?? '');
            if ($code === '') continue;
            if (($d['kategori'] ?? '') === 'Primary') {
                $primary[] = $code;
            } else {
                $secondary[] = $code;
            }
        }
        return implode('#', [...$primary, ...$secondary]);
    }

    private function buildProsedurString(array $prosedur): string
    {
        if (empty($prosedur)) return '';
        return implode('#', collect($prosedur)->pluck('code')->filter()->toArray());
    }

    private function buildClaimDataPayload(array $data, array $pasien, string $nomorSep, string $coderNik, int $rjNo, bool $isUGD): array
    {
        $cost = $isUGD ? $this->calculateUGDCosts($rjNo) : $this->calculateRJCosts($rjNo);
        $rjDate = $this->parseRjDateForBridging($data['rjDate'] ?? '');
        $hakKelas = (string) data_get($data, 'sep.resSep.peserta.hakKelas.kode', '3');
        $nomorKartu = data_get($pasien, 'identitas.idbpjs')
            ?: data_get($data, 'sep.resSep.peserta.noKartu')
            ?: data_get($data, 'sep.reqSep.t_sep.noKartu') ?: '';

        $umurTahun = '0';
        $umurHari = '0';
        $birthStr = trim((string) data_get($pasien, 'tglLahir', ''));
        if ($birthStr && $rjDate) {
            try {
                $birth = Carbon::parse($birthStr);
                $masuk = Carbon::parse($rjDate);
                $umurTahun = (string) $birth->diffInYears($masuk);
                $totalDays = $birth->diffInDays($masuk);
                $umurHari = (string) ($totalDays - ($birth->diffInYears($masuk) * 365));
                if ((int) $umurHari < 0) $umurHari = '0';
            } catch (\Throwable) {}
        }

        return [
            'nomor_sep' => $nomorSep,
            'nomor_kartu' => $nomorKartu,
            'tgl_masuk' => $rjDate,
            'tgl_pulang' => $rjDate,
            'cara_masuk' => $isUGD ? 'er' : 'gp',
            'jenis_rawat' => $isUGD ? '1' : '2',
            'kelas_rawat' => '3',
            'hak_kelas' => $hakKelas !== '' ? $hakKelas : '3',
            'umur_tahun' => $umurTahun,
            'umur_hari' => $umurHari,
            'discharge_status' => '1',
            'birth_weight' => '0',
            'sistole' => '0',
            'diastole' => '0',
            'nomor_kartu_t' => 'kartu_jkn',
            'payor_id' => env('IDRG_PAYOR_ID', '3'),
            'payor_cd' => env('IDRG_PAYOR_CD', 'JKN'),
            'kode_tarif' => env('IDRG_KODE_TARIF', 'DS'),
            'nama_dokter' => (string) ($data['drDesc'] ?? ''),
            'coder_nik' => $coderNik,
            'tarif_rs' => [
                'prosedur_non_bedah' => (string) ($cost['actePrice'] ?? 0),
                'prosedur_bedah' => '0',
                'konsultasi' => (string) (($cost['poliPrice'] ?? 0) + ($cost['rsAdmin'] ?? 0) + ($cost['rjAdmin'] ?? 0)),
                'tenaga_ahli' => (string) ($cost['actdPrice'] ?? 0),
                'keperawatan' => '0',
                'penunjang' => (string) (($cost['actpPrice'] ?? 0) + ($cost['other'] ?? 0)),
                'radiologi' => (string) ($cost['rad'] ?? 0),
                'laboratorium' => (string) ($cost['lab'] ?? 0),
                'pelayanan_darah' => '0',
                'rehabilitasi' => '0',
                'kamar' => '0',
                'rawat_intensif' => '0',
                'obat' => (string) ($cost['obat'] ?? 0),
                'obat_kronis' => '0',
                'obat_kemoterapi' => '0',
                'alkes' => '0',
                'bmhp' => '0',
                'sewa_alat' => '0',
            ],
            'apgar' => [
                'menit_1' => ['appearance' => 0, 'pulse' => 0, 'grimace' => 0, 'activity' => 0, 'respiration' => 0],
                'menit_5' => ['appearance' => 0, 'pulse' => 0, 'grimace' => 0, 'activity' => 0, 'respiration' => 0],
            ],
        ];
    }

    private function parseBirthForBridging(string $str): string
    {
        if (empty($str)) return '';
        try {
            return Carbon::parse($str)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return '';
        }
    }

    private function parseRjDateForBridging(string $str): string
    {
        if (empty($str)) return '';
        $formats = ['d/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d'];
        foreach ($formats as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, trim($str))->format('Y-m-d H:i:s');
            } catch (\Throwable) {}
        }
        try {
            return Carbon::parse($str)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return '';
        }
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

        $this->dispatch('close-modal', name: 'casemix-review-' . $this->queueType);
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
        title="Casemix {{ $queueType }} — E-Klaim"
        subtitle="Review ICD coding AI → Approve → Bridging iDRG/INA-CBG → Upload berkas BPJS & Grouping" />

    {{-- TOOLBAR --}}
    <div class="sticky z-30 px-4 py-3 mb-4 bg-canvas border-b border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700"
         wire:key="casemix-queue-toolbar-{{ $renderVersions['casemix-queue-toolbar'] ?? 0 }}">
        <div class="flex items-end gap-3 overflow-x-auto">

            {{-- LEFT: Scan button + Mode --}}
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
                            class="block w-full pl-10 sm:w-32" placeholder="mm/yyyy" maxlength="7" />
                    @else
                        <x-text-input type="text" wire:model="scanTanggal"
                            class="block w-full pl-10 sm:w-36" placeholder="dd/mm/yyyy" maxlength="10" />
                    @endif
                </div>
            </div>

            {{-- CENTER: AI + Status + Selected --}}
            {{-- Status filter buttons --}}
            @php $s = $this->stats; @endphp
            <div class="grid grid-cols-3 gap-1 mt-auto shrink-0">
                <button type="button" wire:click="$set('filterStatus', 'pending')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg border transition-all
                        {{ $filterStatus === 'pending' ? 'bg-amber-100 border-amber-400 text-amber-700 font-semibold dark:bg-amber-900/30 dark:border-amber-500 dark:text-amber-300' : 'bg-canvas border-gray-300 text-muted hover:bg-amber-50 dark:border-gray-600 dark:hover:bg-amber-900/20' }}">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>{{ $s['pending'] ?? 0 }} Pending
                </button>
                <button type="button" wire:click="$set('filterStatus', 'approved')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg border transition-all
                        {{ $filterStatus === 'approved' ? 'bg-blue-100 border-blue-400 text-blue-700 font-semibold dark:bg-blue-900/30 dark:border-blue-500 dark:text-blue-300' : 'bg-canvas border-gray-300 text-muted hover:bg-blue-50 dark:border-gray-600 dark:hover:bg-blue-900/20' }}">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>{{ $s['approved'] ?? 0 }} Approved
                </button>
                <button type="button" wire:click="$set('filterStatus', 'executed')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg border transition-all
                        {{ $filterStatus === 'executed' ? 'bg-emerald-100 border-emerald-400 text-emerald-700 font-semibold dark:bg-emerald-900/30 dark:border-emerald-500 dark:text-emerald-300' : 'bg-canvas border-gray-300 text-muted hover:bg-emerald-50 dark:border-gray-600 dark:hover:bg-emerald-900/20' }}">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>{{ $s['executed'] ?? 0 }} Executed
                </button>
                <button type="button" wire:click="$set('filterStatus', 'failed')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg border transition-all
                        {{ $filterStatus === 'failed' ? 'bg-red-100 border-red-400 text-red-700 font-semibold dark:bg-red-900/30 dark:border-red-500 dark:text-red-300' : 'bg-canvas border-gray-300 text-muted hover:bg-red-50 dark:border-gray-600 dark:hover:bg-red-900/20' }}">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>{{ $s['failed'] ?? 0 }} Failed
                </button>
                <button type="button" wire:click="$set('filterStatus', 'rejected')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg border transition-all
                        {{ $filterStatus === 'rejected' ? 'bg-gray-200 border-gray-400 text-gray-700 font-semibold dark:bg-gray-700 dark:border-gray-500 dark:text-gray-300' : 'bg-canvas border-gray-300 text-muted hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-800' }}">
                    <span class="w-2 h-2 rounded-full bg-gray-400"></span>{{ $s['rejected'] ?? 0 }} Rejected
                </button>
                <button type="button" wire:click="$set('filterStatus', '')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg border transition-all
                        {{ $filterStatus === '' ? 'bg-gray-100 border-gray-400 text-ink font-semibold dark:bg-gray-700 dark:border-gray-500 dark:text-white' : 'bg-canvas border-gray-300 text-muted hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800' }}">
                    {{ $s['total'] ?? 0 }} Semua
                </button>
            </div>

            {{-- Run AI --}}
            <div class="mt-auto">
                <x-info-button wire:click="aiSuggestBatch" wire:loading.attr="disabled"
                    wire:target="aiSuggestBatch"
                    title="Jalankan AI suggest ICD — {{ !empty($selectedIds) ? count($selectedIds) . ' dipilih' : '5 record per batch' }}">
                    <span wire:loading.remove wire:target="aiSuggestBatch" class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z" />
                        </svg>
                        Run AI {{ !empty($selectedIds) ? '(' . count($selectedIds) . ')' : '(5)' }}
                    </span>
                    <span wire:loading wire:target="aiSuggestBatch" class="flex items-center gap-1">
                        <x-loading /> {{ $aiProgress }}/{{ $aiTotal }}
                    </span>
                </x-info-button>
            </div>

            @if (!empty($selectedIds))
                <span class="text-xs font-semibold text-primary dark:text-primary-light px-2 py-0.5 rounded-full bg-primary/10 mt-auto">{{ count($selectedIds) }} dipilih</span>
            @endif

            {{-- RIGHT: per page + Clear --}}
            <div class="flex items-center gap-3 ml-auto shrink-0">
                <x-toolbar-refresh-reset :label="null" />
                <div class="w-24">
                    <x-select-input wire:model.live="itemsPerPage">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </x-select-input>
                </div>
                <x-confirm-button variant="danger" action="clearQueue()"
                    title="Hapus Antrian" message="Semua antrian {{ $queueType }} akan dihapus. Lanjutkan?"
                    confirmText="Ya, hapus" cancelText="Batal">
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
                <span class="truncate">Panduan: cara pakai Casemix / E-Klaim</span>
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
                    <li>Role user harus <span class="font-semibold">Admin</span> &mdash; fitur AI casemix sementara hanya untuk admin.</li>
                    <li>Transaksi pasien BPJS harus sudah punya <span class="font-semibold">SEP</span> (via Kelola SEP di daftar RJ/RI/UGD).</li>
                    <li>Data SOAP / assessment EMR harus sudah diisi oleh dokter.</li>
                    <li>Koneksi ke server <span class="font-semibold">E-Klaim</span> harus aktif (bridging iDRG).</li>
                    <li>AI suggest pakai OpenClaw Gateway (sudah terkonfigurasi). Bisa override ke provider lain via <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">AI_API_URL</span> di <code>.env</code>.</li>
                </ul>
            </div>

            {{-- ALUR LENGKAP --}}
            <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                <div class="font-semibold">Alur lengkap</div>
                <ol class="mt-1 ml-4 space-y-1 list-decimal">
                    <li><span class="font-semibold">Scan Transaksi</span> &mdash; isi bulan
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">mm/yyyy</span>
                        di toolbar, lalu klik
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Scan</span>.
                        Sistem mengambil semua transaksi BPJS (punya SEP) di bulan tersebut beserta data
                        SOAP dari EMR, lalu memasukkannya ke antrian
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Pending Review</span>.
                        <br><span class="text-xs italic">Scan baru otomatis menghapus antrian lama (clear sesi sebelumnya).</span></li>
                    <li><span class="font-semibold">Pilih Item (opsional)</span> &mdash; gunakan toggle di kolom pertama
                        untuk memilih item tertentu. Toggle di header tabel untuk select/deselect semua di halaman.
                        Jumlah item terpilih tampil di toolbar sebagai badge
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">N dipilih</span>.</li>
                    <li><span class="font-semibold">Run AI</span> &mdash; klik
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Run AI</span>
                        di toolbar. Jika ada item yang dipilih, AI hanya memproses item tersebut.
                        Jika tidak ada yang dipilih, AI memproses 5 item pending per batch.
                        AI membaca data SOAP (Subjective, Objective, Assessment, Plan) dari EMR dan
                        menyarankan kode ICD-10 (diagnosa) dan ICD-9-CM (prosedur). Kode divalidasi
                        terhadap database rumah sakit. Bisa juga per item lewat tombol
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">AI Suggest</span>
                        di modal review.</li>
                    <li><span class="font-semibold">Review &amp; Approve</span> &mdash; klik baris untuk buka detail.
                        Periksa diagnosa &amp; prosedur hasil AI. Jika sesuai, klik
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Approve</span>.
                        Jika tidak,
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Reject</span>.</li>
                    <li><span class="font-semibold">Bridging iDRG / INA-CBG</span> &mdash; untuk RJ dan UGD, bridging
                        berjalan otomatis saat Approve (14 langkah: New Claim &rarr; Set Diagnosa &rarr;
                        Set Data Klaim &rarr; Grouper iDRG &rarr; Final iDRG &rarr; Import INACBG &rarr;
                        Grouper INACBG &rarr; Final INACBG &rarr; Final Claim). Untuk RI, bridging manual
                        lewat modal iDRG.</li>
                    <li><span class="font-semibold">Upload Berkas BPJS</span> &mdash; setelah klaim
                        <span class="font-semibold">Final</span>, klik
                        <span class="font-mono text-xs bg-blue-100 dark:bg-blue-900/40 px-1 rounded">Auto Upload Berkas</span>
                        untuk upload SEP, Rekam Medis, dan berkas lainnya.</li>
                    <li><span class="font-semibold">Upload Grouping</span> &mdash; grouping (slot 2) baru bisa dicetak
                        setelah klaim final. Klik Auto Upload sekali lagi setelah bridging selesai.</li>
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
                    <li>Bridging bisa dihentikan di tengah jalan &mdash; progress tersimpan di JSON transaksi,
                        bisa dilanjutkan dari menu Casemix.</li>
                    <li>Data EMR asli <span class="font-semibold">tidak pernah ditimpa</span> &mdash; bridging menulis
                        ke namespace <span class="font-mono text-xs">idrg.*</span> terpisah.</li>
                    <li>Kondisi khusus (persalinan O80/O82, neonatus APGAR, TB SITB) otomatis terdeteksi.</li>
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
                        <th class="px-3 py-3 w-10">
                            <x-toggle :current="count($selectedIds) > 0 && !array_diff($this->rows->pluck('approval_id')->toArray(), $selectedIds) ? '1' : '0'" trueValue="1" falseValue="0" wireClick="toggleSelectAll" />
                        </th>
                        <th class="px-4 py-3">Pasien</th>
                        <th class="px-4 py-3">Diagnosa (AI Suggest)</th>
                        <th class="px-4 py-3 text-center">Confidence</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Reviewer</th>
                        <th class="px-4 py-3 text-center">Tgl Kunjungan</th>
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
                            {{-- Toggle --}}
                            <td class="px-3 py-3 align-top">
                                <x-toggle :current="in_array($row->approval_id, $selectedIds) ? '1' : '0'" trueValue="1" falseValue="0" wireClick="toggleSelect({{ $row->approval_id }})" />
                            </td>
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
                                        @if (isset($d['validated']) && !$d['validated'])
                                            <span class="text-[10px] text-amber-600 dark:text-amber-400" title="Kode tidak ditemukan di database">?</span>
                                        @endif
                                    </div>
                                @endforeach
                                @if (count($diagnosa) > 3)
                                    <div class="mt-1 text-xs text-muted">+{{ count($diagnosa) - 3 }} lainnya</div>
                                @endif
                                @if (empty($diagnosa))
                                    <span class="text-xs italic text-muted">Belum ada &mdash; klik Run AI</span>
                                @endif
                            </td>

                            {{-- Confidence --}}
                            <td class="px-4 py-3 text-center align-top" x-data="{ expanded: false }">
                                @if (empty($row->ai_model))
                                    {{-- Belum AI — tampilkan EMR % --}}
                                    @php
                                        $emrPct = $payload['emr_percent'] ?? 0;
                                        $emrSections = $payload['emr_sections'] ?? [];
                                        $emrBarColor = $emrPct >= 80 ? 'bg-emerald-500' : ($emrPct >= 50 ? 'bg-amber-400' : 'bg-rose-400');
                                        $emrTextColor = $emrPct >= 80 ? 'text-emerald-700 dark:text-emerald-400' : ($emrPct >= 50 ? 'text-amber-700 dark:text-amber-400' : 'text-rose-700 dark:text-rose-400');

                                        $sectionLabels = match ($row->ref_type) {
                                            'RI' => ['s' => 'S — Pengkajian', 'o' => 'O — TTV', 'a' => 'A — Diagnosa', 'p' => 'P — Rencana', 'n' => 'N — Penilaian', 'c' => 'C — CPPT', 'k' => 'K — Askep'],
                                            'UGD' => ['s' => 'S — Anamnesa', 'o' => 'O — Pemeriksaan', 'a' => 'A — Diagnosa', 'p' => 'P — Perencanaan', 'n' => 'N — Penilaian', 't' => 'T — Triase', 'k' => 'K — SNOMED'],
                                            default => ['s' => 'S — Anamnesa', 'o' => 'O — Pemeriksaan', 'a' => 'A — Diagnosa', 'p' => 'P — Perencanaan', 'n' => 'N — Penilaian', 'k' => 'K — SNOMED'],
                                        };
                                    @endphp
                                    <div class="text-xs font-semibold {{ $emrTextColor }}">EMR {{ $emrPct }}%</div>
                                    <div class="mt-1 mx-auto w-16 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                        <div class="h-full rounded-full {{ $emrBarColor }}" style="width: {{ $emrPct }}%"></div>
                                    </div>
                                    @if (!empty($emrSections))
                                        <div class="mt-1">
                                            <div x-show="expanded" x-cloak class="text-left space-y-0.5">
                                                @foreach ($sectionLabels as $key => $label)
                                                    @if (isset($emrSections[$key]))
                                                        @php $secPct = $emrSections[$key]; @endphp
                                                        <div class="flex items-center gap-1 text-[10px] {{ $secPct >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($secPct > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-500 dark:text-rose-400') }}">
                                                            <span>{{ $secPct >= 80 ? '✓' : ($secPct > 0 ? '◐' : '✗') }}</span>
                                                            <span>{{ $label }}</span>
                                                            <span class="ml-auto font-mono">{{ $secPct }}%</span>
                                                        </div>
                                                    @endif
                                                @endforeach
                                                @if (!empty($payload['has_bridging']))
                                                    <div class="flex items-center gap-1 text-[10px] text-violet-600 dark:text-violet-400">
                                                        <span>✓</span><span>Bridging iDRG</span>
                                                    </div>
                                                @endif
                                            </div>
                                            <button type="button" x-on:click="expanded = !expanded"
                                                class="mt-0.5 text-[10px] font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                <span x-show="!expanded">detail ▾</span>
                                                <span x-show="expanded" x-cloak>tutup ▴</span>
                                            </button>
                                        </div>
                                    @endif
                                @else
                                    {{-- Sudah diproses AI --}}
                                    <span class="{{ $conf['bg'] }} font-semibold">
                                        {{ $conf['icon'] }} {{ $row->ai_confidence ?? 0 }}%
                                    </span>
                                    @if ($row->ai_notes)
                                        <div class="mt-1 text-left">
                                            <div class="text-[10px] text-muted line-clamp-2">{{ \Illuminate\Support\Str::limit($row->ai_notes, 60) }}</div>
                                            @if (mb_strlen($row->ai_notes) > 60)
                                                <div x-show="expanded" x-cloak class="mt-0.5 text-[10px] text-body dark:text-gray-300">
                                                    {{ $row->ai_notes }}
                                                </div>
                                            @endif
                                            @if (!empty($diagnosa))
                                                <div x-show="expanded" x-cloak class="mt-1 space-y-0.5">
                                                    @foreach ($diagnosa as $d)
                                                        @if (!empty($d['reason']))
                                                            <div class="flex items-start gap-1 text-[10px]">
                                                                <span class="font-mono font-semibold text-blue-700 dark:text-blue-300 shrink-0">{{ $d['code'] ?? '' }}</span>
                                                                <span class="text-muted">{{ $d['reason'] }}</span>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                            <button type="button" x-on:click="expanded = !expanded"
                                                class="mt-0.5 text-[10px] font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                <span x-show="!expanded">alasan ▾</span>
                                                <span x-show="expanded" x-cloak>tutup ▴</span>
                                            </button>
                                        </div>
                                    @endif
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

                            {{-- Reviewer --}}
                            <td class="px-4 py-3 text-center align-top text-xs text-muted">
                                {{ $row->reviewer ?? '-' }}
                            </td>

                            {{-- Tgl Kunjungan --}}
                            <td class="px-4 py-3 text-center align-top whitespace-nowrap">
                                <div class="text-xs font-semibold text-ink dark:text-white">{{ $row->ref_date_display ?? ($payload['tgl_kunjungan'] ?? '-') }}</div>
                                @if ($row->reviewed_display)
                                    <div class="text-[10px] text-muted">Review: {{ $row->reviewed_display }}</div>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="px-4 py-3 text-center align-top">
                                @if (in_array($row->status, ['pending', 'failed']))
                                    <x-primary-button wire:click="openReview({{ $row->approval_id }})"
                                        class="!px-3 !py-1.5 !text-xs !min-w-0">
                                        Review
                                    </x-primary-button>
                                @elseif ($row->status === 'executed')
                                    <x-ghost-button wire:click="openReview({{ $row->approval_id }})"
                                        class="!px-3 !py-1.5 !text-xs !min-w-0">
                                        Detail
                                    </x-ghost-button>
                                @else
                                    <x-ghost-button wire:click="openReview({{ $row->approval_id }})"
                                        class="!px-3 !py-1.5 !text-xs !min-w-0">
                                        Lihat
                                    </x-ghost-button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-16">
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
    <x-modal name="casemix-review-{{ $queueType }}" size="full" height="full" focusable>
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
                    <x-icon-button color="gray" type="button" x-on:click="$dispatch('close-modal', { name: 'casemix-review-{{ $queueType }}' })" class="shrink-0">
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

                {{-- AI Notes / EMR Completeness --}}
                @php
                    $isAiProcessed = !empty($reviewData['ai_model'] ?? null);
                    $reviewPayload = json_decode($reviewData['ai_payload'] ?? '{}', true) ?: [];
                    $reviewEmrPct = $reviewPayload['emr_percent'] ?? 0;
                    $reviewEmrSections = $reviewPayload['emr_sections'] ?? [];
                @endphp
                @if ($isAiProcessed || !empty($reviewEmrSections))
                    <div class="p-4 rounded-xl {{ $isAiProcessed ? 'bg-violet-50 dark:bg-violet-900/20 border-violet-200 dark:border-violet-800' : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700' }} border">
                        @if ($isAiProcessed)
                            <div class="flex items-center justify-between mb-1">
                                <div class="text-xs font-semibold text-violet-800 dark:text-violet-300">AI Coding Suggest</div>
                                <span class="text-xs font-bold {{ ($reviewData['ai_confidence'] ?? 0) >= 80 ? 'text-emerald-600' : (($reviewData['ai_confidence'] ?? 0) >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                                    {{ $reviewData['ai_confidence'] ?? 0 }}%
                                </span>
                            </div>
                            @if (!empty($reviewData['ai_notes']))
                                <div class="text-sm text-violet-900 dark:text-violet-200">{{ $reviewData['ai_notes'] }}</div>
                            @endif
                            @if (!empty($editedDiagnosa))
                                <div class="mt-2 pt-2 border-t border-violet-200 dark:border-violet-700 space-y-1">
                                    <div class="text-[10px] font-semibold text-muted uppercase tracking-wider">Alasan per kode</div>
                                    @foreach ($editedDiagnosa as $d)
                                        @if (!empty($d['reason']))
                                            <div class="flex items-start gap-2 text-xs">
                                                <span class="font-mono font-semibold text-blue-700 dark:text-blue-300 shrink-0 min-w-[50px]">{{ $d['code'] ?? '' }}</span>
                                                <span class="text-body dark:text-gray-300">{{ $d['reason'] }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                    @foreach ($editedProsedur as $p)
                                        @if (!empty($p['reason']))
                                            <div class="flex items-start gap-2 text-xs">
                                                <span class="font-mono font-semibold text-purple-700 dark:text-purple-300 shrink-0 min-w-[50px]">{{ $p['code'] ?? '' }}</span>
                                                <span class="text-body dark:text-gray-300">{{ $p['reason'] }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                            @php
                                $rejDx = $reviewPayload['rejected_diagnosa'] ?? [];
                                $rejProc = $reviewPayload['rejected_prosedur'] ?? [];
                            @endphp
                            @if (!empty($rejDx) || !empty($rejProc))
                                <div class="mt-3 p-3 rounded-lg bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800">
                                    <div class="text-xs font-semibold text-rose-800 dark:text-rose-300 mb-1.5">Kode tidak ada di master RS</div>
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
                                    <div class="mt-1.5 text-[10px] text-rose-500">Kode di atas direkomendasikan AI tapi tidak ada di master diagnosa/prosedur RS. Tambahkan ke master jika diperlukan.</div>
                                </div>
                            @endif
                        @else
                            {{-- Belum AI — tampilkan EMR % --}}
                            @php
                                $emrBarColor = $reviewEmrPct >= 80 ? 'bg-emerald-500' : ($reviewEmrPct >= 50 ? 'bg-amber-400' : 'bg-rose-400');
                                $refType = strtoupper($reviewData['ref_type'] ?? 'RJ');
                                $sectionLabels = match ($refType) {
                                    'RI' => ['s' => 'S — Pengkajian', 'o' => 'O — TTV', 'a' => 'A — Diagnosa', 'p' => 'P — Rencana', 'n' => 'N — Penilaian', 'c' => 'C — CPPT', 'k' => 'K — Askep'],
                                    'UGD' => ['s' => 'S — Anamnesa', 'o' => 'O — Pemeriksaan', 'a' => 'A — Diagnosa', 'p' => 'P — Perencanaan', 'n' => 'N — Penilaian', 't' => 'T — Triase', 'k' => 'K — SNOMED'],
                                    default => ['s' => 'S — Anamnesa', 'o' => 'O — Pemeriksaan', 'a' => 'A — Diagnosa', 'p' => 'P — Perencanaan', 'n' => 'N — Penilaian', 'k' => 'K — SNOMED'],
                                };
                            @endphp
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-xs font-semibold text-muted">Kelengkapan EMR</div>
                                <span class="text-xs font-bold {{ $reviewEmrPct >= 80 ? 'text-emerald-600' : ($reviewEmrPct >= 50 ? 'text-yellow-600' : 'text-red-600') }}">{{ $reviewEmrPct }}%</span>
                            </div>
                            <div class="w-full h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden mb-2">
                                <div class="h-full rounded-full {{ $emrBarColor }}" style="width: {{ $reviewEmrPct }}%"></div>
                            </div>
                            <div class="space-y-1">
                                @foreach ($sectionLabels as $key => $label)
                                    @if (isset($reviewEmrSections[$key]))
                                        @php $secPct = $reviewEmrSections[$key]; @endphp
                                        <div class="flex items-center justify-between text-xs {{ $secPct >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($secPct > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-500 dark:text-rose-400') }}">
                                            <span>{{ $secPct >= 80 ? '✓' : ($secPct > 0 ? '◐' : '✗') }} {{ $label }}</span>
                                            <span class="font-mono">{{ $secPct }}%</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            @if (!empty($reviewPayload['has_bridging']))
                                <div class="mt-1 text-xs text-violet-600 dark:text-violet-400">✓ Bridging iDRG tersedia</div>
                            @endif
                        @endif
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
                                @if (isset($d['validated']))
                                    <span class="w-2 h-2 rounded-full shrink-0 {{ $d['validated'] ? 'bg-emerald-500' : 'bg-amber-500' }}"
                                        title="{{ $d['validated'] ? 'Kode valid di database' : 'Kode tidak ditemukan di database' }}"></span>
                                @endif
                                <span class="flex-1 text-sm text-body dark:text-gray-300">{{ $d['desc'] ?? '' }}</span>
                                @if (isset($d['confidence']) && $d['confidence'] > 0)
                                    <span class="text-xs text-muted">{{ $d['confidence'] }}%</span>
                                @endif
                                @if (in_array($reviewData['status'] ?? '', ['pending', 'failed']))
                                    <x-icon-button color="red" wire:click="removeDiagnosa({{ $idx }})" class="!p-1">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </x-icon-button>
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
                                    <x-icon-button color="red" wire:click="removeProsedur({{ $idx }})" class="!p-1">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </x-icon-button>
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
                {{-- Kiri: AI Suggest + Auto Upload Berkas --}}
                <div class="flex items-center gap-2">
                    @if (in_array($reviewData['status'] ?? '', ['pending', 'failed']))
                        <x-info-button wire:click="aiSuggestSingle({{ $reviewId }})" wire:loading.attr="disabled"
                            wire:target="aiSuggestSingle">
                            <span wire:loading.remove wire:target="aiSuggestSingle" class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z" />
                                </svg>
                                AI Suggest
                            </span>
                            <span wire:loading wire:target="aiSuggestSingle" class="flex items-center gap-1.5">
                                <x-loading /> Analyzing...
                            </span>
                        </x-info-button>
                        <x-info-button wire:click="autoUploadBerkas" wire:loading.attr="disabled"
                            wire:target="autoUploadBerkas">
                            <span wire:loading.remove wire:target="autoUploadBerkas" class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                Auto Upload Berkas
                            </span>
                            <span wire:loading wire:target="autoUploadBerkas" class="flex items-center gap-1.5">
                                <x-loading /> Generating...
                            </span>
                        </x-info-button>
                    @endif
                </div>

                {{-- Kanan: Reject / Approve --}}
                <div class="flex items-center gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'casemix-review-{{ $queueType }}' })">
                        Tutup
                    </x-secondary-button>

                    @if (in_array($reviewData['status'] ?? '', ['pending', 'failed']))
                        <x-danger-button wire:click="reject" wire:loading.attr="disabled">
                            Reject
                        </x-danger-button>
                        <x-primary-button wire:click="approve" wire:loading.attr="disabled"
                            wire:target="approve">
                            <span wire:loading.remove wire:target="approve">Approve</span>
                            <span wire:loading wire:target="approve">Menyimpan...</span>
                        </x-primary-button>
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
