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
    protected function findDataRJ(int $rjNo): array
    {
        // 1. Ambil JSON dari DB
        $row = DB::table('rsview_rjkasir')
            ->select([
                'reg_no',
                'reg_name',
                'rj_no',
                'rj_status',
                'dr_id',
                'dr_name',
                'poli_id',
                'poli_desc',
                DB::raw("to_char(rj_date, 'dd/mm/yyyy hh24:mi:ss') as rj_date"),
                'shift',
                'klaim_id',
                'txn_status',
                'erm_status',
                'vno_sep',
                'no_antrian',
                'no_sep',
                'nobooking',
                'waktu_masuk_poli',
                'waktu_masuk_apt',
                'waktu_selesai_pelayanan',
                'kd_dr_bpjs',
                'kd_poli_bpjs',
                'datadaftarpolirj_json',
            ])
            ->where('rj_no', $rjNo)
            ->first();

        $json = $row->datadaftarpolirj_json ?? null;

        // 2. Jika JSON valid, langsung return
        if ($json && $this->isValidRJJson($json, $rjNo)) {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            // 1. Coba ambil data lengkap dari view

            // Mulai dengan template default
            $dataDaftarRJ = $payload;

            $this->populateFromDatabaseEmrRJ($dataDaftarRJ, $row);


            return $dataDaftarRJ;
        }
        $builtData = $this->getDefaultPasienTemplate();
        // 3. Jika JSON tidak ada/invalid, coba build dari DB
        if ($row) {
            $this->populateFromDatabaseEmrRJ($builtData, $row);
        }
        // 4. Jika build dari DB gagal (return default), kembalikan default
        return $builtData;
    }

    /**
     * Validate RJ JSON structure and rj_no
     */
    private function isValidRJJson(?string $json, string $expectedRjNo): bool
    {
        if (!$json || trim($json) == '') {
            return false;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            // Check if it's an array and has 'rjNo' key
            if (!is_array($decoded) || !isset($decoded['rjNo'])) {
                return false;
            }

            // Validate rj_no matches
            return $decoded['rjNo'] == $expectedRjNo;
        } catch (Throwable $e) {
            return false;
        }
    }


    /**
     * Build RJ data from database (only called if JSON is missing)
     */
    private function populateFromDatabaseEmrRJ(array &$dataPasien, object $row): void
    {
        // ============================================
        // POPULATE DATA DARI VIEW
        // ============================================

        // Data Pasien
        $dataDaftarRJ['regNo'] = $row->reg_no ?? '';
        $dataDaftarRJ['regName'] = $row->reg_name ?? '';

        // Data Dokter & Poli
        $dataDaftarRJ['drId'] = $row->dr_id ?? '';
        $dataDaftarRJ['drDesc'] = $row->dr_name ?? '';
        $dataDaftarRJ['poliId'] = $row->poli_id ?? '';
        $dataDaftarRJ['poliDesc'] = $row->poli_desc ?? '';

        // Kode BPJS
        $dataDaftarRJ['kddrbpjs'] = $row->kd_dr_bpjs ?? '';
        $dataDaftarRJ['kdpolibpjs'] = $row->kd_poli_bpjs ?? '';

        // Data Klaim
        $dataDaftarRJ['klaimId'] = $row->klaim_id ?? 'UM';
        $dataDaftarRJ['klaimStatus'] = $this->getKlaimStatus($row->klaim_id ?? 'UM');

        // Data Transaksi Dasar
        $dataDaftarRJ['rjNo'] = $row->rj_no ?? null;
        $dataDaftarRJ['rjDate'] = $row->rj_date ?? '';
        $dataDaftarRJ['shift'] = $row->shift ?? '';

        // Status
        $dataDaftarRJ['rjStatus'] = $row->rj_status ?? 'A';
        $dataDaftarRJ['txnStatus'] = $row->txn_status ?? 'A';
        $dataDaftarRJ['ermStatus'] = $row->erm_status ?? 'A';

        // Nomor-nomor penting
        $dataDaftarRJ['noAntrian'] = $row->no_antrian ?? '';
        $dataDaftarRJ['noBooking'] = $row->nobooking ?? '';

        // Data SEP
        $dataDaftarRJ['sep']['noSep'] = $row->vno_sep ?? $row->no_sep ?? '';

        // ============================================
        // TASK ID Pelayanan
        // ============================================
        // Task 3 = rj_date
        $dataDaftarRJ['taskIdPelayanan']['taskId3'] = $row->rj_date ?? $dataDaftarRJ['taskIdPelayanan']['taskId3'] ?? '';
    }

    /**
     * Get klaim status dari klaim_id
     */
    private function getKlaimStatus(string $klaimId): string
    {
        return DB::table('rsmst_klaimtypes')
            ->where('klaim_id', $klaimId)
            ->value('klaim_status') ?? 'UMUM';
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
            "klaimStatus" => "UMUM",
            'kunjunganId' => '1',

            "rjDate" => "",
            "rjNo" => "",
            "shift" => "",
            "noAntrian" => "",
            "noBooking" => "",
            "slCodeFrom" => "02",
            "passStatus" => "O",
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
                "tambahPendaftaran" => "",
                "taskId1" => "",
                "taskId1Status" => "",
                "taskId2" => "",
                "taskId2Status" => "",
                "taskId3" => "",
                "taskId3Status" => "",
                "taskId4" => "",
                "taskId4Status" => "",
                "taskId5" => "",
                "taskId5Status" => "",
                "taskId6" => "",
                "taskId6Status" => "",
                "taskId7" => "",
                "taskId7Status" => "",
                "taskId99" => "",
                "taskId99Status" => "",
            ],
            'sep' => [
                "noSep" => "",
                "reqSep" => [],
                "resSep" => [],
            ],
        ];
    }


    /**
     * Update JSON RJ with validation
     */
    public static function updateJsonRJ(int $rjNo, array $payload): void
    {
        DB::transaction(function () use ($rjNo, $payload) {
            if (!isset($payload['rjNo']) || $payload['rjNo'] != $rjNo) {
                throw new \RuntimeException("rjNo dalam payload tidak sesuai dengan parameter");
            }

            DB::table('rstxn_rjhdrs')
                ->where('rj_no', $rjNo)
                ->update([
                    'datadaftarpolirj_json' => json_encode(
                        $payload,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                    )
                ]);
        }, 3);
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

    protected function checkEmrRJStatus(string $rjNo): bool
    {
        $rjStatus = DB::table('rstxn_rjhdrs')
            ->select('erm_status')
            ->where('rj_no', $rjNo)
            ->first();

        if (!$rjStatus || empty($rjStatus->rj_status)) {
            return false;
        }

        return $rjStatus->rj_status !== 'A';
    }
}
