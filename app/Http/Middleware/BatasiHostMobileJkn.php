<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Host API Mobile JKN terbuka ke internet (dipanggil BPJS). Halaman SIRUS
 * (grup web) tidak boleh ikut bisa dibuka lewat host itu -> 404. Berlaku juga
 * untuk host khusus API SIAP (SIAP_API_HOST) & API SIKEU (SIKEU_API_HOST) bila diisi.
 * Route API Mobile JKN memakai grup api, jadi tidak melewati middleware ini.
 */
class BatasiHostMobileJkn
{
    public function handle(Request $request, Closure $next): Response
    {
        $hostMobileJkn = config('services.mobile_jkn.host');

        if ($hostMobileJkn && $request->getHost() === $hostMobileJkn) {
            abort(404);
        }

        // Host khusus API SIAP (SIAP_API_HOST) & SIKEU (SIKEU_API_HOST) diperlakukan sama — kecuali bila nilainya host SIRUS sendiri
        // (APP_URL), supaya salah isi tidak mematikan seluruh halaman SIRUS.
        $hostSirus = parse_url((string) config('app.url'), PHP_URL_HOST);
        foreach (['services.siap.host', 'services.sikeu.host'] as $kunciHost) {
            $hostApi = (string) config($kunciHost, '');
            if ($hostApi !== '' && $request->getHost() === $hostApi && $hostApi !== $hostSirus) {
                abort(404);
            }
        }

        return $next($request);
    }
}
