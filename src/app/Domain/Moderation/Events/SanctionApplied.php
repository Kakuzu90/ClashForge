<?php

namespace App\Domain\Moderation\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A suspension or ban took effect (specs/05 §2). Held until the surrounding transaction commits,
 * even when a caller wraps the service in its own; the account's status is already set, so
 * consumers only react (notices, later search de-indexing).
 */
final class SanctionApplied implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly int $sanctionId,
    ) {}
}
