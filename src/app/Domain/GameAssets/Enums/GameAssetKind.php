<?php

namespace App\Domain\GameAssets\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * What a resolved asset identifies; decides the placeholder shape when there is no image.
 */
enum GameAssetKind: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Unit = 'unit';
    case TownHall = 'town_hall';
    case League = 'league';
    case ClanBadge = 'clan_badge';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'Unit',
            self::TownHall => 'Town Hall',
            self::League => 'League',
            self::ClanBadge => 'Clan badge',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }
}
