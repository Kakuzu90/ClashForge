<?php

namespace App\Domain\Search\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * What a search covers (FR-SEARCH-1): everything, grouped, or one kind of result.
 */
enum SearchType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case All = 'all';
    case Bases = 'bases';
    case Players = 'players';
    case Accounts = 'accounts';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            self::Bases => 'Bases',
            self::Players => 'Players',
            self::Accounts => 'Accounts',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }

    /**
     * The result kinds this search reads, in the order the page shows them.
     *
     * @return list<self>
     */
    public function sources(): array
    {
        return $this === self::All ? [self::Bases, self::Players, self::Accounts] : [$this];
    }
}
