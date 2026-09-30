<?php

namespace App\Domain\Auth\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * specs/04 §1: four fixed, hierarchical roles. Each level includes everything below it.
 */
enum Role: string implements HasLabelAndColor
{
    use EnumHelpers;

    case User = 'user';
    case Moderator = 'moderator';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Moderator => 'Moderator',
            self::Admin => 'Admin',
            self::SuperAdmin => 'Super admin',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::User => 'text-muted',
            self::Moderator => 'state-info',
            self::Admin => 'accent',
            self::SuperAdmin => 'brand',
        };
    }

    /**
     * This role holds everything `$other` holds.
     */
    public function includes(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /**
     * Staff may act only on accounts they strictly outrank; same-level cases escalate (specs/04 §2 rule 1).
     */
    public function outranks(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    public function isStaff(): bool
    {
        return $this->includes(self::Moderator);
    }

    private function rank(): int
    {
        return match ($this) {
            self::User => 0,
            self::Moderator => 1,
            self::Admin => 2,
            self::SuperAdmin => 3,
        };
    }
}
