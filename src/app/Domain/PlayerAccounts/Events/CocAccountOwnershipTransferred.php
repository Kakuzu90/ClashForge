<?php

namespace App\Domain\PlayerAccounts\Events;

use App\Domain\PlayerAccounts\Enums\VerificationMethod;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A tag's verified ownership moved to another user: a token verification superseded the holder
 * (specs/13 §3.1), or an admin decided a dispute (P2-03). `accountId` is the new owner's row. The
 * previous holder is told (P2-12).
 */
final class CocAccountOwnershipTransferred implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $accountId,
        public readonly int $fromUserId,
        public readonly int $toUserId,
        public readonly VerificationMethod $method,
    ) {}
}
