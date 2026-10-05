<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\Audit\Data\AuditActorData;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Services\UserStatusService;
use App\Domain\Moderation\Services\BanLookup;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\ReleaseReason;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * `coc:release-banned-tags` (specs/13 §6, specs/20 §3): a banned owner's tags are released once
 * the active ban has run `coc.accounts.ban_release_days`, the delay that lets an overturned ban
 * restore them. A `disputed` row waits for its dispute, which continues without the holder (specs/13
 * §9), and a `suspended` row stays with staff. Safe to run twice: a released row is not the
 * user's any more.
 */
class BannedTagRelease
{
    /** Released after a ban; unlike AccountDeletionHooks::ON_DELETION a disputed row waits for its dispute. */
    private const ON_BAN = [CocAccountStatus::Unverified, CocAccountStatus::Verified];

    public function __construct(
        private readonly BanLookup $bans,
        private readonly UserStatusService $users,
        private readonly AccountOwnershipService $ownership,
    ) {}

    /**
     * @return int the number of rows released
     */
    public function run(): int
    {
        $cutoff = CarbonImmutable::instance(Date::now())->subDays((int) config('coc.accounts.ban_release_days'));
        $released = 0;

        foreach ($this->bans->bannedSince($cutoff) as $userId) {
            $released += $this->releaseFor($userId, $cutoff);
        }

        return $released;
    }

    private function releaseFor(int $userId, CarbonImmutable $cutoff): int
    {
        return DB::transaction(function () use ($userId, $cutoff): int {
            // The user's rows, then the user: the order verification and sanctions lock in.
            $rows = CocAccount::query()->where('user_id', $userId)->whereIn('status', self::ON_BAN)->orderBy('id')->lockForUpdate()->get();
            if ($rows->isEmpty()) {
                return 0;
            }
            $this->users->lockAccounts([$userId]);
            $owner = User::query()->withTrashed()->find($userId);

            // Again under the lock: the ban may have been lifted since the list was read.
            if ($owner === null || $owner->status !== UserStatus::Banned || ! $this->bans->isBannedSince($userId, $cutoff)) {
                return 0;
            }

            foreach ($rows as $row) {
                $this->ownership->release($row, $owner, ReleaseReason::Ban, AuditActorData::scheduler());
            }

            return $rows->count();
        });
    }
}
