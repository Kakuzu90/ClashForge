<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Enums\UserStatus;
use App\Models\User;

/**
 * Finds accounts by their public identifier for other modules, which may not query `users`
 * themselves (specs/05 §2).
 */
class UserLookupService
{
    /**
     * Any name that could be stored in `users.username` (varchar(20), [a-z0-9_], case-insensitive).
     * Anything else cannot exist, and bytes such as %FF or %00 would make Postgres raise instead
     * of finding nothing.
     */
    private const STORABLE = '/^[A-Za-z0-9_]{1,20}$/';

    /**
     * The account behind `/u/{username}`, or null when there is none to show: unknown, soft
     * deleted, banned or pending deletion all look the same (specs/04 §1, specs/11 "Account
     * enumeration"). A suspended account stays listed. `username` is case-insensitive in the
     * column (citext / NOCASE).
     */
    public function findListed(string $username): ?User
    {
        if (preg_match(self::STORABLE, $username) !== 1) {
            return null;
        }

        return User::query()
            ->where('username', $username)
            ->whereNotIn('status', [UserStatus::Banned->value, UserStatus::PendingDeletion->value])
            ->first();
    }

    public function usernameOf(int $userId): ?string
    {
        $username = User::query()->whereKey($userId)->value('username');

        return is_string($username) ? $username : null;
    }
}
