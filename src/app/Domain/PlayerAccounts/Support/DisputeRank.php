<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\Auth\Enums\Role;
use App\Domain\PlayerAccounts\Enums\DisputeRefusal;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Models\User;

/**
 * Who may decide a dispute beyond holding `resolve-disputes` (specs/04 §2 rule 1, owner decision
 * 2026-10-05, P2-17): an admin who strictly outranks both parties. The queue and the review page
 * give the same reason when one cannot.
 */
final class DisputeRank
{
    public static function outranksParties(User $admin, CocAccountDispute $dispute): bool
    {
        return collect([$dispute->claimant, $dispute->holder])->filter()
            ->every(fn (User $party): bool => $admin->role->outranks($party->role));
    }

    /**
     * Rule 1 against one user, for the P2-25 staff writes (the holder of a suspended row, the
     * sender of an image); nobody to outrank counts as allowed.
     */
    public static function outranks(User $admin, ?User $other): bool
    {
        return $other === null || $admin->role->outranks($other->role);
    }

    /**
     * Why this admin cannot decide the dispute at all, or null.
     */
    public static function blockedReason(User $admin, CocAccountDispute $dispute): ?string
    {
        if (! $dispute->status->isActive()) {
            return DisputeRefusal::Closed->label().'.';
        }
        if (self::outranksParties($admin, $dispute)) {
            return null;
        }

        return collect([$dispute->claimant, $dispute->holder])->filter()->contains(fn (User $party): bool => $party->role === Role::SuperAdmin)
            ? 'A super admin is part of this dispute, so it cannot be decided here.'
            : 'An admin is a party to this dispute, so only a super admin can decide it.';
    }
}
