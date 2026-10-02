<?php

namespace App\Domain\PlayerAccounts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A tag became verified with its in-game token (specs/13 §3.1). Consumers arrive with their tasks:
 * the owner's notification (P2-12), the clan stub (P2-13), sync (P2-09) and search.
 */
final class CocAccountVerified implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $accountId,
        public readonly int $userId,
    ) {}
}
