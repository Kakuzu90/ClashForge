<?php

namespace App\Domain\Moderation\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A suspension or ban ended: lifted by staff, or ran out (`expired`). Not dispatched when a
 * sanction is replaced by a new one; the new one's notice covers it.
 */
final class SanctionLifted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly int $sanctionId,
        public readonly bool $expired,
    ) {}
}
