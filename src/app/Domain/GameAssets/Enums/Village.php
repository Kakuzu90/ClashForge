<?php

namespace App\Domain\GameAssets\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Values match the API's `village` field on units (specs/09 §8).
 */
enum Village: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Home = 'home';
    case Builder = 'builderBase';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Home Village',
            self::Builder => 'Builder Base',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }
}
