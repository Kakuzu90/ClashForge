<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * The path of a claim attempt (specs/07 `coc_account_claims.method`).
 */
enum ClaimMethod: string implements HasLabelAndColor
{
    use EnumHelpers;

    case ApiToken = 'api_token';
    case Dispute = 'dispute';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::ApiToken => 'In-game API token',
            self::Dispute => 'Dispute',
            self::Admin => 'Admin decision',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ApiToken => 'state-info',
            self::Dispute => 'state-warning',
            self::Admin => 'state-info',
        };
    }
}
