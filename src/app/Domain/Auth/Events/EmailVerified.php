<?php

namespace App\Domain\Auth\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The account holder confirmed their email (FR-AUTH-3, specs/05 §3); Notifications writes the
 * in-app "Email confirmed".
 */
final class EmailVerified implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
    ) {}
}
