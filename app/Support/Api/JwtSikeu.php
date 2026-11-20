<?php

namespace App\Support\Api;

/**
 * JWT untuk API SIKEU (aplikasi keuangan: cutoff & pembanding versi jurnal) — di atas mesin bersama App\Support\Api\Jwt.
 * Klaim: iss 'sirus', aud 'sikeu', sub (username akun API di tabel users), iat, exp (TTL dari config), jti acak.
 * Rahasia di .env SIKEU_API_JWT_SECRET (≥ 32 karakter), terpisah dari rahasia SIAP & Mobile JKN.
 */
class JwtSikeu
{
    public static function terbitkan(string $subjek): string
    {
        $kini = time();

        return Jwt::encode([
            'iss' => 'sirus', 'aud' => 'sikeu', 'sub' => $subjek,
            'iat' => $kini, 'exp' => $kini + self::ttl(), 'jti' => bin2hex(random_bytes(8)),
        ], self::secret());
    }

    /** @return array<string, mixed>|null payload bila sah & belum kedaluwarsa */
    public static function verifikasi(?string $token): ?array
    {
        $payload = Jwt::decode($token, self::secret());
        if ($payload === null || ($payload['iss'] ?? '') !== 'sirus' || ($payload['aud'] ?? '') !== 'sikeu'
            || !isset($payload['exp']) || (int) $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    public static function ttl(): int
    {
        return max(60, (int) config('services.sikeu.token_ttl', 3600));
    }

    public static function secret(): string
    {
        $secret = (string) config('services.sikeu.jwt_secret', '');

        return strlen($secret) >= 32 ? $secret : '';
    }
}
