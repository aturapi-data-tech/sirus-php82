<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Api\JwtSiap;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga API SIAP: Bearer JWT (App\Support\Api\JwtSiap) + akun users ber-role API SIAP + whitelist IP opsional (SIAP_API_IP_WHITELIST).
 * API ini baca (gaji dokter, master dokter, transaksi) + satu endpoint tulis struktur gaji dokter yang hanya menerima bila
 * services.siap.struktur_gaji_dari_siap menyala; aktif hanya bila SIAP_API_JWT_SECRET terisi.
 */
class AuthSiapApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (JwtSiap::secret() === '') {
            return response()->json(['error' => 'API SIAP tidak aktif (SIAP_API_JWT_SECRET belum diatur).'], 503);
        }
        // Tanpa SIAP_API_HOST, /api/siap ikut terjawab di host Mobile JKN (yang terbuka ke internet) → tutup 404.
        $hostMobileJkn = (string) config('services.mobile_jkn.host', '');
        if ($hostMobileJkn !== '' && $request->getHost() === $hostMobileJkn) {
            abort(404);
        }
        $whitelist = array_filter(array_map('trim', explode(',', (string) config('services.siap.ip_whitelist', ''))));
        if ($whitelist !== [] && !in_array($request->ip(), $whitelist, true)) {
            return response()->json(['error' => 'IP tidak diizinkan.'], 403);
        }
        $payload = JwtSiap::verifikasi($request->bearerToken());
        if ($payload === null) {
            return response()->json(['error' => 'Token tidak sah atau kedaluwarsa.'], 401);
        }
        // Token sah saja belum cukup: akunnya harus masih ada, aktif, dan ber-role API SIAP
        // (dinonaktifkan dari User Control → token yang beredar langsung tidak berlaku).
        $user = User::where('name', (string) ($payload['sub'] ?? ''))->first();
        if (!self::akunBoleh($user)) {
            return response()->json(['error' => 'Akun API SIAP tidak aktif atau bukan akun API.'], 401);
        }
        $request->attributes->set('siap_api_user', $user->name);

        return $next($request);
    }

    /** Akun boleh memakai API SIAP: ada, aktif, dan punya role services.siap.role. */
    public static function akunBoleh(?User $user): bool
    {
        return $user !== null && (string) ($user->active_status ?? '1') !== '0'
            && $user->hasRole((string) config('services.siap.role', 'Api Siap'));
    }
}
