<?php

namespace App\Domain\Auth\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * Account status, orthogonal to role (specs/04 §1).
 */
enum UserStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Active = 'active';
    case Restricted = 'restricted';
    case Suspended = 'suspended';
    case Banned = 'banned';
    case PendingDeletion = 'pending_deletion';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Restricted => 'Restricted',
            self::Suspended => 'Suspended',
            self::Banned => 'Banned',
            self::PendingDeletion => 'Deletion requested',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'state-success',
            self::Restricted => 'state-warning',
            self::Suspended, self::Banned => 'state-danger',
            self::PendingDeletion => 'text-muted',
        };
    }

    /**
     * A timed restriction or suspension whose end has passed no longer applies, even before the
     * expiry job clears the column (specs/23 §7).
     */
    public function effective(?CarbonImmutable $expiresAt): self
    {
        $timed = $this === self::Restricted || $this === self::Suspended;

        if ($timed && $expiresAt !== null && $expiresAt->lessThanOrEqualTo(Date::now())) {
            return self::Active;
        }

        return $this;
    }

    public function canLogIn(): bool
    {
        return $this !== self::Banned;
    }

    /**
     * Profile and settings writes: open to restricted accounts.
     */
    public function allowsAccountWrites(): bool
    {
        return $this === self::Active || $this === self::Restricted;
    }

    /**
     * Staff read abilities (admin area, queues, logs): every status that can read (specs/04 §1).
     */
    public function allowsStaffReads(): bool
    {
        return $this === self::Active || $this === self::Restricted || $this === self::PendingDeletion;
    }

    /**
     * Staff actions on content and accounts: active accounts only, so a sanctioned moderator keeps
     * the role but cannot act while the sanction lasts.
     */
    public function allowsStaffActions(): bool
    {
        return $this === self::Active;
    }

    /**
     * Uploads, publishing, commenting, applying, messaging (specs/04 §3, specs/12 §6).
     */
    public function allowsContentWrites(): bool
    {
        return $this === self::Active;
    }
}
