<?php

namespace App\Domain\Auth\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * When a user was last active, for other modules that weigh it, such as the account sync tiers
 * (specs/09 §6): the later of the last password sign-in and the newest session's last request.
 * A remembered browser keeps a session busy without signing in again, so both count.
 */
class UserActivityReader
{
    public function lastActiveAt(int $userId): ?CarbonImmutable
    {
        $signIn = User::query()->whereKey($userId)->value('last_login_at');
        $session = DB::table((string) config('session.table'))->where('user_id', $userId)->max('last_activity');

        $times = array_filter([
            $signIn === null ? null : CarbonImmutable::parse($signIn),
            $session === null ? null : CarbonImmutable::createFromTimestamp((int) $session),
        ]);

        return $times === [] ? null : max($times);
    }
}
