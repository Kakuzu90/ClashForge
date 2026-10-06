<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Models\User;

/**
 * Tags a staff member has a stake in, so they never review or decide a dispute over one (owner
 * decision 2026-10-06, P2-17): a row of theirs on the tag, any claim attempt on it (a past owner's
 * verification included), or a side in any dispute over it. Wider than the current claimant and
 * holder, so an admin who lost an earlier claim cannot read the holder's evidence later.
 */
final class DisputeStake
{
    /**
     * @return list<string> bare tags
     */
    public static function tags(User $staff): array
    {
        return array_values(array_unique([
            ...CocAccount::query()->where('user_id', $staff->id)->pluck('tag_normalized')->all(),
            ...CocAccountClaim::query()->where('user_id', $staff->id)->distinct()->pluck('tag_normalized')->all(),
            ...CocAccountDispute::query()->where(fn ($q) => $q->where('claimant_id', $staff->id)->orWhere('current_holder_id', $staff->id))
                ->distinct()->pluck('tag_normalized')->all(),
        ]));
    }

    public static function involves(User $staff, CocAccountDispute $dispute): bool
    {
        return $dispute->claimant_id === $staff->id
            || $dispute->current_holder_id === $staff->id
            || in_array($dispute->tag_normalized, self::tags($staff), true);
    }
}
