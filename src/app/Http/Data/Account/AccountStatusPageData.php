<?php

namespace App\Http\Data\Account;

use App\Domain\Auth\Data\AccountStatusData;
use App\Domain\Auth\Enums\UserStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Account/Suspended and Account/WriteBlocked: the holder's own status, the
 * user-visible reason and the end date (ISO 8601), never internal notes.
 */
#[TypeScript]
class AccountStatusPageData extends Data
{
    public function __construct(
        public UserStatus $status,
        public ?string $reason,
        public ?string $endsAt,
    ) {}

    public static function fromNotice(AccountStatusData $notice): self
    {
        return new self(
            status: $notice->status,
            reason: $notice->reason,
            endsAt: $notice->endsAt?->toIso8601String(),
        );
    }
}
