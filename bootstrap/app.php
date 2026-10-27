<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // API Mobile JKN hanya di host-nya sendiri (URL terdaftar di BPJS, path di root).
            // Host kosong = API tidak dimuat.
            $hostMobileJkn = config('services.mobile_jkn.host');
            if (! $hostMobileJkn) {
                return;
            }
            // Rate limiter 'mobile-jkn' didaftarkan di AppServiceProvider::boot() — callback ini
            // dilewati saat route di-cache, jadi limiter tak boleh didefinisikan di sini.
            Route::domain($hostMobileJkn)
                ->middleware(['api', 'throttle:mobile-jkn'])
                ->name('mobilejkn.')
                ->group(base_path('routes/mobile-jkn.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*', headers:
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );
        $middleware->web(prepend: [
            \App\Http\Middleware\BatasiHostMobileJkn::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\TrackUserActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Bentuk error JSON API Mobile JKN dipertahankan sama dengan project lamanya
        $exceptions->render(function (Throwable $e, Request $request) {
            $hostMobileJkn = config('services.mobile_jkn.host');
            if (! $hostMobileJkn || $request->getHost() !== $hostMobileJkn) {
                return null;
            }
            if (! ($request->expectsJson() || $request->wantsJson())) {
                return null;
            }

            return response()->json([
                'error' => 'Error',
                'message' => $e->getMessage(),
            ], $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500);
        });
    })->create();
