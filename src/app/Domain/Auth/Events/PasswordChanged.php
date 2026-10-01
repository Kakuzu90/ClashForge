<?php

namespace App\Domain\Auth\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The account holder changed their password from settings, after the change committed. The
 * security email is Auth's own; Notifications adds the in-app copy (specs/16 §2).
 */
final class PasswordChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
    ) {}
}
