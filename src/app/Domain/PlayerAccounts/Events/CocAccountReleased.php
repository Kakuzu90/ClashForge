<?php

namespace App\Domain\PlayerAccounts\Events;

use App\Domain\PlayerAccounts\Enums\ReleaseReason;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A tag left its owner and is claimable again (specs/13 §6). The row's `user_id` is already null,
 * so the event carries who held it. Consumers: the owner's "Tag released" notice (specs/16 §2);
 * Bases' `DropLostCredits`, which drops the credit on the holder's bases (P3-01).
 */
final class CocAccountReleased implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $accountId,
        public readonly int $userId,
        public readonly ReleaseReason $reason,
    ) {}
}
