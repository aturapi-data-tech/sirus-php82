<?php

namespace App\Http\Traits\Txn\Rj;

use Illuminate\Support\Facades\DB;
use Throwable;

trait EmrRJTrait
{

    /**
     * Find EMR RJ data with cache-first logic
     * - If datadaftarpolirj_json exists & valid: use it directly
     * - If null/invalid: fallback to database query (once)
     * - Validate rj_no: if not found or mismatched, return error
     */
    protected function findDataRJ(string $rjNo): array
    {
        try {
            // 1. Check if JSON exists (cache-first pattern)
            $row = DB::table('rstxn_rjhdrs')
                ->select('datadaftarpolirj_json')
                ->where('rj_no', $rjNo)
                ->first();

            if (!$row) {
                return $this->buildDefaultRJData($rjNo, "Data Rawat Jalan tidak ditemukan untuk RJ No: {$rjNo}");
            }

            $json = $row->datadaftarpolirj_json ?? null;

            // 2. If JSON exists & valid, return immediately
            if ($json && $this->isValidRJJson($json, $rjNo)) {
                return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            }

            // 3. If JSON doesn't exist/invalid, build from database
            return $this->buildRJDataFromDatabase($rjNo);
        } catch (Throwable $e) {
            return $this->buildDefaultRJData($rjNo, $e->getMessage());
        }
    }

    /**
     * Build RJ data from database (only called if JSON is missing)
     */
    private function buildRJDataFromDatabase(string $rjNo): array
    {
        // Start with default template
        $dataDaftarRJ = $this->getDefaultRJTemplate();

        // Get RJ header data
        $rjHeader = DB::table('rstxn_rjhdrs as h')
            ->select(
                DB::raw("to_char(h.rj_date, 'dd/mm/yyyy hh24:mi:ss') as rj_date"),
                DB::raw("to_char(h.rj_date, 'yyyymmddhh24miss') as rj_date1"),
                'h.rj_no',
                'h.reg_no',
                'h.poli_id',
                'h.dr_id',
                'h.klaim_id',
                'h.shift',
                'h.vno_sep',
                'h.no_antrian',
                'h.nobooking',
                'h.rj_status',
                'h.txn_status',
                'h.erm_status',
                'h.kd_dr_bpjs',
                'h.kd_poli_bpjs',
                'h.push_antrian_bpjs_status',
                'h.push_antrian_bpjs_json'
            )
            ->where('h.rj_no', $rjNo)
            ->first();

        if (!$rjHeader) {
            return $this->buildDefaultRJData($rjNo, "Data header RJ tidak ditemukan");
        }

        // Get poli & dokter data
        $poliDokter = DB::table('rstxn_rjhdrs as h')
            ->select(
                'po.poli_desc',
                'd.dr_name'
            )
            ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->where('h.rj_no', $rjNo)
            ->first();

        // Populate basic data from RJ header
        $dataDaftarRJ['rjNo'] = $rjHeader->rj_no;
        $dataDaftarRJ['regNo'] = $rjHeader->reg_no;
        $dataDaftarRJ['poliId'] = $rjHeader->poli_id ?? '';
        $dataDaftarRJ['drId'] = $rjHeader->dr_id ?? '';
        $dataDaftarRJ['klaimId'] = $rjHeader->klaim_id ?? '';
        $dataDaftarRJ['shift'] = $rjHeader->shift ?? '';
        $dataDaftarRJ['vno_sep'] = $rjHeader->vno_sep ?? '';
        $dataDaftarRJ['noAntrian'] = $rjHeader->no_antrian ?? '';
        $dataDaftarRJ['noBooking'] = $rjHeader->nobooking ?? '';
        $dataDaftarRJ['rjDate'] = $rjHeader->rj_date ?? '';
        $dataDaftarRJ['rjDate1'] = $rjHeader->rj_date1 ?? '';
        $dataDaftarRJ['rjStatus'] = $rjHeader->rj_status ?? 'A';
        $dataDaftarRJ['txnStatus'] = $rjHeader->txn_status ?? '';
        $dataDaftarRJ['ermStatus'] = $rjHeader->erm_status ?? '';
        $dataDaftarRJ['kd_dr_bpjs'] = $rjHeader->kd_dr_bpjs ?? '';
        $dataDaftarRJ['kd_poli_bpjs'] = $rjHeader->kd_poli_bpjs ?? '';


        // Populate poli & dokter descriptions
        $dataDaftarRJ['poliDesc'] = $poliDokter->poli_desc ?? '';
        $dataDaftarRJ['drDesc'] = $poliDokter->dr_name ?? '';

        // Set task IDs
        $dataDaftarRJ['taskIdPelayanan']['taskId3'] = $rjHeader->rj_date ?? '';

        // Set SEP data
        $dataDaftarRJ['sep']['noSep'] = $rjHeader->vno_sep ?? '';

        // Auto-save to JSON for next requests
        $this->autoSaveRJToJson($rjNo, $dataDaftarRJ);

        return $dataDaftarRJ;
    }

    /**
     * Validate RJ JSON structure and rj_no
     */
    private function isValidRJJson(?string $json, string $expectedRjNo): bool
    {
        if (!$json || trim($json) === '') {
            return false;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            // Check if it's an array and has 'rjNo' key
            if (!is_array($decoded) || !isset($decoded['rjNo'])) {
                return false;
            }

            // Validate rj_no matches
            return $decoded['rjNo'] === $expectedRjNo;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Auto-save to JSON (optimization for next requests)
     */
    private function autoSaveRJToJson(string $rjNo, array $data): void
    {
        try {
            DB::table('rstxn_rjhdrs')
                ->where('rj_no', $rjNo)
                ->update([
                    'datadaftarpolirj_json' => json_encode(
                        $data,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                    'updated_at' => now(),
                ]);
        } catch (Throwable $e) {
            // Silent fail - auto-save is not critical
        }
    }

    /**
     * Build default RJ data with error message
     */
    private function buildDefaultRJData(string $rjNo, string $errorMessage = ''): array
    {
        $dataDaftarRJ = $this->getDefaultRJTemplate();
        $dataDaftarRJ['rjNo'] = $rjNo;
        $dataDaftarRJ['regName'] = 'DATA TIDAK DITEMUKAN';

        return $dataDaftarRJ;
    }

    /**
     * Get default RJ template
     */
    private function getDefaultRJTemplate(): array
    {
        return [
            "regNo" => "",
            "regName" => "",

            "drId" => "",
            "drDesc" => "",
            "poliId" => "",
            "poliDesc" => "",
            "klaimId" => "UM",
            'kunjunganId' => '1',

            "rjDate" => "",
            "rjNo" => "",
            "shift" => "",
            "noAntrian" => "",
            "noBooking" => "",
            "slCodeFrom" => "02",
            "passStatus" => "",
            "rjStatus" => "A",
            "txnStatus" => "A",
            "ermStatus" => "A",
            "cekLab" => "0",
            "kunjunganInternalStatus" => "0",
            "noReferensi" => "",
            "postInap" => false,
            "internal12" => "1",
            "internal12Desc" => "Faskes Tingkat 1",
            "internal12Options" => [
                ["internal12" => "1", "internal12Desc" => "Faskes Tingkat 1"],
                ["internal12" => "2", "internal12Desc" => "Faskes Tingkat 2 RS"]
            ],
            "kontrol12" => "1",
            "kontrol12Desc" => "Faskes Tingkat 1",
            "kontrol12Options" => [
                ["kontrol12" => "1", "kontrol12Desc" => "Faskes Tingkat 1"],
                ["kontrol12" => "2", "kontrol12Desc" => "Faskes Tingkat 2 RS"],
            ],
            "taskIdPelayanan" => [
                "taskId1" => "",
                "taskId2" => "",
                "taskId3" => "",
                "taskId4" => "",
                "taskId5" => "",
                "taskId6" => "",
                "taskId7" => "",
                "taskId99" => "",
            ],
            'sep' => [
                "noSep" => "",
                "reqSep" => [],
                "resSep" => [],
            ],
        ];
    }

    /**
     * Check RJ status
     */
    protected function checkRJStatus(string $rjNo): bool
    {
        $rjStatus = DB::table('rstxn_rjhdrs')
            ->select('rj_status')
            ->where('rj_no', $rjNo)
            ->first();

        if (!$rjStatus || empty($rjStatus->rj_status)) {
            return false;
        }

        return $rjStatus->rj_status !== 'A';
    }

    /**
     * Update JSON RJ with validation
     */
    public static function updateJsonRJ(string $rjNo, array $payload): void
    {
        DB::transaction(function () use ($rjNo, $payload) {
            // Validate payload has correct rjNo
            if (!isset($payload['rjNo']) || $payload['rjNo'] !== $rjNo) {
                throw new \RuntimeException("rjNo dalam payload tidak sesuai dengan parameter");
            }

            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );

            DB::table('rstxn_rjhdrs')
                ->where('rj_no', $rjNo)
                ->update([
                    'datadaftarpolirj_json' => $json
                ]);
        }, 3);
    }
}
