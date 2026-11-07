<?php

use App\Http\Controllers\Api\Siap\GajiDokterApiController;
use App\Http\Middleware\AuthSiapApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API SIAP — integrasi penggajian (BACA SAJA)
|--------------------------------------------------------------------------
| Dimuat di bootstrap/app.php dengan prefix /api/siap hanya bila SIAP_API_JWT_SECRET terisi.
| Token: POST /api/siap/token (throttle ketat). Endpoint data: Bearer JWT (App\Http\Middleware\AuthSiapApi).
*/

Route::post('token', [GajiDokterApiController::class, 'token'])->middleware('throttle:siap-token')->name('token');

Route::middleware(AuthSiapApi::class)->group(function () {
    Route::get('dokter', [GajiDokterApiController::class, 'dokter'])->name('dokter');
    Route::get('gaji-dokter/{tahun}/{bulan}', [GajiDokterApiController::class, 'periode'])->name('gaji-dokter');
    Route::get('gaji-dokter/{tahun}/{bulan}/lampiran', [GajiDokterApiController::class, 'lampiran'])->name('gaji-dokter.lampiran');
    // transaksi mentah bulan jasa — dipakai SIAP saat slip gaji dokter dihitung di SIAP (SIRUS hanya pemasok transaksi)
    Route::get('gaji-dokter/{tahun}/{bulan}/transaksi', [GajiDokterApiController::class, 'transaksi'])->name('gaji-dokter.transaksi');
});
