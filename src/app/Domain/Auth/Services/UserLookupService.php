<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\UsernameHistory;
use App\Models\User;
use Illuminate\Support\Facades\Date;

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

    /**
     * The listed account that changed away from `$username` inside the hold (FR-PROFILE-7), for
     * the `/u/{old}` redirect; the latest release wins. Null once the hold is over, for a name
     * someone holds now (whatever their status), and for names deleted accounts keep forever.
     */
    public function renamedFrom(string $username): ?User
    {
        if (preg_match(self::STORABLE, $username) !== 1 || User::withTrashed()->where('username', $username)->exists()) {
            return null;
        }

        $since = Date::now()->subDays((int) config('platform.auth.username_reservation_days'));
        $release = UsernameHistory::query()
            ->where('username', $username)
            ->where('reserved_forever', false)
            ->where('released_at', '>', $since)
            ->latest('released_at')
            ->first();

        return $release === null ? null : User::query()
            ->whereKey($release->user_id)
            ->whereNotIn('status', [UserStatus::Banned->value, UserStatus::PendingDeletion->value])
            ->first();
    }

    public function usernameOf(int $userId): ?string
    {
        $username = User::query()->whereKey($userId)->value('username');

        return is_string($username) ? $username : null;
    }
}
