<?php

namespace App\Http\Traits\MobileJkn;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

trait TraitJWTRsiMadinah
{





    private function urlsafeB64Encode($input)
    {
        return str_replace(['+/', '='], ['-_', ''], base64_encode($input));
    }

    private function urlsafeB64Decode($input)
    {
        return str_replace(['-_', ''], ['+/',  '='], base64_decode($input));
    }

    private function signnature($msg, $key, $alg = 'HS256')
    {
        list($function, $algorithm) = $this->algoritm($alg);
        switch ($function) {
            case 'hash_hmac':
                return hash_hmac($algorithm, $msg, $key, true);
        }
    }



    private function encode_jwt($payload, $key, $alg = 'HS256')
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => $alg]);
        $payload = json_encode($payload);
        $segments = array();
        $segments[] = $this->urlsafeB64Encode($header);
        $segments[] = $this->urlsafeB64Encode($payload);
        $sign_input = implode('.', $segments);
        $signature = $this->signnature($sign_input, $key, $alg);
        $segments[] = $this->urlsafeB64Encode($signature);
        return implode('.', $segments);
    }

    private function decode_jwt($token, $key, array $allowed_algs = array())
    {
        if (empty($key)) {
            throw new \Exception('Key may not be empty');
        }

        $tks = explode('.', $token);
        if (count($tks) != 3) {
            throw new \Exception('Wrong number of segments');
        }

        list($headb64, $bodyb64, $cryptob64) = $tks;
        $header = json_decode($this->urlsafeB64Decode($headb64));
        $payload = json_decode($this->urlsafeB64Decode($bodyb64));

        if (null === ($header = json_decode($this->urlsafeB64Decode($headb64)))) {
            throw new \Exception('Invalid header encoding');
        }

        if (null === $payload = json_decode($this->urlsafeB64Decode($bodyb64))) {
            throw new \Exception('Invalid claims encoding');
        }

        if (false === ($sig = $this->urlsafeB64Decode($cryptob64))) {
            throw new \Exception('Invalid signature encoding');
        }

        if (empty($header->alg)) {
            throw new \Exception('Empty algorithm');
        }

        if (empty($this->algoritm($header->alg))) {
            throw new \Exception('Algorithm not supported');
        }

        if (!in_array($header->alg, $allowed_algs)) {
            throw new \Exception('Algorithm not allowed');
        }

        // Check the signature
        if (!$this->verify("$headb64.$bodyb64", $sig, $key, $header->alg)) {
            throw new \Exception('Signature verification failed');
        }

        // Check if this token has expired.
        if (isset($payload->exp) && (time() - $payload->iat) >= $payload->exp) {
            throw new \Exception('Expired token');
        }

        return $payload;
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
            if ($this->decode_jwt($token, $this->privateKey(), ['typ' => 'JWT', 'alg' => 'HS256'])) {
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

    // /* ---------------------- Configurasi TOKEN Information ----------------*/
    private function privateKey()
    {
        $key = '123!!abc**siRUS';
        return $key;
    }

    private function algoritm($alg)
    {
        $supported_algs = array(
            'ES256' => array('openssl', 'SHA256'),
            'HS256' => array('hash_hmac', 'SHA256'),
            'HS384' => array('hash_hmac', 'SHA384'),
            'HS512' => array('hash_hmac', 'SHA512'),
            'RS256' => array('openssl', 'SHA256'),
            'RS384' => array('openssl', 'SHA384'),
            'RS512' => array('openssl', 'SHA512'),
        );
        return $supported_algs[$alg];
    }

    private function verify($msg, $signature, $key, $alg)
    {
        if (empty($this->algoritm($alg))) {
            throw new \Exception('Algorithm not supported');
        }

        list($function, $algorithm) = $this->algoritm($alg);
        switch ($function) {
            case 'hash_hmac':
            default:
                $hash = hash_hmac($algorithm, $msg, $key, true);
                if (function_exists('hash_equals')) {
                    return hash_equals($signature, $hash);
                }
                $len = min(strlen($signature), strlen($hash));

                $status = 0;
                for ($i = 0; $i < $len; $i++) {
                    $status |= (\ord($signature[$i]) ^ \ord($hash[$i]));
                }
                $status |= (strlen($signature) ^ strlen($hash));

                return ($status === 0);
        }
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
