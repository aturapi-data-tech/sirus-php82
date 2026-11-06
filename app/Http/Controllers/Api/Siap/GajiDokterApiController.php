<?php

namespace App\Http\Controllers\Api\Siap;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthSiapApi;
use App\Models\User;
use App\Support\Api\JwtSiap;
use App\Support\GajiDokter\GajiDokter;
use App\Support\GajiDokter\GajiDokterLampiran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * API SIAP (penggajian) — BACA SAJA, untuk menarik slip gaji dokter SIRUS ke SIAP per bulan jasa.
 *
 *   POST /api/siap/token                                   {username, password} → {token, expires_in}
 *   GET  /api/siap/dokter                                   master dokter (pemetaan ke karyawan SIAP)
 *   GET  /api/siap/gaji-dokter/{tahun}/{bulan}               header + detail slip semua dokter periode jasa itu
 *   GET  /api/siap/gaji-dokter/{tahun}/{bulan}/lampiran      lampiran rincian pasien (opsional ?dr_id=)
 *
 * Akun API = user SIRUS (tabel users) dengan role services.siap.role ('Api Siap', buat lewat `php artisan siap-api:akun`);
 * admin bisa mengganti password / menonaktifkannya dari User Control. Rahasia JWT di .env SIAP_API_JWT_SECRET,
 * token HS256 berumur SIAP_API_TOKEN_TTL detik (mesin bersama App\Support\Api\Jwt). Angka dikirim mentah (float), potongan NEGATIF seperti di tabel.
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

    public function dokter(): JsonResponse
    {
        $rows = DB::table('rsmst_doctors')->orderBy('dr_name')
            ->get(['dr_id', 'dr_name', 'dr_nik', 'kd_dr_bpjs', 'active_status', 'npwp_status', 'skema_gaji_pokok', 'basic_salary']);

        return response()->json(['dokter' => $rows->map(fn($d) => [
            'dr_id' => (string) $d->dr_id, 'dr_name' => trim((string) $d->dr_name), 'dr_nik' => trim((string) ($d->dr_nik ?? '')),
            'kd_dr_bpjs' => trim((string) ($d->kd_dr_bpjs ?? '')), 'aktif' => (string) $d->active_status === '1',
            'npwp_status' => (string) ($d->npwp_status ?? 'Y'), 'skema_gaji_pokok' => (string) ($d->skema_gaji_pokok ?? 'A'),
            'gaji_pokok' => (float) ($d->basic_salary ?? 0),
        ])->values()]);
    }

    public function periode(string $tahun, string $bulan): JsonResponse
    {
        if (($galat = $this->periodeTidakValid($tahun, $bulan)) !== null) {
            return $galat;
        }
        $headerList = DB::table('rstxn_gajidoctorhdrs as h')->join('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->where('h.tahun_jasa', $tahun)->where('h.bulan_jasa', $bulan)->orderBy('d.dr_name')
            ->select('h.*', 'd.dr_name', 'd.dr_nik', 'd.kd_dr_bpjs')->get();
        $detailPerSlip = DB::table('rstxn_gajidoctordtls')->whereIn('gajidoctor_no', $headerList->pluck('gajidoctor_no')->all() ?: [0])
            ->orderBy('jenis')->orderBy('urutan')->orderBy('gajidoctor_dtl')->get()->groupBy('gajidoctor_no');

        $slip = $headerList->map(function ($h) use ($detailPerSlip) {
            $detail = ($detailPerSlip[$h->gajidoctor_no] ?? collect())->map(fn($d) => [
                'jenis' => (string) $d->jenis, 'kode' => (string) $d->kode, 'label' => GajiDokter::labelKomponen((string) $d->kode, $d->keterangan),
                'keterangan' => (string) ($d->keterangan ?? ''), 'nilai' => (float) $d->nilai, 'nilai_manual' => (string) $d->nilai_manual === 'Y',
                'jumlah_pasien' => (int) $d->jumlah_pasien, 'urutan' => (int) $d->urutan,
            ])->values();

            return [
                'gajidoctor_no' => (int) $h->gajidoctor_no, 'dr_id' => (string) $h->dr_id, 'dr_name' => trim((string) $h->dr_name),
                'dr_nik' => trim((string) ($h->dr_nik ?? '')), 'kd_dr_bpjs' => trim((string) ($h->kd_dr_bpjs ?? '')),
                'tahun_jasa' => $h->tahun_jasa, 'bulan_jasa' => $h->bulan_jasa, 'tahun_gaji' => $h->tahun_gaji, 'bulan_gaji' => $h->bulan_gaji,
                'skema_gaji_pokok' => $h->skema_gaji_pokok, 'nilai_gaji_pokok' => (float) $h->nilai_gaji_pokok,
                'potongan_rs_basis' => $h->potongan_rs_basis, 'potongan_rs_persen' => (float) $h->potongan_rs_persen,
                'pph21_persen' => (float) $h->pph21_persen, 'npwp_status' => $h->npwp_status,
                'jasa_total' => (float) $h->jasa_total, 'total_gaji' => (float) $h->total_gaji, 'potongan_rs' => (float) $h->potongan_rs,
                'pph21' => (float) $h->pph21, 'potongan_lain_total' => (float) $h->potongan_lain_total, 'tambahan_total' => (float) $h->tambahan_total,
                'gaji_diterima' => (float) $h->gaji_diterima, 'gaji_status' => $h->gaji_status,
                'tanggal_bayar' => $h->tanggal_bayar ? substr((string) $h->tanggal_bayar, 0, 10) : null,
                'update_date' => $h->update_date ? substr((string) $h->update_date, 0, 19) : ($h->entry_date ? substr((string) $h->entry_date, 0, 19) : null),
                'detail' => $detail,
            ];
        })->values();

        return response()->json(['tahun_jasa' => $tahun, 'bulan_jasa' => $bulan, 'jumlah' => $slip->count(), 'diambil_pada' => now()->toDateTimeString(), 'slip' => $slip]);
    }

    public function lampiran(Request $request, string $tahun, string $bulan): JsonResponse
    {
        if (($galat = $this->periodeTidakValid($tahun, $bulan)) !== null) {
            return $galat;
        }
        $drId = trim((string) $request->query('dr_id', ''));
        $noList = DB::table('rstxn_gajidoctorhdrs')->where('tahun_jasa', $tahun)->where('bulan_jasa', $bulan)
            ->when($drId !== '', fn($q) => $q->where('dr_id', $drId))->pluck('gajidoctor_no')->map(fn($n) => (int) $n)->all();

        $hasil = GajiDokterLampiran::susun($noList)->map(fn($satu) => [
            'gajidoctor_no' => (int) $satu['header']->gajidoctor_no, 'dr_id' => (string) $satu['header']->dr_id, 'dr_name' => trim((string) $satu['header']->dr_name),
            'grup_kapita' => array_values($satu['grupKapita']),
            'angka' => $satu['angka'],
            'baris' => collect($satu['lampiran'])->map(fn($b) => [
                'komponen' => $b['desc_doc'], 'group' => $b['group_doc'], 'label' => $b['label'], 'tgl' => $b['tgl'], 'reg_no' => $b['reg_no'], 'nama' => $b['nama'],
                'keterangan' => $b['keterangan'], 'klaim' => $b['klaim'], 'klaim_status' => $b['klaim_status'], 'no_sep' => $b['no_sep'], 'txn_no' => $b['txn_no'],
                'nominal' => (float) $b['nominal'], 'pasien' => (int) $b['pasien'],
                'masuk_slip' => !in_array($b['group_doc'], $satu['grupKapita'], true),
            ])->values(),
        ])->values();

        return response()->json(['tahun_jasa' => $tahun, 'bulan_jasa' => $bulan, 'jumlah_dokter' => $hasil->count(), 'lampiran' => $hasil]);
    }

    private function periodeTidakValid(string $tahun, string $bulan): ?JsonResponse
    {
        return preg_match('/^\d{4}$/', $tahun) && preg_match('/^(0[1-9]|1[0-2])$/', $bulan) ? null
            : response()->json(['error' => 'Periode harus /{tahun 4 digit}/{bulan 01-12}.'], 422);
    }
}
