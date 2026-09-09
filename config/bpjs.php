<?php

/*
|--------------------------------------------------------------------------
| BPJS Kesehatan — jalur keluar (egress) untuk SEMUA panggilan API BPJS
|--------------------------------------------------------------------------
| Kebijakan BPJS (Formulir Pengajuan Akses Bridging SIM, Sep 2026): API hanya
| melayani permintaan dari IP publik yang di-whitelist. IP yang didaftarkan
| adalah IP VPS (bukan IP kantor RS), sehingga seluruh lalu lintas ke BPJS
| dari aplikasi ini harus KELUAR lewat VPS itu — lewat forward proxy (Squid).
|
| Dipakai oleh App\Support\Bpjs\BpjsHttp — satu-satunya pembuat PendingRequest
| untuk trait VClaim, Antrean, Aplicares, iCare, SISRUTE, dan Apotek Online.
| SATUSEHAT dan layanan lain TIDAK lewat sini.
|
| Semua nilai dari .env agar tiap lingkungan (dev/prod) mengatur sendiri.
*/
return [
    // SAKLAR: true = panggilan BPJS keluar lewat proxy_url; false (bawaan) = langsung seperti sebelum
    // ada kebijakan whitelist. Dibuat terpisah dari URL supaya produksi bisa menyimpan URL proxy sejak
    // awal tanpa memakainya, lalu dinyalakan dengan satu baris saat BPJS mulai menegakkan whitelist.
    'proxy_aktif' => filter_var(env('BPJS_PROXY_AKTIF', false), FILTER_VALIDATE_BOOL),

    // URL forward proxy, mis. http://user:pass@38.103.170.232:3128 . Dipakai hanya bila proxy_aktif = true.
    'proxy_url' => env('BPJS_PROXY_URL'),

    // IP publik yang didaftarkan ke BPJS — dipakai `php artisan bpjs:cek-proxy` untuk
    // memastikan IP keluar benar-benar IP itu.
    'ip_whitelist' => env('BPJS_IP_WHITELIST'),

    // Batas waktu panggilan BPJS (detik). Panggilan sinkron tanpa batas = layar membeku
    // saat BPJS gangguan; jangan dinaikkan tanpa alasan.
    'timeout' => (int) env('BPJS_HTTP_TIMEOUT', 8),
    'connect_timeout' => (int) env('BPJS_HTTP_CONNECT_TIMEOUT', 3),

    // Layanan penunjuk IP publik untuk bpjs:cek-proxy.
    'ip_echo_url' => env('BPJS_IP_ECHO_URL', 'https://api.ipify.org?format=json'),
];
