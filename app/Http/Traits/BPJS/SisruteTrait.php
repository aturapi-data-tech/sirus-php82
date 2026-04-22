<?php

namespace App\Http\Traits\BPJS;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use Exception;

/**
 * Sisrute Bridging — Rujukan Berbasis Kompetensi (khusus RJ rawat jalan).
 *
 * Pola kode mengikuti VclaimTrait supaya maintenance enak: tiap endpoint
 * dipecah per method dengan blok bernomor (messages, attributes, data,
 * rules, validator), try/catch Http, dan parser response tunggal.
 *
 * Referensi resmi:
 *   - PDF BPJS 2026-03-05 "Integrasi Sistem Rujukan BPJS Kesehatan dengan Sisrute"
 *   - Postman Docs API Integrasi Satu Sehat Rujukan — https://bit.ly/IntegrasiSatuSehatRujukan
 *
 * Konteks arsitektur:
 *   - Base URL berbeda per level fasilitas:
 *     • FKTP (Puskesmas/Klinik)  → https://apijkn-dev.bpjs-kesehatan.go.id/pcare-sisrute-rest
 *     • FKRTL (Rumah Sakit)      → https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-sisrute-rest
 *     Karena aplikasi ini untuk RS (FKRTL), set SISRUTE_URL ke yang VClaim.
 *   - RJ  → lewat gateway VClaim-Sisrute (BPJS orkestrasi ke Sisrute + Satu Sehat).
 *     Header auth sama dengan VClaim (Cons-ID + Signature HMAC-SHA256).
 *   - UGD & Inap → langsung ke Satu Sehat FHIR (ServiceRequest) — pakai trait
 *     terpisah, TIDAK pakai SisruteTrait ini.
 *
 * Endpoint path (dari PDF resmi BPJS 2026-03-05):
 *   1. GET  {base}/Rujukan/GetKriteriaRujukan  (param: kodeDiagnosa, kodeFaskesSatuSehat)
 *   2. POST {base}/Rujukan/GetFaskesRujukan    (body: diagnosa + kompetensi + kriteria + wilayah)
 *   3. POST {base}/Rujukan/Insert              (body: t_rujukan + satuSehatRujukan)
 *
 * Env yang dipakai:
 *   SISRUTE_URL          -> Base URL VClaim-Sisrute (mis.
 *                           https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-sisrute-rest).
 *                           Fallback: VCLAIM_URL (tidak disarankan, path beda).
 *   SISRUTE_CONS_ID      -> Consumer ID (fallback VCLAIM_CONS_ID)
 *   SISRUTE_SECRET_KEY   -> Secret key HMAC-SHA256 (fallback VCLAIM_SECRET_KEY)
 *   SISRUTE_USER_KEY     -> User key (fallback VCLAIM_USER_KEY)
 *   SISRUTE_AUTH_USER    -> Basic auth username (opsional, untuk X-Authorization)
 *   SISRUTE_AUTH_PASS    -> Basic auth password (opsional)
 */
trait SisruteTrait
{
    // ==============================================================
    // Response wrappers (pola sama VclaimTrait::sendResponse/sendError)
    // ==============================================================

    public static function sisruteSendResponse($message, $data, $code = 200, $url = null, $requestTransferTime = null)
    {
        $response = [
            'response' => $data,
            'metadata' => [
                'message' => $message,
                'code'    => $code,
            ],
        ];

        // Log silent — jangan breaks response kalau log gagal.
        try {
            DB::table('web_log_status')->insert([
                'code'                => $code,
                'date_ref'            => Carbon::now(),
                'response'            => json_encode($response, true),
                'http_req'            => $url,
                'requestTransferTime' => $requestTransferTime,
            ]);
        } catch (\Throwable) {
            // silent
        }

        return response()->json($response, $code);
    }

    public static function sisruteSendError($error, $errorMessages = [], $code = 404, $url = null, $requestTransferTime = null)
    {
        $response = [
            'metadata' => [
                'message' => $error,
                'code'    => $code,
            ],
        ];
        if (!empty($errorMessages)) {
            $response['response'] = $errorMessages;
        }

        try {
            DB::table('web_log_status')->insert([
                'code'                => $code,
                'date_ref'            => Carbon::now(),
                'response'            => json_encode($response, true),
                'http_req'            => $url,
                'requestTransferTime' => $requestTransferTime,
            ]);
        } catch (\Throwable) {
            // silent
        }

        return response()->json($response, $code);
    }

    // ==============================================================
    // Signature (pola sama VclaimTrait::signature, plus X-Authorization opsional)
    // Sisrute tidak pakai decrypt_key — response plain JSON.
    // ==============================================================

    public static function sisruteSignature()
    {
        $cons_id   = env('SISRUTE_CONS_ID', env('VCLAIM_CONS_ID'));
        $secretKey = env('SISRUTE_SECRET_KEY', env('VCLAIM_SECRET_KEY'));
        $userkey   = env('SISRUTE_USER_KEY', env('VCLAIM_USER_KEY'));

        date_default_timezone_set('UTC');
        $tStamp = strval(time() - strtotime('1970-01-01 00:00:00'));
        $signature = hash_hmac('sha256', $cons_id . "&" . $tStamp, $secretKey, true);
        $encodedSignature = base64_encode($signature);

        $headers = [
            'user_key'    => $userkey,
            'x-cons-id'   => $cons_id,
            'x-timestamp' => $tStamp,
            'x-signature' => $encodedSignature,
        ];

        // X-Authorization Basic (opsional, sesuai docs Sisrute Delete/Rujukan)
        $authUser = env('SISRUTE_AUTH_USER');
        $authPass = env('SISRUTE_AUTH_PASS', '');
        if ($authUser !== null && $authUser !== '') {
            $headers['x-authorization'] = 'Basic ' . base64_encode("{$authUser}:{$authPass}");
        }

        return $headers;
    }

    // ==============================================================
    // Response parser (pola sama VclaimTrait::response_no_decrypt)
    // Sisrute balas "metaData" (D besar) sama kaya VClaim.
    // ==============================================================

    public static function sisruteResponse($response, $url, $requestTransferTime)
    {
        if ($response->failed()) {
            return self::sisruteSendError(
                $response->reason(),
                $response->json('response'),
                $response->status(),
                $url,
                $requestTransferTime,
            );
        }

        return self::sisruteSendResponse(
            $response->json('metaData.message'),
            $response->json('response'),
            $response->json('metaData.code') ?? 200,
            $url,
            $requestTransferTime,
        );
    }

    // ==============================================================
    // Helper: base URL dengan trailing slash
    // ==============================================================

    private static function sisruteBaseUrl(): string
    {
        return rtrim(env('SISRUTE_URL', env('VCLAIM_URL')), '/') . '/';
    }

    // ==============================================================
    // 1. GET /Rujukan/GetKriteriaRujukan
    //    Ambil daftar pertanyaan kriteria rujukan + jejaring wilayah
    //    berdasarkan diagnosa + kode faskes Satu Sehat RS sendiri.
    // ==============================================================

    public static function sisrute_get_kriteria_rujukan($kodeDiagnosa, $kodeFaskesSatuSehat)
    {
        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
        ];

        // 2. Attributes (nama field user-friendly)
        $attributes = [
            'kodeDiagnosa'        => 'Kode Diagnosa',
            'kodeFaskesSatuSehat' => 'Kode Faskes Satu Sehat',
        ];

        // 3. Data yang akan divalidasi
        $r = [
            'kodeDiagnosa'        => $kodeDiagnosa,
            'kodeFaskesSatuSehat' => $kodeFaskesSatuSehat,
        ];

        // 4. Rules validasi
        $rules = [
            'kodeDiagnosa'        => 'required',
            'kodeFaskesSatuSehat' => 'required',
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return self::sisruteSendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }

        // handler when time out and off line mode
        try {
            $url = self::sisruteBaseUrl() . "Rujukan/GetKriteriaRujukan";
            $signature = self::sisruteSignature();

            $response = Http::timeout(15)
                ->withHeaders($signature)
                ->get($url, $r);

            return self::sisruteResponse($response, $url, $response->transferStats?->getTransferTime());
        } catch (Exception $e) {
            return self::sisruteSendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 2. POST /Rujukan/GetFaskesRujukan
    //    Cari daftar faskes tujuan rujukan berdasarkan diagnosa,
    //    kompetensi (kodeSpesialis), kriteria rujukan, & jejaring wilayah.
    //    Output dipakai sebagai kdppk tujuan di POST Insert.
    // ==============================================================

    public static function sisrute_get_faskes_rujukan(array $payload)
    {
        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
        ];

        // 2. Attributes (nama field user-friendly)
        $attributes = [
            'kodeFaskesSatuSehat'              => 'Kode Faskes Satu Sehat',
            'kodeDiagnosa'                     => 'Kode Diagnosa',
            'kodeSpesialis'                    => 'Kode Spesialis',
            'tglRencanaKunjungan'              => 'Tanggal Rencana Kunjungan',
            'kriteriaRujukan.item'             => 'Kriteria Rujukan',
            'codeJejaringWilayah.kodePropinsi' => 'Kode Propinsi',
            'codeJejaringWilayah.namaPropinsi' => 'Nama Propinsi',
            'encounter.reference'              => 'Encounter Reference',
        ];

        // 3. Data = payload apa adanya
        $r = $payload;

        // 4. Rules validasi
        $rules = [
            'kodeFaskesSatuSehat'              => 'required|string',
            'kodeDiagnosa'                     => 'required|string',
            'kodeSpesialis'                    => 'required|string',
            'tglRencanaKunjungan'              => 'required|string',
            'kriteriaRujukan.item'             => 'required|array|min:1',
            'codeJejaringWilayah.kodePropinsi' => 'required|string',
            'codeJejaringWilayah.namaPropinsi' => 'required|string',
            'encounter.reference'              => 'required|string',
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return self::sisruteSendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }

        try {
            $url = self::sisruteBaseUrl() . "Rujukan/GetFaskesRujukan";
            $signature = self::sisruteSignature();
            $signature['Content-Type'] = 'application/json';

            $response = Http::timeout(15)
                ->withHeaders($signature)
                ->post($url, $r);

            return self::sisruteResponse($response, $url, $response->transferStats?->getTransferTime());
        } catch (Exception $e) {
            return self::sisruteSendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 3. POST /Rujukan/Insert
    //    Kirim rujukan lengkap (VClaim rujukan + data Satu Sehat).
    //    Body auto-wrap dengan {"request": {"t_rujukan": ...}}.
    //    jnsPelayanan: "1"=Rawat Inap, "2"=Rawat Jalan.
    //    tipeRujukan:  "0"=Penuh, "1"=Partial, "2"=Rujuk Balik.
    // ==============================================================

    public static function sisrute_post_kunjungan(array $payload)
    {
        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
            'in'       => ':attribute harus bernilai :values.',
        ];

        // 2. Attributes (nama field user-friendly)
        $attributes = [
            'noSep'               => 'No. SEP',
            'tglRujukan'          => 'Tanggal Rujukan',
            'tglRencanaKunjungan' => 'Tanggal Rencana Kunjungan',
            'ppkDirujuk'          => 'PPK Tujuan Rujukan',
            'jnsPelayanan'        => 'Jenis Pelayanan',
            'catatan'             => 'Catatan',
            'diagRujukan'         => 'Diagnosa Rujukan',
            'tipeRujukan'         => 'Tipe Rujukan',
            'poliRujukan'         => 'Poli Rujukan',
            'user'                => 'User',

            'satuSehatRujukan.kodeFaskesSatuSehat'         => 'Kode Faskes Satu Sehat',
            'satuSehatRujukan.idPasienSatuSehat'           => 'ID Pasien Satu Sehat',
            'satuSehatRujukan.kdppkSatuSehatTujuanRujukan' => 'Kode PPK Tujuan Rujukan',
            'satuSehatRujukan.kdDokterSatuSehat'           => 'Kode Dokter Satu Sehat',
            'satuSehatRujukan.encounter.reference'         => 'Encounter Reference',
            'satuSehatRujukan.kriteriaRujukan.item'        => 'Kriteria Rujukan',
        ];

        // 3. Data = payload apa adanya
        $r = $payload;

        // 4. Rules validasi
        $rules = [
            'noSep'               => 'required|string',
            'tglRujukan'          => 'required|string',
            'tglRencanaKunjungan' => 'required|string',
            'ppkDirujuk'          => 'required|string',
            'jnsPelayanan'        => 'required|string|in:1,2',
            'catatan'             => 'required|string',
            'diagRujukan'         => 'required|string',
            'tipeRujukan'         => 'required|string|in:0,1,2',
            'poliRujukan'         => 'required|string',
            'user'                => 'required|string',

            'satuSehatRujukan.kodeFaskesSatuSehat'         => 'required|string',
            'satuSehatRujukan.idPasienSatuSehat'           => 'required|string',
            'satuSehatRujukan.kdppkSatuSehatTujuanRujukan' => 'required|string',
            'satuSehatRujukan.kdDokterSatuSehat'           => 'required|string',
            'satuSehatRujukan.encounter.reference'         => 'required|string',
            'satuSehatRujukan.kriteriaRujukan.item'        => 'required|array|min:1',
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return self::sisruteSendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }

        try {
            $url = self::sisruteBaseUrl() . "Rujukan/Insert";
            $signature = self::sisruteSignature();
            $signature['Content-Type'] = 'application/json';

            // Wrap dengan "request.t_rujukan" sesuai docs endpoint.
            $data = [
                'request' => [
                    't_rujukan' => $r,
                ],
            ];

            $response = Http::timeout(15)
                ->withHeaders($signature)
                ->post($url, $data);

            return self::sisruteResponse($response, $url, $response->transferStats?->getTransferTime());
        } catch (Exception $e) {
            return self::sisruteSendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // STUB — belum diimplementasikan (menunggu detail docs Delete)
    // ==============================================================

    public static function sisrute_delete_rujukan(array $payload = [])
    {
        return self::sisruteSendError(
            'Sisrute: delete rujukan belum diimplementasikan.',
            [],
            501,
            null,
            null,
        );
    }
}
