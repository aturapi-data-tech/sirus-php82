<?php

namespace App\Http\Traits\BPJS;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;


use Exception;

/**
 * SISRUTE — Rujukan Berbasis Kompetensi (SRBK) via gateway vclaim-sisrute-rest BPJS.
 *
 * HANYA untuk jalur RAWAT JALAN: BPJS yang meneruskan ke SATUSEHAT (kita tidak
 * mengirim bundle FHIR sendiri). RI/UGD memakai jalur FHIR langsung — trait terpisah.
 *
 * Aturan lengkap + katalog error: docs/rujukan-kompetensi.md dan skill rujukan-kompetensi.
 * - Cons ID SISRUTE terdaftar TERPISAH dari cons ID vclaim biasa (env SISRUTE_CONS_ID).
 * - Signature/header/decrypt = pola VClaim persis (tidak ada mekanisme baru).
 * - Outage upstream SATUSEHAT adalah kondisi normal: timeout ketat + jangan blokir EMR.
 * - Payload & response mentah otomatis terekam di web_log_status (sendResponse/sendError)
 *   — bukti wajib saat lapor Issue Tracker BPJS.
 */
trait SisruteTrait
{
    public static function sendResponse($message, $data, $code = 200, $url = null, $requestTransferTime = null, $payload = null)
    {
        $response = [
            'response' => $data,
            'metadata' => [
                'message' => $message,
                'code' => $code,
            ],
        ];

        // Insert webLogStatus
        DB::table('web_log_status')->insert([
            'code' =>  $code,
            'date_ref' => Carbon::now(),
            'response' => json_encode($response, true),
            'http_req' => $url,
            'http_payload' => $payload,
            'requestTransferTime' => $requestTransferTime
        ]);

        return response()->json($response, $code);
    }
    public static function sendError($error, $errorMessages = [], $code = 404, $url = null, $requestTransferTime = null, $payload = null)
    {
        $response = [
            'metadata' => [
                'message' => $error,
                'code' => $code,
            ],
        ];
        if (!empty($errorMessages)) {
            $response['response'] = $errorMessages;
        }
        // Insert webLogStatus
        DB::table('web_log_status')->insert([
            'code' =>  $code,
            'date_ref' => Carbon::now(),
            'response' => json_encode($response, true),
            'http_req' => $url,
            'http_payload' => $payload,
            'requestTransferTime' => $requestTransferTime
        ]);

        return response()->json($response, $code);
    }

    // API SISRUTE
    public static function signature()
    {
        $cons_id =  env('SISRUTE_CONS_ID');
        $secretKey = env('SISRUTE_SECRET_KEY');
        $userkey = env('SISRUTE_USER_KEY');


        date_default_timezone_set('UTC');
        $tStamp = strval(time() - strtotime('1970-01-01 00:00:00'));
        $signature = hash_hmac('sha256', $cons_id . "&" . $tStamp, $secretKey, true);
        $encodedSignature = base64_encode($signature);

        $response = array(
            'user_key' => $userkey,
            'x-cons-id' => $cons_id,
            'x-timestamp' => $tStamp,
            'x-signature' => $encodedSignature,
            'decrypt_key' => $cons_id . $secretKey . $tStamp,
        );
        return $response;
    }
    public static function stringDecrypt($key, $string)
    {
        $encrypt_method = 'AES-256-CBC';
        $key_hash = hex2bin(hash('sha256', $key));
        $iv = substr(hex2bin(hash('sha256', $key)), 0, 16);
        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key_hash, OPENSSL_RAW_DATA, $iv);
        $output = \LZCompressor\LZString::decompressFromEncodedURIComponent($output);
        return $output;
    }
    public static function response_decrypt($response, $signature, $url, $requestTransferTime)
    {
        // Sniff request body dari Guzzle (di-set Laravel HTTP client via transferStats).
        // GET tanpa body → string kosong; null-safe untuk catch block tanpa $response.
        $payload = $response->transferStats?->getRequest()?->getBody()?->__toString();

        if ($response->failed() && $response->json('metaData.code') === null) {
            // Gagal transport / HTML error page (upstream connect error dll)
            return self::sendError($response->reason(),  $response->json('response'), $response->status(), $url, $requestTransferTime, $payload);
        } else {
            // Check Response !200           -> metaData D besar
            $code = $response->json('metaData.code'); //code 200 -201 500 dll

            if ($code == 200) {
                $raw = $response->json('response');
                if (is_string($raw)) {
                    // Terenkripsi pola vclaim (production); bila decrypt gagal → plain string
                    $decrypt = self::stringDecrypt($signature['decrypt_key'], $raw);
                    $data = $decrypt ? json_decode($decrypt, true) : (json_decode($raw, true) ?? $raw);
                } else {
                    // Environment dev SISRUTE kerap balas plain JSON tanpa enkripsi
                    $data = $raw;
                }
            } else {

                $data = json_decode($response, true);
            }

            return self::sendResponse($response->json('metaData.message'), $data, $code ?? $response->status(), $url, $requestTransferTime, $payload);
        }
    }

    // KRITERIA RUJUKAN
    // Response: kriteriaRujukan[] {linkId, text, type} — linkId DINAMIS per ICD-10,
    // jangan di-cache lintas diagnosa — plus JejaringWilayah (answerOption wilayah).
    public static function sisrute_get_kriteria_rujukan($kodeDiagnosa, $kodeFaskesSatuSehat = null, $encounterUuid = null)
    {
        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
            'regex' => ':attribute harus ICD-10 rinci ber-titik (contoh A02.0) — kode induk 3 karakter ditolak SATUSEHAT.',
        ];

        // 2. Attributes (nama field yang user-friendly)
        $attributes = [
            'kodeDiagnosa' => 'Kode Diagnosa',
            'kodeFaskesSatuSehat' => 'Kode Faskes SATUSEHAT',
        ];

        // 3. Data yang akan divalidasi
        $r = [
            'kodeDiagnosa' => $kodeDiagnosa,
            'kodeFaskesSatuSehat' => $kodeFaskesSatuSehat ?: env('SATUSEHAT_ORGANIZATION_ID'),
        ];

        // 4. Rules validasi
        $rules = [
            'kodeDiagnosa' => ['required', 'regex:/^[A-Z][0-9]{2}\.[0-9]{1,2}$/'],
            'kodeFaskesSatuSehat' => 'required',
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);


        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        // handler when time out and off line mode
        try {

            $url = env('SISRUTE_URL') . "/Rujukan/GetKriteriaRujukan";

            // Verb POST + body JSON — GET ditolak gateway "405 Method Not Allowed".
            // encounter hanya disertakan bila terisi (SATUSEHAT menolak field kosong).
            $body = $r;
            if (!empty($encounterUuid)) {
                $body['encounter'] = ['reference' => 'Encounter/' . $encounterUuid];
            }

            $signature = self::signature();
            $response = Http::timeout(8)->connectTimeout(3)
                ->withHeaders($signature)
                ->post($url, $body);


            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }

    // FASKES RUJUKAN (kandidat)
    // $payload minimal: kodeFaskesSatuSehat, kodeSpesialis, kodeSarana, kodeDiagnosa,
    // estimasiRujuk (dd-mm-yyyy!), kriteriaRujukan.item[] (TEPAT SATU terisi),
    // codeJejaringWilayah, encounter.reference ("Encounter/<uuid>").
    // Response tanpa daftar faskes = memang tidak ada kandidat (bukan selalu error).
    public static function sisrute_get_faskes_rujukan($payload)
    {
        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
            'array' => ':attribute harus berupa array.',
        ];

        // 2. Attributes (nama field yang user-friendly)
        $attributes = [
            'kodeFaskesSatuSehat' => 'Kode Faskes SATUSEHAT',
            'kodeDiagnosa' => 'Kode Diagnosa',
            'kriteriaRujukan.item' => 'Kriteria Rujukan',
        ];

        // 3. Data yang akan divalidasi
        $r = $payload;

        // 4. Rules validasi
        $rules = [
            'kodeFaskesSatuSehat' => 'required',
            'kodeDiagnosa' => 'required',
            'kriteriaRujukan.item' => 'required|array|min:1',
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);


        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        // handler when time out and off line mode
        try {

            $url = env('SISRUTE_URL') . "/Rujukan/GetFaskesRujukan";

            $signature = self::signature();
            $response = Http::timeout(8)->connectTimeout(3)
                ->withHeaders($signature)
                ->post($url, $payload);


            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }

    // INSERT RUJUKAN
    // $tRujukan = isi node t_rujukan (wrapper request.t_rujukan ditambahkan di sini).
    // Sukses SEJATI = response memuat noRujukanSatuSehat — verifikasi di pemanggil,
    // nomor WAJIB tersimpan DB (syarat UAT).
    public static function sisrute_insert_rujukan($tRujukan)
    {
        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
            'date_format' => ':attribute harus format yyyy-mm-dd.',
        ];

        // 2. Attributes (nama field yang user-friendly)
        $attributes = [
            'noSep' => 'No SEP',
            'tglRujukan' => 'Tanggal Rujukan',
            'tglRencanaKunjungan' => 'Tanggal Rencana Kunjungan',
            'ppkDirujuk' => 'PPK Dirujuk',
            'diagRujukan' => 'Diagnosa Rujukan',
            'satuSehatRujukan.kodeFaskesSatuSehat' => 'Kode Faskes SATUSEHAT',
            'satuSehatRujukan.kdppkSatuSehatTujuanRujukan' => 'Kode SATUSEHAT Faskes Tujuan',
        ];

        // 3. Data yang akan divalidasi
        $r = $tRujukan;

        // 4. Rules validasi
        //    ppkDirujuk (BPJS) ↔ kdppkSatuSehatTujuanRujukan wajib RS yang sama — ambil dari kandidat
        $rules = [
            'noSep' => 'required',
            'tglRujukan' => 'required|date_format:Y-m-d',
            'tglRencanaKunjungan' => 'required|date_format:Y-m-d',
            'ppkDirujuk' => 'required',
            'diagRujukan' => 'required',
            'satuSehatRujukan.kodeFaskesSatuSehat' => 'required',
            'satuSehatRujukan.kdppkSatuSehatTujuanRujukan' => 'required',
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);


        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        // handler when time out and off line mode
        try {

            $url = env('SISRUTE_URL') . "/Rujukan/Insert";

            $signature = self::signature();
            $response = Http::timeout(8)->connectTimeout(3)
                ->withHeaders($signature)
                ->post($url, [
                    'request' => [
                        't_rujukan' => $tRujukan,
                    ],
                ]);


            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }

    // DELETE RUJUKAN — HTTP method DELETE (bukan POST!)
    public static function sisrute_delete_rujukan($noRujukan, $user = 'Sirus')
    {
        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
        ];

        // 2. Attributes (nama field yang user-friendly)
        $attributes = [
            'noRujukan' => 'No Rujukan',
        ];

        // 3. Data yang akan divalidasi
        $r = [
            'noRujukan' => $noRujukan,
        ];

        // 4. Rules validasi
        $rules = [
            'noRujukan' => 'required',
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);


        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        // handler when time out and off line mode
        try {

            $url = env('SISRUTE_URL') . "/Rujukan/Delete";

            $signature = self::signature();
            $response = Http::timeout(8)->connectTimeout(3)
                ->withHeaders($signature)
                ->delete($url, [
                    'request' => [
                        't_rujukan' => [
                            'noRujukan' => $noRujukan,
                            'user' => $user,
                        ],
                    ],
                ]);


            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }

    // REFERENSI SPESIALISTIK (master kode spesialis FKRTL)
    public static function sisrute_get_spesialistik()
    {
        // handler when time out and off line mode
        try {

            $url = env('SISRUTE_URL') . "/Rujukan/GetSpesialistik";

            $signature = self::signature();
            $response = Http::timeout(8)->connectTimeout(3)
                ->withHeaders($signature)
                ->get($url);


            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), null, 408, $url, null);
        }
    }
}
