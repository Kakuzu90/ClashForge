<?php

namespace App\Domain\Moderation\Services;

use App\Domain\Moderation\Enums\SanctionType;
use App\Domain\Moderation\Models\UserSanction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * When a ban started, for other modules: a banned owner's tags are released once the ban has run
 * `coc.accounts.ban_release_days` (specs/13 §6). Only the active ban counts, so a lifted ban never
 * does and a second ban starts the wait again.
 */
class BanLookup
{
    /**
     * Ids of users whose active ban started at or before the cutoff, in id order.
     *
     * @return LazyCollection<int, int>
     */
    public function bannedSince(CarbonImmutable $cutoff): LazyCollection
    {
        return $this->query($cutoff)->select('user_id')->distinct()->orderBy('user_id')->cursor()
            ->map(fn (UserSanction $sanction): int => $sanction->user_id);
    }

    /**
     * The same check for one user, to repeat under their lock.
     */
    public function isBannedSince(int $userId, CarbonImmutable $cutoff): bool
    {
        return $this->query($cutoff)->where('user_id', $userId)->exists();
    }

    /**
     * @return Builder<UserSanction>
     */
    private function query(CarbonImmutable $cutoff): Builder
    {
        return UserSanction::query()->active()->where('type', SanctionType::Ban)->where('starts_at', '<=', $cutoff);
    }
}
