<?php

namespace App\Http\Controllers\Api\Siap;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthSiapApi;
use App\Models\User;
use App\Support\Api\JwtSiap;
use App\Support\GajiDokter\GajiDokter;
use App\Support\GajiDokter\ModePindahSiap;
use App\Support\GajiDokter\GajiDokterLampiran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * API SIAP (penggajian) — untuk menarik slip gaji dokter SIRUS ke SIAP per bulan jasa, dan (bertahap) memindahkan
 * penggajian dokter ke SIAP.
 *
 *   POST /api/siap/token                                   {username, password} → {token, expires_in}
 *   GET  /api/siap/dokter                                   master dokter + struktur gaji (pemetaan ke karyawan SIAP) + mode
 *   GET  /api/siap/gaji-dokter/{tahun}/{bulan}               header + detail slip semua dokter periode jasa itu
 *   GET  /api/siap/gaji-dokter/{tahun}/{bulan}/lampiran      lampiran rincian pasien (opsional ?dr_id=)
 *   PUT  /api/siap/dokter/{dr_id}/struktur-gaji              tahap 1: SIAP menulis struktur gaji dokter ke RSMST_DOCTORS
 *                                                            (hanya bila mode SIAP: struktur disetel di SIAP; selain itu 409)
 *   GET  /api/siap/gaji-dokter/{tahun}/{bulan}/transaksi     tahap 3: transaksi mentah bulan jasa per dokter (langsung dari
 *                                                            RSVIEW_NEWDOCSALARIES, tanpa butuh slip SIRUS) + struktur gaji saat ini
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

    /** Kolom struktur gaji di RSMST_DOCTORS yang dibaca GET /dokter dan ditulis PUT /dokter/{dr_id}/struktur-gaji. */
    public const KOLOM_STRUKTUR = ['basic_salary', 'npwp_status', 'npwp', 'skema_gaji_pokok', 'potongan_rs_basis', 'potongan_rs_persen', 'potongan_rs_aturan',
        'pph21_persen', 'tarif_per_kapita_ri', 'tarif_per_kapita_rj', 'tunjangan_struktural', 'tunjangan_fungsional', 'tunjangan_hadir',
        'potongan_idi', 'potongan_arisan', 'potongan_koperasi', 'potongan_angsuran', 'potongan_bpjs', 'potongan_zariyah'];

    /** Mode pemindahan gaji dokter — dibaca SIRUS dari tabel pengaturan SIAP (ModePindahSiap), dikirim bersama /dokter dan /transaksi. */
    public static function mode(): array
    {
        return ModePindahSiap::ambil();
    }

    /** Struktur gaji satu baris RSMST_DOCTORS sebagai array bernama (angka float, teks apa adanya, NULL → default). */
    public static function strukturDokter(object $dokter): array
    {
        return [
            'basic_salary' => (float) ($dokter->basic_salary ?? 0), 'npwp_status' => GajiDokter::statusNpwp($dokter), 'npwp' => trim((string) ($dokter->npwp ?? '')) ?: null,
            'skema_gaji_pokok' => (string) ($dokter->skema_gaji_pokok ?? 'A'), 'potongan_rs_basis' => (string) ($dokter->potongan_rs_basis ?? 'T'),
            'potongan_rs_persen' => (float) ($dokter->potongan_rs_persen ?? 0), 'potongan_rs_aturan' => $dokter->potongan_rs_aturan ?: null,
            'pph21_persen' => (float) ($dokter->pph21_persen ?? 0),
            'tarif_per_kapita_ri' => (float) ($dokter->tarif_per_kapita_ri ?? 0), 'tarif_per_kapita_rj' => (float) ($dokter->tarif_per_kapita_rj ?? 0),
            'tunjangan_struktural' => (float) ($dokter->tunjangan_struktural ?? 0), 'tunjangan_fungsional' => (float) ($dokter->tunjangan_fungsional ?? 0),
            'tunjangan_hadir' => (float) ($dokter->tunjangan_hadir ?? 0),
            'potongan_idi' => (float) ($dokter->potongan_idi ?? 0), 'potongan_arisan' => (float) ($dokter->potongan_arisan ?? 0),
            'potongan_koperasi' => (float) ($dokter->potongan_koperasi ?? 0), 'potongan_angsuran' => (float) ($dokter->potongan_angsuran ?? 0),
            'potongan_bpjs' => (float) ($dokter->potongan_bpjs ?? 0), 'potongan_zariyah' => (float) ($dokter->potongan_zariyah ?? 0),
        ];
    }

    public function dokter(): JsonResponse
    {
        $rows = DB::table('rsmst_doctors')->orderBy('dr_name')->get();

        return response()->json(['mode' => self::mode(), 'dokter' => $rows->map(fn($dokter) => [
            'dr_id' => (string) $dokter->dr_id, 'dr_name' => trim((string) $dokter->dr_name), 'dr_nik' => trim((string) ($dokter->dr_nik ?? '')),
            'kd_dr_bpjs' => trim((string) ($dokter->kd_dr_bpjs ?? '')), 'aktif' => (string) $dokter->active_status === '1',
            // dua kunci lama dipertahankan (kontrak SIAP lama), struktur lengkap di bawahnya
            'npwp_status' => GajiDokter::statusNpwp($dokter), 'skema_gaji_pokok' => (string) ($dokter->skema_gaji_pokok ?? 'A'),
            'gaji_pokok' => (float) ($dokter->basic_salary ?? 0),
            'struktur' => self::strukturDokter($dokter),
        ])->values()]);
    }

    /**
     * Tahap 1 — SIAP menjadi tempat menyetel struktur gaji dokter; SIRUS menerima salinannya di sini dan tetap menghitung slip.
     * Ditolak 409 bila mode di SIAP masih "struktur disetel di SIRUS" (ModePindahSiap), supaya dua sisi tidak saling menimpa. Aturan validasi = aturan form Struktur Gaji di Master Dokter.
     */
    public function strukturGaji(Request $request, string $drId): JsonResponse
    {
        if (!self::mode()['struktur_gaji_dari_siap']) {
            return response()->json(['error' => 'SIRUS masih sumber struktur gaji dokter — ubah mode di SIAP (Gaji Dokter → Struktur Gaji) ke "disetel di SIAP" dulu.'], 409);
        }
        if (!DB::table('rsmst_doctors')->where('dr_id', $drId)->exists()) {
            return response()->json(['error' => "Dokter {$drId} tidak ada di master SIRUS."], 404);
        }
        $validator = validator($request->all(), [
            'basic_salary' => 'nullable|numeric|min:0', 'npwp_status' => 'required|in:Y,N', 'npwp' => 'nullable|string|max:30',
            'skema_gaji_pokok' => 'required|in:A,G,N', 'potongan_rs_basis' => 'required|in:T,J,N,B',
            'potongan_rs_persen' => 'nullable|numeric|min:0|max:100', 'potongan_rs_aturan' => 'nullable|string|max:1000',
            'pph21_persen' => 'nullable|numeric|min:0|max:100',
            'tarif_per_kapita_ri' => 'nullable|numeric|min:0', 'tarif_per_kapita_rj' => 'nullable|numeric|min:0',
            'tunjangan_struktural' => 'nullable|numeric|min:0', 'tunjangan_fungsional' => 'nullable|numeric|min:0', 'tunjangan_hadir' => 'nullable|numeric|min:0',
            'potongan_idi' => 'nullable|numeric|min:0', 'potongan_arisan' => 'nullable|numeric|min:0', 'potongan_koperasi' => 'nullable|numeric|min:0',
            'potongan_angsuran' => 'nullable|numeric|min:0', 'potongan_bpjs' => 'nullable|numeric|min:0', 'potongan_zariyah' => 'nullable|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        if ($data['potongan_rs_basis'] === 'B') {
            $pesanAturan = GajiDokter::validasiAturan($data['potongan_rs_aturan'] ?? null);
            if ($pesanAturan !== null) {
                return response()->json(['error' => $pesanAturan], 422);
            }
        }
        // Field kosong disimpan 0, bukan NULL — NULL dalam aritmetika Oracle membuat seluruh hasil hitung gaji NULL (lihat form master).
        $payload = ['npwp_status' => $data['npwp_status'], 'npwp' => $data['npwp_status'] === 'N' ? null : (trim((string) ($data['npwp'] ?? '')) ?: null),
            'skema_gaji_pokok' => $data['skema_gaji_pokok'], 'potongan_rs_basis' => $data['potongan_rs_basis'],
            'potongan_rs_aturan' => $data['potongan_rs_basis'] === 'B' ? $data['potongan_rs_aturan'] : null];
        foreach (['basic_salary', 'potongan_rs_persen', 'pph21_persen', 'tarif_per_kapita_ri', 'tarif_per_kapita_rj', 'tunjangan_struktural', 'tunjangan_fungsional',
            'tunjangan_hadir', 'potongan_idi', 'potongan_arisan', 'potongan_koperasi', 'potongan_angsuran', 'potongan_bpjs', 'potongan_zariyah'] as $kolom) {
            $payload[$kolom] = (float) ($data[$kolom] ?? 0);
        }
        DB::table('rsmst_doctors')->where('dr_id', $drId)->update($payload);
        $dokter = DB::table('rsmst_doctors')->where('dr_id', $drId)->first();

        return response()->json(['ok' => true, 'dr_id' => (string) $drId, 'struktur' => self::strukturDokter($dokter),
            'ditulis_oleh' => (string) $request->attributes->get('siap_api_user', ''), 'ditulis_pada' => now()->toDateTimeString()]);
    }

    /**
     * Tahap 3 — transaksi mentah satu bulan jasa untuk dihitung SIAP: semua dokter yang punya baris di RSVIEW_NEWDOCSALARIES
     * bulan itu ditambah dokter bertarif kapita (perlu slip walau tanpa baris). Tidak bergantung pada slip SIRUS, jadi tetap
     * bekerja setelah Slip Gaji Dokter SIRUS dimatikan. Baris = bentuk yang sama dengan /lampiran (ditambah group); struktur
     * = RSMST_DOCTORS saat ini (SIAP memakai strukturnya sendiri bila sudah jadi sumber, ini hanya pembanding).
     */
    public function transaksi(Request $request, string $tahun, string $bulan): JsonResponse
    {
        if (($galat = $this->periodeTidakValid($tahun, $bulan)) !== null) {
            return $galat;
        }
        $drIdSaring = trim((string) $request->query('dr_id', ''));
        $drIdBertransaksi = DB::table('rsview_newdocsalaries as v')->distinct()
            ->whereRaw("to_char(to_date(v.doc_date, 'dd/mm/yyyy'), 'mm/yyyy') = ?", [$bulan . '/' . $tahun])
            ->when($drIdSaring !== '', fn($q) => $q->where('v.dr_id', $drIdSaring))
            ->pluck('v.dr_id')->map(fn($drId) => (string) $drId);
        $drIdKapita = DB::table('rsmst_doctors')
            ->where(fn($q) => $q->where('tarif_per_kapita_ri', '>', 0)->orWhere('tarif_per_kapita_rj', '>', 0))
            ->when($drIdSaring !== '', fn($q) => $q->where('dr_id', $drIdSaring))
            ->pluck('dr_id')->map(fn($drId) => (string) $drId);
        $drIdList = $drIdBertransaksi->merge($drIdKapita)->unique()->values()->all();
        $dokterList = DB::table('rsmst_doctors')->whereIn('dr_id', $drIdList ?: [''])->orderBy('dr_name')->get()->keyBy('dr_id');
        $barisPerDokter = GajiDokterLampiran::barisMassal($drIdList, $tahun, $bulan);
        $grupKapita = GajiDokterLampiran::grupKapitaMassal($drIdList);

        $hasil = collect($drIdList)->map(function ($drId) use ($dokterList, $barisPerDokter, $grupKapita) {
            $dokter = $dokterList[$drId] ?? null;
            if (!$dokter) {
                return null;
            }
            return [
                'dr_id' => (string) $drId, 'dr_name' => trim((string) $dokter->dr_name), 'aktif' => (string) $dokter->active_status === '1',
                'struktur' => self::strukturDokter($dokter), 'grup_kapita' => array_values($grupKapita[$drId] ?? []),
                'baris' => collect($barisPerDokter[$drId] ?? [])->map(fn($baris) => [
                    'komponen' => $baris['desc_doc'], 'group' => $baris['group_doc'], 'label' => $baris['label'], 'seq' => $baris['seq'], 'tgl' => $baris['tgl'],
                    'reg_no' => $baris['reg_no'], 'nama' => $baris['nama'], 'keterangan' => $baris['keterangan'], 'klaim' => $baris['klaim'],
                    'klaim_status' => $baris['klaim_status'], 'no_sep' => $baris['no_sep'], 'txn_no' => $baris['txn_no'],
                    'nominal' => (float) $baris['nominal'], 'pasien' => (int) $baris['pasien'],
                ])->values(),
            ];
        })->filter()->sortBy('dr_name')->values();

        return response()->json(['tahun_jasa' => $tahun, 'bulan_jasa' => $bulan, 'mode' => self::mode(), 'jumlah_dokter' => $hasil->count(),
            'diambil_pada' => now()->toDateTimeString(), 'dokter' => $hasil]);
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
