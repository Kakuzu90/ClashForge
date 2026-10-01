<?php

namespace App\Domain\Users\Services;

use Illuminate\Support\Facades\Cache;

/**
 * The one place that names the Users cache keys and what busts them (specs/21 §3, §4). Other
 * modules call it too: account verify/detach and base publish forget the profile.
 */
final class CacheInvalidator
{
    public static function profileKey(string $username): string
    {
        // citext usernames: /u/Chief and /u/chief share one entry.
        return 'profile:'.mb_strtolower($username);
    }

    /**
     * Bumped on every profile invalidation. A cached profile is stored with the version it was
     * built under, so a reader that started before a change and writes after it leaves an entry
     * nobody accepts.
     */
    public static function profileVersionKey(string $username): string
    {
        return self::profileKey($username).':version';
    }

    public static function privacyKey(int $userId): string
    {
        return "user:{$userId}:privacy";
    }

    public static function profileVersion(string $username): int
    {
        return (int) Cache::get(self::profileVersionKey($username), 0);
    }

    public static function profile(string $username): void
    {
        $versionKey = self::profileVersionKey($username);

        // The database store does not create a missing key on increment; the array store does.
        if (Cache::increment($versionKey) === false) {
            Cache::forever($versionKey, 1);
        }

        Cache::forget(self::profileKey($username));
    }

    public static function privacy(int $userId): void
    {
        Cache::forget(self::privacyKey($userId));
    }
}
