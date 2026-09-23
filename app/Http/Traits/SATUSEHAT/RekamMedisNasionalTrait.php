<?php

namespace App\Http\Traits\SATUSEHAT;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * SATUSEHAT Rekam Medis Elektronik (SSRME) v2 — membuka riwayat rekam medis
 * NASIONAL pasien (data kiriman faskes lain) dari dalam EMR.
 * Acuan: "Petunjuk Teknis SATUSEHAT RME" v2.0 (1 Sep 2026).
 *
 *   1. POST ssrme/v2/ntl/chl (CHLink) → verificationUrl: halaman persetujuan
 *      pasien (kode akses dari SATUSEHAT Mobile) atau, dengan
 *      type_medical_summary EMERGENCY, form darurat tanpa persetujuan pasien.
 *   2. POST ssrme/v2/ntl/shl (SHLink) → shlinkUrl yang dibuka dokter.
 *      Belum ada persetujuan = 403 CONSENT_REQUIRED.
 *
 * Persetujuan melekat ke PASIEN + DOKTER (dengan batas waktu), jadi dokter
 * yang sudah disetujui bisa langsung ke langkah 2 pada kunjungan berikutnya.
 *
 * Keamanan (juknis): token & shlinkUrl/verificationUrl adalah URL sensitif —
 * tidak ditulis ke web_log_status (disamarkan), tidak disimpan di DB.
 *
 * Dipakai bersama SatuSehatTrait (getAccessToken, initializeSatuSehat).
 */
trait RekamMedisNasionalTrait
{
    /**
     * $identitas: patient_id (IHS pasien), patient_name, practitioner_id
     * (IHS dokter), practitioner_name. Organisasi diambil dari env.
     */
    protected function rekamMedisNasionalMintaPersetujuan(array $identitas, bool $darurat = false): array
    {
        $body = $this->rekamMedisNasionalBody($identitas);
        if ($darurat) {
            $body['type_medical_summary'] = 'EMERGENCY';
        }

        return $this->rekamMedisNasionalRequest('chl', $body);
    }

    protected function rekamMedisNasionalBukaTautan(array $identitas): array
    {
        return $this->rekamMedisNasionalRequest('shl', $this->rekamMedisNasionalBody($identitas));
    }

    private function rekamMedisNasionalBody(array $identitas): array
    {
        return [
            'patient_id' => (string) ($identitas['patient_id'] ?? ''),
            'patient_name' => (string) ($identitas['patient_name'] ?? ''),
            'practitioner_id' => (string) ($identitas['practitioner_id'] ?? ''),
            'practitioner_name' => (string) ($identitas['practitioner_name'] ?? ''),
            'organization_id' => (string) env('SATUSEHAT_ORGANIZATION_ID'),
            'organization_name' => (string) env('SATUSEHAT_ORGANIZATION_NAME'),
        ];
    }

    /**
     * Base URL SSRME: env SATUSEHAT_SSRME_URL bila diisi, selain itu host
     * SATUSEHAT_BASE_URL (stg/prod ikut sendiri) + /ssrme/v2/ntl/.
     */
    private function rekamMedisNasionalUrl(string $endpoint): string
    {
        $base = (string) env('SATUSEHAT_SSRME_URL', '');
        if ($base === '') {
            $bagianUrl = parse_url((string) env('SATUSEHAT_BASE_URL'));
            $base = ($bagianUrl['scheme'] ?? 'https') . '://' . ($bagianUrl['host'] ?? '') . '/ssrme/v2/ntl/';
        }

        return rtrim($base, '/') . '/' . $endpoint;
    }

    /**
     * Hasil: ok, code, pesan, kodeError (mis. CONSENT_REQUIRED), data.
     * Tidak pernah melempar — gangguan jaringan jadi ok=false code=0.
     */
    private function rekamMedisNasionalRequest(string $endpoint, array $body): array
    {
        $url = $this->rekamMedisNasionalUrl($endpoint);

        try {
            $this->initializeSatuSehat();
            $response = Http::timeout(15)->connectTimeout(5)
                ->withToken($this->getAccessToken())
                ->acceptJson()
                ->post($url, $body);
        } catch (\Throwable $e) {
            $this->logRekamMedisNasional($url, 0, $e->getMessage(), $body);

            return ['ok' => false, 'code' => 0, 'pesan' => 'Tidak bisa menghubungi SATUSEHAT: ' . $e->getMessage(), 'kodeError' => '', 'data' => []];
        }

        $json = $response->json() ?? [];
        $this->logRekamMedisNasional($url, $response->status(), $this->samarkanTautanRekamMedisNasional($response->body()), $body);

        $sukses = $response->successful() && ($json['success'] ?? false) === true;
        $pesan = (string) ($json['message']
            ?? (is_string($json['data'] ?? null) ? $json['data'] : null)
            ?? ($json['fault']['faultstring'] ?? null)
            ?? ('HTTP ' . $response->status()));

        return [
            'ok' => $sukses,
            'code' => (int) ($json['code'] ?? $response->status()),
            'pesan' => $sukses ? $pesan : $this->petunjukGalatRekamMedisNasional($pesan),
            'kodeError' => is_array($json['data'] ?? null) ? (string) ($json['data']['code'] ?? '') : '',
            'data' => $sukses && is_array($json['data'] ?? null) ? $json['data'] : [],
        ];
    }

    /**
     * Pesan asli + arti bagi petugas, untuk galat yang tercatat di Postman
     * "SATUSEHAT RME (AUG 2026)".
     */
    private function petunjukGalatRekamMedisNasional(string $pesan): string
    {
        $petunjuk = match (true) {
            stripos($pesan, 'wrong organization') !== false
                => 'Organization ID di .env tidak cocok dengan credential SATUSEHAT yang dipakai.',
            (bool) preg_match('/patient with ID .* not found/i', $pesan)
                => 'IHS pasien tidak dikenal SATUSEHAT di lingkungan ini (IHS produksi tidak berlaku di staging, dan sebaliknya).',
            (bool) preg_match('/practitioner with ID .* not found/i', $pesan)
                => 'IHS dokter tidak dikenal SATUSEHAT di lingkungan ini.',
            stripos($pesan, 'SIP not found') !== false
                => 'SIP dokter belum terdaftar di SATUSEHAT SDMK untuk RS ini.',
            stripos($pesan, 'ChaRME failed') !== false
                => 'Gangguan di server SATUSEHAT — coba lagi beberapa saat.',
            stripos($pesan, 'Invalid Access Token') !== false
                => 'Token SATUSEHAT ditolak — periksa credential.',
            default => '',
        };

        return $petunjuk === '' ? $pesan : $pesan . ' — ' . $petunjuk;
    }

    /** URL berisi token akses sekali pakai — jangan sampai tersimpan di log. */
    private function samarkanTautanRekamMedisNasional(string $responseBody): string
    {
        return preg_replace('/"(shlinkUrl|verificationUrl)"\s*:\s*"[^"]*"/', '"$1":"***"', $responseBody) ?? '';
    }

    private function logRekamMedisNasional(string $url, int $code, string $responseBody, array $body): void
    {
        try {
            DB::table('web_log_status')->insert([
                'code' => $code,
                'date_ref' => Carbon::now(env('APP_TIMEZONE')),
                'response' => $responseBody,
                'http_req' => $url,
                'http_payload' => json_encode($body),
                'requestTransferTime' => null,
            ]);
        } catch (\Throwable $e) {
            // Log gagal tidak boleh menggagalkan pembukaan rekam medis.
        }
    }
}
