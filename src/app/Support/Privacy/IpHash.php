<?php

namespace App\Support\Privacy;

/**
 * IPs are never stored raw (specs/07, specs/11 §5): a keyed hash still lets us match repeat
 * addresses without keeping the address itself.
 */
final class IpHash
{
    public static function of(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('platform.ip_hash_salt'));
    }
}
