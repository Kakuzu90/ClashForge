<?php

namespace App\Domain\Auth\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new account was created (specs/05 §3): Users creates its profile, privacy and stats rows.
 */
final class UserRegistered implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
    ) {}
}
