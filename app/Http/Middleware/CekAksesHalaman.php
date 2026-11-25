<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AksesHalaman;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga URL halaman: user hanya boleh membuka route yang centang "Lihat"-nya
 * dimiliki salah satu role-nya (aturan di App\Support\AksesHalaman).
 *
 * Didaftarkan juga sebagai persistent middleware Livewire (AppServiceProvider),
 * jadi aksi /livewire/update dari halaman itu ikut dicek — bukan hanya saat
 * halaman pertama kali dibuka.
 *
 * Mode (config akses.mode / env AKSES_HALAMAN_MODE):
 *  - 'log'   : tetap diizinkan, tapi dicatat ke storage/logs/akses-halaman-*.log
 *              (masa transisi: melihat siapa yang selama ini membuka halaman di luar haknya)
 *  - 'tolak' : 403.
 * Route di AksesHalaman::ROUTE_SELALU_DIJAGA (Role/User Control) selalu 403 di kedua mode.
 */
class CekAksesHalaman
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user) {
            return $next($request);
        }

        $route = $request->route()?->getName();
        if (AksesHalaman::boleh($user, $route)) {
            return $next($request);
        }

        $this->catat($user, $route, $request);

        if (config('akses.mode') === 'tolak' || in_array($route, AksesHalaman::ROUTE_SELALU_DIJAGA, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }

    /** Satu catatan per user + route per hari — aksi Livewire berulang tidak membanjiri log. */
    private function catat(User $user, ?string $route, Request $request): void
    {
        $kunci = 'akses-halaman:' . $user->id . ':' . ($route ?? '-') . ':' . now()->format('Ymd');
        if (!Cache::add($kunci, 1, now()->endOfDay())) {
            return;
        }

        Log::channel('akses')->warning('Akses di luar hak', [
            'mode' => config('akses.mode'),
            'user_id' => $user->id,
            'user' => $user->myuser_name ?? $user->name ?? null,
            'roles' => $user->getRoleNames()->implode(', '),
            'route' => $route,
            'dikenal' => $route ? AksesHalaman::dikenal($route) : false,
            'url' => $request->fullUrl(),
        ]);
    }
}
