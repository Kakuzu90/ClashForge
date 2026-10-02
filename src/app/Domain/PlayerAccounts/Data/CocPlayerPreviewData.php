<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\CocIntegration\Data\PlayerData;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The "Is this you?" card before attaching (specs/09 §9 step 1): enough to catch a typo, nothing
 * more. `stale` with `fetchedAt` when the API is down and this is the last good answer.
 */
#[TypeScript]
class CocPlayerPreviewData extends Data
{
    public function __construct(
        public string $tag,
        public string $name,
        public ?int $townHallLevel,
        public ?int $trophies,
        public ?int $expLevel,
        public ?string $clanName,
        public ?string $leagueName,
        public bool $stale,
        public ?CarbonImmutable $fetchedAt,
    ) {}

    public static function fromPlayer(PlayerData $player): self
    {
        return new self(
            tag: $player->tag->value,
            name: $player->name,
            townHallLevel: $player->townHallLevel,
            trophies: $player->trophies,
            expLevel: $player->expLevel,
            clanName: $player->clan?->name,
            leagueName: $player->league?->name,
            stale: $player->stale,
            fetchedAt: $player->fetchedAt,
        );
    }
}
