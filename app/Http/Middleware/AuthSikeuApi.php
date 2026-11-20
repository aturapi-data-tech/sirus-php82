<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Api\JwtSikeu;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga API SIKEU: Bearer JWT (App\Support\Api\JwtSikeu) + akun users ber-role API SIKEU + whitelist IP opsional
 * (SIKEU_API_IP_WHITELIST). API ini BACA SAJA (akun & jurnal) dan hanya aktif bila SIKEU_API_JWT_SECRET terisi.
 */
class AuthSikeuApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (JwtSikeu::secret() === '') {
            return response()->json(['error' => 'API SIKEU tidak aktif (SIKEU_API_JWT_SECRET belum diatur).'], 503);
        }
        // /api/sikeu ikut terjawab di host Mobile JKN (yang terbuka ke internet) → tutup 404.
        $hostMobileJkn = (string) config('services.mobile_jkn.host', '');
        if ($hostMobileJkn !== '' && $request->getHost() === $hostMobileJkn) {
            abort(404);
        }
        $whitelist = array_filter(array_map('trim', explode(',', (string) config('services.sikeu.ip_whitelist', ''))));
        if ($whitelist !== [] && !in_array($request->ip(), $whitelist, true)) {
            return response()->json(['error' => 'IP tidak diizinkan.'], 403);
        }
        $payload = JwtSikeu::verifikasi($request->bearerToken());
        if ($payload === null) {
            return response()->json(['error' => 'Token tidak sah atau kedaluwarsa.'], 401);
        }
        // Token sah saja belum cukup: akunnya harus masih ada, aktif, dan ber-role API SIKEU.
        $user = User::where('name', (string) ($payload['sub'] ?? ''))->first();
        if (!self::akunBoleh($user)) {
            return response()->json(['error' => 'Akun API SIKEU tidak aktif atau bukan akun API.'], 401);
        }
        $request->attributes->set('sikeu_api_user', $user->name);

        return $next($request);
    }

    /** Akun boleh memakai API SIKEU: ada, aktif, dan punya role services.sikeu.role. */
    public static function akunBoleh(?User $user): bool
    {
        return $user !== null && (string) ($user->active_status ?? '1') !== '0'
            && $user->hasRole((string) config('services.sikeu.role', 'Api Sikeu'));
    }
}
