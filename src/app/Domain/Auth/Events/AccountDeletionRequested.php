<?php

namespace App\Domain\Auth\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A user asked for their account to be deleted (FR-AUTH-9); their public content is hidden from
 * now on. Bases drops its cached feeds so their bases leave at once (P3-03).
 */
final class AccountDeletionRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $userId) {}
}
