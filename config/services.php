<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Host API Mobile JKN (tanpa http:// dan port), mis. apijkn.rsimadinah.id.
    // Kosong = route API Mobile JKN tidak dimuat.
    'mobile_jkn' => [
        'host' => env('MOBILE_JKN_HOST'),
        // Rahasia JWT /auth Mobile JKN. Kosong = kunci lama di kode (sementara, untuk migrasi).
        'jwt_secret' => env('MOBILE_JKN_JWT_SECRET'),
    ],

    // API SIAP (penggajian) — baca saja; aktif hanya bila jwt_secret terisi (≥ 32 karakter).
    'siap' => [
        // Host khusus API SIAP (tanpa http:// dan port). Kosong = /api/siap dilayani di host SIRUS biasa
        // (tetap ditolak di host Mobile JKN). Diisi = hanya host ini; halaman web SIRUS ditutup di host itu.
        'host' => env('SIAP_API_HOST'),
        // Akun API = user di tabel users SIRUS dengan role ini (dibuat lewat `php artisan siap-api:akun`).
        'role' => env('SIAP_API_ROLE', 'Api Siap'),
        'jwt_secret' => env('SIAP_API_JWT_SECRET'),
        'token_ttl' => (int) env('SIAP_API_TOKEN_TTL', 3600),
        'ip_whitelist' => env('SIAP_API_IP_WHITELIST', ''),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
