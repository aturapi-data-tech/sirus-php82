<?php

return [
    /*
    | Mode penjaga URL halaman (App\Http\Middleware\CekAksesHalaman):
    |  'log'   = izinkan tapi catat ke storage/logs/akses-halaman-*.log (masa transisi)
    |  'tolak' = balas 403 untuk user yang tidak punya centang Lihat
    */
    'mode' => env('AKSES_HALAMAN_MODE', 'log'),
];
