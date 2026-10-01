<?php

namespace App\Domain\Auth\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * The `known_devices` cookie (specs/11 "Authentication attacks"): the accounts that signed in from
 * this browser, newest last. The cookie is encrypted like every other, so the ULIDs in it are not
 * readable or forgeable by the browser. A security cookie, strictly necessary (NFR-PRIV-3).
 */
final class KnownDevices
{
    public const COOKIE = 'known_devices';

    public static function knows(Request $request, string $userUlid): bool
    {
        return in_array($userUlid, self::read($request), true);
    }

    public static function remember(Request $request, string $userUlid): void
    {
        $accounts = array_values(array_filter(self::read($request), fn (string $ulid): bool => $ulid !== $userUlid));
        $accounts[] = $userUlid;
        $accounts = array_slice($accounts, -1 * (int) config('platform.auth.known_devices_max'));

        Cookie::queue(self::COOKIE, (string) json_encode($accounts), (int) config('platform.auth.known_device_days') * 24 * 60);
    }

    /**
     * @return list<string>
     */
    private static function read(Request $request): array
    {
        $raw = $request->cookie(self::COOKIE);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
