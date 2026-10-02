<?php

namespace App\Domain\CocIntegration\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * What a `sync_states` row schedules (specs/07). Clans join with clan sync (P4-01).
 */
enum SyncResourceType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case CocAccount = 'coc_account';
    case Clan = 'clan';

    public function label(): string
    {
        return match ($this) {
            self::CocAccount => 'CoC account',
            self::Clan => 'Clan',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }
}
