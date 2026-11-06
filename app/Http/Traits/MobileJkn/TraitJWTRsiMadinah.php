<?php

namespace App\Http\Traits\MobileJkn;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\Api\Jwt;

trait TraitJWTRsiMadinah
{
    /*
     * Encode / decode JWT dipindah ke mesin bersama App\Support\Api\Jwt (dipakai juga API SIAP).
     * Bentuk token & aturan klaim Mobile JKN TIDAK berubah: exp = durasi detik sejak iat (12 jam),
     * pesan galat sama seperti sebelumnya. Token lama (base64 biasa, '+' '/') tetap diterima.
     */
    private function encode_jwt($payload, $key)
    {
        return Jwt::encode($payload, $key);
    }

    private function decode_jwt($token, $key)
    {
        if (empty($key)) {
            throw new \Exception('Key may not be empty');
        }
        if (!is_string($token) || substr_count($token, '.') != 2) {
            throw new \Exception('Wrong number of segments');
        }
        $payload = Jwt::decode($token, $key);
        if ($payload === null) {
            throw new \Exception('Signature verification failed');
        }
        if (isset($payload['exp']) && (time() - (int) ($payload['iat'] ?? 0)) >= (int) $payload['exp']) {
            throw new \Exception('Expired token');
        }

        return (object) $payload;
    }

    public function checkUser($username, $password)
    {

        $credentials = [
            'name' => $username,
            'password' => $password
        ];

        if (Auth::attempt($credentials)) {
            return true;
        }
        return false;
    }

    public function createToken($username, $password)
    {
        if ($this->checkUser($username, $password)) {
            $gtoken = $this->encode_jwt($this->payloadtoken($username), $this->privateKey());
            return $gtoken;
        }
        return 'x';
    }

    public function cektoken($token)
    {
        try {
            if ($this->decode_jwt($token, $this->privateKey())) {
                $response = true;
                return $response;
            }
        } catch (\Exception $e) {
            $response = array(
                'metadata' => array(
                    'message' => $e->getMessage(),
                    'code' => 201
                )
            );
            http_response_code(201);
            return $response;
        }
    }

    /* ---------------------- Konfigurasi TOKEN ----------------
     * Rahasia dari .env MOBILE_JKN_JWT_SECRET. Bila kosong, dipakai kunci lama yang dulu tertulis di kode
     * supaya token yang sedang beredar tidak mendadak ditolak — isi .env lalu tunggu 12 jam, kunci lama
     * tidak dipakai lagi. Rahasia ini TERPISAH dari SIAP_API_JWT_SECRET (API penggajian).
     */
    private function privateKey()
    {
        $key = (string) config('services.mobile_jkn.jwt_secret', '');

        return $key !== '' ? $key : '123!!abc**siRUS';
    }

    private function payloadtoken($username)
    {
        $token = array(
            "iss" => "Madinah REST API", //Pembuat Token
            "aud" => "Client Madinah REST API", //Penrima Token
            "iat" => time(), //time create Token
            "exp" => 43200, //12 jam {second time} detik
            "data" => array(
                "username" => $username
            )
        );
        return $token;
    }
    public function loggingMyRequest($request)
    {
        $log = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'headers' => $request->headers->all(),
            'body' => $request->getContent(),
        ];
        return $log;
    }
    public function sendResponse($request, $data, int $code = 200)
    {
        $logInfo = $this->loggingMyRequest($request);

        $response = [
            'response' => $data,
            'metadata' => [
                'message' => 'Ok',
                'code' =>  $code,
            ],
        ];

        DB::table('api_log_status')->insert([
            'datetime' =>  Carbon::now(env('APP_TIMEZONE')),
            'response' => json_encode($response, true),
            'request' => json_encode($logInfo, true),
        ]);


        return response()->json($response, $code);
    }
    public function sendError($request, $error,  $code = 404)
    {
        $logInfo =  $this->loggingMyRequest($request);

        $code = $code ?? 404;
        $response = [
            'metadata' => [
                'message' => $error,
                'code' => $code,
            ],
        ];

        DB::table('api_log_status')->insert([
            'datetime' =>  Carbon::now(env('APP_TIMEZONE')),
            'response' => json_encode($response, true),
            'request' => json_encode($logInfo, true),
        ]);

        return response()->json($response, $code);
    }

    public function hariIndo($hariInggris)
    {
        switch ($hariInggris) {
            case 'Sunday':
                return 'Minggu';
            case 'Monday':
                return 'Senin';
            case 'Tuesday':
                return 'Selasa';
            case 'Wednesday':
                return 'Rabu';
            case 'Thursday':
                return 'Kamis';
            case 'Friday':
                return 'Jumat';
            case 'Saturday':
                return 'Sabtu';
            default:
                return 'hari tidak valid';
        }
    }
}
