<?php

namespace App\Support\Bpjs;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Satu pintu keluar untuk SEMUA panggilan API BPJS Kesehatan (VClaim, Antrean,
 * Aplicares, iCare, SISRUTE, Apotek Online).
 *
 * Kenapa ada: BPJS mem-whitelist IP publik pemanggil. IP yang didaftarkan
 * (config bpjs.ip_whitelist) adalah IP VPS, bukan IP kantor RS, jadi permintaan
 * harus keluar lewat forward proxy di VPS itu (config bpjs.proxy_url).
 *
 * OPSIONAL lewat saklar bpjs.proxy_aktif (env BPJS_PROXY_AKTIF, bawaan false):
 * mati = langsung persis seperti sebelum ada kebijakan (produksi saat ini);
 * hidup = lewat proxy_url. URL boleh tersimpan duluan tanpa dipakai.
 *
 * Pemakaian di trait:  BpjsHttp::mulai()->withHeaders($signature)->get($url);
 * (menggantikan Http::timeout(8)->connectTimeout(3)).
 *
 * Batas waktu tetap dipasang di sini: panggilan BPJS sinkron tanpa batas membuat
 * layar membeku saat BPJS gangguan.
 */
final class BpjsHttp
{
    public static function mulai(): PendingRequest
    {
        $permintaan = Http::timeout(self::timeout())
            ->connectTimeout(self::connectTimeout());

        if (self::lewatProxy()) {
            // Guzzle: satu string proxy berlaku untuk http maupun https (CONNECT).
            $permintaan = $permintaan->withOptions(['proxy' => self::proxyUrl()]);
        }

        return $permintaan;
    }

    /** Saklar env BPJS_PROXY_AKTIF. */
    public static function proxyAktif(): bool
    {
        return (bool) config('bpjs.proxy_aktif', false);
    }

    /** Benar-benar lewat proxy = saklar hidup DAN URL terisi. */
    public static function lewatProxy(): bool
    {
        return self::proxyAktif() && self::proxyUrl() !== '';
    }

    public static function proxyUrl(): string
    {
        return trim((string) config('bpjs.proxy_url', ''));
    }

    public static function ipWhitelist(): string
    {
        return trim((string) config('bpjs.ip_whitelist', ''));
    }

    public static function timeout(): int
    {
        return max(1, (int) config('bpjs.timeout', 8));
    }

    public static function connectTimeout(): int
    {
        return max(1, (int) config('bpjs.connect_timeout', 3));
    }

    /** Keadaan proxy dalam satu kalimat (URL tanpa kata sandi), untuk log/terminal/panduan. */
    public static function proxyUrlTersamar(): string
    {
        $proxy = self::proxyUrl();
        $tersamar = (string) preg_replace('~//([^:/@]+):[^@]*@~', '//$1:***@', $proxy);

        if (!self::proxyAktif()) {
            return $proxy === ''
                ? 'NONAKTIF (BPJS_PROXY_AKTIF=false, URL kosong) — langsung'
                : "NONAKTIF (BPJS_PROXY_AKTIF=false) — langsung; URL tersimpan {$tersamar}";
        }

        return $proxy === ''
            ? 'AKTIF tapi BPJS_PROXY_URL kosong — langsung'
            : "AKTIF lewat {$tersamar}";
    }
}
