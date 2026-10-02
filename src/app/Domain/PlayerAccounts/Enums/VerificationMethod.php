<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * How a tag became verified (specs/07 `coc_accounts.verification_method`).
 */
enum VerificationMethod: string implements HasLabelAndColor
{
    use EnumHelpers;

    case ApiToken = 'api_token';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::ApiToken => 'In-game API token',
            self::Admin => 'Admin decision',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ApiToken => 'state-success',
            self::Admin => 'state-info',
        };
    }
}
