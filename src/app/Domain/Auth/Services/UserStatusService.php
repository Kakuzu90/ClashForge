<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Data\AccountStatusData;
use App\Domain\Auth\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Account status for enforcement (specs/04 §1), and the only writer of `users.status*`, called by
 * Moderation's SanctionService inside its transaction so a sanction applies on the next request.
 * Also the writer of `users.verified_accounts_count` (specs/08 §5), for PlayerAccounts.
 */
class UserStatusService
{
    /**
     * Puts the account under a suspension or ban. A ban also ends every session and cycles the
     * remember token, so it cannot sign in from any browser it already holds (specs/04 §1). The
     * acting admin's own session and cookie are untouched.
     */
    public function applySanction(User $user, UserStatus $status, string $reason, ?CarbonImmutable $until): void
    {
        $user->forceFill(['status' => $status, 'status_reason' => $reason, 'status_expires_at' => $until]);

        if ($status === UserStatus::Banned) {
            $user->forceFill(['remember_token' => Str::random(60)]);
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $user->save();
    }

    public function clearSanction(User $user): void
    {
        $user->forceFill(['status' => UserStatus::Active, 'status_reason' => null, 'status_expires_at' => null])->save();
    }

    /**
     * Locks the given accounts in id order for the rest of the caller's transaction, so two
     * verifications touching the same users serialise instead of racing or deadlocking (specs/13
     * §9). Call it before reading anything the count or the featured flag depends on.
     *
     * @param  list<int>  $userIds
     */
    public function lockAccounts(array $userIds): void
    {
        $ids = array_values(array_unique($userIds));
        sort($ids);

        User::query()->withTrashed()->whereKey($ids)->orderBy('id')->lockForUpdate()->pluck('id');
    }

    /**
     * Sets the verified-account count PlayerAccounts recounted inside its verification
     * transaction (specs/13 §3.1); the nightly reconcile repairs any drift.
     */
    public function syncVerifiedAccounts(int $userId, int $count): void
    {
        User::query()->withTrashed()->whereKey($userId)->update(['verified_accounts_count' => max(0, $count)]);
    }

    /**
     * Ids of accounts whose public content is hidden (specs/12 §7, specs/04 §1): suspended while
     * the suspension runs, banned (anonymised accounts included), or deletion requested. A subquery for another
     * module's `whereNotIn`, so a feed drops them in its own query and shows them again when the
     * status lifts, with nothing to keep in sync (P3-03).
     */
    public function hiddenAuthorIds(): Builder
    {
        $now = Date::now();

        return User::query()->withTrashed()->toBase()->select('id')
            ->where(fn (Builder $query) => $query
                ->whereIn('status', [UserStatus::Banned->value, UserStatus::PendingDeletion->value])
                ->orWhere(fn (Builder $q) => $q->where('status', UserStatus::Suspended->value)
                    ->where(fn (Builder $q) => $q->whereNull('status_expires_at')->orWhere('status_expires_at', '>', $now))));
    }

    public function effectiveStatus(User $user): UserStatus
    {
        return $user->effectiveStatus();
    }

    public function notice(User $user): AccountStatusData
    {
        $status = $this->effectiveStatus($user);

        return new AccountStatusData(
            status: $status,
            reason: $status === UserStatus::Active ? null : $user->status_reason,
            endsAt: $status === UserStatus::Active ? null : $user->status_expires_at,
        );
    }
}
