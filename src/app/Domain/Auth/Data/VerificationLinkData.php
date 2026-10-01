<?php

namespace App\Domain\Auth\Data;

use App\Domain\Auth\Enums\EmailVerificationOutcome;

/**
 * What a verification link points at: its state, and the username it would confirm, so the
 * person can tell whether the account is theirs before pressing the button.
 */
final readonly class VerificationLinkData
{
    public function __construct(
        public EmailVerificationOutcome $outcome,
        public ?string $username,
    ) {}
}
