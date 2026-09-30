<?php

namespace App\Domain\Auth\Data;

use App\Domain\Auth\Enums\UserStatus;
use Carbon\CarbonImmutable;

/**
 * What an account holder is told about their own status: the user-visible reason and end date.
 */
final readonly class AccountStatusData
{
    public function __construct(
        public UserStatus $status,
        public ?string $reason,
        public ?CarbonImmutable $endsAt,
    ) {}
}
