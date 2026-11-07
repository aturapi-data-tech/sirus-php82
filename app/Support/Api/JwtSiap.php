<?php

namespace App\Support\Api;

/**
 * JWT untuk API SIAP (integrasi penggajian) — di atas mesin bersama App\Support\Api\Jwt.
 * Klaim: iss 'sirus', aud 'siap', sub (username akun API di tabel users), iat, exp (TTL dari config), jti acak.
 * Rahasia di .env SIAP_API_JWT_SECRET (≥ 32 karakter), terpisah dari rahasia Mobile JKN.
 */
class JwtSiap
{
    public static function terbitkan(string $subjek): string
    {
        $kini = time();

        return Jwt::encode([
            'iss' => 'sirus', 'aud' => 'siap', 'sub' => $subjek,
            'iat' => $kini, 'exp' => $kini + self::ttl(), 'jti' => bin2hex(random_bytes(8)),
        ], self::secret());
    }

    /** @return array<string, mixed>|null payload bila sah & belum kedaluwarsa */
    public static function verifikasi(?string $token): ?array
    {
        $payload = Jwt::decode($token, self::secret());
        if ($payload === null || ($payload['iss'] ?? '') !== 'sirus' || ($payload['aud'] ?? '') !== 'siap'
            || !isset($payload['exp']) || (int) $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    public static function ttl(): int
    {
        return max(60, (int) config('services.siap.token_ttl', 3600));
    }

    public static function secret(): string
    {
        $secret = (string) config('services.siap.jwt_secret', '');

        return strlen($secret) >= 32 ? $secret : '';
    }
}
