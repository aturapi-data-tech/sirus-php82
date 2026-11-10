<?php

namespace App\Http\Traits\SatuDataKesehatan;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Kirim data diagnosis kunjungan ke Portal Satu Data Kesehatan Tulungagung (DINKESTA).
 *
 * Port dari sirus-lite (App\Http\Traits\SATUDATAKESEHATAN\SatuDataKesehatanTrait).
 * Satu panggilan = satu ICD-10 satu kunjungan. Beda dari versi lama:
 *  - field "kecamatan" kini berisi nama kecamatan (versi lama keliru mengirim nama desa);
 *  - hasil dikembalikan sebagai array, bukan JsonResponse;
 *  - timeout pendek (connect 3 dtk, total 8 dtk) supaya kirim bulk tak menggantung.
 *
 * Env: DINKESTULUNGAGUNG_URL, DINKESTULUNGAGUNG_XUSERNAME, DINKESTULUNGAGUNG_XPASSWORD.
 */
trait SatuDataKesehatanTrait
{
    /** Header otentikasi portal (x-username, x-password, x-token) — rumus sama dgn sirus-lite. */
    protected static function headerSatuDataKesehatan(): array
    {
        $username = env('DINKESTULUNGAGUNG_XUSERNAME');
        $password = env('DINKESTULUNGAGUNG_XPASSWORD');

        $header = json_encode(['user' => $username, 'pass' => $password]);
        $timestamp = strval(time() - strtotime('1970-01-01 00:00:00'));
        $payload = json_encode(['user_id' => $username, 'tStamp' => $timestamp]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, 'ab123', true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return [
            'x-username' => $username,
            'x-password' => $password,
            'x-token' => $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @param array $dataDiagnosis tanggal(d-m-Y), nik, nama, alamat, desa, kecamatan, icdx, kunjungan, kasus
     * HASIL DITENTUKAN DARI ISI "pesan", BUKAN KODE HTTP — portal membalas HTTP 200 untuk
     * SEMUA kasus (uji ke portal asli 10/11/2026):
     *   ditolak : 200  {"pesan":"Data tidak lengkap!"}
     *   diterima: 200  output dari kirim.php ini pesannya. Tanggal= ...{"pesan":"Data berhasil disimpan" }
     *             (teks debug di depan JSON → body BUKAN JSON valid; "pesan" diambil dengan regex)
     *
     * hasil:
     *   berhasil    = "pesan" memuat kata "berhasil"
     *   gagal       = "pesan" lain (mis. "Data tidak lengkap!"), HTTP 4xx, atau gagal sambung
     *                 (connect timeout / DNS / ditolak) — pasti belum tersimpan, boleh dikirim ulang
     *   tidak_pasti = habis waktu SETELAH tersambung, HTTP 5xx, atau balasan tanpa "pesan" —
     *                 data MUNGKIN sudah tersimpan; jangan dikirim ulang otomatis
     *
     * @return array{code:int, message:string, response:array, hasil:string, pesan:string}
     */
    protected static function kirimSatuDataKesehatan(array $dataDiagnosis): array
    {
        $url = (string) env('DINKESTULUNGAGUNG_URL');

        try {
            $response = Http::connectTimeout(3)
                ->timeout(8)
                ->withHeaders(self::headerSatuDataKesehatan())
                ->post($url, $dataDiagnosis);

            [$hasilKirim, $pesanKirim] = self::tafsirBalasanSatuDataKesehatan($response->status(), $response->body());
            $hasil = [
                'code' => $response->status(),
                'message' => $response->body(),
                'response' => [$response->body()],
                'hasil' => $hasilKirim,
                'pesan' => $pesanKirim,
            ];
            $waktuTransfer = $response->transferStats?->getTransferTime();
        } catch (Throwable $e) {
            $pesanGalat = $e->getMessage();
            $habisWaktuSetelahTersambung = str_contains($pesanGalat, 'Operation timed out') || str_contains($pesanGalat, 'bytes received');
            $hasil = [
                'code' => 408,
                'message' => $pesanGalat,
                'response' => [],
                'hasil' => $habisWaktuSetelahTersambung ? 'tidak_pasti' : 'gagal',
                'pesan' => $habisWaktuSetelahTersambung ? 'Habis waktu menunggu balasan portal' : 'Gagal tersambung ke portal',
            ];
            $waktuTransfer = null;
        }

        self::logSatuDataKesehatan($hasil, $url, $dataDiagnosis, $waktuTransfer);

        return $hasil;
    }

    /** @return array{0:string, 1:string} [berhasil|gagal|tidak_pasti, pesan untuk petugas] */
    protected static function tafsirBalasanSatuDataKesehatan(int $statusHttp, string $body): array
    {
        if ($statusHttp >= 500) {
            return ['tidak_pasti', "HTTP {$statusHttp} dari portal"];
        }
        if ($statusHttp !== 200) {
            return ['gagal', "HTTP {$statusHttp} dari portal"];
        }

        // "pesan" TERAKHIR — teks debug portal bisa mendahului JSON-nya.
        if (!preg_match_all('/"pesan"\s*:\s*"([^"]*)"/', $body, $cocok) || empty($cocok[1])) {
            return ['tidak_pasti', 'Balasan portal tak dikenal: ' . mb_strimwidth(trim($body), 0, 120, '...')];
        }
        $pesan = trim((string) end($cocok[1]));

        if (stripos($pesan, 'berhasil') !== false) {
            return ['berhasil', $pesan];
        }
        return ['gagal', $pesan !== '' ? $pesan : 'Ditolak portal tanpa keterangan'];
    }

    private static function logSatuDataKesehatan(array $hasil, string $url, array $dataDiagnosis, $waktuTransfer): void
    {
        try {
            DB::table('web_log_status')->insert([
                'code' => $hasil['code'],
                'date_ref' => Carbon::now(),
                'response' => json_encode(['response' => $hasil['response'], 'metadata' => ['message' => $hasil['message'], 'code' => $hasil['code']]]),
                'http_req' => $url,
                'http_payload' => json_encode($dataDiagnosis),
                'requestTransferTime' => $waktuTransfer,
            ]);
        } catch (Throwable $e) {
            // Log gagal tak boleh menggagalkan kiriman.
        }
    }
}
