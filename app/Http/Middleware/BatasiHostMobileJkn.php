<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Host API Mobile JKN terbuka ke internet (dipanggil BPJS). Halaman SIRUS
 * (grup web) tidak boleh ikut bisa dibuka lewat host itu -> 404.
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

        return $next($request);
    }
}
