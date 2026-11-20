<?php

namespace App\Http\Controllers\Api\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthSikeuApi;
use App\Models\User;
use App\Support\Api\JwtSikeu;
use App\Support\Keuangan\Jurnal;
use App\Support\Keuangan\JurnalCabang;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * API SIKEU (aplikasi keuangan), baca-saja — SIKEU mengambil jurnal untuk cutoff & pembanding versi.
 *
 *   POST /api/sikeu/token                          {username, password} → {token, expires_in}
 *   GET  /api/sikeu/akun                           bagan akun (acmst_accounts, semua kolom)
 *   GET  /api/sikeu/cabang                         katalog cabang jurnal: no, kunci, sumber, label
 *   GET  /api/sikeu/jurnal/{tahun}/{bulan}?cabang=N  SEMUA baris jurnal cabang N pada bulan itu
 *   GET  /api/sikeu/saldo-awal/{tahun}             saldo awal akun per 1 Januari (tktxn_saldoawalakuns)
 *
 * Satu panggilan = satu cabang (satu SELECT tanpa UNION, aman dari wrong-result Oracle 10.2.0.1); SIKEU
 * memanggil berulang untuk tiap cabang dari /cabang. Sumber angka = App\Support\Keuangan\Jurnal, mesin yang
 * sama dengan Buku Besar / Laba Rugi / Neraca di SIRUS. Angka dikirim mentah (float).
 *
 * Akun API = user SIRUS (tabel users) dengan role services.sikeu.role ('Api Sikeu', buat lewat
 * `php artisan sikeu-api:akun`). Tidak ada endpoint tulis: SIKEU tidak pernah mengubah data SIRUS.
 */
class JurnalApiController extends Controller
{
    private const OPSI_JSON = JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE;

    public function token(Request $request): JsonResponse
    {
        if (JwtSikeu::secret() === '') {
            return response()->json(['error' => 'API SIKEU tidak aktif (SIKEU_API_JWT_SECRET belum diatur di .env).'], 503);
        }
        $hostMobileJkn = (string) config('services.mobile_jkn.host', '');
        if ($hostMobileJkn !== '' && $request->getHost() === $hostMobileJkn) {
            abort(404);
        }
        $username = (string) $request->input('username', '');
        $user = $username !== '' ? User::where('name', $username)->first() : null;
        $sah = $user !== null && Auth::validate(['name' => $username, 'password' => (string) $request->input('password', '')]);
        if (!$sah || !AuthSikeuApi::akunBoleh($user)) {
            return response()->json(['error' => 'Username / password API salah, akun nonaktif, atau bukan akun API SIKEU.'], 401);
        }

        return response()->json(['token' => JwtSikeu::terbitkan($user->name), 'token_type' => 'Bearer', 'expires_in' => JwtSikeu::ttl()]);
    }

    public function akun(): JsonResponse
    {
        $akunList = DB::table('acmst_accounts')->orderBy('acc_id')->get()
            ->map(fn ($akun) => array_map(fn ($nilai) => is_string($nilai) ? trim($nilai) : $nilai, (array) $akun));

        return response()->json(['akun' => $akunList->values()], 200, [], self::OPSI_JSON);
    }

    /** Katalog cabang. `kunci` tetap antar-panggilan selama definisi cabangnya tidak diubah. */
    public function cabang(): JsonResponse
    {
        $cabangList = collect(JurnalCabang::semua())->map(fn (array $cabang, int $nomor) => [
            'no' => $nomor,
            'kunci' => Jurnal::kunciCabang($cabang),
            'sumber' => $cabang['sumber'],
            'label' => $cabang['label'],
        ]);

        return response()->json(['jumlah' => $cabangList->count(), 'cabang' => $cabangList->values()], 200, [], self::OPSI_JSON);
    }

    public function jurnal(Request $request, string $tahun, string $bulan): JsonResponse
    {
        if (!preg_match('/^\d{4}$/', $tahun) || !preg_match('/^(0[1-9]|1[0-2])$/', $bulan)) {
            return response()->json(['error' => 'Periode harus /{tahun 4 digit}/{bulan 01-12}.'], 422);
        }
        $cabangList = JurnalCabang::semua();
        $nomor = $request->query('cabang');
        if (!is_string($nomor) || !ctype_digit($nomor) || !isset($cabangList[(int) $nomor])) {
            return response()->json(['error' => 'Parameter ?cabang= wajib, nomor 0–' . (count($cabangList) - 1) . ' (lihat /api/sikeu/cabang).'], 422);
        }
        $cabang = $cabangList[(int) $nomor];
        $awalBulan = Carbon::createFromFormat('Y-m-d', "{$tahun}-{$bulan}-01")->startOfDay();

        $barisList = array_map(fn ($baris) => [
            'txn_name' => trim((string) $baris->txn_name),
            'txn_acc' => $baris->txn_acc === null ? null : trim((string) $baris->txn_acc),
            'txn_acc_k' => $baris->txn_acc_k === null ? null : trim((string) $baris->txn_acc_k),
            'shift' => $baris->shift === null ? null : trim((string) $baris->shift),
            'txn_date' => $baris->txn_date,
            'txn_d' => (float) ($baris->txn_d ?? 0),
            'txn_k' => (float) ($baris->txn_k ?? 0),
        ], Jurnal::barisCabang($cabang, $awalBulan->toDateString(), $awalBulan->copy()->endOfMonth()->toDateString()));

        return response()->json([
            'tahun' => $tahun, 'bulan' => $bulan,
            'cabang' => ['no' => (int) $nomor, 'kunci' => Jurnal::kunciCabang($cabang), 'sumber' => $cabang['sumber']],
            'jumlah' => count($barisList),
            'baris' => $barisList,
        ], 200, [], self::OPSI_JSON);
    }

    /**
     * Saldo awal akun per 1 Januari tahun itu, apa adanya dari tktxn_saldoawalakuns (hasil tutup buku SIRUS).
     * Nilai disimpan di kolom sisi normal akunnya (sa_acc_d untuk akun D, sa_acc_k untuk akun K) dan boleh negatif;
     * SIKEU menghitung nilai bertanda = sa_acc_d - sa_acc_k. Total D/K TIDAK dijamin seimbang (L01 = penyeimbang SIRUS).
     */
    public function saldoAwal(string $tahun): JsonResponse
    {
        if (!preg_match('/^\d{4}$/', $tahun)) {
            return response()->json(['error' => 'Tahun harus 4 digit.'], 422);
        }
        $saldoList = DB::table('tktxn_saldoawalakuns as s')
            ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 's.acc_id')
            ->where('s.sa_year', $tahun)->orderBy('s.acc_id')
            ->get(['s.acc_id', 'a.acc_name', 'a.dk_status', 's.sa_acc_d', 's.sa_acc_k'])
            ->map(fn ($saldo) => [
                'acc_id' => trim((string) $saldo->acc_id),
                'acc_name' => trim((string) $saldo->acc_name),
                'dk_status' => trim((string) $saldo->dk_status),
                'sa_acc_d' => (float) $saldo->sa_acc_d,
                'sa_acc_k' => (float) $saldo->sa_acc_k,
            ]);

        return response()->json(['tahun' => $tahun, 'jumlah' => $saldoList->count(), 'saldo' => $saldoList->values()], 200, [], self::OPSI_JSON);
    }
}
