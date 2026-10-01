<?php

namespace App\Domain\Users\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Who may open `/u/{username}` (FR-PROFILE-4, specs/07 `privacy_settings`).
 */
enum ProfileVisibility: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Public = 'public';
    case Members = 'members';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Everyone',
            self::Members => 'Signed-in members',
            self::Private => 'Only me',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Public => 'state-success',
            self::Members => 'state-info',
            self::Private => 'text-muted',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Public => 'Anyone can see your profile, including search engines if you allow it below.',
            self::Members => 'Only people signed in to Clash Commons can see your profile.',
            self::Private => 'Nobody else can see your profile. Visitors get a page not found.',
        };
    }
}
