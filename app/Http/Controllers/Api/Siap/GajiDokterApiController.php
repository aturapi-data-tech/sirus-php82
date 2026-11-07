<?php

namespace App\Http\Controllers\Api\Siap;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthSiapApi;
use App\Models\User;
use App\Support\Api\JwtSiap;
use App\Support\GajiDokter\GajiDokterLampiran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * API SIAP (penggajian), baca-saja — SIAP menarik transaksi jasa dokter mentah per bulan jasa, lalu menghitung slip
 * gaji dokter sendiri dengan struktur gaji yang disetel di SIAP. SIRUS hanya pemasok data operasional.
 *
 *   POST /api/siap/token                                   {username, password} → {token, expires_in}
 *   GET  /api/siap/dokter                                   master dokter (pemetaan dr_id ↔ karyawan SIAP)
 *   GET  /api/siap/gaji-dokter/{tahun}/{bulan}/transaksi     transaksi mentah bulan jasa per dokter, langsung dari
 *                                                            RSVIEW_NEWDOCSALARIES (opsional ?dr_id=)
 *
 * Endpoint slip (/gaji-dokter/{tahun}/{bulan}) dan /lampiran DIHAPUS 2026-11-07 bersama tabel RSTXN_GAJIDOCTOR* dan
 * kolom parameter gaji di RSMST_DOCTORS (database/sql/2026_11_07_drop_gaji_dokter.sql): slip tidak lagi dihitung
 * di SIRUS, dan SIAP menyimpan slipnya sebagai snapshot sendiri.
 *
 * Akun API = user SIRUS (tabel users) dengan role services.siap.role ('Api Siap', buat lewat `php artisan siap-api:akun`);
 * admin bisa mengganti password / menonaktifkannya dari User Control. Rahasia JWT di .env SIAP_API_JWT_SECRET,
 * token HS256 berumur SIAP_API_TOKEN_TTL detik (mesin bersama App\Support\Api\Jwt). Angka dikirim mentah (float).
 * Tidak ada endpoint tulis: SIAP tidak pernah mengubah data SIRUS.
 */
class GajiDokterApiController extends Controller
{
    public function token(Request $request): JsonResponse
    {
        if (JwtSiap::secret() === '') {
            return response()->json(['error' => 'API SIAP tidak aktif (SIAP_API_JWT_SECRET belum diatur di .env).'], 503);
        }
        $hostMobileJkn = (string) config('services.mobile_jkn.host', '');
        if ($hostMobileJkn !== '' && $request->getHost() === $hostMobileJkn) {
            abort(404);
        }
        $username = (string) $request->input('username', '');
        $user = $username !== '' ? User::where('name', $username)->first() : null;
        $sah = $user !== null && Auth::validate(['name' => $username, 'password' => (string) $request->input('password', '')]);
        if (!$sah || !AuthSiapApi::akunBoleh($user)) {
            return response()->json(['error' => 'Username / password API salah, akun nonaktif, atau bukan akun API SIAP.'], 401);
        }

        return response()->json(['token' => JwtSiap::terbitkan($user->name), 'token_type' => 'Bearer', 'expires_in' => JwtSiap::ttl()]);
    }

    /**
     * Master dokter — identitas saja. Field npwp_status / skema_gaji_pokok / gaji_pokok yang dulu ikut dikirim
     * dihapus bersama kolomnya; SIAP (segarkanDokter) hanya membaca dr_id, dr_name, dr_nik, kd_dr_bpjs, aktif.
     */
    public function dokter(): JsonResponse
    {
        $rows = DB::table('rsmst_doctors')->orderBy('dr_name')->get();

        return response()->json(['dokter' => $rows->map(fn ($dokter) => [
            'dr_id' => (string) $dokter->dr_id, 'dr_name' => trim((string) $dokter->dr_name), 'dr_nik' => trim((string) ($dokter->dr_nik ?? '')),
            'kd_dr_bpjs' => trim((string) ($dokter->kd_dr_bpjs ?? '')), 'aktif' => (string) $dokter->active_status === '1',
        ])->values()]);
    }

    /**
     * Transaksi mentah satu bulan jasa untuk dihitung SIAP: semua dokter yang punya baris di RSVIEW_NEWDOCSALARIES bulan
     * itu. Dokter yang dibayar per kapita tanpa baris transaksi tidak lagi ditambahkan dari sini — tarif kapita dan
     * daftar dokternya ada di struktur gaji SIAP. Baris = bentuk yang sama dengan layar Monitoring Jasa Dokter
     * (GajiDokterLampiran::barisMassal), jadi angka yang ditarik SIAP = angka yang dilihat di SIRUS.
     */
    public function transaksi(Request $request, string $tahun, string $bulan): JsonResponse
    {
        if (($galat = $this->periodeTidakValid($tahun, $bulan)) !== null) {
            return $galat;
        }
        $drIdSaring = trim((string) $request->query('dr_id', ''));
        $drIdList = DB::table('rsview_newdocsalaries as rv')->distinct()
            ->whereRaw("to_char(to_date(rv.doc_date, 'dd/mm/yyyy'), 'mm/yyyy') = ?", [$bulan . '/' . $tahun])
            ->when($drIdSaring !== '', fn ($q) => $q->where('rv.dr_id', $drIdSaring))
            ->pluck('rv.dr_id')->map(fn ($drId) => (string) $drId)->values()->all();
        $dokterList = DB::table('rsmst_doctors')->whereIn('dr_id', $drIdList ?: [''])->orderBy('dr_name')->get()->keyBy('dr_id');
        $barisPerDokter = GajiDokterLampiran::barisMassal($drIdList, $tahun, $bulan);

        $hasil = collect($drIdList)->map(function ($drId) use ($dokterList, $barisPerDokter) {
            $dokter = $dokterList[$drId] ?? null;
            if (!$dokter) {
                return null;
            }

            return [
                'dr_id' => (string) $drId, 'dr_name' => trim((string) $dokter->dr_name), 'aktif' => (string) $dokter->active_status === '1',
                'baris' => collect($barisPerDokter[$drId] ?? [])->map(fn ($row) => [
                    'komponen' => $row['desc_doc'], 'group' => $row['group_doc'], 'label' => $row['label'], 'seq' => $row['seq'], 'tgl' => $row['tgl'],
                    'reg_no' => $row['reg_no'], 'nama' => $row['nama'], 'keterangan' => $row['keterangan'], 'klaim' => $row['klaim'],
                    'klaim_status' => $row['klaim_status'], 'no_sep' => $row['no_sep'], 'txn_no' => $row['txn_no'],
                    'nominal' => (float) $row['nominal'], 'pasien' => (int) $row['pasien'],
                ])->values(),
            ];
        })->filter()->sortBy('dr_name')->values();

        return response()->json(['tahun_jasa' => $tahun, 'bulan_jasa' => $bulan, 'jumlah_dokter' => $hasil->count(),
            'diambil_pada' => now()->toDateTimeString(), 'dokter' => $hasil]);
    }

    private function periodeTidakValid(string $tahun, string $bulan): ?JsonResponse
    {
        return preg_match('/^\d{4}$/', $tahun) && preg_match('/^(0[1-9]|1[0-2])$/', $bulan) ? null
            : response()->json(['error' => 'Periode harus /{tahun 4 digit}/{bulan 01-12}.'], 422);
    }
}
