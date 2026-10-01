<?php

namespace App\Domain\Auth\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A sign-in from a browser that had not signed in to the account before (specs/11, specs/16 §2).
 * `device` is the coarse label from the user agent, `country` a display name or null.
 */
final class UnrecognisedDeviceSignedIn implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly string $device,
        public readonly ?string $country,
    ) {}
}
