<?php

namespace App\Domain\PlayerAccounts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A user attached a tag as an unverified claim (specs/13 §3). Search indexes it later (specs/05 §2).
 */
final class CocAccountAttached implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $accountId,
        public readonly int $userId,
    ) {}
}
