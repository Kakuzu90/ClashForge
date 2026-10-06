<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\GameAssets\Data\GameAssetData;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A CoC account as a PlayerCard shows it (specs/18 §4), for any viewer allowed to see it. Game
 * values are null when the API stopped sending them ("not available", specs/23 §5). `clan` is null
 * outside a clan and when the owner hides it (`clanHidden`). `stale` dims the card: three 404s in a
 * row or data older than `coc.display.stale_hours`. `syncedAgeSeconds` is computed on the server,
 * so the server-rendered and hydrated age read the same.
 */
#[TypeScript]
class PlayerCardData extends Data
{
    public function __construct(
        public string $ulid,
        public string $tag,
        public string $name,
        public CocAccountStatus $status,
        public string $statusLabel,
        public ?int $townHallLevel,
        public ?GameAssetData $townHall,
        public ?int $builderHallLevel,
        public ?int $xpLevel,
        public ?int $trophies,
        public ?int $bestTrophies,
        public ?int $warStars,
        public ?string $leagueName,
        public ?GameAssetData $league,
        public ?AccountClanData $clan,
        public bool $clanHidden,
        public bool $featured,
        public bool $stale,
        public ?CarbonImmutable $syncedAt,
        public ?int $syncedAgeSeconds,
    ) {}
}
