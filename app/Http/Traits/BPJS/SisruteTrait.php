<?php

namespace App\Http\Traits\BPJS;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use Exception;

/**
 * Sisrute Bridging — Rujukan Berbasis Kompetensi (khusus RJ rawat jalan).
 *
 * Referensi dokumentasi Kemenkes + BPJS:
 *   Postman Docs API Integrasi Satu Sehat Rujukan — https://bit.ly/IntegrasiSatuSehatRujukan
 *
 * Konteks arsitektur:
 *   - Base URL berbeda per level fasilitas:
 *     • FKTP (Puskesmas/Klinik)  → https://apijkn-dev.bpjs-kesehatan.go.id/pcare-sisrute-rest
 *     • FKRTL (Rumah Sakit)      → https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-sisrute-rest
 *     Karena aplikasi ini untuk RS (FKRTL), set SISRUTE_URL ke yang VClaim.
 *   - RJ  → lewat gateway VClaim-Sisrute (BPJS orkestrasi ke Sisrute + Satu Sehat).
 *     Header auth sama dengan VClaim (Cons-ID + Signature HMAC-SHA256).
 *   - UGD & Inap → langsung ke Satu Sehat FHIR (ServiceRequest) — pakai trait
 *     terpisah, TIDAK pakai SisruteTrait ini.
 *
 * Endpoint path (dari PDF resmi BPJS 2026-03-05):
 *   1. GET  {base}/Rujukan/GetKriteriaRujukan  (param: kodeDiagnosa, kodeFaskesSatuSehat)
 *   2. POST {base}/Rujukan/GetFaskesRujukan    (body: diagnosa + kompetensi + kriteria + wilayah)
 *   3. POST {base}/Rujukan/Insert              (body: t_rujukan + satuSehatRujukan)
 *
 * Env yang dipakai:
 *   SISRUTE_URL          -> Base URL VClaim-Sisrute (mis.
 *                           https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-sisrute-rest).
 *                           Fallback: VCLAIM_URL (tidak disarankan, path beda).
 *   SISRUTE_CONS_ID      -> Consumer ID (fallback VCLAIM_CONS_ID)
 *   SISRUTE_SECRET_KEY   -> Secret key HMAC-SHA256 (fallback VCLAIM_SECRET_KEY)
 *   SISRUTE_USER_KEY     -> User key (fallback VCLAIM_USER_KEY)
 *   SISRUTE_AUTH_USER    -> Basic auth username (opsional, untuk X-Authorization)
 *   SISRUTE_AUTH_PASS    -> Basic auth password (opsional)
 *
 * Scope (3 method aktif per PDF resmi):
 *   - sisrute_get_kriteria_rujukan($kodeDiagnosa, $kodeFaskesSatuSehat)
 *     → GET /Rujukan/GetKriteriaRujukan
 *   - sisrute_get_faskes_rujukan($payload) → POST /Rujukan/GetFaskesRujukan
 *   - sisrute_post_kunjungan($payload)     → POST /Rujukan/Insert
 *
 * Belum diimplementasikan (stub 501):
 *   - sisrute_delete_rujukan()  — path & struktur belum dipastikan
 */
trait SisruteTrait
{
    // ==============================================================
    // Helpers (signature, request, response parsing)
    // ==============================================================

    private static function sisruteSignature(): array
    {
        $consId    = (string) env('SISRUTE_CONS_ID', env('VCLAIM_CONS_ID'));
        $secretKey = (string) env('SISRUTE_SECRET_KEY', env('VCLAIM_SECRET_KEY'));
        $userKey   = (string) env('SISRUTE_USER_KEY', env('VCLAIM_USER_KEY'));

        $prevTz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $tStamp = (string) (time() - strtotime('1970-01-01 00:00:00'));
        date_default_timezone_set($prevTz);

        $sig     = hash_hmac('sha256', $consId . '&' . $tStamp, $secretKey, true);
        $encoded = base64_encode($sig);

        $headers = [
            'X-Cons-ID'    => $consId,
            'X-Timestamp'  => $tStamp,
            'X-Signature'  => $encoded,
            'user_key'     => $userKey,
            'Content-Type' => 'application/json',
        ];

        // X-Authorization Basic (opsional, sesuai docs Sisrute Delete/Rujukan)
        $authUser = env('SISRUTE_AUTH_USER');
        $authPass = env('SISRUTE_AUTH_PASS', '');
        if ($authUser !== null && $authUser !== '') {
            $headers['X-Authorization'] = 'Basic ' . base64_encode("{$authUser}:{$authPass}");
        }

        return $headers;
    }

    private static function sisruteRequest(string $method, string $endpoint, array $payload = [])
    {
        $base    = rtrim((string) env('SISRUTE_URL', env('VCLAIM_URL')), '/');
        $url     = $base . '/' . ltrim($endpoint, '/');
        $headers = self::sisruteSignature();

        $start = microtime(true);
        try {
            $req      = Http::timeout(15)->withHeaders($headers);
            $response = match (strtoupper($method)) {
                'POST'   => $req->post($url, $payload),
                'DELETE' => $req->delete($url, $payload),
                'PUT'    => $req->put($url, $payload),
                default  => $req->get($url, $payload),
            };
            $tt = microtime(true) - $start;

            return self::sisruteParseResponse($response, $url, $tt);
        } catch (Exception $e) {
            return self::sisruteRespond(
                ['code' => 408, 'message' => $e->getMessage()],
                null,
                $url,
                microtime(true) - $start,
            );
        }
    }

    private static function sisruteParseResponse($response, string $url, float $tt)
    {
        if ($response->failed()) {
            return self::sisruteRespond(
                ['code' => $response->status(), 'message' => $response->reason() ?: 'HTTP error'],
                $response->json('response'),
                $url,
                $tt,
            );
        }

        // Sisrute balas "metaData" (D besar). Fallback ke "metadata" untuk safety.
        $meta = $response->json('metaData') ?? $response->json('metadata') ?? ['code' => 200, 'message' => 'Ok'];
        $data = $response->json('response');
        return self::sisruteRespond($meta, $data, $url, $tt);
    }

    private static function sisruteRespond(array $meta, $data, string $url, float $tt)
    {
        $shape = [
            'response' => $data,
            'metadata' => [
                'code'    => (int) ($meta['code'] ?? 500),
                'message' => (string) ($meta['message'] ?? '-'),
            ],
        ];

        // Log silent — jangan breaks response kalau log gagal.
        try {
            DB::table('web_log_status')->insert([
                'code'                => $shape['metadata']['code'],
                'date_ref'            => Carbon::now(),
                'response'            => json_encode($shape, JSON_UNESCAPED_UNICODE),
                'http_req'            => $url,
                'requestTransferTime' => $tt,
            ]);
        } catch (\Throwable) {
            // silent
        }

        return response()->json($shape, $shape['metadata']['code'] ?: 500);
    }

    // ==============================================================
    // 1. POST /Sisrute/GetFaskesRujukan
    //    Cari daftar faskes tujuan rujukan berdasarkan diagnosa,
    //    kompetensi (kodeSpesialis), kriteria rujukan, & jejaring wilayah.
    //    Output dipakai sebagai kdppk tujuan di POST Kunjungan.
    // ==============================================================

    /**
     * @param array $payload {
     *   kodeFaskesSatuSehat: string,                  // 100010951
     *   kodeDiagnosa:        string,                  // ICD-10, mis. "I10"
     *   kodeSpesialis:       string,                  // dari referensi VClaim, mis. "095"
     *   tglRencanaKunjungan: string,                  // DD-MM-YYYY
     *   kriteriaRujukan:     {item: [{linkId, text, answer: [{valueBoolean|valueString}]}, …]},
     *   codeJejaringWilayah: {kodePropinsi, namaPropinsi, kodeKabupaten?, namaKabupaten?},
     *   encounter:           {reference: string}      // "Encounter/{uuid}"
     * }
     */
    public static function sisrute_get_faskes_rujukan(array $payload)
    {
        $rules = [
            'kodeFaskesSatuSehat'              => 'required|string',
            'kodeDiagnosa'                     => 'required|string',
            'kodeSpesialis'                    => 'required|string',
            'tglRencanaKunjungan'              => 'required|string',
            'kriteriaRujukan.item'             => 'required|array|min:1',
            'codeJejaringWilayah.kodePropinsi' => 'required|string',
            'codeJejaringWilayah.namaPropinsi' => 'required|string',
            'encounter.reference'              => 'required|string',
        ];
        $attributes = [
            'kodeFaskesSatuSehat'              => 'Kode Faskes Satu Sehat',
            'kodeDiagnosa'                     => 'Kode Diagnosa',
            'kodeSpesialis'                    => 'Kode Spesialis',
            'tglRencanaKunjungan'              => 'Tanggal Rencana Kunjungan',
            'kriteriaRujukan.item'             => 'Kriteria Rujukan',
            'codeJejaringWilayah.kodePropinsi' => 'Kode Propinsi',
            'codeJejaringWilayah.namaPropinsi' => 'Nama Propinsi',
            'encounter.reference'              => 'Encounter Reference',
        ];

        $v = Validator::make($payload, $rules, [':attribute wajib diisi.'], $attributes);
        if ($v->fails()) {
            return self::sisruteRespond(
                ['code' => 201, 'message' => $v->errors()->first()],
                $v->errors()->toArray(),
                '',
                0,
            );
        }

        return self::sisruteRequest('POST', '/Rujukan/GetFaskesRujukan', $payload);
    }

    // ==============================================================
    // 2. POST /Sisrute/postKunjungan
    //    Kirim rujukan lengkap (VClaim rujukan + data Satu Sehat).
    //    jnsPelayanan: "1"=Rawat Inap, "2"=Rawat Jalan.
    //    tipeRujukan: "0"=Penuh, "1"=Partial, "2"=Rujuk Balik.
    // ==============================================================

    /**
     * @param array $payload {
     *   noSep, tglRujukan (YYYY-MM-DD), tglRencanaKunjungan (YYYY-MM-DD),
     *   ppkDirujuk, jnsPelayanan ("1"|"2"), catatan, diagRujukan,
     *   tipeRujukan ("0"|"1"|"2"), poliRujukan, user,
     *   satuSehatRujukan: {
     *     kodeFaskesSatuSehat, idPasienSatuSehat,
     *     kdppkSatuSehatTujuanRujukan, kdDokterSatuSehat,
     *     encounter: {reference},
     *     patientInstruction?,
     *     kriteriaRujukan: {item: [{linkId, text, answer: [...]}, …]},
     *     keteranganRujukan?,
     *     codeJejaringWilayah: {kodePropinsi, namaPropinsi, kodeKabupaten?, namaKabupaten?}
     *   }
     * }
     */
    public static function sisrute_post_kunjungan(array $payload)
    {
        $rules = [
            'noSep'               => 'required|string',
            'tglRujukan'          => 'required|string',
            'tglRencanaKunjungan' => 'required|string',
            'ppkDirujuk'          => 'required|string',
            'jnsPelayanan'        => 'required|string|in:1,2',
            'catatan'             => 'required|string',
            'diagRujukan'         => 'required|string',
            'tipeRujukan'         => 'required|string|in:0,1,2',
            'poliRujukan'         => 'required|string',
            'user'                => 'required|string',

            'satuSehatRujukan.kodeFaskesSatuSehat'         => 'required|string',
            'satuSehatRujukan.idPasienSatuSehat'           => 'required|string',
            'satuSehatRujukan.kdppkSatuSehatTujuanRujukan' => 'required|string',
            'satuSehatRujukan.kdDokterSatuSehat'           => 'required|string',
            'satuSehatRujukan.encounter.reference'         => 'required|string',
            'satuSehatRujukan.kriteriaRujukan.item'        => 'required|array|min:1',
        ];
        $attributes = [
            'noSep'               => 'No. SEP',
            'tglRujukan'          => 'Tanggal Rujukan',
            'tglRencanaKunjungan' => 'Tanggal Rencana Kunjungan',
            'ppkDirujuk'          => 'PPK Tujuan Rujukan',
            'jnsPelayanan'        => 'Jenis Pelayanan',
            'catatan'             => 'Catatan',
            'diagRujukan'         => 'Diagnosa Rujukan',
            'tipeRujukan'         => 'Tipe Rujukan',
            'poliRujukan'         => 'Poli Rujukan',
            'user'                => 'User',

            'satuSehatRujukan.kodeFaskesSatuSehat'         => 'Kode Faskes Satu Sehat',
            'satuSehatRujukan.idPasienSatuSehat'           => 'ID Pasien Satu Sehat',
            'satuSehatRujukan.kdppkSatuSehatTujuanRujukan' => 'Kode PPK Tujuan Rujukan',
            'satuSehatRujukan.kdDokterSatuSehat'           => 'Kode Dokter Satu Sehat',
            'satuSehatRujukan.encounter.reference'         => 'Encounter Reference',
            'satuSehatRujukan.kriteriaRujukan.item'        => 'Kriteria Rujukan',
        ];

        $v = Validator::make($payload, $rules, [':attribute wajib diisi.'], $attributes);
        if ($v->fails()) {
            return self::sisruteRespond(
                ['code' => 201, 'message' => $v->errors()->first()],
                $v->errors()->toArray(),
                '',
                0,
            );
        }

        // Wrap dengan "request.t_rujukan" sesuai docs endpoint.
        $body = [
            'request' => [
                't_rujukan' => $payload,
            ],
        ];

        return self::sisruteRequest('POST', '/Rujukan/Insert', $body);
    }

    // ==============================================================
    // STUB — belum diimplementasikan (menunggu detail docs)
    // ==============================================================

    /**
     * DELETE /Rujukan/Delete (struktur baru dengan satuSehatRujukan).
     * Placeholder — scope dasar tahap 1 belum include delete.
     */
    public static function sisrute_delete_rujukan(array $payload = [])
    {
        return self::sisruteRespond(
            ['code' => 501, 'message' => 'Sisrute: delete rujukan belum diimplementasikan.'],
            null,
            '',
            0,
        );
    }

    // ==============================================================
    // GET /Rujukan/GetKriteriaRujukan
    //   Ambil daftar pertanyaan kriteria rujukan dari Satu Sehat Rujukan
    //   berdasarkan diagnosa + kode faskes. Hasil dipakai sebagai input
    //   kriteriaRujukan.item[] di POST GetFaskesRujukan & POST Insert.
    //
    //   Param: kodeDiagnosa (ICD-10), kodeFaskesSatuSehat.
    //   Response berisi: Daftar Pertanyaan + Jejaring Wilayah.
    // ==============================================================

    public static function sisrute_get_kriteria_rujukan(string $kodeDiagnosa, string $kodeFaskesSatuSehat)
    {
        if (empty($kodeDiagnosa)) {
            return self::sisruteRespond(['code' => 201, 'message' => 'Kode diagnosa wajib diisi.'], null, '', 0);
        }
        if (empty($kodeFaskesSatuSehat)) {
            return self::sisruteRespond(['code' => 201, 'message' => 'Kode faskes Satu Sehat wajib diisi.'], null, '', 0);
        }

        // Kirim sebagai query string (GET). Pola endpoint "{URL}/Rujukan/GetKriteriaRujukan".
        return self::sisruteRequest(
            'GET',
            '/Rujukan/GetKriteriaRujukan',
            [
                'kodeDiagnosa'        => $kodeDiagnosa,
                'kodeFaskesSatuSehat' => $kodeFaskesSatuSehat,
            ],
        );
    }
}
