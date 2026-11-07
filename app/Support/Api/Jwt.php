<?php

namespace App\Support\Api;

/**
 * Mesin JWT HS256 bersama (tanpa paket luar) — dipakai API Mobile JKN (TraitJWTRsiMadinah) dan API SIAP (JwtSiap).
 * Kunci TIDAK disimpan di sini: tiap pintu membawa rahasianya sendiri dari .env, supaya token satu pintu
 * tidak pernah bisa membuka pintu lain.
 *
 * encode() menghasilkan base64url standar. decode() menerima base64url maupun base64 biasa ('+', '/'), karena
 * encoder lama Mobile JKN tidak pernah mengganti '+' '/' — token lama yang masih beredar tetap sah.
 * decode() hanya memeriksa bentuk & tanda tangan; klaim (exp, iss, aud) diperiksa pemanggil sesuai konvensinya.
 */
final class Jwt
{
    public static function encode(array $payload, string $secret, array $header = ['typ' => 'JWT', 'alg' => 'HS256']): string
    {
        $kepala = self::b64(json_encode($header));
        $isi = self::b64(json_encode($payload));

        return "{$kepala}.{$isi}." . self::b64(self::tanda("{$kepala}.{$isi}", $secret));
    }

    /** @return array<string, mixed>|null payload bila bentuknya benar dan tanda tangan cocok */
    public static function decode(?string $token, string $secret): ?array
    {
        if ($secret === '' || !is_string($token) || substr_count($token, '.') !== 2) {
            return null;
        }
        [$kepala, $isi, $tanda] = explode('.', $token);
        $header = json_decode(self::unb64($kepala), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }
        if (!hash_equals(self::tanda("{$kepala}.{$isi}", $secret), self::unb64($tanda))) {
            return null;
        }
        $payload = json_decode(self::unb64($isi), true);

        return is_array($payload) ? $payload : null;
    }

    private static function tanda(string $data, string $secret): string
    {
        return hash_hmac('sha256', $data, $secret, true);
    }

    public static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function unb64(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
