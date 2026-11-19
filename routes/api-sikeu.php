<?php

use App\Http\Controllers\Api\Sikeu\JurnalApiController;
use App\Http\Middleware\AuthSikeuApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API SIKEU — aplikasi keuangan (BACA SAJA)
|--------------------------------------------------------------------------
| Dimuat di bootstrap/app.php dengan prefix /api/sikeu hanya bila SIKEU_API_JWT_SECRET terisi.
| Token: POST /api/sikeu/token (throttle ketat). Endpoint data: Bearer JWT (App\Http\Middleware\AuthSikeuApi).
| SIKEU mengambil jurnal per cabang per bulan, menyimpannya sebagai snapshot (cutoff) di skemanya sendiri,
| dan membandingkan antar versi. Mesin jurnal tetap SATU di SIRUS (App\Support\Keuangan\Jurnal + JurnalCabang).
*/
Route::post('token', [JurnalApiController::class, 'token'])->middleware('throttle:sikeu-token')->name('token');

Route::middleware(AuthSikeuApi::class)->group(function () {
    Route::get('akun', [JurnalApiController::class, 'akun'])->name('akun');
    Route::get('cabang', [JurnalApiController::class, 'cabang'])->name('cabang');
    Route::get('jurnal/{tahun}/{bulan}', [JurnalApiController::class, 'jurnal'])->name('jurnal');
});
