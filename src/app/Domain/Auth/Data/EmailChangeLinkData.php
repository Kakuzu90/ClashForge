<?php

namespace App\Domain\Auth\Data;

use App\Domain\Auth\Enums\EmailChangeOutcome;

/**
 * What an email-change link points at: its state, and for a pending change the account and the
 * masked new address, so the person can check both before pressing the button.
 */
final readonly class EmailChangeLinkData
{
    public function __construct(
        public EmailChangeOutcome $outcome,
        public ?string $username = null,
        public ?string $newEmail = null,
    ) {}
}
