<?php

use App\Http\Controllers\Api\MobileJkn\AntrolBPJSController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Mobile JKN (Antrean Online BPJS)
|--------------------------------------------------------------------------
|
| Pindahan dari project api-rsimadinah-laravel. Path SENGAJA di root host API
| (tanpa prefix) karena URL ini sudah terdaftar di BPJS — jangan diubah.
| Dimuat di bootstrap/app.php hanya untuk host services.mobile_jkn.host.
|
*/

Route::get('auth', [AntrolBPJSController::class, 'token'])->name('token');
// operasi
Route::post('jadwaloperasirs', [AntrolBPJSController::class, 'jadwaloperasirs'])->name('jadwaloperasirs');
Route::post('jadwaloperasipasien', [AntrolBPJSController::class, 'jadwaloperasipasien'])->name('jadwaloperasipasien');

// JKN
Route::post('ambilantrean', [AntrolBPJSController::class, 'ambilantrean'])->name('ambilantrean');
Route::post('checkinantrean', [AntrolBPJSController::class, 'checkinantrean'])->name('checkinantrean');
Route::post('batalantrean', [AntrolBPJSController::class, 'batalantrean'])->name('batalantrean');
Route::post('statusantrean', [AntrolBPJSController::class, 'statusantrean'])->name('statusantrean');
Route::post('sisaantrean', [AntrolBPJSController::class, 'sisaantrean'])->name('sisaantrean');
Route::post('pasienbaru', [AntrolBPJSController::class, 'pasienbaru'])->name('pasienbaru');

Route::post('x', [AntrolBPJSController::class, 'x'])->name('x');
