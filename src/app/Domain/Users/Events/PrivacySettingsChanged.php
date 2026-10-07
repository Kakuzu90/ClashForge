<?php

namespace App\Domain\Users\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A user saved their privacy settings (specs/05 §2). Bases drops its cached feeds, whose cards
 * show the author's name, avatar and credited account by those settings (P3-03).
 */
final class PrivacySettingsChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $userId) {}
}
